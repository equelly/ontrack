<?php

namespace App\Events;

use App\Models\Truck;
use App\Models\MiningOrder;
use App\Models\TruckTrip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
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
     * Конструктор поддерживает ТРИ формата:
     * 1. СТАРЫЙ: (int $driverId, array $payload) - для совместимости
     * 2. С MiningOrder: (Truck $truck, MiningOrder $order) - для нового кода
     * 3. С TruckTrip: (Truck $truck, TruckTrip $trip) - для загруженных грузовиков
     */
    public function __construct($truckOrDriverId, $orderOrPayload = null)
    {
        // Если первый аргумент - Truck объект (новый формат)
        if ($truckOrDriverId instanceof Truck) {
            $truck = $truckOrDriverId;
            
            $this->truckId = $truck->id;
            
            // Если второй аргумент - TruckTrip (для загруженных грузовиков)
            if ($orderOrPayload instanceof TruckTrip) {
                $trip = $orderOrPayload;
                $this->payload = [
                    'truck_id' => $truck->id,
                    'truck_number' => $truck->number,
                    'status' => $truck->status,
                    'trip_id' => $trip->id,
                    'order_id' => $trip->mining_order_id,
                    'miner_id' => $trip->miner_id,
                    'miner_name' => $trip->miner?->name_miner,
                    'dump_id' => $trip->dump_id,
                    'dump_name' => $trip->dump?->name_dump,
                    'zone_id' => $trip->zone_id,
                    'zone_name' => $trip->zone?->name_zone,
                    'rock_id' => $trip->rock_id,
                    'rock_name' => $trip->rock?->name_rock,
                    'load_volume' => $trip->load_volume,
                ];
            } else {
                // MiningOrder или null
                $order = $orderOrPayload;
                $this->payload = [
                    'truck_id' => $truck->id,
                    'truck_number' => $truck->number,
                    'status' => $truck->status,
                    'order_id' => $order->id ?? null,
                    'miner_id' => $order->miner_id ?? null,
                    'miner_name' => $order->miner?->name_miner ?? null,
                    'dump_id' => $order->dump_id ?? null,
                    'dump_name' => $order->dump?->name_dump ?? null,
                    'zone_id' => $order->zone_id ?? null,
                    'zone_name' => $order->zone?->name_zone ?? null,
                    'rock_name' => $order->rock?->name_rock ?? null,
                    'distance_km' => $order->distance_km ?? null,
                ];
            }
        } else {
            // Старый формат: (int $driverId, array $payload)
            $this->truckId = is_array($orderOrPayload) && isset($orderOrPayload['truck_id']) 
                ? $orderOrPayload['truck_id'] 
                : (int) $truckOrDriverId;
            $this->payload = is_array($orderOrPayload) ? $orderOrPayload : [];
        }
    }

    public function broadcastOn()
    {
        return new PrivateChannel('driver.' . $this->truckId);
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