<?php

namespace App\Services;

use App\Models\Truck;
use App\Models\MiningOrder;
use App\Events\DispatcherNotification;
use Illuminate\Support\Facades\DB;

class RebuildTruckRouteService
{
    public function rebuild(Truck $truck): void
    {
        DB::transaction(function () use ($truck) {

            /**
             * 1. Поломка грузовика
             */
            if ($truck->status === 'breakdown') {
                broadcast(new DispatcherNotification(
                    truckId: $truck->id,
                    status: $truck->status,
                    payload: [
                        'message' => 'Грузовик сообщил о поломке',
                    ]
                ));

                return;
            }

            /**
             * 2. Плановое обслуживание / заправка
             * Маршрут не пересобираем, ждём завершения текущего рейса
             */
            if (in_array($truck->status, ['maintenance', 'fueling'], true)) {
                broadcast(new DispatcherNotification(
                    truckId: $truck->id,
                    status: $truck->status,
                    payload: [
                        'message' => 'Грузовик направлен на обслуживание',
                    ]
                ));

                return;
            }

            /**
             * 3. Грузовик занят рейсом
             */
            if (in_array($truck->status, [
                'to_miner',
                'loading',
                'transporting',
                'unloading',
                'complited',
            ], true)) {
                // Никаких новых назначений
                return;
            }

            /**
             * 4. Грузовик свободен — пересобираем маршрут
             */
            if ($truck->status === 'free') {

                $orders = MiningOrder::query()
                    ->where('truck_id', $truck->id)
                    ->with([
                        'miner',
                        'dump.zones',
                    ])
                    ->orderBy('assigned_round')
                    ->get();

                $orders = $orders->filter(function ($order) {
                    return
                        $order->miner?->active === true
                        && $order->dump
                        && $order->dump->zones->contains(fn ($zone) => $zone->delivery === true);
                })->values();


                // Сброс active и пересчёт sequence
                foreach ($orders as $index => $order) {
                    $order->active = $index === 0 ? 1 : 0;
                    $order->sequence = $index + 1;
                    $order->save();
                }

                // Обновляем версию маршрута
                $truck->increment('route_version');

                broadcast(new DispatcherNotification(
                    truckId: $truck->id,
                    status: 'route_rebuilt',
                    payload: [
                        'route_version' => $truck->route_version,
                        'orders_count'  => $orders->count(),
                        'active_order'  => $orders->first()?->id,
                    ]
                ));
            }
        });
    }
}
