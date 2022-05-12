<?php

namespace App\Http\Responses;

interface CanRespond
{
    public function toArray();
    public function getStatus();
    public function getData();
}
