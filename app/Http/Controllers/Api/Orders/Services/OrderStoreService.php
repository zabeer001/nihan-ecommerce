<?php

namespace App\Http\Controllers\Api\Orders\Services;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderStoreService
{
    public function __construct(private readonly OrderWorkflowService $orderWorkflow) {}

    public function handle(Request $request): Order
    {
        $validated = $request->validate([
            'submission_key' => 'required|uuid',
            'customer_name' => 'required|string|max:255',
            'phone' => 'required|string|max:40',
            'email' => 'nullable|email|max:255',
            'address' => 'required|string|max:2000',
            'delivery_cents' => 'required|integer|min:0|max:100000000',
            'items' => 'required|array|min:1|max:100',
            'items.*.variant_id' => 'required|integer|distinct|exists:variants,id',
            'items.*.quantity' => 'required|integer|min:1|max:1000000',
        ]);

        return $this->orderWorkflow->create($validated, $request->user()->id);
    }
}
