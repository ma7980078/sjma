<?php

namespace App\Services;

use App\Contracts\CurlContract;
use Illuminate\Support\Facades\DB;

class CurlService implements CurlContract
{
    public function send( $url,$method,$header=[],$post_data = [],$param = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if(!empty($header)){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        if(isset($param['username'])){
            curl_setopt($ch, CURLOPT_POSTFIELDS,  json_encode($post_data));
            curl_setopt($ch, CURLOPT_USERPWD, $param['username']);
        }else{
            curl_setopt($ch, CURLOPT_POSTFIELDS,  json_encode([$post_data]));
        }
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 60000);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 60000);
        $content = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//        var_dump($code);die;

        if(!$code){
            echo 'Curl error: ' . curl_error($ch);die;
        }
        curl_close($ch);
        return $content;

    }
    public function send_img( $url,$method,$header=[],$post_data = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if(!empty($header)){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS,  http_build_query($post_data));
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 60000);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 60000);
        $content = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//        var_dump($code);die;

        if(!$code){
            echo 'Curl error: ' . curl_error($ch);die;
        }
        curl_close($ch);
        return $content;

    }
//    public function send( $url,$method,$header=[],$post_data = [],$param = [] )
//    {
//
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, $url);
//        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//        if ($method == 'POST') {
//            curl_setopt($ch, CURLOPT_POST, 1);
//            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([$post_data]));
//            if(isset($param['username'])){
//
//                curl_setopt($ch, CURLOPT_USERPWD, $param['username']);
//            }
//        }
//        if ($method == 'PUT') {
//            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
//            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
//        }
//
//        $output = curl_exec($ch);
//        if(!$output){
//            echo 'Curl error: ' . curl_error($ch);die;
//        }
//        curl_close($ch);
//        return $output;
//
//    }
    public function getToken($token){
        $token = DB::table('user')->where(['token'=>$token])->first();
        return $token;
    }

    /**
     * curl批量发送
     *
     * @param $params array ['url','params','headers','method']
     * @param array $options params 优先级最高
     * @return array|mixed
     */
    public function multiSend( $params, $options = [] )
    {
        $default = [
            'headers'         => [
                "Accept: application/json",
                "Content-Type: text/json"
            ],
            'method'          => 'POST',
            'CURLOPT_TIMEOUT' => 10
        ];

        $options += $default;

        //创建一个CURL批处理句柄
        $mh = curl_multi_init();

        $curl_array = [];
        foreach ( $params as $key => $param ) {
            /******创建一个CURL批处理句柄*******/
            $ch = curl_init();//初始化一个curl句柄
            $url = $param['url'];
            $data = $param['params'];
            $options = $param + $options;

            $param['curl_command'] = vsprintf( 'curl -H "%s" -X POST -d \'%s\' %s', [ implode( ';', $options['headers'] ), json_encode( $param['params'] ), $param['url'] ] );

            ////////////////////////
            /// 请求体
            if ( !empty( $data ) ) {
                if ( is_array( $data ) ) {
                    if ( strpos( $options['headers'][0], 'json' ) !== false ) {
                        curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );
                    } else {
                        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
                    }
                } else {
                    curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
                }
            }

            curl_setopt( $ch, CURLOPT_URL, $url ); //请求的URL
            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, strtoupper( $options['method'] ) );//请求方式
            curl_setopt( $ch, CURLOPT_HTTPHEADER, $options['headers'] ); //请求头设置
            curl_setopt( $ch, CURLOPT_TIMEOUT, $options['CURLOPT_TIMEOUT'] );//超时设置
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true ); // return don't print
            /******创建一个CURL批处理句柄 end*******/

            curl_multi_add_handle( $mh, $ch ); // 把curl资源放入curl句柄中
            $curl_array[(string)$ch] = [
                'req_params' => $param,
                'handle'     => $ch
            ];
        }
        $running = NULL;//用来判断操作是否仍在执行的标识的引用
        do {
            usleep( 10000 );
            curl_multi_exec( $mh, $running );
        } while ( $running > 0 );

        $arr = [];
        // 循环获取内容
        foreach ( $curl_array as $key => $val ) {
            $arr[] = [
                'req_params' => $val['req_params'],
                'response'   => curl_multi_getcontent( $val["handle"] ),
                'total_time' => curl_getinfo( $val["handle"] )['total_time']
            ];
            curl_multi_remove_handle( $mh, $val["handle"] ); #移除curl句柄
        }
        curl_multi_close( $mh ); #关闭curl_multi句柄
        return $arr;
    }
}
