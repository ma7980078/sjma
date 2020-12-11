<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\CurlService;

class LoginController extends Controller
{
    //注册用户
    public function register(Request $request,CurlService $curlService){

        $validator = \Validator::make($request->all(), [
            'phone'     =>  'required|unique:user|mobile',
            'password'  =>  'required|min:8|max:64'
        ], [
            'phone.mobile'=>'电话格式不对',
        ]);

        if ( $validator->fails() ) {
            $message = array_values($validator->errors()->get('*'))[0][0];
            return json_encode( [ 'message' => $message,'code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }


        $data['phone']    = $request->input('phone');//手机号
        $data['befor']    = substr($request->input('password'),0,4);
        $data['after']    = substr($request->input('password'),4);
        $data['password'] = md5($request->input('password'));
        $data['username'] = $this->randomkeys(8);
        //调取极光注册用户接口
        $send_jg_data['username']=$data['phone'];
        $send_jg_data['password']=$request->input('password');
        $send_jg_data['nickname']=$data['username'];

        $curl='https://api.im.jpush.cn/v1/users/';
        $app_key='a9fb5de6649dcfd8854cb772:';
        $master_secret='289fefce3e0934bef1150c24';
        $header=['Authorization: Basic '.base64_encode($app_key.$master_secret),'Content-Type: application/json'];
        $inter_result = json_decode($curlService->send($curl,'POST',$header,$send_jg_data),true);
        if(isset($inter_result[0]['error'])){
            return json_encode(['code'=>$inter_result[0]['code']['message'],'message'=>$inter_result[0]['error']['message']]);
        }


        $result = DB::table('user')->insert($data);
        if($result){
            return json_encode(['code'=>200,'message'=>'success','data'=>'return why data?']);
        }else{
            return json_encode(['code'=>401,'message'=>'mysql insert error']);
        }
    }

    //随机生成用户名
    public function randomkeys($length){
        $pattern = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLOMNOPQRSTUVWXYZ';
        $key='';
         for($i=0;$i<$length;$i++) {
            $key .= $pattern{mt_rand(0,62)}; //生成php随机数
         }
        return $key;
    }

    //用户登录
    public function login(Request $request){
        $phone     = $request->input('phone');//手机号
        $password  = $request->input('password');
        $validator = \Validator::make($request->all(), [
            'phone'     =>  'required',
            'password'  =>  'required'
        ]);

        if ( $validator->fails() ) {
            $message = array_values($validator->errors()->get('*'))[0][0];
            return json_encode( [ 'message' => $message,'code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }

        $user_info = DB::table('user')->where(['phone'=>$phone])->first();
        if(empty($user_info)){
            return json_encode( [ 'message' => '用户不存在','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }else{
            if( $user_info->password==md5($password) ){
                $token = $this->saveToken($phone,$password);
                return json_encode( [ 'message' => '登录成功','code'=>'200','token'=>$token ],JSON_UNESCAPED_UNICODE );
            }else{
                return json_encode( [ 'message' => '密码错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
            }
        }
    }

    //保存token
    public function saveToken($phone,$password){
        $token = md5($phone.$password);
        DB::table('user')->where(['phone'=>$phone])->update(['token'=>$token]);
        return $token;
    }

    //修改用户个人信息
    public function updateInfo(Request $request,CurlService $curlService){
        $token   = $request->input('token');
        $result  = $curlService->getToken($token);
        if(!$result){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $type    = $request->input('type');
        $value   = $request->input('value');
        switch ($type){
            case 'img':
                $file  = $request->file('picture');

                $value = $this->img_upload($file);
                break;
        }
        if($value){
            DB::table('user')->where(['token'=>$token])->update([$type=>$value]);
            return json_encode( [ 'message' => 'success','code'=>'200','url'=>$value ],JSON_UNESCAPED_UNICODE );
        }else{
            return json_encode( [ 'message' => '上传失败','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }

    }
    public function img_upload($file){

        if (!$file->isValid()) {
            return false;
        }
        // 文件扩展名
        $extension = $file->getClientOriginalExtension();
        // 文件名
        $fileName = $file->getClientOriginalName();
        // 生成新的统一格式的文件名
        $newFileName = md5($fileName . time() . mt_rand(1, 10000)) . '.' . $extension;
        // 图片保存路径
        $savePath = 'images/' . $newFileName;
        // Web 访问路径
        $webPath = '/storage/'. $savePath;

        // 将文件保存到本地 storage/app/public/images 目录下，先判断同名文件是否已经存在，如果存在直接返回
        if (Storage::disk('public')->has($savePath)) {
            return  $webPath;
        }
        // 否则执行保存操作，保存成功将访问路径返回给调用方
        if ($file->storePubliclyAs('images', $newFileName, ['disk' => 'public'])) {
            return  $webPath;
        }
        return false;

    }
}
