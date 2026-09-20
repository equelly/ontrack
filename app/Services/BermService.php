<?php

namespace App\Services;

use App\Models\BermRequest;
use App\Models\Zone;
use App\Models\Truck;
use App\Models\TruckTrip;
use App\Models\MiningOrder;
use App\Events\BermProgress;
use App\Events\RoutesUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BermService — управление запросами на обваловку зоны.
 *
 * Обваловка — это отсыпка предохранительного вала для безопасности
 * ведения горных работ.
 */
class BermService
{
    public function __construct(
        protected RouteAssignmentService $routeService
    ) {}

    /**
     * Создать запрос на обваловку зоны.
     */
    public function createRequest(int $zoneId, int $trucksNeeded, ?int $rockId = null, ?int $createdBy = null): BermRequest
    {
        $zone = Zone::with('dump')->findOrFail($zoneId);

        if ($trucksNeeded < 1 || $trucksNeeded > 50) {
            throw new \RuntimeException('Количество самосвалов должно быть от 1 до 50');
        }

        // Защищаем зону от создания дубликатов запросов через блокировку
        return DB::transaction(function () use ($zone, $zoneId, $trucksNeeded, $rockId, $createdBy) {
            
            // Проверяем и блокируем запись активного запроса для этой зоны
            $existing = BermRequest::activeForZone($zoneId)->lockForUpdate()->first();
            if ($existing) {
                throw new \RuntimeException("На зону «{$zone->name_zone}» уже есть активный запрос обваловки");
            }

            $request = BermRequest::create([
                'zone_id'        => $zoneId,
                'dump_id'        => $zone->dump_id,
                'trucks_needed'  => $trucksNeeded,
                'rock_id'        => $rockId,
                'status'         => BermRequest::STATUS_PENDING,
                'created_by'     => $createdBy,
            ]);

            Log::info('BermRequest created', [
                'request_id'    => $request->id,
                'zone_id'       => $zoneId,
                'zone_name'     => $zone->name_zone,
                'trucks_needed' => $trucksNeeded,
                'rock_id'        => $rockId,
            ]);

            // Пытаемся сразу назначить самосвалы внутри текущей транзакции
            $this->assignTrucks($request);

            return $request;
        });
    }

    /**
     * Отменить запрос на обваловку (мастер вручную).
     */
    public function cancelRequest(int $requestId, ?int $cancelledBy = null): BermRequest
    {
        $request = DB::transaction(function () use ($requestId, $cancelledBy) {
            // Блокируем строку от параллельных изменений счетчиков разгрузки
            $req = BermRequest::where('id', $requestId)->lockForUpdate()->firstOrFail();

            if (!$req->isActive()) {
                throw new \RuntimeException('Запрос уже неактивен');
            }

            $req->update([
                'status'       => BermRequest::STATUS_CANCELLED,
                'completed_at' => now(),
                'completed_by' => $cancelledBy,
            ]);

            return $req;
        });

        Log::info('BermRequest cancelled', [
            'request_id' => $request->id,
            'zone_id'    => $request->zone_id,
            'by'         => $cancelledBy,
        ]);

        try {
            $this->routeService->assignRoutesToAllFree();
            event(new RoutesUpdated());
        } catch (\Exception $e) {
            Log::error('BermService cancelRequest: post-cancel sync failed', ['error' => $e->getMessage()]);
        }

        return $request;
    }

