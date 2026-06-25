<?php

namespace App\Events;

use App\Models\Truck;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ZoneChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Truck $truck;
    public string $oldZone;
    public string $newZone;
    public string $newDump;

    public function __construct(Truck $truck, string $oldZone, string $newZone, string $newDump)
    {
        $this->truck = $truck;
        $this->oldZone = $oldZone;
        $this->newZone = $newZone;
        $this->newDump = $newDump;
    }

    public function broadcastOn()
    {
        return new Channel('truck.' . $this->truck->id);
    }

    public function broadcastWith(): array
    {
        return [
            'truck_id' => $this->truck->id,
            'truck_number' => $this->truck->number,
            'old_zone' => $this->oldZone,
            'new_zone' => $this->newZone,
            'new_dump' => $this->newDump,
            'message' => "Место разгрузки изменено: {$this->newDump} - {$this->newZone}",
        ];
    }

    public function broadcastAs(): string
    {
        return 'zone.changed';
    }
}