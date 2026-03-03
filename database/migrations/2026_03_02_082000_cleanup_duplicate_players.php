<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clean up duplicate player entries before applying constraints
        // Keep only the most recent entry for each player
        $duplicatePlayers = DB::table('players')
            ->select('player_id', DB::raw('count(*) as count'))
            ->groupBy('player_id')
            ->having('count', '>', 1)
            ->pluck('player_id');

        foreach ($duplicatePlayers as $playerId) {
            // Get all entries for this player, ordered by joined_at (most recent first)
            $playerEntries = DB::table('players')
                ->where('player_id', $playerId)
                ->orderBy('joined_at', 'desc')
                ->get();
            
            // Keep the first (most recent) entry, delete the rest
            $keepEntry = $playerEntries->first();
            $deleteEntries = $playerEntries->slice(1);
            
            foreach ($deleteEntries as $entry) {
                DB::table('players')->where('id', $entry->id)->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is for cleanup only, no reversal needed
        // The constraint changes are handled in the previous migration
    }
};
