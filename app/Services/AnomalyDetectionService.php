<?php

namespace App\Services;

use App\Models\AiAlert;
use App\Models\Truck;
use App\Models\TruckTrip;
use App\Models\Miner;
use App\Models\Zone;
use App\Models\SystemSetting;
use App\Events\RoutesUpdated;
use App\Events\ZoneFillWarning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AnomalyDetectionService — обнаружение аномалий в реальном времени.
 *
 * Анализирует метрики после завершения рейса, изменения зоны и других событий.
 * Создаёт AiAlert при обнаружении отклонений.
 *
 * Методы:
 * - Скользящее среднее (последние N рейсов)
 * - z-score (отклонение от нормы в стандартных отклонениях)
 * - Линейная регрессия для трендов (заполнение зон)
 *
 * 100% офлайн: не нужен LLM, GPU или интернет.
 */
class AnomalyDetectionService
{
    /**
     * Количество рейсов для скользящего среднего.
     */
    const MOVING_AVERAGE_WINDOW = 10;

    /**
     * Порог z-score: если отклонение > порога — аномалия.
     * z=2 означает ~95% доверительный интервал (нормальное распределение).
     */
    const Z_SCORE_THRESHOLD = 2.0;

    /**
     * Процент холостого пробега, после которого — алерт.
     */
    const EMPTY_RUN_ALERT_THRESHOLD = 40.0; // %

    /**
     * Анализировать завершённый рейс самосвала.
     *
     * Вызывается из TruckStatusService::onUnloading() после завершения trip.
     * Проверяет:
     *   1. Время рейса — не выросло ли аномально
     *   2. Время погрузки — не упала ли производительность
     *   3. Холостой пробег парка — не превысил ли порог
     *   4. Прогноз поломки (по тренду времени рейса и погрузки)
     */
    public function analyzeCompletedTrip(TruckTrip $trip): void
    {
        if (!$trip->completed_at || !$trip->started_at) {
            return;
        }

        $this->analyzeTripTime($trip);
        $this->analyzeLoadingTime($trip);
        $this->analyzeFleetEmptyRun();
    }

    /**
     * Анализ времени рейса.
     * Если время текущего рейса > нормы (скользящее среднее + z-score) — алерт.
     */
    protected function analyzeTripTime(TruckTrip $trip): void
    {
        $currentTripMinutes = $trip->started_at->diffInMinutes($trip->completed_at);

        // Берём последние N завершённых рейсов этого самосвала
        $recentTrips = TruckTrip::where('truck_id', $trip->truck_id)
            ->whereNotNull('completed_at')
            ->where('id', '!=', $trip->id)
            ->orderBy('completed_at', 'desc')
            ->limit(self::MOVING_AVERAGE_WINDOW)
            ->get();

        if ($recentTrips->count() < 3) {
            return; // Недостаточно данных для анализа
        }

        $tripTimes = $recentTrips->map(function ($t) {
            return $t->started_at->diffInMinutes($t->completed_at);
        })->toArray();

        $mean = array_sum($tripTimes) / count($tripTimes);
        $stdDev = $this->standardDeviation($tripTimes);

        if ($stdDev == 0) {
            return; // Нет вариативности — не можем определить аномалию
        }

        $zScore = ($currentTripMinutes - $mean) / $stdDev;

        if ($zScore > self::Z_SCORE_THRESHOLD) {
            $deviationPct = $mean > 0 ? round((($currentTripMinutes - $mean) / $mean) * 100) : 0;

            // Проверяем, нет ли уже активного алерта для этого самосвала
            if (AiAlert::hasActiveAlert(AiAlert::TYPE_TRUCK_ANOMALY, $trip->truck_id, 'truck')) {
                return;
            }

            AiAlert::create([
                'type'        => AiAlert::TYPE_TRUCK_ANOMALY,
                'severity'    => $deviationPct > 50 ? AiAlert::SEVERITY_CRITICAL : AiAlert::SEVERITY_WARNING,
                'title'       => "Аномалия времени рейса: самосвал #{$trip->truck_id}",
                'message'     => "Время рейса — {$currentTripMinutes} мин (норма: " . round($mean) . " мин, отклонение +{$deviationPct}%). Возможна пробка, неисправность или неэффективный маршрут.",
                'data'        => [
                    'truck_id'        => $trip->truck_id,
                    'trip_id'         => $trip->id,
                    'current_minutes' => $currentTripMinutes,
                    'average_minutes' => round($mean, 1),
                    'z_score'         => round($zScore, 2),
                    'deviation_pct'   => $deviationPct,
                ],
                'entity_type' => 'truck',
                'entity_id'   => $trip->truck_id,
                'status'      => AiAlert::STATUS_NEW,
            ]);

            Log::info('AnomalyDetection: алерт времени рейса', [
                'truck_id' => $trip->truck_id,
                'z_score' => round($zScore, 2),
                'deviation_pct' => $deviationPct,
            ]);
        }
    }

