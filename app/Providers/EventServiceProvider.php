<?php

namespace App\Providers;

use App\Events\BermCompleted;
use App\Events\LoadingCompleted;
use App\Events\MiningOrderActivated;
use App\Events\RockChanged;
use App\Events\ZoneClosed;
use App\Events\ZoneOpened;
use App\Listeners\RouteAssignmentListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * Подход B: раздельные события + один listener (Process Manager).
     * Каждое событие реализует интерфейс TriggersRouteAssignment,
     * RouteAssignmentListener::handle() принимает интерфейс (полиморфизм).
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // === EVENT-DRIVEN: Process Manager для назначения маршрутов ===
        // Любое из этих событий → RouteAssignmentListener (через queue worker)
        // → находит trucks.is_searching_route=true → для каждого assignForTruck()
        LoadingCompleted::class     => [RouteAssignmentListener::class],
        MiningOrderActivated::class  => [RouteAssignmentListener::class],
        ZoneOpened::class           => [RouteAssignmentListener::class],
        ZoneClosed::class            => [RouteAssignmentListener::class],
        RockChanged::class           => [RouteAssignmentListener::class],
        BermCompleted::class         => [RouteAssignmentListener::class],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}