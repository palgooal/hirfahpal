<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\VendorOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_address_id' => ['nullable', 'integer', 'exists:customer_addresses,id'],
            'payment_method' => ['required', 'in:online,cod'],
            'delivery_fees' => ['array'],
            'delivery_fees.*' => ['numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer = $request->user('customer');
        $cart = Cart::query()
            ->with('items.product.vendor')
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->firstOrFail();

        abort_if($cart->items->isEmpty(), 422, 'Cart is empty.');

        foreach ($cart->items as $item) {
            abort_if($item->product->stock_quantity < $item->quantity, 422, "Product [{$item->product->name}] is out of stock.");
        }

        $order = DB::transaction(function () use ($cart, $customer, $data) {
            $groupedItems = $cart->items->groupBy(fn ($item) => $item->product->vendor_id);
            $subtotal = $cart->items->sum(fn ($item) => $item->quantity * (float) $item->unit_price);
            $deliveryTotal = collect($data['delivery_fees'] ?? [])->sum();

            $order = Order::query()->create([
                'number' => $this->nextOrderNumber(),
                'customer_id' => $customer->id,
                'customer_address_id' => $data['customer_address_id'] ?? null,
                'status' => 'confirmed',
                'payment_method' => $data['payment_method'],
                'payment_status' => $data['payment_method'] === 'cod' ? 'pending' : 'authorized',
                'subtotal' => $subtotal,
                'delivery_total' => $deliveryTotal,
                'grand_total' => $subtotal + $deliveryTotal,
                'notes' => $data['notes'] ?? null,
                'confirmed_at' => now(),
            ]);

            foreach ($groupedItems as $vendorId => $items) {
                $vendorSubtotal = $items->sum(fn ($item) => $item->quantity * (float) $item->unit_price);
                $deliveryFee = (float) ($data['delivery_fees'][$vendorId] ?? 0);

                $vendorOrder = VendorOrder::query()->create([
                    'number' => $order->number.'-V'.$vendorId,
                    'order_id' => $order->id,
                    'vendor_id' => $vendorId,
                    'status' => 'pending',
                    'subtotal' => $vendorSubtotal,
                    'delivery_fee' => $deliveryFee,
                    'total' => $vendorSubtotal + $deliveryFee,
                    'vendor_response_due_at' => now()->addDay(),
                ]);

                foreach ($items as $item) {
                    $product = $item->product;

                    $order->items()->create([
                        'vendor_order_id' => $vendorOrder->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'line_total' => $item->quantity * (float) $item->unit_price,
                    ]);

                    $product->decrement('stock_quantity', $item->quantity);
                }
            }

            Payment::query()->create([
                'order_id' => $order->id,
                'method' => $data['payment_method'],
                'status' => $data['payment_method'] === 'cod' ? 'pending' : 'authorized',
                'amount' => $order->grand_total,
            ]);

            $cart->update(['status' => 'ordered']);

            return $order;
        });

        return response()->json($order->load(['vendorOrders.items', 'payment']), 201);
    }

    private function nextOrderNumber(): string
    {
        return 'HF-'.now()->format('Ymd-His').'-'.random_int(100, 999);
    }
}
