<?php

namespace App\Http\Controllers\MoTo;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Validator;
use App\Services\CurlService;
use App\Services\UploadFileService;
use App\Services\ImageService;

class PublishNewsController extends Controller
{
    protected $curl;
    protected $header;
    protected $username;
    protected $password;
    public function __construct()
    {
        $this->curl='https://api.jpush.cn/v3/push';
        $app_key='a9fb5de6649dcfd8854cb772:';
        $master_secret='2de56101722f02b5fddf414d';
        $this->header=['Authorization: Basic '.base64_encode($app_key.$master_secret),'Content-Type: application/json'];
        $this->username='729145859@qq.com';
        $this->password='GwS612106';
    }

    public function sendPush($type,$data){
        $flag = false;
        $content_type=1;
        if($type=='comment'){

            $result['data'] = $this->send_push_comment($data['id']);
            //自己给自己评论不推送通知
            if($result['data']->send_user_id && $result['data']->send_user_id!=$result['data']->user_id){
                $flag = true;
                $content_type = 5;
                $result['un_read_message'] = $this->user_message_count($result['data']->send_user_id);
            }

        }else if($type=='reply'){

            $result['data'] = $this->send_push_reply($data['id']);
            //自己给自己回复不推送通知
            if($result['data']->send_user_id && $result['data']->send_user_id!=$result['data']->user_id){
                $flag = true;
                $content_type = 5;
                $result['un_read_message'] = $this->user_message_count($result['data']->send_user_id);
            }

        }else if($type=='like'){

            $result['data'] = $this->send_push_like($data['id']);
            //自己给自己点赞不推送通知
            if($result['data']->send_user_id && $result['data']->send_user_id!=$result['data']->user_id){
                $flag = true;
                $content_type = 4;
                $result['un_read_message'] = $this->user_message_count($result['data']->send_user_id);
            }

        }else if ($type=='follow'){
            $flag = true;
            $content_type = 3;
            $result['un_read_message'] = $this->user_message_count($data['id']);
        }
        if($flag){
            $send_user_id[]=$content_type == 3 ? $data['id'] : @$result['data']->send_user_id;//通知接收者id
            //回复评论和回复回复的话要通知该作者和评论者
            if(isset($result['data']->comment_user_id) && $result['data']->comment_user_id!=$result['data']->user_id){
                $send_user_id[] = $result['data']->comment_user_id;
            }
            if(isset($result['data']->comment_user_id) && $result['data']->author_user_id!=$result['data']->user_id){
                $send_user_id[] = $result['data']->author_user_id;
            }

            $content = '这是内容';
//        $title = '这是标题';
            $send_jg_data['platform']='all';
            $send_jg_data['audience']['alias']=$send_user_id;
            $send_jg_data['message']['msg_content']=$content;//手机端不显示出来
//        $send_jg_data['message']['title']=$title;
            $send_jg_data['message']['content_type']=$content_type;//string消息内容类型
            $send_jg_data['message']['extras']=json_encode($result);//JSON Object 格式的可选参数自定义
            $log_path=storage_path("logs/senPush.log");

            $param['username'] = $this->username.':'.$this->password;

            $curl = new CurlService();
            $inter_result = json_decode($curl->send($this->curl,'POST',$this->header,$send_jg_data,$param),true);
            file_put_contents($log_path,date("Y-m-d H:i:s")."\n".json_encode($inter_result,256).PHP_EOL,FILE_APPEND);

        }

        if(isset($inter_result[0]['error'])){
            return json_encode(['code'=>@$inter_result[0]['code']['message'],'message'=>@$inter_result[0]['error']['message']]);
        }
    }

    public function send_push_like($data){
        $like = DB::table('like')
            ->select([
                'like.type',
                'like.type_id',
                'like.to_userid as send_user_id',
                'user.id as user_id',
                'user.username',
                'user.head_img',
            ])
            ->leftJoin('user','like.from_userid','=','user.id')
            ->where('like.id','=',$data)
            ->first();
        return $like;
    }

    public function send_push_comment($data){
        //当前用户被评论信息
        $comment_sql = DB::table('comment')
            ->select([
                'comment.type',
                'comment.id as comment_id',
                'comment.is_read',
                'comment.is_hide',
                'comment.publish_type',
                'comment.publish_id',
                'comment.content',
                'comment.created_at',
                'publish_news.user_id as send_user_id',
                'user.id as user_id',
                'user.username',
                'user.head_img',
            ])
            ->leftJoin('user','comment.from_userid','=','user.id')
            ->leftJoin('publish_news','comment.publish_id','=','publish_news.id')
            ->where('comment.id','=',$data)
            ->first();
        return $comment_sql;
    }
    public function send_push_reply($data){
        $result = DB::table('reply')
            ->select([
                'reply.to_username',
                'reply.to_userid as send_user_id',
                'reply.type',
                'reply.comment_id',
                'reply.is_read',
                'reply.is_hide',
                'reply.publish_type',
                'reply.publish_id',
                'reply.content',
                'reply.created_at',
                'comment.from_userid as comment_user_id',
                'publish_news.user_id as author_user_id',
                'user.id as user_id',
                'user.username',
                'user.head_img',
            ])
            ->leftJoin('user','reply.from_userid','=','user.id')
            ->leftJoin('publish_news','reply.publish_id','=','publish_news.id')
            ->leftJoin('comment','reply.comment_id','=','comment.id')
            ->where('reply.id','=',$data)
            ->first();
        return $result;
    }

