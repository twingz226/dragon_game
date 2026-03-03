<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('game_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_code', 8)->unique();
            $table->string('host_id', 36); // UUID of host player
            $table->enum('status', ['waiting', 'playing', 'ended'])->default('waiting');
            $table->unsignedBigInteger('obstacle_seed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_rooms');
    }
};
