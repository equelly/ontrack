<?php

namespace App\Services;

use App\Models\AiAlert;
use App\Models\Miner;
use App\Models\Order;
use App\Models\ServicePost;
use App\Models\Truck;
use App\Models\TruckPlannedTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MaintenancePlannerService — балансировка парка через ТО/ремонты.
 *
 * Запускается в начале каждой смены (7:30 и 19:30) через cron.
 *
 * Логика:
 *   1. Для каждого активного забоя — получить recommended (требуемое кол-во самосвалов)
 *      и current (фактическое)
 *   2. Определить перекос:
 *      - current > recommended → ИЗБЫТОК самосвалов → можно провести ТО/ремонт грузовиков
 *      - current < recommended → ДЕФИЦИТ самосвалов → можно остановить экскаватор для ТО/ремонта
 *   3. Проанализировать очередь заявок (orders) с оценкой времени (estimated_minutes)
 *   4. Проверить свободные сервисные посты (ServicePost)
 *   5. Сформировать рекомендации → AiAlert типа TYPE_BALANCE_SUGGESTION
 *
 * Сценарии:
 *   A: Перекос — избыток или дефицит самосвалов → рекомендации по ТО/ремонту
 *   B: Накопление работ — мелкие ремонты во время крупных
 *   C: Критическая заявка — приоритет над плановым ТО (с учётом свободных постов)
 */
class MaintenancePlannerService
{
    /**
     * Главный метод — анализ баланса и создание AiAlert.
     *
     * @return int — 1 если алерт создан/обновлён, 0 если нет
     */
    public function analyzeBalance(): int
    {
        Log::info('MaintenancePlanner: analyzeBalance START');

        // === 1. Анализ забоев ===
        $minersAnalysis = $this->analyzeMiners();

        // === 2. Анализ очереди заявок ===
        $ordersAnalysis = $this->analyzeOrders();

        // === 3. Анализ свободных постов ===
        $postsAnalysis = $this->analyzeServicePosts();

        // === 4. Формирование рекомендаций ===
        $recommendations = $this->buildRecommendations($minersAnalysis, $ordersAnalysis, $postsAnalysis);

        if (empty($recommendations)) {
            // Нет рекомендаций — закрываем существующий алерт если был
            $existing = AiAlert::active()
                ->where('type', AiAlert::TYPE_BALANCE_SUGGESTION)
                ->first();
            if ($existing) {
                $existing->resolve();
                Log::info('MaintenancePlanner: алерт балансировки закрыт (нет рекомендаций)');
            }
            return 0;
        }

        // === 5. Создаём/обновляем AiAlert ===
        $message = "Рекомендации по балансировке парка на текущую смену:\n\n";
        $message .= implode("\n", $recommendations);

        $alertData = [
            'miners_analysis'  => $minersAnalysis,
            'orders_analysis'   => $ordersAnalysis,
            'posts_analysis'    => $postsAnalysis,
            'recommendations'   => $recommendations,
            'shift_time'        => now()->format('H:i'),
        ];

        $existing = AiAlert::active()
            ->where('type', AiAlert::TYPE_BALANCE_SUGGESTION)
            ->first();

        if ($existing) {
            $existing->update([
                'severity' => AiAlert::SEVERITY_INFO,
                'title'    => 'Балансировка парка: ' . count($recommendations) . ' рекомендаций',
                'message'  => $message,
                'data'     => $alertData,
            ]);
            Log::info('MaintenancePlanner: алерт обновлён', ['recs_count' => count($recommendations)]);
        } else {
            AiAlert::create([
                'type'        => AiAlert::TYPE_BALANCE_SUGGESTION,
                'severity'    => AiAlert::SEVERITY_INFO,
                'title'       => 'Балансировка парка: ' . count($recommendations) . ' рекомендаций',
                'message'     => $message,
                'data'        => $alertData,
                'entity_type' => 'fleet',
                'entity_id'   => null,
                'status'      => AiAlert::STATUS_NEW,
            ]);
            Log::info('MaintenancePlanner: алерт создан', ['recs_count' => count($recommendations)]);
        }

        return 1;
    }

