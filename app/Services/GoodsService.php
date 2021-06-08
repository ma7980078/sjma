<?php

namespace App\Services;


use DB;

class GoodsService
{
	protected $moto_db;
	
	public function __construct()
	{
		$this->moto_db = DB::connection( 'moto' );
	}
	
	public function getCatIdByName( $name )
	{
		return $this->moto_db->table( 'carList' )->where( 'goodsCarName', $name )->first( [ 'carId' ] );
	}
	
	public function getGoodIdByName( $name )
	{
		return $this->moto_db->table( 'brandGood' )->where( 'goodName', $name )->first( [ 'goodId' ] );
	}
}

