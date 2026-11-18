<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    protected $guarded = [];
    protected $casts = ['rating' => 'integer'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function toPublicArray(): array
    {
        return $this->only(['id', 'author_name', 'rating', 'title', 'body', 'created_at']);
    }
}
