<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;

class GameStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomCode;
    public $obstacleSeed;

    public function __construct(string $roomCode, int $obstacleSeed)
    {
        $this->roomCode = $roomCode;
        $this->obstacleSeed = $obstacleSeed;
        
        Log::info('GameStarted event created', [
            'room_code' => $roomCode,
            'obstacle_seed' => $obstacleSeed,
            'channel' => 'game.room.' . $roomCode
        ]);
    }

    public function broadcastOn(): array
    {
        $channel = new PresenceChannel('game.room.' . $this->roomCode);
        
        Log::info('GameStarted event broadcasting on channel', [
            'channel_name' => $channel->name,
            'room_code' => $this->roomCode
        ]);
        
        return [$channel];
    }

    public function broadcastAs(): string
    {
        return 'game.started';
    }
    
    public function broadcastWith(): array
    {
        return [
            'roomCode' => $this->roomCode,
            'obstacleSeed' => $this->obstacleSeed,
            'timestamp' => now()->toISOString()
        ];
    }
}