    /**
     * Назначить самосвалы на активный запрос обваловки.
     */
    public function assignTrucks(?BermRequest $request = null): int
    {
        if ($request && !$request->isActive()) {
            return 0;
        }

        return DB::transaction(function () use ($request) {
            // Блокируем строки запросов для точного расчета remainingToAssign()
            if ($request) {
                $reqModel = BermRequest::where('id', $request->id)->lockForUpdate()->first();
                if (!$reqModel || !$reqModel->isActive()) {
                    return 0;
                }
                $requests = collect([$reqModel]);
            } else {
                $requests = BermRequest::active()->orderBy('created_at')->lockForUpdate()->get();
            }

            if ($requests->isEmpty()) {
                return 0;
            }

            $assigned = 0;

            foreach ($requests as $req) {
                $remaining = $req->remainingToAssign();
                if ($remaining <= 0) {
                    continue;
                }

                // ВАЖНО: Блокируем выбранные самосвалы от параллельного обычного распределения
                $freeTrucks = Truck::whereIn('status', ['free', 'completed'])
                    ->orderBy('updated_at', 'asc')
                    ->limit($remaining)
                    ->lockForUpdate()
                    ->get();

                if ($freeTrucks->isEmpty()) {
                    continue;
                }

                foreach ($freeTrucks as $truck) {
                    if ($req->remainingToAssign() <= 0) {
                        break;
                    }

                    try {
                        $this->assignTruckToBerm($truck, $req);
                        $assigned++;
                    } catch (\Exception $e) {
                        Log::debug("BermService: не удалось назначить грузовик {$truck->id} на обваловку: " . $e->getMessage());
                    }
                }
            }

            return $assigned;
        });
    }
 /**
     * Назначить конкретный самосвал на запрос обваловки.
     *
     * Использует тот же механизм, что и обычный маршрут:
     *   - Находит MiningOrder: забой → отвал зоны обваловки
     *   - Создаёт TruckTrip с zone_id зоны обваловки
     *   - Меняет статус самосвала на 'to_miner'
     *
     * ВАЖНО: для обваловки зона может быть delivery=false (она закрыта для
     * обычных маршрутов, но принимает самосвалы обваловки).
     *
     * ВАЖНО: этот метод должен вызываться ТОЛЬКО внутри транзакции,
     * потому что использует lockForUpdate() для MiningOrder. Если вызвать
     * без транзакции — lockForUpdate() сработает в auto-commit режиме и
     * блокировка сразу снимется (не будет защиты от race condition).
     *
     * @throws \RuntimeException если метод вызван без активной транзакции
     */
    protected function assignTruckToBerm(Truck $truck, BermRequest $request): void
    {
        // Защита от вызова без транзакции — lockForUpdate() без транзакции бесполезен
        if (DB::transactionLevel() === 0) {
            throw new \RuntimeException(
                'assignTruckToBerm должен вызываться внутри DB::transaction() — ' .
                'иначе lockForUpdate() не обеспечивает защиту от race condition'
            );
        }

        Log::info('assignTruckToBerm START', [
            'truck_id'    => $truck->id,
            'request_id'  => $request->id,
            'zone_id'     => $request->zone_id,
        ]);

        // Ищем подходящий MiningOrder: забой с нужной породой → отвал зоны обваловки
        $query = MiningOrder::where('active', true)
            ->where('dump_id', $request->dump_id)
            ->with(['miner.currentRock', 'dump.zones.rocks']);

        if ($request->rock_id) {
            $query->whereHas('miner', function ($q) use ($request) {
                $q->where('current_rock_id', $request->rock_id);
            });
        }

        $order = $query->lockForUpdate()->first();

        if (!$order) {
            throw new \RuntimeException('Нет подходящего маршрута для обваловки');
        }

        $miner = $order->miner;
        if (!$miner || !$miner->isWorking()) {
            throw new \RuntimeException('Забой не работает');
        }

        $currentRock = $miner->currentRock;
        if (!$currentRock) {
            throw new \RuntimeException('У забоя не задана порода');
        }

        $zone = $request->zone;

        DB::transaction(function () use ($truck, $order, $zone, $currentRock, $request) {
            // Завершаем старые незавершённые trip самосвала
            TruckTrip::where('truck_id', $truck->id)
                ->whereNull('completed_at')
                ->update([
                    'completed_at' => now(),
                    'load_volume' => 0,
                ]);

            // Создаём новый trip с zone_id = зоне обваловки
            // Сохраняем холостой пробег и гружёное расстояние для статистики
            $routeService = app(\App\Services\RouteAssignmentService::class);
            $emptyRunKm = $routeService->calculateEmptyRun($truck, $order->miner_id);
            $loadedDistance = (float) ($order->distance_km ?? 0);

            $trip = TruckTrip::create([
                'truck_id'        => $truck->id,
                'driver_id'       => $truck->driver_id,
                'miner_id'        => $order->miner_id,
                'dump_id'         => $order->dump_id,
                'zone_id'         => $zone->id,
                'rock_id'         => $currentRock->id,
                'mining_order_id' => $order->id,
                'distance_km'     => $loadedDistance,
                'started_at'      => now(),
                'empty_run_km'    => $emptyRunKm,
            ]);

            // Обновляем mining_order: привязываем к зоне обваловки
            $order->update([
                'zone_id'           => $zone->id,
                'rock_id'           => $currentRock->id,
                'wrr_cursor'        => ($order->wrr_cursor ?? 0) + 1,
                'last_assigned_at'  => now(),
            ]);

            // Меняем статус самосвала
            $truck->update(['status' => Truck::STATUS_TO_MINER]);

            // Увеличиваем счётчик назначенных самосвалов
            $request->increment('trucks_assigned');
            if ($request->status === BermRequest::STATUS_PENDING) {
                $request->update(['status' => BermRequest::STATUS_IN_PROGRESS]);
            }
            $request->refresh();

            Log::info('assignTruckToBerm: trip created', [
                'trip_id'         => $trip->id,
                'truck_id'        => $truck->id,
                'zone_id'         => $zone->id,
                'assigned_total'  => $request->trucks_assigned,
                'remaining'       => $request->remainingToAssign(),
            ]);

            // Уведомления
            $this->notifyDriver($truck, $order, $zone, 'berm_assigned');
            $this->broadcastProgress($request);
        });
    }

