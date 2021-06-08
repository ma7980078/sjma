<?php

namespace App\Http\Controllers\MoTo;

use App\Http\Controllers\Controller;
use App\Services\GoodsService;
use App\Services\ImageService;
use App\Services\UploadFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DB;

class UploadController extends Controller
{
	
	public function __construct()
	{
		$this->moto_db = DB::connection( 'moto' );
	}
	
	public function html()
	{
		return view( 'upload.index' );
	}
	
	public function file( Request $request, UploadFileService $uploadFileService )
	{
		
		$all_type = [ 'image/jpeg', 'image/gif', 'image/png' ];
		
		$file_type = $_FILES['file']["type"];
		
		$tmp_name = $_FILES['file']["tmp_name"];
		
		$file_name = $_FILES['file']["name"];
		
		$size       = $_FILES['file']["size"];
		
		$opera_type = $request->input( 'type' );
		
		if ( $_FILES['file']["error"] > 0 ) {
			return response( [
				'code'    => -1,
				'message' => '选择文件出错',
				'name'    => $_FILES['file']['name']
			] );
		}
		
		if ( !$uploadFileService->checkType( $file_type, $all_type ) ) {
			return response( [
				'code'    => -1,
				'message' => '文件类型错误',
				'name'    => $_FILES['file']['name']
			] );
		}
		
		$file_arr = explode( '.', $_FILES['file']['name'] );
		
		$ext = end( $file_arr );
		
		$ext = 'jpg';
		
		$name = substr( md5( $file_arr[0] . microtime() ), 0, 16 ) . "." . $ext;
		
		$path = "/image/good/" . date( 'Y/m/d' ) . "/";
		
		//图片上传，重命名
		$file_name = $uploadFileService->upload( $name, $tmp_name, public_path() . $path );
		
		if ( $opera_type == 'high' ) {
			$res = $this->saveGoodLogo( $path, $file_name, $file_arr[0] );
			
			$img     = new ImageService();
			
			$quality = strtolower( $ext ) == 'png' ? 0 : 100;
			$size = 270;
			$img->load( public_path() . $path . $file_name )
				->size( $size, $size )//设置生成图片的宽度和高度
				->fixed_given_size( true )//生成的图片是否以给定的宽度和高度为准
				->keep_ratio( true )//是否保持原图片的原比例
				->rotate( 0 )//指定旋转的角度
				->bg_color( "#ffffff" )//设置背景颜色，按照rgb格式
				->quality( $quality )//设置生成图片的质量 0-100，如果生成的图片格式为png格式，数字越大，压缩越大，如果是其他格式，如jpg，gif，数组越小，压缩越大
				->save( public_path() . $path . $file_name );    //保存生成图片的路径
			
			$watermark = array(
				"filename" => public_path() . $path . $file_name,	//水印文件
				"position" => 'center',	//水印的位置，分别为:center|top|left|bottom|right|top left|top right|bottom left|bottom right
				"opacity" => 1,	//水印的透明度，可以为0-1的任意数值，默认为1
				"x_offset" => 0,	//加水印的x轴偏移量，默认为0
				"y_offset" => 0,	//加水印的y轴偏移量，默认为0
				//"angle" => self::WATERMARK_DIAGONAL_NEG	//水印的旋转角度，可以为-360-360，如果为WATERMARK_DIAGONAL_POS或WATERMARK_DIAGONAL_NEG，则沿着生成图片的对角线旋转，默认为0
				"angle" => 0
			);

			$quality = strtolower( $ext ) == 'png' ? 0 : 100;
			$img->load( public_path() . '/good_logo_bg.jpg' )
				->size( 300, 300 )//设置生成图片的宽度和高度
				->fixed_given_size( true )//生成的图片是否以给定的宽度和高度为准
				->keep_ratio( true )//是否保持原图片的原比例
				//->bg_color( "#ffffff" )//设置背景颜色，按照rgb格式
				->set_watermark($watermark)
				->rotate( 0 )//指定旋转的角度
				->quality( $quality )//设置生成图片的质量 0-100，如果生成的图片格式为png格式，数字越大，压缩越大，如果是其他格式，如jpg，gif，数组越小，压缩越大
				->save( public_path() . $path . $file_name );    //保存生成图片的路径
		} else {
			$img     = new ImageService();
			$quality = strtolower( $ext ) == 'png' ? 0 : 100;
			$img->load( public_path() . $path . $file_name )
				->size( 1000, 500 )//设置生成图片的宽度和高度
				->fixed_given_size( true )//生成的图片是否以给定的宽度和高度为准
				->keep_ratio( true )//是否保持原图片的原比例
				->bg_color( "#ffffff" )//设置背景颜色，按照rgb格式
				->rotate( 0 )//指定旋转的角度
				->quality(100)//设置生成图片的质量 0-100，如果生成的图片格式为png格式，数字越大，压缩越大，如果是其他格式，如jpg，gif，数组越小，压缩越大
				->save( public_path() . $path . $file_name );    //保存生成图片的路径
			$res = $this->saveCarImage( $path, $file_name, $file_arr[0] );
		}
		
		$this->insertDB( [
			'type'     => $opera_type == 'high' ? 'logo' : 'detail',
			'filename' => $_FILES['file']['name'],
			'savename' => $file_name,
			'filepath' => $path,
			'filesize' => $size
		] );
		
		if ( !$res ) {
			return response( [
				'code'    => -1,
				'message' => '图片保存失败',
				'name'    => $_FILES['file']['name']
			] );
		}
		
		return response( [
			'code'    => 200,
			'message' => '上传成功',
			'name'    => $_FILES['file']['name']
		] );
	}
	
