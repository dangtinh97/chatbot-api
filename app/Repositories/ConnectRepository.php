<?php

namespace App\Repositories;

use App\Models\Connect;
use Illuminate\Database\Eloquent\Model;

class ConnectRepository extends BaseRepository
{
    public function __construct(Connect $model)
    {
        parent::__construct($model);
    }

    public function userFindConnectAndSetBusy(int $userId,$uuid="")
    {
        $q = $this->model::query();
        $q->where('status','=',Connect::STATUS_FIND)
            ->where('from_user_id','!=',$userId);
        $userFind= $q->first();
        if(!is_null($userFind)){
            $userFind->update([
                'status' => Connect::STATUS_BUSY,
                'to_user_id' => $userId,
                'room_uuid' => $uuid
            ]);
        }
        return $userFind;
    }
}
