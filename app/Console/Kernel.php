<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\InitMiningOrders;
use App\Console\Commands\CleanMiningOrders;
use App\Console\Commands\OptimizeRoutes;
use App\Console\Commands\AssignRocksToMiners;
use App\Console\Commands\RouteMode;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        InitMiningOrders::class,
        CleanMiningOrders::class,
        OptimizeRoutes::class,
        AssignRocksToMiners::class,
        RouteMode::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
