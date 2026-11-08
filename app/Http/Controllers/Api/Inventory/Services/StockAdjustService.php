<?php

namespace App\Http\Controllers\Api\Inventory\Services;

use App\Models\Variant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockAdjustService
{
    public function __construct(private readonly StockMovementService $stockMovements) {}

    public function handle(Request $request, int $id): Variant
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|not_in:0|min:-1000000|max:1000000',
            'reason' => 'required|string|max:255',
        ]);

        return DB::transaction(function () use ($validated, $id, $request) {
            $variant = Variant::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->stockMovements->record($variant, $validated['quantity'], $validated['reason'], $request->user()->id);

            return $variant;
        });
    }
}
