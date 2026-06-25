<?php

namespace App\Services;

use App\Models\Miner;
use App\Models\Dump;
use App\Models\Zone;
use App\Models\Truck;
use App\Models\TruckTrip;
use App\Models\MiningOrder;
use Illuminate\Support\Facades\DB;

class RouteStatisticsService
{
    /**
     * Получить статистику по зонам
     */
    public function getZonesByRock(bool $activeOnly = false): array
    {
        $query = DB::table('zones')
            ->join('rock_zone', 'zones.id', '=', 'rock_zone.zone_id')
            ->join('rocks', 'rock_zone.rock_id', '=', 'rocks.id')
            ->select(
                'zones.id',
                'zones.name_zone',
                'zones.dump_id',
                'zones.volume',
                'zones.delivery',
                'rocks.name_rock'
            )
            ->orderBy('rocks.name_rock')
            ->orderBy('zones.name_zone');

        $zones = $query->get()->groupBy('name_rock');

        // Сортируем по объёму дампа
        $dumpVolumes = $this->getDumpVolumes();

        return $zones->map(function($zonesForRock) use ($dumpVolumes) {
            return $zonesForRock->sortBy(function($zone) use ($dumpVolumes) {
                return $dumpVolumes[$zone->dump_id] ?? 0;
            })->values();
        })->toArray();
    }

    /**
     * Получить объёмы дампов
     */
    public function getDumpVolumes(): array
    {
        return DB::table('zones')
            ->select('dump_id', DB::raw('SUM(volume) as total_volume'))
            ->whereNotNull('volume')
            ->groupBy('dump_id')
            ->pluck('total_volume', 'dump_id')
            ->toArray();
    }

    /**
     * Общая статистика системы
     */
    public function getSystemStats(): array
    {
        return [
            'total_miners' => Miner::count(),
            'active_miners' => Miner::where('active', true)->count(),
            'total_dumps' => Dump::count(),
            'total_zones' => Zone::count(),
            'active_zones' => Zone::where('delivery', true)->count(),
            'total_trucks' => Truck::count(),
            'trucks_in_work' => Truck::whereIn('status', ['to_miner', 'loading', 'transporting', 'unloading'])->count(),
            'free_trucks' => Truck::where('status', 'free')->count(),
            'breakdown_trucks' => Truck::where('status', 'breakdown')->count(),
        ];
    }

    /**
     * Статистика по грузовикам в работе
     */
    public function getTrucksInWork(): array
    {
        return Truck::whereIn('status', ['to_miner', 'loading', 'transporting', 'unloading'])
            ->with(['driver', 'currentTrip.miner', 'currentTrip.dump'])
            ->get()
            ->map(function($truck) {
                $trip = $truck->currentTrip;
                return [
                    'id' => $truck->id,
                    'number' => $truck->number,
                    'status' => $truck->status,
                    'driver' => $truck->driver?->name,
                    'miner' => $trip?->miner?->name_miner,
                    'dump' => $trip?->dump?->name_dump,
                    'started_at' => $trip?->started_at,
                    'fuel_level' => $truck->fuel_level,
                ];
            })
            ->toArray();
    }

    /**
     * Статистика распределения
     */
    public function getDistributionStats(array $assignments): array
    {
        if (empty($assignments)) {
            return [
                'total_routes' => 0,
                'total_distance' => 0,
                'average_distance' => 0,
                'best_score' => 0,
                'avg_score' => 0,
            ];
        }

        $totalRoutes = count($assignments);
        $totalDistance = array_sum(array_column($assignments, 'distance'));
        $scores = array_column($assignments, 'score');

        return [
            'total_routes' => $totalRoutes,
            'total_distance' => round($totalDistance, 2),
            'average_distance' => round($totalDistance / $totalRoutes, 2),
            'best_score' => round(max($scores), 1),
            'avg_score' => round(array_sum($scores) / count($scores), 1),
        ];
    }
}
