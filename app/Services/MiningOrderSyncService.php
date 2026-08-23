<?php

namespace App\Services;

use App\Models\MiningOrder;
use App\Models\Zone;
use Illuminate\Support\Facades\Log;

class MiningOrderSyncService
{
    /**
     * Синхронизирует статус active для всех MiningOrder, связанных с указанным zone_id,
     * а также для маршрутов без зоны — пытается найти им доступную зону автоматически.
     *
     * @param int $zoneId
     */
    public function syncActiveStatusForZone(int $zoneId): void
    {
        $zone = Zone::find($zoneId);

        // 1. Обновляем маршруты, у которых zone_id = эта зона
        $zoneOrders = MiningOrder::where('zone_id', $zoneId)->get();
        foreach ($zoneOrders as $order) {
            if (!$zone) {
                $order->update([
                    'zone_id' => null,
                    'active' => $this->canFindZoneForOrder($order),
                ]);
                continue;
            }

            $isActive = $zone->delivery && $zone->volume < $zone->capacity;
            $order->update(['active' => $isActive]);
        }

        // 2. Для маршрутов без zone_id — проверяем, можно ли найти зону автоматически.
        $nullZoneOrders = MiningOrder::whereNull('zone_id')->get();
        foreach ($nullZoneOrders as $order) {
            $foundZone = $this->findZoneForOrder($order);
            if ($foundZone) {
                $order->update([
                    'zone_id' => $foundZone->id,
                    'active'  => true,
                ]);
                Log::info("MiningOrderSync: автопривязка зоны", [
                    'order_id' => $order->id,
                    'zone_id'  => $foundZone->id,
                ]);
            } else {
                $order->update(['active' => false]);
            }
        }
    }

    /**
     * Проверяет, может ли маршрут найти доступную зону (без самой привязки).
     */
    protected function canFindZoneForOrder(MiningOrder $order): bool
    {
        return $this->findZoneForOrder($order) !== null;
    }

    /**
     * Найти подходящую зону для маршрута.
     * Использует RouteAssignmentService::selectZoneForRock() — единая точка правды
     * по fallback-логике пород.
     */
    protected function findZoneForOrder(MiningOrder $order): ?Zone
    {
        $miner = $order->miner;
        if (!$miner) {
            return null;
        }

        $currentRock = $miner->currentRock;
        if (!$currentRock) {
            return null;
        }

        $routeService = app(\App\Services\RouteAssignmentService::class);
        return $routeService->selectZoneForRock($order->dump_id, $currentRock->id);
    }

    /**
     * Синхронизирует статус active для конкретного MiningOrder.
     */
    public function syncActiveStatusForOrder(MiningOrder $order): void
    {
        $eligibleZones = Zone::where('dump_id', $order->dump_id)
            ->where('delivery', true)
            ->whereRaw('volume < capacity')
            ->where(function ($query) use ($order) {
                $query->where('rock_id', $order->rock_id)
                    ->orWhereHas('rocks', function ($q) use ($order) {
                        $q->where('rock_id', $order->rock_id);
                    });
            })
            ->exists();

        $order->update(['active' => $eligibleZones]);
    }

    /**
     * Полная синхронизация всех MiningOrder.
     * Возвращает количество маршрутов, для которых удалось найти зону автоматически.
     */
    public function syncAllOrders(): int
    {
        $autoAssigned = 0;

        $ordersWithZone = MiningOrder::whereNotNull('zone_id')->get();
        foreach ($ordersWithZone as $order) {
            $zone = $order->zone;
            if (!$zone || !$zone->delivery || $zone->volume >= $zone->capacity) {
                $newZone = $this->findZoneForOrder($order);
                if ($newZone) {
                    $order->update([
                        'zone_id' => $newZone->id,
                        'active'  => true,
                    ]);
                    $autoAssigned++;
                } else {
                    $order->update(['active' => false]);
                }
            } else {
                $order->update(['active' => true]);
            }
        }

        $ordersWithoutZone = MiningOrder::whereNull('zone_id')->get();
        foreach ($ordersWithoutZone as $order) {
            $newZone = $this->findZoneForOrder($order);
            if ($newZone) {
                $order->update([
                    'zone_id' => $newZone->id,
                    'active'  => true,
                ]);
                $autoAssigned++;
            } else {
                $order->update(['active' => false]);
            }
        }

        return $autoAssigned;
    }
}