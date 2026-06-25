<?php

namespace App\Console\Commands;

use App\Models\Miner;
use App\Models\Rock;
use Illuminate\Console\Command;

class AssignRocksToMiners extends Command
{
    protected $signature = 'miners:assign-rocks';
    protected $description = 'Назначить текущую породу активным забоям (для тестирования)';

    public function handle(): int
    {
        $this->info('=== Назначение пород забоям ===');
        
        $rocks = Rock::all();
        $this->info("Доступные породы:");
        foreach ($rocks as $rock) {
            $this->line("  {$rock->id}: {$rock->name_rock}");
        }
        
        $rockId = $this->ask('Выберите ID породы для назначения всем активным забоям', $rocks->first()->id ?? 1);
        
        $rock = Rock::find($rockId);
        if (!$rock) {
            $this->error("Порода с ID {$rockId} не найдена!");
            return Command::FAILURE;
        }
        
        $activeMiners = Miner::where('active', true)->get();
        $this->info("\nАктивных забоев: {$activeMiners->count()}");
        
        if (!$this->confirm("Назначить породу '{$rock->name_rock}' всем активным забоям?")) {
            return Command::SUCCESS;
        }
        
        $updated = 0;
        foreach ($activeMiners as $miner) {
            $miner->current_rock_id = $rock->id;
            $miner->save();
            $this->line("  Забой {$miner->id} ({$miner->name_miner}) → {$rock->name_rock} (saved: " . ($miner->current_rock_id == $rock->id ? 'OK' : 'FAIL') . ")");
            $updated++;
        }
        
        $this->info("\nОбновлено забоев: {$updated}");
        $this->info("Теперь запустите: php artisan routes:optimize --debug");
        
        return Command::SUCCESS;
    }
}