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
        // Doctrine enum mapping (оставляем)
        if (class_exists(\Doctrine\DBAL\Connection::class)) {
            $platform = DB::connection()->getDoctrineSchemaManager()->getDatabasePlatform();
            $platform->registerDoctrineTypeMapping('enum', 'string');
        }

        date_default_timezone_set('Europe/Moscow');
        Carbon::setLocale('ru_RU');

        Miner::observe(MinerObserver::class);
        Dump::observe(DumpObserver::class);
        MinerDumpDistance::observe(MinerDumpDistanceObserver::class);

        // ⚠️ УБРАТЬ opcache_reset() — он сбрасывает кеш на каждый запрос!
        // В local окружении OPcache и так проверяет timestamp файлов,
        // если opcache.validate_timestamps=1 в php.ini.
    }
}