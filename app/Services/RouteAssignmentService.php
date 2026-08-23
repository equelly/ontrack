<?php

namespace App\Services;

use App\Models\Truck;
use App\Models\MiningOrder;
use App\Models\TruckTrip;
use App\Models\Zone;
use App\Models\Miner;
use App\Models\MinerDumpDistance;
use App\Events\DriverRouteUpdated;
use App\Events\DispatcherNotification;
use App\Events\ExcavatorNotification;
use App\Exceptions\NoRouteAvailableException;
use App\Domain\RouteBlockReason;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


/**
 * RouteAssignmentService - назначение маршрутов грузовикам
 * 
 * Использует ТОЛЬКО активные маршруты (mining_orders.active = 1)
 * Score рассчитывается динамически
 */
class RouteAssignmentService
{
    const DEFAULT_LOADING_TIME_MINUTES = 5;
    const BUFFER_COEFFICIENT = 1.5;

    /**
     * Бизнес-правила совместимости пород при выгрузке.
     *
     * Каждая зона принимает только ОДНУ породу (смешивание запрещено).
     * Исключение: "руда_ЦПТ" (id=5) может быть выгружена также в зоны,
     * принимающие "руда" (id=1), а при их отсутствии — "руда_Sera" (id=6).
     */
    const ROCK_FALLBACK_CHAIN = [
        5 => [5, 1, 6], // "руда_ЦПТ" → "руда" → "руда_Sera"
    ];

    protected RouteOptimizerService $optimizer;

    public function __construct(RouteOptimizerService $optimizer)
    {
        $this->optimizer = $optimizer;
    }

    /**
     * Получить время погрузки для конкретного забоя
     * Приоритет: фактическое среднее > целевое > дефолт 5 минут
     */
    protected function getLoadingTimeForMiner(Miner $miner): float
    {
        $avgLoadTime = $miner->getAvgLoadTime(5);
        if ($avgLoadTime && $avgLoadTime > 0) {
            return $avgLoadTime;
        }

        if ($miner->target_load_time && $miner->target_load_time > 0) {
            return (float) $miner->target_load_time / 60;
        }

        return self::DEFAULT_LOADING_TIME_MINUTES;
    }

    /**
     * Возвращает приоритетный список пород, которые могут быть выгружены
     * на ту же зону, что и запрошенная порода.
     *
     * @return int[] Список rock_id в порядке убывания приоритета
     */
    protected function getAcceptableRockIds(int $rockId): array
    {
        return self::ROCK_FALLBACK_CHAIN[$rockId] ?? [$rockId];
    }

    /**
     * Назначить маршрут грузовику.
     *
     * @throws \App\Exceptions\NoRouteAvailableException если нет доступных маршрутов
     *         (содержит диагностику причин в getDiagnostics())
     * @throws \RuntimeException если грузовик занят или другая системная ошибка
     */
    public function assignForTruck(Truck $truck): void
    {
        Log::info('assignForTruck START', ['truck_id' => $truck->id, 'status' => $truck->status]);

        if (!in_array($truck->status, ['free', 'completed', 'to_miner'])) {
            throw new \RuntimeException("Грузовик занят (статус: {$truck->status})");
        }

        DB::transaction(function () use ($truck) {
            $activeOrders = MiningOrder::where('active', true)
                ->with(['miner.currentRock', 'dump.zones.rocks', 'zone'])
                ->get();

            Log::info('Route search before filtering', ['active_orders' => $activeOrders->count()]);

            if ($activeOrders->isEmpty()) {
                $diagnostics = [
                    'primary_reason' => RouteBlockReason::NO_ACTIVE_ORDERS,
                    'summary' => [RouteBlockReason::NO_ACTIVE_ORDERS => 1],
                    'orders' => [],
                ];
                throw new NoRouteAvailableException('Нет активных маршрутов', $diagnostics);
            }

            $filterResult = $this->filterRoutesWithAvailableZones($activeOrders, $truck);
            $availableRoutes = $filterResult['available'];
            $diagnostics = $filterResult['diagnostics'];

            Log::info('Route search after filtering', [
                'filtered' => count($availableRoutes),
                'blocked' => count($diagnostics['orders']),
            ]);

            if (empty($availableRoutes)) {
                throw new NoRouteAvailableException('Нет доступных маршрутов', $diagnostics);
            }

            $selectedRoute = $this->selectByWeightedWRR($availableRoutes);

            Log::info('Выбран маршрут', [
                'order_id' => $selectedRoute['order']->id,
                'miner_id' => $selectedRoute['order']->miner_id,
                'dump_id' => $selectedRoute['order']->dump_id,
                'zone_id' => $selectedRoute['zone']->id,
                'weight' => $selectedRoute['order']->weight,
            ]);

            $this->createTripAndAssign(
                $truck,
                $selectedRoute['order'],
                $selectedRoute['zone'],
                $selectedRoute['rock_id']
            );

            $this->notifyDriver($truck, $selectedRoute['order'], 'route_assigned');
            $this->notifyDispatcher($truck, $selectedRoute['order'], 'route_assigned');
            $this->notifyExcavator($truck, $selectedRoute['order'], 'route_assigned');

            Log::info('assignForTruck END');
        });
    }

