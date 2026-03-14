<?php

namespace App\Services;

use App\Models\Truck;
use App\Models\MiningOrder;
use App\Models\TruckTrip;
use App\Models\Zone;
use App\Models\Miner;
use App\Events\DriverRouteUpdated;
use App\Events\DispatcherNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RouteAssignmentService
{
    const LOADING_TIME_MINUTES = 5;
    const BUFFER_COEFFICIENT = 1.5;

    /**
     * Назначить маршрут грузовику
     */
    public function assignForTruck(Truck $truck): void
    {
        Log::info('assignForTruck START', ['truck_id' => $truck->id, 'status' => $truck->status]);

        if ($truck->status !== 'free') {
            Log::info("Грузовик {$truck->id} не свободен", ['status' => $truck->status]);
            return;
        }

        DB::transaction(function () use ($truck) {
            Log::info('Inside transaction');

            // Получаем все активные маршруты с расстояниями
            $routes = MiningOrder::query()
                ->where('active', 1)
                ->whereNotNull('rock_id')
                ->join('miner_dump_distances', function ($join) {
                    $join->on('mining_orders.miner_id', '=', 'miner_dump_distances.miner_id')
                        ->on('mining_orders.dump_id', '=', 'miner_dump_distances.dump_id');
                })
                ->select('mining_orders.*', 'miner_dump_distances.travel_time_hours')
                ->lockForUpdate()
                ->get();

            Log::info('Routes found', ['count' => $routes->count()]);

            if ($routes->isEmpty()) {
                Log::info("Нет доступных маршрутов для грузовика {$truck->id}");
                return;
            }

            // Фильтруем маршруты по текущей породе в забое
            $routesWithCurrentRock = $routes->filter(function($route) {
                $miner = Miner::with('rocks')->find($route->miner_id);
                $currentRock = $miner?->rocks->first();
                
                if (!$currentRock) {
                    Log::debug("Нет породы в забое {$route->miner_id}");
                    return false;
                }
                
                // Порода маршрута должна совпадать с текущей породой в забое
                $matches = $route->rock_id == $currentRock->id;
                
                if (!$matches) {
                    Log::debug("Порода не совпадает для маршрута {$route->id}", [
                        'route_rock_id' => $route->rock_id,
                        'current_rock_id' => $currentRock->id,
                        'miner_id' => $route->miner_id,
                    ]);
                }
                
                return $matches;
            });

            Log::info('Routes after rock filter', ['count' => $routesWithCurrentRock->count()]);

            if ($routesWithCurrentRock->isEmpty()) {
                Log::info("Нет маршрутов с текущей породой для грузовика {$truck->id}");
                return;
            }

            // Фильтруем маршруты с доступными зонами
            $availableRoutes = $routesWithCurrentRock->filter(function($route) {
                if (!$this->canAssignToMiner($route)) {
                    return false;
                }
                
                $zone = $this->selectZone($route);
                if (!$zone) {
                    Log::info("Нет доступной зоны для маршрута {$route->id}");
                    return false;
                }
                
                $route->selected_zone = $zone;
                return true;
            });

            Log::info('Available routes after filter', ['count' => $availableRoutes->count()]);

            if ($availableRoutes->isEmpty()) {
                Log::info("Нет маршрутов с доступными зонами для грузовика {$truck->id}");
                return;
            }

            $order = $this->selectByWRR($availableRoutes);

            Log::info('Selected order', ['order_id' => $order?->id, 'zone_id' => $order?->selected_zone?->id]);

            if (!$order) {
                Log::info("Не удалось выбрать маршрут для грузовика {$truck->id}");
                return;
            }

            Log::info('Before createTripAndAssign', [
                'order_id' => $order->id,
                'zone_id' => $order->selected_zone->id
            ]);

            $this->createTripAndAssign($truck, $order, $order->selected_zone);

            Log::info('After createTripAndAssign');

            $this->notifyDriver($truck, $order, 'route_assigned');
            $this->notifyDispatcher($truck, $order, 'route_assigned');

            Log::info('assignForTruck END');
        });
    }

    /**
     * Выбрать зону для маршрута
     */
    protected function selectZone(MiningOrder $order): ?Zone
    {
        return Zone::where('dump_id', $order->dump_id)
            ->where('delivery', true)
            ->whereHas('rocks', function($q) use ($order) {
                $q->where('rocks.id', $order->rock_id);
            })
            ->whereRaw('volume < capacity')
            ->orderBy('volume', 'asc') // Меньше заполнена = выше приоритет
            ->first();
    }

    /**
     * Переназначить грузовик на другой маршрут (диспетчер)
     */
    public function reassignTruck(Truck $truck, MiningOrder $newOrder): bool
    {
        // Нельзя переназначить если идёт загрузка или разгрузка
        if (in_array($truck->status, ['loading', 'unloading'])) {
            Log::warning("Нельзя переназначить грузовик {$truck->id} в статусе {$truck->status}");
            return false;
        }

        return DB::transaction(function () use ($truck, $newOrder) {

            // 1. Удаляем текущий trip если есть
            $this->cancelCurrentTrip($truck);

            // 2. Получаем travel_time для нового маршрута
            $newOrderWithTime = MiningOrder::query()
                ->where('mining_orders.id', $newOrder->id)
                ->join('miner_dump_distances', function ($join) {
                    $join->on('mining_orders.miner_id', '=', 'miner_dump_distances.miner_id')
                         ->on('mining_orders.dump_id', '=', 'miner_dump_distances.dump_id');
                })
                ->select('mining_orders.*', 'miner_dump_distances.travel_time_hours')
                ->first();

            // 3. Проверяем нагрузку на miner нового маршрута
            if (!$this->canAssignToMiner($newOrderWithTime)) {
                Log::warning("Miner {$newOrder->miner_id} перегружен, переназначение невозможно");
                return false;
            }

            // 4. Выбираем зону
            $zone = $this->selectZone($newOrder);
            if (!$zone) {
                Log::warning("Нет доступной зоны для маршрута {$newOrder->id}");
                return false;
            }

            // 5. Создаём новый trip и назначаем
            $this->createTripAndAssign($truck, $newOrder, $zone);

            // 6. Уведомляем водителя о НОВОМ маршруте
            $this->notifyDriver($truck, $newOrder, 'route_reassigned');

            // 7. Уведомляем диспетчера
            $this->notifyDispatcher($truck, $newOrder, 'route_reassigned');

            return true;
        });
    }

    /**
     * Отменить текущее назначение (breakdown или при переназначении)
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
    protected function createTripAndAssign(Truck $truck, MiningOrder $order, Zone $zone): void
    {
        Log::info('createTripAndAssign START', [
            'truck_id' => $truck->id,
            'order_id' => $order->id,
            'zone_id' => $zone->id,
        ]);

        try {
            // Создаём truck_trip
            $trip = TruckTrip::create([
                'truck_id' => $truck->id,
                'driver_id' => $truck->driver_id,
                'miner_id' => $order->miner_id,
                'dump_id' => $order->dump_id,
                'zone_id' => $zone->id,
                'mining_order_id' => $order->id,
                'started_at' => now(),
            ]);

            Log::info('TruckTrip created', ['trip_id' => $trip->id]);

            // Обновляем маршрут через DB - ТОЛЬКО нужные поля!
            $newCursor = ($order->wrr_cursor ?? 0) + $order->score;
            
            DB::table('mining_orders')
                ->where('id', $order->id)
                ->update([
                    'truck_id' => $truck->id,
                    'zone_id' => $zone->id,
                    'last_assigned_at' => now(),
                    'wrr_cursor' => $newCursor,
                    'updated_at' => now(),
                ]);

            Log::info('MiningOrder updated', ['order_id' => $order->id]);

            // Обновляем статус грузовика
            $truck->update([
                'status' => 'to_miner',
            ]);

            Log::info("Маршрут {$order->id} назначен грузовику {$truck->id} в зону {$zone->id}");

        } catch (\Exception $e) {
            Log::error('createTripAndAssign ERROR', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Получить доступные зоны для переназначения водителем
     */
    public function getAvailableZonesForReassign(int $rockId, ?int $excludeZoneId = null): array
    {
        $query = Zone::where('delivery', true)
            ->whereHas('rocks', function($q) use ($rockId) {
                $q->where('rocks.id', $rockId);
            })
            ->whereRaw('volume < capacity');

        if ($excludeZoneId) {
            $query->where('id', '!=', $excludeZoneId);
        }

        return $query->with('dump', 'rocks')
            ->orderBy('volume', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Переназначить зону (водителем)
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
     * Проверка: можно ли назначить на miner
     */
    protected function canAssignToMiner($route): bool
    {
        if (empty($route->travel_time_hours)) {
            Log::warning("travel_time_hours не задан для маршрута {$route->id}");
            return false;
        }

        $currentCount = $this->getCountOnMiner($route->miner_id);
        $maxCount = $this->getMaxCountForMiner($route->travel_time_hours);

        return $currentCount < $maxCount;
    }

    /**
     * Количество грузовиков на miner (to_miner + loading)
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
     * Выбор маршрута по WRR
     */
    protected function selectByWRR($routes): ?MiningOrder
    {
        return $routes->sortBy(function ($route) {
            $score = max($route->score ?? 1, 1);
            return ($route->wrr_cursor ?? 0) / $score;
        })->first();
    }

    /**
     * Уведомление водителя
     */
    protected function notifyDriver(Truck $truck, MiningOrder $order, string $action = 'route_assigned'): void
    {
        if (!$truck->driver_id) {
            Log::warning("Truck {$truck->id} has no driver");
            return;
        }

        event(new DriverRouteUpdated(
            (int) $truck->driver_id,
            [
                'action' => $action,
                'order_id' => $order->id,
                'miner_id' => $order->miner_id,
                'dump_id' => $order->dump_id,
                'zone_id' => $order->zone_id,
                'score' => $order->score,
            ]
        ));

        Log::info("DriverRouteUpdated ({$action}) sent to driver {$truck->driver_id}");
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
                'score' => $order->score,
            ]
        ));

        Log::info("DispatcherNotification ({$action}) sent for truck {$truck->id}");
    }
}