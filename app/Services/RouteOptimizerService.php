<?php

namespace App\Services;

use App\Models\Miner;
use App\Models\Dump;
use App\Models\Zone;
use App\Models\MiningOrder;
use App\Models\MinerDumpDistance;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * RouteOptimizerService - оптимизация маршрутов
 * 
 * Режимы работы:
 * - auto: система автоматически выбирает лучшие маршруты
 * - manual: диспетчер вручную управляет активностью маршрутов
 *
 * Алгоритм выбора (подход C — гибрид):
 *   1. Деактивируем все маршруты (active=false)
 *   2. Сбрасываем wrr_cursor (новая смена/оптимизация)
 *   3. Для каждого активного забоя находим все подходящие маршруты (забой→отвал):
 *      - у забоя есть текущая порода
 *      - на отвалe есть доступная зона (delivery=true, volume<capacity, принимает породу
 *        с учётом fallback-логики: руда_ЦПТ → руда → руда_Sera)
 *      - есть расстояние (distance_km из MiningOrder ИЛИ из miner_dump_distances)
 *   4. Рассчитываем score = (distance × 10) × max(volume_in_zones/1000, 0.1)
 *      Меньше score = лучше (короткое расстояние + мало заполненные зоны)
 *   5. Распределяем по раундам (Round Robin):
 *      Раунд 1: каждому забою — его лучший маршрут
 *      Раунд 2: каждому забою — следующий лучший (резервный)
 *      ...
 *      Количество раундов = динамическое (минимум из: N доступных пар, 5 максимум)
 *      В каждом раунде один отвал обслуживает только один забой (балансировка)
 *   6. Активируем выбранные маршруты
 *
 * ВАЖНО: optimize() вызывается только при:
 *   - начале новой смены (через ShiftPlanningService)
 *   - добавлении/удалении забоя или отвала (через MasterPanel)
 *   - ручном запуске диспетчером (кнопка «Оптимизировать»)
 *   НЕ вызывается при каждом событии изменения зоны/породы — для этого есть syncAllOrders()
 */
class RouteOptimizerService
{
    /**
     * Максимальное количество раундов (защита от избыточной активации).
     */
    const MAX_ROUNDS = 5;

    /**
     * Главная функция - оптимизировать маршруты (только в автоматическом режиме)
     */
    public function optimize(): array
    {
        // Проверяем режим
        if (SystemSetting::isManualMode()) {
            Log::info('RouteOptimizerService: ручной режим, оптимизация пропущена');
            return [
                'error' => 'Ручной режим активации маршрутов. Используйте ручное управление.',
                'mode' => 'manual',
            ];
        }
        
        Log::info('=== RouteOptimizerService::optimize START ===');
        
        $result = [
            'rounds' => [],
            'activated' => [],
            'deactivated' => [],
            'stats' => [],
            'mode' => 'auto',
        ];
        
        // 1. Деактивировать все маршруты
        MiningOrder::query()->update(['active' => false]);
        Log::info('Все маршруты деактивированы');
        
        // 2. Сбросить WRR-курсоры (новая оптимизация = новая смена)
        MiningOrder::query()->update(['wrr_cursor' => 0, 'last_assigned_at' => null]);
        Log::info('WRR-курсоры сброшены');
        
        // 3. Получить все работающие забои
        $activeMiners = Miner::where('active', true)
            ->where('status', Miner::STATUS_ACTIVE)
            ->with('currentRock')
            ->get();
        Log::info("Работающих забоев: {$activeMiners->count()}");
        
        if ($activeMiners->isEmpty()) {
            return $result;
        }
        
        // 4. Получить все маршруты с рассчитанным score
        $routes = $this->getAllRoutesWithScore($activeMiners);
        Log::info("Маршрутов с score: {$routes->count()}");
        
        if ($routes->isEmpty()) {
            return $result;
        }
        
        // 5. Распределить по раундам (динамическое количество, макс. 5)
        $assignments = $this->assignByRounds($routes, $activeMiners);
        
        // 6. Активировать выбранные маршруты
        foreach ($assignments as $round => $roundAssignments) {
            $result['rounds'][$round] = count($roundAssignments);
            
            foreach ($roundAssignments as $assignment) {
                MiningOrder::where('miner_id', $assignment['miner_id'])
                    ->where('dump_id', $assignment['dump_id'])
                    ->update(['active' => true]);
                
                $result['activated'][] = [
                    'miner_id' => $assignment['miner_id'],
                    'dump_id' => $assignment['dump_id'],
                    'round' => $round,
                    'score' => $assignment['score'],
                ];
            }
        }
        
        // 7. Статистика
        $result['stats'] = [
            'total_miners' => $activeMiners->count(),
            'total_routes' => MiningOrder::count(),
            'active_routes' => MiningOrder::where('active', true)->count(),
            'rounds_count' => count($assignments),
        ];
        
        Log::info('=== RouteOptimizerService::optimize END ===', $result['stats']);
        
        return $result;
    }
    
