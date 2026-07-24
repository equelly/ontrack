<?php

namespace App\Services;

use App\Models\MiningOrder;
use App\Models\Zone;

class MiningOrderSyncService
{
    /**
     * Синхронизирует статус active для всех MiningOrder, связанных с указанным zone_id.
     *
     * @param int $zoneId
     */
    public function syncActiveStatusForZone(int $zoneId): void
    {
        // Находим все маршруты для этой зоны
        $zoneOrders = MiningOrder::where('zone_id', $zoneId)->get();

        // Также находим все маршруты без зоны (zone_id = null)
        $nullZoneOrders = MiningOrder::whereNull('zone_id')->get();

        // Получаем зону
        $zone = Zone::find($zoneId);

        // Обновляем маршруты для указанной зоны
        foreach ($zoneOrders as $order) {
            if (!$zone) {
                // Если зона не существует, деактивируем маршрут
                $order->update(['active' => false]);
                continue;
            }

            // Проверяем условия активности: зона открыта и не переполнена
            $isActive = $zone->delivery && $zone->volume < $zone->capacity;
            $order->update(['active' => $isActive]);
        }

        // Жестко деактивируем все маршруты без зоны
        foreach ($nullZoneOrders as $order) {
            $order->update(['active' => false]);
        }
    }

    /**
     * Синхронизирует статус active для конкретного MiningOrder.
     *
     * @param MiningOrder $order
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
}