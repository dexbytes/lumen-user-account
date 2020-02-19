<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;



class AppMask extends Model 
{
	protected $table = 'mask';
	protected $fillable = ['id', 'unique_id', 'build_id', 'mac_address'];

    
}
