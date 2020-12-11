<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\CurlService;

class CurlServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //

    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //将Contract接口和它的实现类绑定
//        $this->app->bind('App\Contracts\CurlContract','App\Services\CurlService');
        $this->app->bind( 'App\Contracts\CurlContract', function () {
            return new CurlService();
        } );
    }
}
