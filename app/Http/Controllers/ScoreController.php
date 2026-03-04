<?php

namespace App\Http\Controllers;

use App\Models\Score;
use Illuminate\Http\Request;

class ScoreController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'player_name' => 'required|string|max:30',
            'player_id'   => 'required|string|max:36',
            'room_code'   => 'nullable|string|max:8',
            'score'       => 'required|integer|min:0',
        ]);

        $score = Score::create($validated);

        return response()->json(['success' => true, 'score' => $score], 201);
    }

    public function leaderboard()
    {
        $scores = Score::select('player_name', 'score', 'created_at')
            ->orderByDesc('score')
            ->limit(5)
            ->get();

        return response()->json($scores);
    }
}