	public function insertDB( $data )
	{
		return $this->moto_db->table( 'upload_file' )->insert( $data );
	}
	
	private function saveGoodLogo( $path, $file_name, $file_info )
	{
		$good_service = new GoodsService();
		
		if ( strpos( $file_info, '_' ) !== false ) {
			$info = explode( '_', $file_info );
			
			$car_name = $info[0];
		} else {
			$car_name = $file_info;
		}
		
		$gid = $good_service->getGoodIdByName( $car_name )->goodId;
		
		if ( !$gid ) {
			return false;
		}
		$db = $this->moto_db->table( 'brandGood' );
		$db->where( 'goodId', $gid )->update( [
			'goodLogo' => $path . $file_name
		] );
		
		return true;
	}
	
	private function saveCarImage( $path, $file_name, $file_info )
	{
		$good_service = new GoodsService();
		
		$info = explode( '_', $file_info );
		
		if ( count( $info ) < 3 ) {
			return false;
		}
		
		$car_name = $info[0];
		
		$color = $info[1];
		
		switch ( $info[2] ) {
			case '全车':
				$update_column = 'overview';
				break;
			case '座椅':
				$update_column = 'details';
				break;
			default:
				$update_column = 'overview';
				break;
		}
		
		$cid = $good_service->getCatIdByName( $car_name )->carId;
		
		if ( !$cid ) {
			return false;
		}
		$db      = $this->moto_db->table( 'car_image' );
		$details = $db->where( 'cid', $cid )->where( 'color', $color )->first();
		if ( !$details ) {
			$db->insert( [
				'cid'          => $cid,
				'color'        => $color,
				$update_column => $path . $file_name,
				'created_at'   => date( 'Y-m-d H:i:s' ),
				'updated_at'   => date( 'Y-m-d H:i:s' )
			] );
		} else {
			$details                 = (array)$details;
			if ($update_column == 'overview') {
				$details[$update_column] = $path . $file_name;
			} else {
				$details[$update_column] .= ',' . $path . $file_name;
				
			}
			
			$db->update( [
				'cid'          => $cid,
				'color'        => $color,
				$update_column => $details[$update_column],
				'updated_at'   => date( 'Y-m-d H:i:s' )
			] );
		}
		
		return true;
	}
	
	
}



