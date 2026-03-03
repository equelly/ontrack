<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverRouteUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public int $truckId;
    public array $route;

    public function __construct(int $truckId, array $route)
    {
        $this->truckId = $truckId;
        $this->route = $route;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('driver.' . $this->truckId);
    }

    public function broadcastAs(): string
    {
        return 'DriverRouteUpdated';  // ← Имя класса (без точек)
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->route['action'] ?? 'route_assigned',
            'route' => $this->route,
            'truck_id' => $this->truckId,
            'ts'    => now()->toDateTimeString(),
        ];
    }
}