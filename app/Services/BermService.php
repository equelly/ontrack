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
 *
 * Логика приоритета:
 *   1. Если есть активный BermRequest на зону — обычные маршруты на эту
 *      зону не назначаются.
 *   2. Свободные самосвалы сначала направляются на обваловку, потом на
 *      обычные маршруты.
 *   3. Маршрут обваловки использует ту же логику, что и обычный:
 *      забой → отвал → зона, просто зона может быть delivery=false.
 *
 * Завершение:
 *   - trucks_completed достиг trucks_needed → completed
 *   - мастер вручную отменяет → cancelled
 */
class BermService
{
    public function __construct(
        protected RouteAssignmentService $routeService
    ) {}

    /**
     * Создать запрос на обваловку зоны.
     *
     * @param int $zoneId Зона, которую нужно обваловать
     * @param int $trucksNeeded Сколько самосвалов нужно
     * @param int|null $rockId Порода для обваловки (null — любая подходящая)
     * @param int|null $createdBy ID мастера
     */
    public function createRequest(int $zoneId, int $trucksNeeded, ?int $rockId = null, ?int $createdBy = null): BermRequest
    {
        $zone = Zone::with('dump')->findOrFail($zoneId);

        // Проверяем, нет ли уже активного запроса на эту зону
        $existing = BermRequest::activeForZone($zoneId)->first();
        if ($existing) {
            throw new \RuntimeException("На зону «{$zone->name_zone}» уже есть активный запрос обваловки");
        }

        if ($trucksNeeded < 1 || $trucksNeeded > 50) {
            throw new \RuntimeException('Количество самосвалов должно быть от 1 до 50');
        }

        return DB::transaction(function () use ($zone, $zoneId, $trucksNeeded, $rockId, $createdBy) {
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

            // Сразу пытаемся назначить самосвалы
            $this->assignTrucks($request);

            return $request;
        });
    }

    /**
     * Отменить запрос на обваловку (мастер вручную).
     */
    public function cancelRequest(int $requestId, ?int $cancelledBy = null): BermRequest
    {
        $request = BermRequest::findOrFail($requestId);

        if (!$request->isActive()) {
            throw new \RuntimeException('Запрос уже неактивен');
        }

        $request->update([
            'status'       => BermRequest::STATUS_CANCELLED,
            'completed_at' => now(),
            'completed_by' => $cancelledBy,
        ]);

        Log::info('BermRequest cancelled', [
            'request_id' => $request->id,
            'zone_id'    => $request->zone_id,
            'by'         => $cancelledBy,
        ]);

        // После отмены — пересинхронизируем маршруты
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
     *
     * Используется:
     * - При создании запроса
     * - При освобождении самосвалов (кто-то завершил рейс)
     * - При обновлении панели мастера
     *
     * @return int Колько назначенных самосвалов
     */
    public function assignTrucks(?BermRequest $request = null): int
    {
        if ($request && !$request->isActive()) {
            return 0;
        }

        // Если запрос не указан — обрабатываем все активные запросы
        $requests = $request
            ? collect([$request])
            : BermRequest::active()->orderBy('created_at')->get();

        if ($requests->isEmpty()) {
            return 0;
        }

        $assigned = 0;

        foreach ($requests as $req) {
            $remaining = $req->remainingToAssign();
            if ($remaining <= 0) {
                continue;
            }

            // Ищем свободные самосвалы
            $freeTrucks = Truck::whereIn('status', ['free', 'completed'])
                ->orderBy('updated_at', 'asc')
                ->limit($remaining)
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
     */
    protected function assignTruckToBerm(Truck $truck, BermRequest $request): void
    {
        Log::info('assignTruckToBerm START', [
            'truck_id'    => $truck->id,
            'request_id'  => $request->id,
            'zone_id'     => $request->zone_id,
        ]);

        // Ищем подходящий MiningOrder: забой с нужной породой → отвал зоны обваловки
        $query = MiningOrder::where('active', true)
            ->where('dump_id', $request->dump_id)
            ->with(['miner.currentRock', 'dump.zones.rocks']);

        // Если в запросе указана порода — ищем забой именно с этой породой
        if ($request->rock_id) {
            $query->whereHas('miner', function ($q) use ($request) {
                $q->where('current_rock_id', $request->rock_id);
            });
        }

        $order = $query->first();

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

        // Зона обваловки — берём из запроса
        $zone = $request->zone;

        // Создаём trip и назначаем — используем внутренний метод RouteAssignmentService
        // через reflection, чтобы получить доступ к protected createTripAndAssign.
        // Альтернатива — вынести createTripAndAssign в public, но это сломает инкапсуляцию.
        // Поэтому идём через assignForTruck с модифицированным order:
        DB::transaction(function () use ($truck, $order, $zone, $currentRock, $request) {
            // Завершаем старые незавершённые trip самосвала
            TruckTrip::where('truck_id', $truck->id)
                ->whereNull('completed_at')
                ->update([
                    'completed_at' => now(),
                    'load_volume' => 0,
                ]);

            // Создаём новый trip с zone_id = зоне обваловки
            $trip = TruckTrip::create([
                'truck_id'        => $truck->id,
                'driver_id'       => $truck->driver_id,
                'miner_id'        => $order->miner_id,
                'dump_id'         => $order->dump_id,
                'zone_id'         => $zone->id,
                'rock_id'         => $currentRock->id,
                'mining_order_id' => $order->id,
                'started_at'      => now(),
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
        $request = BermRequest::activeForZone($zoneId)->first();
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
     *
     * Используется в RouteAssignmentService, чтобы запретить обычные
     * маршруты на эту зону, пока идёт обваловка.
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
