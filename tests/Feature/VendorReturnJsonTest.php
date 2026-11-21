<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * E2-MVP (VEN-BE-023, GET vendor/dashboard/returns and returns/{returnRequest}): a return goes through the
 * VendorReturnJson allowlist in the list and the detail. The request, the customer's name, the parent order's id and
 * number and the vendor's own order with its items, quantities and amounts are there; the admin's note and identity,
 * the customer's account and the parent order's totals, notes and payment data are not; the store isolation, the status
 * filter, the order and the paginator are unchanged.
 */
class VendorReturnJsonTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETS = ['ADMIN-RETURN-NOTE', 'return-customer@example.test', '0594222111', 'RETURN-ORDER-NOTE', '321.45', 'OTHER-STORE-RETURN',
        'ORDER-LEVEL-RETURN'];

    private const EXCLUDED_KEYS = ['admin_note', 'reviewed_by', 'customer_id', 'email', 'phone', 'last_login_at', 'email_verified_at',
        'phone_verified_at', 'avatar', 'grand_total', 'delivery_total', 'discount_total', 'notes', 'payment_method', 'payment_status', 'customer_address_id'];

    private Vendor $vendor;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->approved()->create();
        $this->admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
    }

    public function test_the_list_and_the_detail_publish_the_allowlist_and_nothing_sensitive(): void
    {
        [$older, $newer] = $this->fixture();

        $list = $this->list()->assertOk();
        $show = $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.returns.show', $newer))->assertOk();

        $this->assertSame(['returns'], array_keys($list->json()));
        $this->assertSame(['return'], array_keys($show->json()));
        $row = collect($list->json('returns.data'))->firstWhere('id', $newer->id);
        $detail = $show->json('return');

        foreach (['list' => $row, 'detail' => $detail] as $context => $return) {
            // What vendor-resource-list.js reads: id, status, customer.name, reason, created_at.
            $this->assertSame([$newer->id, 'approved', 'Mona', 'Cracked on arrival.'], [$return['id'], $return['status'], $return['customer']['name'], $return['reason']], $context);
            $this->assertIsString($return['created_at'], $context);
            $this->assertNotNull($return['reviewed_at'], $context);
            $this->assertEqualsCanonicalizing(['id', 'vendor_order_id', 'status', 'reason', 'reviewed_at', 'created_at', 'updated_at', 'order', 'vendor_order', 'customer'],
                array_keys($return), $context);
            $this->assertSame(['name'], array_keys($return['customer']), $context);
            $this->assertSame(['id' => $newer->order_id, 'number' => $newer->order->number], $return['order'], $context);
            // The vendor's own order: its items, quantities and amounts.
            $this->assertSame([$newer->vendor_order_id, '45.00'], [$return['vendor_order']['id'], $return['vendor_order']['total']], $context);
            $this->assertSame(['Clay Plate', 3, '15.00', '45.00'], [$return['vendor_order']['items'][0]['product_name'], $return['vendor_order']['items'][0]['quantity'],
                $return['vendor_order']['items'][0]['unit_price'], $return['vendor_order']['items'][0]['line_total']], $context);
        }
        $this->assertSame('products/plate.jpg', $row['vendor_order']['items'][0]['product']['primary_image']['path']);

        foreach (['list' => $list, 'detail' => $show] as $context => $response) {
            foreach (self::EXCLUDED_KEYS as $key) {
                $this->assertNotContains($key, $this->keys($response->json()), "{$context}: {$key}");
            }
            foreach (self::SECRETS as $secret) {
                $this->assertStringNotContainsString($secret, $response->getContent(), "{$context}: {$secret}");
            }
        }
        $this->assertNotNull($older);
    }

    public function test_only_this_stores_returns_are_listed_or_shown(): void
    {
        [$older, $newer, $otherStore, $orderLevel] = $this->fixture();

        $this->assertEqualsCanonicalizing([$older->id, $newer->id], array_column($this->list()->json('returns.data'), 'id'));
        $this->assertSame(2, $this->list()->json('returns.total'));

        foreach ([$otherStore, $orderLevel] as $return) {
            $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.returns.show', $return))->assertNotFound();
        }
    }

    public function test_the_status_filter_order_and_paginator_are_unchanged(): void
    {
        [$older, $newer] = $this->fixture();

        $this->assertSame([$newer->id], array_column($this->list(['status' => 'approved'])->json('returns.data'), 'id'));
        $this->assertSame([$older->id], array_column($this->list(['status' => 'requested'])->json('returns.data'), 'id'));

        $first = $this->list(['per_page' => 1]);
        $second = $this->list(['per_page' => 1, 'page' => 2]);
        $this->assertSame([1, 2, 2], [$first->json('returns.current_page'), $first->json('returns.last_page'), $first->json('returns.total')]);
        $this->assertSame([$newer->id, $older->id], [$first->json('returns.data.0.id'), $second->json('returns.data.0.id')], 'Newest first.');
        foreach (['first_page_url', 'last_page_url', 'next_page_url', 'prev_page_url', 'links', 'path', 'per_page', 'from', 'to'] as $key) {
            $this->assertArrayHasKey($key, $first->json('returns'), $key);
        }
        $this->assertStringContainsString('per_page=1', $first->json('returns.next_page_url'));
    }

    public function test_the_access_rules_are_unchanged(): void
    {
        [, $newer] = $this->fixture();
        $pending = Vendor::factory()->withProfile('pending')->create();

        $this->actingAs($pending, 'vendor')->getJson(route('vendor.dashboard.returns.index'))->assertForbidden()->assertJsonPath('approval_status', 'pending');
        $this->actingAs($pending, 'vendor')->getJson(route('vendor.dashboard.returns.show', $newer))->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->getJson(route('vendor.dashboard.returns.index'))->assertUnauthorized();
        $this->getJson(route('vendor.dashboard.returns.show', $newer))->assertUnauthorized();
    }

    private function list(array $query = [])
    {
        return $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.returns.index', $query));
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

    /** @return array{0: ReturnRequest, 1: ReturnRequest, 2: ReturnRequest, 3: ReturnRequest} this store's two returns, another store's, an order-level one */
    private function fixture(): array
    {
        $customer = Customer::factory()->create(['name' => 'Mona', 'email' => 'return-customer@example.test', 'phone' => '0594222111']);
        $order = Order::query()->create(['number' => 'HF-'.uniqid(), 'customer_id' => $customer->id, 'status' => 'completed', 'payment_method' => 'online',
            'payment_status' => 'paid', 'subtotal' => 321.45, 'delivery_total' => 0, 'grand_total' => 321.45, 'notes' => 'RETURN-ORDER-NOTE']);
        $vendorOrder = VendorOrder::query()->create(['number' => 'VO-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => $this->vendor->id,
            'status' => 'completed', 'subtotal' => 45, 'delivery_fee' => 0, 'commission_amount' => 4.5, 'total' => 45]);
        $otherVendorOrder = VendorOrder::query()->create(['number' => 'VO-OTHER-'.uniqid(), 'order_id' => $order->id,
            'vendor_id' => Vendor::factory()->approved()->create()->id, 'status' => 'completed', 'subtotal' => 276.45, 'delivery_fee' => 0,
            'commission_amount' => 0, 'total' => 276.45]);
        $product = Product::query()->create(['vendor_id' => $this->vendor->id, 'name' => 'Clay Plate', 'slug' => 'clay-plate-'.uniqid(), 'price' => 15,
            'stock_quantity' => 2, 'low_stock_threshold' => 1, 'status' => 'active']);
        $product->images()->create(['path' => 'products/plate.jpg', 'is_primary' => true, 'sort_order' => 0]);
        $order->items()->create(['vendor_order_id' => $vendorOrder->id, 'product_id' => $product->id, 'product_name' => 'Clay Plate', 'quantity' => 3,
            'unit_price' => 15, 'line_total' => 45]);

        Carbon::setTestNow(now()->subMinutes(10));
        $older = ReturnRequest::query()->create(['order_id' => $order->id, 'vendor_order_id' => $vendorOrder->id, 'customer_id' => $customer->id,
            'status' => 'requested', 'reason' => 'Wrong colour.']);
        Carbon::setTestNow(now()->addMinutes(5));
        $newer = ReturnRequest::query()->create(['order_id' => $order->id, 'vendor_order_id' => $vendorOrder->id, 'customer_id' => $customer->id,
            'status' => 'approved', 'reason' => 'Cracked on arrival.', 'admin_note' => 'ADMIN-RETURN-NOTE', 'reviewed_by' => $this->admin->id,
            'reviewed_at' => now()]);
        Carbon::setTestNow();
        $otherStore = ReturnRequest::query()->create(['order_id' => $order->id, 'vendor_order_id' => $otherVendorOrder->id, 'customer_id' => $customer->id,
            'status' => 'requested', 'reason' => 'OTHER-STORE-RETURN']);
        $orderLevel = ReturnRequest::query()->create(['order_id' => $order->id, 'vendor_order_id' => null, 'customer_id' => $customer->id,
            'status' => 'requested', 'reason' => 'ORDER-LEVEL-RETURN']);

        return [$older->load('order'), $newer->load('order'), $otherStore, $orderLevel];
    }
}
