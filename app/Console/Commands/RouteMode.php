<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Services\RouteOptimizerService;
use Illuminate\Console\Command;

class RouteMode extends Command
{
    protected $signature = 'route:mode 
                            {action : show|set|auto|manual}
                            {--force : Принудительно запустить оптимизацию в авто режиме}';
    protected $description = 'Управление режимом активации маршрутов';

    public function handle(RouteOptimizerService $optimizer): int
    {
        $action = $this->argument('action');
        
        switch ($action) {
            case 'show':
                return $this->showMode();
            
            case 'set':
                $mode = $this->choice('Выберите режим', ['auto', 'manual'], 0);
                return $this->setMode($optimizer, $mode);
            
            case 'auto':
                return $this->setMode($optimizer, 'auto');
            
            case 'manual':
                return $this->setMode($optimizer, 'manual');
            
            default:
                $this->error("Неизвестное действие: {$action}");
                $this->info("Доступные действия: show, set, auto, manual");
                return Command::FAILURE;
        }
    }
    
    protected function showMode(): int
    {
        $mode = SystemSetting::getRouteActivationMode();
        $activeRoutes = \App\Models\MiningOrder::where('active', true)->count();
        $totalRoutes = \App\Models\MiningOrder::count();
        
        $this->info("=== Текущий режим ===");
        $this->info("Режим: {$mode}" . ($mode === 'auto' ? ' (автоматический)' : ' (ручной)'));
        $this->info("Активных маршрутов: {$activeRoutes} / {$totalRoutes}");
        
        return Command::SUCCESS;
    }
    
    protected function setMode(RouteOptimizerService $optimizer, string $mode): int
    {
        $result = $optimizer->setMode($mode);
        
        if (isset($result['error'])) {
            $this->error($result['error']);
            return Command::FAILURE;
        }
        
        $this->info("Режим изменён: {$mode}");
        $this->info($result['message']);
        
        if ($mode === 'auto' && $this->option('force')) {
            $this->info("\nЗапуск оптимизации...");
            $optimizeResult = $optimizer->optimize();
            
            if (isset($optimizeResult['stats'])) {
                $this->info("Активных маршрутов: {$optimizeResult['stats']['active_routes']}");
            }
        }
        
        return Command::SUCCESS;
    }
}
