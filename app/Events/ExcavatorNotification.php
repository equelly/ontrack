<?php

namespace App\Events;

use App\Models\Truck;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: Уведомление экскаваторщика
 * Отправляется на канал miner.{minerId}
 *
 * ВАЖНО: implements ShouldBroadcastNow (не ShouldBroadcast) — отправляет
 * в Reverb СИНХРОННО, без queue. Если queue worker не обрабатывает
 * BroadcastEvent jobs — события не доходят. ShouldBroadcastNow обходит queue.
 */
class ExcavatorNotification implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $minerId;
    public string $type;
    public array $payload;

    public function __construct(int $minerId, string $type, array $payload = [])
    {
        $this->minerId = $minerId;
        $this->type = $type;
        $this->payload = $payload;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('miner.' . $this->minerId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'payload' => $this->payload,
            'time' => now()->format('H:i:s'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'excavator.notification';
    }
}
