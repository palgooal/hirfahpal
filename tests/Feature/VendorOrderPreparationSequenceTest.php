<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * VEN-BE-020 (slice 1): the vendor's own transitions follow SRS §4.5 one step at a time: Pending → Accepted → Preparing
 * → Ready for Delivery. Preparing cannot be skipped, and a transition already made cannot be repeated: both are refused
 * with 422 (no field errors, as for a repeated Accept) and change nothing. Admin, driver and customer transitions and
 * the parent order aggregation are unchanged.
 */
class VendorOrderPreparationSequenceTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->approved()->create();
    }

    public function test_accepted_cannot_skip_preparing(): void
    {
        $order = $this->vendorOrder('accepted');

        $this->refused('ready', $order);

        $this->assertSame('accepted', $order->fresh()->status);
        $this->assertNull($order->fresh()->ready_at);
        $this->assertSame('processing', $order->order->fresh()->status);
    }

    public function test_pending_cannot_move_to_preparing_or_ready(): void
    {
        $order = $this->vendorOrder('pending');

        $this->refused('preparing', $order);
        $this->refused('ready', $order);

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->accepted_at);
        $this->assertNull($order->fresh()->ready_at);
        $this->assertSame('pending', $order->order->fresh()->status);
    }

    public function test_preparing_cannot_be_repeated(): void
    {
        $order = $this->vendorOrder('preparing');
        $acceptedAt = $order->accepted_at->toJSON();

        $this->refused('preparing', $order);

        $this->assertSame('preparing', $order->fresh()->status);
        $this->assertSame($acceptedAt, $order->fresh()->accepted_at->toJSON());
    }

    public function test_ready_cannot_be_repeated(): void
    {
        $order = $this->vendorOrder('ready_for_delivery');
        $readyAt = $order->ready_at->toJSON();

        $this->refused('ready', $order);

        $this->assertSame('ready_for_delivery', $order->fresh()->status);
        $this->assertSame($readyAt, $order->fresh()->ready_at->toJSON(), 'ready_at is kept.');
    }

    public function test_after_ready_and_in_the_exceptional_states_the_vendor_cannot_prepare_or_mark_ready(): void
    {
        foreach (['assigned', 'out_for_delivery', 'delivered', 'completed', 'rejected', 'cancelled'] as $status) {
            $order = $this->vendorOrder($status);

            $this->refused('preparing', $order);
            $this->refused('ready', $order);

            $this->assertSame($status, $order->fresh()->status, $status);
        }
    }

    public function test_accepted_then_preparing_then_ready_succeeds_and_the_parent_stays_processing(): void
    {
        $order = $this->vendorOrder('accepted');
        $sibling = $this->vendorOrder('pending', $order->order, Vendor::factory()->approved()->create());
        $acceptedAt = $order->accepted_at->toJSON();

        $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.orders.preparing', $order))
            ->assertOk()
            ->assertJsonPath('order.status', 'preparing')
            ->assertJsonPath('allowed_actions', ['accept' => false, 'reject' => false, 'mark_preparing' => false, 'mark_ready' => true]);
        $this->assertSame('processing', $order->order->fresh()->status);
        $this->assertNull($order->fresh()->ready_at);

        $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.orders.ready', $order))
            ->assertOk()
            ->assertJsonPath('order.status', 'ready_for_delivery')
            ->assertJsonPath('allowed_actions', ['accept' => false, 'reject' => false, 'mark_preparing' => false, 'mark_ready' => false]);
        $this->assertSame('processing', $order->order->fresh()->status);
        $this->assertNotNull($order->fresh()->ready_at);
        $this->assertSame($acceptedAt, $order->fresh()->accepted_at->toJSON(), 'accepted_at is kept.');
        $this->assertSame('pending', $sibling->fresh()->status, "Another vendor's order is untouched.");
    }

    public function test_the_full_vendor_sequence_from_pending(): void
    {
        $order = $this->vendorOrder('pending');
        $this->assertSame('pending', $order->order->fresh()->status);

        foreach (['accept' => 'accepted', 'preparing' => 'preparing', 'ready' => 'ready_for_delivery'] as $action => $status) {
            $this->actingAs($this->vendor, 'vendor')->patchJson(route("vendor.dashboard.orders.$action", $order))
                ->assertOk()
                ->assertJsonPath('order.status', $status);
            $this->assertSame('processing', $order->order->fresh()->status, $action);
        }
    }

    public function test_another_vendor_cannot_prepare_or_mark_ready(): void
    {
        $other = Vendor::factory()->approved()->create();
        $accepted = $this->vendorOrder('accepted');
        $preparing = $this->vendorOrder('preparing');

        $this->actingAs($other, 'vendor')->patchJson(route('vendor.dashboard.orders.preparing', $accepted))->assertNotFound();
        $this->actingAs($other, 'vendor')->patchJson(route('vendor.dashboard.orders.ready', $preparing))->assertNotFound();

        $this->assertSame('accepted', $accepted->fresh()->status);
        $this->assertSame('preparing', $preparing->fresh()->status);
        $this->assertNull($preparing->fresh()->ready_at);
    }

    public function test_the_allowed_actions_offer_only_the_next_step(): void
    {
        $expected = [
            'pending' => ['accept', 'reject'], 'accepted' => ['mark_preparing'], 'preparing' => ['mark_ready'], 'ready_for_delivery' => [],
        ];

        foreach ($expected as $status => $actions) {
            $allowed = $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.orders.show', $this->vendorOrder($status)))->assertOk()->json('allowed_actions');
            $this->assertSame($actions, array_keys(array_filter($allowed)), $status);
        }
    }

    private function refused(string $action, VendorOrder $order): void
    {
        $response = $this->actingAs($this->vendor, 'vendor')->patchJson(route("vendor.dashboard.orders.$action", $order))->assertStatus(422);
        $this->assertNull($response->json('errors'), "$action from {$order->status}: refused without field errors.");
    }

    private function vendorOrder(string $status, ?Order $order = null, ?Vendor $vendor = null): VendorOrder
    {
        $order ??= Order::query()->create([
            'number' => 'HF-'.uniqid(),
            'customer_id' => Customer::factory()->create()->id,
            'status' => $status === 'pending' ? 'pending' : 'processing',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'subtotal' => 10,
            'delivery_total' => 0,
            'grand_total' => 10,
        ]);
        $past = Carbon::parse('2026-10-01 09:00:00');
        $reached = fn (array $statuses) => in_array($status, $statuses, true) ? $past : null;

        return VendorOrder::query()->create([
            'number' => 'VO-'.uniqid(),
            'order_id' => $order->id,
            'vendor_id' => ($vendor ?? $this->vendor)->id,
            'status' => $status,
            'subtotal' => 10,
            'delivery_fee' => 0,
            'commission_amount' => 0,
            'total' => 10,
            'accepted_at' => $reached(['accepted', 'preparing', 'ready_for_delivery', 'assigned', 'out_for_delivery', 'delivered', 'completed']),
            'rejected_at' => $reached(['rejected']),
            'rejection_reason' => $status === 'rejected' ? 'Unavailable.' : null,
            'ready_at' => $reached(['ready_for_delivery', 'assigned', 'out_for_delivery', 'delivered', 'completed']),
            'delivered_at' => $reached(['delivered', 'completed']),
            'completed_at' => $reached(['completed']),
        ])->load('order');
    }
}
