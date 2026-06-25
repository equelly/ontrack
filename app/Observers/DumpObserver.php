<?php

namespace App\Observers;

use App\Models\Dump;
use App\Models\MiningOrder;
use App\Models\MinerDumpDistance;
use Illuminate\Support\Facades\Log;

/**
 * Observer для модели Dump
 * 
 * При создании отвала - создать маршруты от всех забоев (где есть расстояние)
 * При удалении отвала - удалить все его маршруты
 */
class DumpObserver
{
    /**
     * При создании нового отвала - создать маршруты от всех забоев
     */
    public function created(Dump $dump): void
    {
        Log::info("DumpObserver: создан отвал {$dump->id} ({$dump->name_dump}), создание маршрутов");
        
        // Получаем все расстояния для этого отвала
        $distances = MinerDumpDistance::where('dump_id', $dump->id)->get();
        $created = 0;
        
        foreach ($distances as $distance) {
            // Проверяем, нет ли уже такого маршрута
            $exists = MiningOrder::where('miner_id', $distance->miner_id)
                ->where('dump_id', $dump->id)
                ->exists();
            
            if (!$exists) {
                MiningOrder::create([
                    'miner_id' => $distance->miner_id,
                    'dump_id' => $dump->id,
                    'distance_km' => $distance->distance_km,
                    'active' => false,
                    'weight' => 100,
                ]);
                $created++;
            }
        }
        
        Log::info("DumpObserver: создано {$created} маршрутов для отвала {$dump->id}");
    }

    /**
     * При удалении отвала - удалить все его маршруты
     */
    public function deleted(Dump $dump): void
    {
        $count = MiningOrder::where('dump_id', $dump->id)->delete();
        Log::info("DumpObserver: удалено {$count} маршрутов отвала {$dump->id}");
    }
}
