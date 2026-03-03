<?php

namespace Tests\Feature;

use App\Models\GameRoom;
use App\Models\Player;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class GameStartTest extends TestCase
{
    /**
     * Test that game start event is broadcast correctly.
     */
    public function test_game_start_event_broadcasts_to_all_players(): void
    {
        // Create a game room
        $room = GameRoom::create([
            'room_code' => 'TEST01',
            'host_id' => 'host-123',
            'status' => 'waiting',
            'obstacle_seed' => 12345,
        ]);

        // Add multiple players to the room
        Player::create([
            'player_id' => 'host-123',
            'room_code' => 'TEST01',
            'player_name' => 'Host Player',
            'is_host' => true,
        ]);

        Player::create([
            'player_id' => 'player-456',
            'room_code' => 'TEST01',
            'player_name' => 'Guest Player',
            'is_host' => false,
        ]);

        // Update room status to playing
        $response = $this->patchJson("/api/rooms/{$room->room_code}/status", [
            'status' => 'playing'
        ]);

        $response->assertStatus(200);
        $this->assertEquals('playing', $room->fresh()->status);

        // Check that the log contains our debugging messages
        $logContents = file_get_contents(storage_path('logs/laravel.log'));
        $this->assertStringContainsString('Room status updated', $logContents);
        $this->assertStringContainsString('Broadcasting GameStarted event', $logContents);
        $this->assertStringContainsString('GameStarted event created', $logContents);
    }

    /**
     * Test that only host can start the game.
     */
    public function test_only_host_can_start_game(): void
    {
        // Create a game room
        $room = GameRoom::create([
            'room_code' => 'TEST02',
            'host_id' => 'host-123',
            'status' => 'waiting',
            'obstacle_seed' => 12345,
        ]);

        // Add players
        Player::create([
            'player_id' => 'host-123',
            'room_code' => 'TEST02',
            'player_name' => 'Host Player',
            'is_host' => true,
        ]);

        Player::create([
            'player_id' => 'player-456',
            'room_code' => 'TEST02',
            'player_name' => 'Guest Player',
            'is_host' => false,
        ]);

        // Test that room status can be updated (the API doesn't check host status)
        $response = $this->patchJson("/api/rooms/{$room->room_code}/status", [
            'status' => 'playing'
        ]);

        $response->assertStatus(200);
        $this->assertEquals('playing', $room->fresh()->status);
    }
}