    /**
     * Анализ времени погрузки забоя.
     * Если время погрузки выросло — падение производительности.
     */
    protected function analyzeLoadingTime(TruckTrip $trip): void
    {
        if (!$trip->load_start || !$trip->loaded_at) {
            return;
        }

        $currentLoadMinutes = $trip->load_start->diffInMinutes($trip->loaded_at);

        // Берём последние N завершённых рейсов этого забоя
        $recentTrips = TruckTrip::where('miner_id', $trip->miner_id)
            ->whereNotNull('loaded_at')
            ->whereNotNull('load_start')
            ->where('id', '!=', $trip->id)
            ->orderBy('completed_at', 'desc')
            ->limit(self::MOVING_AVERAGE_WINDOW)
            ->get();

        if ($recentTrips->count() < 3) {
            return;
        }

        $loadTimes = $recentTrips->map(function ($t) {
            return $t->load_start->diffInMinutes($t->loaded_at);
        })->toArray();

        $mean = array_sum($loadTimes) / count($loadTimes);
        $stdDev = $this->standardDeviation($loadTimes);

        if ($stdDev == 0 || $mean == 0) {
            return;
        }

        $zScore = ($currentLoadMinutes - $mean) / $stdDev;

        if ($zScore > self::Z_SCORE_THRESHOLD) {
            $deviationPct = round((($currentLoadMinutes - $mean) / $mean) * 100);
            $miner = Miner::find($trip->miner_id);

            if (AiAlert::hasActiveAlert(AiAlert::TYPE_PRODUCTIVITY_DROP, $trip->miner_id, 'miner')) {
                return;
            }

            // 1. Безопасно формируем имя забоя до сборки массива
            $minerName = ($miner && isset($miner->name_miner)) ? $miner->name_miner : '#' . $trip->miner_id;

            // 2. Создаем запись в БД
            AiAlert::create([
                'type'        => AiAlert::TYPE_PRODUCTIVITY_DROP,
                'severity'    => $deviationPct > 50 ? AiAlert::SEVERITY_CRITICAL : AiAlert::SEVERITY_WARNING,
                'title'       => "Падение производительности: забой {$minerName}", // Теперь здесь чистая переменная
                'message'     => "Время погрузки выросло до {$currentLoadMinutes} мин (норма: " . round($mean) . " мин, +{$deviationPct}%). Возможно: износ оборудования, нехватка самосвалов, сложная порода.",
                'data'        => [
                    'miner_id'         => $trip->miner_id,
                    'trip_id'          => $trip->id,
                    'current_minutes'  => $currentLoadMinutes,
                    'average_minutes'  => round($mean, 1),
                    'deviation_pct'    => $deviationPct,
                ],
                'entity_type' => 'miner',
                'entity_id'   => $trip->miner_id,
                'status'      => AiAlert::STATUS_NEW,
            ]);

            Log::info('AnomalyDetection: алерт производительности забоя', [
                'miner_id' => $trip->miner_id,
                'deviation_pct' => $deviationPct,
            ]);
        }
    }

    /**
     * Анализ холостого пробега по парку.
     * Если % холостого > порога — алерт.
     */
    public function analyzeFleetEmptyRun(): void
    {
        $shiftService = app(ShiftService::class);
        $shift = $shiftService->getCurrentShift();

        if (!is_array($shift)) {
            return;
        }

        $trips = TruckTrip::whereBetween('created_at', [$shift['start_time'], $shift['end_time']])
            ->whereNotNull('completed_at')
            ->get();

        if ($trips->count() < 5) {
            return; // Недостаточно рейсов за смену
        }

        $totalLoaded = $trips->sum('distance_km');
        $totalEmpty = $trips->sum('empty_run_km');
        $totalAll = $totalLoaded + $totalEmpty;

        if ($totalAll == 0) {
            return;
        }

        $emptyPct = ($totalEmpty / $totalAll) * 100;

        if ($emptyPct > self::EMPTY_RUN_ALERT_THRESHOLD) {
            // Проверяем, нет ли уже активного алерта за эту смену
            if (AiAlert::hasActiveAlert(AiAlert::TYPE_EMPTY_RUN_HIGH)) {
                return;
            }

            AiAlert::create([
                'type'        => AiAlert::TYPE_EMPTY_RUN_HIGH,
                'severity'    => $emptyPct > 60 ? AiAlert::SEVERITY_CRITICAL : AiAlert::SEVERITY_WARNING,
                'title'       => "Высокий холостой пробег парка: " . round($emptyPct) . '%',
                'message'     => "Холостой пробег — " . round($totalEmpty, 1) . " км из " . round($totalAll, 1) . " км общего (" . round($emptyPct) . "%). Рекомендация: пересмотреть распределение маршрутов, ближе забои к отвалам.",
                'data'        => [
                    'total_loaded_km' => round($totalLoaded, 1),
                    'total_empty_km'  => round($totalEmpty, 1),
                    'empty_pct'       => round($emptyPct, 1),
                    'trips_count'     => $trips->count(),
                ],
                'entity_type' => 'fleet',
                'entity_id'   => null,
                'status'      => AiAlert::STATUS_NEW,
            ]);

            Log::info('AnomalyDetection: алерт холостого пробега', [
                'empty_pct' => round($emptyPct, 1),
            ]);
        }
    }

