<?php

namespace App\Http\Controllers\MoTo;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MoTo\PublishNewsController;
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
        $data['from_userid']= $request->input('from_userid');
        $data['to_userid']  = $request->input('to_userid');
        $data['type_id']    = $request->input('type_id');
        $data['type']       = $request->input('type');
        $data['status']     = $request->input('status');
        $result_token       = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $result = DB::table('like')->updateOrInsert([
            'from_userid'   =>  $data['from_userid'],
            'type_id'   =>  $data['type_id'],
            'type'      =>  $data['type'],
        ],$data);
        if($result && $data['status']==1){
            if($data['type']==1){
                DB::table('publish_news')->where(['id'=>$data['type_id']])->increment('like_count');
            }else if($data['type']==2){
                DB::table('comment')->where(['id'=>$data['type_id']])->increment('like_count');
            }else if($data['type']==3){
                DB::table('reply')->where(['id'=>$data['type_id']])->increment('like_count');
            }

            $new_insert_data = DB::table('like')
                ->where('from_userid','=',$data['from_userid'])
                ->where('type_id','=',$data['type_id'])
                ->where('type','=',$data['type'])
                ->first();
            if($new_insert_data->created_at == $new_insert_data->updated_at){
                $publish_send_push = new PublishNewsController;
                $publish_send_push->sendPush('like',['id'=>$new_insert_data->id]);
            }


        }else{
            if($data['type']==1){
                DB::table('publish_news')->where(['id'=>$data['type_id']])->decrement('like_count');
            }else if($data['type']==2){
                DB::table('comment')->where(['id'=>$data['type_id']])->decrement('like_count');
            }else if($data['type']==3){
                DB::table('reply')->where(['id'=>$data['type_id']])->decrement('like_count');
            }
        }


        if(!$result){
            return json_encode(['code'=>401,'message'=>'点赞失败']);
        }else{
            return json_encode(['code'=>200,'message'=>'点赞成功']);
        }

    }

    //搜索
    public function search_all(Request $request, CurlService $curlService){
        $keyword    = $request->input('keyword');
        $type       = $request->input('type');
        $per_page   = $request->input('per_page');
        $page       = $request->input('page');
        $result = DB::table('publish_news')
            ->select([
                'user.username',
                'user.head_img',
                'user.authen_status',
                'user.authen_car_name',
                'user.authen_brand_log',
                'publish_news.id',
                'publish_news.user_id',
                'publish_news.image',
                'publish_news.thumbnails_img',
                'publish_news.video_img_logo',
                'publish_news.content',
                'publish_news.news_type',
                'publish_news.img_w_h',
                'publish_news.title',
                'publish_news.created_at',
                'publish_news.pv',
                'publish_news.comment_reply_num',
                'publish_news.show',
                'publish_news.power',
                'publish_news.operate',
                'publish_news.config',
                'publish_news.comfort',
                'topic.topic',
                'carList.carName',
                'carList.goodsCarName',
            ])
            ->leftJoin('favorites',function($join){
                $join->on('publish_news.id','=','favorites.publish_id')->on('publish_news.user_id','=','favorites.user_id');
            })
            ->leftJoin( 'user', 'publish_news.user_id', '=', 'user.id' )
            ->leftJoin('brandGood','publish_news.model','=','brandGood.goodId')
            ->leftJoin('carList','publish_news.carId','=','carList.carId')
            ->leftJoin('topic','publish_news.topic','=','topic.id');

        switch($type){
            case 'all'://全部搜索
                $result = $result->where(function ($query) use ($keyword){
                    $query->orWhere('publish_news.title', 'like', "%{$keyword}%")
                        ->orWhere('publish_news.content', 'like', "%{$keyword}%")
                        ->orWhere('brandGood.goodName', 'like', "%{$keyword}%")
                        ->orWhere('user.username', 'like', "%{$keyword}%");
                });
                break;
            case 'dynamic'://动态搜索
                $result = $result->where(function ($query) use ($keyword){
                    $query->where('publish_news.type', '=', 0)
                        ->orWhere('publish_news.title', 'like', "%{$keyword}%")
                        ->orWhere('publish_news.content', 'like', "%{$keyword}%");
                });
                break;
            case 'article'://文章搜索
                $result = $result->where(function ($query) use ($keyword){
                    $query->where('publish_news.type', '=', 1)
                        ->orWhere('publish_news.title', 'like', "%{$keyword}%")
                        ->orWhere('publish_news.content', 'like', "%{$keyword}%");
                });
                break;
            case 'video'://视频搜索
                $result = $result->where(function ($query) use ($keyword){
                    $query->where('publish_news.type', '=', 2)
                        ->orWhere('publish_news.title', 'like', "%{$keyword}%")
                        ->orWhere('publish_news.content', 'like', "%{$keyword}%");
                });
                break;
            case 'public_praise'://口碑搜索
                $result = $result->where(function ($query) use ($keyword){
                    $query->where('publish_news.type', '=', 3)
                        ->orWhere('publish_news.title', 'like', "%{$keyword}%")
                        ->orWhere('publish_news.content', 'like', "%{$keyword}%");
                });
                break;
            case 'model'://车型搜索
                $result = $result->where(function ($query) use ($keyword){
                    $query->where('brandGood.goodName', 'like', "%{$keyword}%");
                });
                break;
            case 'user'://用户搜索
                $result = $result->where(function ($query) use ($keyword){
                    $query->where('user.username', 'like', "%{$keyword}%");
                });
                break;
            case 'shop'://经销商搜索
                $results = DB::table("shop")
                    ->where('shopName','like',"%{$keyword}%")
                    ->offset($per_page * ( $page - 1 ))
                    ->limit($per_page)
                    ->get()
                    ->toArray();
                return json_encode(['code'=>200,'data'=>$results]);
                break;
        }

        $result =$result->offset($per_page * ( $page - 1 ))
            ->limit($per_page)
            ->orderBy('publish_news.created_at','DESC')
            ->get()->toArray();

        foreach($result as $key => $val){
            //把图片信息写进一个单独的object里面

            if($val->image){

                $images = explode(",",$val->image);
                $thumbnails_img = explode(",",$val->thumbnails_img);
                $img_w_h = json_decode($val->img_w_h);
                if($val->news_type==2){//视频，只有一个
                    $result[$key]->video_path = $images[0];
                    $result[$key]->video_width = $img_w_h[0];
                    $result[$key]->video_height = $img_w_h[1];
                }else{
                    foreach($images as $k=>$v){
                        $result[$key]->imageList[$k]['path']=$v;
                        $result[$key]->imageList[$k]['thumbnails_img']=@$thumbnails_img[$k];
                        $result[$key]->imageList[$k]['width']=@$img_w_h[$k][0];
                        $result[$key]->imageList[$k]['height']=@$img_w_h[$k][1];
                    }
                }

            }

        }
        return json_encode(['code'=>200,'data'=>$result]);
    }

    //用户认证车辆
    public function user_authen_car(Request $request, CurlService $curlService, UploadFileService $uploadFileService){
        $token                                      = $request->input('token');
        $result_token       = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $data['user_id']                            = $result_token->id;
        $data['carId']                              = $request->input('carId');
        $data['brandId']                            = $request->input('brandId');
        $data['goodId']                             = $request->input('goodId');

        $carName                                    = $request->input('carName');
//        $data['driver_license']                     = $request->input('driver_license');
//        $data['car_image']                          = $request->input('car_image');
//        $data['authen_fail_msg']                    = $request->input('authen_fail_msg');
//        $data['authen_name']                        = $request->input('authen_name');
//        $data['authen_car_id']                      = $request->input('authen_car_id');
//        $data['address']                            = $request->input('address');
//        $data['use_type']                           = $request->input('use_type');
//        $data['brand_model']                        = $request->input('brand_model');
//        $data['identification_code']                = $request->input('identification_code');
//        $data['engine_number']                      = $request->input('engine_number');
//        $data['car_register']                       = $request->input('car_register');
//        $data['driver_license_date']                = $request->input('driver_license_date');
//        $data['now_use_authen']                     = $request->input('now_use_authen');

//        $authen_car_img                     = $request->file('authen_car_img');
        $driver_license_img                 = $request->file('driver_license_img');
        $driver_license_back_img            = $request->file('driver_license_back_img');

        if( $driver_license_img){
            $driver_license[]               = $uploadFileService->driver_license_upload($driver_license_img,'driver_license_img/'.date("Ymd").'/image');
            $driver_license[]               = $uploadFileService->driver_license_upload($driver_license_back_img,'driver_license_img/'.date("Ymd").'/image');
//            $result_authen_car                 = $uploadFileService->video_upload($authen_car_img,'authen_car_img');
            $data['driver_license']         = implode(',',$driver_license) ;
//            $data['car_image']              = implode(',',$result_authen_car[0]) ;
        }
        $brand_logo = DB::table("brand")->select(['brandLogo'])->where('brandId','=',$data['brandId'])->first();

        DB::table("user")->where('id','=',$result_token->id)->update(['authen_car_name'=>$carName,'authen_status'=>0,'authen_brand_log'=>$brand_logo->brandLogo]);
        DB::table("car_authen")->insert($data);
        return json_encode(['code'=>200,'message'=>'认证成功']);
    }

    //待认证列表
    public function check_authen_list(Request $request){
        $authen_list = DB::table("car_authen")
            ->leftJoin( 'user', 'car_authen.user_id', '=', 'user.id' )
            ->leftJoin( 'carList', 'car_authen.carId', '=', 'carList.carId' )
            ->select([
                'car_authen.id',
                'car_authen.driver_license',
                'car_authen.status',
                'user.username',
                'carList.goodsCarName',
            ])
//            ->where('status','=',0)
//            ->offset($per_page * ( $page - 1 ))
//            ->limit($per_page)
//            ->orderBy('publish_news.created_at','DESC')
            ->get()
            ->toArray();
        return json_encode(['code'=>200,'data'=>$authen_list]);
    }

    public function authen_result(Request $request){
        $id        = $request->input('id');
        $status    = $request->input('status');
        $message   = $request->input('message') ? $request->input('message') : '';

        DB::table("car_authen")->where('id','=',$id)->update(['status'=>$status,'authen_fail_msg'=>$message]);
        $result = DB::table("car_authen")->where('id','=',$id)->first();
        DB::table("user")->where('id','=',$result->id)->update(['status'=>$status]);

        return json_encode(['code'=>200]);
    }


    //关注-取关
    public function follow(Request $request, CurlService $curlService){
        $token                       = $request->input('token');
        $data['status']              = $request->input('status');
        $data['followed_user_id']    = $request->input('followed_user_id');//被关注者id
        $data['mutual']              = 0;//是否关注默认没互关

        $result_token                = $curlService->getToken($token);

        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        if($data['followed_user_id'] == $result_token->id){
            return json_encode(['code'=>401,'message'=>'不能自己关注自己！！！']);
        }

        //关注时查询对方是否关注自己，如果关注修改互关字段
        $mutual = DB::table('follow')
            ->where('user_id','=',$data['followed_user_id'])
            ->where('followed_user_id','=',$result_token->id)
            ->where('status','=',1)
            ->first();
        //如果对方有关注自己
        if($mutual){

            if($data['status'] == 1){//关注

                $data['mutual'] = 1;

                //关注时要把对方的那一条数据的互关字段也改成1
                $flag = 1;

            }else{//取关

                //如果是取关，要把对方的互关字段改成0
                $flag = 0;

            }
            DB::table("follow")
                ->where('user_id','=',$data['followed_user_id'])
                ->where('followed_user_id','=',$result_token->id)
                ->update(['mutual'=>$flag]);
        }

        $data['user_id'] = $result_token->id;
        //每次重新关注时把状态都设置成未读状态
        if($data['status'] == 1){
            $data['is_read'] = 0;
        }
        $result = DB::table('follow')->updateOrInsert([
            'followed_user_id'   =>  $data['followed_user_id'],
            'user_id'   =>  $data['user_id'],
        ],$data);

        //关注时推送通知，取关就不用了
        if($data['status'] == 1){
            $publish_send_push = new PublishNewsController;
            $publish_send_push->sendPush('follow',['id'=>$data['followed_user_id']]);
        }

        if(!$result){
            return json_encode(['code'=>401,'message'=>'失败']);
        }else{
            return json_encode(['code'=>200,'message'=>'成功']);
        }
    }

    //消息列表的关注
    public function message_follow_list(Request $request, CurlService $curlService){
        $token                       = $request->input('token');
        $per_page                    = (int)$request->input( 'per_page', 10 );
        $page                        = (int)$request->input( 'page', 1 );
        $result_token                = $curlService->getToken($token);

        if($token && !$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $select_userid = $result_token ? $result_token->id : 0;
        $result = DB::table('follow')
            ->select([
                'user.id as user_id',
                'user.username',
                'user.head_img',
                'user.authen_status',
                'user.authen_car_name',
                'user.authen_brand_log',
                'follow.id',
                'follow.is_read',
                'follow.is_hide',
                'follow.mutual as is_mutual',
            ])
            ->leftJoin( 'user', 'follow.user_id', '=', 'user.id' )
            ->where('follow.user_id','!=',$select_userid)
            ->where('follow.followed_user_id','=',$select_userid)
            ->where('follow.status','=',1)
            ->where('follow.is_hide','=',0)
            ->offset($per_page * ( $page - 1 ))
            ->limit($per_page)
            ->orderBy('follow.updated_at','DESC')
            ->get()
            ->toArray();
        $result = $this->login_user($result,$result_token->id);
        return json_encode(['code'=>200,'data'=>$result]);

    }
    //关注列表
    public function follow_list(Request $request, CurlService $curlService){
        $token                       = $request->input('token');
        $per_page                    = (int)$request->input( 'per_page', 10 );
        $page                        = (int)$request->input( 'page', 1 );
        $user_id                     = $request->input('user_id');//查看指定用户的关注列表
        $result_token                = $curlService->getToken($token);

        if($token && !$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $select_userid = $user_id ? $user_id : $result_token->id;
        $result = DB::table('follow')
            ->select([
                'user.id as user_id',
                'user.username',
                'user.head_img',
                'user.authen_status',
                'user.authen_car_name',
                'user.authen_brand_log',
                'follow.id',
                'follow.is_read',
                'follow.mutual as is_mutual',
            ])
            ->leftJoin( 'user', 'follow.followed_user_id', '=', 'user.id' )
            ->where('follow.user_id','=',$select_userid)
            ->where('follow.followed_user_id','!=',$select_userid)
            ->where('follow.status','=',1)
            ->offset($per_page * ( $page - 1 ))
            ->limit($per_page)
            ->orderBy('follow.created_at','DESC')
            ->get()
            ->toArray();

        $result = $this->login_user($result,$result_token->id);

        return json_encode(['code'=>200,'data'=>$result]);

    }
    //粉丝列表
    public function fans_list(Request $request, CurlService $curlService){
        $token                       = $request->input('token');
        $per_page                    = (int)$request->input( 'per_page', 10 );
        $page                        = (int)$request->input( 'page', 1 );
        $user_id                     = $request->input('user_id');//查看指定用户的关注列表
        $result_token                = $curlService->getToken($token);

        if($token && !$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $select_userid = $user_id ? $user_id : $result_token->id;
        $result = DB::table('follow')
            ->select([
                'user.id as user_id',
                'user.username',
                'user.head_img',
                'user.authen_status',
                'user.authen_car_name',
                'user.authen_brand_log',
                'follow.id',
                'follow.is_read',
                'follow.mutual as is_mutual',
            ])
            ->leftJoin( 'user', 'follow.user_id', '=', 'user.id' )
            ->where('follow.user_id','!=',$select_userid)
            ->where('follow.followed_user_id','=',$select_userid)
            ->where('follow.status','=',1)
            ->offset($per_page * ( $page - 1 ))
            ->limit($per_page)
            ->orderBy('follow.created_at','DESC')
            ->get()
            ->toArray();

        $result = $this->login_user($result,$result_token->id);


        return json_encode(['code'=>200,'data'=>$result]);
    }

    public function login_user($result,$login_user_id){
        //查询出当前登录账户所有的关注和粉丝
        $all_follow=[];
        $all_fans=[];
        $results = DB::table('follow')
            ->select([
                'follow.user_id',
                'follow.followed_user_id',
            ])
            ->where(function ($query) use ($login_user_id) {
                $query->where('follow.user_id','=',$login_user_id);
                $query->orWhere('follow.followed_user_id','=',$login_user_id);
            })
            ->where('follow.status','=',1)
            ->get()
            ->toArray();
        foreach($results as $key =>$val){
            $all_follow[]=$val->followed_user_id;
            $all_fans[]=$val->user_id;
        }

        foreach($result as $key =>$val){

            if(isset($val->user_id)){
                //判断-被查看用户的粉丝/关注列表里如果有当前登录用户关注的
                if(in_array($val->user_id,$all_follow)){
                    $result[$key]->is_follow = 1;
                }else{
                    $result[$key]->is_follow = 0;
                }

                //判断-被查看用户的粉丝/关注列表里如果有当前登录的粉丝
                if(in_array($val->user_id,$all_fans)){
                    $result[$key]->is_fans = 1;
                }else{
                    $result[$key]->is_fans = 0;
                }
            }else{
                //判断-被查看用户的粉丝/关注列表里如果有当前登录用户关注的
                if(in_array($val['user_id'],$all_follow)){
                    $result[$key]['is_follow'] = 1;
                }else{
                    $result[$key]['is_follow'] = 0;
                }

                //判断-被查看用户的粉丝/关注列表里如果有当前登录的粉丝
                if(in_array($val['user_id'],$all_fans)){
                    $result[$key]['is_fans'] = 1;
                }else{
                    $result[$key]['is_fans'] = 0;
                }
            }

        }

        return $result;

    }

    //在消息列表那里删除关注信息，修改库里字段
    public function delete_message_follow(Request $request, CurlService $curlService){
        $token                       = $request->input('token');
        $id                          = $request->input('id');
        $result_token                = $curlService->getToken($token);

        if($token && !$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        DB::table('follow')->where('id','=',$id)->update(['is_hide'=>1]);
        return json_encode(['code'=>200]);
    }
    //已读关注信息
    public function read_follow(Request $request, CurlService $curlService){
        $token                       = $request->input('token');
        $id                          = $request->input('id');
        $type                        = $request->input('type');
        $result_token                = $curlService->getToken($token);

        if($token && !$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        if($type=='all'){
            DB::table('follow')->where('followed_user_id','=',$result_token->id)->update(['is_read'=>1]);
        }else{
            DB::table('follow')->where('followed_user_id','=',$id)->update(['is_read'=>1]);
        }

        return json_encode(['code'=>200]);
    }
    //已读点赞信息
    public function read_like(Request $request, CurlService $curlService){
        $token                       = $request->input('token');
        $result_token                = $curlService->getToken($token);

        if($token && !$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        DB::table('like')->where('to_userid','=',$result_token->id)->update(['is_read'=>1]);


        return json_encode(['code'=>200]);
    }

    //用户关注的所有人发布的咨询列表
    public function user_follow_publish_list(Request $request, CurlService $curlService){
        $token                       = $request->input('token');
        $per_page                    = (int)$request->input( 'per_page', 10 );
        $page                        = (int)$request->input( 'page', 1 );
        $result_token                = $curlService->getToken($token);

        if( !$result_token ){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $result = DB::table('publish_news')
            ->select([
                'user.username',
                'user.head_img',
                'user.authen_status',
                'user.authen_car_name',
                'user.authen_brand_log',
                'publish_news.id',
                'publish_news.user_id',
                'publish_news.image',
                'publish_news.thumbnails_img',
                'publish_news.video_img_logo',
                'publish_news.content',
                'publish_news.news_type',
                'publish_news.img_w_h',
                'publish_news.title',
                'publish_news.created_at',
                'publish_news.pv',
                'publish_news.comment_reply_num',
                'publish_news.show',
                'publish_news.power',
                'publish_news.operate',
                'publish_news.config',
                'publish_news.comfort',
                'topic.topic',
                'carList.carName',
                'carList.goodsCarName',
            ])
            ->leftJoin( 'follow', 'publish_news.user_id', '=', 'follow.followed_user_id' )
            ->leftJoin('favorites',function($join){
                $join->on('publish_news.id','=','favorites.publish_id')->on('publish_news.user_id','=','favorites.user_id');
            })
            ->leftJoin( 'user', 'publish_news.user_id', '=', 'user.id' )
            ->leftJoin('brandGood','publish_news.model','=','brandGood.goodId')
            ->leftJoin('carList','publish_news.carId','=','carList.carId')
            ->leftJoin('topic','publish_news.topic','=','topic.id')
            ->where('follow.user_id','=',$result_token->id)
            ->offset($per_page * ( $page - 1 ))
            ->limit($per_page)
            ->orderBy('publish_news.created_at','DESC')
            ->get()->toArray();


        foreach($result as $key => $val){
            //把图片信息写进一个单独的object里面

            if($val->image){
                $images = explode(",",$val->image);
                $thumbnails_img = explode(",",$val->thumbnails_img);
                $img_w_h = json_decode($val->img_w_h);
                if($val->news_type==2){//视频，只有一个
                    $result[$key]->video_path = $images[0];
                    $result[$key]->video_width = $img_w_h[0];
                    $result[$key]->video_height = $img_w_h[1];
                }else{
                    foreach($images as $k=>$v){
                        $result[$key]->imageList[$k]['path']=$v;
                        $result[$key]->imageList[$k]['thumbnails_img']=@$thumbnails_img[$k];
                        $result[$key]->imageList[$k]['width']=@$img_w_h[$k][0];
                        $result[$key]->imageList[$k]['height']=@$img_w_h[$k][1];
                    }
                }
            }

        }
        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$result ],JSON_UNESCAPED_UNICODE );
    }

    public function new_version(){
        $results = DB::table('new_version')
            ->get()
            ->toArray();
        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$results ],JSON_UNESCAPED_UNICODE );

    }

}
