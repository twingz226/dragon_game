<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
| For the Dino game we use guest-friendly presence channels authenticated
| by a UUID (player_id) passed in the channel auth request.
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Presence channel for game rooms — no Laravel auth required, guest UUID auth
Broadcast::channel('game.room.{roomCode}', function ($user, $roomCode) {
    // Log the authentication attempt for debugging
    Log::info('Game room auth attempt', [
        'room_code' => $roomCode,
        'player_id' => request('player_id'),
        'player_name' => request('player_name'),
        'socket_id' => request('socket_id'),
        'channel_name' => request('channel_name')
    ]);
    
    // Get player data from request
    $playerId = request('player_id');
    $playerName = request('player_name');
    
    // Validate required parameters
    if (!$playerId) {
        Log::warning('Missing player_id in auth request');
        return false;
    }
    
    // Log successful authentication
    Log::info('Player authenticated successfully', [
        'player_id' => $playerId,
        'room_code' => $roomCode
    ]);
    
    // Return user-like object for presence channel
    return [
        'id' => $playerId,
        'name' => $playerName ?: 'Anonymous Player',
        'room_code' => $roomCode,
    ];
});