    /**
     * Получить текущий режим
     */
    public function getMode(): string
    {
        return SystemSetting::getRouteActivationMode();
    }
    
    /**
     * Переключить режим
     */
    public function setMode(string $mode): array
    {
        if (!in_array($mode, ['auto', 'manual'])) {
            return ['error' => 'Неверный режим. Используйте: auto или manual'];
        }
        
        SystemSetting::setRouteActivationMode($mode);
        Log::info("Режим активации маршрутов изменён на: {$mode}");
        
        return [
            'mode' => $mode,
            'message' => $mode === 'auto' 
                ? 'Автоматический режим включён. Запустите routes:optimize для оптимизации.'
                : 'Ручной режим включён. Управляйте маршрутами вручную.',
        ];
    }
    
    /**
     * Вручную активировать маршрут
     */
    public function activateRoute(int $minerId, int $dumpId): array
    {
        $order = MiningOrder::where('miner_id', $minerId)
            ->where('dump_id', $dumpId)
            ->first();
        
        if (!$order) {
            return ['error' => 'Маршрут не найден'];
        }
        
        // Проверяем доступность зон (с учётом fallback пород)
        $miner = Miner::with('currentRock')->find($minerId);
        if ($miner && $miner->currentRock) {
            $zones = $this->getAvailableZonesForRock($dumpId, $miner->currentRock->id);
            if ($zones->isEmpty()) {
                return ['error' => 'Нет доступных зон для этого маршрута'];
            }
        }
        
        $order->update(['active' => true]);
        Log::info("Маршрут активирован вручную: забой {$minerId} → отвал {$dumpId}");
        
        return [
            'success' => true,
            'miner_id' => $minerId,
            'dump_id' => $dumpId,
        ];
    }
    
    /**
     * Вручную деактивировать маршрут
     */
    public function deactivateRoute(int $minerId, int $dumpId): array
    {
        $updated = MiningOrder::where('miner_id', $minerId)
            ->where('dump_id', $dumpId)
            ->update(['active' => false]);
        
        if ($updated === 0) {
            return ['error' => 'Маршрут не найден'];
        }
        
        Log::info("Маршрут деактивирован вручную: забой {$minerId} → отвал {$dumpId}");
        
        return [
            'success' => true,
            'miner_id' => $minerId,
            'dump_id' => $dumpId,
        ];
    }
    
    /**
     * Изменить вес маршрута
     */
    public function setRouteWeight(int $minerId, int $dumpId, int $weight): array
    {
        $order = MiningOrder::where('miner_id', $minerId)
            ->where('dump_id', $dumpId)
            ->first();
        
        if (!$order) {
            return ['error' => 'Маршрут не найден'];
        }
        
        $order->update(['weight' => max(1, min(1000, $weight))]);
        Log::info("Вес маршрута изменён: забой {$minerId} → отвал {$dumpId}, вес {$weight}");
        
        return [
            'success' => true,
            'miner_id' => $minerId,
            'dump_id' => $dumpId,
            'weight' => $order->weight,
        ];
    }
    
