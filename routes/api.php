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
Route::post( 'login/updateHeaderImg', 'LoginController@updateHeaderImg' )->name( 'login.updateHeaderImg' );//修改头像
Route::post( 'login/loginOut', 'LoginController@loginOut' )->name( 'login.loginOut' );//登出
Route::post( 'login/reg_user', 'LoginController@reg_user' )->name( 'login.reg_user' );//验证登录状态
Route::post( 'login/sendSMS', 'LoginController@sendSMS' )->name( 'login.sendSMS' );//发送验证码
Route::post( 'login/backGroundImg', 'LoginController@backGroundImg' )->name( 'login.backGroundImg' );//修改背景图片
Route::post( 'login/updateUserName', 'LoginController@updateUserName' )->name( 'login.updateUserName' );//修改用户昵称
Route::post( 'login/updateUserIntroduction', 'LoginController@updateUserIntroduction' )->name( 'login.updateUserIntroduction' );//修改简介
Route::post( 'login/updateUserSex', 'LoginController@updateUserSex' )->name( 'login.updateUserSex' );//修改性别
Route::post( 'login/updateUserAddress', 'LoginController@updateUserAddress' )->name( 'login.updateUserAddress' );//修改住址
Route::post( 'login/updateUserBirthDate', 'LoginController@updateUserBirthDate' )->name( 'login.updateUserBirthDate' );//修改出生日期
Route::post( 'login/getUserInfo', 'LoginController@getUserInfo' )->name( 'login.getUserInfo' );//获取用户详细信息


Route::post( 'publishNews/index', 'MoTo\PublishNewsController@index' )->name( 'publishNews.index' );//发布动态
Route::post( 'publishNews/getCommentInfo', 'MoTo\PublishNewsController@getCommentInfo' )->name( 'publishNews.getCommentInfo' );//获取动态信息
Route::post( 'publishNews/UserPublishNews', 'MoTo\PublishNewsController@UserPublishNews' )->name( 'publishNews.UserPublishNews' );//获取用户关联动态信息
Route::post( 'publishNews/favoritesNewsList', 'MoTo\PublishNewsController@favoritesNewsList' )->name( 'publishNews.favoritesNewsList' );//获取用户收藏文章
Route::post( 'publishNews/setUserFavorites', 'MoTo\PublishNewsController@setUserFavorites' )->name( 'publishNews.setUserFavorites' );//用户收藏文章/取消收藏
Route::post( 'publishNews/comment', 'MoTo\PublishNewsController@comment' )->name( 'publishNews.comment' );//用户评论
Route::post( 'publishNews/reply', 'MoTo\PublishNewsController@reply' )->name( 'publishNews.comment' );//用户回复
Route::post( 'publishNews/comment_list', 'MoTo\PublishNewsController@comment_list' )->name( 'publishNews.comment_list' );//评论列表
Route::post( 'publishNews/reply_list', 'MoTo\PublishNewsController@reply_list' )->name( 'publishNews.reply_list' );//回复列表
Route::post( 'publishNews/delete', 'MoTo\PublishNewsController@delete' )->name( 'publishNews.delete' );//删除


include base_path( 'routes/ybshui.php' );

