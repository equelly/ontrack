<?php

namespace App\Services;

use App\Models\Miner;
use App\Models\Dump;
use App\Models\Zone;
use App\Models\Truck;
use App\Models\MiningOrder;
use App\Models\MinerDumpDistance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DistributionService
{
    protected ScoreCalculatorService $scoreCalculator;
    protected RouteStatisticsService $statistics;

    public function __construct(
        ScoreCalculatorService $scoreCalculator,
        RouteStatisticsService $statistics
    ) {
        $this->scoreCalculator = $scoreCalculator;
        $this->statistics = $statistics;
    }

    /**
     * Главное распределение
     */
    public function distribute(array $options): array
    {
        $mode = $options['mode'] ?? 'balance';
        $activeZonesOnly = $options['active_zones_only'] ?? false;

        // Отладка
        Log::debug('DistributionService::distribute', [
            'mode' => $mode,
            'activeZonesOnly' => $activeZonesOnly,
        ]);

        $routes = $this->getAvailableRoutes($activeZonesOnly);
        
        Log::debug('Available routes count', ['count' => $routes->count()]);
        
        $routesWithScore = $this->calculateScores($routes, $mode);
        $assignments = $this->assignByRounds($routesWithScore);
        $stats = $this->buildStatistics($assignments, $mode, $activeZonesOnly);

        return [
            'assignments' => $assignments,
            'stats' => $stats,
            'mode' => $mode,
            'active_zones_only' => $activeZonesOnly,
        ];
    }

    /**
     * Получить доступные маршруты
     */
    public function getAvailableRoutes(bool $activeZonesOnly): Collection
    {
        return MinerDumpDistance::with(['miner', 'dump.zones.rocks'])
            ->whereHas('miner', fn($q) => $q->where('active', true))
            ->get()
            ->filter(function($record) use ($activeZonesOnly) {
                $zones = $record->dump->zones;
                
                if ($activeZonesOnly) {
                    return $zones->where('delivery', true)->isNotEmpty();
                }
                
                return $zones->isNotEmpty();
            })
            ->map(function($record) use ($activeZonesOnly) {
                $zones = $activeZonesOnly 
                    ? $record->dump->zones->where('delivery', true)
                    : $record->dump->zones;

                return [
                    'miner_id' => $record->miner_id,
                    'miner_name' => $record->miner->name_miner ?? "Забой #{$record->miner_id}",
                    'dump_id' => $record->dump_id,
                    'dump' => $record->dump,
                    'distance' => $record->distance_km,
                    'travel_time' => $record->travel_time_hours,
                    'volume' => $zones->sum('volume'),
                    'dump_capacity' => $record->dump->capacity ?? 60,
                    'zones' => $zones,
                ];
            });
    }

    /**
     * Рассчитать score для всех маршрутов
     */
    protected function calculateScores(Collection $routes, string $mode): Collection
    {
        return $routes->map(function($route) use ($mode) {
            $route['score'] = $this->scoreCalculator->calculate([
                'volume' => $route['volume'],
                'distance' => $route['distance'],
                'dump_capacity' => $route['dump_capacity'],
            ], $mode);
            
            return $route;
        })->sortByDesc('score')->values();
    }

    /**
     * Распределение по раундам
     */
    protected function assignByRounds(Collection $routes): array
    {
        // Группируем по miner_id
        $byMiner = $routes->groupBy('miner_id');
        
        // Группируем по dump_id для распределения
        $byDump = $routes->groupBy('dump_id');
        
        $assignments = [];
        $assignedMiners = [];
        $round = 1;
        $maxRounds = 10;

        while ($round <= $maxRounds) {
            $assignedThisRound = [];

            foreach ($byDump as $dumpId => $dumpRoutes) {
                // Сортируем по score
                $sorted = $dumpRoutes->sortByDesc('score');

                foreach ($sorted as $route) {
                    $minerId = $route['miner_id'];

                    // Если miner не назначен в этом раунде
                    if (!in_array($minerId, $assignedMiners) && !in_array($minerId, $assignedThisRound)) {
                        $assignments[] = [
                            'miner_id' => $route['miner_id'],
                            'miner_name' => $route['miner_name'],
                            'dump_id' => $route['dump_id'],
                            'dump_name' => $route['dump']->name_dump ?? "Дамп #{$route['dump_id']}",
                            'dump' => $route['dump'],
                            'distance' => $route['distance'],
                            'travel_time' => $route['travel_time'],
                            'volume' => $route['volume'],
                            'score' => $route['score'],
                            'round' => $round,
                        ];

                        $assignedThisRound[] = $minerId;
                        $assignedMiners[] = $minerId;
                        break; // Переходим к следующему дампу
                    }
                }
            }

            if (empty($assignedThisRound)) {
                break; // Все распределены
            }

            $round++;
        }

        return $assignments;
    }

    /**
     * Собрать статистику
     */
    protected function buildStatistics(array $assignments, string $mode, bool $activeZonesOnly): array
    {
        $distStats = $this->statistics->getDistributionStats($assignments);
        $systemStats = $this->statistics->getSystemStats();
        $zonesByRock = $this->statistics->getZonesByRock($activeZonesOnly);

        return [
            // Статистика распределения
            'total_routes' => $distStats['total_routes'],
            'total_distance' => $distStats['total_distance'],
            'average_distance' => $distStats['average_distance'],
            'best_score' => $distStats['best_score'],
            'avg_score' => $distStats['avg_score'],
            
            // Системная статистика
            'total_miners' => $systemStats['total_miners'],
            'active_miners' => $systemStats['active_miners'],
            'total_dumps' => $systemStats['total_dumps'],
            'total_zones' => $systemStats['total_zones'],
            'active_zones' => $systemStats['active_zones'],
            'total_trucks' => $systemStats['total_trucks'],
            'trucks_in_work' => $systemStats['trucks_in_work'],
            'free_trucks' => $systemStats['free_trucks'],
            'breakdown_trucks' => $systemStats['breakdown_trucks'],
            
            // Зоны по породам
            'zones_by_rock' => $zonesByRock,
            
            // Режим
            'mode' => $mode,
            'mode_name' => $this->getModeName($mode),
            'active_zones_only' => $activeZonesOnly,
        ];
    }

    /**
     * Название режима
     */
    protected function getModeName(string $mode): string
    {
        return match($mode) {
            'volume' => '📏 Приоритет по объёму',
            'distance' => '🏃 Приоритет по расстоянию',
            default => '⚖️ Баланс объёма и расстояния (30/70)',
        };
    }
}