    /**
     * Получить все маршруты с информацией для отображения
     */
    public function getAllRoutesWithInfo(): Collection
    {
        $activeMiners = Miner::where('active', true)->with('currentRock')->get();
        $minerIds = $activeMiners->pluck('id')->toArray();
        
        return MiningOrder::whereIn('miner_id', $minerIds)
            ->with(['miner.currentRock', 'dump.zones'])
            ->get()
            ->map(function($order) {
                $miner = $order->miner;
                $rock = $miner?->currentRock;
                
                // Сначала берём distance_km из самого MiningOrder, потом из miner_dump_distances
                $distance = $order->distance_km ?? MinerDumpDistance::where('miner_id', $order->miner_id)
                    ->where('dump_id', $order->dump_id)
                    ->value('distance_km');
                
                // Доступные зоны (с учётом fallback пород)
                $availableZones = $rock ? $this->getAvailableZonesForRock($order->dump_id, $rock->id) : collect();
                
                // Score
                $score = null;
                if ($availableZones->isNotEmpty() && $distance) {
                    $volumeInZones = $availableZones->sum('volume');
                    $score = ($distance * 10) * max($volumeInZones / 1000, 0.1);
                }
                
                return [
                    'id' => $order->id,
                    'miner_id' => $order->miner_id,
                    'miner_name' => $miner?->name_miner,
                    'dump_id' => $order->dump_id,
                    'dump_name' => $order->dump?->name_dump,
                    'rock_name' => $rock?->name_rock,
                    'active' => $order->active,
                    'weight' => $order->weight,
                    'wrr_cursor' => $order->wrr_cursor,
                    'distance' => $distance,
                    'score' => $score ? round($score, 2) : null,
                    'available_zones' => $availableZones->count(),
                    'zones_available' => $availableZones->isNotEmpty(),
                ];
            })
            ->sortBy('miner_id');
    }
    
    /**
     * Получить все маршруты с рассчитанным score.
     *
     * ВАЖНО: использует distance_km из самого MiningOrder (если задан),
     * иначе из таблицы miner_dump_distances. Раньше требовалось наличие записи
     * в miner_dump_distances — теперь нет.
     *
     * ВАЖНО: использует fallback-логику пород через getAvailableZonesForRock().
     */
    protected function getAllRoutesWithScore(Collection $activeMiners): Collection
    {
        $minerIds = $activeMiners->pluck('id')->toArray();
        
        $orders = MiningOrder::whereIn('miner_id', $minerIds)
            ->with(['dump.zones.rocks'])
            ->get();
        
        $routes = collect();
        
        foreach ($orders as $order) {
            $miner = $activeMiners->firstWhere('id', $order->miner_id);
            
            if (!$miner || !$miner->currentRock) {
                continue;
            }
            
            $rockId = $miner->currentRock->id;
            
            // Сначала distance_km из MiningOrder, потом из miner_dump_distances
            $distance = $order->distance_km;
            if (!$distance) {
                $distance = MinerDumpDistance::where('miner_id', $order->miner_id)
                    ->where('dump_id', $order->dump_id)
                    ->value('distance_km');
            }
            
            if (!$distance) {
                // Нет расстояния — пропускаем (нельзя рассчитать score)
                Log::debug("Маршрут {$order->id} пропущен: нет distance_km", [
                    'miner_id' => $order->miner_id,
                    'dump_id' => $order->dump_id,
                ]);
                continue;
            }
            
            // Доступные зоны с учётом fallback пород
            $availableZones = $this->getAvailableZonesForRock($order->dump_id, $rockId);
            
            if ($availableZones->isEmpty()) {
                $rockName = $miner->currentRock?->name_rock ?? "id={$rockId}";
                Log::debug("Маршрут {$order->id} пропущен: нет доступных зон для породы «{$rockName}» на отвалe {$order->dump_id}");
                continue;
            }
            
            $volumeInZones = $availableZones->sum('volume');
            $score = ($distance * 10) * max($volumeInZones / 1000, 0.1);
            
            $routes->push([
                'miner_id' => $order->miner_id,
                'miner_name' => $miner->name_miner,
                'dump_id' => $order->dump_id,
                'dump_name' => $order->dump->name_dump,
                'weight' => $order->weight ?? 100,
                'distance' => $distance,
                'volume_in_zones' => $volumeInZones,
                'score' => round($score, 2),
                'available_zones' => $availableZones,
            ]);
        }
        
        return $routes->sortBy('score')->values();
    }
    
