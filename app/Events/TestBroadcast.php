<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast; // ВАЖНО
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestBroadcast implements \Illuminate\Contracts\Broadcasting\ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        // Имя канала должно совпадать с тем, что в JS (test-channel)
        return [
            new Channel('test-channel'),
        ];
    }

    public function broadcastAs(): string
    {
        // Имя события для JS (без него Laravel добавит namespace)
        return 'test.event';
    }
}

