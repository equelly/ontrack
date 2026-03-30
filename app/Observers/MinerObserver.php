<?php

namespace App\Observers;

use App\Models\Miner;
use App\Models\MiningOrder;
use App\Models\MinerDumpDistance;
use Illuminate\Support\Facades\Log;

/**
 * Observer для модели Miner
 * 
 * При создании забоя - создать маршруты ко всем отвалам (где есть расстояние)
 * При удалении забоя - удалить все его маршруты
 */
class MinerObserver
{
    /**
     * При создании нового забоя - создать маршруты ко всем отвалам
     */
    public function created(Miner $miner): void
    {
        Log::info("MinerObserver: создан забой {$miner->id} ({$miner->name_miner}), создание маршрутов");
        
        // Получаем все расстояния для этого забоя
        $distances = MinerDumpDistance::where('miner_id', $miner->id)->get();
        $created = 0;
        
        foreach ($distances as $distance) {
            // Проверяем, нет ли уже такого маршрута
            $exists = MiningOrder::where('miner_id', $miner->id)
                ->where('dump_id', $distance->dump_id)
                ->exists();
            
            if (!$exists) {
                MiningOrder::create([
                    'miner_id' => $miner->id,
                    'dump_id' => $distance->dump_id,
                    'distance_km' => $distance->distance_km,
                    'active' => false,
                    'weight' => 100,
                ]);
                $created++;
            }
        }
        
        Log::info("MinerObserver: создано {$created} маршрутов для забоя {$miner->id}");
    }

    /**
     * При удалении забоя - удалить все его маршруты
     */
    public function deleted(Miner $miner): void
    {
        $count = MiningOrder::where('miner_id', $miner->id)->delete();
        Log::info("MinerObserver: удалено {$count} маршрутов забоя {$miner->id}");
    }
}
