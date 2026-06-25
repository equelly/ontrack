<?php

namespace App\Events;

use App\Models\Truck;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;  // ← добавить
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoadingCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Truck $truck;
    public ?string $newZone;
    public ?string $newDump;

    public function __construct(Truck $truck, ?string $newZone = null, ?string $newDump = null)
    {
        $this->truck = $truck;
        $this->newZone = $newZone;
        $this->newDump = $newDump;
    }

    public function broadcastOn()
    {
        // приватный 
        return new PrivateChannel('truck.' . $this->truck->id);
    }

    public function broadcastWith(): array
    {
        $data = [
            'truck_id' => $this->truck->id,
            'truck_number' => $this->truck->number,
            'message' => "Погрузка завершена, начните движение к месту разгрузки",
        ];

        if ($this->newZone) {
            $data['zone_changed'] = true;
            $data['new_zone'] = $this->newZone;
            $data['new_dump'] = $this->newDump;
            $data['message'] = "Погрузка завершена. Место разгрузки изменено: {$this->newDump} - {$this->newZone}";
        }

        return $data;
    }

    public function broadcastAs(): string
    {
        return 'loading.completed';
    }
}