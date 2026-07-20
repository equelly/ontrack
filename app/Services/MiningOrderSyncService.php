<?php

namespace App\Services;

use App\Models\MiningOrder;
use App\Models\Zone;

class MiningOrderSyncService
{
    /**
     * Синхронизирует статус active для всех MiningOrder, связанных с указанным dump_id.
     *
     * @param int $dumpId
     */
    public function syncActiveStatusForDump(int $dumpId): void
    {
        // Находим все маршруты для этого отвала
        $orders = MiningOrder::where('dump_id', $dumpId)->get();

        foreach ($orders as $order) {
            if (!$order->rock_id) {
                // Если у маршрута не указана порода, деактивируем его
                $order->update(['active' => false]);
                continue;
            }

            // Проверяем наличие подходящих зон для конкретного маршрута
            $hasValidZone = Zone::where('dump_id', $dumpId)
                ->where('delivery', true)
                ->whereRaw('volume < capacity')
                ->where(function ($query) use ($order) {
                    // Проверяем прямое поле rock_id (если оно есть в таблице zones)
                    $query->where('rock_id', $order->rock_id)
                          // Или проверяем через связанную таблицу (если используется many-to-many)
                          ->orWhereHas('rocks', fn($q) => $q->where('rocks.id', $order->rock_id));
                })
                ->exists();

            $order->update(['active' => $hasValidZone]);
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