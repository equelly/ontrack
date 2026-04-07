<?php

namespace App\Services;

use App\Models\Miner;
use App\Models\MinerPause;
use App\Models\Truck;
use App\Models\TruckTrip;
use App\Models\MiningOrder;
use App\Events\DispatcherNotification;
use App\Events\DriverRouteUpdated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MinerStatusService - управление статусами забоев
 *
 * При поломке: грузовики перенаправляются, маршруты деактивируются
 * При плановой остановке: грузовики доезжают, новые не назначаются
 */
class MinerStatusService
{
    protected RouteSyncService $routeSync;

    public function __construct(RouteSyncService $routeSync)
    {
        $this->routeSync = $routeSync;
    }

    /**
     * Изменить статус забоя
     */
    public function changeStatus(Miner $miner, string $newStatus, ?int $changedBy = null): array
    {
        $oldStatus = $miner->status;

        Log::info('=== MinerStatusService::changeStatus START ===', [
            'miner_id' => $miner->id,
            'miner_name' => $miner->name_miner,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        // Проверяем допустимость перехода
        if (!$this->isValidTransition($oldStatus, $newStatus)) {
            Log::warning("Invalid status transition: {$oldStatus} -> {$newStatus}");
            return [
                'success' => false,
                'message' => "Недопустимый переход статуса: {$oldStatus} -> {$newStatus}",
            ];
        }

        DB::transaction(function () use ($miner, $newStatus, $oldStatus, $changedBy) {
            // Обновляем статус
            $miner->update([
                'status' => $newStatus,
                'status_changed_at' => now(),
                'status_changed_by' => $changedBy ?? Auth::id(),
            ]);

            // Логируем паузу при переходе в задержку
            if (in_array($newStatus, Miner::STATUSES_DELAYED)) {
                $this->startPause($miner, $newStatus, $changedBy);
            }

            // Завершаем паузу при возврате в работу
            if ($newStatus === Miner::STATUS_ACTIVE && in_array($oldStatus, Miner::STATUSES_DELAYED)) {
                $this->endPause($miner, $changedBy);
            }

            // Обрабатываем грузовики в зависимости от статуса
            if ($newStatus === Miner::STATUS_BREAKDOWN) {
                // Поломка - перенаправляем грузовики
                $this->redirectTrucksOnBreakdown($miner);
            }

            // Деактивируем маршруты при любой задержке
            if (in_array($newStatus, Miner::STATUSES_DELAYED)) {
                $this->deactivateRoutes($miner);
            }

            // Активируем маршруты при возвращении в работу
            if ($newStatus === Miner::STATUS_ACTIVE) {
                $this->activateRoutes($miner);
            }
        });

        // Уведомляем диспетчера
        $this->notifyDispatcher($miner, $oldStatus, $newStatus);

        Log::info('=== MinerStatusService::changeStatus END ===', [
            'miner_id' => $miner->id,
            'new_status' => $newStatus,
        ]);

        return [
            'success' => true,
            'message' => "Статус изменён: {$miner->getStatusLabel()}",
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ];
    }

    /**
     * Проверить допустимость перехода статуса
     */
    protected function isValidTransition(string $from, string $to): bool
    {
        // Любой статус можно изменить на любой другой
        // (в реальности могут быть ограничения бизнес-логики)
        $validStatuses = array_keys(Miner::getAllStatuses());

        return in_array($to, $validStatuses);
    }

    /**
     * Начать паузу забоя
     */
    protected function startPause(Miner $miner, string $type, ?int $changedBy = null): MinerPause
    {
        // Проверяем, нет ли уже активной паузы (прямой запрос к БД)
        $existingPause = MinerPause::where('miner_id', $miner->id)
            ->whereNull('ended_at')
            ->first();

        if ($existingPause) {
            Log::info("Miner {$miner->id} already has active pause", ['pause_id' => $existingPause->id]);
            return $existingPause;
        }

        $pause = MinerPause::create([
            'miner_id' => $miner->id,
            'type' => $type,
            'started_at' => now(),
            'started_by' => $changedBy ?? Auth::id(),
        ]);

        Log::info("Pause started for miner {$miner->id}", [
            'pause_id' => $pause->id,
            'type' => $type,
        ]);

        return $pause;
    }

    /**
     * Завершить активную паузу
     */
    protected function endPause(Miner $miner, ?int $changedBy = null): ?MinerPause
    {
        // Получаем активную паузу напрямую из БД (не через связь модели)
        $activePause = MinerPause::where('miner_id', $miner->id)
            ->whereNull('ended_at')
            ->first();

        if (!$activePause) {
            Log::info("No active pause found for miner {$miner->id}");
            return null;
        }

        $activePause->end($changedBy ?? Auth::id());

        Log::info("Pause ended for miner {$miner->id}", [
            'pause_id' => $activePause->id,
            'type' => $activePause->type,
            'duration_seconds' => $activePause->duration_seconds,
        ]);

        return $activePause;
    }

    /**
     * Перенаправить грузовики при поломке
     */
    protected function redirectTrucksOnBreakdown(Miner $miner): int
    {
        Log::info("Redirecting trucks due to breakdown at miner {$miner->id}");

        // Грузовики в пути к забою или на погрузке
        $trucks = Truck::whereIn('status', ['to_miner', 'loading', 'waiting_loading'])
            ->whereHas('trips', function ($q) use ($miner) {
                $q->where('miner_id', $miner->id)
                  ->whereNull('completed_at');
            })
            ->with(['trips' => function ($q) use ($miner) {
                $q->where('miner_id', $miner->id)
                  ->whereNull('completed_at');
            }])
            ->get();

        $redirectedCount = 0;

        foreach ($trucks as $truck) {
            $trip = $truck->trips->first();

            if (!$trip) {
                continue;
            }

            // Если уже загружен - отправляем на разгрузку
            if ($truck->status === 'loading' && $trip->load_volume > 0) {
                $this->sendLoadedTruckToUnloading($truck, $trip);
                $redirectedCount++;
                continue;
            }

            // Если на погрузке но не загружен - освобождаем
            if ($truck->status === 'loading') {
                $this->releaseTruckFromLoading($truck, $trip, $miner);
                $redirectedCount++;
                continue;
            }

            // Если едет к забою - отменяем и назначаем новый маршрут
            if ($truck->status === 'to_miner') {
                $this->reassignTruckToNewMiner($truck, $trip, $miner);
                $redirectedCount++;
            }
        }

        Log::info("Redirected {$redirectedCount} trucks from miner {$miner->id}");

        return $redirectedCount;
    }

    /**
     * Отправить загруженный грузовик на разгрузку
     */
    protected function sendLoadedTruckToUnloading(Truck $truck, TruckTrip $trip): void
    {
        $truck->update(['status' => 'transporting']);

        // Уведомляем водителя
        event(new DriverRouteUpdated(
            (int) $truck->driver_id,
            [
                'truck_id' => $truck->id,
                'action' => 'breakdown_continue_to_unload',
                'message' => 'Забой остановлен (поломка). Отправляйтесь на разгрузку.',
            ]
        ));

        Log::info("Truck {$truck->id} sent to unloading despite breakdown");
    }

    /**
     * Освободить грузовик с погрузки (не загружен)
     */
    protected function releaseTruckFromLoading(Truck $truck, TruckTrip $trip, Miner $miner): void
    {
        // Отменяем текущий trip
        $trip->update([
            'completed_at' => now(),
            'load_volume' => 0,
        ]);

        // Переводим в статус completed для получения нового назначения
        $truck->update(['status' => 'completed']);

        // Уведомляем водителя
        event(new DriverRouteUpdated(
            (int) $truck->driver_id,
            [
                'truck_id' => $truck->id,
                'action' => 'breakdown_cancelled',
                'message' => "Погрузка отменена (поломка на забое {$miner->name_miner}). Ожидайте нового назначения.",
            ]
        ));

        Log::info("Truck {$truck->id} released from loading due to breakdown");
    }

    /**
     * Переназначить грузовик на другой забой
     */
    protected function reassignTruckToNewMiner(Truck $truck, TruckTrip $trip, Miner $oldMiner): void
    {
        // Отменяем текущий trip
        $trip->update([
            'completed_at' => now(),
            'load_volume' => 0,
        ]);

        // Переводим в статус completed для получения нового назначения
        $truck->update(['status' => 'completed']);

        // Уведомляем водителя
        event(new DriverRouteUpdated(
            (int) $truck->driver_id,
            [
                'truck_id' => $truck->id,
                'action' => 'breakdown_reassigned',
                'message' => "Маршрут отменён (поломка на забое {$oldMiner->name_miner}). Ожидайте нового назначения.",
            ]
        ));

        Log::info("Truck {$truck->id} reassigned from miner {$oldMiner->id}");
    }

    /**
     * Деактивировать маршруты забоя
     */
    protected function deactivateRoutes(Miner $miner): int
    {
        $count = MiningOrder::where('miner_id', $miner->id)
            ->where('active', true)
            ->update(['active' => false]);

        Log::info("Deactivated {$count} routes for miner {$miner->id}");

        return $count;
    }

    /**
     * Активировать маршруты забоя
     */
    protected function activateRoutes(Miner $miner): int
    {
        // Активируем маршруты через RouteSyncService
        // Это пересоздаст маршруты для совместимых зон
        $created = 0;

        // Получаем все открытые зоны с подходящей породой
        $rockId = $miner->current_rock_id;
        if (!$rockId) {
            Log::info("Miner {$miner->id} has no current_rock_id, skipping route activation");
            return 0;
        }

        // Находим зоны с этой породой
        $zones = \App\Models\Zone::where('delivery', true)
            ->whereHas('rocks', fn($q) => $q->where('rocks.id', $rockId))
            ->get();

        foreach ($zones as $zone) {
            // Проверяем существование маршрута
            $exists = MiningOrder::where('miner_id', $miner->id)
                ->where('zone_id', $zone->id)
                ->where('rock_id', $rockId)
                ->exists();

            if (!$exists) {
                // Создаём маршрут
                $distance = \App\Models\MinerDumpDistance::where('miner_id', $miner->id)
                    ->where('dump_id', $zone->dump_id)
                    ->value('distance_km');

                MiningOrder::create([
                    'miner_id' => $miner->id,
                    'dump_id' => $zone->dump_id,
                    'zone_id' => $zone->id,
                    'rock_id' => $rockId,
                    'distance_km' => $distance,
                    'active' => false,
                    'weight' => 100,
                    'wrr_cursor' => 0,
                ]);

                $created++;
            }
        }

        Log::info("Created {$created} routes for miner {$miner->id}");

        // Запускаем оптимизацию для активации маршрутов
        $optimizer = app(RouteOptimizerService::class);
        $optimizer->optimize();

        return $created;
    }

    /**
     * Уведомить диспетчера о смене статуса
     */
    protected function notifyDispatcher(Miner $miner, string $oldStatus, string $newStatus): void
    {
        $event = in_array($newStatus, Miner::STATUSES_DELAYED) ? 'miner_delayed' : 'miner_resumed';

        event(new DispatcherNotification(
            0,
            $event,
            [
                'miner_id' => $miner->id,
                'miner_name' => $miner->name_miner,
                'old_status' => $oldStatus,
                'old_status_label' => match($oldStatus) {
                    Miner::STATUS_ACTIVE => 'В работе',
                    Miner::STATUS_BREAKDOWN => 'Поломка',
                    Miner::STATUS_MAINTENANCE => 'Обслуживание',
                    Miner::STATUS_DISMANTLING => 'Разбор забоя',
                    Miner::STATUS_ACCESS_SETUP => 'Устройство подъезда',
                    default => $oldStatus,
                },
                'new_status' => $newStatus,
                'new_status_label' => $miner->getStatusLabel(),
                'duration_minutes' => $miner->getStatusDurationMinutes(),
                'requires_action' => $newStatus === Miner::STATUS_BREAKDOWN,
            ]
        ));

        Log::info("Dispatcher notified: {$event} for miner {$miner->id}");
    }
}
