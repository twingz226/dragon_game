<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Score extends Model
{
    protected $fillable = ['player_name', 'player_id', 'room_code', 'score'];
}
