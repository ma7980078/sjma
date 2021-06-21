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
    public function getCommentInfo( Request $request ){

        $per_page           = (int)$request->input( 'per_page', 10 );
        $page               = (int)$request->input( 'page', 1 );

        $result = $this->getData($per_page,$page);

        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$result ],JSON_UNESCAPED_UNICODE );
    }

    //用户的动态文章详情
    public function UserPublishNews( Request $request, CurlService $curlService){
        $token              = $request->input('token');
        $user_id            = $request->input('user_id');
        $per_page           = (int)$request->input( 'per_page', 10 );
        $page               = (int)$request->input( 'page', 1 );
        $result_token       = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }

        $result = $this->getData($per_page,$page,$user_id);
        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$result ],JSON_UNESCAPED_UNICODE );
    }

    public function getData($per_page,$page,$user_id=null){
        $where[] = $user_id ? ['publish_news.user_id','=',$user_id] : ['publish_news.id','>',0];
        $result = DB::table('publish_news')
            ->select([
                'user.username',
                'user.head_img',
                'publish_news.user_id',
                'publish_news.image',
                'publish_news.content',
                'publish_news.news_type',
                'publish_news.img_w_h',
                'publish_news.title',
                'publish_news.created_at',
                'brandGood.goodId',
                'brandGood.brandName',
                'brandGood.goodName',
                'brandGood.maxPrice',
                'brandGood.minPrice',
                'brandGood.goodLogo',
                'topic.topic',
            ])
            ->leftJoin( 'user', 'publish_news.user_id', '=', 'user.id' )
            ->leftJoin('brandGood','publish_news.model','=','brandGood.goodId')
            ->leftJoin('topic','publish_news.topic','=','topic.id')
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
                $result[$key]->carInfo['goodName']  = $val->goodName;
                $result[$key]->carInfo['maxPrice']  = $val->maxPrice;
                $result[$key]->carInfo['minPrice']  = $val->minPrice;
                $result[$key]->carInfo['goodLogo']  = $val->goodLogo;
            }
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
            'publish_news.created_at',
        ])
            ->leftJoin('publish_news','favorites.publish_id','=','publish_news.id')
            ->where(['favorites.user_id'=>$user_id])
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
}
