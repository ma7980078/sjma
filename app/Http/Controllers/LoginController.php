<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\CurlService;
use \Cache;

class LoginController extends Controller
{
    protected $curl;
    protected $header;
    public function __construct()
    {
        $this->curl='https://api.im.jpush.cn/v1/users/';
        $app_key='a9fb5de6649dcfd8854cb772:';
        $master_secret='289fefce3e0934bef1150c24';
        $this->header=['Authorization: Basic '.base64_encode($app_key.$master_secret),'Content-Type: application/json'];
    }

    //注册用户
    public function register(Request $request,CurlService $curlService){
        $validator = \Validator::make($request->all(), [
            'phone'     =>  'required|mobile',
            'verification_code'  =>  'required'
        ], [
            'phone.mobile'=>'电话格式不对',
        ]);

        if ( $validator->fails() ) {
            $message = array_values($validator->errors()->get('*'))[0][0];
            return json_encode( [ 'message' => $message,'code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }


        $data['phone']    = $request->input('phone');//手机号

        $verification_code = $request->input('verification_code');//验证码
        if($verification_code!=Cache::get($data['phone'])){
            return json_encode(['code'=>403,'message'=>'验证码错误']);
        }


        $data['username'] = $this->randomkeys(8);
        $data['status'] = 1;

        $user_info = DB::table('user')->where(['phone'=>$data['phone']])->first();
        if(empty($user_info)){

            $result = $this->insertDataReturnUserInfo($data);

            if(!$result){
                return json_encode(['code'=>403,'message'=>'入库失败']);
            }

            //调取极光注册用户接口
            $send_jg_data['username']=$data['phone'];
            $send_jg_data['password']=md5($data['phone'].'MotoCircle');
            $send_jg_data['nickname']=$data['username'];
            $send_jg_data['extras']=json_encode(['user_id'=>$result->id]);


            $inter_result = json_decode($curlService->send($this->curl,'POST',$this->header,$send_jg_data),true);
            if(isset($inter_result[0]['error'])){
                return json_encode(['code'=>@$inter_result[0]['code']['message'],'message'=>@$inter_result[0]['error']['message']]);
            }


            $token = $this->saveToken($data['phone']);

            $this->setLoginStatus($data['phone'],1);

            return json_encode(['code'=>200,'message'=>'注册成功','user_info'=>$user_info,'password'=>'','token'=>$token]);

        }else{
            $password = $user_info->password ? $user_info->befor.$user_info->after : '';
            $token = $this->saveToken($data['phone']);
            $this->setLoginStatus($data['phone'],1);
            return json_encode(['code'=>200,'message'=>'登录成功','user_info'=>$user_info,'password'=>$password,'token'=>$token]);
        }
    }
    function setLoginStatus($phone,$status){
        DB::table('user')->where(['phone'=>$phone])->update(['status'=>$status]);
    }

    function insertDataReturnUserInfo($data){
        $result = DB::table('user')->insert($data);
        $user_info='';
        if($result){
            $user_info = DB::table('user')->where(['phone'=>$data['phone']])->first();
        }
        return $user_info;
    }

    //登出
    public function loginOut(Request $request){
        $phone    = $request->input('phone');//手机号
        $this->setLoginStatus($phone,0);

        return json_encode(['code'=>200,'message'=>'退出成功']);
    }
    //验证
    public function reg_user(Request $request,CurlService $curlService){
        $token    = $request->input('token');
        $result     = $curlService->getToken($token);

        if(!$result){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }else{
            $token = $this->saveToken($result->phone);
            return json_encode( [ 'message' => 'success','code'=>'200','token'=>$token ],JSON_UNESCAPED_UNICODE );
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
                $token = $this->saveToken($phone);
                return json_encode( [ 'message' => '登录成功','code'=>'200','user_info'=>$user_info,'token'=>$token ],JSON_UNESCAPED_UNICODE );
            }else{
                return json_encode( [ 'message' => '密码错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
            }
        }
    }

    //保存token
    public function saveToken($phone){
        $token = md5($phone.time());
        DB::table('user')->where(['phone'=>$phone])->update(['token'=>$token]);
        return $token;
    }

    //修改用户头像
    public function updateHeaderImg(Request $request,CurlService $curlService){
        $token      = $request->input('token');
        $phone   = $request->input('phone');
        $file   = $request->file('head_img');
        $result     = $curlService->getToken($token);
        if(!$result){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
//        $data['password']    = md5($password);
//        $data['befor']    = substr($password,0,4);
//        $data['after']    = substr($password,4);
        $result = $this->img_upload($file,'headerImg');
        if(!$result){
            return json_encode( [ 'message' => '保存图片失败','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }else{
            //删除原头像图片
            $back_img = DB::table('user')->select("head_img")->where(['phone'=>$phone])->first();
            if($back_img->head_img){
                $file_path = public_path().'/storage/images/headerImg/'.$back_img->head_img;
                if(file_exists($file_path)){
                    unlink($file_path);
                }
            }

            //换新的头像
            DB::table('user')->where(['phone'=>$phone])->update(['head_img'=>$result]);
            return json_encode( [ 'message' => '保存图片成功','code'=>'200','path'=>$result ],JSON_UNESCAPED_UNICODE );

        }

    }
    public function img_upload($file,$type){

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
        $savePath = 'images/'.$type.'/' . $newFileName;
        // Web 访问路径
        $webPath = '/storage/'. $savePath;

        // 将文件保存到本地 storage/app/public/images 目录下，先判断同名文件是否已经存在，如果存在直接返回
        if (Storage::disk('public')->has($savePath)) {
            return  $newFileName;
        }
        // 否则执行保存操作，保存成功将访问路径返回给调用方
        if ($file->storePubliclyAs('images/'.$type, $newFileName, ['disk' => 'public'])) {
            return  $newFileName;
        }
        return false;

    }
    //修改背景图片
    public function backGroundImg(Request $request,CurlService $curlService){
        $token      = $request->input('token');
        $phone   = $request->input('phone');
        $file   = $request->file('back_img');
        $result_token     = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $result = $this->img_upload($file,'backGround');
        if(!$result){
            return json_encode( [ 'message' => '保存图片失败','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }else{
            //删除原背景图片
            $back_img = DB::table('user')->select("background_img")->where(['phone'=>$phone])->first();
            if($back_img->background_img){
                $file_path = public_path().'/storage/images/backGround/'.$back_img->background_img;
                if(file_exists($file_path)){
                    unlink($file_path);
                }
            }

            //换新的背景图片
            DB::table('user')->where(['phone'=>$phone])->update(['background_img'=>$result]);
            return json_encode( [ 'message' => '保存图片成功','code'=>'200','path'=>$result ],JSON_UNESCAPED_UNICODE );

        }
    }

    //获取用户详细信息
    public function getUserInfo(Request $request,CurlService $curlService){
        $token      = $request->input('token');
        $result_token     = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $user_id      = $request->input('user_id');
        $user_info = DB::table('user')->select([
            'id',
            'phone',
            'username',
            'created_at',
            'token',
            'status',
            'head_img',
            'background_img',
            'introduction',
            'sex',
            'address',
            'birth_date',
        ])->where(['id'=>$user_id])->first();
        if(empty($user_info)){
            return json_encode( [ 'message' => '用户不存在','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }else{
            return json_encode( [ 'message' => '获取成功','code'=>'200','user_info'=>$user_info ],JSON_UNESCAPED_UNICODE );
        }
    }

    //修改用户昵称(同步到极光)
    public function updateUserName(Request $request,CurlService $curlService){
        $token      = $request->input('token');
        $result_token     = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $phone   = $request->input('phone');
        $nickname   = $request->input('nickname');
        $validator = \Validator::make($request->all(), [
            'phone'     =>  'required',
            'nickname'  =>  'required'
        ]);

        if ( $validator->fails() ) {
            $message = array_values($validator->errors()->get('*'))[0][0];
            return json_encode( [ 'message' => $message,'code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }

        //调取极光注册用户接口
        $send_jg_data['nickname']=$nickname;


        $inter_result = json_decode($curlService->send($this->curl.$phone,'PUT',$this->header,$send_jg_data),true);


        if(isset($inter_result[0]['error'])){
            return json_encode(['code'=>@$inter_result[0]['code']['message'],'message'=>@$inter_result[0]['error']['message']]);
        }
        $result = DB::table('user')->where(['phone'=>$phone])->update(['username'=>$nickname]);
        if(!$result){
            return json_encode(['code'=>'401','message'=>'修改数据失败']);
        }else{
            return json_encode(['code'=>'200','message'=>'修改数据成功']);
        }

    }
    //修改个人简介
    public function updateUserIntroduction(Request $request,CurlService $curlService){
        $token      = $request->input('token');
        $result_token     = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $phone   = $request->input('phone');
        $desc   = $request->input('desc');
        $validator = \Validator::make($request->all(), [
            'phone'     =>  'required',
            'desc'  =>  'required'
        ]);

        if ( $validator->fails() ) {
            $message = array_values($validator->errors()->get('*'))[0][0];
            return json_encode( [ 'message' => $message,'code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $result = DB::table('user')->where(['phone'=>$phone])->update(['introduction'=>$desc]);
        if(!$result){
            return json_encode(['code'=>'401','message'=>'修改数据失败']);
        }else{
            return json_encode(['code'=>'200','message'=>'修改数据成功']);
        }

    }
    //修改性别
    public function updateUserSex(Request $request,CurlService $curlService){
        $token      = $request->input('token');
        $result_token     = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $phone   = $request->input('phone');
        $sex   = $request->input('sex');
        $validator = \Validator::make($request->all(), [
            'phone'     =>  'required',
            'sex'  =>  'required'
        ]);

        if ( $validator->fails() ) {
            $message = array_values($validator->errors()->get('*'))[0][0];
            return json_encode( [ 'message' => $message,'code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $result = DB::table('user')->where(['phone'=>$phone])->update(['sex'=>$sex]);
        if(!$result){
            return json_encode(['code'=>'401','message'=>'修改数据失败']);
        }else{
            return json_encode(['code'=>'200','message'=>'修改数据成功']);
        }

    }
    //修改所在地
    public function updateUserAddress(Request $request,CurlService $curlService){
        $token      = $request->input('token');
        $result_token     = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $phone   = $request->input('phone');
        $address  = $request->input('addresss');
        $validator = \Validator::make($request->all(), [
            'phone'     =>  'required',
            'address'  =>  'required'
        ]);

        if ( $validator->fails() ) {
            $message = array_values($validator->errors()->get('*'))[0][0];
            return json_encode( [ 'message' => $message,'code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $result = DB::table('user')->where(['phone'=>$phone])->update(['address'=>$address]);
        if(!$result){
            return json_encode(['code'=>'401','message'=>'修改数据失败']);
        }else{
            return json_encode(['code'=>'200','message'=>'修改数据成功']);
        }

    }
    //修改出生日期
    public function updateUserBirthDate(Request $request,CurlService $curlService){
        $token      = $request->input('token');
        $result_token     = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $phone   = $request->input('phone');
        $birth_date  = $request->input('birth_date');
        $validator = \Validator::make($request->all(), [
            'phone'     =>  'required',
            'birth_date'  =>  'required'
        ]);

        if ( $validator->fails() ) {
            $message = array_values($validator->errors()->get('*'))[0][0];
            return json_encode( [ 'message' => $message,'code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $result = DB::table('user')->where(['phone'=>$phone])->update(['birth_date'=>$birth_date]);
        if(!$result){
            return json_encode(['code'=>'401','message'=>'修改数据失败']);
        }else{
            return json_encode(['code'=>'200','message'=>'修改数据成功']);
        }

    }


    /*
   文字短信发送demo
   视频短信、弹屏闪信、语音短信、国际短信模板发送与文字短信类似
   */
public function sendSMS(Request $request){
    $phone   = $request->input('phone');
//    echo $request->session()->get('code');die;

    $action='sendtemplate';
    $url = 'http://www.lokapi.cn/smsUTF8.aspx';
    $username = '17611512282';
    $password =strtoupper(md5('17611512282'));
    $token = '4aa9d601';
    $templateid = '670D5EDE';
    $code = rand(1000,9999);
    Cache::put($phone,$code,1);

    $param = $phone.'|'.$code;
    $timestamp = $this->getMillisecond();
    $sign = strtoupper(md5('action='.$action.'&username='.$username.'&password='.$password.'&token='.$token.'&timestamp='.$timestamp));
    $postData = array
    (
        'action'=>$action,
        'username'=>$username,
        'password'=>$password,
        'token'=>$token,
        'timestamp'=>$timestamp,
        'sign'=>$sign,
        'rece'=>'json',
        'templateid'=>$templateid,
        'param'=>$param
    );

    $result= json_decode($this->postSMS($url,$postData),true);
    if($result['returnstatus']=='success'){
        return json_encode( [ 'message' => '发送成功','code'=>'200' ],JSON_UNESCAPED_UNICODE );
    }else{
        return json_encode( [ 'message' => '发送失败','code'=>'401' ],JSON_UNESCAPED_UNICODE );
    }
//    var_dump($result) ;
}




function postSMS($url,$postData)
{
    $row = parse_url($url);
    $host = $row['host'];
    $port = isset($row['port']) ? $row['port']:80;
    $file = $row['path'];
    $post = "";
    foreach($postData as $k=>$v){
        $post .= rawurlencode($k)."=".rawurlencode($v)."&";
    }
    $post = substr( $post , 0 , -1 );
    $len = strlen($post);
    $fp = @fsockopen( $host ,$port, $errno, $errstr, 10);
    if (!$fp) {
        return "$errstr ($errno)\n";
    } else {
        $receive = '';
        $out = "POST $file HTTP/1.1\r\n";
        $out .= "Host: $host\r\n";
        $out .= "Content-type: application/x-www-form-urlencoded\r\n";
        $out .= "Connection: Close\r\n";
        $out .= "Content-Length: $len\r\n\r\n";
        $out .= $post;
        fwrite($fp, $out);
        while (!feof($fp)) {
            $receive .= fgets($fp, 128);
        }
        fclose($fp);
        $receive = explode("\r\n\r\n",$receive);
        unset($receive[0]);
        return implode("",$receive);
    }
}
function getMillisecond() {
    list($t1, $t2) = explode(' ', microtime());
    return (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
}

}
