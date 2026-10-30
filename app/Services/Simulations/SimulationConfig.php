<?php

namespace App\Services\Simulation;

use App\Models\Miner;
use App\Models\Truck;
use App\Models\Zone;
use App\Models\MiningOrder;
use App\Models\MinerDumpDistance;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * SimulationConfig — гибридная загрузка:
 *   - Инфраструктура (забои, самосвалы, зоны, расстояния, породы, delivery) — из БД
 *   - Фактические параметры (скорость, время погрузки/разгрузки) — из JSON (Modular)
 *   - События (простои, ТО) — из JSON (Modular)
 *   - Фактические результаты смены (actual_results) — из JSON (Modular)
 *
 * Файл JSON:
 *   {
 *     "shift": {"date": "2026-10-15", "type": "day"},
 *     "metrics_from_modular": {
 *       "avg_speed_kmh": 25.0,
 *       "avg_load_time_min": 5.0,
 *       "avg_unload_time_min": 3.0
 *     },
 *     "events": [
 *       {"time": "10:00", "entity": "truck", "entity_id": 1, "type": "breakdown", "duration_min": 60}
 *     ],
 *     "actual_results": {
 *       "avg_distance": 4.3,
 *       "total_empty_km": 86.4,
 *       ...
 *     }
 *   }
 */
class SimulationConfig
{
    public Carbon $shiftStart;
    public Carbon $shiftEnd;
    public int $totalMinutes;

    // Из БД
    public Collection $miners;
    public Collection $trucks;
    public Collection $zones;
    public Collection $orders;
    public Collection $distances;

    // Из JSON (Modular)
    public float $avgSpeed;
    public float $avgLoadTime;
    public float $avgUnloadTime;
    public array $events = [];
    public array $actualResults = [];

    public int $timeStepMinutes = 5;
    public int $seed = 42;

    // Сырой JSON
    private array $json;

    public function __construct(string $jsonPath)
    {
        $this->json = $this->loadJson($jsonPath);
        $this->initShiftTimes();
        $this->loadFromDatabase();
        $this->loadMetricsFromJson();
        $this->loadEventsFromJson();
        $this->loadActualResultsFromJson();
    }

