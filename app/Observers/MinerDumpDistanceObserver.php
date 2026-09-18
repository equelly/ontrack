<?php

namespace App\Observers;

use App\Models\MinerDumpDistance;
use App\Models\MiningOrder;
use Illuminate\Support\Facades\Log;

/**
 * Observer для модели MinerDumpDistance
 *
 * Синхронизирует расстояния между забоями и отвалами с маршрутами (MiningOrder).
 * Автоматически создает маршрут, если он отсутствует, обновляет при изменении и удаляет при удалении.
 */
class MinerDumpDistanceObserver
{
    /**
     * При создании записи о расстоянии — синхронизировать с MiningOrder
     */
    public function created(MinerDumpDistance $distance): void
    {
        $this->syncMiningOrderDistance($distance);
    }

    /**
     * При обновлении записи о расстоянии — синхронизировать с MiningOrder
     */
    public function updated(MinerDumpDistance $distance): void
    {
        // Обновляем только если изменилось само расстояние
        if ($distance->isDirty('distance_km')) {
            $this->syncMiningOrderDistance($distance);
        }
    }

    /**
     * При удалении расстояния — удалить связанный маршрут
     */
    public function deleted(MinerDumpDistance $distance): void
    {
        $count = MiningOrder::where('miner_id', $distance->miner_id)
            ->where('dump_id', $distance->dump_id)
            ->delete();
        
        Log::info("MinerDumpDistanceObserver: удалён маршрут забой {$distance->miner_id} → отвал {$distance->dump_id} (удалено записей: {$count})");
    }

    /**
     * Интеллектуальная синхронизация данных (создание или обновление)
     */
    protected function syncMiningOrderDistance(MinerDumpDistance $distance): void
    {
        // Ищем по уникальной паре забой-отвал
        // Если не найден — создаем с дефолтными значениями, если найден — обновляем distance_km
        $order = MiningOrder::updateOrCreate(
            [
                'miner_id' => $distance->miner_id,
                'dump_id'  => $distance->dump_id,
            ],
            [
                'distance_km' => $distance->distance_km,
                // Значения ниже применятся только при СОЗДАНИИ новой записи (благодаря Eloquent)
                'active'      => false,
                'weight'      => 100,
                'wrr_cursor'  => 0,
            ]
        );

        if ($order->wasRecentlyCreated) {
            Log::info("MinerDumpDistanceObserver: создан новый маршрут забой {$distance->miner_id} → отвал {$distance->dump_id}, distance: {$distance->distance_km} км");
        } else {
            Log::info("MinerDumpDistanceObserver: обновлен distance_km для маршрута #{$order->id} (забой {$distance->miner_id} → отвал {$distance->dump_id}) на {$distance->distance_km} км");
        }
    }
}
