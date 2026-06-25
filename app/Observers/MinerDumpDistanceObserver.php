<?php

namespace App\Observers;

use App\Models\MinerDumpDistance;
use App\Models\MiningOrder;
use Illuminate\Support\Facades\Log;

/**
 * Observer для модели MinerDumpDistance
 * 
 * При создании расстояния - создать маршрут (inactive)
 * При обновлении расстояния - обновить distance_km в маршруте
 * При удалении расстояния - удалить маршрут
 */
class MinerDumpDistanceObserver
{
    /**
     * При создании нового расстояния - создать маршрут
     */
    public function created(MinerDumpDistance $distance): void
    {
        Log::info("MinerDumpDistanceObserver: создано расстояние забой {$distance->miner_id} → отвал {$distance->dump_id} ({$distance->distance_km} км)");
        
        // Проверяем, нет ли уже такого маршрута
        $exists = MiningOrder::where('miner_id', $distance->miner_id)
            ->where('dump_id', $distance->dump_id)
            ->exists();
        
        if (!$exists) {
            MiningOrder::create([
                'miner_id' => $distance->miner_id,
                'dump_id' => $distance->dump_id,
                'distance_km' => $distance->distance_km,
                'active' => false,
                'weight' => 100,
            ]);
            
            Log::info("MinerDumpDistanceObserver: создан маршрут забой {$distance->miner_id} → отвал {$distance->dump_id}");
        }
    }

    /**
     * При обновлении расстояния - обновить distance_km в маршруте
     */
    public function updated(MinerDumpDistance $distance): void
    {
        if ($distance->isDirty('distance_km')) {
            MiningOrder::where('miner_id', $distance->miner_id)
                ->where('dump_id', $distance->dump_id)
                ->update(['distance_km' => $distance->distance_km]);
            
            Log::info("MinerDumpDistanceObserver: обновлено расстояние в маршруте забой {$distance->miner_id} → отвал {$distance->dump_id}: {$distance->distance_km} км");
        }
    }

    /**
     * При удалении расстояния - удалить маршрут
     */
    public function deleted(MinerDumpDistance $distance): void
    {
        $count = MiningOrder::where('miner_id', $distance->miner_id)
            ->where('dump_id', $distance->dump_id)
            ->delete();
        
        Log::info("MinerDumpDistanceObserver: удалён маршрут забой {$distance->miner_id} → отвал {$distance->dump_id} (удалено: {$count})");
    }
}