    /**
     * Получить доступные зоны для породы на отвалe.
     *
     * ВАЖНО: использует fallback-логику пород через RouteAssignmentService::ROCK_FALLBACK_CHAIN.
     * Для "руда_ЦПТ" ищутся зоны, принимающие "руда" или "руда_Sera",
     * если нет зон именно под "руда_ЦПТ".
     *
     * ВАЖНО: ищет по НАЗВАНИЮ породы (name_rock), а не по ID —
     * устойчив к изменению ID при пересоздании таблицы rocks.
     *
     * Возвращает ВСЕ доступные зоны (не одну), чтобы оптимизатор мог рассчитать
     * суммарный volume_in_zones для score.
     */
    protected function getAvailableZonesForRock(int $dumpId, int $rockId): Collection
    {
        // Получаем название породы по ID
        $rock = \App\Models\Rock::find($rockId);
        if (!$rock) {
            Log::debug("getAvailableZonesForRock: порода id={$rockId} не найдена в таблице rocks");
            return collect();
        }

        // Используем RouteAssignmentService для получения fallback-цепочки НАЗВАНИЙ пород
        $routeService = app(\App\Services\RouteAssignmentService::class);
        $acceptableRockNames = $routeService::ROCK_FALLBACK_CHAIN[$rock->name_rock] ?? [$rock->name_rock];

        // Возвращаем ВСЕ зоны на этом отвале, которые:
        // - delivery = true (открыты)
        // - volume < capacity (есть место)
        // - принимают ХОТЯ БЫ ОДНУ из acceptable пород (с учётом fallback)
        $zones = Zone::where('dump_id', $dumpId)
            ->where('delivery', true)
            ->whereRaw('volume < capacity')
            ->whereHas('rocks', function ($q) use ($acceptableRockNames) {
                $q->whereIn('rocks.name_rock', $acceptableRockNames);
            })
            ->get();

        if ($zones->isEmpty()) {
            Log::debug("getAvailableZonesForRock: нет зон", [
                'dump_id' => $dumpId,
                'rock_name' => $rock->name_rock,
                'checked_rock_names' => $acceptableRockNames,
            ]);
        }

        return $zones;
    }
    
    /**
     * Распределить маршруты по раундам (динамическое количество).
     *
     * Количество раундов = min(количество_доступных_маршрутов_для_самого_обеспеченного_забоя, MAX_ROUNDS)
     *
     * Раунд 1: каждому забою — его ЛУЧШИЙ маршрут (с минимальным score)
     * Раунд 2: каждому забою — СЛЕДУЮЩИЙ лучший (резервный, на другой отвал)
     * Раунд N: ...
     *
     * АДАПТИВНАЯ БАЛАНСИРОВКА (через SystemSetting::getZoneSharingThreshold()):
     *   - Если отвал уже занят в этом раунде другим забоем, проверяем заполненность зоны:
     *     • Зона ≤ порога (по умолчанию 30%) → РАЗРЕШАЕМ делиться отвалом между забоями
     *       (минимизация среднего расстояния перевозки). Зона почти пустая — нет смысла
     *       блокировать её эксклюзивно.
     *     • Зона > порога → СОХРАНЯЕМ эксклюзивный режим (один отвал — один забой),
     *       чтобы избежать "жадного захвата" и обеспечить равномерное заполнение.
     *
     * Ограничения:
     *   - В каждом раунде забой может получить только один маршрут
     *   - Один и тот же маршрут не активируется в нескольких раундах
     *   - Если у забоя больше нет доступных маршрутов — он пропускается в этом раунде
     *
     * @param Collection $routes Маршруты с score (отсортированы по возрастанию score)
     * @param Collection $activeMiners Активные забои
     * @return array [round_number => [assignments]]
     */
    protected function assignByRounds(Collection $routes, Collection $activeMiners): array
    {
        $assignments = [];

        // Порог адаптивной балансировки (по умолчанию 30%)
        $sharingThreshold = SystemSetting::getZoneSharingThreshold();

        // Группируем маршруты по забою, сортируя внутри группы по score (возрастание)
        $byMiner = $routes->groupBy('miner_id')->map(function ($group) {
            return $group->sortBy('score')->values();
        });

        // Динамическое количество раундов:
        // берём max количество маршрутов у одного забоя, но не больше MAX_ROUNDS
        $maxRoutesPerMiner = $byMiner->map(fn($r) => $r->count())->max() ?: 0;
        $roundsCount = min($maxRoutesPerMiner, self::MAX_ROUNDS);

        Log::info('assignByRounds: старт', [
            'rounds_count' => $roundsCount,
            'sharing_threshold_pct' => $sharingThreshold,
            'miners_count' => $byMiner->count(),
        ]);

        for ($round = 1; $round <= $roundsCount; $round++) {
            $roundAssignments = [];
            // Каждый отвал может обслуживать несколько забоев, если зона ≤ порога.
            // Структура: [dump_id => ['miner_id' => X, 'fill_pct' => Y]]
            $usedDumpsInThisRound = [];

            foreach ($byMiner as $minerId => $minerRoutes) {
                $assigned = false;

                foreach ($minerRoutes as $route) {
                    $dumpId = $route['dump_id'];

                    // Если отвал ещё не использовался в этом раунде — берём без вопросов
                    if (!isset($usedDumpsInThisRound[$dumpId])) {
                        $roundAssignments[] = $route;
                        $usedDumpsInThisRound[$dumpId] = [
                            'miner_id' => $minerId,
                            'fill_pct' => $this->calculateDumpFillPercent($route),
                        ];
                        $assigned = true;
                        break;
                    }

                    // Отвал уже занят другим забоем — проверяем адаптивный порог
                    $fillPct = $usedDumpsInThisRound[$dumpId]['fill_pct'];

                    if ($fillPct <= $sharingThreshold) {
                        // Зона почти пустая — разрешаем "поделиться" отвалом
                        // (минимизация расстояния перевозки)
                        $roundAssignments[] = $route;
                        Log::info('assignByRounds: адаптивное разделение отвала', [
                            'round' => $round,
                            'dump_id' => $dumpId,
                            'fill_pct' => $fillPct,
                            'threshold' => $sharingThreshold,
                            'miner_id' => $minerId,
                            'also_used_by' => $usedDumpsInThisRound[$dumpId]['miner_id'],
                        ]);
                        $assigned = true;
                        break;
                    }
                    // Зона > порога — продолжаем искать другой отвал (балансировка)
                }

                if ($assigned) {
                    // Удаляем использованный маршрут из доступных для следующих раундов
                    $byMiner[$minerId] = $minerRoutes->reject(function ($r) use ($route) {
                        return $r['dump_id'] === $route['dump_id'];
                    })->values();
                }
            }

            if (!empty($roundAssignments)) {
                $assignments[$round] = $roundAssignments;
                Log::info("assignByRounds: раунд {$round} завершён", [
                    'assignments_count' => count($roundAssignments),
                    'shared_dumps' => count(array_filter($usedDumpsInThisRound, fn($d) => $d['fill_pct'] <= $sharingThreshold)),
                ]);
            } else {
                // Если раунд пуст — заканчиваем (больше маршрутов нет)
                break;
            }
        }

        return $assignments;
    }

