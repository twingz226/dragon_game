<?php

use App\Http\Controllers\ScoreController;
use Illuminate\Support\Facades\Route;

Route::post('/scores', [ScoreController::class, 'store']);
Route::get('/leaderboard', [ScoreController::class, 'leaderboard']);
Route::post('/personal-high-scores', [ScoreController::class, 'getPersonalHighScores']);