    public function index( Request $request, CurlService $curlService, UploadFileService $uploadFileService ){
        $token              = $request->input('token');
        $data['news_type']  = $request->input('news_type');
        $result_token       = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $images             = $request->file('image');
        if($images){
            //缩略图
            $img = new ImageService();

            $quality = 50;
            if($data['news_type']==2){
                $video_img_logo              = $request->file('video_img_logo');
                $result_video_img_logo       = $uploadFileService->video_upload($video_img_logo,'video_img_logo/'.date("Ymd").'/image');
                $result_video                = $uploadFileService->video_upload($images,'video/'.date("Ymd"));
                $data['image']               = $result_video[0][0];
                $data['video_img_logo']      = implode(',',$result_video_img_logo[0]);
                $width_height[]              = $request->input('width');
                $width_height[]              = $request->input('height');
                $data['img_w_h']    = json_encode($width_height);

                $width  = intval($result_video_img_logo[1][0][0]/2);
                $height = intval($result_video_img_logo[1][0][1]/2);

                $newFileName = md5( time(). mt_rand(1, 10000) ).'.'.substr($data['video_img_logo'],strripos($data['video_img_logo'],".")+1);

                $video_thumbnails = '/storage/images/video_img_logo/'.date("Ymd").'/thumbnails/';
                if (!file_exists(public_path().$video_thumbnails)) {
                    mkdir(public_path().$video_thumbnails,0777,true);
                }

                //调用java接口压缩图片
                $result = json_decode($curlService->send_img('http://motocircle.cn:8899/image/imgThumb','POST',[],['desPath'=>public_path().'/storage/images/video_img_logo/'.date("Ymd").'/thumbnails/'. $newFileName,'srcPath'=>public_path().$data['video_img_logo'],'height'=>$height,'width'=>$width]),true);
                if(file_exists(public_path().$data['video_img_logo'])){
                    unlink(public_path().$data['video_img_logo']);//压缩完删除原图
                }

//                $img->load( public_path().$data['video_img_logo'] )
//                    ->size( $width, $height )//设置生成图片的宽度和高度
//                    ->fixed_given_size( true )//生成的图片是否以给定的宽度和高度为准
//                    ->keep_ratio( true )//是否保持原图片的原比例
//                    ->rotate( 0 )//指定旋转的角度
////                    ->bg_color( "#ffffff" )//设置背景颜色，按照rgb格式
////                    ->quality( $quality )//设置生成图片的质量 0-100，如果生成的图片格式为png格式，数字越大，压缩越大，如果是其他格式，如jpg，gif，数组越小，压缩越大
//                    ->save( public_path().'/storage/images/video_img_logo/'.date("Ymd").'/thumbnails/'. $newFileName);    //保存生成图片的路径

                $thumbnails_img[]='/storage/images/video_img_logo/'.date("Ymd").'/thumbnails/'. $newFileName;
                $data['thumbnails_img']      = implode(',',$thumbnails_img) ;
            }else{
                $result_image       = $uploadFileService->img_upload($images,'publishNews/'.date("Ymd").'/image/');
                $data['image']      = implode(',',$result_image[0]) ;
                $data['img_w_h']    = json_encode($result_image[1]);

                $size    = count($result_image[0]) > 1 ? 500 : 3 ;//如果是一张就 / 3， 多张宽高固定300
                $thumbnails_url = '/storage/images/publishNews/'.date("Ymd").'/thumbnails/';
                if (!file_exists(public_path().$thumbnails_url)) {
                    mkdir(public_path().$thumbnails_url,0777,true);
                }
                foreach($result_image[0] as $key=>$val){
                    if($result_image[1][$key][0]>500 || $result_image[1][$key][1]>500){
                        $width  = $size == 500 ? $size : intval($result_image[1][$key][0]/$size);
                        $height = $size == 500 ? $size : intval($result_image[1][$key][1]/$size);
                    }else{
                        $width  = intval($result_image[1][$key][0]);
                        $height = intval($result_image[1][$key][1]);
                    }


                    $newFileName = md5( time(). mt_rand(1, 10000) ).'.'.substr($val,strripos($val,".")+1);
                    //调用java接口压缩图片
                    $result = json_decode($curlService->send_img('http://motocircle.cn:8899/image/imgThumb','POST',[],['desPath'=>public_path().$thumbnails_url. $newFileName,'srcPath'=>public_path().$val,'height'=>$height,'width'=>$width]),true);

//                    $img->load( public_path().$val )
//                        ->size( $width, $height )//设置生成图片的宽度和高度
//                        ->fixed_given_size( true )//生成的图片是否以给定的宽度和高度为准
//                        ->keep_ratio( true )//是否保持原图片的原比例
//                        ->rotate( 0 )//指定旋转的角度
////                    ->bg_color( "#ffffff" )//设置背景颜色，按照rgb格式
////                        ->quality( $quality )//设置生成图片的质量 0-100，如果生成的图片格式为png格式，数字越大，压缩越大，如果是其他格式，如jpg，gif，数组越小，压缩越大
//                        ->save( public_path().$thumbnails_url. $newFileName);    //保存生成图片的路径

                    $thumbnails_img[]=$thumbnails_url. $newFileName;
                }
                $data['thumbnails_img']      = implode(',',$thumbnails_img) ;
            }
        }

//        file_put_contents( storage_path( "logs/upload_img.log" ), json_encode($result_image) . PHP_EOL, FILE_APPEND );

        $data['user_id']    = $request->input('user_id');
        $data['address']    = $request->input('address');
        $data['model']      = $request->input('model')  ? $request->input('model') : 0;

        //0是发布动态。1是发布文章,2:视频,3:口碑,4:问答
        switch($data['news_type']){
            case 1:
                $data['title']      = urldecode(urldecode($request->input('title')));
                $data['content']    = $request->input('content');

                break;
            case 3:
                $validator = \Validator::make($request->all(), [
                    'model'       =>  'required',
                    'carId'       =>  'required',
                    'show'        =>  'required',
                    'power'       =>  'required',
                    'operate'     =>  'required',
                    'config'      =>  'required',
                    'comfort'     =>  'required',
                ]);

                if ( $validator->fails() ) {
                    $message = array_values($validator->errors()->get('*'))[0][0];
                    return json_encode( [ 'message' => $message,'code'=>'401' ],JSON_UNESCAPED_UNICODE );
                }
                $data['model']      = $request->input('model');
                $data['carId']      = $request->input('carId');
                $data['show']       = $request->input('show');
                $data['power']      = $request->input('power');
                $data['operate']    = $request->input('operate');
                $data['config']     = $request->input('config');
                $data['comfort']    = $request->input('comfort');
                $data['content']    = urldecode(urldecode($request->input('content')));

                //取出车型表里面车型的平均评分
                $pre_score = DB::table('brandGood')->select([
                    "score",
                    "show_score",
                    "power_score",
                    "operate_score",
                    "config_score",
                    "comfort_score",])->where("goodId","=",$data['model'])->get()->toArray();

                //如果数据库平均分为0，不除2。
                $update['show_score']       = $pre_score[0]->show_score==0       ? $data['show']        : ($pre_score[0]->show_score+$data['show'])/2;
                $update['power_score']      = $pre_score[0]->power_score==0      ? $data['power']       : ($pre_score[0]->power_score+$data['power'])/2;
                $update['operate_score']    = $pre_score[0]->operate_score==0    ? $data['operate']     : ($pre_score[0]->operate_score+$data['operate'])/2;
                $update['config_score']     = $pre_score[0]->config_score==0     ? $data['config']      : ($pre_score[0]->config_score+$data['config'])/2;
                $update['comfort_score']    = $pre_score[0]->comfort_score==0    ? $data['comfort']     : ($pre_score[0]->comfort_score+$data['comfort'])/2;

                $update['score'] = round( ($update['show_score']+$update['power_score']+$update['operate_score']+$update['config_score']+$update['comfort_score'] )/5,1);


                DB::table('brandGood')->where("goodId","=",$data['model'])->update($update);
                break;
            default:
                $data['content']      = urldecode(urldecode($request->input('content')));
                $data['topic']      = $request->input('topic');
                break;
        }


        $result = DB::table('publish_news')->insert($data);

        if(!$result){
            return json_encode(['code'=>401,'message'=>'发布失败']);
        }else{
            return json_encode(['code'=>200,'message'=>'发布成功']);
        }
    }

