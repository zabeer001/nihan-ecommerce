<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $guarded = [];

    protected $casts = ['primary' => 'boolean'];

    public function withUrl(): array
    {
        return $this->toArray() + ['url' => Storage::disk('public')->url($this->path)];
    }
}
