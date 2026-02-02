<?php

namespace App\Events;

use App\Models\Truck;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class TruckStatusUpdated implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public Truck $truck;

    public function __construct(Truck $truck)
    {
        $this->truck = $truck;
    }

    // Личный канал водителя
    public function broadcastOn(): Channel
    {
        return new Channel("truck.{$this->truck->id}");
    }

    // Данные, которые приходят JS
    public function broadcastWith(): array
    {
        return [
            'truck_id'   => $this->truck->id,
            'status'     => $this->truck->status,
            'nextAction' => $this->truck->status, // можно кастомизировать
        ];
    }
    // 
    public function broadcastAs(): string
    {
        return 'TruckStatusUpdated';
    }

}
