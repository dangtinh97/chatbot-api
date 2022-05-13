<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class UserRepository extends BaseRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findOrCreateUser($senderId)
    {
        return $this->updateOrCreate([
            'fb_uid' => $senderId
        ],[
            'fb_uid' => $senderId,
            'full_name' => "ẩn danh",
            'password' => Hash::make("hauichatbot123"),
            'gender' => "MALE",
        ]);
    }
}
