<?php

namespace App\Services\Simulation;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * SimulationEngine — движок симуляции нашей системы распределения.
 *
 * Полностью в памяти — НЕ пишет в БД.
 * Шаг: 5 минут. На каждом тике:
 *   1. Завершает рейсы (если время прошло → самосвал свободен)
 *   2. Обновляет объёмы зон (после выгрузки)
 *   3. Назначает маршруты свободным самосвалам (упрощённый WRR)
 *   4. Обрабатывает события (остановки)
 *   5. Обновляет метрики
 *
 * WRR упрощённый:
 *   - Для каждого свободного самосвала
 *   - Найти все активные маршруты (MiningOrder) с доступной зоной
 *   - Score = distance_km (меньше = лучше) × (volume_in_zone / 1000 + 0.1)
 *   - Назначить маршрут с минимальным score (ближний + пустая зона)
 */
class SimulationEngine
{
    // Виртуальное состояние
    private array $trucks = [];     // [id => {status, available, available_at, busy_minutes, current_trip}]
    private array $miners = [];      // [id => {available, available_at, name, busy_minutes}]
    private array $zones = [];       // [id => {volume, capacity, delivery, dump_id, name}]
    private array $trips = [];       // [{truck_id, miner_id, dump_id, zone_id, distance_km, empty_run_km, started_at, will_complete_at, load_volume}]

    // Метрики
    private array $metrics = [];
    private int $tripsCount = 0;

    public function __construct(private SimulationConfig $config)
    {
        $this->initState();
        $this->initMetrics();
    }

    /**
     * Инициализация виртуального состояния из конфига.
     */
    private function initState(): void
    {
        // Самосвалы — все свободны и доступны
        foreach ($this->config->trucks as $truck) {
            $this->trucks[$truck->id] = [
                'id'            => $truck->id,
                'number'        => $truck->number,
                'status'        => 'free',
                'available'      => true,
                'available_at'  => $this->config->shiftStart->copy(),
                'busy_minutes'  => 0,
                'current_trip'  => null,
            ];
        }

        // Экскаваторы — все доступны
        foreach ($this->config->miners as $miner) {
            $this->miners[$miner->id] = [
                'id'            => $miner->id,
                'name'          => $miner->name_miner,
                'available'      => true,
                'available_at'  => $this->config->shiftStart->copy(),
                'busy_minutes'  => 0,
            ];
        }

        // Зоны — фактический volume на начало смены
        foreach ($this->config->zones as $zone) {
            $this->zones[$zone->id] = [
                'id'       => $zone->id,
                'dump_id'  => $zone->dump_id,
                'volume'   => (float) ($zone->volume ?? 0),
                'capacity' => (float) ($zone->capacity ?? 0),
                'delivery' => (bool) $zone->delivery,
                'name'     => $zone->name_zone,
            ];
        }
    }

    private function initMetrics(): void
    {
        $this->metrics = [
            'total_distance'    => 0,
            'total_empty'       => 0,
            'trips_count'       => 0,
            'total_volume'      => 0,
            'truck_busy_time'   => [], // [truck_id => minutes]
            'miner_busy_time'   => [], // [miner_id => minutes]
            'truck_wait_time'   => [], // [truck_id => minutes waiting for assignment]
            'miner_idle_time'   => [], // [miner_id => minutes no trucks]
        ];

        foreach ($this->trucks as $id => $_) {
            $this->metrics['truck_busy_time'][$id] = 0;
            $this->metrics['truck_wait_time'][$id] = 0;
        }

        foreach ($this->config->miners as $miner) {
            $this->metrics['miner_busy_time'][$miner->id] = 0;
            $this->metrics['miner_idle_time'][$miner->id] = 0;
        }
    }

