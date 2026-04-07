<?php

namespace App\Services;

use App\Models\Zone;
use App\Models\Miner;
use App\Models\MiningOrder;
use App\Models\MinerDumpDistance;
use App\Events\DispatcherNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RouteSyncService - синхронизация маршрутов при изменении зон
 *
 * Инкрементальный подход - обновляем только затронутые маршруты,
 * сохраняя WRR-распределение для остальных.
 */
class RouteSyncService
{
    protected RouteOptimizerService $optimizer;

    public function __construct(RouteOptimizerService $optimizer)
    {
        $this->optimizer = $optimizer;
    }

    /**
     * Зона закрылась - удалить маршруты к этой зоне
     * Грузовики в пути доезжают и разгружаются
     */
    public function syncOnZoneClose(Zone $zone): array
    {
        Log::info("=== RouteSyncService::syncOnZoneClose START ===", [
            'zone_id' => $zone->id,
            'zone_name' => $zone->name_zone,
            'dump_id' => $zone->dump_id,
        ]);

        $deletedCount = MiningOrder::where('zone_id', $zone->id)->delete();

        Log::info("Deleted mining_orders for zone {$zone->id}: {$deletedCount}");

        // Уведомляем диспетчера
        $this->notifyDispatcher('zone_closed', [
            'zone_id' => $zone->id,
            'zone_name' => $zone->name_zone,
            'deleted_routes' => $deletedCount,
        ]);

        // Пересчитываем приоритеты
        $this->recalculatePriorities();

        return [
            'deleted' => $deletedCount,
            'zone_id' => $zone->id,
        ];
    }

    /**
     * Зона открылась - создать маршруты для совместимых забоев
     */
    public function syncOnZoneOpen(Zone $zone): array
    {
        Log::info("=== RouteSyncService::syncOnZoneOpen START ===", [
            'zone_id' => $zone->id,
            'zone_name' => $zone->name_zone,
            'dump_id' => $zone->dump_id,
            'delivery' => $zone->delivery,
        ]);

        // Получаем породы в зоне (перезагружаем связь)
        $zoneRockIds = $zone->load('rocks')->rocks()->pluck('rocks.id')->toArray();
        
        Log::info("Zone rocks: " . json_encode($zoneRockIds));

        if (empty($zoneRockIds)) {
            Log::info("Zone {$zone->id} has no rocks, skipping");
            return ['created' => 0, 'zone_id' => $zone->id];
        }

        // Находим забои с текущей породой из зоны (только работающие)
        $miners = Miner::where('active', true)
            ->where('status', Miner::STATUS_ACTIVE)
            ->whereIn('current_rock_id', $zoneRockIds)
            ->get();

        Log::info("Found {$miners->count()} miners with matching rocks", [
            'rock_ids' => $zoneRockIds,
            'miner_ids' => $miners->pluck('id')->toArray(),
        ]);

        $created = 0;

        foreach ($miners as $miner) {
            $created += $this->createRouteIfNotExists($miner, $zone);
        }

        // Уведомляем диспетчера
        $this->notifyDispatcher('zone_opened', [
            'zone_id' => $zone->id,
            'zone_name' => $zone->name_zone,
            'created_routes' => $created,
        ]);

        // Пересчитываем приоритеты
        $this->recalculatePriorities();

        return [
            'created' => $created,
            'zone_id' => $zone->id,
        ];
    }

    /**
     * Порода добавлена в зону - создать маршруты для забоев с этой породой
     */
    public function syncOnRockAdded(Zone $zone, int $rockId): array
    {
        Log::info("=== RouteSyncService::syncOnRockAdded START ===", [
            'zone_id' => $zone->id,
            'rock_id' => $rockId,
        ]);

        // Находим забои с текущей породой = rockId (только работающие)
        $miners = Miner::where('active', true)
            ->where('status', Miner::STATUS_ACTIVE)
            ->where('current_rock_id', $rockId)
            ->get();

        Log::info("Found {$miners->count()} miners with rock {$rockId}");

        $created = 0;

        foreach ($miners as $miner) {
            $created += $this->createRouteIfNotExists($miner, $zone, $rockId);
        }

        // Уведомляем диспетчера
        $this->notifyDispatcher('rock_added_to_zone', [
            'zone_id' => $zone->id,
            'zone_name' => $zone->name_zone,
            'rock_id' => $rockId,
            'created_routes' => $created,
        ]);

        // Пересчитываем приоритеты
        $this->recalculatePriorities();

        return [
            'created' => $created,
            'zone_id' => $zone->id,
            'rock_id' => $rockId,
        ];
    }

