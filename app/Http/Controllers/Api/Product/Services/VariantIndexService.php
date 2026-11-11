<?php

namespace App\Http\Controllers\Api\Product\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VariantIndexService
{
    public function handle(Request $request, bool $forOrder = false): LengthAwarePaginator
    {
        $query = DB::table('variants')->join('products', 'products.id', '=', 'variants.product_id')
            ->when($request->query('search'), fn ($query, $search) => $query->where(
                fn ($query) => $query->where('sku', 'like', "%{$search}%")->orWhere('products.name', 'like', "%{$search}%")
            ))
            ->orderBy('variants.id');

        if ($forOrder) {
            $query->where('variants.active', true)->where('products.status', 'active')
                ->select('variants.id', 'variants.sku', 'variants.price_cents', 'variants.stock', 'variants.active');
        } else {
            $query->select('variants.*');
        }

        return $query->addSelect('products.name as product_name')->paginate(30);
    }
}
