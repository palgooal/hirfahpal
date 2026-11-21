<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * E2-MVP (VEN-BE-023, GET vendor/dashboard/commissions): each commission goes through the VendorCommissionJson
 * allowlist. The record as calculated (rate, base amount, amount, status, earned date) and the vendor's own order
 * amounts are there, the parent order by id and number only; the parent order's totals, notes and payment data, the
 * customer's address reference and the vendor id are not. The isolation, the summary, the status filter, the order and
 * the paginator are unchanged.
 */
class VendorCommissionJsonTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETS = ['COMMISSION-ORDER-NOTE', '987.65', '927.65', '99.99'];

    private const EXCLUDED_KEYS = ['vendor_id', 'grand_total', 'delivery_total', 'discount_total', 'notes', 'payment_method', 'payment_status',
        'customer_address_id', 'customer_id', 'commission_amount', 'rejection_reason', 'delivery_driver_id', 'customer'];

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->approved()->create();
    }

    public function test_each_commission_carries_the_record_and_the_vendors_own_order_and_nothing_sensitive(): void
    {
        [$pending, $earned, $order] = $this->fixture();

        $response = $this->list()->assertOk();
        $json = $response->json();

        $this->assertSame(['commissions', 'summary'], array_keys($json));
        $row = collect($json['commissions']['data'])->firstWhere('id', $earned->id);

        $this->assertEqualsCanonicalizing(['id', 'vendor_order_id', 'rate', 'base_amount', 'amount', 'status', 'earned_at', 'created_at', 'updated_at', 'vendor_order'],
            array_keys($row));
        // The record as calculated: amount = base_amount × rate.
        $this->assertSame(['12.50', '40.00', '5.00', 'earned'], [$row['rate'], $row['base_amount'], $row['amount'], $row['status']]);
        $this->assertNotNull($row['earned_at']);
        $this->assertIsString($row['created_at']);
        // The vendor's own order, and the parent order by id and number only.
        $this->assertEqualsCanonicalizing(['id', 'number', 'status', 'subtotal', 'delivery_fee', 'total', 'order'], array_keys($row['vendor_order']));
        $this->assertSame([$earned->vendor_order_id, 'completed', '35.00', '5.00', '40.00'], [$row['vendor_order']['id'], $row['vendor_order']['status'],
            $row['vendor_order']['subtotal'], $row['vendor_order']['delivery_fee'], $row['vendor_order']['total']]);
        $this->assertSame(['id' => $order->id, 'number' => $order->number], $row['vendor_order']['order']);
        // What vendor-resource-list.js reads: vendor_order.number, amount, status, created_at.
        $this->assertSame($earned->vendorOrder->number, $row['vendor_order']['number']);

        // The summary is unchanged: this vendor's records only.
        $this->assertEquals(['pending' => 3.0, 'earned' => 5.0, 'paid' => 0.0, 'waived' => 0.0], $json['summary']);

        foreach (self::EXCLUDED_KEYS as $key) {
            $this->assertNotContains($key, $this->keys($json), $key);
        }
        foreach (self::SECRETS as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent(), $secret);
        }
        $this->assertNotNull($pending);
    }

    public function test_another_vendors_commission_never_appears_even_on_the_same_parent_order(): void
    {
        [$pending, $earned] = $this->fixture();

        $response = $this->list();

        $this->assertEqualsCanonicalizing([$pending->id, $earned->id], array_column($response->json('commissions.data'), 'id'));
        $this->assertSame(2, $response->json('commissions.total'));
        $this->assertEquals(0.0, $response->json('summary.paid'), 'The other vendor\'s paid commission is not counted.');
    }

    public function test_the_status_filter_order_and_paginator_are_unchanged(): void
    {
        [$pending, $earned] = $this->fixture();

        $this->assertSame([$earned->id], array_column($this->list(['status' => 'earned'])->json('commissions.data'), 'id'));
        $this->assertSame([$pending->id], array_column($this->list(['status' => 'pending'])->json('commissions.data'), 'id'));

        $first = $this->list(['per_page' => 1]);
        $second = $this->list(['per_page' => 1, 'page' => 2]);
        $this->assertSame([1, 2, 2], [$first->json('commissions.current_page'), $first->json('commissions.last_page'), $first->json('commissions.total')]);
        $this->assertSame([$earned->id, $pending->id], [$first->json('commissions.data.0.id'), $second->json('commissions.data.0.id')], 'Newest first.');
        foreach (['first_page_url', 'last_page_url', 'next_page_url', 'prev_page_url', 'links', 'path', 'per_page', 'from', 'to'] as $key) {
            $this->assertArrayHasKey($key, $first->json('commissions'), $key);
        }
        $this->assertStringContainsString('per_page=1', $first->json('commissions.next_page_url'));
    }

    public function test_the_access_rules_are_unchanged(): void
    {
        $pending = Vendor::factory()->withProfile('pending')->create();
        $this->actingAs($pending, 'vendor')->getJson(route('vendor.dashboard.commissions.index'))->assertForbidden()->assertJsonPath('approval_status', 'pending');

        $this->app['auth']->forgetGuards();
        $this->getJson(route('vendor.dashboard.commissions.index'))->assertUnauthorized();
    }

    private function list(array $query = [])
    {
        return $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.commissions.index', $query));
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

    /** @return array{0: Commission, 1: Commission, 2: Order} this vendor's pending and earned commissions on a parent order shared with another vendor */
    private function fixture(): array
    {
        $order = Order::query()->create(['number' => 'HF-'.uniqid(), 'customer_id' => Customer::factory()->create()->id, 'status' => 'processing',
            'payment_method' => 'online', 'payment_status' => 'paid', 'subtotal' => 987.65, 'delivery_total' => 0, 'grand_total' => 987.65,
            'notes' => 'COMMISSION-ORDER-NOTE']);
        $completed = VendorOrder::query()->create(['number' => 'VO-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => $this->vendor->id,
            'status' => 'completed', 'subtotal' => 35, 'delivery_fee' => 5, 'commission_amount' => 5, 'total' => 40]);
        $preparing = VendorOrder::query()->create(['number' => 'VO-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => $this->vendor->id,
            'status' => 'preparing', 'subtotal' => 20, 'delivery_fee' => 0, 'commission_amount' => 3, 'total' => 20]);
        $other = Vendor::factory()->approved()->create();
        $otherVendorOrder = VendorOrder::query()->create(['number' => 'VO-OTHER-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => $other->id,
            'status' => 'completed', 'subtotal' => 927.65, 'delivery_fee' => 0, 'commission_amount' => 99.99, 'total' => 927.65]);

        Carbon::setTestNow(now()->subMinutes(10));
        $pending = Commission::query()->create(['vendor_order_id' => $preparing->id, 'vendor_id' => $this->vendor->id, 'rate' => 15, 'base_amount' => 20,
            'amount' => 3, 'status' => 'pending']);
        Carbon::setTestNow(now()->addMinutes(5));
        $earned = Commission::query()->create(['vendor_order_id' => $completed->id, 'vendor_id' => $this->vendor->id, 'rate' => 12.5, 'base_amount' => 40,
            'amount' => 5, 'status' => 'earned', 'earned_at' => now()]);
        Carbon::setTestNow();
        Commission::query()->create(['vendor_order_id' => $otherVendorOrder->id, 'vendor_id' => $other->id, 'rate' => 10.78, 'base_amount' => 927.65,
            'amount' => 99.99, 'status' => 'paid', 'earned_at' => now()]);

        return [$pending, $earned->load('vendorOrder'), $order];
    }
}
