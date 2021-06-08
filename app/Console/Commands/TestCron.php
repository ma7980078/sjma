<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;

class TestCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update-logo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
	    $this->moto_db = DB::connection( 'moto' );
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
	    $goods = $this->moto_db->table( 'brandGood' )->get()->toArray();
	
	    $good_ids = array_column( $goods, 'goodId' );
	    $goods    = array_column( $goods, null, 'goodId' );
	    $carList  = $this->moto_db->table( 'carList' )->whereIn( 'goodId', $good_ids )->get()->toArray();
	    $db       = $this->moto_db->table( 'car_image' );
	    $insert = [];
	    foreach ( $carList as $item ) {
		    
		    $insert[] = [
			    'cid'        => $item->carId,
			    'color'      => '默认',
			    'overview'   => $goods[$item->goodId]->goodLogo,
			    'created_at' => date( 'Y-m-d H:i:s' ),
			    'updated_at' => date( 'Y-m-d H:i:s' )
		    ];
		    
	    }
	    $db->insert( $insert );
	    dd(2);
    }
}