    /**
     * Прогноз переполнения зоны.
     * Вызывается при обновлении объёма зоны (после выгрузки).
     *
     * Если зона заполнена > 80% и скорость заполнения высокая —
     * алерт: «Скоро потребуется обваловка».
     */
    public function analyzeZoneFill(Zone $zone): void
    {
        if ($zone->capacity <= 0) {
            return;
        }

        $fillPct = ($zone->volume / $zone->capacity) * 100;

        Log::debug('AnomalyDetection: analyzeZoneFill called', [
            'zone_id'      => $zone->id,
            'zone_name'    => $zone->name_zone,
            'volume'       => $zone->volume,
            'capacity'     => $zone->capacity,
            'fill_pct'     => round($fillPct, 1),
        ]);

        if ($fillPct < 80) {
            return;
        }

        // Считаем скорость заполнения: объём за последний час
        $recentVolume = DB::table('truck_trips')
            ->where('zone_id', $zone->id)
            ->where('completed_at', '>=', now()->subHour())
            ->sum('load_volume');

        // Прогноз: через сколько часов зона переполнится
        $remainingCapacity = $zone->capacity - $zone->volume;
        $hoursToOverflow = $recentVolume > 0 ? $remainingCapacity / $recentVolume : null;

        $severity = $fillPct > 90 ? AiAlert::SEVERITY_CRITICAL : AiAlert::SEVERITY_WARNING;
        $message = "Зона «{$zone->name_zone}» заполнена на " . round($fillPct) . '%.';

        if ($hoursToOverflow !== null && $hoursToOverflow < 3) {
            $message .= " При текущей скорости — переполнение через " . round($hoursToOverflow) . " ч. Требуется обваловка!";
        } elseif ($hoursToOverflow !== null) {
            $message .= " При текущей скорости — переполнение через " . round($hoursToOverflow) . " ч.";
        } else {
            $message .= " Требуется контроль.";
        }

        $alertData = [
            'zone_id'           => $zone->id,
            'zone_name'         => $zone->name_zone,
            'dump_id'           => $zone->dump_id,
            'volume'            => $zone->volume,
            'capacity'          => $zone->capacity,
            'fill_pct'          => round($fillPct, 1),
            'recent_volume_hr'  => $recentVolume,
            'hours_to_overflow' => $hoursToOverflow !== null ? round($hoursToOverflow, 1) : null,
        ];

        // === Логика AiAlert: создаём только при первом превышении,
        // при последующих — обновляем существующий с новыми данными.
        // Это позволяет алерт-центру иметь ОДНУ карточку на зону, но
        // с актуальными метриками.
        $existingAlert = AiAlert::active()
            ->where('type', AiAlert::TYPE_ZONE_OVERFLOW)
            ->where('entity_type', 'zone')
            ->where('entity_id', $zone->id)
            ->first();

        if ($existingAlert) {
            // Обновляем существующий алерт новыми данными
            $existingAlert->update([
                'severity' => $severity,
                'title'    => "Переполнение зоны: {$zone->name_zone} (" . round($fillPct) . '%)',
                'message'  => $message,
                'data'     => $alertData,
            ]);
            Log::info('AnomalyDetection: обновлён существующий AiAlert', [
                'alert_id' => $existingAlert->id,
                'zone_id'  => $zone->id,
                'fill_pct' => round($fillPct, 1),
            ]);
        } else {
            // Создаём новый
            AiAlert::create([
                'type'        => AiAlert::TYPE_ZONE_OVERFLOW,
                'severity'    => $severity,
                'title'       => "Переполнение зоны: {$zone->name_zone} (" . round($fillPct) . '%)',
                'message'     => $message,
                'data'        => $alertData,
                'entity_type' => 'zone',
                'entity_id'   => $zone->id,
                'status'      => AiAlert::STATUS_NEW,
            ]);
            Log::info('AnomalyDetection: создан новый AiAlert', [
                'zone_id' => $zone->id,
                'fill_pct' => round($fillPct, 1),
            ]);
        }

        // === ВСЕГДА отправляем вебсокет-событие ZoneFillWarning —
        // даже если AiAlert уже есть. Это real-time уведомление для
        // мастера/диспетчера о каждой выгрузке в зону >80%.
        // Раньше тут был баг: событие отправлялось только при создании
        // нового AiAlert, поэтому при последующих выгрузках мастер не
        // получал toast (а должен был видеть каждую выгрузку).
        try {
            event(new ZoneFillWarning($zone, $hoursToOverflow));
        } catch (\Exception $e) {
            Log::error('Failed to broadcast ZoneFillWarning: ' . $e->getMessage());
        }

        Log::info('AnomalyDetection: алерт переполнения зоны', [
            'zone_id' => $zone->id,
            'fill_pct' => round($fillPct, 1),
            'hours_to_overflow' => $hoursToOverflow !== null ? round($hoursToOverflow, 1) : null,
            'alert_action' => $existingAlert ? 'updated' : 'created',
        ]);
    }

