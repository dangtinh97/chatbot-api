<?php

namespace App\Repositories;

use App\Models\GameOanTuTi;
use Illuminate\Database\Eloquent\Model;

class OanTuTiRepository extends BaseRepository
{
    public function __construct(GameOanTuTi $model)
    {
        parent::__construct($model);
    }
}
