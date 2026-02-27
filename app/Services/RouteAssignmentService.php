<?php

namespace App\Services;

use App\Models\Truck;
use App\Models\MiningOrder;
use App\Models\TruckTrip;
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
        if ($truck->status !== 'free') {
            Log::info("Грузовик {$truck->id} не свободен", ['status' => $truck->status]);
            return;
        }

        DB::transaction(function () use ($truck) {

            $routes = MiningOrder::query()
                ->where('active', 1)
                ->join('miner_dump_distances', function ($join) {
                    $join->on('mining_orders.miner_id', '=', 'miner_dump_distances.miner_id')
                         ->on('mining_orders.dump_id', '=', 'miner_dump_distances.dump_id');
                })
                ->select('mining_orders.*', 'miner_dump_distances.travel_time_hours')
                ->lockForUpdate()
                ->get();

            if ($routes->isEmpty()) {
                Log::info("Нет доступных маршрутов для грузовика {$truck->id}");
                return;
            }

            $availableRoutes = $routes->filter(fn($route) => $this->canAssignToMiner($route));

            if ($availableRoutes->isEmpty()) {
                Log::info("Все miner перегружены для грузовика {$truck->id}");
                return;
            }

            $order = $this->selectByWRR($availableRoutes);

            if (!$order) {
                Log::info("Не удалось выбрать маршрут для грузовика {$truck->id}");
                return;
            }

            $this->createTripAndAssign($truck, $order);
            $this->notifyDriver($truck, $order);
            $this->notifyDispatcher($truck, $order, 'route_assigned');

        });
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

        DB::transaction(function () use ($truck, $newOrder) {

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

            // 4. Создаём новый trip и назначаем
            $this->createTripAndAssign($truck, $newOrder);

            // 5. Уведомляем водителя о НОВОМ маршруте
            $this->notifyDriver($truck, $newOrder, 'route_reassigned');

            // 6. Уведомляем диспетчера
            $this->notifyDispatcher($truck, $newOrder, 'route_reassigned');

        });

        return true;
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
    protected function createTripAndAssign(Truck $truck, MiningOrder $order): void
    {
        Log::debug('createTripAndAssign called', [
            'order_id' => $order->id,
            'old_cursor' => $order->wrr_cursor,
            'score' => $order->score,
        ]);

        // Создаём truck_trip
        TruckTrip::create([
            'truck_id' => $truck->id,
            'driver_id' => $truck->driver_id,
            'miner_id' => $order->miner_id,
            'dump_id' => $order->dump_id,
            'mining_order_id' => $order->id,
            'started_at' => now(),
        ]);

        // Обновляем маршрут
        $newCursor = ($order->wrr_cursor ?? 0) + $order->score;
        
        $order->update([
            'truck_id' => $truck->id,
            'last_assigned_at' => now(),
            'wrr_cursor' => $newCursor,
        ]);

        Log::debug('createTripAndAssign after update', [
            'order_id' => $order->id,
            'new_cursor' => $newCursor,
            'fresh_cursor' => $order->fresh()->wrr_cursor,
        ]);

        // Обновляем статус грузовика
        $truck->update([
            'status' => 'to_miner',
        ]);

        Log::info("Маршрут {$order->id} назначен грузовику {$truck->id}");
    }

    /**
     * Проверка: можно ли назначить на miner
     */
    protected function canAssignToMiner($route): bool
    {
        // Если travel_time_hours не задан — пропускаем маршрут
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
                'score' => $order->score,
            ]
        ));

        Log::info("DispatcherNotification ({$action}) sent for truck {$truck->id}");
    }
}