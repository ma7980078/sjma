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
        $result_token       = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }



        $validator = \Validator::make($request->all(), [
            'user_id'     =>  'required|integer',
            'content'     =>  'required|max:500',
        ]);

        if ( $validator->fails() ) {
            $message = array_values($validator->errors()->get('*'))[0][0];
            return json_encode( [ 'message' => $message,'code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }

        //APP那边说不能传image数组，不知道为啥。

        $images   = [
            'image1'=>$request->file('image1'),
            'image2'=>$request->file('image2'),
            'image3'=>$request->file('image3'),
            'image4'=>$request->file('image4'),
            'image5'=>$request->file('image5'),
            'image6'=>$request->file('image6'),
            'image7'=>$request->file('image7'),
            'image8'=>$request->file('image8'),
            'image9'=>$request->file('image9'),

        ];
        $result_image       = $uploadFileService->img_upload($images,'publishNews');
        $data['image']      = implode(',',$result_image) ;


        $data['user_id']    = $request->input('user_id');
        $data['model']      = $request->input('model');
        $data['topic']      = $request->input('topic');
        $data['content']    = $request->input('content');

        $result = DB::table('publish_news')->insert($data);

        if(!$result){
            return json_encode(['code'=>401,'message'=>'发布失败']);
        }else{
            return json_encode(['code'=>200,'message'=>'发布成功']);
        }
    }



    //获取评论的详细信息
    public function getCommentInfo( Request $request, CurlService $curlService ){
        $token              = $request->input('token');
        $result_token       = $curlService->getToken($token);
        if(!$result_token){
            return json_encode( [ 'message' => 'token错误','code'=>'401' ],JSON_UNESCAPED_UNICODE );
        }
        $per_page           = (int)$request->input( 'per_page', 10 );
        $page               = (int)$request->input( 'page', 1 );

        $result = DB::table('publish_news')
            ->select([
                'user.username',
                'user.head_img',
                'publish_news.user_id',
                'publish_news.image',
                'publish_news.content',
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
            ->offset($per_page * ( $page - 1 ))
            ->limit($per_page)
            ->get()->toArray();
        return json_encode( [ 'message' => 'success','code'=>'200', 'data'=>$result ],JSON_UNESCAPED_UNICODE );
    }
}
