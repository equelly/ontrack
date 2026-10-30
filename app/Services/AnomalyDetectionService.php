<?php

namespace App\Services;

use App\Models\AiAlert;
use App\Models\Truck;
use App\Models\TruckTrip;
use App\Models\Miner;
use App\Models\Zone;
use App\Models\Dump;
use App\Models\MiningOrder;
use App\Models\MinerDumpDistance;
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
     * Порог ОБЪЁМА в зоне (в м³), ниже которого «необходимы действия».
     *
     * 760 м³ = 2 «вертушки» по 380 м³ (типовая вместимость кузова
     * самосвала-«вертушки» в горной промышленности).
     *
     * ВАЖНО: Контекст — зоны не только наполняются самосвалами, но и
     * ОТГРУЖАЮТСЯ в ж/д транспорт или на конвейер. Отгрузка пока
     * делается вручную через интерфейс мастера (мастер редактирует
     * объём зоны), этот процесс медленный и управляется человеком.
     *
     * Логика: ёмкость (capacity) НЕ учитываем — только объём (volume)
     * в зоне. Когда объём в зоне упал ниже 760 м³ (т.е. меньше 2
     * вертушек) — диспетчер должен обратить внимание:
     *   - delivery=true  → WARNING  (зона активна для приёма, но материала
     *                       мало — нужно подвозить больше, иначе отгрузка
     *                       простаивает)
     *   - delivery=false → CRITICAL (зона НЕ активна и материала мало —
     *                       отгрузка не может вестись. Нужно либо активировать
     *                       зону delivery=true чтобы самосвалы подвезли,
     *                       либо разбираться, почему нет материала)
     *
     * Если объём поднялся до ≥ 760 (после подвоза самосвалами) —
     * алерт автоматически закрывается (resolve).
     */
    const ZONE_VOLUME_ACTION_THRESHOLD = 760; // м³

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

            // Грузим модель Truck, чтобы в title видеть номер самосвала (а не ID)
            $truck = Truck::find($trip->truck_id);
            $truckNumber = $truck?->number ?? "#{$trip->truck_id}";

            AiAlert::create([
                'type'        => AiAlert::TYPE_TRUCK_ANOMALY,
                'severity'    => $deviationPct > 50 ? AiAlert::SEVERITY_CRITICAL : AiAlert::SEVERITY_WARNING,
                'title'       => "Аномалия времени рейса: самосвал {$truckNumber}",
                'message'     => "Время рейса — {$currentTripMinutes} мин (норма: " . round($mean) . " мин, отклонение +{$deviationPct}%). Возможна пробка, неисправность или неэффективный маршрут.",
                'data'        => [
                    'truck_id'        => $trip->truck_id,
                    'truck_number'    => $truckNumber,
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
                'truck_id'     => $trip->truck_id,
                'truck_number' => $truckNumber,
                'z_score'      => round($zScore, 2),
                'deviation_pct'=> $deviationPct,
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

            AiAlert::create([
                'type'        => AiAlert::TYPE_PRODUCTIVITY_DROP,
                'severity'    => $deviationPct > 50 ? AiAlert::SEVERITY_CRITICAL : AiAlert::SEVERITY_WARNING,
                'title' => "Падение производительности: забой " . ($miner?->name_miner ?? "№" . $trip->miner_id),
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
     * Проверка всех зон по порогу ОБЪЁМА (без учёта capacity).
     *
     * Вызывается по cron (раз в час) из analyzeFleetMaintenance() или
     * вручную. Для каждой зоны:
     *   - если volume < ZONE_VOLUME_ACTION_THRESHOLD (760 м³)
     *     → создаётся алерт типа TYPE_ZONE_VOLUME_ACTION;
     *   - severity зависит от флага delivery:
     *     • delivery=true  → WARNING  (зона активна для приёма, но материала
     *                       мало — нужно подвозить больше, иначе отгрузка
     *                       простаивает)
     *     • delivery=false → CRITICAL (зона НЕ активна и материала мало —
     *                       отгрузка не может вестись. Нужно либо активировать
     *                       зону delivery=true чтобы самосвалы подвезли,
     *                       либо разбираться, почему нет материала)
     *
     * Если по зоне уже есть активный алерт — он обновляется с новыми метриками
     * (логика как в analyzeZoneFill: одна карточка на зону).
     *
     * Если объём поднялся ≥ порога (например, после подвоза самосвалами) —
     * существующий алерт закрывается (resolve), чтобы не было висящих
     * «устаревших» алертов.
     */
    public function analyzeZoneVolumeAction(): int
    {
        $threshold = self::ZONE_VOLUME_ACTION_THRESHOLD;

        // Берём все зоны. НЕ фильтруем по capacity — она не учитывается.
        $zones = Zone::all();

        $alertsCreated = 0;
        $alertsUpdated = 0;
        $alertsResolved = 0;

        foreach ($zones as $zone) {
            $volume = (float) ($zone->volume ?? 0);

            // Ищем существующий активный алерт по этой зоне
            $existingAlert = AiAlert::active()
                ->where('type', AiAlert::TYPE_ZONE_VOLUME_ACTION)
                ->where('entity_type', 'zone')
                ->where('entity_id', $zone->id)
                ->first();

            if ($volume >= $threshold) {
                // Объём в норме (≥ порога) — закрываем висящий алерт, если он был
                if ($existingAlert) {
                    $existingAlert->resolve();
                    $alertsResolved++;
                    Log::info('AnomalyDetection: алерт объёма зоны закрыт (объём в норме)', [
                        'zone_id'    => $zone->id,
                        'zone_name'  => $zone->name_zone,
                        'volume'     => round($volume, 1),
                        'threshold'  => $threshold,
                    ]);
                }
                continue;
            }

            // Нужно создать/обновить алерт — объём ниже порога
            $severity = $zone->delivery
                ? AiAlert::SEVERITY_WARNING
                : AiAlert::SEVERITY_CRITICAL;

            $vertushkiLeft = $volume > 0 ? intdiv($volume, 380) : 0; // сколько «вертушек» ещё в зоне

            // Лаконичная рекомендация:
            // - delivery=true  → «зона подготовлена — нужно подвозить больше»
            // - delivery=false → «зону нужно подготовить для завозки горной массы»
            $deliveryNote = $zone->delivery
                ? '→ Зона подготовлена — нужно подвозить больше'
                : '→ Зону нужно подготовить для завозки горной массы';

            // Заголовок оставляем как есть (информативный)
            $title = "Мало материала в зоне: {$zone->name_zone} — " . round($volume) . ' м³ (< порога ' . $threshold . ')';

            // Лаконичное сообщение: объём + вертушки + рекомендация
            $message = "В зоне " . round($volume, 0) . " м³ (порог {$threshold} м³, ≈ "
                . max(0, $vertushkiLeft) . " вертушек). " . $deliveryNote;

            $alertData = [
                'zone_id'         => $zone->id,
                'zone_name'       => $zone->name_zone,
                'dump_id'         => $zone->dump_id,
                'volume'          => round($volume, 1),
                'threshold'       => $threshold,
                'delivery'        => (bool) $zone->delivery,
                'vertushki_left'  => $vertushkiLeft,
            ];

            if ($existingAlert) {
                // Обновляем существующий алерт свежими метриками
                $existingAlert->update([
                    'severity' => $severity,
                    'title'    => $title,
                    'message'  => $message,
                    'data'     => $alertData,
                ]);
                $alertsUpdated++;
            } else {
                AiAlert::create([
                    'type'        => AiAlert::TYPE_ZONE_VOLUME_ACTION,
                    'severity'    => $severity,
                    'title'       => $title,
                    'message'     => $message,
                    'data'        => $alertData,
                    'entity_type' => 'zone',
                    'entity_id'   => $zone->id,
                    'status'      => AiAlert::STATUS_NEW,
                ]);
                $alertsCreated++;
            }
        }

        Log::info('AnomalyDetection: analyzeZoneVolumeAction', [
            'zones_analyzed'   => $zones->count(),
            'alerts_created'   => $alertsCreated,
            'alerts_updated'   => $alertsUpdated,
            'alerts_resolved'  => $alertsResolved,
            'threshold_m3'     => $threshold,
        ]);

        return $alertsCreated + $alertsUpdated;
    }

    /**
     * Проверка среднего расстояния перевозки за смену.
     *
     * Если фактическое среднее расстояние завершённых рейсов за смену
     * превышает порог (SystemSetting::getAvgDistanceThreshold(), по умолчанию 5 км)
     * → создаётся AiAlert типа TYPE_AVG_DISTANCE_HIGH с ОДНОЙ сводной рекомендацией
     * по перенаправлению грузопотоков:
     *
     *   Для каждого активного маршрута с distance_km > порога:
     *     1. Найти все альтернативные отвалы с подходящей породой (с fallback:
     *        руда_ЦПТ → руда → руда_Sera) и со свободным объёмом (volume < capacity)
     *     2. Из них выбрать ближайший (минимальное distance_km из miner_dump_distances)
     *     3. Определить статус:
     *        • delivery=true → «✅ Готов» — диспетчер может сразу переключать
     *        • delivery=false → «⚠ Нужна активация» — попросить мастера подготовить зону
     *        • Нет альтернатив → «❌ Нет альтернатив»
     *
     * Логика "одна карточка на алерт" — обновляем существующий, не создаём дубль.
     * Если среднее опустилось ≤ порога — закрываем существующий алерт (resolve).
     */
    public function analyzeAvgTransportDistance(): int
    {
        $threshold = SystemSetting::getAvgDistanceThreshold();

        // Получаем смену
        $shiftService = app(ShiftService::class);
        $shift = $shiftService->getCurrentShift();

        if (!is_array($shift)) {
            Log::info('AnomalyDetection: analyzeAvgTransportDistance — getCurrentShift не вернул массив', [
                'shift_result' => $shift,
            ]);
            return 0;
        }

        // Фактические завершённые рейсы за смену.
        // ВАЖНО: используем completed_at (как в KPI), а не created_at —
        // рейс может быть создан в предыдущей смене, а завершён в текущей.
        // Если фильтровать по created_at — такие рейсы не попадут в выборку,
        // и среднее будет занижено или равно 0.
        $trips = TruckTrip::where('completed_at', '>=', $shift['start_time'])
            ->where('completed_at', '<', $shift['end_time'])
            ->whereNotNull('completed_at')
            ->with('miningOrder')
            ->get();

        Log::info('AnomalyDetection: analyzeAvgTransportDistance — выборка рейсов', [
            'shift_start'   => $shift['start_time']->format('Y-m-d H:i'),
            'shift_end'      => $shift['end_time']->format('Y-m-d H:i'),
            'shift_type'     => $shift['shift_type'] ?? null,
            'threshold_km'   => $threshold,
            'trips_found'    => $trips->count(),
        ]);

        if ($trips->isEmpty()) {
            // Нет завершённых рейсов за смену — пропускаем
            Log::info('AnomalyDetection: analyzeAvgTransportDistance — нет завершённых рейсов за смену');
            return 0;
        }

        // Считаем расстояние по каждому рейсу с fallback:
        // trip->distance_km → MiningOrder->distance_km → MinerDumpDistance → 0
        // (это решает проблему старых рейсов, где distance_km не был сохранён)
        $tripDistances = [];
        foreach ($trips as $trip) {
            $dist = (float) ($trip->distance_km ?? 0);
            $source = 'trip.distance_km';
            if ($dist <= 0 && $trip->miningOrder?->distance_km) {
                $dist = (float) $trip->miningOrder->distance_km;
                $source = 'miningOrder.distance_km';
            }
            if ($dist <= 0 && $trip->miner_id && $trip->dump_id) {
                $distFromTable = MinerDumpDistance::where('miner_id', $trip->miner_id)
                    ->where('dump_id', $trip->dump_id)
                    ->value('distance_km');
                if ($distFromTable !== null) {
                    $dist = (float) $distFromTable;
                    $source = 'miner_dump_distances';
                }
            }
            if ($dist > 0) {
                $tripDistances[] = $dist;
                Log::debug('AnomalyDetection: trip distance', [
                    'trip_id'   => $trip->id,
                    'distance'   => $dist,
                    'source'     => $source,
                ]);
            } else {
                Log::warning('AnomalyDetection: trip без расстояния (даже после fallback)', [
                    'trip_id'    => $trip->id,
                    'truck_id'   => $trip->truck_id,
                    'miner_id'   => $trip->miner_id,
                    'dump_id'    => $trip->dump_id,
                ]);
            }
        }

        if (count($tripDistances) < 1) {
            // Нет ни одного рейса с известным расстоянием — пропускаем
            Log::info('AnomalyDetection: analyzeAvgTransportDistance — нет данных о расстоянии', [
                'trips_count' => $trips->count(),
                'trips_with_distance' => count($tripDistances),
            ]);
            return 0;
        }

        $tripsCount = count($tripDistances);
        $totalDistance = array_sum($tripDistances);
        $avgDistance = $totalDistance / $tripsCount;

        Log::info('AnomalyDetection: analyzeAvgTransportDistance — расчёт среднего', [
            'trips_with_distance' => $tripsCount,
            'total_distance'      => round($totalDistance, 2),
            'avg_distance'        => round($avgDistance, 2),
            'threshold'           => $threshold,
            'exceeds_threshold'   => $avgDistance > $threshold,
        ]);

        // Ищем существующий активный алерт
        $existingAlert = AiAlert::active()
            ->where('type', AiAlert::TYPE_AVG_DISTANCE_HIGH)
            ->where('entity_type', 'fleet')
            ->first();

        if ($avgDistance <= $threshold) {
            // Среднее в норме — закрываем висящий алерт
            if ($existingAlert) {
                $existingAlert->resolve();
                Log::info('AnomalyDetection: алерт среднего расстояния закрыт (в норме)', [
                    'avg_distance' => round($avgDistance, 2),
                    'threshold'    => $threshold,
                    'trips_count'  => $tripsCount,
                ]);
            }
            return 0;
        }

        // Среднее превысило порог — формируем рекомендации
        $recommendations = $this->buildDistanceRecommendations($threshold, $avgDistance);

        $severity = $avgDistance > $threshold * 1.5
            ? AiAlert::SEVERITY_CRITICAL
            : AiAlert::SEVERITY_WARNING;

        // Лаконичный заголовок: «Расстояние перевозки: 4.3 км > 2.5 км (порог)»
        $title = "Расстояние перевозки: " . round($avgDistance, 1)
            . " км > {$threshold} км (порог)";

        // Лаконичное сообщение: краткое summary + список рекомендаций по одной строке на маршрут
        $message = "Рекомендации по перенаправлению грузопотоков:\n";

        if (empty($recommendations)) {
            $message .= "Нет активных маршрутов с расстоянием выше порога — рекомендации не требуются.";
        } else {
            $message .= implode("\n", array_column($recommendations, 'text_message'));
        }

        $alertData = [
            'avg_distance'    => round($avgDistance, 2),
            'threshold'       => $threshold,
            'trips_count'     => $tripsCount,
            'total_distance'  => round($totalDistance, 1),
            'shift_start'     => isset($shift['start_time']) ? $shift['start_time']->format('Y-m-d H:i') : null,
            'shift_end'       => isset($shift['end_time']) ? $shift['end_time']->format('Y-m-d H:i') : null,
            'recommendations' => $recommendations,
        ];

        if ($existingAlert) {
            $existingAlert->update([
                'severity' => $severity,
                'title'    => $title,
                'message'  => $message,
                'data'     => $alertData,
            ]);
            $action = 'updated';
        } else {
            AiAlert::create([
                'type'        => AiAlert::TYPE_AVG_DISTANCE_HIGH,
                'severity'    => $severity,
                'title'       => $title,
                'message'     => $message,
                'data'        => $alertData,
                'entity_type' => 'fleet',
                'entity_id'   => null,
                'status'      => AiAlert::STATUS_NEW,
            ]);
            $action = 'created';
        }

        Log::info('AnomalyDetection: алерт среднего расстояния перевозки ' . $action, [
            'avg_distance'        => round($avgDistance, 2),
            'threshold'           => $threshold,
            'trips_count'         => $tripsCount,
            'recommendations_cnt' => count($recommendations),
        ]);

        return 1;
    }

    /**
     * Сформировать ОДНУ сводную рекомендацию по перенаправлению грузопотоков.
     *
     * Для каждого активного маршрута с distance_km > порога:
     *   1. Найти все альтернативные отвалы с подходящей породой (с fallback:
     *      руда_ЦПТ → руда → руда_Sera) и со свободным объёмом (volume < capacity)
     *   2. Из них выбрать ближайший (минимальное distance_km из miner_dump_distances)
     *   3. Определить статус:
     *      • delivery=true → «✅ Готов» — диспетчер может сразу переключать
     *      • delivery=false → «⚠ Нужна активация» — попросить мастера подготовить зону
     *      • Нет альтернатив → «❌ Нет альтернатив»
     *
     * Возвращает структурированный массив recommendations[] для data AiAlert.
     * Каждый элемент содержит:
     *   - miner_id, miner_name, rock_name
     *   - current_dump_id, current_dump_name, current_distance_km
     *   - recommended_dump_id, recommended_dump_name, recommended_distance_km (null если нет альтернативы)
     *   - saving_km (null если нет альтернативы)
     *   - status: ready | needs_activation | no_alternative
     *   - status_label: читабельный текст
     *   - text_message: готовый текст для отображения в UI
     */
    protected function buildDistanceRecommendations(float $threshold, float $avgDistance): array
    {
        $recommendations = [];

        // Подгружаем отвалы с зонами (с capacity) и породами зон
        $dumps = Dump::with(['zones' => function ($q) {
            $q->whereNotNull('capacity')->with('rocks');
        }])->get();

        $miners = Miner::where('status', Miner::STATUS_ACTIVE)->with('currentRock')->get();
        $minerIds = $miners->pluck('id')->toArray();

        // Все расстояния от забоев до отвалов
        $distances = MinerDumpDistance::whereIn('miner_id', $minerIds)->get();

        // Активные маршруты с заполненным distance_km (загружаем dump для текущего отвала)
        $activeOrders = MiningOrder::where('active', true)
            ->whereNotNull('distance_km')
            ->with('dump')
            ->get();

        foreach ($activeOrders as $order) {
            $currentDist = (float) $order->distance_km;
            if ($currentDist <= $threshold) {
                continue; // маршрут в норме — не включаем в рекомендации
            }

            $miner = $miners->firstWhere('id', $order->miner_id);
            if (!$miner || !$miner->currentRock) {
                continue;
            }
            $rockName = $miner->currentRock->name_rock;

            // Допустимые названия пород (с fallback: руда_ЦПТ → руда → руда_Sera)
            $acceptableRockNames = RouteAssignmentService::ROCK_FALLBACK_CHAIN[$rockName] ?? [$rockName];

            // Ищем лучший альтернативный отвал
            $bestAlt = null;
            $bestAltDist = $currentDist;
            $bestAltZone = null;

            foreach ($dumps as $altDump) {
                // Не предлагаем тот же отвал
                if ($altDump->id === $order->dump_id) {
                    continue;
                }

                // Проверяем, есть ли в отвалe зона с подходящей породой и свободным объёмом
                $matchingZone = null;
                foreach ($altDump->zones as $zone) {
                    // volume < capacity — есть свободное место
                    if ((float) $zone->volume >= (float) $zone->capacity) {
                        continue;
                    }
                    // Проверяем породу зоны (с учётом fallback)
                    $zoneHasAcceptableRock = false;
                    foreach ($zone->rocks as $zoneRock) {
                        if (in_array($zoneRock->name_rock, $acceptableRockNames, true)) {
                            $zoneHasAcceptableRock = true;
                            break;
                        }
                    }
                    if ($zoneHasAcceptableRock) {
                        $matchingZone = $zone;
                        break;
                    }
                }

                if (!$matchingZone) {
                    continue; // нет зоны с подходящей породой и свободным объёмом
                }

                // Ищем расстояние от забоя до этого отвала
                $altDistance = $distances->firstWhere(fn($d) => $d->miner_id === $order->miner_id && $d->dump_id === $altDump->id);
                if (!$altDistance) {
                    continue; // нет записи о расстоянии — не можем предложить
                }
                $altDist = (float) $altDistance->distance_km;

                // Берём ближайший
                if ($altDist < $bestAltDist) {
                    $bestAlt = $altDump;
                    $bestAltDist = $altDist;
                    $bestAltZone = $matchingZone;
                }
            }

            // Формируем элемент рекомендации — лаконично, в одну строку
            if ($bestAlt && $bestAltZone) {
                $savingKm = round($currentDist - $bestAltDist, 2);
                $isReady = (bool) $bestAltZone->delivery;
                $status = $isReady ? 'ready' : 'needs_activation';
                $statusLabel = $isReady
                    ? '✅ Готов — можно переключать'
                    : '⚠ Нужна активация зоны';

                // Лаконичный формат: ЭКГ-18 → п/п 10(1) (8.2 км) → п/п 12(2) (2.5 км), экономия 5.7 км/рейс
                // + если нужна активация: « — подготовить зону п/п 12(2)»
                $textMessage = "• {$miner->name_miner} → {$order->dump->name_dump} ({$currentDist} км)"
                    . " → {$bestAlt->name_dump} ({$bestAltDist} км), экономия {$savingKm} км/рейс";
                if (!$isReady) {
                    $textMessage .= " — подготовить зону {$bestAltZone->name_zone}";
                }

                $recommendations[] = [
                    'miner_id'                  => $miner->id,
                    'miner_name'                => $miner->name_miner,
                    'rock_name'                 => $rockName,
                    'current_dump_id'           => $order->dump_id,
                    'current_dump_name'         => $order->dump->name_dump,
                    'current_distance_km'       => $currentDist,
                    'recommended_dump_id'       => $bestAlt->id,
                    'recommended_dump_name'     => $bestAlt->name_dump,
                    'recommended_zone_id'       => $bestAltZone->id,
                    'recommended_zone_name'     => $bestAltZone->name_zone,
                    'recommended_distance_km'   => $bestAltDist,
                    'saving_km'                 => $savingKm,
                    'status'                    => $status,
                    'status_label'              => $statusLabel,
                    'text_message'              => $textMessage,
                ];
            } else {
                // Нет альтернатив — лаконично
                $textMessage = "• {$miner->name_miner} → {$order->dump->name_dump} ({$currentDist} км)"
                    . " — ❌ нет альтернатив (все другие отвалы дальше или нет зоны с подходящей породой)";

                $recommendations[] = [
                    'miner_id'                  => $miner->id,
                    'miner_name'                => $miner->name_miner,
                    'rock_name'                 => $rockName,
                    'current_dump_id'           => $order->dump_id,
                    'current_dump_name'         => $order->dump->name_dump,
                    'current_distance_km'       => $currentDist,
                    'recommended_dump_id'       => null,
                    'recommended_dump_name'     => null,
                    'recommended_zone_id'       => null,
                    'recommended_zone_name'     => null,
                    'recommended_distance_km'   => null,
                    'saving_km'                 => null,
                    'status'                    => 'no_alternative',
                    'status_label'              => '❌ Нет альтернатив',
                    'text_message'              => $textMessage,
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Анализ всего парка — предсказание необходимости ТО.
     * Вызывается по cron (раз в час) или вручную.
     *
     * В одном проходе:
     *   1. Сканирует тренды времени рейсов всех самосвалов (Maintenance Predict)
     *   2. Сканирует все зоны по порогу объёма (Zone Volume Action)
     *   3. Проверяет среднее расстояние перевозки за смену (Avg Distance High)
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
                        'title'       => "Риск поломки: самосвал {$truck->number} (#{$truck->id})",
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

        // === Сканирование всех зон по порогу объёма ===
        // Вызывается в том же проходе, что и проверка трендов самосвалов —
        // экономит один запрос к БД при cron-вызове раз в час.
        $zoneAlerts = $this->analyzeZoneVolumeAction();

        // === Проверка среднего расстояния перевозки за смену ===
        $distanceAlerts = $this->analyzeAvgTransportDistance();

        Log::info('AnomalyDetection: analyzeFleetMaintenance', [
            'trucks_analyzed'  => $trucks->count(),
            'truck_alerts'     => $alerts,
            'zone_alerts'      => $zoneAlerts,
            'distance_alerts'  => $distanceAlerts,
            'total_alerts'     => $alerts + $zoneAlerts + $distanceAlerts,
        ]);

        return [
            'trucks_analyzed'  => $trucks->count(),
            'truck_alerts'     => $alerts,
            'zone_alerts'      => $zoneAlerts,
            'distance_alerts'  => $distanceAlerts,
            'total_alerts'     => $alerts + $zoneAlerts + $distanceAlerts,
        ];
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