<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerDied implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $playerId;
    public string $playerName;
    public string $roomCode;
    public int $score;

    public function __construct(string $playerId, string $playerName, string $roomCode, int $score)
    {
        $this->playerId   = $playerId;
        $this->playerName = $playerName;
        $this->roomCode   = $roomCode;
        $this->score      = $score;
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('game.room.' . $this->roomCode),
        ];
    }

    public function broadcastAs(): string
    {
        return 'player-died';
    }
}
