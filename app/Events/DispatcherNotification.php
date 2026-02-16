<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DispatcherNotification implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

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

    /**
     * Канал (пока public, как test-channel)
     */
    public function broadcastOn(): Channel
    {
        return new Channel('dispatcher-channel');
    }

    /**
     * Имя события (ВАЖНО — с точкой)
     */
    public function broadcastAs(): string
    {
        return 'dispatcher.notification';
    }

    /**
     * Payload события
     */
    public function broadcastWith(): array
    {
        return [
            'truck_id' => $this->truckId,
            'status'   => $this->status,
            'data'     => $this->payload,
            'ts'       => now()->toDateTimeString(),
        ];
    }
}
