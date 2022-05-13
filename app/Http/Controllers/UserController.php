<?php

namespace App\Http\Controllers;

use App\Http\Requests\FbUidRequest;
use App\Http\Requests\FChatRegisterRequest;
use App\Services\FacebookService;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public $userService;
    protected $facebookService;
    public function __construct(UserService $userService,FacebookService $facebookService)
    {
        $this->userService = $userService;
        $this->facebookService  =$facebookService;
    }


    function register(FChatRegisterRequest $request)
    {
        $create = $this->userService->create($request->all());
        return response()->json($create->toArray());
    }

    function connect(FbUidRequest $request)
    {
        $connect = $this->userService->connect($request->get('fb_uid'));
        return response()->json($connect);
    }

    function disconnect(FbUidRequest $request)
    {
        $disconnect = $this->userService->disconnect($request->get('fb_uid'));
        return response()->json($disconnect);
    }

    function sendMessage(FbUidRequest $request)
    {
        $send = $this->userService->sendMessage($request->get('fb_uid'),$request->get('message'));
        return response()->json($send);
    }

    function webhook(Request $request)
    {
        $data = $request->toArray();
        $process = $this->facebookService->webHook($data);
        return response()->json($process->toArray());
    }

}
