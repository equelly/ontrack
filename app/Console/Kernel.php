<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\InitMiningOrders;
use App\Console\Commands\CleanMiningOrders;
use App\Console\Commands\OptimizeRoutes;
use App\Console\Commands\AssignRocksToMiners;
use App\Console\Commands\DebugRoutes;
use App\Console\Commands\RouteMode;
use App\Console\Commands\PlanMaintenanceBalance;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        InitMiningOrders::class,
        CleanMiningOrders::class,
        OptimizeRoutes::class,
        AssignRocksToMiners::class,
        RouteMode::class,
        PlanMaintenanceBalance::class,
        DebugRoutes::class,
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

        // Балансировка парка: анализ забои↔самосвалы + очередь заявок
        // Запускается в начале каждой смены — 07:30 (дневная) и 19:30 (ночная)
        $schedule->command('maintenance:plan-balance')->dailyAt('07:30');
        $schedule->command('maintenance:plan-balance')->dailyAt('19:30');

}

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
