<?php

namespace App\Services;


class UploadFileService
{
	public function __construct()
	{
	
	}
	
	public function checkType( $type = 'jpeg', $type_list = [] )
	{
		if ( empty( $type_list ) ) {
			return true;
		}
		
		if ( !in_array( $type, $type_list ) ) {
			return false;
		}
		
		return true;
	}
	
	public function checkSize( $size = 0, $max_size = 2 )
	{
		if ( $size == 0 ) {
			return false;
		}
		
		if ( $size > $max_size ) {
			return false;
		}
		
		return true;
	}
	
	public function checkImage( $tmp_name, $image_type_list )
	{
		//验证图片真实性
		if ( !function_exists( 'exif_imagetype' ) ) {
			list( $width, $height, $type2, $attr ) = getimagesize( $tmp_name );
			$type = $type2;
		} else {
			$type = null;
			try {
				$type = exif_imagetype( $tmp_name );
			} catch ( \Exception $ex ) {
				return false;
			}
		}
		
		if ( !in_array( $type, $image_type_list ) ) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * 检查上传文件内容和文件后缀
	 *
	 * @param $file
	 * @param $image_type_list
	 * @param $fileExtensions
	 *
	 * @return boolean
	 */
	public function checkImageFileContent( $file, $image_type_list, $fileExtensions = [
		'.png',
		'.jpeg',
		'.jpg',
		'.gif',
	] )
	{
		
		if ( !in_array( strstr( $file['name'], "." ), $fileExtensions ) ) {
			return false;
		}
		
		$filename = $file['tmp_name'];
		//获取上传文件类型
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		
		$mimetype = finfo_file( $finfo, $filename );
		
		finfo_close( $finfo );
		
		if ( !in_array( $mimetype, $image_type_list ) ) {
			
			return false;
		}
		
		$fileContent = file_get_contents( $file['tmp_name'] );
		
		if ( strpos( $fileContent, "<?php" ) !== false ) {
			
			return false;
		}
		
		return true;
	}
	
	public function upload( $filename, $tmp_name, $path )
	{
		
		$fileExtensions = [
			'.png',
			'.jpeg',
			'.jpg',
			'.gif',
		];
		
		if ( !in_array( strtolower( strstr( $filename, "." ) ), $fileExtensions ) ) {
			exit();
		}
		
		$file_path = $path . $filename;
		// 判断当前的目录是否存在，若不存在就新建一个!
		if ( !is_dir( $path ) ) {
			mkdir( $path, 0777, true );
			
		}
		
		if ( !file_exists( $file_path ) ) {
			//此函数只支持 HTTP POST 上传的文件
			move_uploaded_file( $tmp_name, $file_path );
		}
		
		return $filename;
	}
}

