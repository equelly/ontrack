<?php

namespace App\Console\Commands;

use App\Models\Miner;
use App\Models\Zone;
use App\Models\MiningOrder;
use App\Models\MinerDumpDistance;
use App\Models\SystemSetting;
use App\Services\RouteOptimizerService;
use Illuminate\Console\Command;

class OptimizeRoutes extends Command
{
    protected $signature = 'routes:optimize 
                            {--debug : Показать отладочную информацию}
                            {--force : Запустить даже в ручном режиме}';
    protected $description = 'Оптимизировать маршруты: рассчитать score и выбрать активные';

    public function handle(RouteOptimizerService $optimizer): int
    {
        $this->info('=== Оптимизация маршрутов ===');
        
        // Проверяем режим
        $mode = SystemSetting::getRouteActivationMode();
        $this->info("Текущий режим: {$mode}");
        
        if ($mode === 'manual' && !$this->option('force')) {
            $this->warn('Внимание: включён ручной режим!');
            $this->warn('Оптимизация пропущена. Используйте --force для принудительного запуска.');
            $this->info("\nДля переключения в авто режим: php artisan route:mode auto");
            return Command::SUCCESS;
        }
        
        // Отладочная информация
        if ($this->option('debug')) {
            $this->debugInfo();
        }
        
        $result = $optimizer->optimize();
        
        if (isset($result['error'])) {
            $this->error($result['error']);
            return Command::FAILURE;
        }
        
        $this->info("\n=== Результаты ===");
        $this->info("Забоев: {$result['stats']['total_miners']}");
        $this->info("Всего маршрутов: {$result['stats']['total_routes']}");
        $this->info("Активных: {$result['stats']['active_routes']}");
        $this->info("Раундов: {$result['stats']['rounds_count']}");
        
        if (!empty($result['rounds'])) {
            $this->info("\nПо раундам:");
            foreach ($result['rounds'] as $round => $count) {
                $this->line("  Раунд {$round}: {$count} маршрутов");
            }
        }
        
        if (!empty($result['activated'])) {
            $this->info("\nАктивированные маршруты:");
            foreach (array_slice($result['activated'], 0, 10) as $item) {
                $this->line("  Забой {$item['miner_id']} → Отвал {$item['dump_id']} (score: {$item['score']})");
            }
            if (count($result['activated']) > 10) {
                $this->line("  ... и ещё " . (count($result['activated']) - 10));
            }
        }
        
        return Command::SUCCESS;
    }
    
    protected function debugInfo(): void
    {
        $this->info("\n=== ОТЛАДКА ===");
        
        // 1. Активные забои
        $activeMiners = Miner::where('active', true)->get();
        $this->info("Активных забоев: {$activeMiners->count()}");
        
        foreach ($activeMiners as $miner) {
            $rock = $miner->currentRock;
            $this->line("  Забой {$miner->id} ({$miner->name_miner}): current_rock_id={$miner->current_rock_id}, порода = " . ($rock ? $rock->name_rock : 'НЕТ'));
        }
        
        // 2. Зоны
        $zones = Zone::where('delivery', true)->get();
        $this->info("\nЗон с delivery=true: {$zones->count()}");
        
        foreach ($zones as $zone) {
            $rocks = $zone->rocks->pluck('name_rock')->join(', ');
            $this->line("  Зона {$zone->id} (отвал {$zone->dump_id}): volume={$zone->volume}, capacity={$zone->capacity}, породы: {$rocks}");
        }
        
        // 3. Расстояния
        $distances = MinerDumpDistance::count();
        $this->info("\nРасстояний в базе: {$distances}");
        
        // 4. Маршруты для активных забоев
        $minerIds = $activeMiners->pluck('id')->toArray();
        $orders = MiningOrder::whereIn('miner_id', $minerIds)->get();
        $this->info("Маршрутов для активных забоев: {$orders->count()}");
        
        // 5. Проверка связей
        $this->info("\n=== Проверка связей ===");
        foreach ($activeMiners as $miner) {
            if (!$miner->currentRock) {
                $this->warn("Забой {$miner->id}: нет текущей породы!");
                continue;
            }
            
            $rockId = $miner->currentRock->id;
            
            $availableZones = Zone::where('delivery', true)
                ->whereHas('rocks', fn($q) => $q->where('rocks.id', $rockId))
                ->whereRaw('volume < capacity')
                ->get();
            
            $this->line("Забой {$miner->id} (порода {$rockId}): доступных зон = {$availableZones->count()}");
        }
    }
}
