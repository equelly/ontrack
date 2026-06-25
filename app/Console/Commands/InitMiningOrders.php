<?php

namespace App\Console\Commands;

use App\Models\MinerDumpDistance;
use App\Models\MiningOrder;
use Illuminate\Console\Command;

class InitMiningOrders extends Command
{
    protected $signature = 'mining-orders:init';
    protected $description = 'Создать недостающие маршруты на основе расстояний (miner_dump_distances)';

    public function handle(): int
    {
        $this->info('=== Инициализация маршрутов ===');
        
        // Получаем все расстояния
        $distances = MinerDumpDistance::all();
        $this->info("Найдено расстояний: {$distances->count()}");
        
        $created = 0;
        $skipped = 0;
        
        foreach ($distances as $distance) {
            // Проверяем, существует ли уже маршрут
            $exists = MiningOrder::where('miner_id', $distance->miner_id)
                ->where('dump_id', $distance->dump_id)
                ->exists();
            
            if ($exists) {
                $skipped++;
                continue;
            }
            
            // Создаём маршрут
            MiningOrder::create([
                'miner_id' => $distance->miner_id,
                'dump_id' => $distance->dump_id,
                'distance_km' => $distance->distance_km,
                'active' => false,
                'weight' => 100,
            ]);
            
            $created++;
        }
        
        $this->info("\n=== Результат ===");
        $this->info("Создано: {$created}");
        $this->info("Пропущено (уже есть): {$skipped}");
        $this->info("Всего маршрутов: " . MiningOrder::count());
        
        return Command::SUCCESS;
    }
}
