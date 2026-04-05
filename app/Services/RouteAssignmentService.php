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
use Illuminate\Support\Collection;
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
    const LOADING_TIME_MINUTES = 5;
    const BUFFER_COEFFICIENT = 1.5;

    protected RouteOptimizerService $optimizer;

    public function __construct(RouteOptimizerService $optimizer)
    {
        $this->optimizer = $optimizer;
    }

    /**
     * Назначить маршрут грузовику
     * 
     * Вызывается из TruckStatusService::onToMiner() после смены статуса на 'to_miner'
     * Также может вызываться для грузовиков в статусе 'free' или 'completed'
     */
    public function assignForTruck(Truck $truck): void
    {
        Log::info('assignForTruck START', ['truck_id' => $truck->id, 'status' => $truck->status]);

        // Разрешаем назначение для статусов: free, completed, to_miner
        if (!in_array($truck->status, ['free', 'completed', 'to_miner'])) {
            Log::info("Грузовик {$truck->id} занят, нельзя назначить маршрут", ['status' => $truck->status]);
            return;
        }

        DB::transaction(function () use ($truck) {
            // Получаем только АКТИВНЫЕ маршруты
            $activeOrders = MiningOrder::where('active', true)
                ->with(['miner.currentRock', 'miner.rocks', 'dump.zones.rocks'])
                ->get();

            Log::info('Активных маршрутов', ['count' => $activeOrders->count()]);

            if ($activeOrders->isEmpty()) {
                Log::info("Нет активных маршрутов для грузовика {$truck->id}");
                return;
            }

            // Фильтруем маршруты с доступными зонами
            $availableRoutes = $this->filterRoutesWithAvailableZones($activeOrders);

            Log::info('Доступных маршрутов', ['count' => count($availableRoutes)]);

            if (empty($availableRoutes)) {
                Log::info("Нет маршрутов с доступными зонами для грузовика {$truck->id}");
                return;
            }

            // Выбираем маршрут по WRR с учётом весов
            $selectedRoute = $this->selectByWeightedWRR($availableRoutes);

            if (!$selectedRoute) {
                Log::info("Не удалось выбрать маршрут для грузовика {$truck->id}");
                return;
            }

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

            Log::info('assignForTruck END');
        });
    }

    /**
     * Фильтруем маршруты с доступными зонами
     * 
     * @param Collection<int, MiningOrder> $orders
     * @return array<int, array{order: MiningOrder, zone: Zone, rock_id: int, weight: int}>
     */
    protected function filterRoutesWithAvailableZones(Collection $orders): array
    {
        $available = [];

        foreach ($orders as $order) {
            $miner = $order->miner;
            
            if (!$miner) {
                Log::debug("Маршрут {$order->id}: нет забоя");
                continue;
            }
            
            if (!$miner->active) {
                Log::debug("Маршрут {$order->id}: забой {$miner->id} не активен");
                continue;
            }

            // Получаем текущую породу забоя (или первую доступную как fallback)
            $currentRock = $miner->currentRock;
            
            if (!$currentRock) {
                // Fallback: берём первую породу из доступных в забое
                $currentRock = $miner->rocks->first();
                if ($currentRock) {
                    Log::debug("Маршрут {$order->id}: используем fallback породу {$currentRock->name_rock} для забоя {$miner->id}");
                }
            }
            
            if (!$currentRock) {
                Log::debug("Маршрут {$order->id}: нет пород в забое {$miner->id}");
                continue;
            }

            // Проверяем, можно ли назначить на этот забой
            if (!$this->canAssignToMiner($order)) {
                Log::debug("Маршрут {$order->id}: нельзя назначить на забой {$miner->id} (лимит грузовиков)");
                continue;
            }

            // Ищем доступную зону для текущей породы
            $zone = $this->selectZoneForRock($order->dump_id, $currentRock->id);

            if (!$zone) {
                Log::debug("Маршрут {$order->id}: нет доступной зоны для породы {$currentRock->name_rock} на перегрузке {$order->dump_id}");
                continue;
            }

            Log::debug("Маршрут {$order->id}: ПОДХОДИТ", [
                'miner' => $miner->name_miner,
                'rock' => $currentRock->name_rock,
                'zone' => $zone->name_zone,
            ]);

            $available[] = [
                'order' => $order,
                'zone' => $zone,
                'rock_id' => $currentRock->id,
                'weight' => $order->weight ?? 100,
            ];
        }

        return $available;
    }

    /**
     * Выбор маршрута по Weighted WRR
     * Учитывает weight, wrr_cursor и last_assigned_at (чтобы не отправлять один за другим)
     */
    protected function selectByWeightedWRR(array $routes): ?array
    {
        if (count($routes) === 0) {
            return null;
        }

        if (count($routes) === 1) {
            return $routes[0];
        }

        $loadingTimeSeconds = self::LOADING_TIME_MINUTES * 60;

        // Сортируем с учётом:
        // 1. last_assigned_at - если прошло меньше времени погрузки, повышаем score
        // 2. wrr_cursor / weight - базовый WRR
        usort($routes, function($a, $b) use ($loadingTimeSeconds) {
            $baseScoreA = ($a['order']->wrr_cursor ?? 0) / max($a['weight'], 1);
            $baseScoreB = ($b['order']->wrr_cursor ?? 0) / max($b['weight'], 1);
            
            // Штраф за недавнее назначение (чтобы не отправлять один за другим)
            $lastAssignedA = $a['order']->last_assigned_at;
            $lastAssignedB = $b['order']->last_assigned_at;
            
            $secondsSinceA = $lastAssignedA ? now()->diffInSeconds($lastAssignedA) : $loadingTimeSeconds;
            $secondsSinceB = $lastAssignedB ? now()->diffInSeconds($lastAssignedB) : $loadingTimeSeconds;
            
            // Если прошло меньше времени погрузки - добавляем штраф
            $penaltyA = ($secondsSinceA < $loadingTimeSeconds) ? ($loadingTimeSeconds - $secondsSinceA) * 10 : 0;
            $penaltyB = ($secondsSinceB < $loadingTimeSeconds) ? ($loadingTimeSeconds - $secondsSinceB) * 10 : 0;
            
            $scoreA = $baseScoreA + $penaltyA;
            $scoreB = $baseScoreB + $penaltyB;
            
            return $scoreA <=> $scoreB;
        });

        // Берём первый (наименее использованный с учётом веса и без штрафа)
        return $routes[0];
    }

    /**
     * Выбрать зону для конкретной породы
     */
    public function selectZoneForRock(int $dumpId, int $rockId): ?Zone
    {
        return Zone::where('dump_id', $dumpId)
            ->where('delivery', true)
            ->whereHas('rocks', function($q) use ($rockId) {
                $q->where('rocks.id', $rockId);
            })
            ->whereRaw('volume < capacity')
            ->orderBy('volume', 'asc') // Менее заполненные сначала
            ->first();
    }

    /**
     * Назначить маршруты всем свободным грузовикам (free или completed)
     */
    public function assignRoutesToAllFree(): int
    {
        // Ищем грузовики в статусе free (в отстое) или completed (ждут назначения)
        $freeTrucks = Truck::whereIn('status', ['free', 'completed'])->get();
        $count = 0;

        foreach ($freeTrucks as $truck) {
            try {
                $this->assignForTruck($truck);
                if ($truck->fresh()->status !== 'free') {
                    $count++;
                }
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
            // Завершаем старые незавершённые trip
            TruckTrip::where('truck_id', $truck->id)
                ->whereNull('completed_at')
                ->update([
                    'completed_at' => now(),
                    'load_volume' => 0,
                ]);

            // Создаём новый trip
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

            // Обновляем wrr_cursor и last_assigned_at
            $newCursor = ($order->wrr_cursor ?? 0) + 1;
            $order->update([
                'wrr_cursor' => $newCursor,
                'last_assigned_at' => now(),
            ]);

            // Обновляем статус грузовика
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
        $travelTime = MinerDumpDistance::where('miner_id', $order->miner_id)
            ->where('dump_id', $order->dump_id)
            ->value('travel_time_hours');

        if (!$travelTime) {
            return false;
        }

        $currentCount = $this->getCountOnMiner($order->miner_id);
        $maxCount = $this->getMaxCountForMiner($travelTime);

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
                    ->whereIn('status', ['to_miner', 'loading']);
            })
            ->count();
    }

    /**
     * Максимальное количество грузовиков на miner
     */
    protected function getMaxCountForMiner(float $travelTimeHours): int
    {
        $travelTimeMinutes = $travelTimeHours * 60;
        $maxCount = ($travelTimeMinutes / self::LOADING_TIME_MINUTES) * self::BUFFER_COEFFICIENT;
        
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
}
