<?php

namespace App\Events;

use App\Models\Truck;
use App\Models\MiningOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverRouteUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $truckId;
    public array $payload;

    /**
     * Конструктор поддерживает ДВА формата:
     * 1. СТАРЫЙ: (int $driverId, array $payload) - для совместимости
     * 2. НОВЫЙ: (Truck $truck, MiningOrder $order) - для нового кода
     */
    public function __construct($truckOrDriverId, $orderOrPayload = null)
    {
        if ($truckOrDriverId instanceof Truck) {
            $truck = $truckOrDriverId;
            $order = $orderOrPayload;
            
            $this->truckId = $truck->id;
            $this->payload = [
                'truck_id' => $truck->id,
                'truck_number' => $truck->number,
                'status' => $truck->status,
                'order_id' => $order->id,
                'miner_id' => $order->miner_id,
                'miner_name' => $order->miner?->name_miner,
                'dump_id' => $order->dump_id,
                'dump_name' => $order->dump?->name_dump,
                'zone_id' => $order->zone_id,
                'zone_name' => $order->zone?->name_zone,
                'rock_name' => $order->rock?->name_rock,
                'distance_km' => $order->distance_km,
            ];
        } else {
            $this->truckId = is_array($orderOrPayload) && isset($orderOrPayload['truck_id']) 
                ? $orderOrPayload['truck_id'] 
                : (int) $truckOrDriverId;
            $this->payload = is_array($orderOrPayload) ? $orderOrPayload : [];
        }
    }

    public function broadcastOn()
    {
        return new Channel('driver.' . $this->truckId);
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }

    public function broadcastAs(): string
    {
        return 'route.updated';
    }
}