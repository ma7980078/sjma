<?php

namespace App\Http\Controllers\MoTo;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Validator;
use App\Services\CurlService;
use App\Services\UploadFileService;

class PersonalInfoController extends Controller
{
    protected $curl;
    protected $header;
    protected $username;
    protected $password;
    public function __construct()
    {
        $this->curl='https://api.jpush.cn/v3/push';
        $app_key='a9fb5de6649dcfd8854cb772:';
        $master_secret='289fefce3e0934bef1150c24';
        $this->header=['Authorization: Basic '.base64_encode($app_key.$master_secret),'Content-Type: application/json'];
        $this->username='729145859@qq.com';
        $this->password='GwS612106';
    }



    public function index( Request $request, CurlService $curlService, UploadFileService $uploadFileService ){

        $token              = $request->input('token');
        $data['news_type']  = $request->input('news_type');
        $result_token       = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }

    }

    public function setLike(Request $request, CurlService $curlService){
        $token              = $request->input('token');
        $data['user_id']    = $request->input('user_id');
        $data['type_id']    = $request->input('type_id');
        $data['type']       = $request->input('type');
        $data['status']     = $request->input('status');
        $result_token       = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $result = DB::table('like')->updateOrInsert([
            'user_id'   =>  $data['user_id'],
            'type_id'   =>  $data['type_id'],
            'type'      =>  $data['type'],
        ],$data);

        if(!$result){
            return json_encode(['code'=>401,'message'=>'点赞失败']);
        }else{
            return json_encode(['code'=>200,'message'=>'点赞成功']);
        }
    }
    //获取用户的点赞列表
    public function likeList(){

    }
}
