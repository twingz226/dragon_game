<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameRoom extends Model
{
    protected $fillable = ['room_code', 'host_id', 'status', 'obstacle_seed'];

    public function scores()
    {
        return $this->hasMany(Score::class, 'room_code', 'room_code');
    }
}
