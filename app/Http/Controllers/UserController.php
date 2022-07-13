<?php

namespace App\Http\Controllers;

use App\Services\FacebookService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    protected $facebookService;
    public function __construct(FacebookService $facebookService)
    {
        $this->facebookService  =$facebookService;
    }

    function webhook(Request $request)
    {
        $data = $request->toArray();
        $process = $this->facebookService->webHook($data);
        return response()->json($process->toArray());
    }

}
