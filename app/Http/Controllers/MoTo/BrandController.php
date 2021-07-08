<?php

namespace App\Http\Controllers\MoTo;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Validator;

class BrandController extends Controller
{
	
	protected $moto_db;
	
	public function __construct()
	{
		$this->moto_db = DB::connection( 'mysql' );
	}
	
	public function brand( Request $request )
	{

        $input = $request->all();

        $brand_list = $this->moto_db->table( 'brand' )->where( 'show', 1 );

		if ( @$input['keyword'] ) {
			$brand_list = $brand_list->where( 'keywords', 'like', "%{$input['keyword']}%" );
		}
		
		if ( @$input['brandEnergyType'] ) {
			$brand_list = $brand_list->where( 'brandEnergyType', $input['brandEnergyType'] );
		}
		
		$brand_list = $brand_list
			->get( [ 'brandId', 'aleph', 'brandName', 'brandLogo', 'brandEnergyType' ] )->toArray();
		/*$hot_list = $this->hot_list( 1, 10 );
		
		if ( count( $hot_list ) == 0 ) {
			$hot_list = array_slice( $brand_list, 0, 10 );
			$hot_id   = array_column( $hot_list, 'brandId' );
		} else {
			$hot_id = array_column( $hot_list, 'pid' );
		}*/
		return response( [
			'code'    => 200,
			'message' => 'success',
			'data'    => [
				'brand_list' => $brand_list,
				//'hot_list'   => $hot_id
			]
		] );
	}
	
	public function brand_good( Request $request )
	{
		$input = $request->all();
		
		$validator = Validator::make( $input, [
			'brandId' => 'required',
		] );
		
		if ( $validator->fails() ) {
			return response( [ 'errors' => $validator->errors() ], 422 );
		}
		
		$list = $this->moto_db->table( 'brandGood' );
		
		$c_list = clone $list;
		//能源类型筛选
		if ( isset( $input['energyType'] ) ) {
			$list = $list->where( 'energyType', $input['energyType'] );
		}
		//车辆类型筛选
		if ( isset( $input['series'] ) ) {
			if ( strpos( $input['series'], '跑车' ) !== false ) {
				$list = $list->where( 'seriesName', 'like', "%{$input['series']}%" );
			} else {
				$list = $list->where( 'seriesName', 'not like', "%跑车%" );
			}
		}
		//价格筛选
		if ( isset( $input['price'] ) && count( $input['price'] ) > 0 ) {
			foreach ( $input['price'] as $item ) {
				$list = $list->where( 'minPrice', $item['symbol'], $item['price'] );
			}
		}
		
		//排量
		if ( isset( $input['volume'] ) ) {
			foreach ( $input['volume'] as $item ) {
				$list = $list->where( 'goodVolumeDecimal', $item['symbol'], $item['volume'] );
			}
		}
		
		//气缸
		if ( isset( $input['cylinder'] ) ) {
			$list = $list->where( 'goodCylinder', $input['cylinder'] );
			
		}
		//冷却方式
		if ( isset( $input['cool_down'] ) ) {
			$list = $list->where( 'goodCoolDown', $input['cool_down'] );
			
		}
		//abs
		if ( isset( $input['abs'] ) ) {
			$list = $list->where( 'goodAbs', $input['abs'] );
			
		}
		$list = $list->where( 'brandId', $input['brandId'] )
			->get()->toArray();
		
		//$hot_list = $this->hot_list( 2, 10 );
		
		/*if ( count( $hot_list ) == 0 ) {
			$hot_list = array_slice( $c_list->get()->toArray(), 0, 10 );
			$hot_id   = array_column( $hot_list, 'goodId' );
		} else {
			$hot_id = array_column( $hot_list, 'pid' );
		}*/
		
		$brandCulture = $this->moto_db->table( 'brand' )->where( 'brandId', $input['brandId'] )->first( [ 'brandCulture' ] );
		
		return response( [
			'code'    => 200,
			'message' => 'success',
			'data'    => [
				'brand_culture' => $brandCulture->brandCulture,
				'good_list'     => $list,
				//'hot_list'      => $hot_id
			]
		] );
	}
	
	private function hot_list( $type, $limit )
	{
		return $this->moto_db->table( 'hot_list' )->where( 'type', $type )
			->orderBy( 'total_count', 'desc' )
			->limit( $limit )->get()->toArray();
	}
}



