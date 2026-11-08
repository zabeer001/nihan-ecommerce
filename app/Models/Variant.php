<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Variant extends Model
{
    protected $guarded = [];

    protected $casts = ['attributes' => 'array', 'active' => 'boolean'];
}
