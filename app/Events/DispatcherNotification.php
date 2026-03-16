<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DispatcherNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $truckId;
    public string $status;
    public array $payload;

    public function __construct(
        int $truckId,
        string $status,
        array $payload = []
    ) {
        $this->truckId = $truckId;
        $this->status = $status;
        $this->payload = $payload;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('dispatcher'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'truck_id' => $this->truckId,
            'status' => $this->status,
            'payload' => $this->payload,
            'time' => now()->format('H:i:s')
        ];
    }

    public function broadcastAs(): string
    {
        return 'truck-updated';
    }
}
