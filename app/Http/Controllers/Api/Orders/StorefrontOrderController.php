<?php

namespace App\Http\Controllers\Api\Orders;

use App\Http\Controllers\Api\Orders\Services\OrderWorkflowService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\StorefrontQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StorefrontOrderController extends Controller
{
    private const TRACKING_RULE = 'required|string|regex:/\A[a-f0-9]{64}\z/';

    private const ITEM_RULES = [
        'items' => 'required|array|list|min:1|max:100',
        'items.*.variant_id' => 'required|integer|distinct|min:1',
        'items.*.quantity' => 'required|integer|min:1|max:1000000',
    ];

    public function quote(Request $request, StorefrontQuote $quotes): JsonResponse
    {
        $data = $request->validate(self::ITEM_RULES + ['items.*' => 'required|array:variant_id,quantity']);
        $items = $data['items'];

        $delivery = $quotes->deliveryCents();
        $variants = $quotes->variants($items);
        $variants->loadMissing([
            'images' => fn ($images) => $images->reorder('primary', 'desc')->orderBy('position')->orderBy('id')->limit(1),
            'product.images' => fn ($images) => $images->limit(1),
        ]);

        return response()->json($quotes->quote($items, $variants, $delivery))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, OrderWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate(self::ITEM_RULES + [
            'items.*' => 'required|array:variant_id,quantity,price_cents',
            'items.*.price_cents' => 'required|integer|min:0',
            'delivery_cents' => 'required|integer|min:0|max:100000000',
            'submission_key' => 'required|uuid',
            'tracking_secret' => self::TRACKING_RULE,
            'customer_name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'max:40', 'regex:/\A(?:\+?880|0)1[3-9][0-9]{8}\z/'],
            'email' => 'nullable|email|max:255',
            'address' => 'required|string|max:2000',
        ]);
        $trackingHash = hash('sha256', $data['tracking_secret']);
        unset($data['tracking_secret']);
        $data['submission_key'] = strtolower($data['submission_key']);
        $data['delivery_cents'] = (int) $data['delivery_cents'];
        $data['items'] = collect($data['items'])->map(fn ($item) => [
            'variant_id' => (int) $item['variant_id'],
            'quantity' => (int) $item['quantity'],
            'price_cents' => (int) $item['price_cents'],
        ])->sortBy('variant_id')->values()->all();

        return $this->receipt($workflow->create($data, null, $trackingHash));
    }

    public function tracking(Request $request): JsonResponse
    {
        $data = $request->validate(['tracking_secret' => self::TRACKING_RULE]);
        $order = Order::where('source', 'storefront')
            ->where('tracking_hash', hash('sha256', $data['tracking_secret']))
            ->firstOrFail();

        return $this->receipt($order);
    }

    private function receipt(Order $order): JsonResponse
    {
        $history = DB::table('order_status_events')->where('order_id', $order->id)->orderBy('id')
            ->get(['status', 'created_at'])
            ->map(fn ($event) => [
                'status' => $event->status,
                'created_at' => Carbon::parse($event->created_at)->toIso8601String(),
            ]);

        return response()->json([
            'reference' => $order->id,
            'status' => $history->last()['status'] ?? $order->status,
            'subtotal_cents' => $order->subtotal_cents,
            'delivery_cents' => $order->delivery_cents,
            'total_cents' => $order->total_cents,
            'history' => $history,
        ])->header('Cache-Control', 'private, no-store');
    }
}
