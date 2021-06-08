<?php
Route::group( [
	'middleware' => [ ],
	'prefix'     => 'moto'
], function () {
	Route::get( '/upload/html', 'MoTo\UploadController@html' )->name('moto.upload.html');
	Route::post( '/upload/file', 'MoTo\UploadController@file' )->name('moto.upload.image');
	Route::post( '/brand', 'MoTo\BrandController@brand' )->name('moto.brand');
	Route::post( '/brand_good', 'MoTo\BrandController@brand_good' )->name('moto.brand_good');
	Route::post( '/good/{good_id}', 'MoTo\GoodController@good' )->name('moto.good');
	Route::post( '/update_car_logo', 'MoTo\GoodController@updateCarLogo' )->name('moto.updateCarLogo');

	
	Route::post( '/shop/list', 'MoTo\ShopController@list' )->name('moto.shop.list');
});


