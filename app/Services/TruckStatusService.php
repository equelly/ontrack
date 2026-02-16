<?php

namespace App\Services;

use App\Models\Truck;
use App\Models\MiningOrder;
use App\Events\DispatcherNotification;
use App\Events\DriverRouteUpdated;  
use App\Models\TruckTrip;
use DomainException;



class TruckStatusService
{
    

public function changeStatus(Truck $truck, string $to, array $context = []): void
{
    $from = $truck->status;

    if (! $this->canTransition($from, $to)) {
        throw new DomainException("Переход {$from} → {$to} запрещён");
    }

    $truck->update([
        'status' => $to,
    ]);

    // 🔔 уведомление диспетчера (уже есть)
    event(new DispatcherNotification(
        $truck->id,
        $to,
        [
            
            'from'  => $from,
            'to'    => $to,
            'label' => \App\Domain\TruckStatus::label($to),
        ]
    ));

    /**
     * 🟢 1. Завершили рейс — закрываем MiningOrder
     */
    if ($to === 'completed') {

        $order = MiningOrder::where('truck_id', $truck->id)
            ->where('active', 1)
            ->latest()
            ->first();

        if ($order) {
            // закрываем order
            $order->update([
                'active'       => 0,
                'completed_at' => now(),
            ]);

            // ⬅️ ФИКСИРУЕМ ФАКТ РЕЙСА
            TruckTrip::create([
                'truck_id'        => $truck->id,
                'driver_id'       => $truck->driver_id,
                'miner_id'        => $order->miner_id,
                'dump_id'         => $order->dump_id,
                'mining_order_id' => $order->id,
                'load_volume'     => $order->planned_volume ?? null,
                'started_at'      => $order->created_at,
                'completed_at'    => now(),
            ]);
                app(RouteAssignmentService::class)->assignForTruck($truck);
        }
    }
}



    protected function canTransition(string $from, string $to): bool
    {
        $map = [
                // рабочий цикл
                'to_miner'     => ['loading'],
                'loading'      => ['transporting'],
                'transporting' => ['unloading'],
                'unloading'    => ['completed'],

                // completed — ТЕРМИНАЛЬНЫЙ
                'completed'    => [],

                // служебные
                'free'         => ['to_miner', 'maintenance', 'fueling'],
                'maintenance'  => ['free'],
                'fueling'      => ['free'],

                // авария
                '*'            => ['breakdown'],
                'breakdown'    => ['free'],
            ];

        // универсальный переход (авария)
        if (isset($map['*']) && in_array($to, $map['*'], true)) {
            return true;
        }

        return in_array($to, $map[$from] ?? [], true);
    }

    /* =========================
       УВЕДОМЛЕНИЯ
       ========================= */

    protected function notifyDispatcher(Truck $truck, string $old, string $new): void
    {
        event(new DispatcherNotification(
            $truck->id,
            $new,
            [
                'from' => $old,
                'to'   => $new,
            ]
        ));
    }

    protected function notifyDriver(int $driverId, array $payload): void
    {
        event(new DriverRouteUpdated(
            $driverId,
            $payload
        ));
    }

    /* =========================
       ОБРАБОТЧИКИ СТАТУСОВ
       ========================= */

    /**
     * 🚨 НЕЗАПЛАНИРОВАННАЯ поломка
     */
    protected function onBreakdown(Truck $truck): void
    {
        // Если есть активное назначение — отменяем
        $order = MiningOrder::where('truck_id', $truck->id)
            ->where('active', 1)
            ->first();

        if ($order) {
            $order->update([
                'active' => 0,
            ]);
        }

        if ($truck->driver_id) {
            $this->notifyDriver($truck->driver_id, [
                'action'   => 'route_cancelled',
                'reason'   => 'truck_breakdown',
                'truck_id' => $truck->id,
            ]);
        }
    }

    /**
     * ✅ Рейс завершён
     */
    protected function onCompleted(Truck $truck): void
    {
        $order = MiningOrder::where('truck_id', $truck->id)
            ->where('active', 1)
            ->first();

        if ($order) {
            $order->update([
                'active' => 0,
            ]);
        }

        if ($truck->driver_id) {
            $this->notifyDriver($truck->driver_id, [
                'action' => 'route_completed',
            ]);
        }
    }

    /**
     * 🟢 Грузовик свободен — можно назначать следующий маршрут
     */
    protected function onFree(Truck $truck): void
    {
        app(\App\Services\RouteAssignmentService::class)
            ->assignForTruck($truck);
    }


    /**
     * 🛠 Плановые остановки (обслуживание / заправка)
     */
    protected function onPlannedStop(Truck $truck, string $type): void
    {
        // Маршрут НЕ прерывается
        // Просто уведомляем водителя

        if ($truck->driver_id) {
            $this->notifyDriver($truck->driver_id, [
                'action' => 'planned_stop',
                'type'   => $type,
            ]);
        }
    }
}
