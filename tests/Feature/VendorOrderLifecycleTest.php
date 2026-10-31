<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Review;
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

    public function test_driver_delivery_does_not_complete_the_order_or_mark_cod_paid(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $driver = DeliveryDriver::factory()->create();
        $driver->profile()->create(['approval_status' => 'approved', 'approved_at' => now(), 'is_available' => true]);
        [$order, $vendorOrder] = $this->orderWithVendorOrder($vendor, status: 'out_for_delivery');
        $order->update(['status' => 'processing', 'payment_status' => 'pending']);
        Payment::query()->create([
            'order_id' => $order->id,
            'method' => 'cod',
            'status' => 'pending',
            'amount' => 10,
        ]);
        $assignment = DeliveryAssignment::query()->create([
            'vendor_order_id' => $vendorOrder->id,
            'delivery_driver_id' => $driver->id,
            'status' => 'picked_up',
            'assigned_at' => now(),
            'picked_up_at' => now(),
        ]);
        $vendorOrder->update(['delivery_driver_id' => $driver->id]);

        $this->actingAs($driver, 'delivery_driver')
            ->patchJson(route('delivery-driver.dashboard.assignments.delivered', $assignment))
            ->assertOk()
            ->assertJsonPath('assignment.vendor_order.status', 'delivered')
            ->assertJsonPath('assignment.vendor_order.order.payment.status', 'pending');

        $this->assertSame('delivered', $vendorOrder->fresh()->status);
        $this->assertNull($vendorOrder->fresh()->customer_receipt_confirmed_at);
        $this->assertNull($vendorOrder->fresh()->completed_at);
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('pending', Payment::sole()->status);
    }

    public function test_customer_receipt_confirmation_completes_delivered_vendor_order(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        [$order, $vendorOrder] = $this->orderWithVendorOrder($vendor, status: 'delivered');
        $order->update(['status' => 'processing', 'payment_status' => 'pending']);
        Payment::query()->create([
            'order_id' => $order->id,
            'method' => 'cod',
            'status' => 'pending',
            'amount' => 10,
        ]);

        $this->actingAs($order->customer, 'customer')
            ->patchJson(route('customer.orders.vendor-orders.confirm-receipt', [$order, $vendorOrder]))
            ->assertOk()
            ->assertJsonPath('order.status', 'completed')
            ->assertJsonPath('order.payment.status', 'pending');

        $this->assertSame('completed', $vendorOrder->fresh()->status);
        $this->assertNotNull($vendorOrder->fresh()->customer_receipt_confirmed_at);
        $this->assertNotNull($vendorOrder->fresh()->completed_at);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_customer_cannot_confirm_receipt_before_delivery(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        [$order, $vendorOrder] = $this->orderWithVendorOrder($vendor, status: 'out_for_delivery');

        $this->actingAs($order->customer, 'customer')
            ->patchJson(route('customer.orders.vendor-orders.confirm-receipt', [$order, $vendorOrder]))
            ->assertStatus(422);

        $this->assertSame('out_for_delivery', $vendorOrder->fresh()->status);
        $this->assertNull($vendorOrder->fresh()->customer_receipt_confirmed_at);
    }

    public function test_completed_vendor_order_can_be_reviewed_when_parent_is_partially_cancelled(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $otherVendor = Vendor::factory()->approved()->create();
        $product = $this->product($vendor);
        [$order, $vendorOrder] = $this->orderWithVendorOrder($vendor, status: 'completed');
        $order->update(['status' => 'partially_cancelled']);
        $this->vendorOrder($order, $otherVendor, status: 'rejected');
        $this->itemAndReservation($order, $vendorOrder, $product, 1);

        $this->actingAs($order->customer, 'customer')
            ->postJson(route('customer.orders.reviews.store', $order), [
                'vendor_order_id' => $vendorOrder->id,
                'product_id' => $product->id,
                'product_rating' => 5,
                'vendor_rating' => 5,
            ])
            ->assertCreated();

        $this->assertSame(2, Review::count());
    }

    public function test_customer_can_add_a_product_review_after_reviewing_the_store(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $product = $this->product($vendor);
        [$order, $vendorOrder] = $this->orderWithVendorOrder($vendor, status: 'completed');
        $this->itemAndReservation($order, $vendorOrder, $product, 1);

        $this->actingAs($order->customer, 'customer')
            ->postJson(route('customer.orders.reviews.store', $order), [
                'vendor_order_id' => $vendorOrder->id,
                'vendor_rating' => 4,
            ])
            ->assertCreated();

        $this->actingAs($order->customer, 'customer')
            ->postJson(route('customer.orders.reviews.store', $order), [
                'vendor_order_id' => $vendorOrder->id,
                'product_id' => $product->id,
                'product_rating' => 5,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('reviews', [
            'vendor_order_id' => $vendorOrder->id,
            'product_id' => $product->id,
            'reviewable_type' => 'product',
            'rating' => 5,
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('reviews', [
            'vendor_order_id' => $vendorOrder->id,
            'vendor_id' => $vendor->id,
            'reviewable_type' => 'vendor',
            'rating' => 4,
        ]);
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
            'accepted_at' => in_array($status, ['accepted', 'preparing', 'ready_for_delivery', 'assigned', 'out_for_delivery', 'delivered', 'completed'], true) ? now() : null,
            'ready_at' => in_array($status, ['ready_for_delivery', 'assigned', 'out_for_delivery', 'delivered', 'completed'], true) ? now() : null,
            'delivered_at' => in_array($status, ['delivered', 'completed'], true) ? now() : null,
            'customer_receipt_confirmed_at' => $status === 'completed' ? now() : null,
            'completed_at' => $status === 'completed' ? now() : null,
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
