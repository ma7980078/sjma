<?php
Route::group( [
	'middleware' => [ ],
	'prefix'     => 'moto'
], function () {
	Route::get( '/upload/html', 'MoTo\UploadController@html' )->name('moto.upload.html');
	Route::post( '/upload/file', 'MoTo\UploadController@file' )->name('moto.upload.image');
	Route::get( '/upload/good_logo_v2', 'MoTo\UploadController@good_logo_v2' )->name('moto.upload.good_logo_v2');
	Route::post( '/upload/file_v2', 'MoTo\UploadController@file_v2' )->name('moto.upload.file_v2');
	Route::post( '/brand', 'MoTo\BrandController@brand' )->name('moto.brand');
	Route::post( '/brand_good', 'MoTo\BrandController@brand_good' )->name('moto.brand_good');
	Route::post( '/good/{good_id}', 'MoTo\GoodController@good' )->name('moto.good');
	Route::post( '/update_car_logo', 'MoTo\GoodController@updateCarLogo' )->name('moto.updateCarLogo');
	
	Route::get( '/upload/detail_html', 'MoTo\UploadController@detail_html' )->name('moto.upload.detail_html');
	Route::post( '/upload/detail_img', 'MoTo\UploadController@detail_img' )->name('moto.upload.detail_img');
	
	Route::get( '/cars/list', 'MoTo\CarsController@car_list' )->name('moto.cars.car_list');
	Route::post( '/cars/image', 'MoTo\CarsController@car_image' )->name('moto.cars.car_image');
	
	Route::match( [ 'GET', 'POST' ],'/shop/list', 'MoTo\ShopController@list' )->name('moto.shop.list');
});


