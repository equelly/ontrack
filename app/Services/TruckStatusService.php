<?php

namespace App\Services;

use App\Models\Truck;
use App\Models\TruckTrip;
use App\Models\TripPause;
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

    /* =========================
       ОСНОВНОЙ МЕТОД: Смена статуса
       ========================= */
    public function changeStatus(Truck $truck, string $to, array $context = []): void
    {
        $from = $truck->status;

        if (!$this->canTransition($from, $to)) {
            throw new DomainException("Переход {$from} → {$to} запрещён");
        }

        // Сохраняем предыдущий статус при поломке/задержке
        if ($to === 'breakdown' || $to === 'delayed') {
            $truck->update([
                'status' => $to,
                'before_breakdown' => $from,
                'pause_started_at' => now(),
            ]);
        } else {
            $truck->update(['status' => $to]);
        }

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
            'delayed'           => ['transporting', 'to_miner', 'breakdown'], // может вернуться в to_miner
            'breakdown'         => ['free'], // через resolveBreakdown
            'maintenance'       => ['free'],
            'fueling'           => ['free'],
        ];

        if ($to === 'breakdown') {
            return true;
        }

        return in_array($to, $map[$from] ?? [], true);
    }

    /* =========================
       НОВЫЕ МЕТОДЫ: Управление паузами
       ========================= */

    /**
     * Начать паузу рейса
     */
    protected function startPause(TruckTrip $trip, string $type, ?string $reason = null): TripPause
    {
        // Проверяем, нет ли уже активной паузы
        $existingPause = $trip->pauses()->whereNull('ended_at')->first();
        if ($existingPause) {
            Log::warning("Trip {$trip->id} already has active pause", ['pause_id' => $existingPause->id]);
            return $existingPause;
        }

        $pause = TripPause::create([
            'truck_trip_id' => $trip->id,
            'truck_id' => $trip->truck_id,
            'type' => $type,
            'reason' => $reason,
            'started_at' => now(),
        ]);

        Log::info("Pause started for trip {$trip->id}", [
            'pause_id' => $pause->id,
            'type' => $type,
            'reason' => $reason,
        ]);

        return $pause;
    }

    /**
     * Завершить активную паузу
     */
    protected function endActivePause(TruckTrip $trip): ?TripPause
    {
        $activePause = $trip->pauses()->whereNull('ended_at')->first();

        if (!$activePause) {
            return null;
        }

        $activePause->end();

        Log::info("Pause ended for trip {$trip->id}", [
            'pause_id' => $activePause->id,
            'type' => $activePause->type,
            'duration' => $activePause->duration_seconds,
        ]);

        return $activePause;
    }

    /**
     * Восстановление после поломки
     * @param bool $continueTrip true = продолжить рейс, false = отменить
     */
    public function resolveBreakdown(Truck $truck, bool $continueTrip = true): void
    {
        if ($truck->status !== 'breakdown') {
            throw new DomainException("Восстановление возможно только из статуса breakdown");
        }

        $trip = $this->getActiveTrip($truck);
        $beforeBreakdown = $truck->before_breakdown;

        Log::info('resolveBreakdown CALLED', [
            'truck_id' => $truck->id,
            'continue_trip' => $continueTrip,
            'before_breakdown' => $beforeBreakdown,
            'trip_id' => $trip?->id,
        ]);

        // Завершаем паузу
        if ($trip) {
            $this->endActivePause($trip);
        }

        if ($continueTrip && $trip && $beforeBreakdown) {
            // Продолжаем рейс - восстанавливаем статус
            $truck->update([
                'status' => $beforeBreakdown,
                'before_breakdown' => null,
                'pause_started_at' => null,
            ]);

            Log::info("Truck {$truck->id} resumed from breakdown - continuing trip as {$beforeBreakdown}");
            $this->notifyDispatcher($truck, 'breakdown', $beforeBreakdown);

        } else {
            // Отменяем рейс
            if ($trip) {
                // Завершаем все активные паузы
                foreach ($trip->pauses()->whereNull('ended_at')->get() as $pause) {
                    $pause->end();
                }

                // Просто завершаем рейс с нулевым объёмом
                $trip->update([
                    'completed_at' => now(),
                    'load_volume' => 0,
                ]);
            }

            // Освобождаем mining_order
            $order = MiningOrder::where('truck_id', $truck->id)->first();
            if ($order) {
                $order->update(['truck_id' => null, 'zone_id' => null]);
            }

            $truck->update([
                'status' => 'free',
                'before_breakdown' => null,
                'pause_started_at' => null,
            ]);

            Log::info("Truck {$truck->id} resolved from breakdown - trip cancelled, going to free");
            $this->notifyDispatcher($truck, 'breakdown', 'free');

            // Назначаем новый маршрут
            $this->assignmentService->assignForTruck($truck);
        }
    }

    /**
     * Возобновление после задержки
     */
    public function resumeFromDelay(Truck $truck): void
    {
        if ($truck->status !== 'delayed') {
            throw new DomainException("Возобновление возможно только из статуса delayed");
        }

        $trip = $this->getActiveTrip($truck);
        $previousStatus = $truck->before_breakdown ?? 'transporting';

        Log::info('resumeFromDelay CALLED', [
            'truck_id' => $truck->id,
            'previous_status' => $previousStatus,
            'trip_id' => $trip?->id,
        ]);

        // Завершаем паузу
        if ($trip) {
            $this->endActivePause($trip);
        }

        $truck->update([
            'status' => $previousStatus,
            'before_breakdown' => null,
            'pause_started_at' => null,
        ]);

        Log::info("Truck {$truck->id} resumed from delay - restored to {$previousStatus}");
        $this->notifyDispatcher($truck, 'delayed', $previousStatus);
    }

    /* =========================
       ОБРАБОТЧИКИ СТАТУСОВ
       ========================= */

    protected function onBreakdown(Truck $truck): void
    {
        $trip = $this->getActiveTrip($truck);

        Log::info("Truck {$truck->id} breakdown", [
            'trip_id' => $trip?->id,
            'before_breakdown' => $truck->before_breakdown,
        ]);

        // Начинаем паузу
        if ($trip) {
            $this->startPause($trip, TripPause::TYPE_BREAKDOWN);
        }

        // Уведомляем водителя
        if ($truck->driver_id) {
            $this->notifyDriver($truck, [
                'action'   => 'breakdown',
                'message'  => 'Поломка зарегистрирована. Маршрут сохранён.',
                'truck_id' => $truck->id,
            ]);
        }
    }

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

                $newZone = $this->findAvailableZone($trip->dump_id, $minerRock->id);

                if ($newZone) {
                    $oldZoneName = $currentZone->name_zone;

                    $trip->update(['zone_id' => $newZone->id]);
                    $trip->miningOrder?->update(['zone_id' => $newZone->id]);

                    Log::info("Zone reassigned for truck {$truck->id}", [
                        'old_zone' => $oldZoneName,
                        'new_zone' => $newZone->name_zone,
                    ]);

                    event(new ZoneChanged(
                        $truck,
                        $oldZoneName,
                        $newZone->name_zone,
                        $trip->dump->name_dump
                    ));
                } else {
                    Log::warning("No available zone for truck {$truck->id}", [
                        'rock' => $minerRock->name_rock,
                    ]);

                    event(new NoZoneAvailable($truck, $minerRock->name_rock));
                }
            }
        }

        $trip->update(['load_time' => now()]);

        if ($miner) {
            event(new TruckStartedLoading($truck, $miner->id));
        }
    }

    public function findAvailableZone(int $dumpId, int $rockId): ?Zone
    {
        return Zone::where('dump_id', $dumpId)
            ->where('delivery', true)
            ->whereHas('rocks', fn($q) => $q->where('rocks.id', $rockId))
            ->whereRaw('volume < capacity')
            ->orderBy('volume', 'asc')
            ->first();
    }

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
        $payload['truck_id'] = $truck->id;
        $payload['driver_id'] = $truck->driver_id;

        event(new DriverRouteUpdated($truck->driver_id, $payload));
    }

    /* =========================
       ДРУГИЕ ОБРАБОТЧИКИ
       ========================= */

    protected function onCompleted(Truck $truck): void
    {
        $trip = TruckTrip::where('truck_id', $truck->id)
            ->whereNull('completed_at')
            ->latest()
            ->first();

        if ($trip) {
            // Завершаем все активные паузы
            foreach ($trip->pauses()->whereNull('ended_at')->get() as $pause) {
                $pause->end();
            }

            $loadVolume = $trip->miningOrder->planned_volume ?? $truck->load_capacity ?? 0;

            $trip->update([
                'completed_at' => now(),
                'load_volume'  => $loadVolume,
            ]);

            // Логируем статистику пауз
            $pauseStats = $trip->pauses()
                ->selectRaw('type, SUM(duration_seconds) as total_seconds')
                ->groupBy('type')
                ->get()
                ->pluck('total_seconds', 'type')
                ->toArray();

            Log::info("Trip {$trip->id} completed", [
                'load_volume' => $loadVolume,
                'pause_stats' => $pauseStats,
                'total_pause_seconds' => $trip->getTotalPauseSeconds(),
            ]);

            $dump = $trip->dump;
            if ($dump) {
                $dump->increment('trips_count');
                $dump->increment('delivered_volume', $loadVolume);

                Log::info("Dump {$dump->id} updated", [
                    'trips_count' => $dump->trips_count,
                    'delivered_volume' => $dump->delivered_volume,
                ]);
            }

            $zone = $trip->zone;
            if ($zone) {
                $volumeInTrains = $loadVolume / self::TRAIN_CAPACITY;
                $zone->increment('volume', $volumeInTrains);

                Log::info("Zone {$zone->id} volume updated", [
                    'volume_trains' => $zone->fresh()->volume,
                    'capacity_trains' => $zone->capacity,
                    'added_m3' => $loadVolume,
                    'added_trains' => round($volumeInTrains, 4),
                ]);

                if ($zone->fresh()->volume >= $zone->capacity) {
                    Log::warning("Zone {$zone->id} is FULL!", [
                        'volume' => $zone->fresh()->volume,
                        'capacity' => $zone->capacity,
                    ]);
                }
            }
        }

        $order = MiningOrder::where('truck_id', $truck->id)->first();

        if ($order) {
            $order->update([
                'truck_id' => null,
                'zone_id' => null,
            ]);
        }

        if ($truck->driver_id) {
            $this->notifyDriver($truck, [
                'action' => 'route_completed',
            ]);
        }

        $truck->update(['status' => 'free']);
        $truck->refresh();
        $this->assignmentService->assignForTruck($truck);
    }

    protected function onFree(Truck $truck): void
    {
        $this->assignmentService->assignForTruck($truck);
    }

    protected function onPlannedStop(Truck $truck, string $type): void
    {
        $this->assignmentService->cancelCurrentTrip($truck);

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
    }

    protected function onWaitingUnloading(Truck $truck, array $context = []): void
    {
        Log::info("Truck {$truck->id} waiting for unloading", $context);
    }

    protected function onDelayed(Truck $truck, array $context = []): void
    {
        $trip = $this->getActiveTrip($truck);

        // Маппинг причины из UI на тип паузы
        $reasonMap = [
            'traffic' => TripPause::TYPE_TRAFFIC,
            'road_works' => TripPause::TYPE_ROAD_WORKS,
            'weather' => TripPause::TYPE_WEATHER,
            'waiting_loading' => TripPause::TYPE_WAITING_LOADING,
            'waiting_unloading' => TripPause::TYPE_WAITING_UNLOADING,
            'technical' => TripPause::TYPE_OTHER,
            'other' => TripPause::TYPE_OTHER,
        ];

        $pauseType = $reasonMap[$context['reason'] ?? 'other'] ?? TripPause::TYPE_OTHER;

        Log::info("Truck {$truck->id} delayed in transit", [
            'context' => $context,
            'pause_type' => $pauseType,
        ]);

        // Начинаем паузу
        if ($trip) {
            $this->startPause($trip, $pauseType, $context['reason'] ?? null);
        }
    }
}
