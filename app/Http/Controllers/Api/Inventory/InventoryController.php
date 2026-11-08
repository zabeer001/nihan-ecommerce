<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Api\Inventory\Services\StockAdjustService;
use App\Http\Controllers\Api\Inventory\Services\StockHistoryService;
use App\Http\Controllers\Controller;
use App\Models\Variant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function adjust(StockAdjustService $service, Request $request, int $id): Variant
    {
        return $service->handle($request, $id);
    }

    public function movements(StockHistoryService $service, int $id): LengthAwarePaginator
    {
        return $service->handle($id);
    }
}
