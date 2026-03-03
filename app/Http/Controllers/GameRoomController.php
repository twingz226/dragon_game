<?php

namespace App\Http\Controllers;

use App\Events\GameStarted;
use App\Models\GameRoom;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GameRoomController extends Controller
{
    public function join(Request $request)
    {
        $request->validate([
            'player_id'   => 'required|string|max:36',
            'player_name' => 'required|string|max:30',
            'room_code'   => 'nullable|string|max:8',
        ]);

        $roomCode = $request->room_code;

        if ($roomCode) {
            // Join existing room
            $room = GameRoom::where('room_code', $roomCode)->first();
            if (! $room) {
                return response()->json(['error' => 'Room not found.'], 404);
            }
            $isHost = ($room->host_id === $request->player_id);
        } else {
            // Create new room
            do {
                $roomCode = strtoupper(Str::random(6));
            } while (GameRoom::where('room_code', $roomCode)->exists());

            $seed = random_int(1, 999999999);
            $room = GameRoom::create([
                'room_code'     => $roomCode,
                'host_id'       => $request->player_id,
                'status'        => 'waiting',
                'obstacle_seed' => $seed,
            ]);
            $isHost = true;
        }

        // Add or update player in the room
        Player::updateOrCreate(
            [
                'player_id' => $request->player_id,
                'room_code' => $roomCode,
            ],
            [
                'player_name' => $request->player_name,
                'is_host' => $isHost,
                'joined_at' => now(),
            ]
        );

        return response()->json([
            'room_code'     => $room->room_code,
            'is_host'       => $isHost,
            'host_id'       => $room->host_id,
            'status'        => $room->status,
            'obstacle_seed' => $room->obstacle_seed,
        ]);
    }

    public function show($code)
    {
        $room = GameRoom::where('room_code', $code)->firstOrFail();
        return response()->json($room);
    }

    public function getPlayers($code)
    {
        $room = GameRoom::where('room_code', $code)->firstOrFail();
        
        $players = Player::where('room_code', $code)
            ->orderBy('joined_at')
            ->get()
            ->map(function ($player) {
                return [
                    'id' => $player->player_id,
                    'name' => $player->player_name,
                    'isHost' => $player->is_host,
                    'joinedAt' => $player->joined_at
                ];
            });
        
        return response()->json($players);
    }

    public function updateStatus(Request $request, $code)
    {
        $request->validate(['status' => 'required|in:waiting,playing,ended']);
        $room = GameRoom::where('room_code', $code)->firstOrFail();
        
        $oldStatus = $room->status;
        $room->update(['status' => $request->status]);
        
        Log::info('Room status updated', [
            'room_code' => $code,
            'old_status' => $oldStatus,
            'new_status' => $request->status,
            'obstacle_seed' => $room->obstacle_seed
        ]);
        
        // Broadcast game started event to all players in the room
        if ($request->status === 'playing') {
            Log::info('Broadcasting GameStarted event', [
                'room_code' => $code,
                'obstacle_seed' => $room->obstacle_seed
            ]);
            
            // Create and broadcast the event immediately using ShouldBroadcastNow
            $event = new GameStarted($code, $room->obstacle_seed);
            broadcast($event);
            
            Log::info('GameStarted event broadcast completed');
        }
        
        return response()->json($room);
    }
}