    /**
     * Вызывается после завершения рейса самосвала (выгрузки на зоне).
     *
     * Проверяет, была ли эта зона под обваловкой, и увеличивает счётчик.
     * Если trucks_completed достиг trucks_needed — закрывает запрос.
     */
    public function onTruckUnloaded(int $truckId, int $zoneId): void
    {
        // Используем транзакцию и блокировку, чтобы инкремент выполненных рейсов 
        // при одновременной выгрузке двух машин не привел к потере данных счетчика
        DB::transaction(function () use ($zoneId) {
            $request = BermRequest::activeForZone($zoneId)->lockForUpdate()->first();
            if (!$request) {
                return;
            }

            $request->increment('trucks_completed');
            $request->refresh();

            Log::info('BermRequest progress', [
                'request_id'        => $request->id,
                'zone_id'           => $zoneId,
                'trucks_completed'  => $request->trucks_completed,
                'trucks_needed'     => $request->trucks_needed,
                'remaining'         => $request->remainingToComplete(),
            ]);

            // Если достаточно самосвалов отсыпали — завершаем
            if ($request->trucks_completed >= $request->trucks_needed) {
                $this->completeRequest($request);
            } else {
                // Иначе пытаемся назначить ещё самосвалов (если ещё нужно)
                $this->assignTrucks($request);
            }

            $this->broadcastProgress($request);
        });
    }

    /**
     * Завершить запрос обваловки автоматически (когда N самосвалов отсыпали).
     */
    protected function completeRequest(BermRequest $request): void
    {
        $request->update([
            'status'       => BermRequest::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        Log::info('BermRequest COMPLETED', [
            'request_id' => $request->id,
            'zone_id'    => $request->zone_id,
            'trucks'     => $request->trucks_completed,
        ]);

        // После завершения — обычные маршруты на эту зону снова работают.
        // Пересинхронизируем и назначаем свободные самосвалы.
        try {
            $this->routeService->assignRoutesToAllFree();
            event(new RoutesUpdated());
        } catch (\Exception $e) {
            Log::error('BermService completeRequest: post-complete sync failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Проверить, находится ли зона под обваловкой.
     */
    public function isZoneUnderBerm(int $zoneId): bool
    {
        return BermRequest::activeForZone($zoneId)->exists();
    }

    /**
     * Получить активный запрос обваловки для зоны (если есть).
     */
    public function getActiveRequestForZone(int $zoneId): ?BermRequest
    {
        return BermRequest::activeForZone($zoneId)->first();
    }

    /**
     * Получить все активные запросы обваловки.
     */
    public function getActiveRequests()
    {
        return BermRequest::active()
            ->with(['zone', 'dump', 'rock', 'creator'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Отправить событие прогресса обваловки (вебсокеты).
     */
    protected function broadcastProgress(BermRequest $request): void
    {
        try {
            event(new BermProgress($request));
        } catch (\Exception $e) {
            Log::error('BermService broadcastProgress failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Уведомить водителя о назначении на обваловку.
     */
    protected function notifyDriver(Truck $truck, MiningOrder $order, Zone $zone, string $action): void
    {
        if (!$truck->driver_id) {
            return;
        }

        try {
            event(new \App\Events\DriverRouteUpdated(
                (int) $truck->driver_id,
                [
                    'truck_id' => $truck->id,
                    'action'   => $action,
                    'order_id' => $order->id,
                    'miner_id' => $order->miner_id,
                    'dump_id'  => $order->dump_id,
                    'zone_id'  => $zone->id,
                    'message'  => 'Назначен на обваловку зоны ' . $zone->name_zone,
                ]
            ));

            Log::info("BermService notifyDriver ({$action}) sent for truck {$truck->id}");
        } catch (\Exception $e) {
            Log::error('BermService notifyDriver failed', ['error' => $e->getMessage()]);
        }
    }
}
