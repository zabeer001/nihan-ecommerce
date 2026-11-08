<?php

namespace App\Http\Controllers\Api\Inventory\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StockHistoryService
{
    public function handle(int $id): LengthAwarePaginator
    {
        return DB::table('stock_movements')->where('variant_id', $id)->orderByDesc('id')->paginate(30);
    }
}
