<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $guarded = [];

    protected $hidden = ['tracking_hash'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
