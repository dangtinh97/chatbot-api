<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameOanTuTi extends Model
{
    use HasFactory;
    protected $table ='game_oan_tu_ti';
    protected $fillable = ['room_uuid','player_1','player_2','status'];
}
