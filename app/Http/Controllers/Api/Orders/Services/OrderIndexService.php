<?php

namespace App\Http\Controllers\Api\Orders\Services;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderIndexService
{
    public function handle(): LengthAwarePaginator
    {
        return Order::orderByDesc('id')->paginate(20);
    }
}
