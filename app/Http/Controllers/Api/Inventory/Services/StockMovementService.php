<?php

namespace App\Http\Controllers\Api\Inventory\Services;

use App\Models\Variant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    // Call within a transaction after locking the variant row.
    public function record(Variant $variant, int $quantity, string $reason, int $userId, ?int $orderId = null): void
    {
        if ($variant->stock + $quantity < 0) {
            throw ValidationException::withMessages(['stock' => "Insufficient stock for {$variant->sku}."]);
        }
        $variant->stock += $quantity;
        $variant->save();
        DB::table('stock_movements')->insert([
            'variant_id' => $variant->id,
            'user_id' => $userId,
            'order_id' => $orderId,
            'quantity' => $quantity,
            'balance' => $variant->stock,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
