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
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('player_id', 36)->unique(); // UUID from frontend
            $table->string('room_code', 8);
            $table->string('player_name', 30);
            $table->boolean('is_host')->default(false);
            $table->timestamp('joined_at');
            $table->timestamps();
            
            $table->foreign('room_code')->references('room_code')->on('game_rooms')->onDelete('cascade');
            $table->index(['room_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
