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
    public function __construct()
    {
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

        $result = $this->getData($per_page,$page,null,null,isset($result_token->id) ? $result_token->id : null);

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
        $result = $this->getData($per_page,$page,$user_id,$news_type,isset($result_token->id) ? $result_token->id : null);
        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$result ],JSON_UNESCAPED_UNICODE );
    }

    public function getData($per_page,$page,$user_id=null,$news_type=null,$current_uid=null){
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
            ->leftJoin( 'user', 'publish_news.user_id', '=', 'user.id' )
            ->leftJoin('brandGood','publish_news.model','=','brandGood.goodId')
            ->leftJoin('topic','publish_news.topic','=','topic.id')
            ->leftJoin('favorites','publish_news.id','=','favorites.publish_id')
            ->where($where)
            ->offset($per_page * ( $page - 1 ))
            ->limit($per_page)
            ->orderBy('publish_news.created_at','DESC')
            ->get()->toArray();


        foreach($result as $key => $val){
            $result[$key]->goodId = $val->goodId ? $val->goodId : 0;
            //把图片信息写进一个单独的object里面
            if($val->image){
                $images = explode(",",$val->image);
                $img_w_h = json_decode($val->img_w_h);
                foreach($images as $k=>$v){
                    $result[$key]->imageList[$k]['path']=$v;
                    $result[$key]->imageList[$k]['width']=@$img_w_h[$k][0];
                    $result[$key]->imageList[$k]['height']=@$img_w_h[$k][1];
//                    if($val->news_type===1){
//                        $val->content = preg_replace('/\/8moquan8/', 'motocircle.cn'.$v, $val->content, 1);
//                    }
                }
            }
            //把车辆信息写进一个单独的object里面
            if($val->goodId) {
                $result[$key]->carInfo['goodId']    = $val->goodId;
                $result[$key]->carInfo['brandName'] = $val->brandName;
                $result[$key]->carInfo['brandId'] = $val->brandId;
                $result[$key]->carInfo['goodName']  = $val->goodName;
                $result[$key]->carInfo['maxPrice']  = $val->maxPrice;
                $result[$key]->carInfo['minPrice']  = $val->minPrice;
                $result[$key]->carInfo['goodLogo']  = $val->goodLogo;
                $result[$key]->carInfo['carDetailLogo']  = $val->carDetailLogo;
                $result[$key]->carInfo['goodType']  = $val->goodType;
                $result[$key]->carInfo['goodVolume']  = $val->goodVolume;
                $result[$key]->carInfo['goodCylinder']  = $val->goodCylinder;
                $result[$key]->carInfo['goodAbs']  = $val->goodAbs;
                $result[$key]->carInfo['saleStatus']  = $val->saleStatus;
            }else{
                unset($result[$key]->goodId);
                unset($result[$key]->brandName);
                unset($result[$key]->brandId);
                unset($result[$key]->goodName);
                unset($result[$key]->minPrice);
                unset($result[$key]->minPrice);
                unset($result[$key]->goodLogo);
                unset($result[$key]->carDetailLogo);
                unset($result[$key]->goodType);
                unset($result[$key]->goodVolume);
                unset($result[$key]->goodCylinder);
                unset($result[$key]->goodAbs);
                unset($result[$key]->saleStatus);
            }

            //判断当前用户是否收藏文章
            $result[$key]->is_favorites = $val->current_uid==$current_uid&&$val->status==1 ? 1 :0;
        }
        return $result;
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

    //用户点赞/取消点赞
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
        return json_encode( [ 'message' => 'success','code'=>'200','comment_id'=>$result ],JSON_UNESCAPED_UNICODE );
    }

    //回复
    public function reply(Request $request, CurlService $curlService){
        $token                      = $request->input('token');
        $publish_id                 = $request->input('publish_id');//文章id
        $data['comment_id']         = $request->input('comment_id');//评论id
        $data['reply_id']           = $request->input('reply_id');//回复目标id，reply_type=0时为空
        $data['reply_type']         = $request->input('reply_type');//回复类型，0=针对评论进行回复；1=针对回复进行回复
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
            DB::table('publish_news')->where(['id'=>$publish_id])->increment('comment_reply_num');
        }
        return json_encode( [ 'message' => 'success','code'=>'200','reply_id'=>$result ],JSON_UNESCAPED_UNICODE );
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
                'reply.reply_type',
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