    /**
     * Диагностика причин, по которым грузовику не может быть назначен маршрут.
     * НЕ делает назначение — только возвращает массив причин.
     *
     * @return array
     */
    public function diagnoseForTruck(Truck $truck): array
    {
        if (!in_array($truck->status, ['free', 'completed', 'to_miner'])) {
            return [
                'can_assign' => false,
                'primary_reason' => RouteBlockReason::TRUCK_BUSY,
                'summary' => [RouteBlockReason::TRUCK_BUSY => 1],
                'orders' => [],
            ];
        }

        $activeOrders = MiningOrder::where('active', true)
            ->with(['miner.currentRock', 'dump.zones.rocks', 'zone'])
            ->get();

        if ($activeOrders->isEmpty()) {
            return [
                'can_assign' => false,
                'primary_reason' => RouteBlockReason::NO_ACTIVE_ORDERS,
                'summary' => [RouteBlockReason::NO_ACTIVE_ORDERS => 1],
                'orders' => [],
            ];
        }

        $filterResult = $this->filterRoutesWithAvailableZones($activeOrders, $truck);

        return [
            'can_assign' => !empty($filterResult['available']),
            'primary_reason' => $filterResult['diagnostics']['primary_reason'],
            'summary' => $filterResult['diagnostics']['summary'],
            'orders' => $filterResult['diagnostics']['orders'],
        ];
    }

    /**
     * Проверяет, запрещена ли порода для грузовика
     */
    protected function isRockRestricted(int $truckId, int $rockId): bool
    {
        return \App\Models\TruckRestriction::where('truck_id', $truckId)
            ->where('rock_id', $rockId)
            ->exists();
    }

