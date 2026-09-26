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
    // ==========================================
    // Аналитика и обслуживание
    // ==========================================

    // AI: Анализ парка самосвалов — предсказание необходимости ТО
    // Запускаем в :00 минуту каждого часа
    $schedule->command('ai:analyze-fleet')
        ->hourly()
        ->withoutOverlapping()        // Не запускать повторно, если предыдущий ещё работает
        ->appendOutputTo(storage_path('logs/ai-analyze-fleet.log'));

    // ==========================================
    // Здоровье данных
    // ==========================================

    // Закрытие зависших рейсов (operator забыл завершить, сеть упала, etc.)
    // Каждые 30 минут — чтобы зависший рейс не жил дольше 30 минут
    // Запускаем со сдвигом :15 минут — чтобы не совпадало с AI-анализом
    $schedule->command('trips:close-stale')
        ->cron('15,45 * * * *')        // В :15 и :45 каждого часа
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/trips-close-stale.log'));

    // Опционально: если будешь деплоить на несколько серверов —
    // добавь ->onOneServer() к каждой команде, чтобы избежать дублей.
    // Например:
    //   $schedule->command('ai:analyze-fleet')->hourly()->onOneServer();
}

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
