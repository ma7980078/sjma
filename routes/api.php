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
Route::match( [ 'GET', 'POST' ], 'publishNews/comment_list', 'MoTo\PublishNewsController@comment_list' )->name( 'publishNews.comment_list' );//评论列表
Route::post( 'publishNews/reply_list', 'MoTo\PublishNewsController@reply_list' )->name( 'publishNews.reply_list' );//回复列表
Route::post( 'publishNews/delete', 'MoTo\PublishNewsController@delete' )->name( 'publishNews.delete' );//删除
Route::post( 'publishNews/user_comment_list', 'MoTo\PublishNewsController@user_comment_list' )->name( 'publishNews.user_comment_list' );//查询用户被评论被回复列表
Route::post( 'publishNews/operation_read', 'MoTo\PublishNewsController@operation_read' )->name( 'publishNews.operation_read' );//评论||回复已读状态设置
Route::post( 'publishNews/delete_comment_or_reply', 'MoTo\PublishNewsController@delete_comment_or_reply' )->name( 'publishNews.delete_comment_or_reply' );//删除评论||回复
Route::match( [ 'GET', 'POST' ], 'publishNews/PublishNewsDetail', 'MoTo\PublishNewsController@PublishNewsDetail' )->name( 'publishNews.PublishNewsDetail' );//单篇文章/动态详情
Route::post( 'publishNews/sendPush', 'MoTo\PublishNewsController@sendPush' )->name( 'publishNews.sendPush' );//APP通知用户
Route::post( 'publishNews/message_in_comment_list', 'MoTo\PublishNewsController@message_in_comment_list' )->name( 'publishNews.message_in_comment_list' );//在消息页面点击进入文章详情评论区
Route::post( 'publishNews/like_list', 'MoTo\PublishNewsController@like_list' )->name( 'publishNews.like_list' );//APP通知用户
Route::post( 'publishNews/word_of_mouth_list', 'MoTo\PublishNewsController@word_of_mouth_list' )->name( 'publishNews.word_of_mouth_list' );//口碑列表
Route::post( 'publishNews/word_of_mouth_detail', 'MoTo\PublishNewsController@word_of_mouth_detail' )->name( 'publishNews.word_of_mouth_detail' );//口碑详情
Route::post( 'publishNews/car_publish', 'MoTo\PublishNewsController@car_publish' )->name( 'publishNews.word_of_mouth_publish' );//车辆对应的咨询
Route::post( 'publishNews/message_count', 'MoTo\PublishNewsController@message_count' )->name( 'publishNews.message_count' );//消息页面点赞评论总数
Route::post( 'publishNews/publish_like', 'MoTo\PublishNewsController@publish_like' )->name( 'publishNews.publish_like' );//咨询点赞列表
Route::post( 'publishNews/user_like', 'MoTo\PublishNewsController@user_like' )->name( 'publishNews.user_like' );//用户都给哪些文章点过赞
Route::post( 'publishNews/car_info', 'MoTo\PublishNewsController@car_info' )->name( 'publishNews.car_info' );//车辆详情


Route::post( 'publishNews/java_image_upload', 'MoTo\PublishNewsController@java_image_upload' )->name( 'publishNews.java_image_upload' );





Route::post( 'PersonalInfo/setLike', 'MoTo\PersonalInfoController@setLike' )->name( 'PersonalInfo.setLike' );//点赞
Route::post( 'PersonalInfo/search_all', 'MoTo\PersonalInfoController@search_all' )->name( 'PersonalInfo.search_all' );//搜索
Route::post( 'PersonalInfo/user_authen_car', 'MoTo\PersonalInfoController@user_authen_car' )->name( 'PersonalInfo.user_authen_car' );//车辆认证
Route::post( 'PersonalInfo/check_authen_list', 'MoTo\PersonalInfoController@check_authen_list' )->name( 'PersonalInfo.check_authen_list' );//所有车辆认证信息
Route::post( 'PersonalInfo/authen_result', 'MoTo\PersonalInfoController@authen_result' )->name( 'PersonalInfo.check_authen_list' );//车辆认证结果
Route::post( 'PersonalInfo/follow', 'MoTo\PersonalInfoController@follow' )->name( 'PersonalInfo.follow' );//关注
Route::post( 'PersonalInfo/follow_list', 'MoTo\PersonalInfoController@follow_list' )->name( 'PersonalInfo.follow_list' );//关注列表
Route::post( 'PersonalInfo/fans_list', 'MoTo\PersonalInfoController@fans_list' )->name( 'PersonalInfo.fans_list' );//粉丝列表
Route::post( 'PersonalInfo/user_follow_publish_list', 'MoTo\PersonalInfoController@user_follow_publish_list' )->name( 'PersonalInfo.user_follow_publish_list' );//关注的所有用户发布的咨询
Route::post( 'PersonalInfo/message_follow_list', 'MoTo\PersonalInfoController@message_follow_list' )->name( 'PersonalInfo.message_follow_list' );//消息列表的关注
Route::post( 'PersonalInfo/delete_message_follow', 'MoTo\PersonalInfoController@delete_message_follow' )->name( 'PersonalInfo.delete_message_follow' );//在消息列表那里删除关注信息，修改库里字段
Route::post( 'PersonalInfo/read_follow', 'MoTo\PersonalInfoController@read_follow' )->name( 'PersonalInfo.read_follow' );//已读关注信息
Route::post( 'PersonalInfo/search_all', 'MoTo\PersonalInfoController@search_all' )->name( 'PersonalInfo.search_all' );//搜索
Route::post( 'PersonalInfo/read_like', 'MoTo\PersonalInfoController@read_like' )->name( 'PersonalInfo.read_like' );//点赞已读





include base_path( 'routes/ybshui.php' );

