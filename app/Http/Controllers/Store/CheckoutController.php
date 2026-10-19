<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\VendorOrder;
use App\Support\Orders\DeliveryAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            $choice = $request->input('addressChoice');
            $request->merge([
                'payment_method' => $request->input('paymentMethod'),
                'customer_address_id' => is_string($choice) && ctype_digit($choice) ? (int) $choice : null,
            ]);
        }
        $data = $request->validate([
            'customer_address_id' => ['nullable', 'integer', 'exists:customer_addresses,id'],
            'payment_method' => ['required', 'in:online,cod'],
            'delivery_fees' => ['array'],
            'delivery_fees.*' => ['numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'recipient_name' => [$request->input('addressChoice') === 'new' ? 'required' : 'nullable', 'string', 'max:255'],
            'phone' => [$request->input('addressChoice') === 'new' ? 'required' : 'nullable', 'string', 'max:32'],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'address_line' => [$request->input('addressChoice') === 'new' ? 'required' : 'nullable', 'string', 'max:500'],
        ]);

        if (! $request->expectsJson() && $request->input('addressChoice') !== 'new') {
            $request->validate(['customer_address_id' => ['required']]);
        }

        $customer = $request->user('customer');
        if (! empty($data['customer_address_id'])) {
            abort_unless(
                $customer->addresses()->whereKey($data['customer_address_id'])->exists(),
                403,
                'The selected address does not belong to this customer.'
            );
        }

        $cart = $this->checkoutCart($request, $customer->id);

        abort_if($cart->items->isEmpty(), 422, 'Cart is empty.');

        $data['delivery_fees'] = $cart->items->pluck('product.vendor_id')->unique()
            ->mapWithKeys(fn ($vendorId) => [$vendorId => max(0, (float) config('marketplace.delivery_fee', 0))])->all();

        foreach ($cart->items as $item) {
            abort_unless($item->product->isSellable(), 422, "Product [{$item->product->name}] is no longer available for sale.");
            abort_if($item->product->availableStockQuantity() < $item->quantity, 422, "Product [{$item->product->name}] is out of stock.");
        }

        $order = DB::transaction(function () use ($cart, $customer, $data, $request) {
            $lockedCart = Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedCart->status === 'active', 422, 'This cart has already been submitted.');
            // ORD-BE-001: the delivery address is copied from the address row as it is now, in this transaction.
            // A saved address is read again locked FOR UPDATE with its owner (a concurrent edit or delete is
            // ordered before or after the copy; a deleted or foreign address ends in 403). Lock order: cart,
            // address, then products ascending (VEN-BE-019).
            $deliveryAddress = null;

            if ($request->input('addressChoice') === 'new') {
                $deliveryAddress = $customer->addresses()->create([
                    'label' => 'عنوان الطلب', 'recipient_name' => $data['recipient_name'], 'phone' => $data['phone'],
                    'governorate_id' => $data['governorate_id'] ?? null, 'address_line' => $data['address_line'],
                ]);
                $data['customer_address_id'] = $deliveryAddress->id;
            } elseif (! empty($data['customer_address_id'])) {
                $deliveryAddress = CustomerAddress::query()
                    ->whereKey($data['customer_address_id'])
                    ->where('customer_id', $customer->id)
                    ->lockForUpdate()
                    ->first();

                abort_unless($deliveryAddress, 403, 'The selected address does not belong to this customer.');
            }

            $deliveryColumns = $deliveryAddress
                ? DeliveryAddress::snapshotColumns($deliveryAddress->load(['governorate', 'city']))
                : DeliveryAddress::noAddressColumns();
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
                ...$deliveryColumns,
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

        return $request->expectsJson()
            ? response()->json($order->load(['vendorOrders.items', 'payment']), 201)
            : redirect()->route('customer.dashboard')->with('success', 'تم إنشاء الطلب '.$order->number.' بنجاح.');
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
