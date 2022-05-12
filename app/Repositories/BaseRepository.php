<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;

class BaseRepository
{
    public $model;
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function create(array $data)
    {
        return $this->model::query()->create($data);
    }

    public function updateOrCreate($cond,$data)
    {
        return $this->model::query()->updateOrCreate($cond,$data);
    }

    public function find($cond){
        return $this->model::query()->where($cond)->get();
    }

    public function findOne($cond)
    {
        return $this->model::query()->where($cond)->first();
    }
}