    /**
     * Порода удалена из зоны - удалить маршруты с этой породой к этой зоне
     */
    public function syncOnRockRemoved(Zone $zone, int $rockId): array
    {
        Log::info("=== RouteSyncService::syncOnRockRemoved START ===", [
            'zone_id' => $zone->id,
            'rock_id' => $rockId,
        ]);

        $deletedCount = MiningOrder::where('zone_id', $zone->id)
            ->where('rock_id', $rockId)
            ->delete();

        Log::info("Deleted mining_orders for zone {$zone->id}, rock {$rockId}: {$deletedCount}");

        // Уведомляем диспетчера
        $this->notifyDispatcher('rock_removed_from_zone', [
            'zone_id' => $zone->id,
            'zone_name' => $zone->name_zone,
            'rock_id' => $rockId,
            'deleted_routes' => $deletedCount,
        ]);

        // Пересчитываем приоритеты
        $this->recalculatePriorities();

        return [
            'deleted' => $deletedCount,
            'zone_id' => $zone->id,
            'rock_id' => $rockId,
        ];
    }

    /**
     * Полная синхронизация маршрутов для зоны
     * Используется при批量 обновлении пород
     */
    public function fullSyncForZone(Zone $zone, array $newRockIds): array
    {
        Log::info("=== RouteSyncService::fullSyncForZone START ===", [
            'zone_id' => $zone->id,
            'new_rock_ids' => $newRockIds,
        ]);

        // Если зона закрыта - удаляем все маршруты к ней
        if (!$zone->delivery) {
            return $this->syncOnZoneClose($zone);
        }

        // Текущие породы в зоне (до синхронизации)
        $currentRockIds = $zone->rocks()->pluck('rocks.id')->toArray();

        // Определяем добавленные и удалённые породы
        $addedRocks = array_diff($newRockIds, $currentRockIds);
        $removedRocks = array_diff($currentRockIds, $newRockIds);

        Log::info("Rocks diff", [
            'added' => $addedRocks,
            'removed' => $removedRocks,
        ]);

        $results = [
            'created' => 0,
            'deleted' => 0,
        ];

        // Удаляем маршруты для удалённых пород
        foreach ($removedRocks as $rockId) {
            $result = $this->syncOnRockRemoved($zone, $rockId);
            $results['deleted'] += $result['deleted'];
        }

        // Создаём маршруты для добавленных пород
        foreach ($addedRocks as $rockId) {
            $result = $this->syncOnRockAdded($zone, $rockId);
            $results['created'] += $result['created'];
        }

        // Один раз пересчитываем приоритеты
        $this->recalculatePriorities();

        return $results;
    }

    /**
     * Создать маршрут если не существует
     */
    protected function createRouteIfNotExists(Miner $miner, Zone $zone, ?int $rockId = null): int
    {
        $rockId = $rockId ?? $miner->current_rock_id;

        if (!$rockId) {
            Log::debug("Miner {$miner->id} has no current_rock_id, skipping");
            return 0;
        }

        // Проверяем существование маршрута
        $exists = MiningOrder::where('miner_id', $miner->id)
            ->where('zone_id', $zone->id)
            ->where('rock_id', $rockId)
            ->exists();

        if ($exists) {
            Log::debug("Route already exists: miner {$miner->id}, zone {$zone->id}, rock {$rockId}");
            return 0;
        }

        // Получаем расстояние
        $distance = MinerDumpDistance::where('miner_id', $miner->id)
            ->where('dump_id', $zone->dump_id)
            ->value('distance_km');

        // Создаём маршрут
        MiningOrder::create([
            'miner_id' => $miner->id,
            'dump_id' => $zone->dump_id,
            'zone_id' => $zone->id,
            'rock_id' => $rockId,
            'distance_km' => $distance,
            'active' => false, // Будет активирован при оптимизации
            'weight' => 100,
            'wrr_cursor' => 0,
        ]);

        Log::info("Created route: miner {$miner->id} → zone {$zone->id}, rock {$rockId}");

        return 1;
    }

    /**
     * Пересчитать приоритеты WRR
     */
    protected function recalculatePriorities(): void
    {
        // Сбрасываем wrr_cursor для всех маршрутов
        // Новые маршруты начнут с 0, существующие сохранят свои веса
        MiningOrder::query()->update(['wrr_cursor' => 0]);

        Log::info("WRR priorities reset");

        // Если в автоматическом режиме - запускаем оптимизацию
        if ($this->optimizer->getMode() === 'auto') {
            $this->optimizer->optimize();
        }
    }

    /**
     * Уведомить диспетчера
     */
    protected function notifyDispatcher(string $action, array $data): void
    {
        event(new DispatcherNotification(
            0, // truck_id = 0 для системных уведомлений
            $action,
            $data
        ));

        Log::info("Dispatcher notified: {$action}", $data);
    }
}
