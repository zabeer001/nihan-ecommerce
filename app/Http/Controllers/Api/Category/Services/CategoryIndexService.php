<?php

namespace App\Http\Controllers\Api\Category\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryIndexService
{
    public function handle(): Collection
    {
        return Category::orderBy('name')->get();
    }
}
