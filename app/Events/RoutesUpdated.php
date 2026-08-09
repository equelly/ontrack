<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoutesUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct()
    {
        //
    }

    public function broadcastOn(): array
    {
        // Транслируем на публичный канал 'routes'
        return [
            new Channel('routes'),
        ];
    }

    public function broadcastWith(): array
    {
        // Минимальный payload, просто чтобы дать сигнал
        return ['status' => 'updated'];
    }
}