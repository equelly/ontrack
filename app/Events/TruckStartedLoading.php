<?php

namespace App\Events;

use App\Models\Truck;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TruckStartedLoading implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Truck $truck;
    public int $minerId;

    public function __construct(Truck $truck, int $minerId)
    {
        $this->truck = $truck;
        $this->minerId = $minerId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('miner.' . $this->minerId);
    }

    public function broadcastWith(): array
    {
        return [
            'truck_id' => $this->truck->id,
            'truck_number' => $this->truck->number,
            'capacity' => $this->truck->load_capacity,
            'message' => "Начало погрузки самосвала {$this->truck->number} ",
        ];
    }

    public function broadcastAs(): string
    {
        return 'loading.started';
    }
}