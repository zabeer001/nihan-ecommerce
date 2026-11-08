<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\DeliveryChallan;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\RawMaterial;
use App\Models\RawMaterialPurchase;
use App\Models\RawMaterialPurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class OrderDemoSeeder extends Seeder
{
    public function run(): void
    {
        $today = CarbonImmutable::today();
        $date = static fn (int $daysAgo): string => $today
            ->subDays(min($daysAgo, $today->day - 1))
            ->toDateString();
        $dates = [
            'order_101' => $date(12),
            'order_102' => $date(11),
            'pi' => $date(10),
            'local_purchase' => $date(9),
            'import_purchase' => $date(8),
            'lc_opening' => $date(7),
            'shipment' => $date(7),
            'arrival' => $date(6),
            'challan_001' => $date(5),
            'challan_002' => $date(3),
            'challan_003' => $date(1),
            'challan_004' => $date(0),
            'invoice' => $date(0),
            'payment' => $date(0),
            'delivery' => $today->addDays(7)->toDateString(),
            'expiry' => $today->addMonth()->toDateString(),
        ];
        $admin = User::query()->where('email', 'admin@gmail.com')->first();

        $customer = Customer::query()->updateOrCreate(
            ['email' => 'accounts@abc-garments.test'],
            [
                'name' => 'ABC Garments Ltd',
                'address' => "House 12, Road 8\nBanani, Dhaka 1213\nBangladesh",
                'delivery_address' => "ABC Garments Factory\nPlot 45, Industrial Zone\nGazipur, Bangladesh",
                'shipping_address' => "ABC Garments Factory\nPlot 45, Industrial Zone\nGazipur, Bangladesh",
                'mobile' => '+8801716481738',
                'email' => 'accounts@abc-garments.test',
                'bin_vat_number' => 'BIN-ABC-1001',
                'contact_person' => 'Mr. Rahman',
                'payment_type' => 'lc_90',
                'payment_currency' => 'USD',
                'payment_terms' => '<p><strong>LC 90 days</strong> from bill of lading date.</p><ul><li>All bank charges outside Bangladesh are buyer account.</li><li>Payment documents must reference PI and invoice numbers.</li><li>Partial shipment is allowed with prior approval.</li></ul>',
                'notes' => "Demo customer for order history.\nBill to: ABC Garments Ltd.\nShip to: ABC Garments Factory, Gazipur.",
            ]
        );

        $order101 = Order::query()->updateOrCreate(
            ['order_number' => 'ORD-101'],
            [
                'customer_id' => $customer->id,
                'production_order_number' => 'PO-101',
                'order_date' => $dates['order_101'],
                'delivery_date' => $dates['delivery'],
                'po_number' => 'BUYER-PO-101',
                'work_order_number' => 'WO-101',
                'reference_number' => 'REF-101',
                'file_reference' => 'FILE-101',
                'currency' => 'USD',
                'payment_type' => 'lc_90',
                'ordered_qty' => 10000,
                'pp_qty' => 10000,
                'yarn_qty' => 0,
                'material_amount' => 6200,
                'total_amount' => 6200,
                'status' => 'processing',
                'delivery_status' => 'delivered',
                'remarks' => 'Demo order with full operational history.',
                'created_by' => $admin?->id,
            ]
        );

        $zipperItem = $this->seedOrderItem($order101, 'ZIP-NYL-STD-BLK', 'Nylon Zipper', 10000, 0.62, 'Nylon zipper', 'Standard');
        $this->pruneDemoOrderItems($order101, [$zipperItem->id]);

        $order102 = Order::query()->updateOrCreate(
            ['order_number' => 'ORD-102'],
            [
                'customer_id' => $customer->id,
                'production_order_number' => 'PO-102',
                'order_date' => $dates['order_102'],
                'delivery_date' => $dates['delivery'],
                'po_number' => 'BUYER-PO-102',
                'work_order_number' => 'WO-102',
                'reference_number' => 'REF-102',
                'file_reference' => 'FILE-102',
                'currency' => 'USD',
                'payment_type' => 'lc_90',
                'ordered_qty' => 8000,
                'pp_qty' => 8000,
                'yarn_qty' => 0,
                'material_amount' => 800,
                'total_amount' => 800,
                'status' => 'processing',
                'delivery_status' => 'partial',
                'remarks' => 'Second demo order linked to the same invoice.',
                'created_by' => $admin?->id,
            ]
        );

        $order102Item = $this->seedOrderItem($order102, 'LCK-REG-BLK', 'Locket', 8000, 0.10, 'Locket', 'Regular');
        $this->pruneDemoOrderItems($order102, [$order102Item->id]);

        $suppliers = $this->seedSuppliers();
        $rawMaterials = $this->seedRawMaterials();
        $this->seedRawMaterialPurchases($order101, $order102, $suppliers, $rawMaterials, $admin?->id, $dates);

        $pi001 = ProformaInvoice::query()->updateOrCreate(
            ['order_id' => $order101->id],
            [
                'customer_id' => $customer->id,
                'pi_number' => 'PI-001',
                'pi_date' => $dates['pi'],
                'pi_expire_date' => $dates['expiry'],
                'delivery_date' => $dates['delivery'],
                'currency' => 'USD',
                'payment_type' => 'lc_90',
                'brand' => 'CELIO',
                'coordinator' => 'Almas',
                'shipment_method' => 'By truck',
                'shipping_terms' => 'Ex-Factory',
                'country_of_origin' => 'Bangladesh',
                'subtotal' => 7000,
                'bank_document_charge' => 0,
                'freight_courier_charge' => 0,
                'grand_total' => 7000,
                'total_weight' => 791.42,
                'amount_in_words' => 'Seven thousand dollars only',
                'remarks' => 'Demo multi-order PI with customer, shipment, and order summary data.',
                'terms_text' => 'Goods are supplied under buyer-approved PI terms.',
                'status' => 'approved',
                'created_by' => $admin?->id,
            ]
        );
        $pi001->orders()->sync([$order101->id, $order102->id]);
        $this->seedProformaInvoiceItem($pi001, $zipperItem);
        $this->seedProformaInvoiceItem($pi001, $order102Item);

        ProformaInvoice::query()
            ->where('pi_number', 'PI-002')
            ->get()
            ->each(function (ProformaInvoice $proformaInvoice): void {
                $proformaInvoice->items()->delete();
                $proformaInvoice->orders()->detach();
                $proformaInvoice->delete();
            });

        foreach ([
            ['number' => 'CH-001', 'date' => $dates['challan_001'], 'qty' => 3000],
            ['number' => 'CH-002', 'date' => $dates['challan_002'], 'qty' => 4000],
            ['number' => 'CH-003', 'date' => $dates['challan_003'], 'qty' => 3000],
        ] as $challanData) {
            $challan = DeliveryChallan::query()->updateOrCreate(
                ['challan_number' => $challanData['number']],
                [
                    'customer_id' => $customer->id,
                    'order_id' => $order101->id,
                    'proforma_invoice_id' => $pi001->id,
                    'challan_date' => $challanData['date'],
                    'delivery_qty' => $challanData['qty'],
                    'delivery_unit' => 'PC',
                    'delivery_person_name' => 'Delivery Team',
                    'package_info' => $challanData['qty'].' pcs',
                    'status' => 'delivered',
                    'created_by' => $admin?->id,
                ]
            );

            $this->seedDeliveryVariantLine($challan, $zipperItem, $challanData['qty']);
        }

        $challan004 = DeliveryChallan::query()->updateOrCreate(
            ['challan_number' => 'CH-004'],
            [
                'customer_id' => $customer->id,
                'order_id' => $order102->id,
                'proforma_invoice_id' => $pi001->id,
                'challan_date' => $dates['challan_004'],
                'delivery_qty' => 5000,
                'delivery_unit' => 'PC',
                'delivery_person_name' => 'Delivery Team',
                'package_info' => '5000 pcs',
                'status' => 'delivered',
                'created_by' => $admin?->id,
            ]
        );
        $this->seedDeliveryVariantLine($challan004, $order102Item, 5000);

        $invoice = Invoice::query()->updateOrCreate(
            ['invoice_number' => 'INV-001'],
            [
                'customer_id' => $customer->id,
                'proforma_invoice_id' => $pi001->id,
                'document_type' => 'invoice',
                'invoice_date' => $dates['invoice'],
                'currency' => 'USD',
                'payment_type' => 'lc_90',
                'subtotal' => 7000,
                'total_amount' => 7000,
                'payable_amount' => 7000,
                'amount_in_words' => 'Seven thousand dollars only',
                'payment_status' => 'paid',
                'status' => 'issued',
                'created_by' => $admin?->id,
            ]
        );

        $order101->update(['invoice_id' => $invoice->id]);
        $order102->update(['invoice_id' => $invoice->id]);
        $invoice->orders()->sync([$order101->id, $order102->id]);

        $this->seedInvoiceItem($invoice, $zipperItem);
        $this->seedInvoiceItem($invoice, $order102Item);

        foreach ([
            ['reference' => 'PAY-001', 'date' => $dates['payment'], 'amount' => 3000, 'note' => 'First partial payment.'],
            ['reference' => 'PAY-002', 'date' => $dates['payment'], 'amount' => 2000, 'note' => 'Second partial payment.'],
            ['reference' => 'PAY-003', 'date' => $dates['payment'], 'amount' => 2000, 'note' => 'Final payment.'],
        ] as $paymentData) {
            Payment::query()->updateOrCreate(
                [
                    'invoice_id' => $invoice->id,
                    'reference_number' => $paymentData['reference'],
                ],
                [
                    'payment_date' => $paymentData['date'],
                    'proforma_invoice_id' => $invoice->proforma_invoice_id,
                    'paid_amount' => $paymentData['amount'],
                    'payment_method' => 'bank_transfer',
                    'payment_note' => $paymentData['note'],
                    'created_by' => $admin?->id,
                ]
            );
        }

        $pi001->forceFill([
            'paid_total' => 7000,
            'due_total' => 0,
        ])->save();

        $order101->forceFill(['due_amount' => 0])->save();
        $order102->forceFill(['due_amount' => 0])->save();
    }

    /**
     * @return array<string, Supplier>
     */
    private function seedSuppliers(): array
    {
        return [
            'local' => Supplier::query()->updateOrCreate(
                ['email' => 'sales@dhaka-trims.test'],
                [
                    'company_name' => 'Dhaka Trims Supply',
                    'contact_person' => 'Mr. Kamal',
                    'mobile' => '+8801711111111',
                    'country' => 'Bangladesh',
                    'supplier_type' => 'local',
                    'address' => 'Tejgaon Industrial Area, Dhaka',
                    'notes' => 'Local supplier for demo raw material purchases.',
                    'is_active' => true,
                ]
            ),
            'import' => Supplier::query()->updateOrCreate(
                ['email' => 'export@global-zipper.test'],
                [
                    'company_name' => 'Global Zipper Materials Co.',
                    'contact_person' => 'Ms. Chen',
                    'mobile' => '+8613800000000',
                    'country' => 'China',
                    'supplier_type' => 'import',
                    'address' => 'Guangzhou, China',
                    'notes' => 'Import supplier for LC demo purchase.',
                    'is_active' => true,
                ]
            ),
        ];
    }

    /**
     * @return array<string, RawMaterial>
     */
    private function seedRawMaterials(): array
    {
        return [
            'nylon_tape' => RawMaterial::query()->updateOrCreate(
                ['name' => 'Nylon Tape'],
                ['description' => 'Nylon zipper tape raw material.']
            ),
            'metal_wire' => RawMaterial::query()->updateOrCreate(
                ['name' => 'Metal Wire'],
                ['description' => 'Metal wire used for sliders and pullers.']
            ),
            'zinc_alloy' => RawMaterial::query()->updateOrCreate(
                ['name' => 'Zinc Alloy'],
                ['description' => 'Zinc alloy for lockets and accessories.']
            ),
            'poly_bag' => RawMaterial::query()->updateOrCreate(
                ['name' => 'Poly Bag'],
                ['description' => 'Packaging poly bag for finished goods.']
            ),
        ];
    }

    /**
     * @param array<string, Supplier> $suppliers
     * @param array<string, RawMaterial> $rawMaterials
     * @param array<string, string> $dates
     */
    private function seedRawMaterialPurchases(
        Order $order101,
        Order $order102,
        array $suppliers,
        array $rawMaterials,
        ?int $adminId,
        array $dates
    ): void {
        $localPurchase = RawMaterialPurchase::query()->updateOrCreate(
            ['purchase_number' => 'RMP-LOCAL-001'],
            [
                'supplier_id' => $suppliers['local']->id,
                'order_id' => $order101->id,
                'source_type' => 'local_purchase',
                'payment_mode' => 'cash',
                'purchase_date' => $dates['local_purchase'],
                'currency' => 'BDT',
                'exchange_rate' => 1,
                'subtotal' => 125000,
                'tax_amount' => 0,
                'shipping_cost' => 2500,
                'customs_cost' => 0,
                'other_cost' => 500,
                'grand_total' => 128000,
                'status' => 'received',
                'remarks' => 'Local demo purchase for ORD-101.',
                'created_by' => $adminId,
            ]
        );

        $this->seedRawMaterialPurchaseItem($localPurchase, $rawMaterials['nylon_tape'], 450, 'kg', 'Tape for nylon zipper production.');
        $this->seedRawMaterialPurchaseItem($localPurchase, $rawMaterials['poly_bag'], 12000, 'pcs', 'Packaging for finished goods.');

        $importPurchase = RawMaterialPurchase::query()->updateOrCreate(
            ['purchase_number' => 'RMP-IMPORT-001'],
            [
                'supplier_id' => $suppliers['import']->id,
                'order_id' => $order102->id,
                'source_type' => 'import',
                'payment_mode' => 'lc',
                'lc_type' => 'own_lc',
                'purchase_date' => $dates['import_purchase'],
                'currency' => 'USD',
                'exchange_rate' => 120,
                'subtotal' => 2800,
                'tax_amount' => 0,
                'shipping_cost' => 350,
                'customs_cost' => 420,
                'other_cost' => 80,
                'grand_total' => 3650,
                'status' => 'received',
                'remarks' => 'Import demo purchase for ORD-102.',
                'lc_number' => 'LC-IMP-001',
                'lc_bank_name' => 'Demo Bank Ltd',
                'lc_opening_date' => $dates['lc_opening'],
                'lc_value' => 3650,
                'lc_currency' => 'USD',
                'lc_margin_amount' => 500,
                'lc_charges' => 75,
                'shipment_date' => $dates['shipment'],
                'expected_arrival_date' => $dates['arrival'],
                'import_document_number' => 'IMP-DOC-001',
                'expiry_date' => $dates['expiry'],
                'last_shipment_date' => $dates['challan_003'],
                'created_by' => $adminId,
            ]
        );

        $this->seedRawMaterialPurchaseItem($importPurchase, $rawMaterials['metal_wire'], 900, 'kg', 'Imported metal wire for slider production.');
        $this->seedRawMaterialPurchaseItem($importPurchase, $rawMaterials['zinc_alloy'], 1.5, 'tons', 'Imported zinc alloy for lockets.');
    }

    private function seedRawMaterialPurchaseItem(
        RawMaterialPurchase $purchase,
        RawMaterial $rawMaterial,
        float $quantity,
        string $unit,
        string $description
    ): void {
        RawMaterialPurchaseItem::query()->updateOrCreate(
            [
                'raw_material_purchase_id' => $purchase->id,
                'raw_material_id' => $rawMaterial->id,
            ],
            [
                'received_quantity' => $quantity,
                'description' => $description,
                'unit' => $unit,
                'quantity' => $quantity,
                'remarks' => 'Seeded demo raw material purchase item.',
            ]
        );
    }

    private function seedOrderItem(
        Order $order,
        string $itemCode,
        string $itemName,
        float $quantity,
        float $unitPrice,
        string $description,
        string $sizeType
    ): OrderItem {
        $catalogItem = Item::query()->where('item_code', $itemCode)->first();

        $orderItem = OrderItem::query()->updateOrCreate(
            [
                'order_id' => $order->id,
                'item_code' => $itemCode,
            ],
            [
                'item_name' => $itemName,
                'item_type' => $catalogItem?->item_type,
                'color' => $catalogItem?->color,
                'description' => $description,
                'size_type' => $sizeType,
                'currency' => $order->currency ?? $catalogItem?->currency ?? 'USD',
                'hh_code' => $catalogItem?->hh_code,
                'weight' => $catalogItem?->weight,
                'quantity' => $quantity,
                'unit' => 'PC',
                'unit_price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
                'remarks' => "Seeded {$itemName} order item.",
            ]
        );

        $orderItem->styles()->updateOrCreate(
            ['style_name' => "{$itemName} Standard"],
            [
                'style_code' => strtoupper(str_replace(' ', '-', $itemName)).'-STD',
                'color' => 'Black',
                'size' => $sizeType,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
                'custom_fields' => [
                    'hs_code' => $itemName === 'Nylon Zipper' ? '9607.11.00' : '9607.20.00',
                    'uom' => 'PC',
                    'packing' => 'Boxes',
                ],
                'remarks' => "Seeded style row for {$itemName}.",
            ]
        );

        return $orderItem;
    }

    private function seedDeliveryVariantLine(DeliveryChallan $challan, OrderItem $orderItem, float $quantity): void
    {
        $variant = $orderItem->styles()->firstOrFail();

        $challan->variantLines()->delete();
        $challan->variantLines()->create([
            'order_item_style_id' => $variant->id,
            'quantity' => $quantity,
            'unit' => $orderItem->unit,
            'item_code_snapshot' => $orderItem->item_code,
            'item_name_snapshot' => $orderItem->item_name,
            'variant_name_snapshot' => $variant->style_name,
            'custom_fields_snapshot' => $variant->custom_fields,
            'legacy_unallocated' => false,
        ]);
    }

    /**
     * @param array<int, int> $keptOrderItemIds
     */
    private function pruneDemoOrderItems(Order $order, array $keptOrderItemIds): void
    {
        $order->items()
            ->whereNotIn('id', $keptOrderItemIds)
            ->get()
            ->each(function (OrderItem $orderItem): void {
                $orderItem->proformaInvoiceItems()->delete();
                $orderItem->invoiceItems()->delete();
                $orderItem->styles()->delete();
                $orderItem->delete();
            });
    }

    private function seedProformaInvoiceItem(ProformaInvoice $proformaInvoice, OrderItem $orderItem): void
    {
        $quantity = (float) ($orderItem->quantity ?? 0);
        $unitPrice = (float) ($orderItem->unit_price ?? 0);

        $proformaInvoice->items()->updateOrCreate(
            [
                'order_item_id' => $orderItem->id,
            ],
            [
                'description' => $orderItem->description,
                'size_type' => $orderItem->size_type,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
            ]
        );
    }

    private function seedInvoiceItem(Invoice $invoice, OrderItem $orderItem): void
    {
        InvoiceItem::query()->updateOrCreate(
            [
                'invoice_id' => $invoice->id,
                'order_item_id' => $orderItem->id,
            ],
            [
                'description' => $orderItem->description,
                'size_type' => $orderItem->size_type,
                'quantity' => $orderItem->quantity,
                'unit_price' => $orderItem->unit_price,
                'line_total' => $orderItem->line_total,
            ]
        );
    }
}
