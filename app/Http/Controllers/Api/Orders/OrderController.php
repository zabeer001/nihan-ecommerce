<?php

namespace App\Http\Controllers\Api\Orders;

use App\Http\Controllers\Api\Orders\Services\OrderIndexService;
use App\Http\Controllers\Api\Orders\Services\OrderShowService;
use App\Http\Controllers\Api\Orders\Services\OrderStatusService;
use App\Http\Controllers\Api\Orders\Services\OrderStoreService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function orders(OrderIndexService $service): LengthAwarePaginator
    {
        return $service->handle();
    }

    public function order(OrderShowService $service, int $id): array
    {
        return $service->handle($id);
    }

    public function createOrder(OrderStoreService $service, Request $request): Order
    {
        return $service->handle($request);
    }

    public function status(OrderStatusService $service, Request $request, int $id): Order
    {
        return $service->handle($request, $id);
    }
}
