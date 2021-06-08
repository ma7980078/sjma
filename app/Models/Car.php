<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Car extends Model
{
	protected $connection = 'moto';
	
	protected $table = "carList";
	
}