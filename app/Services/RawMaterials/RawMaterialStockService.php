<?php

namespace App\Services\RawMaterials;

use App\Models\RawMaterial;
use App\Models\RawMaterialAllocation;
use App\Models\RawMaterialPurchase;
use App\Models\RawMaterialPurchaseItem;
use App\Models\RawMaterialStockLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class RawMaterialStockService
{
    public function availableQuantity(int $rawMaterialId): float
    {
        $totals = RawMaterialStockLedger::query()
            ->where('raw_material_id', $rawMaterialId)
            ->selectRaw('COALESCE(SUM(quantity_in), 0) as quantity_in, COALESCE(SUM(quantity_out), 0) as quantity_out')
            ->first();

        return (float) $totals->quantity_in - (float) $totals->quantity_out;
    }

    public function recalculatePurchaseCosts(RawMaterialPurchase $purchase): void
    {
        $purchase->load(['items']);

        $extraCost = (float) $purchase->tax_amount
            + (float) $purchase->shipping_cost
            + (float) $purchase->customs_cost
            + (float) $purchase->other_cost
            + (float) $purchase->lc_charges;

        $purchase->forceFill([
            'subtotal' => 0,
            'grand_total' => round($extraCost, 2),
        ])->save();
    }

    public function syncPurchaseReceipt(RawMaterialPurchaseItem $item, ?int $userId = null): void
    {
        RawMaterialStockLedger::query()
            ->where('reference_type', RawMaterialPurchaseItem::class)
            ->where('reference_id', $item->id)
            ->where('movement_type', 'purchase_received')
            ->delete();

        if ((float) $item->received_quantity <= 0) {
            return;
        }

        $this->recordMovement(
            rawMaterialId: $item->raw_material_id,
            movementType: 'purchase_received',
            quantityIn: (float) $item->received_quantity,
            quantityOut: 0,
            unitCost: 0,
            reference: $item,
            userId: $userId,
            remarks: 'Purchase receipt'
        );
    }

    public function reserveAllocation(RawMaterialAllocation $allocation, ?int $userId = null): void
    {
        if ($this->availableQuantity($allocation->raw_material_id) < (float) $allocation->allocated_quantity) {
            $material = RawMaterial::find($allocation->raw_material_id);

            throw ValidationException::withMessages([
                'allocated_quantity' => 'Not enough stock available for '.($material?->name ?? 'this material').'.',
            ]);
        }

        $this->recordMovement(
            rawMaterialId: $allocation->raw_material_id,
            movementType: 'allocation_reserved',
            quantityIn: 0,
            quantityOut: (float) $allocation->allocated_quantity,
            unitCost: $this->allocationUnitCost($allocation),
            reference: $allocation,
            userId: $userId,
            remarks: 'Reserved for order allocation'
        );
    }

    public function syncAllocationReturn(RawMaterialAllocation $allocation, float $previousReturned, ?int $userId = null): void
    {
        $delta = (float) $allocation->returned_quantity - $previousReturned;

        if ($delta <= 0) {
            return;
        }

        $this->recordMovement(
            rawMaterialId: $allocation->raw_material_id,
            movementType: 'allocation_returned',
            quantityIn: $delta,
            quantityOut: 0,
            unitCost: $this->allocationUnitCost($allocation),
            reference: $allocation,
            userId: $userId,
            remarks: 'Unused material returned to stock'
        );
    }

    private function allocationUnitCost(RawMaterialAllocation $allocation): float
    {
        if ($allocation->purchaseItem) {
            return 0;
        }

        if ((float) $allocation->allocated_quantity > 0 && (float) $allocation->estimated_cost > 0) {
            return (float) $allocation->estimated_cost / (float) $allocation->allocated_quantity;
        }

        return 0;
    }

    private function recordMovement(
        int $rawMaterialId,
        string $movementType,
        float $quantityIn,
        float $quantityOut,
        float $unitCost,
        Model $reference,
        ?int $userId,
        ?string $remarks = null
    ): RawMaterialStockLedger {
        return RawMaterialStockLedger::create([
            'raw_material_id' => $rawMaterialId,
            'movement_type' => $movementType,
            'quantity_in' => $quantityIn,
            'quantity_out' => $quantityOut,
            'unit_cost' => $unitCost,
            'reference_type' => $reference::class,
            'reference_id' => $reference->getKey(),
            'movement_date' => now()->toDateString(),
            'remarks' => $remarks,
            'created_by' => $userId,
        ]);
    }
}
