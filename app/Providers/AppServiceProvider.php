<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use App\Models\Miner;
use App\Models\Dump;
use App\Models\MinerDumpDistance;
use App\Observers\MinerObserver;
use App\Observers\DumpObserver;
use App\Observers\MinerDumpDistanceObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Проверяем, используется ли связь с пакетом Doctrine
        if (class_exists(\Doctrine\DBAL\Connection::class)) {
            $platform = DB::connection()->getDoctrineSchemaManager()->getDatabasePlatform();
            // Говорим Doctrine воспринимать enum как обычную строку
            $platform->registerDoctrineTypeMapping('enum', 'string');
        }
                // Установка московского часового пояса
        date_default_timezone_set('Europe/Moscow');

        Carbon::setLocale('ru_RU');

        // Регистрация Observer для автоматического создания mining_orders
        Miner::observe(MinerObserver::class);
        Dump::observe(DumpObserver::class);
        MinerDumpDistance::observe(MinerDumpDistanceObserver::class);

        // Очистка OPcache в режиме разработки
        if ($this->app->environment('local') && function_exists('opcache_reset')) {
            opcache_reset();
        }
    }
}