    /**
     * Запуск симуляции.
     */
    public function run(): array
    {
        $totalTicks = $this->config->getTotalTicks();
        Log::info("SimulationEngine: starting {$totalTicks} ticks");

        for ($tick = 0; $tick < $totalTicks; $tick++) {
            $currentTime = $this->config->tickToTime($tick);

            // 0. Обработка событий (простои, поломки, ТО)
            $this->processEvents($currentTime);

            // 1. Завершить рейсы
            $this->completeTrips($currentTime);

            // 2. Назначить маршруты свободным самосвалам
            $this->assignRoutes($currentTime, $tick);

            // 3. Обновить метрики простоя
            $this->updateIdleMetrics($tick);
        }

        return $this->buildResult();
    }

    /**
     * Обработка событий (простои, поломки, ТО) из конфига.
     *
     * Логика:
     *   - При наступлении события → entity становится недоступен на duration минут
     *   - При истечении duration → entity снова доступен
     *   - Самосвал в момент события: если в рейсе → завершает рейс и уходит в простой
     *   - Экскаватор: просто недоступен для новых назначений
     */
    private function processEvents(Carbon $currentTime): void
    {
        // 1. Восстановление доступности (если available_at прошла)
        foreach ($this->trucks as $id => &$truck) {
            if (!$truck['available'] && $currentTime >= $truck['available_at']) {
                $truck['available'] = true;
            }
        }
        foreach ($this->miners as $id => &$miner) {
            if (!$miner['available'] && $currentTime >= $miner['available_at']) {
                $miner['available'] = true;
            }
        }
        unset($truck, $miner);

        // 2. Обработка новых событий в этом тике
        foreach ($this->config->events as $event) {
            $eventTime = $event['time'];
            // Событие должно начаться в этом тике (между прошлым тиком и текущим)
            $tickStart = $currentTime->copy()->subMinutes($this->config->timeStepMinutes);
            if ($eventTime < $tickStart || $eventTime >= $currentTime) continue;

            $entityId = $event['entity_id'];
            $durationMin = $event['duration_min'] ?? 0;
            $availableAt = $currentTime->copy()->addMinutes($durationMin);

            if ($event['entity'] === 'truck' && isset($this->trucks[$entityId])) {
                // Самосвал → недоступен
                $this->trucks[$entityId]['available'] = false;
                $this->trucks[$entityId]['available_at'] = $availableAt;
                // Если самосвал в рейсе — завершаем рейс досрочно (поломка в пути)
                if ($this->trucks[$entityId]['current_trip']) {
                    $this->trucks[$entityId]['current_trip'] = null;
                    $this->trucks[$entityId]['status'] = 'free';
                }
                Log::debug("Event: truck {$entityId} unavailable until {$availableAt->format('H:i')}", [
                    'type' => $event['type'], 'duration' => $durationMin
                ]);
            } elseif ($event['entity'] === 'miner' && isset($this->miners[$entityId])) {
                // Экскаватор → недоступен
                $this->miners[$entityId]['available'] = false;
                $this->miners[$entityId]['available_at'] = $availableAt;
                Log::debug("Event: miner {$entityId} unavailable until {$availableAt->format('H:i')}", [
                    'type' => $event['type'], 'duration' => $durationMin
                ]);
            }
        }
    }

    /**
     * Завершить рейсы, чьё время истекло.
     */
    private function completeTrips(Carbon $currentTime): void
    {
        foreach ($this->trucks as $truckId => &$truck) {
            if ($truck['current_trip'] === null) continue;

            $trip = &$truck['current_trip'];
            if ($currentTime >= $trip['will_complete_at']) {
                // Рейс завершён
                $this->metrics['total_distance'] += $trip['distance_km'];
                $this->metrics['total_empty'] += $trip['empty_run_km'];
                $this->metrics['total_volume'] += $trip['load_volume'];
                $this->metrics['trips_count']++;
                $this->tripsCount++;

                // Время в работе
                $tripMinutes = $trip['started_at']->diffInMinutes($trip['will_complete_at']);
                $this->metrics['truck_busy_time'][$truckId] += $tripMinutes;
                $this->metrics['miner_busy_time'][$trip['miner_id']] += $this->config->avgLoadTime;

                // Обновить объём зоны
                $zoneId = $trip['zone_id'];
                if (isset($this->zones[$zoneId])) {
                    $this->zones[$zoneId]['volume'] += $trip['load_volume'];
                }

                // Самосвал свободен
                $truck['status'] = 'free';
                $truck['available_at'] = $currentTime->copy();
                $truck['current_trip'] = null;
            }
        }
        unset($truck, $trip);
    }

