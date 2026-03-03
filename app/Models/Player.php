<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $fillable = [
        'player_id',
        'room_code', 
        'player_name',
        'is_host',
        'joined_at'
    ];

    protected $casts = [
        'is_host' => 'boolean',
        'joined_at' => 'datetime',
    ];

    public function room()
    {
        return $this->belongsTo(GameRoom::class, 'room_code', 'room_code');
    }
}
