<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $guarded = [];

    protected $casts = ['active' => 'boolean', 'content_cards' => 'array', 'usage_guide' => 'array'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}
