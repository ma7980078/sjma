<?php

namespace App\Http\Controllers\MoTo;

require storage_path('GeoIp2/vendor/autoload.php');

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Validator;
use GeoIp2\Database\Reader;

class ShopController extends Controller
{
	
	protected $moto_db;
	
	public function __construct()
	{
		$this->moto_db = DB::connection( 'moto' );
	}
	
	public function list( Request $request )
	{
		$input     = $request->all();
		$validator = Validator::make( $input, [
			'per_page'  => 'nullable',
			'page'      => 'nullable|integer|min:1',
		] );
		
		if ( $validator->fails() ) {
			return response( [ 'errors' => $validator->errors() ], 422 );
		}
		
		$reader = new Reader(storage_path('GeoIp2/GeoLite2-City_20200714/GeoLite2-City.mmdb'));
		
		$record = $reader->city($_SERVER['HTTP_X_REAL_IP']);
		
		$client_lon = isset($record->location->longitude) ? $record->location->longitude : '';
		$client_lat = isset($record->location->latitude) ? $record->location->latitude : '';
		
		$list = $this->moto_db->table( 'shop' );
		
		if (isset($input['keyword'])) {
			$list = $list->where(function ($query) use ($request){
				$query->orWhere('shopName', 'like', "%{$request->keyword}%")
					->orWhere('province', 'like', "%{$request->keyword}%")
					->orWhere('city', 'like', "%{$request->keyword}%")
					->orWhere('district', 'like', "%{$request->keyword}%")
					->orWhere('addr', 'like', "%{$request->keyword}%");
			});
		}
		
		if (isset($input['city'])) {
			$list = $list->where('city', 'like', "%{$input['city']}%");
		}
		
//		if (isset($input['brandId'])) {
//			$shop_ids = $this->moto_db->table( 'brand_shop' )->where('brandId', $input['brandId'])->get(['shopId'])->toArray();
//			$shop_ids = array_column($shop_ids, 'shopId');
//			$list = $list->whereIn('shopId', $shop_ids);
//		}
		
		$total = clone $list;
		$list  = $list->skip( ( $input['page'] - 1 ) * $input['per_page'] )
			->take( $input['per_page'] )
			->get()->toArray();
		
		foreach ( $list as $key => $item ) {
			$list[$key]->distance = $this->distance( $client_lat, $client_lon, $item->latitude, $item->longitude );
		}
		
		return response( [
			'code'    => 200,
			'message' => 'success',
			'data'    => [
				'items' => $list,
				'total' => $total->count()
			]
		] );
		
	}
	
	private function distance( $lat1, $lon1, $lat2, $lon2 )
	{
		$R    = 6371393; //地球平均半径,单位M
		$dlat = deg2rad( $lat2 - $lat1 );
		$dlon = deg2rad( $lon2 - $lon1 );
		$a    = pow( sin( $dlat / 2 ), 2 ) + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * pow( sin( $dlon / 2 ), 2 );
		$c    = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
		$d    = $R * $c / 1000; //单位：KM
		return intval( $d );
	}
}



