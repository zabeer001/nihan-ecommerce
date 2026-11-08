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
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class ExpandedDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@gmail.com')->first();
        $today = CarbonImmutable::today();
        $orders = collect($this->workflows())->mapWithKeys(function (array $workflow) use ($admin, $today): array {
            $order = $this->seedWorkflow($workflow, $admin?->id, $today);

            return [$workflow['code'] => $order];
        });

        $this->seedProcurement($orders->all(), $admin?->id, $today);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function workflows(): array
    {
        return [
            [
                'code' => '201', 'customer' => 'Delta Apparels Ltd', 'email' => 'accounts@delta-apparels.demo',
                'currency' => 'USD', 'payment_type' => 'lc_60', 'item_code' => 'ZIP-NYL-05-BLK',
                'item_name' => 'Nylon Zipper #5', 'quantity' => 5000, 'unit_price' => 0.45,
                'days_ago' => 2, 'status' => 'completed', 'delivery_status' => 'delivered',
                'pi' => true, 'challans' => [2500, 2500], 'invoice' => true, 'payments' => [1000, 1250],
                'amount_words' => 'Two thousand two hundred fifty US dollars only',
            ],
            [
                'code' => '202', 'customer' => 'Metro Fashion House', 'email' => 'finance@metro-fashion.demo',
                'currency' => 'BDT', 'payment_type' => 'cash', 'item_code' => 'SLD-MTL-MD-SIL',
                'item_name' => 'Metal Slider', 'quantity' => 3000, 'unit_price' => 18,
                'days_ago' => 5, 'status' => 'processing', 'delivery_status' => 'partial',
                'pi' => true, 'challans' => [1200], 'invoice' => false, 'payments' => [],
                'amount_words' => 'Fifty-four thousand taka only',
            ],
            [
                'code' => '203', 'customer' => 'Apex Knitwear PLC', 'email' => 'commercial@apex-knitwear.demo',
                'currency' => 'USD', 'payment_type' => 'lc_90', 'item_code' => 'LCK-REG-GLD',
                'item_name' => 'Gold Locket', 'quantity' => 7500, 'unit_price' => 0.12,
                'days_ago' => 9, 'status' => 'confirmed', 'delivery_status' => 'pending',
                'pi' => true, 'challans' => [], 'invoice' => false, 'payments' => [],
                'amount_words' => 'Nine hundred US dollars only',
            ],
            [
                'code' => '204', 'customer' => 'Nova Accessories Ltd', 'email' => 'accounts@nova-accessories.demo',
                'currency' => 'BDT', 'payment_type' => 'cheque', 'item_code' => 'PUL-RBR-STD-BLK',
                'item_name' => 'Rubber Puller', 'quantity' => 1200, 'unit_price' => 65,
                'days_ago' => 14, 'status' => 'completed', 'delivery_status' => 'delivered',
                'pi' => true, 'challans' => [1200], 'invoice' => true, 'payments' => [],
                'amount_words' => 'Seventy-eight thousand taka only',
            ],
            [
                'code' => '205', 'customer' => 'Sunrise Garments', 'email' => 'finance@sunrise-garments.demo',
                'currency' => 'USD', 'payment_type' => 'lc_30', 'item_code' => 'ZIP-NYL-03-WHT',
                'item_name' => 'Nylon Zipper #3', 'quantity' => 4000, 'unit_price' => 0.30,
                'days_ago' => 20, 'status' => 'completed', 'delivery_status' => 'delivered',
                'pi' => true, 'challans' => [1500, 2500], 'invoice' => true, 'payments' => [400, 300],
                'amount_words' => 'One thousand two hundred US dollars only',
            ],
            [
                'code' => '206', 'customer' => 'Urban Stitch Limited', 'email' => 'office@urban-stitch.demo',
                'currency' => 'BDT', 'payment_type' => 'cash', 'item_code' => 'TAP-NYL-20MM-BLK',
                'item_name' => 'Nylon Tape', 'quantity' => 1000, 'unit_price' => 25,
                'days_ago' => 25, 'status' => 'cancelled', 'delivery_status' => 'pending',
                'pi' => false, 'challans' => [], 'invoice' => false, 'payments' => [],
                'amount_words' => 'Twenty-five thousand taka only',
            ],
            [
                'code' => '207', 'customer' => 'Prime Textile Group', 'email' => 'orders@prime-textile.demo',
                'currency' => 'USD', 'payment_type' => 'lc_120', 'item_code' => 'LCK-REG-BLK',
                'item_name' => 'Black Locket', 'quantity' => 6000, 'unit_price' => 0.08,
                'days_ago' => 35, 'status' => 'processing', 'delivery_status' => 'pending',
                'pi' => false, 'challans' => [], 'invoice' => false, 'payments' => [],
                'amount_words' => 'Four hundred eighty US dollars only',
            ],
            [
                'code' => '208', 'customer' => 'Heritage Clothing Co', 'email' => 'accounts@heritage-clothing.demo',
                'currency' => 'BDT', 'payment_type' => 'cheque', 'item_code' => 'SLD-MTL-SM-BLK',
                'item_name' => 'Small Metal Slider', 'quantity' => 2500, 'unit_price' => 12,
                'days_ago' => 45, 'status' => 'completed', 'delivery_status' => 'delivered',
                'pi' => true, 'challans' => [2500], 'invoice' => true, 'payments' => [30000],
                'amount_words' => 'Thirty thousand taka only',
            ],
        ];
    }

    private function seedWorkflow(array $data, ?int $adminId, CarbonImmutable $today): Order
    {
        $orderDate = $today->subDays($data['days_ago']);
        $total = round($data['quantity'] * $data['unit_price'], 2);
        $customer = Customer::query()->updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['customer'],
                'mobile' => '+88017'.str_pad($data['code'], 8, '0', STR_PAD_LEFT),
                'address' => "Corporate Office, Dhaka\nBangladesh",
                'delivery_address' => "Factory Unit {$data['code']}, Gazipur, Bangladesh",
                'shipping_address' => "Factory Unit {$data['code']}, Gazipur, Bangladesh",
                'contact_person' => 'Demo Contact '.$data['code'],
                'payment_type' => $data['payment_type'],
                'payment_currency' => $data['currency'],
                'payment_terms' => "<p>Demo {$data['payment_type']} payment terms for testing.</p>",
                'notes' => 'Expanded operational demo customer.',
            ]
        );

        $order = Order::query()->updateOrCreate(
            ['order_number' => 'DEMO-ORD-'.$data['code']],
            [
                'customer_id' => $customer->id,
                'production_order_number' => 'DEMO-PROD-'.$data['code'],
                'order_date' => $orderDate->toDateString(),
                'delivery_date' => $orderDate->addDays(14)->toDateString(),
                'po_number' => 'DEMO-PO-'.$data['code'],
                'work_order_number' => 'DEMO-WO-'.$data['code'],
                'reference_number' => 'DEMO-REF-'.$data['code'],
                'currency' => $data['currency'],
                'payment_type' => $data['payment_type'],
                'ordered_qty' => $data['quantity'],
                'pp_qty' => $data['quantity'],
                'yarn_qty' => 0,
                'material_amount' => $total,
                'total_amount' => $total,
                'due_amount' => $total,
                'status' => $data['status'],
                'delivery_status' => $data['delivery_status'],
                'remarks' => 'Expanded demo order covering '.$data['status'].' / '.$data['delivery_status'].'.',
                'created_by' => $adminId,
            ]
        );

        $catalogItem = Item::query()->where('item_code', $data['item_code'])->first();
        $orderItem = OrderItem::query()->updateOrCreate(
            ['order_id' => $order->id, 'item_code' => $data['item_code']],
            [
                'item_name' => $data['item_name'],
                'item_type' => $catalogItem?->item_type ?? 'Accessories',
                'color' => $catalogItem?->color ?? 'Assorted',
                'description' => 'Expanded demo '.$data['item_name'],
                'size_type' => $catalogItem?->size ?? 'Standard',
                'currency' => $data['currency'],
                'hh_code' => $catalogItem?->hh_code,
                'weight' => $catalogItem?->weight,
                'quantity' => $data['quantity'],
                'unit_price' => $data['unit_price'],
                'line_total' => $total,
                'unit' => $catalogItem?->unit_measurement ?? 'PC',
                'remarks' => 'Expanded demo order item.',
            ]
        );
        $order->items()->where('id', '!=', $orderItem->id)->delete();
        $variant = $orderItem->styles()->updateOrCreate(
            ['style_name' => $data['item_name'].' Standard'],
            [
                'style_code' => 'DEMO-STYLE-'.$data['code'],
                'color' => $orderItem->color,
                'size' => $orderItem->size_type,
                'quantity' => $data['quantity'],
                'unit_price' => $data['unit_price'],
                'line_total' => $total,
                'custom_fields' => ['hs_code' => $orderItem->hh_code ?: '9607.20.00', 'packing' => 'Cartons'],
                'remarks' => 'Expanded demo variant.',
            ]
        );

        if (! $data['pi']) {
            return $order;
        }

        $pi = ProformaInvoice::query()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'customer_id' => $customer->id,
                'pi_number' => 'DEMO-PI-'.$data['code'],
                'pi_date' => $orderDate->addDay()->toDateString(),
                'pi_expire_date' => $orderDate->addDays(31)->toDateString(),
                'delivery_date' => $order->delivery_date,
                'currency' => $data['currency'],
                'payment_type' => $data['payment_type'],
                'brand' => 'Demo Brand '.$data['code'],
                'coordinator' => 'Demo Coordinator',
                'shipment_method' => 'By truck',
                'shipping_terms' => 'Ex-Factory',
                'country_of_origin' => 'Bangladesh',
                'subtotal' => $total,
                'grand_total' => $total,
                'total_weight' => (float) ($orderItem->weight ?? 0) * $data['quantity'],
                'paid_total' => 0,
                'due_total' => $total,
                'amount_in_words' => $data['amount_words'],
                'remarks' => 'Expanded demo proforma invoice.',
                'terms_text' => 'Goods supplied under approved commercial terms.',
                'status' => 'approved',
                'created_by' => $adminId,
            ]
        );
        $pi->orders()->sync([$order->id]);
        $pi->items()->updateOrCreate(
            ['order_item_id' => $orderItem->id],
            [
                'description' => $orderItem->description,
                'size_type' => $orderItem->size_type,
                'quantity' => $data['quantity'],
                'unit_price' => $data['unit_price'],
                'line_total' => $total,
            ]
        );

        foreach ($data['challans'] as $index => $quantity) {
            $challan = DeliveryChallan::query()->updateOrCreate(
                ['challan_number' => 'DEMO-CH-'.$data['code'].'-'.($index + 1)],
                [
                    'customer_id' => $customer->id,
                    'order_id' => $order->id,
                    'proforma_invoice_id' => $pi->id,
                    'challan_date' => $orderDate->addDays(5 + $index)->toDateString(),
                    'delivery_qty' => $quantity,
                    'delivery_unit' => $orderItem->unit,
                    'delivery_person_name' => 'Demo Delivery Team '.($index + 1),
                    'package_info' => $quantity.' '.$orderItem->unit,
                    'remarks' => 'Expanded demo delivery challan.',
                    'status' => 'delivered',
                    'created_by' => $adminId,
                ]
            );
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

        if (! $data['invoice']) {
            return $order;
        }

        $invoice = Invoice::query()->updateOrCreate(
            ['invoice_number' => 'DEMO-INV-'.$data['code']],
            [
                'customer_id' => $customer->id,
                'proforma_invoice_id' => $pi->id,
                'document_type' => 'invoice',
                'invoice_date' => $orderDate->addDays(8)->toDateString(),
                'currency' => $data['currency'],
                'payment_type' => $data['payment_type'],
                'subtotal' => $total,
                'total_amount' => $total,
                'payable_amount' => $total,
                'amount_in_words' => $data['amount_words'],
                'remarks' => 'Expanded demo commercial invoice.',
                'terms_text' => 'Payment according to agreed customer terms.',
                'payment_status' => 'unpaid',
                'status' => 'issued',
                'created_by' => $adminId,
            ]
        );
        $invoice->orders()->sync([$order->id]);
        $order->update(['invoice_id' => $invoice->id]);
        InvoiceItem::query()->updateOrCreate(
            ['invoice_id' => $invoice->id, 'order_item_id' => $orderItem->id],
            [
                'description' => $orderItem->description,
                'size_type' => $orderItem->size_type,
                'quantity' => $data['quantity'],
                'unit_price' => $data['unit_price'],
                'line_total' => $total,
            ]
        );

        foreach ($data['payments'] as $index => $amount) {
            Payment::query()->updateOrCreate(
                ['invoice_id' => $invoice->id, 'reference_number' => 'DEMO-PAY-'.$data['code'].'-'.($index + 1)],
                [
                    'proforma_invoice_id' => $pi->id,
                    'payment_date' => $orderDate->addDays(10 + $index)->toDateString(),
                    'paid_amount' => $amount,
                    'amount_in_words' => 'Demo payment of '.$data['currency'].' '.$amount,
                    'payment_method' => $index % 2 === 0 ? 'bank_transfer' : 'cheque',
                    'payment_note' => 'Expanded demo payment installment '.($index + 1).'.',
                    'created_by' => $adminId,
                ]
            );
        }

        $paidTotal = (float) $invoice->payments()->sum('paid_amount');
        $dueTotal = max(0, $total - $paidTotal);
        $invoice->update(['payment_status' => $dueTotal <= 0 ? 'paid' : ($paidTotal > 0 ? 'partial' : 'unpaid')]);
        $pi->forceFill(['paid_total' => $paidTotal, 'due_total' => $dueTotal, 'status' => $dueTotal <= 0 ? 'paid' : 'approved'])->save();
        $order->forceFill(['due_amount' => $dueTotal])->save();

        return $order;
    }

    /**
     * @param array<string, Order> $orders
     */
    private function seedProcurement(array $orders, ?int $adminId, CarbonImmutable $today): void
    {
        $suppliers = [
            ['code' => '201', 'name' => 'Bangla Packaging Industries', 'email' => 'sales@bangla-packaging.demo', 'country' => 'Bangladesh', 'type' => 'local'],
            ['code' => '202', 'name' => 'Eastern Dyeing Chemicals', 'email' => 'orders@eastern-dyeing.demo', 'country' => 'Bangladesh', 'type' => 'local'],
            ['code' => '203', 'name' => 'Guangzhou Metal Parts Co', 'email' => 'export@guangzhou-metal.demo', 'country' => 'China', 'type' => 'import'],
            ['code' => '204', 'name' => 'Shenzhen Polymer Supply', 'email' => 'trade@shenzhen-polymer.demo', 'country' => 'China', 'type' => 'import'],
        ];
        $materials = [
            ['name' => 'Polyester Yarn', 'description' => 'High-tenacity polyester yarn for zipper production.'],
            ['name' => 'Electroplating Chemical', 'description' => 'Metal finishing chemical for sliders and pullers.'],
            ['name' => 'Carton Box', 'description' => 'Export-grade finished goods carton.'],
            ['name' => 'Rubber Compound', 'description' => 'Flexible compound used for rubber pullers.'],
        ];

        foreach ($suppliers as $index => $supplierData) {
            $supplier = Supplier::query()->updateOrCreate(
                ['email' => $supplierData['email']],
                [
                    'company_name' => $supplierData['name'],
                    'contact_person' => 'Supplier Contact '.$supplierData['code'],
                    'mobile' => '+88018'.str_pad($supplierData['code'], 8, '0', STR_PAD_LEFT),
                    'country' => $supplierData['country'],
                    'supplier_type' => $supplierData['type'],
                    'address' => $supplierData['country'].' demo business address.',
                    'notes' => 'Expanded demo supplier.',
                    'is_active' => true,
                ]
            );
            $material = RawMaterial::query()->updateOrCreate(
                ['name' => $materials[$index]['name']],
                ['description' => $materials[$index]['description']]
            );
            $isImport = $supplierData['type'] === 'import';
            $subtotal = $isImport ? 2400 + ($index * 350) : 65000 + ($index * 15000);
            $shipping = $isImport ? 300 : 1500;
            $customs = $isImport ? 450 : 0;
            $purchase = RawMaterialPurchase::query()->updateOrCreate(
                ['purchase_number' => 'DEMO-RMP-'.$supplierData['code']],
                [
                    'supplier_id' => $supplier->id,
                    'order_id' => $orders[(string) (201 + $index)]?->id,
                    'source_type' => $isImport ? 'import' : 'local_purchase',
                    'payment_mode' => $isImport ? 'lc' : ($index === 0 ? 'cash' : 'cheque'),
                    'lc_type' => $isImport ? 'own_lc' : null,
                    'purchase_date' => $today->subDays(3 + $index)->toDateString(),
                    'currency' => $isImport ? 'USD' : 'BDT',
                    'exchange_rate' => $isImport ? 122 : 1,
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shipping,
                    'customs_cost' => $customs,
                    'grand_total' => $subtotal + $shipping + $customs,
                    'status' => $index === 1 ? 'ordered' : 'received',
                    'remarks' => 'Expanded demo raw material purchase.',
                    'lc_number' => $isImport ? 'DEMO-LC-'.$supplierData['code'] : null,
                    'lc_bank_name' => $isImport ? 'National Credit and Commerce Bank Ltd.' : null,
                    'lc_opening_date' => $isImport ? $today->subDays(5)->toDateString() : null,
                    'lc_value' => $isImport ? $subtotal + $shipping + $customs : null,
                    'lc_currency' => $isImport ? 'USD' : null,
                    'shipment_date' => $isImport ? $today->subDays(2)->toDateString() : null,
                    'expected_arrival_date' => $isImport ? $today->addDays(12)->toDateString() : null,
                    'import_document_number' => $isImport ? 'DEMO-IMP-'.$supplierData['code'] : null,
                    'expiry_date' => $isImport ? $today->addMonth()->toDateString() : null,
                    'last_shipment_date' => $isImport ? $today->addDays(20)->toDateString() : null,
                    'created_by' => $adminId,
                ]
            );
            $purchase->items()->updateOrCreate(
                ['raw_material_id' => $material->id],
                [
                    'received_quantity' => 500 + ($index * 250),
                    'description' => $materials[$index]['description'],
                    'unit' => $index === 2 ? 'pcs' : 'kg',
                    'quantity' => 500 + ($index * 250),
                    'remarks' => 'Expanded demo purchase item.',
                ]
            );
        }
    }
}
