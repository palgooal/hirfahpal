<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\DriverCashSettlement;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * E2-MVP batch 05 (VEN-BE-023, GET vendor/dashboard/orders): each row of the vendor's orders list goes through the
 * VendorOrderJson allowlist; the paginator, the search, the filters and the order are unchanged, the checkout delivery
 * address stays, and nothing sensitive (customer account, parent totals, driver data, cash) appears at any depth.
 */
class VendorOrderListJsonTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETS = ['list-customer@example.test', '0598111222', 'list-driver@example.test', 'LIST-ORDER-NOTE', 'LIST-ASSIGN-NOTE',
        'LIST-DECLINE-NOTE', 'LIST-FAILURE-NOTE', 'LIST-SETTLEMENT-NOTE', 'LIST-APPROVAL-SECRET', 'LIST-OTHER-VENDOR', '999.99'];

    private Vendor $vendor;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->approved()->create();
        $this->admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
    }

    public function test_each_row_carries_the_list_fields_and_nothing_sensitive(): void
    {
        $vendorOrder = $this->sensitiveOrder();

        $response = $this->list()->assertOk();
        $json = $response->json();
        $row = $json['orders']['data'][0];

        $this->assertSame(['orders', 'statuses'], array_keys($json));
        // What vendor-resource-list.js reads: id, number, status, order.customer.name, created_at, total.
        $this->assertSame([$vendorOrder->id, $vendorOrder->number, 'delivered', 'Layla', '42.50'], [$row['id'], $row['number'], $row['status'], $row['order']['customer']['name'], $row['total']]);
        $this->assertIsString($row['created_at']);
        $this->assertSame(['id', 'name'], array_keys($row['order']['customer']));
        // VEN-BE-023 (ORD-BE-001's vendor part): the parent order by its number, status and customer name only; the
        // checkout delivery address (recipient, phone, line) and the payment data are never published to the vendor.
        $this->assertEqualsCanonicalizing(['id', 'number', 'status', 'created_at', 'updated_at', 'customer'], array_keys($row['order']));
        foreach (['Olive Street 7', '0597000000', 'checkout'] as $deliveryValue) {
            $this->assertStringNotContainsString($deliveryValue, $response->getContent(), $deliveryValue);
        }
        $this->assertSame(['id', 'name', 'phone'], array_keys($row['delivery_assignment']['delivery_driver']));

        foreach (['email', 'last_login_at', 'email_verified_at', 'phone_verified_at', 'avatar', 'grand_total', 'delivery_total', 'discount_total',
            'cod_amount', 'cod_collected_amount', 'cod_collected_at', 'assigned_by', 'return_confirmed_by', 'decline_note', 'decline_code', 'declined_at',
            'failure_reason', 'profile', 'approval_status', 'approved_by', 'cash_balance', 'recorded_by', 'reverses_id',
            'delivery_address', 'address', 'recipient_name', 'latitude', 'longitude', 'payment_method', 'payment_status', 'customer_id',
            'customer_address_id', 'notes'] as $key) {
            $this->assertNotContains($key, $this->keys($json), $key);
        }
        foreach (self::SECRETS as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent(), $secret);
        }
    }

    public function test_another_vendors_orders_never_appear_even_inside_a_shared_order(): void
    {
        $vendorOrder = $this->sensitiveOrder();
        VendorOrder::query()->create(['number' => 'LIST-OTHER-VENDOR-ONLY', 'order_id' => Order::query()->create(['number' => 'HF-'.uniqid(),
            'customer_id' => Customer::factory()->create()->id, 'status' => 'pending', 'payment_method' => 'cod', 'payment_status' => 'pending',
            'subtotal' => 10, 'delivery_total' => 0, 'grand_total' => 10])->id, 'vendor_id' => Vendor::factory()->approved()->create()->id,
            'status' => 'pending', 'subtotal' => 10, 'delivery_fee' => 0, 'commission_amount' => 0, 'total' => 10]);

        $response = $this->list()->assertOk();

        $this->assertSame([$vendorOrder->id], array_column($response->json('orders.data'), 'id'));
        $this->assertSame(1, $response->json('orders.total'));
        $this->assertStringNotContainsString('LIST-OTHER-VENDOR', $response->getContent());
    }

    public function test_the_pagination_and_the_newest_first_order_are_unchanged(): void
    {
        $ids = [];
        foreach (range(1, 15) as $i) {
            Carbon::setTestNow(now()->addMinute());
            $ids[] = $this->plainOrder("HF-PAGE-{$i}", 'pending')->id;
        }
        Carbon::setTestNow();

        $first = $this->list()->assertOk();
        $second = $this->list(['page' => 2])->assertOk();

        $this->assertSame([1, 2, 15, 12], [$first->json('orders.current_page'), $first->json('orders.last_page'), $first->json('orders.total'), count($first->json('orders.data'))]);
        $this->assertSame([2, 3], [$second->json('orders.current_page'), count($second->json('orders.data'))]);
        $this->assertSame(array_reverse($ids), [...array_column($first->json('orders.data'), 'id'), ...array_column($second->json('orders.data'), 'id')]);
        $this->assertStringContainsString('page=2', $first->json('orders.next_page_url'));
        foreach (['first_page_url', 'last_page_url', 'links', 'path', 'per_page', 'from', 'to', 'prev_page_url'] as $key) {
            $this->assertArrayHasKey($key, $first->json('orders'), $key);
        }
    }

    public function test_the_search_and_filters_are_unchanged(): void
    {
        $nour = $this->plainOrder('HF-SEARCH-1', 'pending', 'Nour');
        $sami = $this->plainOrder('HF-SEARCH-2', 'accepted', 'Sami');

        $this->assertSame([$nour->id], array_column($this->list(['search' => 'Nour'])->json('orders.data'), 'id'), 'By customer name.');
        $this->assertSame([$sami->id], array_column($this->list(['search' => 'HF-SEARCH-2'])->json('orders.data'), 'id'), 'By number.');
        $this->assertSame([$sami->id], array_column($this->list(['status' => 'accepted'])->json('orders.data'), 'id'));
        $this->assertSame([$nour->id], array_column($this->list(['needs_response' => 1])->json('orders.data'), 'id'));
        $this->assertStringContainsString('search=Nour', (string) $this->list(['search' => 'Nour', 'per_page' => 1])->json('orders.first_page_url'));
    }

    // ---------------------------------------------------------------- helpers

    private function list(array $query = [])
    {
        return $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.orders.index', $query));
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

    private function plainOrder(string $number, string $status, string $customerName = 'Customer'): VendorOrder
    {
        $order = Order::query()->create(['number' => $number.'-P', 'customer_id' => Customer::factory()->create(['name' => $customerName])->id,
            'status' => 'pending', 'payment_method' => 'cod', 'payment_status' => 'pending', 'subtotal' => 10, 'delivery_total' => 0, 'grand_total' => 10]);

        return VendorOrder::query()->create(['number' => $number, 'order_id' => $order->id, 'vendor_id' => $this->vendor->id, 'status' => $status,
            'subtotal' => 10, 'delivery_fee' => 0, 'commission_amount' => 0, 'total' => 10]);
    }

    /** A delivered cash-on-delivery order shared with another vendor, with every sensitive field filled. */
    private function sensitiveOrder(): VendorOrder
    {
        $customer = Customer::factory()->create(['name' => 'Layla', 'email' => 'list-customer@example.test', 'phone' => '0598111222']);
        $driver = DeliveryDriver::factory()->create(['status' => 'active', 'email' => 'list-driver@example.test']);
        $driver->profile()->create(['approval_status' => 'approved', 'approved_at' => now(), 'approved_by' => $this->admin->id, 'is_available' => true,
            'rejection_reason' => 'LIST-APPROVAL-SECRET']);
        $order = Order::query()->create(['number' => 'HF-'.uniqid(), 'customer_id' => $customer->id, 'status' => 'processing', 'payment_method' => 'cod',
            'payment_status' => 'pending', 'subtotal' => 999.99, 'delivery_total' => 0, 'grand_total' => 999.99, 'notes' => 'LIST-ORDER-NOTE',
            'delivery_address_source' => 'checkout', 'delivery_recipient_name' => 'Layla', 'delivery_phone' => '0597000000',
            'delivery_address_line' => 'Olive Street 7']);
        $vendorOrder = VendorOrder::query()->create(['number' => 'VO-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => $this->vendor->id,
            'delivery_driver_id' => $driver->id, 'status' => 'delivered', 'subtotal' => 42.50, 'delivery_fee' => 0, 'commission_amount' => 4.25,
            'total' => 42.50, 'delivered_at' => now()]);
        VendorOrder::query()->create(['number' => 'LIST-OTHER-VENDOR-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => Vendor::factory()->approved()->create()->id,
            'status' => 'accepted', 'subtotal' => 957.49, 'delivery_fee' => 0, 'commission_amount' => 0, 'total' => 957.49]);
        DeliveryAssignment::query()->create(['vendor_order_id' => $vendorOrder->id, 'delivery_driver_id' => $driver->id, 'assigned_by' => $this->admin->id,
            'status' => 'cancelled', 'assigned_at' => now(), 'declined_at' => now(), 'decline_code' => 'other', 'decline_note' => 'LIST-DECLINE-NOTE']);
        DeliveryAssignment::query()->create(['vendor_order_id' => $vendorOrder->id, 'delivery_driver_id' => $driver->id, 'assigned_by' => $this->admin->id,
            'status' => 'delivered', 'assigned_at' => now(), 'picked_up_at' => now(), 'delivered_at' => now(), 'notes' => 'LIST-ASSIGN-NOTE',
            'failure_reason' => 'LIST-FAILURE-NOTE', 'return_confirmed_by' => $this->admin->id, 'cod_amount' => 42.50, 'cod_collected_amount' => 42.50,
            'cod_collected_at' => now()]);
        DriverCashSettlement::query()->create(['delivery_driver_id' => $driver->id, 'kind' => 'settlement', 'amount' => 10, 'recorded_by' => $this->admin->id,
            'note' => 'LIST-SETTLEMENT-NOTE']);

        return $vendorOrder;
    }
}