    //获取所有咨询信息
    public function getCommentInfo( Request $request , CurlService $curlService){
        $token              = $request->input('token');
        if($token){
            $result_token       = $curlService->getToken($token);
            if(!$result_token){
                return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
            }
        }
        $per_page           = (int)$request->input( 'per_page', 10 );
        $page               = (int)$request->input( 'page', 1 );

        $result = $this->getData($per_page,$page,null,null);

        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$result ],JSON_UNESCAPED_UNICODE );
    }

    //用户的动态文章详情
    public function UserPublishNews( Request $request, CurlService $curlService){
        $token              = $request->input('token');
        $user_id            = $request->input('user_id');
        $news_type          = $request->input('news_type');
        $per_page           = (int)$request->input( 'per_page', 10 );
        $page               = (int)$request->input( 'page', 1 );
        if($token){
            $result_token       = $curlService->getToken($token);
            if(!$result_token){
                return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
            }
        }
        $result = $this->getData($per_page,$page,$user_id,$news_type);
        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$result ],JSON_UNESCAPED_UNICODE );
    }

    public function getData($per_page,$page,$user_id=null,$news_type=null){
        $where[] = $user_id   ? ['publish_news.user_id','=',$user_id] : ['publish_news.id','>',0];
        $where[] = isset($news_type) ? ['publish_news.news_type','=',$news_type] : ['publish_news.id','>',0];
        $where[] = ['publish_news.is_hide','=',0];
//        $where[] = ['favorites.status','=',1] ;
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
            ->leftJoin('topic','publish_news.topic','=','topic.id')
            ->where($where)
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
        return $result;
    }
    //单篇文章/动态详情
    public function PublishNewsDetail(Request $request, CurlService $curlService){
        $publish_id         = $request->input('publish_id');
        $token              = $request->input('token');

        //调取接口增加浏览量
        $rand = rand(1,5);
        DB::table('publish_news')->where(['id'=>$publish_id])->increment('pv',$rand);

        $like_ids=[];
        $follow_ids=[];
        $mutual_ids=[];
        if($token){
            $result_token       = $curlService->getToken($token);
            if(!$result_token){
                return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
            }
            //是否收藏
            $current_uid        = isset($result_token->id) ? $result_token->id : null;
            //是否点赞
            $result_like = DB::table('like')
                ->where('from_userid','=',$current_uid)
                ->where('type','=',1)
                ->where('status','=',1)
                ->get()
                ->toArray();
            foreach($result_like as $key=>$val){
                $like_ids[]=$val->type_id;
            }
            //是否关注
            $result_follow = DB::table('follow')
                ->where('user_id','=',$current_uid)
                ->where('status','=',1)
                ->get()
                ->toArray();
            foreach($result_follow as $key=>$val){
                $follow_ids[$key]=$val->followed_user_id;
                if($val->mutual==1){
                    $mutual_ids[] = $val->followed_user_id;
                }
            }

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
                'publish_news.content',
                'publish_news.news_type',
                'publish_news.img_w_h',
                'publish_news.title',
                'publish_news.address',
                'publish_news.like_count',
                'publish_news.created_at',
                'publish_news.pv',
                'publish_news.comment_reply_num',
                'brandGood.goodId',
                'brandGood.brandName',
                'brandGood.brandId',
                'brandGood.saleStatus',
                'brandGood.goodName',
                'brandGood.maxPrice',
                'brandGood.minPrice',
                'brandGood.goodLogo',
                'brandGood.carDetailLogo',
                'brandGood.goodType',
                'brandGood.goodVolume',
                'brandGood.goodCylinder',
                'brandGood.goodAbs',
                'topic.topic',
                'favorites.user_id as current_uid',
                'favorites.status',
            ])
            ->leftJoin('favorites',function($join){
                $join->on('publish_news.id','=','favorites.publish_id')->on('publish_news.user_id','=','favorites.user_id');
            })
            ->leftJoin( 'user', 'publish_news.user_id', '=', 'user.id' )
            ->leftJoin('brandGood','publish_news.model','=','brandGood.goodId')
            ->leftJoin('topic','publish_news.topic','=','topic.id')
            ->where(['publish_news.id'=>$publish_id])
            ->orderBy('publish_news.created_at','DESC')
            ->get()->toArray();

        if(empty($result)){
            return json_encode( [ 'message' => 'no data','code'=>'203' ],JSON_UNESCAPED_UNICODE );
        }
        $results = $result[0];
        foreach($results as $key => $val){
            $results->goodId = isset($results->goodId) && $results->goodId ? $results->goodId : 0;
            //把图片信息写进一个单独的object里面

                if($results->image){
                    $images = explode(",",$results->image);
                    $thumbnails_img = explode(",",$results->thumbnails_img);
                    $img_w_h = json_decode($results->img_w_h);
                    if($results->news_type==2){//视频，只有一个
                        $results->video_path = $images[0];
                        $results->video_thumbnails_img = @$thumbnails_img[0];
                        $results->video_width = $img_w_h[0];
                        $results->video_height = $img_w_h[1];
                    }else{
                        foreach($images as $k=>$v){
                            $results->imageList[$k]['path']=$v;
                            $results->imageList[$k]['thumbnails_img']=@$thumbnails_img[$k];
                            $results->imageList[$k]['width']=@$img_w_h[$k][0];
                            $results->imageList[$k]['height']=@$img_w_h[$k][1];
                        }
                    }
                }

            //把车辆信息写进一个单独的object里面
            if($results->goodId) {
                $results->carInfo['goodId']    = $results->goodId;
                $results->carInfo['brandName'] = $results->brandName;
                $results->carInfo['brandId'] = $results->brandId;
                $results->carInfo['goodName']  = $results->goodName;
                $results->carInfo['maxPrice']  = $results->maxPrice;
                $results->carInfo['minPrice']  = $results->minPrice;
                $results->carInfo['goodLogo']  = $results->goodLogo;
                $results->carInfo['carDetailLogo']  = $results->carDetailLogo;
                $results->carInfo['goodType']  = $results->goodType;
                $results->carInfo['goodVolume']  = $results->goodVolume;
                $results->carInfo['goodCylinder']  = $results->goodCylinder;
                $results->carInfo['goodAbs']  = $results->goodAbs;
                $results->carInfo['saleStatus']  = $results->saleStatus;
            }else{
                unset($results->goodId);
                unset($results->brandName);
                unset($results->brandId);
                unset($results->goodName);
                unset($results->minPrice);
                unset($results->minPrice);
                unset($results->goodLogo);
                unset($results->carDetailLogo);
                unset($results->goodType);
                unset($results->goodVolume);
                unset($results->goodCylinder);
                unset($results->goodAbs);
                unset($results->saleStatus);
            }

            //判断当前用户是否收藏文章
            $results->is_favorites = $results->current_uid==@$current_uid&&$results->status==1 ? 1 : 0;
            //判断当前用户是否点赞文章
            $results->is_like = in_array($results->id,$like_ids)  ? 1 : 0;
            //判断当前用户是否关注文章作者
            $results->is_follow = in_array($results->user_id,$follow_ids)  ? 1 : 0;
            //判断当前用户和文章作者是否是互关状态
            $results->is_mutual = in_array($results->user_id,$mutual_ids) ? 1 : 0;
        }

        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$results ],JSON_UNESCAPED_UNICODE );
    }

    public function favoritesNewsList(Request $request, CurlService $curlService){
        $token              = $request->input('token');
        $user_id            = $request->input('user_id');
        $per_page           = (int)$request->input( 'per_page', 10 );
        $page               = (int)$request->input( 'page', 1 );
        $result_token       = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $result = DB::table('favorites')->select([
            'publish_news.user_id',
            'publish_news.image',
            'publish_news.content',
            'publish_news.news_type',
            'publish_news.img_w_h',
            'publish_news.title',
            'publish_news.address',
            'publish_news.pv',
            'publish_news.created_at',
            'brandGood.goodId',
            'brandGood.brandName',
            'brandGood.goodName',
            'brandGood.maxPrice',
            'brandGood.minPrice',
            'brandGood.goodLogo',
            'topic.topic',
        ])
            ->leftJoin('publish_news','favorites.publish_id','=','publish_news.id')
            ->leftJoin('brandGood','publish_news.model','=','brandGood.goodId')
            ->leftJoin('topic','publish_news.topic','=','topic.id')
            ->where(['favorites.user_id'=>$user_id])
            ->where(['favorites.status'=>1])
            ->offset($per_page * ( $page - 1 ))
            ->limit($per_page)
            ->get()
            ->toArray();
        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$result ],JSON_UNESCAPED_UNICODE );

    }

    //用户收藏/取消收藏
    public function setUserFavorites(Request $request, CurlService $curlService){
        $token              = $request->input('token');
        $publish_id         = $request->input('publish_id');
        $user_id            = $request->input('user_id');
        $status             = $request->input('status');
        $result_token       = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }

        DB::table('favorites')
            ->updateOrInsert(['publish_id'=>$publish_id,'user_id'=>$user_id],['status'=>$status]);
        return json_encode( [ 'message' => 'success','code'=>'200' ],JSON_UNESCAPED_UNICODE );
    }

    //评论
    public function comment(Request $request, CurlService $curlService){
        $token                      = $request->input('token');
        $data['publish_id']         = $request->input('publish_id');
        $data['publish_type']       = $request->input('publish_type');
        $data['from_userid']        = $request->input('from_userid');
        $data['content']            = urldecode(urldecode($request->input('content')));

        $data['reply_count']        = 0;
        $result_token               = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $result = DB::table('comment')->insertGetId($data);
        if($result){
            DB::table('publish_news')->where(['id'=>$data['publish_id']])->increment('comment_reply_num');
        }

        $user_id = DB::table('publish_news')->select(['user_id'])->where(['id'=>$data['publish_id']])->get()->toArray();

        if($user_id[0]->user_id == $data['from_userid']){
            $is_author = 'yes';
        }else{
            $is_author = 'no';
        }
        $this->sendPush('comment',['id'=>$result]);
        return json_encode( [ 'message' => 'success','code'=>'200','id'=>$result,'is_author'=>$is_author ],JSON_UNESCAPED_UNICODE );
    }

    //回复
    public function reply(Request $request, CurlService $curlService){
        $token                      = $request->input('token');
        $data['publish_id']         = $request->input('publish_id');//文章id
        $data['author_user_id']     = $request->input('author_user_id');//文章作者id
        $data['comment_id']         = $request->input('comment_id');//评论id
        $data['reply_id']           = $request->input('reply_id',0);//回复目标的id
        $data['type']               = $data['reply_id'] ? 2 : 1;//回复type=1回复评论。=2回复回复
        $data['publish_type']       = $request->input('publish_type');//回复类型，0=对动态进行回复；1=对文章进行回复，2=问答，3=口碑
        $data['content']            = urldecode(urldecode($request->input('content')));//回复内容


        $data['from_userid']        = $request->input('from_userid');//回复者id
        $data['to_userid']          = $request->input('to_userid');//被回复用户id
        $data['to_username']          = $request->input('to_username');//被回复用户name
        $result_token               = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $result = DB::table('reply')->insertGetId($data);
        if($result){
            DB::table('comment')->where(['id'=>$data['comment_id']])->increment('reply_count');
            DB::table('publish_news')->where(['id'=>$data['publish_id']])->increment('comment_reply_num');
        }

        $user_id = DB::table('publish_news')->select(['user_id'])->where(['id'=>$data['publish_id']])->get()->toArray();

        if($user_id[0]->user_id == $data['from_userid']){
            $is_author = 'yes';
        }else{
            $is_author = 'no';
        }
        $this->sendPush('reply',['id'=>$result]);
        return json_encode( [ 'message' => 'success','code'=>'200','id'=>$result,'is_author'=>$is_author ],JSON_UNESCAPED_UNICODE );
    }
    //评论列表
    public function comment_list(Request $request, CurlService $curlService){
        $publish_id                 = $request->input('publish_id');//文章id
        $comment_id                 = $request->input('comment_id',0);//评论id 从消息列表点击评论过来的需要传
        $reply['current_reply_id']  = $request->input('current_reply_id');//回复id
        $reply['pre_reply_id']      = $request->input('pre_reply_id');//被回复id
        $select_total               = (int)$request->input( 'select_total', 5 );
        $start_total                = (int)$request->input( 'start_total', 0 );
        $token                      = $request->input('token');
        //当前登录用户在评论里面所有的赞
        $like_ids=[];
        $reply_like_ids=[];
        if($token){
            $result_token       = $curlService->getToken($token);
            if(!$result_token){
                return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
            }
            $current_uid        = isset($result_token->id) ? $result_token->id : null;
            //评论是否点赞
            $result_like = DB::table('like')
                ->select()
                ->where('from_userid','=',$current_uid)
                ->where('type','=',2)
                ->where('status','=',1)
                ->get()
                ->toArray();
            foreach($result_like as $key=>$val){
                $like_ids[]=$val->type_id;
            }
            //回复是否点赞
            $result_like_reply = DB::table('like')
                ->select()
                ->where('from_userid','=',$current_uid)
                ->where('type','=',3)
                ->where('status','=',1)
                ->get()
                ->toArray();
            foreach($result_like_reply as $key=>$val){
                $reply_like_ids[]=$val->type_id;
            }
        }

        //发布咨询的作者id
        $result_author_id = DB::table('publish_news')
            ->select(['user_id'])
            ->where('id','=',$publish_id)
            ->first();

        $result = DB::table('comment')
            ->select([
                'comment.id',
                'comment.content',
                'comment.reply_count',
                'comment.like_count',
                'comment.created_at',
                'user.id as user_id',
                'user.username',
                'user.head_img',
                'user.authen_status',
                'user.authen_car_name',
                'user.authen_brand_log',
            ])
            ->leftJoin('user','comment.from_userid','=','user.id')
            ->where(['publish_id'=>$publish_id])
            ->where(['comment.is_hide'=>0])
            ->orderByRaw(DB::raw("FIELD(comment.id,".$comment_id.") desc"))//如果传comment_id了，就根据comment_id，排序，把传过来的这个排到第一个
            ->orderBy('created_at','desc')
            ->offset($start_total)
            ->limit($select_total)
            ->get()
            ->toArray();
        foreach($result as $key=>$val){
            if(in_array($val->id,$like_ids)){
                $result[$key]->is_like = 1;
            }else{
                $result[$key]->is_like = 0;
            }
            //判断评论是否是作者评论
            if($val->user_id == $result_author_id->user_id){
                $result[$key]->is_author = 1;
            }else{
                $result[$key]->is_author = 0;
            }
            //如果评论下面有回复就查询出3条回复返回
            if($val->reply_count){
                $result_reply = DB::table('reply')
                    ->select([
                        'reply.id',
                        'reply.content',
                        'reply.comment_id',
                        'reply.to_username',
                        'reply.to_userid',
                        'reply.reply_id',
                        'reply.from_userid',
                        'reply.like_count',
                        'reply.created_at',
                        'user.id as user_id',
                        'user.username',
                        'user.head_img',
                        'user.authen_status',
                        'user.authen_car_name',
                        'user.authen_brand_log',
                        ])
                    ->leftJoin('user','reply.from_userid','=','user.id')
                    ->where('comment_id','=',$val->id)
                    ->where('reply.is_hide','=',0)
                    ->where(function ($query) use ($reply) {
                        if($reply['current_reply_id']){
                            $query->where('reply.id','=',$reply['current_reply_id']);
                        }
                        if($reply['pre_reply_id']){
                            $query->orWhere('reply.id','=',$reply['pre_reply_id']);
                        }
                    })
                    ->orderBy('reply.created_at','asc')
                    ->offset(0)
                    ->limit(3)
                    ->get()
                    ->toArray();
                foreach($result_reply as $k=>$v){
                    //判断回复是否是作者评论
                    if($v->from_userid == $result_author_id->user_id){
                        $result_reply[$k]->is_author = 1;
                    }else{
                        $result_reply[$k]->is_author = 0;
                    }
                    //判断是否点赞
                    if(in_array($v->id,$reply_like_ids)){
                        $result_reply[$k]->is_like = 1;
                    }else{
                        $result_reply[$k]->is_like = 0;
                    }
                }
                $result[$key]->reply = $result_reply;

                //用户在消息列表那里点击进入文章详情
                if($comment_id && $comment_id==$val->id){
                    //把点击进来的评论放到数组第一个
                    $first_array = $result[$key];
                    unset($result[$key]);
                    array_unshift($result,$first_array);
//                    //如果是点击回复评论的信息进来的就给加上当前回复信息
//                    if($reply['current_reply_id']){
//                        $result[0]->current_reply_info = @$result_reply[0];
//                    }
//                    //如果是点击回复 回复的信息进来的就给加上被回复的那条信息
//                    if($reply['pre_reply_id']){
//                        $result[0]->pre_reply_info = @$result_reply[1];
//                    }
                }
            }
        }

        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$result, 'total'=>count($result) ],JSON_UNESCAPED_UNICODE );
    }
    //回复列表
    public function reply_list(Request $request, CurlService $curlService){
        $comment_id = $request->input('comment_id');//评论id
        $publish_id = $request->input('publish_id');//咨询id
        $reply_id   = $request->input('reply_id')?explode(",",$request->input('reply_id')):[0];//回复id

        $select_total     = (int)$request->input( 'select_total', 5 );
        $start_total      = (int)$request->input( 'start_total', 0 );

        $token      = $request->input('token');
        //当前登录用户在回复里面所有的赞
        $like_ids=[];
        if($token){
            $result_token       = $curlService->getToken($token);
            if(!$result_token){
                return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
            }
            $current_uid        = isset($result_token->id) ? $result_token->id : null;
            //是否点赞
            $result_like = DB::table('like')
                ->select()
                ->where('from_userid','=',$current_uid)
                ->where('type','=',3)
                ->where('status','=',1)
                ->get()
                ->toArray();
            foreach($result_like as $key=>$val){
                $like_ids[]=$val->type_id;
            }

        }
        //发布咨询的作者id
        $result_author_id = DB::table('publish_news')
            ->select(['user_id'])
            ->where('id','=',$publish_id)
            ->first();

        $result = DB::table('reply')
            ->select([
                'reply.id',
                'reply.content',
                'reply.like_count',
                'reply.reply_id',
                'reply.publish_type',
                'reply.to_username',
                'reply.to_userid',
                'reply.from_userid',
                'reply.created_at',
                'user.id as user_id',
                'user.username',
                'user.head_img',
                'user.authen_status',
                'user.authen_car_name',
                'user.authen_brand_log',
            ])
            ->leftJoin('user','reply.from_userid','=','user.id')
            ->where('comment_id','=',$comment_id)
            ->where(['reply.is_hide'=>0])
            ->whereNotIn('reply.id',$reply_id)
            ->orderBy('created_at','asc')
            ->offset($start_total)
            ->limit($select_total)
            ->get()
            ->toArray();
        foreach($result as $key=>$val){
            if(in_array($val->id,$like_ids)){
                $result[$key]->is_like = 1;
            }else{
                $result[$key]->is_like = 0;
            }
            //判断回复是否是作者回复
            if($val->from_userid == $result_author_id->user_id){
                $result[$key]->is_author = 1;
            }else{
                $result[$key]->is_author = 0;
            }
        }
        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$result ],JSON_UNESCAPED_UNICODE );
    }
    //用户在消息列表那里点击进入文章详情
