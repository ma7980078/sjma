<?php

namespace App\Http\Controllers\MoTo;

use App\Http\Controllers\Controller;
use App\Models\Car;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DB;

class CarsController extends Controller
{
	
	protected $moto_db;
	
	public function __construct()
	{
		$this->moto_db = DB::connection( 'moto' );
	}
	
	public function car_list()
	{
		$car_list = $this->moto_db->table( 'carList' )->get( [ 'carId', 'goodsCarName' ] )->toArray();
		
		$list = [];
		foreach ( $car_list as $item ) {
			$list[] = [
				'label' => $item->goodsCarName,
				'value' => $item->carId,
			];
		}
		
		return response( [
			'code'    => 200,
			'message' => 'success',
			'data'    => $list
		] );
	}
	
	public function car_image( Request $request )
	{
		$gid  = $request->input( 'gid', '' );
		$cid  = $request->input( 'cid', '' );
		$type = $request->input( 'type', '' );
		
		$db = $this->moto_db->table( 'car_image' );
		
		if ( $gid ) {
			$goods = $this->moto_db->table( 'carList' )->where( 'goodId', $gid )->get( [ 'carId', 'goodsCarName' ] )->toArray();
			$goods = array_column( $goods, null, 'carId' );
			$cids  = array_column( $goods, 'carId' );
			
		} else {
			$goods = $this->moto_db->table( 'carList' )->where( 'carId', $cid )->get( [ 'carId', 'goodsCarName' ] )->toArray();
			$goods = array_column( $goods, null, 'carId' );
			$cids  = [ $cid ];
		}
		$column = [ 'cid', 'color', 'overview', 'details', 'official' ];
		if ( $type != '' ) {
			$column = [ 'cid', 'color', $type ];
		}
		
		$car_list = $db->whereIn( 'cid', $cids )->get( $column )->toArray();
		
		foreach ( $car_list as $key => $item ) {
			if ( $type != '' ) {
				$car_list[$key] = [
					'name'   => $goods[$item->cid]->goodsCarName ?? '',
					'color'  => $item->color,
					'images' => !is_null( $item->$type ) && $item->$type != '' ? explode( ',', $item->$type ) : [],
				];
			} else {
				$car_list[$key] = [
					'name'            => $goods[$item->cid]->goodsCarName ?? '',
					'color'           => $item->color,
					'overview_images' => !is_null( $item->overview ) && $item->overview != '' ? explode( ',', $item->overview ) : '',
					'details_images'  => !is_null( $item->details ) && $item->details != '' ? explode( ',', $item->details ) : [],
					'official_images' => !is_null( $item->official ) && $item->official != '' ? explode( ',', $item->official ) : [],
				];
			}
			
		}
		
		
		return response( [
			'code'    => 200,
			'message' => 'success',
			'data'    => $car_list
		] );
	}
}