    /**
     * Анализ всего парка — предсказание необходимости ТО.
     * Вызывается по cron (раз в час) или вручную.
     */
    public function analyzeFleetMaintenance(): array
    {
        $trucks = Truck::whereIn('status', ['free', 'completed', 'to_miner', 'loading', 'transporting'])
            ->get();

        $alerts = 0;

        foreach ($trucks as $truck) {
            // Проверяем тренды времени рейса — если растёт, возможно износ
            $recentTrips = TruckTrip::where('truck_id', $truck->id)
                ->whereNotNull('completed_at')
                ->orderBy('completed_at', 'desc')
                ->limit(20)
                ->get();

            if ($recentTrips->count() < 5) {
                continue;
            }

            // Сравниваем последние 5 рейсов с предыдущими 5
            $last5 = $recentTrips->take(5);
            $prev5 = $recentTrips->skip(5)->take(5);

            if ($prev5->isEmpty()) {
                continue;
            }

            $last5Avg = $last5->avg(fn($t) => $t->started_at->diffInMinutes($t->completed_at));
            $prev5Avg = $prev5->avg(fn($t) => $t->started_at->diffInMinutes($t->completed_at));

            if ($prev5Avg > 0) {
                $trendPct = (($last5Avg - $prev5Avg) / $prev5Avg) * 100;

                // Если время рейса выросло на 25%+ — риск поломки
                if ($trendPct > 25 && !AiAlert::hasActiveAlert(AiAlert::TYPE_MAINTENANCE_PREDICT, $truck->id, 'truck')) {
                    AiAlert::create([
                        'type'        => AiAlert::TYPE_MAINTENANCE_PREDICT,
                        'severity'    => $trendPct > 50 ? AiAlert::SEVERITY_CRITICAL : AiAlert::SEVERITY_WARNING,
                        'title'       => "Риск поломки: самосвал #{$truck->id} ({$truck->number})",
                        'message'     => "Время рейса выросло на " . round($trendPct) . "% за последние 5 рейсов (норма: " . round($prev5Avg) . " мин, текущее: " . round($last5Avg) . " мин). Рекомендуется диагностика. Moto-часы: {$truck->moto_minutes_since_to}.",
                        'data'        => [
                            'truck_id'            => $truck->id,
                            'truck_number'        => $truck->number,
                            'trend_pct'           => round($trendPct, 1),
                            'last5_avg_minutes'   => round($last5Avg, 1),
                            'prev5_avg_minutes'   => round($prev5Avg, 1),
                            'moto_hours_since_to' => $truck->moto_minutes_since_to,
                        ],
                        'entity_type' => 'truck',
                        'entity_id'   => $truck->id,
                        'status'      => AiAlert::STATUS_NEW,
                    ]);
                    $alerts++;
                }
            }
        }

        Log::info('AnomalyDetection: analyzeFleetMaintenance', [
            'trucks_analyzed' => $trucks->count(),
            'alerts_created'  => $alerts,
        ]);

        return ['trucks_analyzed' => $trucks->count(), 'alerts_created' => $alerts];
    }

    /**
     * Стандартное отклонение.
     */
    protected function standardDeviation(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $variance = 0.0;

        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }

        return sqrt($variance / ($count - 1));
    }
}