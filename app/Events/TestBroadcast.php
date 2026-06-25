<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    // app/Events/TestBroadcast.php
public function __construct(public string $message) 
{
    //
}


    public function broadcastOn(): Channel
    {
        return new Channel('test-channel');
    }

    public function broadcastAs(): string
    {
        return 'test.event';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => '🔥 Reverb работает!',
            'time' => now()->toDateTimeString(),
        ];
    }
}
