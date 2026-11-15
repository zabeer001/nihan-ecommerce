<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    protected $hidden = ['tracking_hash'];
}
