<?php

namespace App\Http\Controllers\Api\Orders\Services;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderStatusService
{
    public function __construct(private readonly OrderWorkflowService $orderWorkflow) {}

    public function handle(Request $request, int $id): Order
    {
        $validated = $request->validate([
            'status' => 'required|in:Pending,Confirmed,Processing,Shipped,Delivered,Cancelled',
        ]);

        return $this->orderWorkflow->status($id, $validated['status'], $request->user()->id);
    }
}
