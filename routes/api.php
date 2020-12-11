<?php

use Illuminate\Http\Request;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});



Route::post( 'login/register', 'LoginController@register' )->name( 'login.register' );//注册
Route::post( 'login/login', 'LoginController@login' )->name( 'login.login' );//登录
Route::post( 'login/updateInfo', 'LoginController@updateInfo' )->name( 'login.updateInfo' );//修改信息
