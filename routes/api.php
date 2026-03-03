<?php

use App\Http\Controllers\ScoreController;
use App\Http\Controllers\GameRoomController;
use Illuminate\Support\Facades\Route;

Route::post('/scores', [ScoreController::class, 'store']);
Route::get('/leaderboard', [ScoreController::class, 'leaderboard']);

Route::post('/rooms/join', [GameRoomController::class, 'join']);
Route::get('/rooms/{code}', [GameRoomController::class, 'show']);
Route::get('/rooms/{code}/players', [GameRoomController::class, 'getPlayers']);
Route::patch('/rooms/{code}/status', [GameRoomController::class, 'updateStatus']);
