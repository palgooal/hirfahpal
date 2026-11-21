<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E2-MVP batch 05 (VEN-BE-023, the four vendor actions: accept, reject, preparing, ready): the response keeps message,
 * order.status and allowed_actions, and its order is the VendorOrderJson allowlist: no customer email, phone or account
 * data, no parent order totals or notes, nothing about another vendor's order, no driver cash or admin references.
 */
class VendorOrderActionJsonTest extends TestCase
{
    use RefreshDatabase;

    /** Unique customer phone numbers across the fixtures. */
    private static int $sequence = 0;

    private const SECRETS = ['customer-act-', '0599888777', 'ORDER-NOTE-SECRET', 'OTHER-VENDOR-ORDER', '777.77', 'ASSIGN-NOTE-SECRET'];

    /** action route => [status before, status after, payload] */
    private const ACTIONS = [
        'accept' => ['pending', 'accepted', []],
        'reject' => ['pending', 'rejected', ['rejection_reason' => 'Out of clay this week.']],
        'preparing' => ['accepted', 'preparing', []],
        'ready' => ['preparing', 'ready_for_delivery', []],
    ];

    public function test_each_action_keeps_its_contract_and_publishes_only_the_allowlist(): void
    {
        foreach (self::ACTIONS as $action => [$before, $after, $payload]) {
            [$vendor, $vendorOrder] = $this->fixture($before);

            $response = $this->actingAs($vendor, 'vendor')->patchJson(route('vendor.dashboard.orders.'.$action, $vendorOrder), $payload)->assertOk();
            $json = $response->json();

            $this->assertSame(['message', 'order', 'allowed_actions'], array_keys($json), $action);
            $this->assertIsString($json['message'], $action);
            $this->assertSame([$vendorOrder->id, $after], [$json['order']['id'], $json['order']['status']], $action);
            $this->assertSame(['accept', 'reject', 'mark_preparing', 'mark_ready'], array_keys($json['allowed_actions']), $action);

            // The customer by name only; the parent order without its totals or notes.
            $this->assertSame(['id', 'name'], array_keys($json['order']['order']['customer']), $action);
            // VEN-BE-023 (ORD-BE-001's vendor part): no payment data or customer references either.
            $this->assertEqualsCanonicalizing(['id', 'number', 'status', 'created_at', 'updated_at', 'customer'], array_keys($json['order']['order']), $action);
            $this->assertSame('Vase', $json['order']['items'][0]['product_name'], $action);
            $this->assertSame(['id', 'name', 'slug', 'primary_image'], array_keys($json['order']['items'][0]['product']), $action);

            foreach (['email', 'phone', 'last_login_at', 'email_verified_at', 'phone_verified_at', 'avatar', 'grand_total', 'delivery_total', 'discount_total',
                'notes', 'cod_amount', 'cod_collected_amount', 'cod_collected_at', 'assigned_by', 'return_confirmed_by', 'decline_note', 'delivery_assignment',
                'stock_quantity', 'low_stock_threshold', 'delivery_address', 'address', 'payment_method', 'payment_status', 'customer_id',
                'customer_address_id'] as $key) {
                $this->assertNotContains($key, $this->keys($json), "{$action}: {$key}");
            }
            foreach (self::SECRETS as $secret) {
                $this->assertStringNotContainsString($secret, $response->getContent(), "{$action}: {$secret}");
            }
        }
    }

    public function test_an_action_on_another_vendors_order_is_refused_and_changes_nothing(): void
    {
        foreach (self::ACTIONS as $action => [$before, , $payload]) {
            [, $vendorOrder] = $this->fixture($before);
            $other = Vendor::factory()->approved()->create();

            $this->actingAs($other, 'vendor')->patchJson(route('vendor.dashboard.orders.'.$action, $vendorOrder), $payload)->assertNotFound();

            $this->assertSame($before, $vendorOrder->fresh()->status, $action);
        }
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

    /** @return array{0: Vendor, 1: VendorOrder} the vendor's order inside a two-vendor order, with every sensitive field filled */
    private function fixture(string $status): array
    {
        $vendor = Vendor::factory()->approved()->create();
        $customer = Customer::factory()->create(['email' => uniqid('customer-act-').'@example.test', 'phone' => '0599888777'.(++self::$sequence)]);
        $order = Order::query()->create(['number' => 'HF-'.uniqid(), 'customer_id' => $customer->id, 'status' => 'processing', 'payment_method' => 'cod',
            'payment_status' => 'pending', 'subtotal' => 777.77, 'delivery_total' => 0, 'grand_total' => 777.77, 'notes' => 'ORDER-NOTE-SECRET']);
        $vendorOrder = VendorOrder::query()->create(['number' => 'VO-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => $vendor->id, 'status' => $status,
            'subtotal' => 30, 'delivery_fee' => 0, 'commission_amount' => 3, 'total' => 30, 'vendor_response_due_at' => now()->addDay(),
            'accepted_at' => $status === 'pending' ? null : now()]);
        VendorOrder::query()->create(['number' => uniqid('OTHER-VENDOR-ORDER-'), 'order_id' => $order->id, 'vendor_id' => Vendor::factory()->approved()->create()->id,
            'status' => 'accepted', 'subtotal' => 747.77, 'delivery_fee' => 0, 'commission_amount' => 0, 'total' => 747.77]);
        $product = Product::query()->create(['vendor_id' => $vendor->id, 'name' => 'Vase', 'slug' => 'vase-'.uniqid(), 'price' => 30, 'stock_quantity' => 9,
            'low_stock_threshold' => 1, 'status' => 'active']);
        $item = $order->items()->create(['vendor_order_id' => $vendorOrder->id, 'product_id' => $product->id, 'product_name' => 'Vase', 'quantity' => 1,
            'unit_price' => 30, 'line_total' => 30]);
        // The checkout reservation the vendor's accept commits (committed once accepted).
        StockReservation::query()->create(['product_id' => $product->id, 'order_id' => $order->id, 'vendor_order_id' => $vendorOrder->id,
            'order_item_id' => $item->id, 'quantity' => 1, 'status' => $status === 'pending' ? 'reserved' : 'committed', 'reserved_at' => now(),
            'committed_at' => $status === 'pending' ? null : now()]);
        // An attempt with admin and cash fields, never loaded by the action responses.
        $driver = DeliveryDriver::factory()->create(['status' => 'active']);
        DeliveryAssignment::query()->create(['vendor_order_id' => $vendorOrder->id, 'delivery_driver_id' => $driver->id,
            'assigned_by' => Admin::factory()->create(['super_admin' => true, 'status' => 'active'])->id, 'status' => 'cancelled', 'assigned_at' => now(),
            'notes' => 'ASSIGN-NOTE-SECRET', 'cod_amount' => 30]);

        return [$vendor, $vendorOrder];
    }
}
