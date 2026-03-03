<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;


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

    public function broadcastOn(): Channel
    {
        return new Channel('dispatcher');  // имя канала
    }

    public function broadcastAs(): string
    {
        return 'DispatcherNotification';  // Имя класса
    }

    public function broadcastWith(): array
    {
        Log::debug('DispatcherNotification broadcastWith', [
        'truck_id' => $this->truckId,
        'status' => $this->status,
    ]);
        return [
            'truck_id' => $this->truckId,
            'status'   => $this->status,
            'data'     => $this->payload,
            'ts'       => now()->toDateTimeString(),
        ];
    }
}