<?php

namespace App\Events;

use App\Models\Truck;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * NoZoneAvailable — нет зоны для породы, требуется решение диспетчера.
 * ShouldBroadcastNow — критичное (диспетчер должен немедленно решить).
 */
class NoZoneAvailable implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Truck $truck;
    public string $rockName;

    public function __construct(Truck $truck, string $rockName)
    {
        $this->truck = $truck;
        $this->rockName = $rockName;
    }

    public function broadcastOn()
    {
        return new Channel('dispatcher');
    }

    public function broadcastWith(): array
    {
        return [
            'truck_number' => $this->truck->number,
            'rock_name' => $this->rockName,
            'message' => "Нет доступной зоны для породы '{$this->rockName}' (самосвал {$this->truck->number})",
            'type' => 'warning',
        ];
    }

    public function broadcastAs(): string
    {
        return 'zone.unavailable';
    }
}