    /**
     * Назначить маршруты свободным самосвалам (упрощённый WRR).
     */
    private function assignRoutes(Carbon $currentTime, int $tick): void
    {
        // Свободные и доступные самосвалы (не в простое/поломке)
        $freeTrucks = array_filter($this->trucks, fn($t) =>
            $t['status'] === 'free' && $t['available'] === true
        );
        if (empty($freeTrucks)) return;

        // Доступные маршруты с доступными зонами
        $availableRoutes = $this->findAvailableRoutes();
        if (empty($availableRoutes)) return;

        // Для каждого самосвала — найти лучший маршрут (минимальный score)
        $assignedRoutes = []; // dump_id, чтобы не назначать 2 самосвала на один отвал
        foreach ($freeTrucks as $truckId => $truck) {
            $bestRoute = null;
            $bestScore = PHP_FLOAT_MAX;

            foreach ($availableRoutes as $route) {
                // Skip если этот отвал уже занят в этом тике
                if (in_array($route['dump_id'], $assignedRoutes)) continue;

                // Score = distance (меньше = лучше) × (volume / 1000 + 0.1)
                $score = $route['distance_km'] * max($route['zone_free_volume'] / 1000, 0.1);
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestRoute = $route;
                }
            }

            if ($bestRoute) {
                $this->assignTrip($truckId, $bestRoute, $currentTime);
                $assignedRoutes[] = $bestRoute['dump_id'];
            }
        }
    }

    /**
     * Найти доступные маршруты: MiningOrder + доступная зона с подходящей породой.
     */
    private function findAvailableRoutes(): array
    {
        $routes = [];

        foreach ($this->config->orders as $order) {
            $distance = $this->config->getDistance($order->miner_id, $order->dump_id);
            if (!$distance || $distance <= 0) continue;

            // Проверка: экскаватор доступен? (не в простое/поломке)
            $minerId = $order->miner_id;
            if (!isset($this->miners[$minerId]) || !$this->miners[$minerId]['available']) continue;

            // Порода текущего забоя
            $rockName = $order->miner?->currentRock?->name_rock;
            if (!$rockName) continue;

            // Найти доступную зону на этом отвалe с подходящей породой
            foreach ($this->zones as $zone) {
                if ($zone['dump_id'] !== $order->dump_id) continue;
                if (!$zone['delivery']) continue;
                if ($zone['volume'] >= $zone['capacity']) continue;

                // Проверка породы — через config
                if (!$this->config->zoneAcceptsRock($zone['id'], $rockName)) continue;

                $freeVolume = $zone['capacity'] - $zone['volume'];

                $routes[] = [
                    'order_id'        => $order->id,
                    'miner_id'        => $order->miner_id,
                    'dump_id'         => $order->dump_id,
                    'zone_id'         => $zone['id'],
                    'distance_km'     => $distance,
                    'zone_free_volume' => $freeVolume,
                    'load_volume'     => (float) ($order->miner->capacity_per_trip ?? 50),
                    'rock_name'       => $rockName,
                ];
                break; // первая подходящая зона
            }
        }

        return $routes;
    }

    /**
     * Назначить рейс самосвалу.
     */
    private function assignTrip(int $truckId, array $route, Carbon $currentTime): void
    {
        $tripTime = $this->config->calculateTripTime($route['distance_km']);
        $willCompleteAt = $currentTime->copy()->addMinutes((int) round($tripTime));

        $this->trucks[$truckId]['status'] = 'transporting';
        $this->trucks[$truckId]['current_trip'] = [
            'miner_id'       => $route['miner_id'],
            'dump_id'        => $route['dump_id'],
            'zone_id'        => $route['zone_id'],
            'distance_km'    => $route['distance_km'],
            'empty_run_km'   => $route['distance_km'], // симметричный
            'started_at'     => $currentTime->copy(),
            'will_complete_at' => $willCompleteAt,
            'load_volume'    => $route['load_volume'],
        ];
    }

    /**
     * Обновить метрики простоя (за тик = 5 минут).
     */
    private function updateIdleMetrics(int $tick): void
    {
        $step = $this->config->timeStepMinutes;

        // Простои самосвалов:
        // - free без назначения (ждёт маршрут)
        // - недоступен (в поломке/ТО) — это тоже простой
        foreach ($this->trucks as $truckId => $truck) {
            if ($truck['status'] === 'free' && $truck['current_trip'] === null) {
                $this->metrics['truck_wait_time'][$truckId] += $step;
            }
            if (!$truck['available']) {
                $this->metrics['truck_wait_time'][$truckId] += $step;
            }
        }

        // Простои экскаваторов:
        // - нет занятых самосвалов на этом забое
        // - экскаватор недоступен (в поломке/ТО) — это тоже простой
        $busyMiners = [];
        foreach ($this->trucks as $truck) {
            if ($truck['current_trip']) {
                $busyMiners[$truck['current_trip']['miner_id']] = true;
            }
        }
        foreach ($this->config->miners as $miner) {
            $minerId = $miner->id;
            $isIdle = !isset($busyMiners[$minerId]); // нет самосвалов на забое
            $isUnavailable = isset($this->miners[$minerId]) && !$this->miners[$minerId]['available'];

            if ($isIdle || $isUnavailable) {
                $this->metrics['miner_idle_time'][$minerId] += $step;
            }
        }
    }

    /**
     * Построить результат симуляции.
     */
    private function buildResult(): array
    {
        $shiftMinutes = $this->config->totalMinutes;
        $truckCount = count($this->trucks);
        $minerCount = $this->config->miners->count();

        $totalTruckBusy = array_sum($this->metrics['truck_busy_time']);
        $totalTruckAvailable = $truckCount * $shiftMinutes;

        $totalMinerBusy = array_sum($this->metrics['miner_busy_time']);
        $totalMinerAvailable = $minerCount * $shiftMinutes;

        $totalWait = array_sum($this->metrics['truck_wait_time']);
        $totalMinerIdle = array_sum($this->metrics['miner_idle_time']);

        $avgDistance = $this->metrics['trips_count'] > 0
            ? round($this->metrics['total_distance'] / $this->metrics['trips_count'], 2)
            : 0;

        $totalAll = $this->metrics['total_distance'] + $this->metrics['total_empty'];
        $emptyPct = $totalAll > 0 ? round(($this->metrics['total_empty'] / $totalAll) * 100, 1) : 0;

        return [
            'label'              => 'Симуляция (наша система)',
            'avg_distance'       => $avgDistance,
            'total_empty_km'     => round($this->metrics['total_empty'], 1),
            'empty_pct'          => $emptyPct,
            'truck_kio'          => $totalTruckAvailable > 0 ? round(($totalTruckBusy / $totalTruckAvailable) * 100, 1) : 0,
            'miner_kio'          => $totalMinerAvailable > 0 ? round(($totalMinerBusy / $totalMinerAvailable) * 100, 1) : 0,
            'truck_pause_min'    => round($totalWait), // в симуляции = время ожидания
            'avg_truck_pause'    => $truckCount > 0 ? round($totalWait / $truckCount) : 0,
            'miner_idle_min'     => round($totalMinerIdle),
            'avg_miner_idle'     => $minerCount > 0 ? round($totalMinerIdle / $minerCount) : 0,
            'trips_count'        => $this->metrics['trips_count'],
            'total_volume'       => round($this->metrics['total_volume'], 1),
            'truck_count'        => $truckCount,
            'miner_count'        => $minerCount,
        ];
    }
}