    /**
     * Загрузить JSON-файл с данными из Modular.
     */
    protected function loadJson(string $path): array
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Файл не найден: {$path}");
        }

        $content = file_get_contents($path);
        $json = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("Ошибка JSON: " . json_last_error_msg());
        }

        return $json;
    }

    /**
     * Определить начало/конец смены из JSON.
     */
    protected function initShiftTimes(): void
    {
        $shift = $this->json['shift'] ?? [];
        $date = $shift['date'] ?? date('Y-m-d');
        $type = $shift['type'] ?? 'day';

        $baseDate = Carbon::parse($date);

        if ($type === 'day') {
            $this->shiftStart = $baseDate->copy()->setTime(7, 30, 0);
            $this->shiftEnd = $baseDate->copy()->setTime(19, 30, 0);
        } else {
            $this->shiftStart = $baseDate->copy()->setTime(19, 30, 0);
            $this->shiftEnd = $baseDate->copy()->addDay()->setTime(7, 30, 0);
        }

        $this->totalMinutes = $this->shiftStart->diffInMinutes($this->shiftEnd);

        Log::info('SimulationConfig: shift', [
            'start' => $this->shiftStart->format('Y-m-d H:i'),
            'end'   => $this->shiftEnd->format('Y-m-d H:i'),
            'minutes' => $this->totalMinutes,
        ]);
    }

    /**
     * Загрузить инфраструктуру из БД (забои, самосвалы, зоны, расстояния, маршруты).
     * Эти данные настраиваются через панель мастера.
     */
    protected function loadFromDatabase(): void
    {
        $this->miners = Miner::where('status', Miner::STATUS_ACTIVE)
            ->where('status', Miner::STATUS_ACTIVE)
            ->with(['currentRock', 'rocks'])
            ->get();

        $this->trucks = Truck::where('status', '!=', 'breakdown')
            ->with('truckModel')
            ->get();

        $this->zones = Zone::with(['rocks', 'dump'])->get();

        $this->orders = MiningOrder::where('active', true)
            ->with(['miner', 'dump'])
            ->get();

        $this->distances = MinerDumpDistance::all();

        Log::info('SimulationConfig: DB loaded', [
            'miners'   => $this->miners->count(),
            'trucks'   => $this->trucks->count(),
            'zones'    => $this->zones->count(),
            'orders'   => $this->orders->count(),
            'distances'=> $this->distances->count(),
        ]);
    }

    /**
     * Загрузить фактические параметры из JSON (Modular).
     */
    protected function loadMetricsFromJson(): void
    {
        $m = $this->json['metrics_from_modular'] ?? [];

        $this->avgSpeed = (float) ($m['avg_speed_kmh'] ?? 25.0);
        $this->avgLoadTime = (float) ($m['avg_load_time_min'] ?? 5.0);
        $this->avgUnloadTime = (float) ($m['avg_unload_time_min'] ?? 3.0);

        Log::info('SimulationConfig: metrics from JSON', [
            'avg_speed'       => $this->avgSpeed,
            'avg_load_time'   => $this->avgLoadTime,
            'avg_unload_time' => $this->avgUnloadTime,
        ]);
    }

    /**
     * Загрузить события (простои, ТО) из JSON.
     */
    protected function loadEventsFromJson(): void
    {
        $events = $this->json['events'] ?? [];

        foreach ($events as $ev) {
            $timeStr = $ev['time'] ?? null;
            if (!$timeStr) continue;

            // Парсим время относительно даты смены
            $eventTime = $this->shiftStart->copy()->setTimeFromTimeString($timeStr);
            // Если ночная смена и время < 12:00 — это следующий день
            if ($this->json['shift']['type'] ?? 'day' === 'night' && intval($timeStr) < 12) {
                $eventTime = $eventTime->addDay();
            }

            $this->events[] = [
                'time'        => $eventTime,
                'type'        => $ev['type'] ?? 'pause',
                'entity'      => $ev['entity'] ?? 'truck',
                'entity_id'   => (int) ($ev['entity_id'] ?? 0),
                'duration_min'=> (int) ($ev['duration_min'] ?? 0),
                'reason'      => $ev['reason'] ?? '',
            ];
        }

        Log::info('SimulationConfig: events loaded', ['count' => count($this->events)]);
    }

    /**
     * Загрузить фактические результаты из JSON (Modular).
     */
    protected function loadActualResultsFromJson(): void
    {
        $this->actualResults = $this->json['actual_results'] ?? [];

        if (empty($this->actualResults)) {
            Log::warning('SimulationConfig: actual_results пустой — сравнение невозможно');
        }
    }

    /**
     * Получить расстояние от забоя до отвала (в км) — из БД.
     */
    public function getDistance(int $minerId, int $dumpId): ?float
    {
        $dist = $this->distances->firstWhere(fn($d) => $d->miner_id === $minerId && $d->dump_id === $dumpId);
        return $dist ? (float) $dist->distance_km : null;
    }

    /**
     * Проверить, принимает ли зона данную породу — из БД.
     */
    public function zoneAcceptsRock(int $zoneId, string $rockName): bool
    {
        $zone = $this->zones->firstWhere('id', $zoneId);
        if (!$zone) return false;
        return $zone->rocks->contains('name_rock', $rockName);
    }

    /**
     * Рассчитать время рейса для симуляции.
     * Формула: load + transport + unload + empty_run
     */
    public function calculateTripTime(float $distanceKm): float
    {
        $transportMinutes = ($distanceKm / $this->avgSpeed) * 60;
        $emptyRunMinutes = $transportMinutes;
        return $this->avgLoadTime + $transportMinutes + $this->avgUnloadTime + $emptyRunMinutes;
    }

    public function getTotalTicks(): int
    {
        return intdiv($this->totalMinutes, $this->timeStepMinutes);
    }

    public function tickToTime(int $tick): Carbon
    {
        return $this->shiftStart->copy()->addMinutes($tick * $this->timeStepMinutes);
    }

    /**
     * Получить фактические результаты (из JSON) в формате для сравнения.
     */
    public function getActualResults(): array
    {
        return [
            'label'            => 'Факт (Modular)',
            'avg_distance'      => (float) ($this->actualResults['avg_distance'] ?? 0),
            'total_empty_km'   => (float) ($this->actualResults['total_empty_km'] ?? 0),
            'empty_pct'         => (float) ($this->actualResults['empty_pct'] ?? 0),
            'truck_kio'         => (float) ($this->actualResults['truck_kio'] ?? 0),
            'miner_kio'         => (float) ($this->actualResults['miner_kio'] ?? 0),
            'truck_pause_min'   => (int) ($this->actualResults['truck_pause_min'] ?? 0),
            'avg_truck_pause'   => $this->trucks->count() > 0
                ? (int) round(($this->actualResults['truck_pause_min'] ?? 0) / $this->trucks->count())
                : 0,
            'miner_idle_min'    => (int) ($this->actualResults['miner_idle_min'] ?? 0),
            'avg_miner_idle'    => $this->miners->count() > 0
                ? (int) round(($this->actualResults['miner_idle_min'] ?? 0) / $this->miners->count())
                : 0,
            'trips_count'       => (int) ($this->actualResults['trips_count'] ?? 0),
            'total_volume'      => (float) ($this->actualResults['total_volume'] ?? 0),
            'truck_count'       => $this->trucks->count(),
            'miner_count'       => $this->miners->count(),
        ];
    }
}
