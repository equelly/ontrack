<?php

namespace App\Services\Simulation;

use App\Models\TruckTrip;
use App\Models\TripPause;
use App\Models\Truck;
use App\Models\Miner;
use Illuminate\Support\Facades\DB;

/**
 * ExistingMetricsCalculator — считает фактические метрики из БД за смену.
 *
 * Берёт реальные TruckTrip, TripPause и считает:
 *   - Среднее расстояние перевозки
 *   - Холостой пробег (км + %)
 *   - КИО самосвалов (время в работе / время смены)
 *   - КИО экскаваторов
 *   - Простои самосвалов (мин)
 *   - Простои экскаваторов (мин)
 *   - Количество рейсов
 *   - Объём горной массы (м³)
 */
class ExistingMetricsCalculator
{
    public function __construct(private SimulationConfig $config) {}

    public function calculate(): array
    {
        $trips = TruckTrip::whereBetween('created_at', [
            $this->config->shiftStart, $this->config->shiftEnd
        ])
            ->whereNotNull('completed_at')
            ->with(['truck', 'miner'])
            ->get();

        $pauses = TripPause::whereBetween('started_at', [
            $this->config->shiftStart, $this->config->shiftEnd
        ])->get();

        $shiftMinutes = $this->config->totalMinutes;

        // === Среднее расстояние ===
        $distances = $trips->map(fn($t) => (float) ($t->distance_km ?? 0))->filter(fn($d) => $d > 0);
        $avgDistance = $distances->isNotEmpty() ? round($distances->avg(), 2) : 0;

        // === Холостой пробег ===
        $totalLoaded = $trips->sum(fn($t) => (float) ($t->distance_km ?? 0));
        $totalEmpty = $trips->sum(fn($t) => (float) ($t->empty_run_km ?? $t->distance_km ?? 0));
        $totalAll = $totalLoaded + $totalEmpty;
        $emptyPct = $totalAll > 0 ? round(($totalEmpty / $totalAll) * 100, 1) : 0;

        // === Рейсы и объём ===
        $tripsCount = $trips->count();
        $totalVolume = round($trips->sum('load_volume'), 1);

        // === КИО самосвалов ===
        // busy_time = время всех рейсов (started_at → completed_at)
        $truckBusyTime = []; // [truck_id => minutes]
        foreach ($trips as $trip) {
            if ($trip->started_at && $trip->completed_at) {
                $minutes = $trip->started_at->diffInMinutes($trip->completed_at);
                if ($minutes > 0 && $minutes <= 240) {
                    $truckBusyTime[$trip->truck_id] = ($truckBusyTime[$trip->truck_id] ?? 0) + $minutes;
                }
            }
        }
        $truckCount = $this->config->trucks->count();
        $totalTruckBusy = array_sum($truckBusyTime);
        $totalTruckAvailable = $truckCount * $shiftMinutes;
        $truckKIO = $totalTruckAvailable > 0
            ? round(($totalTruckBusy / $totalTruckAvailable) * 100, 1)
            : 0;

        // === КИО экскаваторов ===
        // busy_time = время погрузки (load_start → loaded_at)
        $minerBusyTime = []; // [miner_id => minutes]
        foreach ($trips as $trip) {
            if ($trip->load_start && $trip->loaded_at && $trip->miner_id) {
                $minutes = $trip->load_start->diffInMinutes($trip->loaded_at);
                if ($minutes > 0 && $minutes < 60) {
                    $minerBusyTime[$trip->miner_id] = ($minerBusyTime[$trip->miner_id] ?? 0) + $minutes;
                }
            }
        }
        $minerCount = $this->config->miners->count();
        $totalMinerBusy = array_sum($minerBusyTime);
        $totalMinerAvailable = $minerCount * $shiftMinutes;
        $minerKIO = $totalMinerAvailable > 0
            ? round(($totalMinerBusy / $totalMinerAvailable) * 100, 1)
            : 0;

        // === Простои самосвалов ===
        $truckPauseMinutes = 0;
        foreach ($pauses as $pause) {
            $truckPauseMinutes += $pause->getCurrentDuration();
        }
        $avgTruckPause = $truckCount > 0 ? round($truckPauseMinutes / $truckCount) : 0;

        // === Простои экскаваторов (когда нет самосвалов) ===
        // Упрощённо: (доступное время забоя) − (время погрузки) = простой
        $minerIdleMinutes = $totalMinerAvailable - $totalMinerBusy;
        $avgMinerIdle = $minerCount > 0 ? round($minerIdleMinutes / $minerCount) : 0;

        return [
            'label'              => 'Факт (existing)',
            'avg_distance'       => $avgDistance,
            'total_empty_km'     => round($totalEmpty, 1),
            'empty_pct'          => $emptyPct,
            'truck_kio'          => $truckKIO,
            'miner_kio'          => $minerKIO,
            'truck_pause_min'    => round($truckPauseMinutes),
            'avg_truck_pause'    => $avgTruckPause,
            'miner_idle_min'     => round(max(0, $minerIdleMinutes)),
            'avg_miner_idle'     => $avgMinerIdle,
            'trips_count'        => $tripsCount,
            'total_volume'       => $totalVolume,
            'truck_count'        => $truckCount,
            'miner_count'        => $minerCount,
        ];
    }
}