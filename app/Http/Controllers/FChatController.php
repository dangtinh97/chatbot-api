<?php

namespace App\Http\Controllers;

use App\Http\Requests\FChatRegisterRequest;
use Illuminate\Http\Request;

class FChatController extends Controller
{
    function testApi(Request $request)
    {
        return response()->json([
           [
               'text' => "ĐÂY LÀ API ĐỂ TEST\n".date('Y/m/d H:i:s',time())
           ]
        ]);
    }
}
