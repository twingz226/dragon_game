<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the existing unique constraint on player_id
        Schema::table('players', function (Blueprint $table) {
            $table->dropUnique(['player_id']);
            
            // Add composite unique constraint for player_id + room_code
            $table->unique(['player_id', 'room_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropUnique(['player_id', 'room_code']);
            $table->unique(['player_id']);
        });
    }
};
