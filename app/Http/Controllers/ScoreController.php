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
        $scores = Score::query()
            ->selectRaw('TRIM(player_name) as player_name, MAX(score) as score')
            ->groupByRaw('TRIM(player_name)')
            ->orderByDesc('score')
            ->limit(10)
            ->get();

        return response()->json($scores);
    }

    public function getPersonalHighScores(Request $request)
    {
        $request->validate([
            'player_ids' => 'required|array',
            'player_ids.*' => 'string|max:36'
        ]);

        $highScores = Score::selectRaw('player_id, MAX(score) as high_score')
            ->whereIn('player_id', $request->player_ids)
            ->groupBy('player_id')
            ->get()
            ->pluck('high_score', 'player_id');

        return response()->json($highScores);
    }
}

