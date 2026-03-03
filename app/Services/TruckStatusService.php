<?php

namespace App\Services;

use App\Models\Truck;
use App\Models\TruckTrip;
use App\Models\MiningOrder;
use App\Events\DispatcherNotification;
use App\Events\DriverRouteUpdated;
use DomainException;
use Illuminate\Support\Facades\Log;


class TruckStatusService
{
    protected RouteAssignmentService $assignmentService;

    public function __construct(RouteAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    public function changeStatus(Truck $truck, string $to, array $context = []): void
    {
        $from = $truck->status;

        if (!$this->canTransition($from, $to)) {
            throw new DomainException("Переход {$from} → {$to} запрещён");
        }

        $truck->update(['status' => $to]);

        $this->notifyDispatcher($truck, $from, $to);

        switch ($to) {
            case 'breakdown':
                $this->onBreakdown($truck);
                break;

            case 'completed':
                $this->onCompleted($truck);
                break;

            case 'free':
                $this->onFree($truck);
                break;

            case 'maintenance':
            case 'fueling':
                $this->onPlannedStop($truck, $to);
                break;
        }
    }

    protected function canTransition(string $from, string $to): bool
    {
        $map = [
            'to_miner'     => ['loading'],
            'loading'      => ['transporting'],
            'transporting' => ['unloading'],
            'unloading'    => ['completed'],
            'completed'    => [],
            'free'         => ['to_miner', 'maintenance', 'fueling'],
            'maintenance'  => ['free'],
            'fueling'      => ['free'],
            '*'            => ['breakdown'],
            'breakdown'    => ['free'],
        ];

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
                'label'=> \App\Domain\TruckStatus::label($new),
            ]
        ));
    }

    protected function notifyDriver(int $driverId, array $payload): void
    {
        event(new DriverRouteUpdated($driverId, $payload));
    }

    /* =========================
       ОБРАБОТЧИКИ СТАТУСОВ
       ========================= */

    protected function onBreakdown(Truck $truck): void
    {
        // 1. Отменяем текущее назначение
        $this->assignmentService->cancelCurrentTrip($truck);

        // 2. Освобождаем mining_order (НЕ деактивируем!)
        $order = MiningOrder::where('truck_id', $truck->id)->first();

        if ($order) {
            $order->update(['truck_id' => null]);
        }

        // 3. Уведомляем водителя
        if ($truck->driver_id) {
            $this->notifyDriver($truck->driver_id, [
                'action'   => 'route_cancelled',
                'reason'   => 'truck_breakdown',
                'truck_id' => $truck->id,
            ]);
        }
    }

    protected function onCompleted(Truck $truck): void
    {
        // 1. Завершаем truck_trip
        $trip = TruckTrip::where('truck_id', $truck->id)
            ->whereNull('completed_at')
            ->latest()
            ->first();

        if ($trip) {
            // Получаем объём загрузки
            $loadVolume = $trip->miningOrder->planned_volume ?? $truck->load_capacity ?? 0;
            
            $trip->update([
                'completed_at' => now(),
                'load_volume'  => $loadVolume,
            ]);
            
            // 2. Обновляем статистику dump
            $dump = $trip->dump;
            if ($dump) {
                $dump->increment('trips_count');
                $dump->increment('delivered_volume', $loadVolume);
                
                Log::info("Dump {$dump->id} updated", [
                    'trips_count' => $dump->trips_count,
                    'delivered_volume' => $dump->delivered_volume,
                ]);
            }
        }

        // 3. Освобождаем mining_order (НЕ деактивируем!)
        $order = MiningOrder::where('truck_id', $truck->id)->first();

        if ($order) {
            $order->update(['truck_id' => null]);
        }

        // 4. Уведомляем водителя
        if ($truck->driver_id) {
            $this->notifyDriver($truck->driver_id, [
                'action' => 'route_completed',
            ]);
        }

        // 5. Переводим в free и назначаем следующий маршрут
        $truck->update(['status' => 'free']);
        $this->onFree($truck);
    }

    protected function onFree(Truck $truck): void
    {
        // Назначаем следующий маршрут
        $this->assignmentService->assignForTruck($truck);
    }

    protected function onPlannedStop(Truck $truck, string $type): void
    {
        // Отменяем текущее назначение
        $this->assignmentService->cancelCurrentTrip($truck);

        // Освобождаем mining_order
        $order = MiningOrder::where('truck_id', $truck->id)->first();

        if ($order) {
            $order->update(['truck_id' => null]);
        }

        if ($truck->driver_id) {
            $this->notifyDriver($truck->driver_id, [
                'action' => 'planned_stop',
                'type'   => $type,
            ]);
        }
    }
}