//    public function message_in_comment_list(Request $request){
//        $publish_id = $request->input('publish_id');//文章id
//        $comment_id = $request->input('comment_id');//评论id
//        $reply['current_reply_id'] = $request->input('current_reply_id');//回复id
//        $reply['pre_reply_id'] = $request->input('pre_reply_id');//被回复id
//        $per_page   = (int)$request->input( 'per_page', 10 );
//        $page       = (int)$request->input( 'page', 1 );
//        $result = DB::table('comment')
//            ->select([
//                'comment.id',
//                'comment.content',
//                'comment.reply_count',
//                'comment.created_at',
//                'user.id as user_id',
//                'user.username',
//                'user.head_img',
//            ])
//            ->leftJoin('user','comment.from_userid','=','user.id')
//            ->where(['comment.publish_id'=>$publish_id])
//            ->where(['comment.is_hide'=>0])
//            ->orderByRaw(DB::raw("FIELD(comment.id,".$comment_id.") desc"))
//            ->offset($per_page * ( $page - 1 ))
//            ->limit($per_page)
//            ->get()
//            ->toArray();
//
//        $reply_result = DB::table('reply')
//            ->select([
//                'reply.id',
//                'reply.content',
//                'reply.reply_id',
//                'reply.publish_type',
//                'reply.to_username',
//                'reply.to_userid',
//                'reply.created_at',
//                'user.id as user_id',
//                'user.username',
//                'user.head_img',
//            ])
//            ->leftJoin('user','reply.from_userid','=','user.id')
//            ->where(['reply.is_hide'=>0])
//            ->where(function ($query) use ($reply) {
//                if($reply['current_reply_id']){
//                    $query->where('reply.id','=',$reply['current_reply_id']);
//                }
//                if($reply['pre_reply_id']){
//                    $query->orWhere('reply.id','=',$reply['pre_reply_id']);
//                }
//            })
//            ->orderBy('id','desc')
//            ->offset($per_page * ( $page - 1 ))
//            ->limit($per_page)
//            ->get()
//            ->toArray();
//
//        if($comment_id){
//            foreach($result as $key=>$val){
//
//                if($comment_id==$val->id){
//                    //把点击进来的评论放到数组第一个
//                    $first_array = $result[$key];
//                    unset($result[$key]);
//                    array_unshift($result,$first_array);
//                    //如果是点击回复评论的信息进来的就给加上当前回复信息
//                    if($reply['current_reply_id']){
//                        $result[0]->current_reply_info = @$reply_result[0];
//                    }
//                    //如果是点击回复 回复的信息进来的就给加上被回复的那条信息
//                    if($reply['pre_reply_id']){
//                        $result[0]->pre_reply_info = @$reply_result[1];
//                    }
//                }
//
//            }
//        }
//
//
//        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$result, 'total'=>count($result) ],JSON_UNESCAPED_UNICODE );
//    }
    //查询用户被评论被回复列表
    public function user_comment_list(Request $request){
        $user_id = $request->input('user_id');//用户id
        $per_page   = (int)$request->input( 'per_page', 10 );
        $page       = (int)$request->input( 'page', 1 );

        //是否关注
        $follow_ids=[];
        $mutual_ids=[];
        $result_follow = DB::table('follow')
            ->where('user_id','=',$user_id)
            ->where('status','=',1)
            ->get()
            ->toArray();
        foreach($result_follow as $key=>$val){
            $follow_ids[$key]=$val->followed_user_id;
            if($val->mutual==1){
                $mutual_ids[] = $val->followed_user_id;
            }
        }

        //当前用户被评论信息
        $comment_sql = DB::table('comment')
            ->select([
                'reply.id as current_reply_id',
                'reply.reply_id as pre_reply_id',
                'reply.to_username',
                'reply.author_user_id',
                'comment.type',
                'comment.id as comment_id',
                'comment.is_read',
                'comment.is_hide',
                'comment.publish_type',
                'comment.publish_id',
                'comment.content',
                'comment.created_at',
                'user.id as user_id',
                'user.username',
                'user.head_img',
                'user.authen_status',
                'user.authen_car_name',
                'user.authen_brand_log',
            ])
            ->leftJoin('user','comment.from_userid','=','user.id')
            ->leftJoin('reply',function($join){
                $join->on('reply.from_userid','=','user.id')->on('reply.comment_id','=','comment.id');
            })
            ->leftJoin('publish_news','comment.publish_id','=','publish_news.id')
            ->where(function ($query) use ($user_id) {
                    $query->where('publish_news.user_id','=',$user_id);
                    $query->where('comment.from_userid','!=',$user_id);
//                    $query->where('reply.reply_id','=',0);
            });


            $result = DB::table('reply')
                ->select([
                    'reply.id as current_reply_id',
                    'reply.reply_id as pre_reply_id',
                    'reply.to_username',
                    'reply.author_user_id',
                    'reply.type',
                    'reply.comment_id',
                    'reply.is_read',
                    'reply.is_hide',
                    'reply.publish_type',
                    'reply.publish_id',
                    'reply.content',
                    'reply.created_at',
                    'user.id as user_id',
                    'user.username',
                    'user.head_img',
                    'user.authen_status',
                    'user.authen_car_name',
                    'user.authen_brand_log',
                ])
                ->leftJoin('user','reply.from_userid','=','user.id')
                ->leftJoin('comment','reply.comment_id','=','comment.id')
                ->leftJoin('publish_news','reply.publish_id','=','publish_news.id')
//                ->where('publish_news.user_id','=',$user_id)
                ->where('reply.from_userid','!=',$user_id)
//                ->where('comment.from_userid','=',$user_id)
//
                ->where(function ($query) use ($user_id) {
                    $query->Orwhere('reply.to_userid','=',$user_id);
                    $query->Orwhere('publish_news.user_id','=',$user_id);
                    $query->Orwhere('comment.from_userid','=',$user_id);
                })
                ->union($comment_sql)
                ->orderBy('created_at','DESC')
                ->offset($per_page * ( $page - 1 ))
                ->limit($per_page)
                ->get()
                ->toArray();
            foreach($result as $key=>$val){
                if($val->author_user_id && $val->author_user_id == $val->user_id){
                    $result[$key]->is_author = 1;
                }else{
                    $result[$key]->is_author = 0;
                }
                //判断当前用户是否关注文章作者
                $result[$key]->is_follow = in_array($val->user_id,$follow_ids)  ? 1 : 0;
                //判断当前用户和文章作者是否是互关状态
                $result[$key]->is_mutual = in_array($val->user_id,$mutual_ids) ? 1 : 0;
            }


        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$result, 'total'=>count($result) ],JSON_UNESCAPED_UNICODE );
    }
    public function today_like_type($user_id){
        $today = date("Ymd");
        $today_like_type = DB::table('like')
            ->select([
                'user.id as user_id',
                'user.username',
                'user.head_img',
                'like.created_at',
                'like.type',
                'like.type_id',
            ])
            ->leftJoin('user','like.from_userid','=','user.id')
            ->where('like.to_userid','=',$user_id)
            ->where('like.from_userid','!=',$user_id)
//            ->whereRaw(DB::raw("DATE_FORMAT(like.created_at,'%Y%m%d')=".$today ))
            ->get()
            ->toArray();

        return $today_like_type;

    }
    //点赞列表
    public function like_list(Request $request){
        $user_id    = $request->input('user_id');//用户id
        $per_page   = (int)$request->input( 'per_page', 10 );
        $page       = (int)$request->input( 'page', 1 );

        //是否关注
        $follow_ids=[];
        $mutual_ids=[];
        $result_follow = DB::table('follow')
            ->where('user_id','=',$user_id)
            ->where('status','=',1)
            ->get()
            ->toArray();
        foreach($result_follow as $key=>$val){
            $follow_ids[$key]=$val->followed_user_id;
            if($val->mutual==1){
                $mutual_ids[] = $val->followed_user_id;
            }
        }

        //文章点赞信息
        $publish = DB::table('like')
            ->select([
                //为了下面使用union，字段必须一样
                'publish_news.model as current_reply_id',
                'publish_news.news_type as pre_reply_id',

                'publish_news.news_type as publish_type',
                'publish_news.id as publish_id',
                'publish_news.carId as comment_id',
                'like.is_read as is_read',
                'publish_news.is_hide as is_hide',
                'publish_news.content as content',
                'user.id as user_id',
                'user.username',
                'user.head_img',
                'user.authen_status',
                'user.authen_car_name',
                'user.authen_brand_log',
                'like.created_at',
                'like.type',
            ])
            ->leftJoin('user','like.from_userid','=','user.id')
            ->leftJoin('publish_news','publish_news.id','=','like.type_id')
            ->where('like.to_userid','=',$user_id)
            ->where('like.from_userid','!=',$user_id)
            ->where('like.type','=',1);

        //评论点赞信息
        $comment = DB::table('like')
            ->select([
                'reply.id as current_reply_id',
                'reply.reply_id as pre_reply_id',

                'comment.publish_type',
                'comment.publish_id',
                'comment.id as comment_id',
                'comment.is_read',
                'comment.is_hide',
                'comment.content',
                'user.id as user_id',
                'user.username',
                'user.head_img',
                'user.authen_status',
                'user.authen_car_name',
                'user.authen_brand_log',
                'like.created_at',
                'like.type',
            ])
            ->leftJoin('user','like.from_userid','=','user.id')
            ->leftJoin('comment','comment.id','=','like.type_id')
            ->leftJoin('reply',function($join){
                $join->on('reply.from_userid','=','user.id')->on('reply.comment_id','=','comment.id');
            })
            ->where('like.to_userid','=',$user_id)
            ->where('like.from_userid','!=',$user_id)
            ->where('like.type','=',2);


        //回复点赞信息
        $result = DB::table('like')
            ->select([
                'reply.id as current_reply_id',
                'reply.reply_id as pre_reply_id',
                'reply.publish_type',
                'reply.publish_id',
                'reply.comment_id',
                'reply.is_read',
                'reply.is_hide',
                'reply.content',
                'user.id as user_id',
                'user.username',
                'user.head_img',
                'user.authen_status',
                'user.authen_car_name',
                'user.authen_brand_log',
                'like.created_at',
                'like.type',
            ])
            ->leftJoin('user','like.from_userid','=','user.id')
            ->leftJoin('reply','reply.id','=','like.type_id')
            ->where('like.to_userid','=',$user_id)
            ->where('like.from_userid','!=',$user_id)
            ->where('like.type','=',3)
            ->union($comment)
            ->union($publish)
            ->orderBy('created_at','DESC')
            ->offset($per_page * ( $page - 1 ))
            ->limit($per_page)
            ->get()
            ->toArray();
//        var_dump($result);die;
        //当天某一文章或评论或回复收到2个以上点赞时返回
        $today_like_type = $this->today_like_type($user_id);
        $res = [];
        //把同一天给同一个点赞的删除，只留一个，下面循环$today_like_type时候是所有点赞数据，在匹配这两个数组的日期，一样的话就把它的插入到没删除的那个数组的子数组里
        foreach($result as $key=>$val) {

            if ( isset( $res[$val->type . '_' . $val->publish_id . '_' . $val->comment_id . '_' . $val->current_reply_id.'_'.substr($val->created_at,0,10) ]) ) {
                unset($result[$key]);
            } else {
                $res[ $val->type . '_' . $val->publish_id . '_' . $val->comment_id . '_' . $val->current_reply_id.'_'.substr($val->created_at,0,10) ] = $val->user_id;
            }

        }

        $result = array_values($result);


        foreach($result as $key=>$val){
            $result[$key]->current_reply_id = $val->current_reply_id===null ? 0 : $val->current_reply_id;
            $result[$key]->pre_reply_id = $val->pre_reply_id===null ? 0 : $val->pre_reply_id;
            $result[$key]->publish_type = $val->publish_type===null ? 0 : $val->publish_type;
            $result[$key]->publish_id = $val->publish_id===null ? 0 : $val->publish_id;
            $result[$key]->comment_id = $val->comment_id===null ? 0 : $val->comment_id;
            $result[$key]->is_hide = $val->is_hide===null ? 1 : $val->is_hide;//0是没删除，null=1=删除

            //判断当前用户是否关注文章作者
            $result[$key]->is_follow = in_array($val->user_id,$follow_ids)  ? 1 : 0;
            //判断当前用户和文章作者是否是互关状态
            $result[$key]->is_mutual = in_array($val->user_id,$mutual_ids) ? 1 : 0;

            $i = 0;
            $j = 0;
            $ks = 0;

            foreach($today_like_type as $k=>$v){
                //文章点赞
                if($v->type==1){
                    if( $val->type == $v->type && $val->publish_id == $v->type_id && $val->user_id!=$v->user_id && date("Ymd",strtotime($val->created_at)) ==  date("Ymd",strtotime($v->created_at)) ){
                        $result[$key]->user_group[$i]['id']  = $v->user_id;
                        $result[$key]->user_group[$i]['username'] = $v->username;
                        $result[$key]->user_group[$i]['head_img'] = $v->head_img;
                        $i++;

                    }
                }

                //评论点赞
                if($v->type==2){
                    if( $val->type == $v->type && $val->comment_id == $v->type_id && $val->user_id!=$v->user_id && date("Ymd",strtotime($val->created_at)) ==  date("Ymd",strtotime($v->created_at)) ){
                        $result[$key]->user_group[$j]['id']  = $v->user_id;
                        $result[$key]->user_group[$j]['username'] = $v->username;
                        $result[$key]->user_group[$j]['head_img'] = $v->head_img;
                        $j++;
                    }

                }

                //回复点赞
                if($v->type==3){
                    if( $val->type == $v->type && $val->current_reply_id == $v->type_id && $val->user_id!=$v->user_id && date("Ymd",strtotime($val->created_at)) ==  date("Ymd",strtotime($v->created_at)) ){
                        $result[$key]->user_group[$ks]['id']  = $v->user_id;
                        $result[$key]->user_group[$ks]['username'] = $v->username;
                        $result[$key]->user_group[$ks]['head_img'] = $v->head_img;
                        $ks++;
                    }
                }


            }
            $result[$key] = json_decode( json_encode( $result[$key] ),true) ;

        }


        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$result, 'total'=>count($result) ],JSON_UNESCAPED_UNICODE );

    }

    //评论||回复 ||点赞已读状态设置
    public function operation_read(Request $request, CurlService $curlService){
        $token                      = $request->input('token');
        $id                         = $request->input('id');//文章id
        $type                       = $request->input('type');//all 全部已读
        $result_token               = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $id = explode(",",$id);
        if($type=='comment'){
            DB::table('comment')->whereIn('id',$id)->update(['is_read'=>1]);
        }else if($type=='reply'){
            DB::table('reply')->whereIn('id',$id)->update(['is_read'=>1]);
        }else if($type=='like'){
            DB::table('like')->whereIn('id',$id)->update(['is_read'=>1]);
        } else if($type=='all'){
            DB::table('comment')->leftJoin('publish_list')->where('publish_news.user_id','=',$result_token->id)->update(['comment.is_read'=>1]);
            DB::table('reply')->where('to_userid','=',$result_token->id)->update(['is_read'=>1]);
            DB::table('like')->where('to_userid','=',$result_token->id)->update(['is_read'=>1]);


        }

        return json_encode( [ 'message' => 'success','code'=>'200' ],JSON_UNESCAPED_UNICODE );
    }

    //删除评论或者回复(不删除数据库，修改状态)
    public function delete_comment_or_reply(Request $request, CurlService $curlService){
        $token                      = $request->input('token');
        $id                         = $request->input('id');//评论或回复id
        $type                       = $request->input('type');//类型
        $result_token               = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $id = explode(",",$id);
        if($type=='comment'){

            //删除评论同时把该评论下的回复也删了
            DB::table('comment')->whereIn('id',$id)->update(['is_hide'=>1]);
            DB::table('reply')->whereIn('comment_id',$id)->update(['is_hide'=>1]);
            //查询出该评论下有多少回复+上该条评论的数量，在文章表里总数减去这个数量
            $result = DB::table('comment')->select(['publish_id','reply_count'])->whereIn('id',$id)->get()->toArray();
            foreach($result as $key=>$val){
                DB::table('publish_news')->where(['id'=>$val['publish_id']])->decrement('comment_reply_num',$val->reply_count);
            }

        }else if($type=='reply'){
            DB::table('reply')->whereIn('id',$id)->update(['is_hide'=>1]);
        }

        return json_encode( [ 'message' => 'success','code'=>'200' ],JSON_UNESCAPED_UNICODE );
    }

    //口碑列表
    public function word_of_mouth_list(Request $request, CurlService $curlService){
        $token                      = $request->input('token');
        $goodId                     = $request->input('goodId');
        $carId                      = $request->input('carId');
        $per_page                   = (int)$request->input( 'per_page', 10 ) ;
        $page                       = (int)$request->input( 'page', 1 );
        $type                       = $request->input('type');

        $where[] = $type=='all'   ? ['publish_news.image','!=',1] : ['publish_news.image','!=',Null];

        $where[] = $carId         ? ['publish_news.carId','=',$carId] : ['publish_news.carId','!=',Null];


        $result_token               = $curlService->getToken($token);

        $query = DB::table('publish_news')
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
                'publish_news.content',
                'publish_news.img_w_h',
                'publish_news.created_at',
                'publish_news.pv',
                'publish_news.news_type',
                'publish_news.show',
                'publish_news.power',
                'publish_news.operate',
                'publish_news.config',
                'publish_news.comfort',
                'brandGood.score',
                'brandGood.show_score',
                'brandGood.power_score',
                'brandGood.operate_score',
                'brandGood.config_score',
                'brandGood.comfort_score',
                'carList.carName',
                'carList.goodsCarName',
            ])
            ->leftJoin( 'user', 'publish_news.user_id', '=', 'user.id' )
            ->leftJoin('brandGood','publish_news.model','=','brandGood.goodId')
            ->leftJoin('carList','publish_news.carId','=','carList.carId')
            ->where("publish_news.model","=",$goodId)
            ->where("publish_news.news_type","=",3)
            ->where($where)
            ->orderBy('publish_news.created_at','DESC');
        if($per_page==''){
            $result = $query->get()->toArray();
        }else{
            $result = $query->offset($per_page * ( $page - 1 ))
                ->limit($per_page)
                ->get()->toArray();
        }


        if(empty($result)){
            return json_encode( [ 'message' => 'no data','code'=>'203' ],JSON_UNESCAPED_UNICODE );
        }

        $score['score']           =   $result[0]->score;
        $score['show_score']      =   $result[0]->show_score;
        $score['power_score']     =   $result[0]->power_score;
        $score['operate_score']   =   $result[0]->operate_score;
        $score['config_score']    =   $result[0]->config_score;
        $score['comfort_score']   =   $result[0]->comfort_score;

        foreach($result as $key => $val){

            //把图片信息写进一个单独的object里面
            if($val->image){
                $images = explode(",",$val->image);
                $img_w_h = json_decode($val->img_w_h);
                $thumbnails_img = explode(",",$val->thumbnails_img);

                foreach($images as $k=>$v){
                    $result[$key]->imageList[$k]['path']=$v;
                    $result[$key]->imageList[$k]['thumbnails_img']=$thumbnails_img[$k];
                    $result[$key]->imageList[$k]['width']=@$img_w_h[$k][0];
                    $result[$key]->imageList[$k]['height']=@$img_w_h[$k][1];
                }
            }
            unset($result[$key]->score);
            unset($result[$key]->show_score);
            unset($result[$key]->power_score);
            unset($result[$key]->operate_score);
            unset($result[$key]->config_score);
            unset($result[$key]->comfort_score);
        }



        return json_encode( [ 'message' => 'success','code'=>'200','data'=>$result ,'total'=>count($result),'score'=>$score],JSON_UNESCAPED_UNICODE );

    }
    //口碑详情
    public function word_of_mouth_detail(Request $request, CurlService $curlService){
        $publish_id         = $request->input('publish_id');
        $token              = $request->input('token');

        //调取接口增加浏览量
        $rand = rand(1,5);
        DB::table('publish_news')->where(['id'=>$publish_id])->increment('pv',$rand);

        $like_ids=[];
        $follow_ids=[];
        $mutual_ids=[];
        if($token){
            $result_token       = $curlService->getToken($token);
            if(!$result_token){
                return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
            }
            //是否收藏
            $current_uid        = isset($result_token->id) ? $result_token->id : null;
            //是否点赞
            $result_like = DB::table('like')
                ->select()
                ->where('from_userid','=',$current_uid)
                ->where('type','=',1)
                ->where('status','=',1)
                ->get()
                ->toArray();
            foreach($result_like as $key=>$val){
                $like_ids[]=$val->type_id;
            }

            //是否关注
            $result_follow = DB::table('follow')
                ->where('user_id','=',$current_uid)
                ->where('status','=',1)
                ->get()
                ->toArray();
            foreach($result_follow as $key=>$val){
                $follow_ids[$key]=$val->followed_user_id;
                if($val->mutual==1){
                    $mutual_ids[] = $val->followed_user_id;
                }
            }

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
                'publish_news.content',
                'publish_news.news_type',
                'publish_news.img_w_h',
                'publish_news.title',
                'publish_news.address',
                'publish_news.like_count',
                'publish_news.created_at',
                'publish_news.pv',
                'publish_news.comment_reply_num',
                'publish_news.show',
                'publish_news.power',
                'publish_news.operate',
                'publish_news.config',
                'publish_news.comfort',
                'brandGood.goodId',
                'brandGood.brandName',
                'brandGood.brandId',
                'brandGood.saleStatus',
                'brandGood.goodName',
                'brandGood.maxPrice',
                'brandGood.minPrice',
                'brandGood.goodLogo',
                'brandGood.carDetailLogo',
                'brandGood.goodType',
                'brandGood.goodVolume',
                'brandGood.goodCylinder',
                'brandGood.goodAbs',
                'brandGood.score',
                'brandGood.show_score',
                'brandGood.power_score',
                'brandGood.operate_score',
                'brandGood.config_score',
                'brandGood.comfort_score',
                'carList.carName',
                'carList.goodsCarName',

            ])
            ->leftJoin( 'user', 'publish_news.user_id', '=', 'user.id' )
            ->leftJoin('brandGood','publish_news.model','=','brandGood.goodId')
            ->leftJoin('carList','publish_news.carId','=','carList.carId')
            ->where(['publish_news.id'=>$publish_id])
            ->orderBy('publish_news.created_at','DESC')
            ->get()->toArray();

        if(empty($result)){
            return json_encode( [ 'message' => 'no data','code'=>'203' ],JSON_UNESCAPED_UNICODE );
        }
        $results = $result[0];
        foreach($results as $key => $val){
            $results->goodId = isset($results->goodId) && $results->goodId ? $results->goodId : 0;
            //把图片信息写进一个单独的object里面
            if($results->image){
                $images = explode(",",$results->image);
                $img_w_h = json_decode($results->img_w_h);
                $thumbnails_img = explode(",",$results->thumbnails_img);
                foreach($images as $k=>$v){
                    $results->imageList[$k]['path']=$v;
                    $results->imageList[$k]['thumbnails_img']=$thumbnails_img[$k];
                    $results->imageList[$k]['width']=@$img_w_h[$k][0];
                    $results->imageList[$k]['height']=@$img_w_h[$k][1];
                }
            }
            //把车辆信息写进一个单独的object里面
            if($results->goodId) {
                $results->carInfo['goodId']    = $results->goodId;
                $results->carInfo['brandName'] = $results->brandName;
                $results->carInfo['brandId'] = $results->brandId;
                $results->carInfo['goodName']  = $results->goodName;
                $results->carInfo['maxPrice']  = $results->maxPrice;
                $results->carInfo['minPrice']  = $results->minPrice;
                $results->carInfo['goodLogo']  = $results->goodLogo;
                $results->carInfo['carDetailLogo']  = $results->carDetailLogo;
                $results->carInfo['goodType']  = $results->goodType;
                $results->carInfo['goodVolume']  = $results->goodVolume;
                $results->carInfo['goodCylinder']  = $results->goodCylinder;
                $results->carInfo['goodAbs']  = $results->goodAbs;
                $results->carInfo['saleStatus']  = $results->saleStatus;
            }else{
                unset($results->goodId);
                unset($results->brandName);
                unset($results->brandId);
                unset($results->goodName);
                unset($results->minPrice);
                unset($results->minPrice);
                unset($results->goodLogo);
                unset($results->carDetailLogo);
                unset($results->goodType);
                unset($results->goodVolume);
                unset($results->goodCylinder);
                unset($results->goodAbs);
                unset($results->saleStatus);
            }

            //判断当前用户是否点赞口碑
            $results->is_like = in_array($results->id,$like_ids)  ? 1 :0;
            //判断当前用户是否关注文章作者
            $results->is_follow = in_array($results->user_id,$follow_ids)  ? 1 : 0;
            //判断当前用户和文章作者是否是互关状态
            $results->is_mutual = in_array($results->user_id,$mutual_ids) ? 1 : 0;

        }
        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$results ],JSON_UNESCAPED_UNICODE );
    }

    public function car_publish(Request $request, CurlService $curlService){
        $goodId             = $request->input('goodId');
        $token              = $request->input('token');
        if($token){
            $result_token       = $curlService->getToken($token);
            if(!$result_token){
                return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
            }
        }
        $per_page           = (int)$request->input( 'per_page', 10 );
        $page               = (int)$request->input( 'page', 1 );

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
            ->leftJoin('topic','publish_news.topic','=','topic.id')
            ->where(["model"=>$goodId])
            ->whereIn("news_type",[0,1,2])
            ->offset($per_page * ( $page - 1 ))
            ->limit($per_page)
            ->orderBy('publish_news.created_at','DESC')
            ->get()->toArray();

        if(empty($result)){
            return json_encode( [ 'message' => 'no data','code'=>'203' ],JSON_UNESCAPED_UNICODE );
        }

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

    //删除
    public function delete(Request $request, CurlService $curlService){
        $token            = $request->input('token');
        $type             = $request->input('type');
        $publish_id       = $request->input('publish_id');
        $comment_id       = $request->input('comment_id');
        $reply_id         = $request->input('reply_id');



        $result_token               = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $validator = \Validator::make($request->all(), [
            'type'     =>  'required',
        ]);

        if ( $validator->fails() ) {
            $message = array_values($validator->errors()->get('*'))[0][0];
            return json_encode( [ 'message' => $message,'code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }

        switch($type){

            case 'publish':
                //删除文章同时把该文章下的评论和回复也删了
                $image = DB::table('publish_news')->select(['image'])->where(['id'=>$publish_id])->first();
                $images=explode(",",$image->image);
                foreach($images as $key=>$val){
                    $file_path = public_path().$val;
                    if(file_exists($file_path)){
                        unlink($file_path);
                    }
                }

                DB::table('publish_news')->where(['id'=>$publish_id])->delete();

                DB::table('comment')->where(['publish_id'=>$publish_id])->update(['is_hide'=>1]);
                DB::table('reply')->where(['publish_id'=>$publish_id])->update(['is_hide'=>1]);
                DB::table('like')->where(['type'=>1])->where(['type_id'=>$publish_id])->update(['status'=>0]);

                break;
            case 'comment':
                //删除评论同时把该评论下的回复也删了
                DB::table('comment')->where(['id'=>$comment_id])->update(['is_hide'=>1]);
                DB::table('reply')->where(['comment_id'=>$comment_id])->update(['is_hide'=>1]);
                DB::table('like')->where(['type'=>2])->where(['type_id'=>$comment_id])->update(['status'=>0]);
                //查询出该评论下有多少回复+上该条评论的数量，在文章表里总数减去这个数量
                $result = DB::table('comment')->select(['reply_count'])->where(['id'=>$comment_id])->get()->toArray();
                if($result){
                    DB::table('publish_news')->where(['id'=>$publish_id])->decrement('comment_reply_num',$result[0]->reply_count+1);
                }
                break;
            case 'reply':
                $result = DB::table('reply')->where(['id'=>$reply_id])->update(['is_hide'=>1]);
                DB::table('like')->where(['type'=>3])->where(['type_id'=>$reply_id])->update(['status'=>0]);

                if($result){
                    DB::table('comment')->where(['id'=>$comment_id])->decrement('reply_count');
                    DB::table('publish_news')->where(['id'=>$publish_id])->decrement('comment_reply_num');
                }
                break;
        }
        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>@$result ],JSON_UNESCAPED_UNICODE );


    }

    //消息页面展示评论和点赞的总数量和最后一次点赞/评论的用户
    public function message_count(Request $request, CurlService $curlService){
        $token            = $request->input('token');

        $result_token               = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $user_id = $result_token->id;
        $result = $this->user_message_count($user_id);



        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$result ],JSON_UNESCAPED_UNICODE );
    }

    public function user_message_count($user_id){
        $result['like_count']       = DB::table('like')->where(["to_userid"=>$user_id])->count();//点赞总数
        $result['follow_count']       = DB::table('follow')->where(["user_id"=>$user_id])->where(["status"=>1])->count();//关注数
        $result['fans_count']       = DB::table('follow')->where(["followed_user_id"=>$user_id])->where(["status"=>1])->count();//粉丝数
        $reply_count                = DB::table('reply')->where(["to_userid"=>$user_id])->count();//回复总数
        $result['comment_count']    = DB::table('comment')
                ->leftJoin( 'publish_news', 'publish_news.id', '=', 'comment.publish_id' )
                ->where(["publish_news.user_id"=>$user_id])
                ->count()+$reply_count;//评论总数+回复总数

        $last_like                  = DB::table('like')
            ->select(['user.username','user.head_img','like.type','like.type_id','like.created_at'])
            ->leftJoin( 'user', 'user.id', '=', 'like.from_userid' )
            ->where("like.to_userid",'=',$user_id)
            ->where("like.from_userid",'!=',$user_id)
            ->orderBy('like.created_at','DESC')
            ->first();//最后一次点赞用户
        if(isset($last_like->type)){
            if($last_like->type == 1){
                $news_type = DB::table('publish_news')
                    ->select(['news_type'])
                    ->where("id",'=',$last_like->type_id)
                    ->first();//最后一次点赞用户
                $result['last_like']            = $last_like;
                $result['last_like']->news_type =  isset($news_type) ? $news_type->news_type : null;
            }else{
                $result['last_like']            = $last_like;
            }
        }

        $result['last_follow']                 = DB::table('follow')
            ->select(['user.username','user.head_img','follow.created_at'])
            ->leftJoin( 'user', 'user.id', '=', 'follow.user_id' )
            ->where("follow.followed_user_id",'=',$user_id)
            ->where("follow.user_id",'!=',$user_id)
            ->where("follow.status",'=',1)
            ->orderBy('follow.created_at','DESC')
            ->first();//最后一次关注的用户

        $comment_info    = DB::table('comment')
            ->select(['comment.created_at','user.head_img','user.username','comment.publish_type'])
            ->leftJoin( 'publish_news', 'publish_news.id', '=', 'comment.publish_id' )
            ->leftJoin( 'user', 'user.id', '=', 'comment.from_userid' )
            ->where("publish_news.user_id",'=',$user_id)
            ->where("comment.from_userid",'!=',$user_id)
            ->orderBy('comment.created_at','DESC')
            ->first();
        $reply_info    = DB::table('reply')
            ->select(['reply.created_at','user.username','reply.publish_type','reply.reply_id','reply.to_username'])
            ->leftJoin('user','reply.from_userid','=','user.id')
            ->leftJoin('comment','reply.comment_id','=','comment.id')
            ->leftJoin('publish_news','reply.publish_id','=','publish_news.id')
            ->where('reply.from_userid','!=',$user_id)
            ->where(function ($query) use ($user_id) {
                $query->Orwhere('reply.to_userid','=',$user_id);
                $query->Orwhere('publish_news.user_id','=',$user_id);
                $query->Orwhere('comment.from_userid','=',$user_id);
            })
            ->orderBy('reply.created_at','DESC')
            ->first();
        $result['last_comment_or_reply'] = strtotime(@$comment_info->created_at) > strtotime(@$reply_info->created_at) ? $comment_info : $reply_info;

        //未读数量
        $last_like_un_read_count =DB::table('like')->where(["status"=>1])->where(["to_userid"=>$user_id])->where(["is_read"=>0])->count();//未读点赞数
        if($last_like_un_read_count){

            @$result['last_like']->un_read_count       = $last_like_un_read_count;

        }

        $last_follow_un_read_count = DB::table('follow')->where(["followed_user_id"=>$user_id])->where(["status"=>1])->where(["is_read"=>0])->count();//未读关注数
        if($last_follow_un_read_count){

            @$result['last_follow']->un_read_count      = $last_follow_un_read_count;

        }


        @$un_reply_count                = DB::table('reply')
            ->where(["to_userid"=>$user_id])
            ->where(["is_hide"=>0])
            ->where(["is_read"=>0])->count();//回复总数
        @$un_comment_count              = DB::table('comment')
            ->leftJoin( 'publish_news', 'publish_news.id', '=', 'comment.publish_id' )
            ->where(["publish_news.user_id"=>$user_id])
            ->where(["comment.is_read"=>0])
            ->where(["comment.is_hide"=>0])
            ->count();
        if($un_reply_count || $un_comment_count){
            @$result['last_comment_or_reply']->un_read_count = $un_comment_count+$un_reply_count;//评论总数+回复总数
        }

        return $result;
    }

    //咨询点赞列表
    public function publish_like(Request $request, CurlService $curlService){
        $token              = $request->input('token');
        $publish_id         = $request->input('publish_id');
        $user_id            = (int)$request->input('user_id',0);
        $per_page           = (int)$request->input( 'per_page', 10 );
        $page               = (int)$request->input( 'page', 1 );
        $result_token       = $curlService->getToken($token);
        if($token && !$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        //是否关注
        $follow_ids=[];
        $mutual_ids=[];
        if($result_token){
            $result_follow = DB::table('follow')
                ->where('user_id','=',$result_token->id)
                ->where('status','=',1)
                ->get()
                ->toArray();
            foreach($result_follow as $key=>$val){
                $follow_ids[$key]=$val->followed_user_id;
                if($val->mutual==1){
                    $mutual_ids[] = $val->followed_user_id;
                }
            }
        }


        $like_list          = DB::table('like')
            ->select(DB::raw('user.username,user.head_img,user.authen_status,user.authen_car_name,user.authen_brand_log,user.id as user_id,like.created_at,case when like.from_userid='.$user_id.' then 0 else 1 end as flag'))
            ->leftJoin( 'user', 'user.id', '=', 'like.from_userid' )
            ->where('like.type','=',1)
            ->where('like.status','=',1)
            ->where('like.type_id','=',$publish_id)
            ->orderBy('flag','asc')
            ->offset($per_page * ( $page - 1 ))
            ->limit($per_page)
            ->get()
            ->toArray();
        foreach($like_list as $key=>$val){
            //判断当前用户是否关注文章作者
            $like_list[$key]->is_follow = in_array($val->user_id,$follow_ids)  ? 1 : 0;
            //判断当前用户和文章作者是否是互关状态
            $like_list[$key]->is_mutual = in_array($val->user_id,$mutual_ids) ? 1 : 0;
        }
        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$like_list ],JSON_UNESCAPED_UNICODE );


    }
    //用户都给哪些文章点过赞
    public function user_like(Request $request, CurlService $curlService){

        $token              = $request->input('token');
        $user_id            = (int)$request->input('user_id',0);
        $per_page           = (int)$request->input( 'per_page', 10 );
        $page               = (int)$request->input( 'page', 1 );
        $result_token       = $curlService->getToken($token);
        if($token && !$result_token){
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
            ->leftJoin( 'like', 'like.type_id', '=', 'publish_news.id' )
            ->leftJoin( 'user', 'publish_news.user_id', '=', 'user.id' )
            ->leftJoin('brandGood','publish_news.model','=','brandGood.goodId')
            ->leftJoin('carList','publish_news.carId','=','carList.carId')
            ->leftJoin('topic','publish_news.topic','=','topic.id')
            ->where('like.status','=',1)
            ->where('like.type','=',1)
            ->where('like.from_userid','=',$user_id)
            ->where('like.to_userid','!=',$user_id)
            ->offset($per_page * ( $page - 1 ))
            ->limit($per_page)
            ->orderBy('like.created_at','DESC')
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

    public function car_info(Request $request, CurlService $curlService){
        $goodId             = $request->input('goodId');
        $token              = $request->input('token');
        $result_token       = $curlService->getToken($token);
        if($token && !$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $goodIds=[];


        $car_info = DB::table('brandGood')
            ->select([
                'brandGood.goodId',
                'brandGood.goodName',
                'brandGood.brandId',
                'brandGood.brandName',
                'brandGood.maxPrice',
                'brandGood.minPrice',
                'brandGood.saleStatus',
                'brandGood.goodLogo',
                'brandGood.seriesId',
                'brandGood.seriesName',
                'brandGood.goodVolume',
                'brandGood.goodVolumeDecimal',
                'brandGood.goodCylinder',
                'brandGood.goodCoolDown',
                'brandGood.carDetailLogo',
                'brandGood.userManual',
                'brandGood.goodAbs',
                'brandGood.goodType',
                'brandGood.energyType',
            ])
            ->where('brandGood.goodId','=',$goodId)
            ->first();

        if($token){
            $good_id = DB::table('my_like')
                ->select([
                    'good_id',
                ])
                ->where('my_like.user_id','=',$result_token->id)
                ->get()
                ->toArray();
            foreach($good_id as $key=>$val){
                $goodIds[]=$val->good_id;
            }

        }
        //是否关注
        if(in_array($car_info->goodId,$goodIds)){
            $car_info->isLike = 1;
        }else{
            $car_info->isLike = 0;
        }

        //图片数量
        $img_type_count = DB::table('car_image_new')
            ->select(['car_image_new.img_type','car_image_new.img'])
            ->leftJoin( 'carList', 'carList.carId', '=', 'car_image_new.cid' )
            ->where('carList.goodId','=',$goodId)
            ->get()
            ->toArray();
            $car_info->imgDetailsCount = 0;
            $car_info->imgOfficialCount = 0;
            $car_info->imgOverviewCount = 0;
            foreach($img_type_count as $key => $val){
                switch($val->img_type){
                    case 'details':
                        $car_info->imgDetailsCount += count(explode(",",$val->img));
                        break;
                    case 'official':
                        $car_info->imgOfficialCount += count(explode(",",$val->img));
                        break;
                    case 'overview':
                        $car_info->imgOverviewCount += count(explode(",",$val->img));
                        break;
                 }
            }

        //资讯数量（只要动态，文章，视频的总数量）
        $car_info->publish_count = DB::table('publish_news')->where('model','=',$goodId)->whereIn('news_type',[0,1,2])->count();
        //全部口碑数量
        $car_info->public_praise_count = DB::table('publish_news')->where('model','=',$goodId)->where('news_type','=',3)->count();
        //带图片的口碑数量
        $car_info->public_praise_image_count = DB::table('publish_news')->where('model','=',$goodId)->where('news_type','=',3)->where('image','!=',null)->count();


        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$car_info ],JSON_UNESCAPED_UNICODE );

    }


    //测试调用java接口
    public function java_image_upload(Request $request){
        $file = $request->input('file');
//        $souce_src = '/storage/images/publishNews/'.date("Ymd").'/thumbnails/';
        $despath = public_path().'/storage/images/test_thumb/'.md5(rand(1,11)).'.png';
        $curl = new CurlService();
        $result = json_decode($curl->send_img('http://motocircle.cn:8899/image/imgThumb','POST',[],['desPath'=>$despath,'srcPath'=>public_path().'/storage/images/test_img/img_4367.png','height'=>900,'width'=>600]),true);
        var_dump($result);die;
    }

}
