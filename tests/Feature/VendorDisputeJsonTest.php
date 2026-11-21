<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * E2-MVP (VEN-BE-023, GET vendor/dashboard/disputes and disputes/{dispute}): a dispute goes through the
 * VendorDisputeJson allowlist in the list and the detail. What the customer raised, its status, the admin's resolution
 * and when it was resolved, the customer's name, the parent order's id and number and the vendor's own order are there;
 * the resolving admin, the customer's account and the parent order's totals, notes and payment data are not; the store
 * isolation, the filters, the order and the paginator are unchanged.
 */
class VendorDisputeJsonTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETS = ['dispute-customer@example.test', '0593777888', 'DISPUTE-ORDER-NOTE', '432.10', 'OTHER-STORE-DISPUTE', 'ORDER-LEVEL-DISPUTE'];

    private const EXCLUDED_KEYS = ['resolved_by', 'customer_id', 'email', 'phone', 'last_login_at', 'email_verified_at', 'phone_verified_at', 'avatar',
        'grand_total', 'delivery_total', 'discount_total', 'notes', 'payment_method', 'payment_status', 'customer_address_id'];

    private Vendor $vendor;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->approved()->create();
        $this->admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
    }

    public function test_the_list_and_the_detail_publish_the_allowlist_with_the_resolution_and_nothing_sensitive(): void
    {
        [$open, $resolved] = $this->fixture();

        $list = $this->list()->assertOk();
        $show = $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.disputes.show', $resolved))->assertOk();

        $this->assertSame(['disputes'], array_keys($list->json()));
        $this->assertSame(['dispute'], array_keys($show->json()));
        $row = collect($list->json('disputes.data'))->firstWhere('id', $resolved->id);
        $detail = $show->json('dispute');

        foreach (['list' => $row, 'detail' => $detail] as $context => $dispute) {
            // What vendor-resource-list.js reads: id, type, status, customer.name, created_at.
            $this->assertSame([$resolved->id, 'delivery', 'resolved', 'Rami'], [$dispute['id'], $dispute['type'], $dispute['status'], $dispute['customer']['name']], $context);
            $this->assertIsString($dispute['created_at'], $context);
            $this->assertSame(['Late parcel', 'Arrived four days late.'], [$dispute['subject'], $dispute['description']], $context);
            // The admin's shareable outcome, and when it was decided.
            $this->assertSame('Delivery fee refunded to the customer.', $dispute['resolution'], $context);
            $this->assertNotNull($dispute['resolved_at'], $context);
            $this->assertEqualsCanonicalizing(['id', 'vendor_order_id', 'type', 'status', 'subject', 'description', 'resolution', 'resolved_at', 'created_at',
                'updated_at', 'order', 'vendor_order', 'customer'], array_keys($dispute), $context);
            $this->assertSame(['name'], array_keys($dispute['customer']), $context);
            $this->assertSame(['id' => $resolved->order_id, 'number' => $resolved->order->number], $dispute['order'], $context);
            $this->assertSame([$resolved->vendor_order_id, '60.00'], [$dispute['vendor_order']['id'], $dispute['vendor_order']['total']], $context);
            $this->assertSame(['Clay Bowl', 2, '30.00', '60.00'], [$dispute['vendor_order']['items'][0]['product_name'], $dispute['vendor_order']['items'][0]['quantity'],
                $dispute['vendor_order']['items'][0]['unit_price'], $dispute['vendor_order']['items'][0]['line_total']], $context);
        }
        $this->assertNull(collect($list->json('disputes.data'))->firstWhere('id', $open->id)['resolution'], 'An open dispute has no resolution yet.');

        foreach (['list' => $list, 'detail' => $show] as $context => $response) {
            foreach (self::EXCLUDED_KEYS as $key) {
                $this->assertNotContains($key, $this->keys($response->json()), "{$context}: {$key}");
            }
            foreach (self::SECRETS as $secret) {
                $this->assertStringNotContainsString($secret, $response->getContent(), "{$context}: {$secret}");
            }
        }
    }

    public function test_only_this_stores_disputes_are_listed_or_shown(): void
    {
        [$open, $resolved, $otherStore, $orderLevel] = $this->fixture();

        $this->assertEqualsCanonicalizing([$open->id, $resolved->id], array_column($this->list()->json('disputes.data'), 'id'));
        $this->assertSame(2, $this->list()->json('disputes.total'));

        foreach ([$otherStore, $orderLevel] as $dispute) {
            $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.disputes.show', $dispute))->assertNotFound();
        }
    }

    public function test_the_filters_order_and_paginator_are_unchanged(): void
    {
        [$open, $resolved] = $this->fixture();

        $this->assertSame([$resolved->id], array_column($this->list(['status' => 'resolved'])->json('disputes.data'), 'id'));
        $this->assertSame([$open->id], array_column($this->list(['status' => 'open'])->json('disputes.data'), 'id'));
        $this->assertSame([$open->id], array_column($this->list(['type' => 'product'])->json('disputes.data'), 'id'));
        $this->assertSame([$resolved->id], array_column($this->list(['type' => 'delivery'])->json('disputes.data'), 'id'));

        $first = $this->list(['per_page' => 1]);
        $second = $this->list(['per_page' => 1, 'page' => 2]);
        $this->assertSame([1, 2, 2], [$first->json('disputes.current_page'), $first->json('disputes.last_page'), $first->json('disputes.total')]);
        $this->assertSame([$resolved->id, $open->id], [$first->json('disputes.data.0.id'), $second->json('disputes.data.0.id')], 'Newest first.');
        foreach (['first_page_url', 'last_page_url', 'next_page_url', 'prev_page_url', 'links', 'path', 'per_page', 'from', 'to'] as $key) {
            $this->assertArrayHasKey($key, $first->json('disputes'), $key);
        }
        $this->assertStringContainsString('per_page=1', $first->json('disputes.next_page_url'));
    }

    public function test_the_access_rules_are_unchanged(): void
    {
        [, $resolved] = $this->fixture();
        $pending = Vendor::factory()->withProfile('pending')->create();

        $this->actingAs($pending, 'vendor')->getJson(route('vendor.dashboard.disputes.index'))->assertForbidden()->assertJsonPath('approval_status', 'pending');
        $this->actingAs($pending, 'vendor')->getJson(route('vendor.dashboard.disputes.show', $resolved))->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->getJson(route('vendor.dashboard.disputes.index'))->assertUnauthorized();
        $this->getJson(route('vendor.dashboard.disputes.show', $resolved))->assertUnauthorized();
    }

    private function list(array $query = [])
    {
        return $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.disputes.index', $query));
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

    /** @return array{0: Dispute, 1: Dispute, 2: Dispute, 3: Dispute} this store's open and resolved disputes, another store's, an order-level one */
    private function fixture(): array
    {
        $customer = Customer::factory()->create(['name' => 'Rami', 'email' => 'dispute-customer@example.test', 'phone' => '0593777888']);
        $order = Order::query()->create(['number' => 'HF-'.uniqid(), 'customer_id' => $customer->id, 'status' => 'completed', 'payment_method' => 'cod',
            'payment_status' => 'paid', 'subtotal' => 432.10, 'delivery_total' => 0, 'grand_total' => 432.10, 'notes' => 'DISPUTE-ORDER-NOTE']);
        $vendorOrder = VendorOrder::query()->create(['number' => 'VO-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => $this->vendor->id,
            'status' => 'completed', 'subtotal' => 60, 'delivery_fee' => 0, 'commission_amount' => 6, 'total' => 60]);
        $otherVendorOrder = VendorOrder::query()->create(['number' => 'VO-OTHER-'.uniqid(), 'order_id' => $order->id,
            'vendor_id' => Vendor::factory()->approved()->create()->id, 'status' => 'completed', 'subtotal' => 372.10, 'delivery_fee' => 0,
            'commission_amount' => 0, 'total' => 372.10]);
        $product = Product::query()->create(['vendor_id' => $this->vendor->id, 'name' => 'Clay Bowl', 'slug' => 'clay-bowl-'.uniqid(), 'price' => 30,
            'stock_quantity' => 2, 'low_stock_threshold' => 1, 'status' => 'active']);
        $order->items()->create(['vendor_order_id' => $vendorOrder->id, 'product_id' => $product->id, 'product_name' => 'Clay Bowl', 'quantity' => 2,
            'unit_price' => 30, 'line_total' => 60]);

        Carbon::setTestNow(now()->subMinutes(10));
        $open = Dispute::query()->create(['order_id' => $order->id, 'vendor_order_id' => $vendorOrder->id, 'customer_id' => $customer->id, 'type' => 'product',
            'status' => 'open', 'subject' => 'Chipped rim', 'description' => 'The rim was chipped.']);
        Carbon::setTestNow(now()->addMinutes(5));
        $resolved = Dispute::query()->create(['order_id' => $order->id, 'vendor_order_id' => $vendorOrder->id, 'customer_id' => $customer->id,
            'type' => 'delivery', 'status' => 'resolved', 'subject' => 'Late parcel', 'description' => 'Arrived four days late.',
            'resolution' => 'Delivery fee refunded to the customer.', 'resolved_by' => $this->admin->id, 'resolved_at' => now()]);
        Carbon::setTestNow();
        $otherStore = Dispute::query()->create(['order_id' => $order->id, 'vendor_order_id' => $otherVendorOrder->id, 'customer_id' => $customer->id,
            'type' => 'other', 'status' => 'open', 'subject' => 'OTHER-STORE-DISPUTE', 'description' => 'OTHER-STORE-DISPUTE']);
        $orderLevel = Dispute::query()->create(['order_id' => $order->id, 'vendor_order_id' => null, 'customer_id' => $customer->id, 'type' => 'payment',
            'status' => 'open', 'subject' => 'ORDER-LEVEL-DISPUTE', 'description' => 'ORDER-LEVEL-DISPUTE']);

        return [$open->load('order'), $resolved->load('order'), $otherStore, $orderLevel];
    }
}