    /**
     * Рассчитать средний процент заполнения зон отвалa.
     * Используется для принятия решения об адаптивной балансировке.
     *
     * @param array $route Элемент из коллекции routes (с available_zones)
     * @return float Средний процент заполнения (0-100)
     */
    protected function calculateDumpFillPercent(array $route): float
    {
        $zones = $route['available_zones'] ?? collect();

        if ($zones->isEmpty()) {
            return 100.0; // Нет зон — считаем полностью заполненным (не делимся)
        }

        $totalFillPct = 0;
        $count = 0;

        foreach ($zones as $zone) {
            if ($zone->capacity > 0) {
                $totalFillPct += ($zone->volume / $zone->capacity) * 100;
                $count++;
            }
        }

        return $count > 0 ? round($totalFillPct / $count, 1) : 100.0;
    }
    
    /**
     * Деактивировать маршруты для забоя
     */
    public function deactivateMiner(int $minerId): int
    {
        return MiningOrder::where('miner_id', $minerId)->update(['active' => false]);
    }
    
    /**
     * Получить текущие активные маршруты с информацией
     */
    public function getActiveRoutesInfo(): Collection
    {
        return MiningOrder::where('active', true)
            ->with(['miner.currentRock', 'dump.zones'])
            ->get()
            ->map(function($order) {
                $distance = $order->distance_km ?? MinerDumpDistance::where('miner_id', $order->miner_id)
                    ->where('dump_id', $order->dump_id)
                    ->value('distance_km');
                
                $rock = $order->miner?->currentRock;
                
                $zones = $rock ? $this->getAvailableZonesForRock($order->dump_id, $rock->id) : collect();
                
                return [
                    'id' => $order->id,
                    'miner_id' => $order->miner_id,
                    'miner_name' => $order->miner?->name_miner,
                    'dump_id' => $order->dump_id,
                    'dump_name' => $order->dump?->name_dump,
                    'weight' => $order->weight,
                    'distance' => $distance,
                    'available_zones' => $zones->count(),
                    'rock' => $rock?->name_rock,
                ];
            });
    }
}