<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//Route::post('register','')
Route::group([
    'prefix' => 'f-chat'
],function (){
    Route::post('/send-message',[\App\Http\Controllers\UserController::class,'sendMessage']);
});

Route::post('/register',[\App\Http\Controllers\UserController::class,'register']); //đăng ký user
Route::post('/connect',[\App\Http\Controllers\UserController::class,'connect']);
Route::post('/disconnect',[\App\Http\Controllers\UserController::class,'disconnect']);