    /**
     * Анализ забоев — для каждого активного забоя получить recommended и current.
     */
    protected function analyzeMiners(): array
    {
        $miners = Miner::where('status', Miner::STATUS_ACTIVE)
            ->where('status', Miner::STATUS_ACTIVE)
            ->get();

        $result = [];
        $totalRecommended = 0;
        $totalCurrent = 0;

        foreach ($miners as $miner) {
            $data = $miner->getRecommendedTruckCount();
            if (!$data) {
                continue;
            }

            $recommended = $data['recommended'] ?? 0;
            $current = $data['current'] ?? 0;
            $diff = $current - $recommended;

            $result[] = [
                'miner_id'    => $miner->id,
                'miner_name'   => $miner->name_miner,
                'recommended' => $recommended,
                'current'     => $current,
                'diff'        => $diff,
                'balance'     => $data['balance'] ?? '—',
            ];

            $totalRecommended += $recommended;
            $totalCurrent += $current;
        }

        return [
            'miners'           => $result,
            'total_recommended' => $totalRecommended,
            'total_current'     => $totalCurrent,
            'total_diff'        => $totalCurrent - $totalRecommended,
        ];
    }

    /**
     * Анализ очереди заявок — активные с оценкой времени.
     */
    protected function analyzeOrders(): array
    {
        $activeOrders = Order::whereIn('status', [
            Order::STATUS_NEW,
            Order::STATUS_IN_PROGRESS,
            Order::STATUS_PAUSED,
        ])
            ->whereNotNull('estimated_minutes')
            ->orderByRaw("CASE priority
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
                ELSE 5 END")
            ->with(['mashine', 'inspector'])
            ->get();

        $totalEstimatedMinutes = $activeOrders->sum('estimated_minutes');
        $criticalCount = $activeOrders->where('priority', Order::PRIORITY_CRITICAL)->count();

        return [
            'active_with_estimate' => $activeOrders->count(),
            'total_estimated_hours' => round($totalEstimatedMinutes / 60, 1),
            'critical_count'        => $criticalCount,
            'orders'                => $activeOrders->map(fn($o) => [
                'id'              => $o->id,
                'mashine_number'  => $o->mashine?->number,
                'work_type'       => $o->workTypeLabel(),
                'priority'        => $o->priorityLabel(),
                'estimated_time'  => $o->estimatedTimeFormatted(),
                'estimated_minutes' => $o->estimated_minutes,
                'status'          => $o->statusLabel(),
            ])->toArray(),
        ];
    }

    /**
     * Анализ свободных сервисных постов.
     */
    protected function analyzeServicePosts(): array
    {
        $posts = ServicePost::with('currentTruck')->get();

        $free = $posts->filter(fn($p) => !$p->currentTruck);
        $occupied = $posts->filter(fn($p) => $p->currentTruck);

        return [
            'total'    => $posts->count(),
            'free'     => $free->count(),
            'occupied' => $occupied->count(),
            'free_types' => $free->pluck('type')->unique()->values()->toArray(),
        ];
    }

    /**
     * Формирование рекомендаций на основе анализа.
     */
    protected function buildRecommendations(array $miners, array $orders, array $posts): array
    {
        $recs = [];

        // === Сценарий A: Перекос ===
        $totalDiff = $miners['total_diff'] ?? 0;

        if ($totalDiff > 0) {
            // ИЗБЫТОК самосвалов — можно отправить на ТО/ремонт
            $recs[] = sprintf(
                "📊 Избыток самосвалов: на забоях %d шт, требуется %d (избыток +%d). " .
                "Можно отправить %d самосвал(ов) на ТО или ремонт без ущерба для производства.",
                $miners['total_current'],
                $miners['total_recommended'],
                $totalDiff,
                $totalDiff
            );

            // Если есть заявки с оценкой времени — показываем какие можно сделать
            if ($orders['active_with_estimate'] > 0 && $posts['free'] > 0) {
                $recs[] = sprintf(
                    "🔧 Свободно постов: %d. Заявок с оценкой времени: %d (≈%s ч). " .
                    "Можно провести работы в порядке приоритета.",
                    $posts['free'],
                    $orders['active_with_estimate'],
                    $orders['total_estimated_hours']
                );
            }
        } elseif ($totalDiff < 0) {
            // ДЕФИЦИТ самосвалов — можно остановить экскаватор для ТО/ремонта
            $deficit = abs($totalDiff);
            $recs[] = sprintf(
                "📊 Дефицит самосвалов: на забоях %d шт, требуется %d (дефицит -%d). " .
                "Можно остановить %d экскаватор(ов) для ТО или ремонта — нехватка самосвалов " .
                "компенсируется снижением нагрузки на оставшиеся забои.",
                $miners['total_current'],
                $miners['total_recommended'],
                $deficit,
                min(1, $deficit) // хотя бы 1 экскаватор
            );
        }

        // === Сценарий B: Накопление работ ===
        if ($orders['active_with_estimate'] >= 3 && $orders['total_estimated_hours'] > 2) {
            $criticalOrders = array_filter($orders['orders'], fn($o) =>
                in_array(strtolower($o['priority']), ['критический', 'critical'])
            );

            if (!empty($criticalOrders)) {
                $first = reset($criticalOrders);
                $recs[] = sprintf(
                    "⚡ Критическая заявка #%d (%s, %s) — оценка %s. " .
                    "Проведите её в первую очередь, параллельно можно начать " .
                    "мелкие работы на других постах.",
                    $first['id'],
                    $first['mashine_number'] ?? '—',
                    $first['work_type'],
                    $first['estimated_time']
                );
            }

            // Если есть несколько мелких заявок (< 60 мин) — можно сгруппировать
            $quickOrders = array_filter($orders['orders'], fn($o) =>
                ($o['estimated_minutes'] ?? 0) > 0 && ($o['estimated_minutes'] ?? 0) <= 60
            );
            if (count($quickOrders) >= 2 && $posts['free'] >= 1) {
                $recs[] = sprintf(
                    "📋 Накоплено %d мелких работ (≤60 мин каждая). " .
                    "Их можно выполнить последовательно на одном свободном посту за %s ч.",
                    count($quickOrders),
                    round(array_sum(array_column($quickOrders, 'estimated_minutes')) / 60, 1)
                );
            }
        }

        // === Сценарий C: Нет свободных постов ===
        if ($orders['active_with_estimate'] > 0 && $posts['free'] == 0) {
            $recs[] = sprintf(
                "⚠ Все %d сервисных постов заняты. Заявок в очереди: %d. " .
                "Дождитесь освобождения поста перед началом новых работ.",
                $posts['total'],
                $orders['active_with_estimate']
            );
        }

        // === Детализация по забоям с перекосом ===
        foreach ($miners['miners'] as $m) {
            if ($m['diff'] > 0 && $m['diff'] >= 2) {
                $recs[] = sprintf(
                    "📍 Забой «%s»: самосвалов %d (нужно %d, избыток +%d). " .
                    "Часть можно отправить на ТО.",
                    $m['miner_name'],
                    $m['current'],
                    $m['recommended'],
                    $m['diff']
                );
            } elseif ($m['diff'] < 0 && abs($m['diff']) >= 2) {
                $recs[] = sprintf(
                    "📍 Забой «%s»: самосвалов %d (нужно %d, дефицит %d). " .
                    "Можно остановить экскаватор для ТО/ремонта.",
                    $m['miner_name'],
                    $m['current'],
                    $m['recommended'],
                    $m['diff']
                );
            }
        }

        return $recs;
    }
}