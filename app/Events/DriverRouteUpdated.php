<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverRouteUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public int $driverId;
    public array $route;

    public function __construct(int $driverId, array $route)
    {
        $this->driverId = $driverId;
        $this->route = $route;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('driver.' . $this->driverId);
    }

    public function broadcastAs(): string
    {
        return 'driver.route.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'route' => $this->route,
            'ts'    => now()->toDateTimeString(),
        ];
    }
}
