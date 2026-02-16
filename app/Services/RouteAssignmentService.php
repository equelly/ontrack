<?php

namespace App\Services;

use App\Models\Truck;
use App\Models\MiningOrder;
use App\Events\DriverRouteUpdated;
use App\Events\DispatcherNotification;
use Illuminate\Support\Facades\Log;

class RouteAssignmentService
{
    public function assignForTruck(Truck $truck): void
    {
        // 1️⃣ Грузовик должен быть свободен
        if ($truck->status !== 'completed') {
            Log::info("Truck {$truck->id} is not free, skipping assignment");
            return;
        }

        // 2️⃣ Находим лучший доступный маршрут
        $order = MiningOrder::query()
            ->where('active', 1)
            ->whereNull('truck_id')
            ->orderByDesc('score')
            ->first();

        if (! $order) {
            Log::info("No available mining orders for Truck {$truck->id}");
            return;
        }

        // 3️⃣ Назначаем маршрут
        $order->update([
            'truck_id' => $truck->id,
        ]);
        Log::info("Assigned order {$order->id} to Truck {$truck->id}");

        // 4️⃣ Обновляем статус грузовика
        $truck->update([
            'status' => 'to_miner',
        ]);
Log::info("{$truck}");
        // 5️⃣ Уведомляем водителя
        if ($truck->driver_id) {
            $driverId = (int)$truck->driver_id; // 👈 приведение к числу
            event(new DriverRouteUpdated(
                $driverId,
                [
                    'action'   => 'route_assigned',
                    'order_id' => $order->id,
                    'miner_id' => $order->miner_id,
                    'dump_id'  => $order->dump_id,
                    'score'    => $order->score,
                ]
            ));
            Log::info("DriverRouteUpdated event sent to driver {$driverId}");
        } else {
            Log::warning("Truck {$truck->id} has no driver assigned");
        }

        // 6️⃣ Уведомляем диспетчера
        event(new DispatcherNotification(
            $truck->id,
            'route_assigned',
            [
                'order_id'  => $order->id,
                'driver_id' => $truck->driver_id,
                'score'     => $order->score,
            ]
        ));
        Log::info("DispatcherNotification event sent for Truck {$truck->id}");
    }
}
