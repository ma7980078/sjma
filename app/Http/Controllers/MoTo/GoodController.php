<?php

namespace App\Http\Controllers\MoTo;

use App\Http\Controllers\Controller;
use App\Models\Car;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Validator;

class GoodController extends Controller
{
	
	protected $moto_db;
	
	public function __construct()
	{
		$this->moto_db = DB::connection( 'mysql' );
	}
	
	public function good( Request $request, $good_id )
	{
//		$list = $this->moto_db->table( 'carList' )->leftJoin( 'car_image', 'carList.carId', '=', 'car_image.cid' )->where( 'goodId', $good_id )->get()->toArray();
		$list = $this->moto_db->table( 'carList' )->where( 'goodId', $good_id )->orderBy("carName","desc")->get()->toArray();

		$res = [];
		foreach ( $list as $item ) {
			if ( !isset( $res[$item->goodsCarName] ) ) {
				$res[$item->goodsCarName] = [
					'carId'          => $item->carId,
					'brandId'        => $item->brandId,
					'brandName'      => $item->brandName,
					'carName'        => $item->carName,
					'energyType'     => $item->energyType,
					'goodId'         => $item->goodId,
					'goodName'       => $item->goodName,
					'goodAbs'        => $item->goodAbs,
					'goodCbs'        => $item->goodCbs,
					'goodCoolDown'   => $item->goodCoolDown,
					'goodCylinder'   => $item->goodCylinder,
					'goodPrice'      => $item->goodPrice,
					'goodType'       => $item->goodType,
					'goodVolume'     => $item->goodVolume,
					'goodsCarName'   => $item->goodsCarName,
					'goodSaddleHigh' => $item->goodSaddleHigh,
					'saleStatus'     => $item->saleStatus,
					'carTypeName'    => $item->carTypeName
				];
			}
			
//			$image                               = [
//				'color'           => $item->color,
//				'overview_images' => !is_null( $item->overview ) ? $item->overview : '',
//				'details_images'  => !is_null( $item->details ) ? explode( ',', $item->details ) : [],
//			];
            $image                               = [];
			$res[$item->goodsCarName]['image'][] = $image;
		}
		
		return response( [
			'code'    => 200,
			'message' => 'success',
			'data'    => [
				'items' => array_values( $res ),
				'total' => count( $res ),
			]
		] );
	}
	
	public function updateCarLogo()
	{
		ini_set('date.timezone','Asia/Shanghai');
		$goods = $this->moto_db->table( 'brandGood' )->get()->toArray();
		
		$good_ids = array_column( $goods, 'goodId' );
		$goods    = array_column( $goods, null, 'goodId' );
		$carList  = $this->moto_db->table( 'carList' )->whereIn( 'goodId', $good_ids )->get()->toArray();
		$db       = $this->moto_db->table( 'car_image' );
		foreach ( $carList as $item ) {
			$details = $db->where( 'cid', $item->carId )->where( 'color', '默认' )->first();
			if ( !$details ) {
				$db->insert( [
					'cid'        => $item->carId,
					'color'      => '默认',
					'overview'   => $goods[$item->goodId]->goodLogo,
					'created_at' => date( 'Y-m-d H:i:s' ),
					'updated_at' => date( 'Y-m-d H:i:s' )
				] );
			} else {
				$db->update( [
					'cid'        => $item->carId,
					'color'      => '默认',
					'overview'   => $goods[$item->goodId]->goodLogo,
					'updated_at' => date( 'Y-m-d H:i:s' )
				] );
			}
			
		}
		dd(2);
	}
	
	private function hot_list( $type, $limit )
	{
		return $this->moto_db->table( 'hot_list' )->where( 'type', $type )
			->orderBy( 'total_count', 'desc' )
			->limit( $limit )->get()->toArray();
	}
}