    /**
     * Фильтруем маршруты с доступными зонами + собираем диагностику причин отказа.
     *
     * @return array{available: array, diagnostics: array}
     */
    protected function filterRoutesWithAvailableZones($orders, Truck $truck): array
    {
        $available = [];
        $diagnosticsOrders = [];
        $summary = [];

        foreach ($orders as $order) {
            $miner = $order->miner;
            $reason = null;

            // ===== 1. ПРОВЕРКА ЗАБОЯ =====
            if (!$miner) {
                $reason = RouteBlockReason::MINER_NOT_FOUND;
            } elseif (!$miner->active) {
                $reason = RouteBlockReason::MINER_INACTIVE;
            } elseif (!$miner->isWorking()) {
                $reason = RouteBlockReason::MINER_NOT_WORKING;
            }

            // ===== 2. ПРОВЕРКА ПОРОДЫ =====
            if (!$reason) {
                $currentRock = $miner->currentRock;
                if (!$currentRock) {
                    $reason = RouteBlockReason::NO_CURRENT_ROCK;
                }
            } else {
                $currentRock = $miner?->currentRock;
            }

            // ===== 3. ПРОВЕРКА ЗАГРУЗКИ ЗАБОЯ =====
            if (!$reason && !$this->canAssignToMiner($order)) {
                $reason = RouteBlockReason::MINER_OVERLOADED;
            }

            // ===== 4. ПРОВЕРКА ОГРАНИЧЕНИЙ ГРУЗОВИКА =====
            if (!$reason && $currentRock && $this->isRockRestricted($truck->id, $currentRock->id)) {
                $reason = RouteBlockReason::ROCK_RESTRICTED;
            }

            // ===== 5. ПОИСК ЗОНЫ =====
            $zone = null;
            if (!$reason) {
                if ($order->zone_id && $order->zone) {
                    if ($order->zone->delivery && $order->zone->volume < $order->zone->capacity) {
                        $zone = $order->zone;
                    }
                }

                if (!$zone) {
                    $zone = $this->selectZoneForRock($order->dump_id, $currentRock->id);
                    if ($zone) {
                        $order->update(['zone_id' => $zone->id]);
                        $order->refresh();
                    } else {
                        $reason = RouteBlockReason::NO_AVAILABLE_ZONES;
                    }
                }
            }

            // ===== 6. РЕЗУЛЬТАТ =====
            if ($reason) {
                $diagnosticsOrders[] = [
                    'order_id' => $order->id,
                    'miner_id' => $order->miner_id,
                    'miner_name' => $miner?->name_miner ?? "Забой #{$order->miner_id}",
                    'miner_status' => $miner?->status,
                    'dump_id' => $order->dump_id,
                    'rock_id' => $currentRock?->id,
                    'rock_name' => $currentRock?->name_rock,
                    'reason' => $reason,
                    'reason_label' => RouteBlockReason::label($reason),
                    'action' => RouteBlockReason::action($reason),
                ];
                $summary[$reason] = ($summary[$reason] ?? 0) + 1;

                Log::debug("Маршрут {$order->id} пропущен: {$reason}", [
                    'miner_id' => $order->miner_id,
                    'miner_status' => $miner?->status,
                    'rock_id' => $currentRock?->id,
                ]);
            } else {
                $loadingTime = $this->getLoadingTimeForMiner($miner);
                $available[] = [
                    'order' => $order,
                    'zone' => $zone,
                    'rock_id' => $currentRock->id,
                    'weight' => $order->weight ?? 100,
                    'loading_time' => $loadingTime,
                ];
            }
        }

        $primaryReason = null;
        if (empty($available) && !empty($summary)) {
            arsort($summary);
            $primaryReason = array_key_first($summary);
        }

        $diagnostics = [
            'primary_reason' => $primaryReason,
            'summary' => $summary,
            'orders' => $diagnosticsOrders,
        ];

        return [
            'available' => $available,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * Выбор маршрута по Weighted WRR
     */
    protected function selectByWeightedWRR(array $routes): array
    {
        if (count($routes) === 0) {
            return null;
        }

        if (count($routes) === 1) {
            return $routes[0];
        }

        usort($routes, function($a, $b) {
            $baseScoreA = ($a['order']->wrr_cursor ?? 0) / max($a['weight'], 1);
            $baseScoreB = ($b['order']->wrr_cursor ?? 0) / max($b['weight'], 1);
            
            $loadingTimeSecondsA = $a['loading_time'] * 60;
            $loadingTimeSecondsB = $b['loading_time'] * 60;
            
            $lastAssignedA = $a['order']->last_assigned_at;
            $lastAssignedB = $b['order']->last_assigned_at;
            
            $secondsSinceA = $lastAssignedA ? now()->diffInSeconds($lastAssignedA) : $loadingTimeSecondsA;
            $secondsSinceB = $lastAssignedB ? now()->diffInSeconds($lastAssignedB) : $loadingTimeSecondsB;
            
            $penaltyA = ($secondsSinceA < $loadingTimeSecondsA) ? ($loadingTimeSecondsA - $secondsSinceA) * 10 : 0;
            $penaltyB = ($secondsSinceB < $loadingTimeSecondsB) ? ($loadingTimeSecondsB - $secondsSinceB) * 10 : 0;
            
            $scoreA = $baseScoreA + $penaltyA;
            $scoreB = $baseScoreB + $penaltyB;
            
            return $scoreA <=> $scoreB;
        });

        return $routes[0];
    }

    /**
     * Выбрать доступную зону для конкретной породы на отвалe.
     * Использует приоритетный список пород через getAcceptableRockIds().
     */
    public function selectZoneForRock(int $dumpId, int $rockId): ?Zone
    {
        $acceptableRockIds = $this->getAcceptableRockIds($rockId);

        foreach ($acceptableRockIds as $acceptableRockId) {
            $zone = Zone::where('dump_id', $dumpId)
                ->where('delivery', true)
                ->whereRaw('volume < capacity')
                ->whereHas('rocks', fn($q) => $q->where('rocks.id', $acceptableRockId))
                ->orderBy('volume', 'asc')
                ->first();

            if ($zone) {
                if ($acceptableRockId !== $rockId) {
                    Log::info("selectZoneForRock: fallback породы", [
                        'dump_id' => $dumpId,
                        'requested_rock_id' => $rockId,
                        'used_rock_id' => $acceptableRockId,
                        'zone_id' => $zone->id,
                        'zone_name' => $zone->name_zone,
                    ]);
                }
                return $zone;
            }
        }

        Log::info("selectZoneForRock: нет зон для всех fallback-пород", [
            'dump_id' => $dumpId,
            'checked_rock_ids' => $acceptableRockIds,
        ]);

        return null;
    }

    /**
     * Назначить маршруты всем свободным грузовикам (free или completed)
     */
    public function assignRoutesToAllFree(): int
    {
        $freeTrucks = Truck::whereIn('status', ['free', 'completed'])->get();
        $count = 0;

        foreach ($freeTrucks as $truck) {
            try {
                $this->assignForTruck($truck);
                if ($truck->fresh()->status !== 'free') {
                    $count++;
                }
            } catch (NoRouteAvailableException $e) {
                // Это нормальная ситуация — просто нет маршрутов, не логируем как ошибку
                Log::debug("assignRoutesToAllFree: нет маршрута для грузовика {$truck->id}", [
                    'reason' => $e->getPrimaryReason(),
                ]);
            } catch (\Exception $e) {
                Log::error("Ошибка назначения для грузовика {$truck->id}: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Переназначить грузовик
     */
    public function reassignTruck(Truck $truck, MiningOrder $newOrder): bool
    {
        if (in_array($truck->status, ['loading', 'unloading'])) {
            Log::warning("Нельзя переназначить грузовик {$truck->id} в статусе {$truck->status}");
            return false;
        }

        return DB::transaction(function () use ($truck, $newOrder) {
            $this->cancelCurrentTrip($truck);

            if (!$newOrder->active) {
                Log::warning("Маршрут {$newOrder->id} не активен");
                return false;
            }

            $miner = $newOrder->miner;
            $currentRock = $miner?->currentRock;

            if (!$currentRock) {
                Log::warning("Нет породы в забое {$newOrder->miner_id}");
                return false;
            }

            $zone = $this->selectZoneForRock($newOrder->dump_id, $currentRock->id);
            
            if (!$zone) {
                Log::warning("Нет доступной зоны для маршрута {$newOrder->id}");
                return false;
            }

            $this->createTripAndAssign($truck, $newOrder, $zone, $currentRock->id);

            $this->notifyDriver($truck, $newOrder, 'route_reassigned');
            $this->notifyDispatcher($truck, $newOrder, 'route_reassigned');
            $this->notifyExcavator($truck, $newOrder, 'route_reassigned');

            return true;
        });
    }

    /**
     * Отменить текущее назначение
     */
    public function cancelCurrentTrip(Truck $truck): void
    {
        $trip = TruckTrip::where('truck_id', $truck->id)
            ->whereNull('completed_at')
            ->latest()
            ->first();

        if ($trip) {
            $trip->delete();
            Log::info("Trip {$trip->id} удалён для грузовика {$truck->id}");
        }
    }

    /**
     * Создать trip и назначить маршрут
     */
    protected function createTripAndAssign(Truck $truck, MiningOrder $order, Zone $zone, ?int $rockId = null): void
    {
        Log::info('createTripAndAssign START', [
            'truck_id' => $truck->id,
            'order_id' => $order->id,
            'zone_id' => $zone->id,
        ]);

        try {
            TruckTrip::where('truck_id', $truck->id)
                ->whereNull('completed_at')
                ->update([
                    'completed_at' => now(),
                    'load_volume' => 0,
                ]);

            $trip = TruckTrip::create([
                'truck_id' => $truck->id,
                'driver_id' => $truck->driver_id,
                'miner_id' => $order->miner_id,
                'dump_id' => $order->dump_id,
                'zone_id' => $zone->id,
                'rock_id' => $rockId,
                'mining_order_id' => $order->id,
                'started_at' => now(),
            ]);

            Log::info('TruckTrip created', ['trip_id' => $trip->id]);

            if ($rockId) {
                $order->update(['rock_id' => $rockId]);
            }

            $newCursor = ($order->wrr_cursor ?? 0) + 1;
            $order->update([
                'wrr_cursor' => $newCursor,
                'last_assigned_at' => now(),
            ]);

            $truck->update(['status' => Truck::STATUS_TO_MINER]);

            Log::info("Маршрут назначен: грузовик {$truck->id} → забой {$order->miner_id} → зона {$zone->id}");

        } catch (\Exception $e) {
            Log::error('createTripAndAssign ERROR', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Переназначить зону
     */
    public function reassignZone(Truck $truck, int $newZoneId): bool
    {
        if (!in_array($truck->status, ['transporting', 'waiting_unloading'])) {
            Log::warning("Нельзя переназначить зону в статусе {$truck->status}");
            return false;
        }

        $zone = Zone::findOrFail($newZoneId);

        if (!$zone->delivery || $zone->volume >= $zone->capacity) {
            Log::warning("Зона {$newZoneId} недоступна");
            return false;
        }

        return DB::transaction(function () use ($truck, $zone) {
            $trip = TruckTrip::where('truck_id', $truck->id)
                ->whereNull('completed_at')
                ->latest()
                ->first();

            if ($trip) {
                $trip->update(['zone_id' => $zone->id]);
                
                if ($trip->miningOrder) {
                    $trip->miningOrder->update(['zone_id' => $zone->id]);
                }

                Log::info("Грузовик {$truck->id} переназначен в зону {$zone->id}");
                
                $this->notifyDriver($truck, $trip->miningOrder, 'zone_reassigned');
                $this->notifyDispatcher($truck, $trip->miningOrder, 'zone_reassigned');
            }

            return true;
        });
    }

    /**
     * Переназначить грузовики при закрытии зоны
     */
    public function reassignOnZoneClose(Zone $zone): int
    {
        Log::info("reassignOnZoneClose START for zone {$zone->id}");

        $trucks = Truck::whereIn('status', ['to_miner', 'transporting'])
            ->whereHas('trips', function ($q) use ($zone) {
                $q->where('zone_id', $zone->id)
                  ->whereNull('completed_at');
            })
            ->with(['trips' => function ($q) use ($zone) {
                $q->where('zone_id', $zone->id)
                  ->whereNull('completed_at')
                  ->with('rock');
            }])
            ->get();

        $reassignedCount = 0;

        foreach ($trucks as $truck) {
            $trip = $truck->trips->first();
            if (!$trip || !$trip->rock_id) {
                continue;
            }

            $newZone = $this->selectZoneForRock($zone->dump_id, $trip->rock_id);

            if ($newZone && $newZone->id !== $zone->id) {
                $trip->update(['zone_id' => $newZone->id]);
                
                if ($trip->miningOrder) {
                    $trip->miningOrder->update(['zone_id' => $newZone->id]);
                }

                $this->notifyDriver($truck, $trip->miningOrder, 'zone_reassigned');
                
                Log::info("Грузовик {$truck->id} переназначен из зоны {$zone->id} в зону {$newZone->id}");
                $reassignedCount++;
            }
        }

        Log::info("reassignOnZoneClose END, reassigned: {$reassignedCount}");

        return $reassignedCount;
    }

    /**
     * Проверка: можно ли назначить на miner
     */
    protected function canAssignToMiner(MiningOrder $order): bool
    {
        $miner = $order->miner;
        
        if (!$miner) {
            return false;
        }

        $recommendations = $miner->getRecommendedTruckCount();
        
        if ($recommendations) {
            $maxCount = ($recommendations['recommended'] ?? 2) + 1;
            $currentCount = $recommendations['current'] ?? $this->getCountOnMiner($miner->id);
            
            Log::debug('canAssignToMiner using recommendations', [
                'miner_id' => $miner->id,
                'recommended' => $recommendations['recommended'] ?? null,
                'current' => $currentCount,
                'max' => $maxCount,
            ]);
            
            return $currentCount < $maxCount;
        }

        $travelTime = MinerDumpDistance::where('miner_id', $order->miner_id)
            ->where('dump_id', $order->dump_id)
            ->value('travel_time_hours');

        if (!$travelTime) {
            return true;
        }

        $currentCount = $this->getCountOnMiner($order->miner_id);
        $maxCount = $this->getMaxCountForMiner($travelTime, $miner);

        return $currentCount < $maxCount;
    }

    /**
     * Количество грузовиков на miner
     */
    protected function getCountOnMiner(int $minerId): int
    {
        return TruckTrip::where('miner_id', $minerId)
            ->whereNull('completed_at')
            ->whereIn('truck_id', function ($query) {
                $query->select('id')
                    ->from('trucks')
                    ->whereIn('status', ['to_miner', 'loading', 'waiting_loading']);
            })
            ->count();
    }

    /**
     * Максимальное количество грузовиков на miner
     */
    protected function getMaxCountForMiner(float $travelTimeHours, Miner $miner): int
    {
        $travelTimeMinutes = $travelTimeHours * 60;
        $loadingTime = $this->getLoadingTimeForMiner($miner);
        
        $maxCount = ($travelTimeMinutes / $loadingTime) * self::BUFFER_COEFFICIENT;
        
        return (int) round($maxCount);
    }

    /**
     * Уведомление водителя
     */
    protected function notifyDriver(Truck $truck, MiningOrder $order, string $action = 'route_assigned'): void
    {
        if (!$truck->driver_id) {
            return;
        }

        event(new DriverRouteUpdated(
            (int) $truck->driver_id,
            [
                'truck_id' => $truck->id,
                'action' => $action,
                'order_id' => $order->id,
                'miner_id' => $order->miner_id,
                'dump_id' => $order->dump_id,
                'zone_id' => $order->zone_id,
            ]
        ));

        Log::info("DriverRouteUpdated ({$action}) sent for truck {$truck->id}");
    }

    /**
     * Уведомление диспетчера
     */
    protected function notifyDispatcher(Truck $truck, MiningOrder $order, string $action = 'route_assigned'): void
    {
        event(new DispatcherNotification(
            $truck->id,
            $action,
            [
                'order_id' => $order->id,
                'driver_id' => $truck->driver_id,
                'zone_id' => $order->zone_id,
            ]
        ));

        Log::info("DispatcherNotification ({$action}) sent for truck {$truck->id}");
    }

    /**
     * Уведомление экскаваторщика
     */
    protected function notifyExcavator(Truck $truck, MiningOrder $order, string $action = 'route_assigned'): void
    {
        if (!$order->miner_id) {
            return;
        }

        event(new ExcavatorNotification(
            $order->miner_id,
            $action,
            [
                'truck_id' => $truck->id,
                'truck_number' => $truck->number,
                'driver_name' => $truck->driver?->name,
                'status' => 'to_miner',
                'message' => $action === 'route_assigned'
                    ? "Самосвал {$truck->number} направляется к забою"
                    : "Самосвал {$truck->number} переназначен",
            ]
        ));

        Log::info("ExcavatorNotification ({$action}) sent for truck {$truck->id} to miner {$order->miner_id}");
    }
}