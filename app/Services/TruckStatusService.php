<?php

namespace App\Services;

use App\Models\Truck;
use App\Models\TruckTrip;
use App\Models\MiningOrder;
use App\Models\Zone;
use App\Events\DispatcherNotification;
use App\Events\DriverRouteUpdated;
use App\Events\TruckStartedLoading;
use App\Events\ZoneChanged;
use App\Events\NoZoneAvailable;
use App\Domain\TruckStatus;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TruckStatusService
{
    const TRAIN_CAPACITY = 380; // куб.м в одном ж.д. составе

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

            case 'waiting_loading':
                $this->onWaitingLoading($truck, $context);
                break;

            case 'waiting_unloading':
                $this->onWaitingUnloading($truck, $context);
                break;

            case 'delayed':
                $this->onDelayed($truck, $context);
                break;

            case 'loading':
                $this->onLoading($truck, $context);
                break;
        }
    }

    protected function canTransition(string $from, string $to): bool
    {
        $map = [
            'free'              => ['to_miner', 'maintenance', 'fueling'],
            'to_miner'          => ['loading', 'delayed', 'breakdown'],
            'loading'           => ['transporting', 'waiting_loading', 'breakdown'],
            'transporting'      => ['unloading', 'delayed', 'breakdown'],
            'unloading'         => ['completed', 'waiting_unloading', 'breakdown'],
            'completed'         => [],
            'waiting_loading'   => ['loading', 'breakdown'],
            'waiting_unloading' => ['unloading', 'breakdown'],
            'delayed'           => ['transporting', 'breakdown'],
            'breakdown'         => ['free'],
            'maintenance'       => ['free'],
            'fueling'           => ['free'],
        ];

        if ($to === 'breakdown') {
            return true;
        }

        return in_array($to, $map[$from] ?? [], true);
    }

    /* =========================
       НОВЫЙ МЕТОД: Начало погрузки
       ========================= */
    protected function onLoading(Truck $truck, array $context = []): void
    {
        $trip = $this->getActiveTrip($truck);
        
        if (!$trip) {
            Log::warning("Truck {$truck->id} has no active trip on loading start");
            return;
        }

        $miner = $trip->miner;
        $currentZone = $trip->zone;
        $minerRock = $miner?->rocks->first();

        // Проверяем соответствие породы
        if ($minerRock && $currentZone) {
            $zoneAcceptsRock = $currentZone->rocks->contains($minerRock->id);

            if (!$zoneAcceptsRock) {
                Log::info("Zone mismatch for truck {$truck->id}", [
                    'rock' => $minerRock->name_rock,
                    'zone' => $currentZone->name_zone,
                ]);

                // Ищем новую зону
                $newZone = $this->findAvailableZone($trip->dump_id, $minerRock->id);

                if ($newZone) {
                    $oldZoneName = $currentZone->name_zone;
                    
                    // Переназначаем зону
                    $trip->update(['zone_id' => $newZone->id]);
                    $trip->miningOrder?->update(['zone_id' => $newZone->id]);

                    Log::info("Zone reassigned for truck {$truck->id}", [
                        'old_zone' => $oldZoneName,
                        'new_zone' => $newZone->name_zone,
                    ]);

                    // Уведомляем водителя об изменении зоны
                    event(new ZoneChanged(
                        $truck,
                        $oldZoneName,
                        $newZone->name_zone,
                        $trip->dump->name_dump
                    ));
                } else {
                    // Зоны нет - уведомляем диспетчера
                    Log::warning("No available zone for truck {$truck->id}", [
                        'rock' => $minerRock->name_rock,
                    ]);
                    
                    event(new NoZoneAvailable($truck, $minerRock->name_rock));
                }
            }
        }

        // Обновляем время начала погрузки в рейсе
        $trip->update(['load_time' => now()]);

        // Уведомляем машиниста о начале погрузки
        if ($miner) {
            event(new TruckStartedLoading($truck, $miner->id));
        }
    }

    /* =========================
       НОВЫЙ МЕТОД: Поиск доступной зоны
       ========================= */
    public function findAvailableZone(int $dumpId, int $rockId): ?Zone
    {
        return Zone::where('dump_id', $dumpId)
            ->where('delivery', true)
            ->whereHas('rocks', fn($q) => $q->where('rocks.id', $rockId))
            ->whereRaw('volume < capacity')
            ->orderBy('volume', 'asc')
            ->first();
    }

    /* =========================
       НОВЫЙ МЕТОД: Получить активный рейс
       ========================= */
    protected function getActiveTrip(Truck $truck): ?TruckTrip
    {
        return $truck->trips()
            ->whereNull('completed_at')
            ->latest()
            ->first();
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
                'label'=> TruckStatus::label($new),
            ]
        ));
    }

    protected function notifyDriver(Truck $truck, array $payload): void
    {
        // Передаём truck_id для правильного канала вещания
        $payload['truck_id'] = $truck->id;
        $payload['driver_id'] = $truck->driver_id;
        
        event(new DriverRouteUpdated($truck->driver_id, $payload));
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
            $order->update(['truck_id' => null, 'zone_id' => null]);
        }

        // 3. Уведомляем водителя
        if ($truck->driver_id) {
            $this->notifyDriver($truck, [
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

            // 3. Обновляем объём в зоне (в ж.д. составах)
            $zone = $trip->zone;
            if ($zone) {
                // Переводим куб.м в доли ж.д. состава
                $volumeInTrains = $loadVolume / self::TRAIN_CAPACITY;
                $zone->increment('volume', $volumeInTrains);

                Log::info("Zone {$zone->id} volume updated", [
                    'volume_trains' => $zone->fresh()->volume,
                    'capacity_trains' => $zone->capacity,
                    'added_m3' => $loadVolume,
                    'added_trains' => round($volumeInTrains, 4),
                ]);

                // Если зона заполнена - логируем предупреждение
                if ($zone->fresh()->volume >= $zone->capacity) {
                    Log::warning("Zone {$zone->id} is FULL!", [
                        'volume' => $zone->fresh()->volume,
                        'capacity' => $zone->capacity,
                    ]);
                }
            }
        }

        // 4. Освобождаем mining_order (НЕ деактивируем!)
        $order = MiningOrder::where('truck_id', $truck->id)->first();

        if ($order) {
            $order->update([
                'truck_id' => null,
                'zone_id' => null,
            ]);
        }

        // 5. Уведомляем водителя
        if ($truck->driver_id) {
            $this->notifyDriver($truck, [
                'action' => 'route_completed',
            ]);
        }

        // 6. Переводим в free и назначаем следующий маршрут
        $truck->update(['status' => 'free']);
        $truck->refresh(); // Обновляем объект из БД
        $this->assignmentService->assignForTruck($truck);
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
            $order->update([
                'truck_id' => null,
                'zone_id' => null,
            ]);
        }

        if ($truck->driver_id) {
            $this->notifyDriver($truck, [
                'action' => 'planned_stop',
                'type'   => $type,
            ]);
        }
    }

    protected function onWaitingLoading(Truck $truck, array $context = []): void
    {
        Log::info("Truck {$truck->id} waiting for loading", $context);
        
        // Уведомление уже отправлено в notifyDispatcher
    }

    protected function onWaitingUnloading(Truck $truck, array $context = []): void
    {
        Log::info("Truck {$truck->id} waiting for unloading", $context);
        
        // Уведомление уже отправлено в notifyDispatcher
    }

    protected function onDelayed(Truck $truck, array $context = []): void
    {
        Log::info("Truck {$truck->id} delayed in transit", $context);
        
        // Уведомление уже отправлено в notifyDispatcher
    }
}
