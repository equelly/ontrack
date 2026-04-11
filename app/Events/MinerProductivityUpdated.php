<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MinerProductivityUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $minerId;
    public array $data;

    public function __construct(int $minerId, array $data = [])
    {
        $this->minerId = $minerId;
        $this->data = $data;
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
            'miner_id' => $this->minerId,
            'target_load_time' => $this->data['target_load_time'] ?? null,
            'avg_load_time' => $this->data['avg_load_time'] ?? null,
            'avg_wait_time' => $this->data['avg_wait_time'] ?? null,
            'recommended_trucks' => $this->data['recommended_trucks'] ?? null,
            'current_trucks' => $this->data['current_trucks'] ?? null,
            'balance' => $this->data['balance'] ?? null,
            'time' => now()->format('H:i:s')
        ];
    }

    public function broadcastAs(): string
    {
        return 'miner-productivity-updated';
    }
}
