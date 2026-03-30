<?php

namespace App\Console\Commands;

use App\Models\MiningOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanMiningOrders extends Command
{
    protected $signature = 'mining-orders:clean';
    protected $description = 'Очистить дубликаты в mining_orders и установить правильную структуру';

    public function handle(): int
    {
        $this->info('=== Очистка mining_orders ===');
        
        // 1. Показать текущее состояние
        $totalBefore = MiningOrder::count();
        $duplicates = DB::select("
            SELECT miner_id, dump_id, COUNT(*) as cnt 
            FROM mining_orders 
            GROUP BY miner_id, dump_id 
            HAVING COUNT(*) > 1
        ");
        
        $this->info("Всего записей: {$totalBefore}");
        $this->info("Групп с дубликатами: " . count($duplicates));
        
        if (count($duplicates) > 0) {
            $this->warn('Найдены дубликаты:');
            foreach ($duplicates as $dup) {
                $this->line("  Забой {$dup->miner_id} → Отвал {$dup->dump_id}: {$dup->cnt} записей");
            }
        }
        
        // 2. Удалить дубликаты (оставляем запись с минимальным id)
        if ($this->confirm('Удалить дубликаты?')) {
            $deleted = DB::delete("
                DELETE m1 FROM mining_orders m1
                INNER JOIN mining_orders m2 
                WHERE m1.id > m2.id 
                  AND m1.miner_id = m2.miner_id 
                  AND m1.dump_id = m2.dump_id
            ");
            
            $this->info("Удалено записей: {$deleted}");
        }
        
        // 3. Деактивировать все маршруты
        $this->info("\n=== Установка активности ===");
        
        if ($this->confirm('Деактивировать все маршруты? (будут активированы лучшими при распределении)')) {
            MiningOrder::query()->update(['active' => false]);
            $this->info('Все маршруты деактивированы');
        }
        
        // 4. Показать итоговое состояние
        $totalAfter = MiningOrder::count();
        $activeCount = MiningOrder::where('active', true)->count();
        
        $this->info("\n=== Итог ===");
        $this->info("Записей: {$totalAfter}");
        $this->info("Активных: {$activeCount}");
        
        return Command::SUCCESS;
    }
}