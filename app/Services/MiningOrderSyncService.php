<?php

namespace App\Services;

use App\Models\MiningOrder;
use App\Models\Zone;
use App\Models\Miner;
use Illuminate\Support\Facades\Log;

class MiningOrderSyncService
{
    /**
     * Синхронизирует статус active для всех MiningOrder, связанных с указанным zone_id,
     * а также для маршрутов без зоны — пытается найти им доступную зону автоматически.
     *
     * Вызывается из:
     * - Zone::boot() (saved/deleted)
     * - MasterPanel::updateZoneField()
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
     * Использует RouteAssignmentService::selectZoneForRock() как единую точку правды
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
     *
     * ВКЛЮЧАЕТ АВАРИЙНЫЙ РЕЖИМ:
     * Если у забоя все активные маршруты стали недоступны (зоны закрылись/переполнились/
     * под обваловкой), система автоматически активирует первый доступный неактивный маршрут.
     * Это страховка от ситуации, когда забой "зависает" без маршрута.
     */
    public function syncAllOrders(): int
    {
        $autoAssigned = 0;

        // 1. Обрабатываем маршруты с zone_id — проверяем доступность
        $ordersWithZone = MiningOrder::whereNotNull('zone_id')->get();
        foreach ($ordersWithZone as $order) {
            $zone = $order->zone;
            if (!$zone || !$zone->delivery || $zone->volume >= $zone->capacity) {
                // Зона недоступна — пытаемся найти другую
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

        // 2. Обрабатываем маршруты без zone_id — ищем зону
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

        // 3. АВАРИЙНЫЙ РЕЖИМ: если у забоя все активные маршруты стали недоступны,
        //    активируем первый доступный неактивный маршрут (с доступной зоной).
        $autoAssigned += $this->activateEmergencyRoutes();

        return $autoAssigned;
    }

    /**
     * Аварийный режим: для забоев без активных маршрутов активировать первый
     * доступный неактивный маршрут.
     *
     * Срабатывает когда:
     *   - У забоя все active=true маршруты стали недоступны
     *   - Есть неактивные маршруты с доступной зоной
     *
     * @return int Количество аварийно активированных маршрутов
     */
    protected function activateEmergencyRoutes(): int
    {
        // Находим забои, у которых нет ни одного активного маршрута
        $minersWithoutActiveRoutes = Miner::where('active', true)
            ->where('status', Miner::STATUS_ACTIVE)
            ->whereDoesntHave('orders', function ($q) {
                $q->where('active', true);
            })
            ->pluck('id');

        if ($minersWithoutActiveRoutes->isEmpty()) {
            return 0;
        }

        $activated = 0;

        foreach ($minersWithoutActiveRoutes as $minerId) {
            // Ищем неактивные маршруты этого забоя, для которых есть доступная зона
            $inactiveOrders = MiningOrder::where('miner_id', $minerId)
                ->where('active', false)
                ->with(['dump.zones.rocks', 'miner.currentRock'])
                ->get();

            foreach ($inactiveOrders as $order) {
                $zone = $this->findZoneForOrder($order);
                if ($zone) {
                    $order->update([
                        'zone_id' => $zone->id,
                        'active'  => true,
                    ]);
                    $activated++;

                    Log::info('Аварийная активация маршрута', [
                        'order_id'  => $order->id,
                        'miner_id'  => $minerId,
                        'zone_id'   => $zone->id,
                        'dump_id'   => $order->dump_id,
                    ]);
                    break; // активируем только один маршрут на забой
                }
            }
        }

        return $activated;
    }
}
