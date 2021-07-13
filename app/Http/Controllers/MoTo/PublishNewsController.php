<?php

namespace App\Http\Controllers\MoTo;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Validator;
use App\Services\CurlService;
use App\Services\UploadFileService;

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
        $master_secret='289fefce3e0934bef1150c24';
        $this->header=['Authorization: Basic '.base64_encode($app_key.$master_secret),'Content-Type: application/json'];
        $this->username='729145859@qq.com';
        $this->password='GwS612106';
    }

    public function sendPush(Request $request, CurlService $curlService){
        //调取极光注册用户接口
        $content = $request->input('content','testPush');
        $send_jg_data['platform']='all';
        $send_jg_data['audience']='all';
        $send_jg_data['notification']['alert']=$content;

        $param['username'] = $this->username.':'.$this->password;


        $inter_result = json_decode($curlService->send($this->curl,'POST',$this->header,$send_jg_data,$param),true);
        var_dump($inter_result);die;
        if(isset($inter_result[0]['error'])){
            return json_encode(['code'=>@$inter_result[0]['code']['message'],'message'=>@$inter_result[0]['error']['message']]);
        }
    }

    public function index( Request $request, CurlService $curlService, UploadFileService $uploadFileService ){
        $token              = $request->input('token');
        $data['news_type']  = $request->input('news_type');
        $result_token       = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $images             = $request->file('image');


        $result_image       = $uploadFileService->img_upload($images,'publishNews');

        file_put_contents( storage_path( "logs/upload_img.log" ), json_encode($result_image) . PHP_EOL, FILE_APPEND );

        $data['image']      = implode(',',$result_image[0]) ;
        $data['content']    = $request->input('content');
        $data['user_id']    = $request->input('user_id');
        $data['img_w_h']    = json_encode($result_image[1]);
        $data['address']    = $request->input('address');

        //0是发布动态。1是发布文章
        if($data['news_type']==0){
            $validator = \Validator::make($request->all(), [
                'user_id'     =>  'required|integer',
                'content'     =>  'required|max:500',
            ]);

            if ( $validator->fails() ) {
                $message = array_values($validator->errors()->get('*'))[0][0];
                return json_encode( [ 'message' => $message,'code'=>'401' ],JSON_UNESCAPED_UNICODE );
            }

            $data['model']      = $request->input('model');
            $data['topic']      = $request->input('topic');
        }else if($data['news_type']==1){
            $data['title']      = $request->input('title');
        }

        $result = DB::table('publish_news')->insert($data);

        if(!$result){
            return json_encode(['code'=>401,'message'=>'发布失败']);
        }else{
            return json_encode(['code'=>200,'message'=>'发布成功']);
        }
    }

    //获取评论的详细信息
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
//        $where[] = ['favorites.status','=',1] ;
        $result = DB::table('publish_news')
            ->select([
                'user.username',
                'user.head_img',
                'publish_news.id',
                'publish_news.user_id',
                'publish_news.image',
                'publish_news.content',
                'publish_news.news_type',
                'publish_news.img_w_h',
                'publish_news.title',
                'publish_news.created_at',
                'publish_news.comment_reply_num',
                'topic.topic',
            ])
            ->leftJoin('favorites',function($join){
                $join->on('publish_news.id','=','favorites.publish_id')->on('publish_news.user_id','=','favorites.user_id');
            })
            ->leftJoin( 'user', 'publish_news.user_id', '=', 'user.id' )
            ->leftJoin('brandGood','publish_news.model','=','brandGood.goodId')
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
                $img_w_h = json_decode($val->img_w_h);
                foreach($images as $k=>$v){
                    $result[$key]->imageList[$k]['path']=$v;
                    $result[$key]->imageList[$k]['width']=@$img_w_h[$k][0];
                    $result[$key]->imageList[$k]['height']=@$img_w_h[$k][1];
                }
            }
        }
        return $result;
    }
    //单篇文章/动态详情
    public function PublishNewsDetail(Request $request, CurlService $curlService){
        $publish_id         = $request->input('publish_id');
        $token              = $request->input('token');
        if($token){
            $result_token       = $curlService->getToken($token);
            $current_uid        = isset($result_token->id) ? $result_token->id : null;
            if(!$result_token){
                return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
            }
        }
        $result = DB::table('publish_news')
            ->select([
                'user.username',
                'user.head_img',
                'publish_news.id',
                'publish_news.user_id',
                'publish_news.image',
                'publish_news.content',
                'publish_news.news_type',
                'publish_news.img_w_h',
                'publish_news.title',
                'publish_news.address',
                'publish_news.created_at',
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

        $results = $result[0];
        foreach($results as $key => $val){
            $results->goodId = isset($results->goodId) && $results->goodId ? $results->goodId : 0;
            //把图片信息写进一个单独的object里面
            if($results->image){
                $images = explode(",",$results->image);
                $img_w_h = json_decode($results->img_w_h);
                foreach($images as $k=>$v){
                    $results->imageList[$k]['path']=$v;
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

            //判断当前用户是否收藏文章
            $results->is_favorites = $results->current_uid==@$current_uid&&$results->status==1 ? 1 :0;
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
        $status            = $request->input('status');
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
        $data['content']            = $request->input('content');
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
        return json_encode( [ 'message' => 'success','code'=>'200','comment_id'=>$result,'is_author'=>$is_author ],JSON_UNESCAPED_UNICODE );
    }

    //回复
    public function reply(Request $request, CurlService $curlService){
        $token                      = $request->input('token');
        $data['publish_id']         = $request->input('publish_id');//文章id
        $data['comment_id']         = $request->input('comment_id');//评论id
        $data['reply_id']           = $request->input('reply_id');//回复目标id，publish_type=0时为空
        $data['publish_type']         = $request->input('publish_type');//回复类型，0=针对评论进行回复；1=针对回复进行回复
        $data['content']            = $request->input('content');//回复内容
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
        return json_encode( [ 'message' => 'success','code'=>'200','reply_id'=>$result,'is_author'=>$is_author ],JSON_UNESCAPED_UNICODE );
    }
    //评论列表
    public function comment_list(Request $request){
        $publish_id = $request->input('publish_id');//文章id
        $per_page   = (int)$request->input( 'per_page', 10 );
        $page       = (int)$request->input( 'page', 1 );
        $result = DB::table('comment')
            ->select([
                'comment.id',
                'comment.content',
                'comment.reply_count',
                'comment.created_at',
                'user.id as user_id',
                'user.username',
                'user.head_img',
            ])
            ->leftJoin('user','comment.from_userid','=','user.id')
            ->where(['publish_id'=>$publish_id])
            ->offset($per_page * ( $page - 1 ))
            ->limit($per_page)
            ->get()
            ->toArray();

        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$result, 'total'=>count($result) ],JSON_UNESCAPED_UNICODE );
    }
    //回复列表
    public function reply_list(Request $request){
        $comment_id = $request->input('comment_id');//评论id
        $per_page   = (int)$request->input( 'per_page', 10 );
        $page       = (int)$request->input( 'page', 1 );
        $result = DB::table('reply')
            ->select([
                'reply.id',
                'reply.content',
                'reply.reply_id',
                'reply.publish_type',
                'reply.to_username',
                'reply.to_userid',
                'reply.created_at',
                'user.id as user_id',
                'user.username',
                'user.head_img',
            ])
            ->leftJoin('user','reply.from_userid','=','user.id')
            ->where(['comment_id'=>$comment_id])
            ->offset($per_page * ( $page - 1 ))
            ->limit($per_page)
            ->get()
            ->toArray();
        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$result ],JSON_UNESCAPED_UNICODE );
    }
    //用户在消息列表那里点击进入文章详情
    public function message_in_comment_list(Request $request){
        $publish_id = $request->input('publish_id');//文章id
        $comment_id = $request->input('comment_id');//评论id
        $reply['current_reply_id'] = $request->input('current_reply_id');//回复id
        $reply['pre_reply_id'] = $request->input('pre_reply_id');//被回复id
        $per_page   = (int)$request->input( 'per_page', 10 );
        $page       = (int)$request->input( 'page', 1 );
        $result = DB::table('comment')
            ->select([
                'comment.id',
                'comment.content',
                'comment.reply_count',
                'comment.created_at',
                'user.id as user_id',
                'user.username',
                'user.head_img',
            ])
            ->leftJoin('user','comment.from_userid','=','user.id')
            ->where(['comment.publish_id'=>$publish_id])
            ->orderByRaw(DB::raw("FIELD(comment.id,".$comment_id.") desc"))
            ->offset($per_page * ( $page - 1 ))
            ->limit($per_page)
            ->get()
            ->toArray();

        $reply_result = DB::table('reply')
            ->select([
                'reply.id',
                'reply.content',
                'reply.reply_id',
                'reply.publish_type',
                'reply.to_username',
                'reply.to_userid',
                'reply.created_at',
                'user.id as user_id',
                'user.username',
                'user.head_img',
            ])
            ->leftJoin('user','reply.from_userid','=','user.id')
            ->where(function ($query) use ($reply) {
                if($reply['current_reply_id']){
                    $query->where('reply.id','=',$reply['current_reply_id']);
                }
                if($reply['pre_reply_id']){
                    $query->orWhere('reply.id','=',$reply['pre_reply_id']);
                }
            })
            ->orderBy('id','desc')
            ->offset($per_page * ( $page - 1 ))
            ->limit($per_page)
            ->get()
            ->toArray();

        if($comment_id){
            foreach($result as $key=>$val){

                if($comment_id==$val->id){
                    //把点击进来的评论放到数组第一个
                    $first_array = $result[$key];
                    unset($result[$key]);
                    array_unshift($result,$first_array);
                    //如果是点击回复评论的信息进来的就给加上当前回复信息
                    if($reply['current_reply_id']){
                        $result[0]->current_reply_info = $reply_result[0];
                    }
                    //如果是点击回复 回复的信息进来的就给加上被回复的那条信息
                    if($reply['pre_reply_id']){
                        $result[0]->pre_reply_info = $reply_result[1];
                    }
                }

            }
        }


        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$result, 'total'=>count($result) ],JSON_UNESCAPED_UNICODE );
    }
    //查询用户被评论被回复列表
    public function user_comment_list(Request $request){
        $user_id = $request->input('user_id');//用户id
        $per_page   = (int)$request->input( 'per_page', 10 );
        $page       = (int)$request->input( 'page', 1 );
        //当前用户被评论信息
        $comment_sql = DB::table('comment')
            ->select([
                'reply.id as current_reply_id',
                'reply.reply_id as pre_reply_id',
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
            ])
            ->leftJoin('user','comment.from_userid','=','user.id')
            ->leftJoin('reply',function($join){
                $join->on('reply.from_userid','=','user.id')->on('reply.comment_id','=','comment.id');
            })
            ->leftJoin('publish_news','comment.publish_id','=','publish_news.id')
            ->where(function ($query) use ($user_id) {
                    $query->where('publish_news.user_id','=',$user_id);
                    $query->where('comment.from_userid','!=',$user_id);
            });


            $result = DB::table('reply')
                ->select([
                    'reply.id as current_reply_id',
                    'reply.reply_id as pre_reply_id',
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
                ])
                ->leftJoin('user','reply.from_userid','=','user.id')
                ->leftJoin('publish_news','reply.publish_id','=','publish_news.id')
                ->where('publish_news.user_id','=',$user_id)
                ->Orwhere(function ($query) use ($user_id) {
                    $query->where('reply.from_userid','!=',$user_id);
                    $query->where('reply.to_userid','=',$user_id);
                })
                ->union($comment_sql)
                ->orderBy('created_at','DESC')
                ->offset($per_page * ( $page - 1 ))
                ->limit($per_page)
                ->get()
                ->toArray();


        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$result, 'total'=>count($result) ],JSON_UNESCAPED_UNICODE );
    }

    //评论||回复已读状态设置
    public function operation_read(Request $request, CurlService $curlService){
        $token                      = $request->input('token');
        $id                         = $request->input('id');//文章id
        $type                       = $request->input('type');//评论id
        $result_token               = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $id = explode(",",$id);
        if($type=='comment'){
            DB::table('comment')->whereIn('id',$id)->update(['is_read'=>1]);
        }else if($type=='reply'){
            DB::table('reply')->whereIn('id',$id)->update(['is_read'=>1]);
        }

        return json_encode( [ 'message' => 'success','code'=>'200' ],JSON_UNESCAPED_UNICODE );
    }

    //删除评论或者回复(不删除数据库，修改状态)
    public function delete_comment_or_reply(Request $request, CurlService $curlService){
        $token                      = $request->input('token');
        $id                         = $request->input('id');//文章id
        $type                       = $request->input('type');//评论id
        $result_token               = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $id = explode(",",$id);
        if($type=='comment'){
            DB::table('comment')->whereIn('id',$id)->update(['is_hide'=>1]);
        }else if($type=='reply'){
            DB::table('reply')->whereIn('id',$id)->update(['is_hide'=>1]);
        }

        return json_encode( [ 'message' => 'success','code'=>'200' ],JSON_UNESCAPED_UNICODE );
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
                $result = DB::table('publish_news')->where(['publish_id'=>$publish_id])->delete();
                break;
            case 'comment':
                $result = DB::table('comment')->where(['id'=>$comment_id])->delete();
                if($result){
                    DB::table('publish_news')->where(['id'=>$publish_id])->decrement('comment_reply_num');
                }
                break;
            case 'reply':
                $result = DB::table('reply')->where(['id'=>$reply_id])->delete();
                if($result){
                    DB::table('comment')->where(['id'=>$comment_id])->decrement('reply_count');
                }
                break;
        }
        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>@$result ],JSON_UNESCAPED_UNICODE );


    }
}
