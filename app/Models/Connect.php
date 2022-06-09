<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Connect extends Model
{
    use HasFactory;
    protected $table = 'connects';
    protected $fillable = ['from_user_id','to_user_id','status','message_last','send_last','count_message_day','room_uuid'];
    const MAX_MESSAGE_DAY = 100;
    const STATUS_FREE = "FREE";
    const STATUS_BUSY = "BUSY";
    const STATUS_FIND = "FIND";

    public function user(){
        return $this->hasOne(User::class,'id','from_user_id');
    }

    public function oantuti(){
        return $this->hasOne(GameOanTuTi::class,'room_uuid','room_uuid');
    }

    public function userConnect(){
        return $this->hasOne(User::class,"id","to_user_id");
    }
}
