<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepting_a_vendor_order_moves_the_parent_order_to_processing(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $product = $this->product($vendor);
        [$order, $vendorOrder] = $this->orderWithVendorOrder($vendor);
        $this->itemAndReservation($order, $vendorOrder, $product, 2);

        $this->actingAs($vendor, 'vendor')
            ->patchJson(route('vendor.dashboard.orders.accept', $vendorOrder))
            ->assertOk()
            ->assertJsonPath('order.status', 'accepted');

        $this->assertSame('processing', $order->fresh()->status);
        $this->assertNull($order->fresh()->completed_at);
    }

    public function test_rejecting_every_vendor_order_cancels_the_parent_order(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        [$order, $vendorOrder] = $this->orderWithVendorOrder($vendor);

        $this->actingAs($vendor, 'vendor')
            ->patchJson(route('vendor.dashboard.orders.reject', $vendorOrder), [
                'rejection_reason' => 'Unavailable.',
            ])
            ->assertOk()
            ->assertJsonPath('order.status', 'rejected');

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_rejecting_one_of_multiple_vendor_orders_partially_cancels_the_parent_order(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $otherVendor = Vendor::factory()->approved()->create();
        [$order, $vendorOrder] = $this->orderWithVendorOrder($vendor);
        $otherVendorOrder = $this->vendorOrder($order, $otherVendor, status: 'pending');

        $this->actingAs($vendor, 'vendor')
            ->patchJson(route('vendor.dashboard.orders.reject', $vendorOrder), [
                'rejection_reason' => 'Unavailable.',
            ])
            ->assertOk();

        $this->assertSame('partially_cancelled', $order->fresh()->status);
        $this->assertSame('pending', $otherVendorOrder->fresh()->status);
    }

    public function test_preparing_and_ready_transitions_keep_the_parent_processing(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        [$order, $vendorOrder] = $this->orderWithVendorOrder($vendor, status: 'accepted');

        $this->actingAs($vendor, 'vendor')
            ->patchJson(route('vendor.dashboard.orders.preparing', $vendorOrder))
            ->assertOk()
            ->assertJsonPath('order.status', 'preparing');

        $this->assertSame('processing', $order->fresh()->status);

        $this->actingAs($vendor, 'vendor')
            ->patchJson(route('vendor.dashboard.orders.ready', $vendorOrder))
            ->assertOk()
            ->assertJsonPath('order.status', 'ready_for_delivery');

        $this->assertSame('processing', $order->fresh()->status);
        $this->assertNotNull($vendorOrder->fresh()->ready_at);
    }

    private function product(Vendor $vendor): Product
    {
        return Product::query()->create([
            'vendor_id' => $vendor->id,
            'name' => 'Lifecycle Product',
            'slug' => 'lifecycle-product-'.uniqid(),
            'price' => 10,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);
    }

    /**
     * @return array{0: Order, 1: VendorOrder}
     */
    private function orderWithVendorOrder(Vendor $vendor, string $status = 'pending'): array
    {
        $order = Order::query()->create([
            'number' => 'HF-'.uniqid(),
            'customer_id' => Customer::factory()->create()->id,
            'status' => 'pending',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'subtotal' => 10,
            'delivery_total' => 0,
            'grand_total' => 10,
        ]);

        return [$order, $this->vendorOrder($order, $vendor, $status)];
    }

    private function vendorOrder(Order $order, Vendor $vendor, string $status = 'pending'): VendorOrder
    {
        return VendorOrder::query()->create([
            'number' => 'VO-'.uniqid(),
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => $status,
            'subtotal' => 10,
            'delivery_fee' => 0,
            'commission_amount' => 0,
            'total' => 10,
            'accepted_at' => in_array($status, ['accepted', 'preparing', 'ready_for_delivery'], true) ? now() : null,
        ]);
    }

    private function itemAndReservation(Order $order, VendorOrder $vendorOrder, Product $product, int $quantity): void
    {
        $item = $order->items()->create([
            'vendor_order_id' => $vendorOrder->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => 10,
            'line_total' => 10 * $quantity,
        ]);

        StockReservation::query()->create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'vendor_order_id' => $vendorOrder->id,
            'order_item_id' => $item->id,
            'quantity' => $quantity,
            'status' => 'reserved',
            'reserved_at' => now(),
        ]);
    }
}
