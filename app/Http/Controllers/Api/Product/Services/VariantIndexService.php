<?php

namespace App\Http\Controllers\Api\Product\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VariantIndexService
{
    public function handle(Request $request): LengthAwarePaginator
    {
        return DB::table('variants')->join('products', 'products.id', '=', 'variants.product_id')->select('variants.*', 'products.name as product_name')->when($request->query('search'), fn ($query, $search) => $query->where(fn ($query) => $query->where('sku', 'like', "%$search%")->orWhere('products.name', 'like', "%$search%")))->orderBy('variants.id')->paginate(30);
    }
}
