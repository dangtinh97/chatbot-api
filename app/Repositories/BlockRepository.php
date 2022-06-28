<?php

namespace App\Repositories;

use App\Models\Block;

class BlockRepository extends BaseRepository
{
    public function __construct(Block $model)
    {
        parent::__construct($model);
    }
}
