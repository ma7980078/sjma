<?php

namespace App\Contracts;

Interface CurlContract
{
    public function send( $url,$method, $post_data = [] );

}
