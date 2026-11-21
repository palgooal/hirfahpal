<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\DriverCashSettlement;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E2-MVP batch 05 (VEN-BE-023, GET vendor/dashboard/orders/{vendorOrder}): the vendor's order JSON is an explicit
 * allowlist. What fulfilling the order needs is there; the cash collected on delivery, the cash custody, internal admin
 * references, the drivers' notes, the driver's email and approval data and the customer's email are not, even with the
 * nested relations loaded.
 */
class VendorOrderShowJsonTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETS = ['customer-hidden@example.test', 'driver-hidden@example.test', 'ASSIGN-NOTE-SECRET', 'DECLINE-NOTE-SECRET',
        'FAILURE-NOTE-SECRET', 'SETTLEMENT-NOTE-SECRET', 'Driver approval secret'];

    public function test_the_vendor_gets_what_the_order_needs_and_nothing_sensitive(): void
    {
        [$vendor, $vendorOrder, $driver] = $this->fixture();

        $response = $this->actingAs($vendor, 'vendor')->getJson(route('vendor.dashboard.orders.show', $vendorOrder))->assertOk();
        $json = $response->json();
        $order = $json['order'];

        $this->assertSame(['order', 'allowed_actions'], array_keys($json));
        $this->assertSame([$vendorOrder->number, 'delivered', '42.50'], [$order['number'], $order['status'], $order['total']]);
        // VEN-BE-023 (ORD-BE-001's vendor part): no delivery address, payment data or customer references.
        $this->assertEqualsCanonicalizing(['id', 'number', 'status', 'created_at', 'updated_at', 'customer'], array_keys($order['order']));
        $this->assertSame(['id', 'name'], array_keys($order['order']['customer']));
        $this->assertSame('Bowl', $order['items'][0]['product_name']);
        $this->assertSame(2, $order['items'][0]['quantity']);
        $this->assertSame(['id', 'name', 'slug'], array_slice(array_keys($order['items'][0]['product']), 0, 3));
        $this->assertSame(['id', 'name', 'phone'], array_keys($order['delivery_driver']));
        $this->assertSame([$driver->name, $driver->phone], [$order['delivery_driver']['name'], $order['delivery_driver']['phone']]);
        $this->assertEqualsCanonicalizing(['id', 'vendor_order_id', 'delivery_driver_id', 'status', 'assigned_at', 'picked_up_at', 'delivered_at', 'failed_at',
            'failure_code', 'returned_at', 'return_declared_at', 'created_at', 'updated_at', 'delivery_driver'], array_keys($order['delivery_assignment']));
        $this->assertSame(['id', 'name', 'phone'], array_keys($order['delivery_assignment']['delivery_driver']));
        $this->assertSame('committed', $order['stock_reservations'][0]['status']);

        // Nowhere in the nested payload.
        foreach (['cod_amount', 'cod_collected_amount', 'cod_collected_at', 'return_confirmed_by', 'assigned_by', 'decline_note', 'decline_code',
            'declined_at', 'failure_reason', 'email', 'password', 'remember_token', 'approval_status', 'approved_by', 'approved_at',
            'profile', 'grand_total', 'discount_total', 'cash_balance', 'settlements', 'reverses_id', 'recorded_by',
            // VEN-BE-023: no delivery address (and so no address notes) or payment data at any depth.
            'notes', 'delivery_address', 'address', 'recipient_name', 'latitude', 'longitude', 'payment_method', 'payment_status',
            'customer_id', 'customer_address_id'] as $key) {
            $this->assertNotContains($key, $this->keys($json), $key);
        }
        foreach (self::SECRETS as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent(), $secret);
        }
    }

    public function test_another_vendors_order_stays_out_of_reach(): void
    {
        [, $vendorOrder] = $this->fixture();
        $other = Vendor::factory()->approved()->create();

        $this->actingAs($other, 'vendor')->getJson(route('vendor.dashboard.orders.show', $vendorOrder))->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->getJson(route('vendor.dashboard.orders.show', $vendorOrder))->assertUnauthorized();
    }

    /** @return list<string> every key of the payload, at any depth */
    private function keys(array $data): array
    {
        $keys = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $keys[] = $key;
            }
            if (is_array($value)) {
                $keys = [...$keys, ...$this->keys($value)];
            }
        }

        return $keys;
    }

    /** @return array{0: Vendor, 1: VendorOrder, 2: DeliveryDriver} a delivered cash-on-delivery order with every sensitive field filled */
    private function fixture(): array
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
        $vendor = Vendor::factory()->approved()->create();
        $customer = Customer::factory()->create(['email' => 'customer-hidden@example.test']);
        $driver = DeliveryDriver::factory()->create(['status' => 'active', 'email' => 'driver-hidden@example.test']);
        $driver->profile()->create(['approval_status' => 'approved', 'approved_at' => now(), 'approved_by' => $admin->id, 'is_available' => true,
            'rejection_reason' => 'Driver approval secret']);
        $order = Order::query()->create(['number' => 'HF-'.uniqid(), 'customer_id' => $customer->id, 'status' => 'processing', 'payment_method' => 'cod',
            'payment_status' => 'pending', 'subtotal' => 42.50, 'delivery_total' => 0, 'grand_total' => 42.50]);
        $vendorOrder = VendorOrder::query()->create(['number' => 'VO-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => $vendor->id,
            'delivery_driver_id' => $driver->id, 'status' => 'delivered', 'subtotal' => 42.50, 'delivery_fee' => 0, 'commission_amount' => 4.25,
            'total' => 42.50, 'delivered_at' => now()]);
        $product = Product::query()->create(['vendor_id' => $vendor->id, 'name' => 'Bowl', 'slug' => 'bowl-'.uniqid(), 'price' => 21.25,
            'stock_quantity' => 3, 'low_stock_threshold' => 1, 'status' => 'active']);
        $item = $order->items()->create(['vendor_order_id' => $vendorOrder->id, 'product_id' => $product->id, 'product_name' => 'Bowl',
            'quantity' => 2, 'unit_price' => 21.25, 'line_total' => 42.50]);
        StockReservation::query()->create(['product_id' => $product->id, 'order_id' => $order->id, 'vendor_order_id' => $vendorOrder->id,
            'order_item_id' => $item->id, 'quantity' => 2, 'status' => 'committed', 'reserved_at' => now(), 'committed_at' => now()]);
        // An earlier declined attempt, then the delivered one with its cash collected.
        DeliveryAssignment::query()->create(['vendor_order_id' => $vendorOrder->id, 'delivery_driver_id' => $driver->id, 'assigned_by' => $admin->id,
            'status' => 'cancelled', 'assigned_at' => now(), 'declined_at' => now(), 'decline_code' => 'other', 'decline_note' => 'DECLINE-NOTE-SECRET']);
        DeliveryAssignment::query()->create(['vendor_order_id' => $vendorOrder->id, 'delivery_driver_id' => $driver->id, 'assigned_by' => $admin->id,
            'status' => 'delivered', 'assigned_at' => now(), 'picked_up_at' => now(), 'delivered_at' => now(), 'notes' => 'ASSIGN-NOTE-SECRET',
            'failure_reason' => 'FAILURE-NOTE-SECRET', 'return_confirmed_by' => $admin->id, 'cod_amount' => 42.50, 'cod_collected_amount' => 42.50,
            'cod_collected_at' => now()]);
        DriverCashSettlement::query()->create(['delivery_driver_id' => $driver->id, 'kind' => 'settlement', 'amount' => 10, 'recorded_by' => $admin->id,
            'note' => 'SETTLEMENT-NOTE-SECRET']);

        return [$vendor, $vendorOrder, $driver];
    }
}
