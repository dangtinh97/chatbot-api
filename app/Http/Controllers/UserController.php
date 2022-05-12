<?php

namespace App\Http\Controllers;

use App\Helpers\FChatHelper;
use App\Http\Requests\FChatRegisterRequest;
use App\Http\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public $userService;
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }


    function register(FChatRegisterRequest $request)
    {
        $create = $this->userService->create($request->all());
        return response()->json($create->toArray());
    }

    function connect(Request $request)
    {
        $connect = $this->userService->connect($request->get('fb_uid'));
        return response()->json($connect);
    }

    function disconnect(Request $request)
    {
        $disconnect = $this->userService->disconnect($request->get('fb_uid'));
        return response()->json($disconnect);
    }

    function sendMessage(Request $request)
    {
        $send = $this->userService->sendMessage($request->get('fb_uid'),$request->get('message'));
        return response()->json($send);
    }

}
