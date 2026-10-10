<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
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
        $cart = $this->checkoutCart($request, $customer->id);

        abort_if($cart->items->isEmpty(), 422, 'Cart is empty.');

        foreach ($cart->items as $item) {
            abort_unless($item->product->isSellable(), 422, "Product [{$item->product->name}] is no longer available for sale.");
            abort_if($item->product->availableStockQuantity() < $item->quantity, 422, "Product [{$item->product->name}] is out of stock.");
        }

        $order = DB::transaction(function () use ($cart, $customer, $data) {
            // Lock every product up front in ascending id order, so concurrent checkouts
            // (and vendor accepts) always take product locks in the same order (VEN-BE-019).
            $lockedProducts = Product::query()
                ->whereIn('id', $cart->items->pluck('product_id')->unique()->sort()->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->load(['vendor.profile'])
                ->keyBy('id');

            $groupedItems = $cart->items->groupBy(fn ($item) => $item->product->vendor_id);
            $subtotal = $cart->items->sum(fn ($item) => $item->quantity * (float) $lockedProducts->get($item->product_id)?->price);
            $deliveryTotal = collect($data['delivery_fees'] ?? [])->sum();

            $order = Order::query()->create([
                'number' => $this->nextOrderNumber(),
                'customer_id' => $customer->id,
                'customer_address_id' => $data['customer_address_id'] ?? null,
                'status' => 'pending',
                'payment_method' => $data['payment_method'],
                'payment_status' => 'pending',
                'subtotal' => $subtotal,
                'delivery_total' => $deliveryTotal,
                'grand_total' => $subtotal + $deliveryTotal,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($groupedItems as $vendorId => $items) {
                $vendorSubtotal = $items->sum(fn ($item) => $item->quantity * (float) $lockedProducts->get($item->product_id)?->price);
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
                    $product = $lockedProducts->get($item->product_id);
                    abort_if($product === null, 422, 'A product in the cart is no longer available.');
                    abort_unless($product->isSellable(), 422, "Product [{$product->name}] is no longer available for sale.");
                    abort_if($product->availableStockQuantity() < $item->quantity, 422, "Product [{$product->name}] is out of stock.");
                    $unitPrice = (float) $product->price;

                    $orderItem = $order->items()->create([
                        'vendor_order_id' => $vendorOrder->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'quantity' => $item->quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $item->quantity * $unitPrice,
                    ]);

                    $order->stockReservations()->create([
                        'product_id' => $product->id,
                        'vendor_order_id' => $vendorOrder->id,
                        'order_item_id' => $orderItem->id,
                        'quantity' => $item->quantity,
                        'status' => 'reserved',
                        'reserved_at' => now(),
                    ]);
                }
            }

            Payment::query()->create([
                'order_id' => $order->id,
                'method' => $data['payment_method'],
                'status' => 'pending',
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

    private function checkoutCart(Request $request, int $customerId): Cart
    {
        $customerCart = Cart::query()
            ->with('items.product.vendor')
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->first();

        $guestCart = Cart::query()
            ->with('items.product.vendor')
            ->where('session_id', $request->session()->getId())
            ->whereNull('customer_id')
            ->where('status', 'active')
            ->first();

        if ($guestCart && $customerCart) {
            foreach ($guestCart->items as $guestItem) {
                $customerItem = $customerCart->items->firstWhere('product_id', $guestItem->product_id);

                if ($customerItem) {
                    $customerItem->update([
                        'quantity' => $customerItem->quantity + $guestItem->quantity,
                        'unit_price' => $guestItem->unit_price,
                    ]);

                    continue;
                }

                $customerCart->items()->create([
                    'product_id' => $guestItem->product_id,
                    'quantity' => $guestItem->quantity,
                    'unit_price' => $guestItem->unit_price,
                ]);
            }

            $guestCart->update(['status' => 'abandoned']);

            return $customerCart->fresh('items.product.vendor');
        }

        if ($guestCart) {
            $guestCart->update(['customer_id' => $customerId]);

            return $guestCart->fresh('items.product.vendor');
        }

        return $customerCart ?? abort(404, 'Cart not found.');
    }
}
