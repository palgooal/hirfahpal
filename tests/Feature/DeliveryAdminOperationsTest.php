<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * E2-MVP batch 04: (1) the parent order's final statuses come from its vendor orders only; (2) an admin cancels a vendor
 * order before pickup (reason, admin, time; the active attempt ends; a pending order's reservations are released, stock
 * deducted at the vendor's accept is not restored, nothing financial); (3) an admin records a failed delivery after pickup
 * and the custody still closes only through the return confirmation; (4) a driver no longer eligible keeps their tasks,
 * shown to the admin; (5) no sham reassignment to the driver who already holds the order.
 */
class DeliveryAdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
    }

    // ---------------------------------------------------------------- 1. parent order status

    public function test_the_parent_order_final_statuses_cannot_be_set_by_hand(): void
    {
        $vendorOrder = $this->vendorOrder('preparing');
        $order = $vendorOrder->order;

        foreach (['cancelled', 'partially_cancelled', 'completed'] as $status) {
            $this->actingAs($this->admin, 'admin')->from(route('dashboard.orders.show', $order))
                ->patch(route('dashboard.orders.update-status', $order), ['status' => $status])
                ->assertSessionHasErrors('status');
            $this->assertSame(['processing', null], [$order->fresh()->status, $order->fresh()->completed_at], $status);
        }

        $this->actingAs($this->admin, 'admin')->patch(route('dashboard.orders.update-status', $order), ['status' => 'pending'])->assertSessionHasNoErrors();
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_a_derived_final_status_cannot_be_changed_by_hand_and_the_page_shows_no_form(): void
    {
        $vendorOrder = $this->vendorOrder('ready_for_delivery');
        $this->cancel($vendorOrder, 'Vendor closed.')->assertSessionHasNoErrors();
        $order = $vendorOrder->order->fresh();
        $this->assertSame('cancelled', $order->status);

        $this->actingAs($this->admin, 'admin')->patch(route('dashboard.orders.update-status', $order), ['status' => 'processing'])->assertStatus(422);
        $this->assertSame('cancelled', $order->fresh()->status);

        $this->actingAs($this->admin, 'admin')->get(route('dashboard.orders.show', $order))->assertOk()
            ->assertDontSee('action="'.route('dashboard.orders.update-status', $order).'"', false)
            ->assertSee('data-order-status-derived', false);
    }

    // ---------------------------------------------------------------- 2. cancel before pickup

    public function test_a_pending_order_is_cancelled_and_its_reservations_released(): void
    {
        $vendorOrder = $this->vendorOrder('pending');
        $reservation = $this->reservation($vendorOrder, 'reserved');

        $this->cancel($vendorOrder, '  Customer asked by phone.  ')->assertRedirect()->assertSessionHas('success');

        $this->assertSame('cancelled', $vendorOrder->fresh()->status);
        $this->assertSame('released', $reservation->fresh()->status);
        $this->assertSame('cancelled', $vendorOrder->order->fresh()->status, 'The parent order follows its only vendor order.');
        $history = OrderStatusHistory::sole();
        $this->assertSame(['cancelled_by_admin', 'admin', $this->admin->id, 'Customer asked by phone.'], [$history->status, $history->actor_type, $history->actor_id, $history->note]);
        $this->assertNotNull($history->created_at);
    }

    public function test_after_the_vendor_accepted_the_deducted_stock_is_not_restored(): void
    {
        foreach (['accepted', 'preparing', 'ready_for_delivery'] as $status) {
            $vendorOrder = $this->vendorOrder($status);
            $reservation = $this->reservation($vendorOrder, 'committed');
            $stockBefore = $reservation->product->fresh()->stock_quantity;

            $this->cancel($vendorOrder, 'Out of service area.')->assertSessionHasNoErrors();

            $this->assertSame('cancelled', $vendorOrder->fresh()->status, $status);
            $this->assertSame('committed', $reservation->fresh()->status, $status);
            $this->assertSame($stockBefore, $reservation->product->fresh()->stock_quantity, $status);
            $this->assertSame('pending', $vendorOrder->order->fresh()->payment_status, "{$status}: nothing financial.");
        }
    }

    public function test_an_assigned_order_is_cancelled_with_its_active_attempt(): void
    {
        foreach (['assigned', 'accepted'] as $attemptStatus) {
            $driver = $this->driver();
            $vendorOrder = $this->vendorOrder('assigned', $driver);
            $attempt = $this->attempt($vendorOrder, $driver, $attemptStatus);

            $this->cancel($vendorOrder, 'Duplicate order.')->assertSessionHasNoErrors();

            $this->assertSame(['cancelled', null], [$vendorOrder->fresh()->status, $vendorOrder->fresh()->delivery_driver_id], $attemptStatus);
            $this->assertSame('cancelled', $attempt->fresh()->status, $attemptStatus);
            // The driver can no longer act on it.
            $this->actingAs($driver, 'delivery_driver')->patchJson(route('delivery-driver.dashboard.assignments.pick-up', $attempt))->assertUnprocessable();
        }
    }

    public function test_cancelling_is_refused_once_the_goods_left_the_vendor_or_the_order_is_closed(): void
    {
        $driver = $this->driver();
        $cases = [
            'out for delivery' => [$this->vendorOrder('out_for_delivery', $driver), 'picked_up'],
            'failed after pickup' => [$this->vendorOrder('out_for_delivery', $driver), 'failed'],
            'delivered' => [$this->vendorOrder('delivered', $driver), 'delivered'],
            'completed' => [$this->vendorOrder('completed'), null],
            'rejected' => [$this->vendorOrder('rejected'), null],
            'cancelled' => [$this->vendorOrder('cancelled'), null],
            'assigned without an active attempt' => [$this->vendorOrder('assigned', $driver), 'cancelled'],
        ];

        foreach ($cases as $case => [$vendorOrder, $attemptStatus]) {
            $attempt = $attemptStatus ? $this->attempt($vendorOrder, $driver, $attemptStatus) : null;
            $before = $vendorOrder->fresh()->status;

            $this->cancel($vendorOrder, 'Try.')->assertStatus(422);

            $this->assertSame($before, $vendorOrder->fresh()->status, $case);
            if ($attempt) {
                $this->assertSame($attemptStatus, $attempt->fresh()->status, $case);
            }
        }
        $this->assertSame(0, OrderStatusHistory::count());
    }

    public function test_the_reason_is_required_and_the_orders_edit_ability_is_needed(): void
    {
        $vendorOrder = $this->vendorOrder('ready_for_delivery');

        foreach (['', '   ', str_repeat('a', 1001)] as $reason) {
            $this->cancel($vendorOrder, $reason)->assertSessionHasErrors('cancel_reason');
        }

        $viewer = Admin::factory()->create(['status' => 'active']);
        $viewer->roles()->createMany([['role_name' => 'vendor-orders.view', 'ability' => 'allow'], ['role_name' => 'vendor-orders.assign-driver', 'ability' => 'allow']]);
        $page = $this->actingAs($viewer, 'admin')->get(route('dashboard.vendor-orders.show', $vendorOrder))->assertOk();
        $page->assertDontSee('action="'.route('dashboard.vendor-orders.cancel', $vendorOrder).'"', false);
        $this->cancel($vendorOrder, 'No ability.', $viewer)->assertForbidden();
        $this->assertSame('ready_for_delivery', $vendorOrder->fresh()->status);

        $editor = Admin::factory()->create(['status' => 'active']);
        $editor->roles()->createMany([['role_name' => 'vendor-orders.view', 'ability' => 'allow'], ['role_name' => 'orders.edit', 'ability' => 'allow']]);
        $this->actingAs($editor, 'admin')->get(route('dashboard.vendor-orders.show', $vendorOrder))->assertOk()
            ->assertSee('action="'.route('dashboard.vendor-orders.cancel', $vendorOrder).'"', false);
        $this->cancel($vendorOrder, 'With ability.', $editor)->assertSessionHasNoErrors();
        $this->assertSame([$editor->id, 'cancelled'], [OrderStatusHistory::sole()->actor_id, $vendorOrder->fresh()->status]);
    }

    public function test_cancelling_one_vendor_order_leaves_the_others_and_partially_cancels_the_parent(): void
    {
        $first = $this->vendorOrder('ready_for_delivery');
        $second = VendorOrder::query()->create([
            'number' => 'VO-'.uniqid(), 'order_id' => $first->order_id, 'vendor_id' => Vendor::factory()->approved()->create()->id,
            'status' => 'preparing', 'subtotal' => 20, 'delivery_fee' => 0, 'commission_amount' => 0, 'total' => 20,
        ]);

        $this->cancel($first, 'Vendor unavailable.')->assertSessionHasNoErrors();

        $this->assertSame(['cancelled', 'preparing', 'partially_cancelled'], [$first->fresh()->status, $second->fresh()->status, $first->order->fresh()->status]);
    }

    public function test_the_admin_page_shows_who_cancelled_why_and_when(): void
    {
        $vendorOrder = $this->vendorOrder('accepted');
        $this->cancel($vendorOrder, 'Paid by mistake twice.')->assertSessionHasNoErrors();

        $this->actingAs($this->admin, 'admin')->get(route('dashboard.vendor-orders.show', $vendorOrder))->assertOk()
            ->assertSee('data-admin-cancellation', false)
            ->assertSee('Cancelled by administration')
            ->assertSee('Paid by mistake twice.')
            ->assertDontSee('action="'.route('dashboard.vendor-orders.cancel', $vendorOrder).'"', false);
    }

    public function test_the_cancellation_reason_reaches_neither_the_customer_nor_the_driver(): void
    {
        $driver = $this->driver();
        $vendorOrder = $this->vendorOrder('assigned', $driver);
        $attempt = $this->attempt($vendorOrder, $driver, 'assigned');
        $this->cancel($vendorOrder, 'INTERNAL-CANCEL-REASON-91')->assertSessionHasNoErrors();

        $customer = $vendorOrder->order->customer;
        $customerJson = $this->actingAs($customer, 'customer')->getJson(route('customer.orders.show', $vendorOrder->order))->assertOk()->getContent();
        $driverJson = $this->actingAs($driver, 'delivery_driver')->getJson(route('delivery-driver.dashboard.assignments.show', $attempt))->assertOk()->getContent();
        $driverPage = $this->actingAs($driver, 'delivery_driver')->get(route('delivery-driver.dashboard'))->assertOk()->getContent();

        foreach ([$customerJson, $driverJson, $driverPage] as $body) {
            $this->assertStringNotContainsString('INTERNAL-CANCEL-REASON-91', $body);
        }
    }

    /** The driver picks up while the admin cancels: the locked re-read sees the goods with the driver and refuses. */
    public function test_a_pickup_committed_during_the_cancellation_wins(): void
    {
        $driver = $this->driver();
        $vendorOrder = $this->vendorOrder('assigned', $driver);
        $attempt = $this->attempt($vendorOrder, $driver, 'accepted');
        $this->whenTheTransactionBegins(function () use ($vendorOrder, $attempt): void {
            DB::table('delivery_assignments')->where('id', $attempt->id)->update(['status' => 'picked_up', 'picked_up_at' => now()]);
            DB::table('vendor_orders')->where('id', $vendorOrder->id)->update(['status' => 'out_for_delivery']);
        });

        // In this SQLite simulation the pickup is written inside the cancellation's transaction and rolled back with the
        // refusal; only the cancellation's own effect is checked.
        $this->cancel($vendorOrder, 'Too late.')->assertStatus(422);

        $this->assertSame(0, OrderStatusHistory::where('status', 'cancelled_by_admin')->count());
        $this->assertNotSame('cancelled', $vendorOrder->fresh()->status);
    }

    /** The vendor accepts while the admin cancels a pending order: the cancellation sees Accepted and releases nothing. */
    public function test_a_vendor_accept_committed_during_the_cancellation_keeps_the_committed_stock(): void
    {
        $vendorOrder = $this->vendorOrder('pending');
        $reservation = $this->reservation($vendorOrder, 'reserved');
        $this->whenTheTransactionBegins(function () use ($vendorOrder, $reservation): void {
            DB::table('vendor_orders')->where('id', $vendorOrder->id)->update(['status' => 'accepted', 'accepted_at' => now()]);
            DB::table('stock_reservations')->where('id', $reservation->id)->update(['status' => 'committed', 'committed_at' => now()]);
        });

        $this->cancel($vendorOrder, 'Changed mind.')->assertSessionHasNoErrors();

        $this->assertSame(['cancelled', 'committed'], [$vendorOrder->fresh()->status, $reservation->fresh()->status]);
    }

    public function test_the_cancellation_locks_the_vendor_order_then_the_assignment(): void
    {
        $driver = $this->driver();
        $vendorOrder = $this->vendorOrder('assigned', $driver);
        $this->attempt($vendorOrder, $driver, 'assigned');
        $this->actingAs($this->admin, 'admin');
        $baseLevel = DB::transactionLevel();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = [strtolower($query->sql), DB::transactionLevel()];
        });

        $this->cancel($vendorOrder, 'Order check.')->assertSessionHasNoErrors();

        $inTransaction = array_values(array_filter($queries, fn ($query) => $query[1] > $baseLevel));
        $this->assertMatchesRegularExpression('/^select .* from "vendor_orders"/', $inTransaction[0][0]);
        $this->assertMatchesRegularExpression('/^select .* from "delivery_assignments"/', $inTransaction[1][0]);
        $this->assertMatchesRegularExpression('/^update "delivery_assignments"/', $inTransaction[2][0]);
    }

    // ---------------------------------------------------------------- 3. failure recorded by the admin

    public function test_the_admin_records_a_failure_after_pickup_and_the_custody_stays_open_until_the_return_is_confirmed(): void
    {
        $driver = $this->driver();
        $vendorOrder = $this->vendorOrder('out_for_delivery', $driver);
        $attempt = $this->attempt($vendorOrder, $driver, 'out_for_delivery');

        $this->recordFailure($vendorOrder, ['failure_code' => 'customer_unreachable', 'failure_reason' => 'Driver not answering since noon.'])
            ->assertRedirect()->assertSessionHas('success');

        $row = $attempt->fresh();
        $this->assertSame(['failed', 'customer_unreachable', 'Driver not answering since noon.', null, null], [$row->status, $row->failure_code, $row->failure_reason, $row->returned_at, $row->return_confirmed_by]);
        $this->assertNotNull($row->failed_at);
        $this->assertSame(['out_for_delivery', $driver->id], [$vendorOrder->fresh()->status, $vendorOrder->fresh()->delivery_driver_id]);
        $history = OrderStatusHistory::sole();
        $this->assertSame(['delivery_failed', 'admin', $this->admin->id], [$history->status, $history->actor_type, $history->actor_id]);
        $this->assertStringContainsString('Driver not answering since noon.', $history->note);

        // Still no reassignment or cancellation with the goods out.
        $this->actingAs($this->admin, 'admin')->patch(route('dashboard.vendor-orders.assign-driver', $vendorOrder), ['delivery_driver_id' => $this->driver()->id])->assertStatus(422);
        $this->cancel($vendorOrder, 'Not yet.')->assertStatus(422);

        // The return confirmation (batch 03) closes the custody; the order can then be cancelled.
        $this->actingAs($this->admin, 'admin')->patch(route('dashboard.vendor-orders.confirm-return', $vendorOrder))->assertRedirect();
        $this->assertSame('ready_for_delivery', $vendorOrder->fresh()->status);
        $this->cancel($vendorOrder, 'Customer no longer wants it.')->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $vendorOrder->fresh()->status);
    }

    public function test_recording_a_failure_needs_a_listed_reason_a_note_the_state_and_the_ability(): void
    {
        $driver = $this->driver();
        $vendorOrder = $this->vendorOrder('out_for_delivery', $driver);
        $attempt = $this->attempt($vendorOrder, $driver, 'picked_up');

        foreach ([['failure_code' => 'customer_unreachable'], ['failure_code' => 'customer_unreachable', 'failure_reason' => '  '], ['failure_code' => 'lost', 'failure_reason' => 'x'], ['failure_reason' => 'x']] as $input) {
            $this->recordFailure($vendorOrder, $input)->assertSessionHasErrors();
        }

        $viewer = Admin::factory()->create(['status' => 'active']);
        $viewer->roles()->create(['role_name' => 'vendor-orders.view', 'ability' => 'allow']);
        $this->actingAs($viewer, 'admin')->get(route('dashboard.vendor-orders.show', $vendorOrder))->assertOk()->assertDontSee('data-record-failure', false);
        $this->recordFailure($vendorOrder, ['failure_code' => 'other', 'failure_reason' => 'x'], $viewer)->assertForbidden();
        $this->assertSame('picked_up', $attempt->fresh()->status);

        foreach ([['assigned', 'assigned'], ['delivered', 'delivered'], ['failed', 'out_for_delivery']] as [$attemptStatus, $orderStatus]) {
            $other = $this->vendorOrder($orderStatus, $driver);
            $otherAttempt = $this->attempt($other, $driver, $attemptStatus);

            $this->recordFailure($other, ['failure_code' => 'other', 'failure_reason' => 'x'])->assertStatus(422);
            $this->assertSame([$attemptStatus, $orderStatus], [$otherAttempt->fresh()->status, $other->fresh()->status], $attemptStatus);
        }
        $this->assertSame(0, OrderStatusHistory::count());
    }

    /** The ability itself grants the action: a regular admin (no super-admin bypass) holding only view + record-delivery-failure. */
    public function test_a_regular_admin_with_the_record_delivery_failure_ability_records_the_failure(): void
    {
        $driver = $this->driver();
        $vendorOrder = $this->vendorOrder('out_for_delivery', $driver);
        $attempt = $this->attempt($vendorOrder, $driver, 'out_for_delivery');
        $recorder = Admin::factory()->create(['super_admin' => false, 'status' => 'active']);
        $recorder->roles()->createMany([
            ['role_name' => 'vendor-orders.view', 'ability' => 'allow'],
            ['role_name' => 'vendor-orders.record-delivery-failure', 'ability' => 'allow'],
        ]);
        $this->assertFalse($recorder->fresh()->isSuperAdmin());
        $this->assertSame(['vendor-orders.record-delivery-failure', 'vendor-orders.view'], $recorder->roles()->orderBy('role_name')->pluck('role_name')->all());

        $this->actingAs($recorder, 'admin')->get(route('dashboard.vendor-orders.show', $vendorOrder))->assertOk()
            ->assertSee('data-record-failure', false)
            ->assertSee('action="'.route('dashboard.vendor-orders.record-failure', $vendorOrder).'"', false);

        $this->recordFailure($vendorOrder, ['failure_code' => 'customer_refused', 'failure_reason' => 'Recipient refused at the door.'], $recorder)
            ->assertRedirect(route('dashboard.vendor-orders.show', $vendorOrder))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $row = $attempt->fresh();
        $this->assertSame(['failed', 'customer_refused', 'Recipient refused at the door.'], [$row->status, $row->failure_code, $row->failure_reason]);
        // The custody stays open: no return recorded or confirmed, the goods still with the driver.
        $this->assertSame([null, null], [$row->returned_at, $row->return_confirmed_by]);
        $this->assertSame(['out_for_delivery', $driver->id], [$vendorOrder->fresh()->status, $vendorOrder->fresh()->delivery_driver_id]);

        $history = OrderStatusHistory::sole();
        $this->assertSame(['delivery_failed', 'admin', $recorder->id], [$history->status, $history->actor_type, $history->actor_id]);
    }

    // ---------------------------------------------------------------- 4. driver no longer eligible

    public function test_an_ineligible_drivers_tasks_are_kept_and_shown_to_the_admin(): void
    {
        $rejected = $this->driver();
        $beforePickup = $this->vendorOrder('assigned', $rejected);
        $beforePickupAttempt = $this->attempt($beforePickup, $rejected, 'accepted');
        $blocked = $this->driver();
        $withGoods = $this->vendorOrder('out_for_delivery', $blocked);
        $withGoodsAttempt = $this->attempt($withGoods, $blocked, 'out_for_delivery');
        $unavailable = $this->driver();
        $unavailableOrder = $this->vendorOrder('assigned', $unavailable);
        $this->attempt($unavailableOrder, $unavailable, 'assigned');

        $this->actingAs($this->admin, 'admin')->patch(route('dashboard.delivery-drivers.reject', $rejected), ['rejection_reason' => 'Documents expired.'])->assertRedirect();
        $this->actingAs($this->admin, 'admin')->patch(route('dashboard.delivery-drivers.availability', $blocked), ['status' => 'blocked', 'is_available' => 0])->assertRedirect();
        $unavailable->profile->update(['is_available' => false]);

        // Nothing is unassigned automatically.
        $this->assertSame(['accepted', 'assigned', $rejected->id], [$beforePickupAttempt->fresh()->status, $beforePickup->fresh()->status, $beforePickup->fresh()->delivery_driver_id]);
        $this->assertSame(['out_for_delivery', 'out_for_delivery'], [$withGoodsAttempt->fresh()->status, $withGoods->fresh()->status]);

        // The delivery issue filter lists both, not the task of a driver who is merely unavailable.
        $listed = $this->actingAs($this->admin, 'admin')->get(route('dashboard.vendor-orders.index', ['delivery_issue' => 1]))->assertOk()
            ->viewData('vendorOrders')->getCollection()->pluck('id')->sort()->values()->all();
        $this->assertSame([$beforePickup->id, $withGoods->id], $listed);

        foreach ([$beforePickup, $withGoods] as $vendorOrder) {
            $this->actingAs($this->admin, 'admin')->get(route('dashboard.vendor-orders.show', $vendorOrder))->assertOk()->assertSee('data-driver-not-eligible', false);
        }
        $this->actingAs($this->admin, 'admin')->get(route('dashboard.vendor-orders.show', $unavailableOrder))->assertOk()->assertDontSee('data-driver-not-eligible', false);

        // Before pickup the admin reassigns; after pickup the admin records the failure (then confirms the return).
        $this->actingAs($this->admin, 'admin')->patch(route('dashboard.vendor-orders.assign-driver', $beforePickup), ['delivery_driver_id' => $this->driver()->id])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $beforePickupAttempt->fresh()->status, 'The accepted attempt is kept as a cancelled one.');
        $this->recordFailure($withGoods, ['failure_code' => 'other', 'failure_reason' => 'Driver blocked.'])->assertSessionHasNoErrors();
        $this->assertSame('failed', $withGoodsAttempt->fresh()->status);
    }

    public function test_the_driver_page_lists_every_task_the_driver_still_holds(): void
    {
        $driver = $this->driver();
        $old = $this->vendorOrder('out_for_delivery', $driver);
        $oldAttempt = $this->attempt($old, $driver, 'failed');
        // More recent finished tasks than the history list shows.
        foreach (range(1, 16) as $i) {
            $this->attempt($this->vendorOrder('completed', $driver), $driver, 'delivered');
        }
        $active = $this->vendorOrder('assigned', $driver);
        $activeAttempt = $this->attempt($active, $driver, 'assigned');
        $driver->update(['status' => 'blocked']);

        $html = $this->actingAs($this->admin, 'admin')->get(route('dashboard.delivery-drivers.show', $driver))->assertOk()->getContent();

        $this->assertStringContainsString('data-active-task="'.$oldAttempt->id.'"', $html);
        $this->assertStringContainsString('data-active-task="'.$activeAttempt->id.'"', $html);
        $this->assertSame(2, substr_count($html, 'data-active-task="'));
        $this->assertStringContainsString('data-driver-not-eligible', $html);
    }

    // ---------------------------------------------------------------- 5. no sham reassignment

    public function test_the_driver_who_holds_the_order_cannot_be_assigned_again_but_a_new_attempt_is_allowed(): void
    {
        foreach (['assigned', 'accepted'] as $attemptStatus) {
            $driver = $this->driver();
            $vendorOrder = $this->vendorOrder('assigned', $driver);
            $attempt = $this->attempt($vendorOrder, $driver, $attemptStatus);
            $before = $attempt->fresh()->getAttributes();

            $this->actingAs($this->admin, 'admin')->from(route('dashboard.vendor-orders.show', $vendorOrder))
                ->patch(route('dashboard.vendor-orders.assign-driver', $vendorOrder), ['delivery_driver_id' => $driver->id])
                ->assertSessionHasErrors(['delivery_driver_id' => 'This driver is already assigned to this order.']);

            $this->assertSame($before, $attempt->fresh()->getAttributes(), $attemptStatus);
            $this->assertSame(1, DeliveryAssignment::where('vendor_order_id', $vendorOrder->id)->count(), $attemptStatus);
        }

        // After the driver declined, assigning them again is a real new attempt.
        $driver = $this->driver();
        $vendorOrder = $this->vendorOrder('assigned', $driver);
        $attempt = $this->attempt($vendorOrder, $driver, 'assigned');
        $this->actingAs($driver, 'delivery_driver')->patchJson(route('delivery-driver.dashboard.assignments.decline', $attempt), ['decline_code' => 'schedule_conflict'])->assertOk();
        $this->actingAs($this->admin, 'admin')->patch(route('dashboard.vendor-orders.assign-driver', $vendorOrder), ['delivery_driver_id' => $driver->id])->assertSessionHasNoErrors();
        $this->assertSame(['cancelled', 'assigned'], DeliveryAssignment::where('vendor_order_id', $vendorOrder->id)->orderBy('id')->pluck('status')->all());
    }

    // ---------------------------------------------------------------- helpers

    private function cancel(VendorOrder $vendorOrder, string $reason, ?Admin $admin = null)
    {
        return $this->actingAs($admin ?? $this->admin, 'admin')->from(route('dashboard.vendor-orders.show', $vendorOrder))
            ->patch(route('dashboard.vendor-orders.cancel', $vendorOrder), ['cancel_reason' => $reason]);
    }

    private function recordFailure(VendorOrder $vendorOrder, array $input, ?Admin $admin = null)
    {
        return $this->actingAs($admin ?? $this->admin, 'admin')->from(route('dashboard.vendor-orders.show', $vendorOrder))
            ->patch(route('dashboard.vendor-orders.record-failure', $vendorOrder), $input);
    }

    private function whenTheTransactionBegins(callable $change): void
    {
        $pending = true;
        Event::listen(TransactionBeginning::class, function () use (&$pending, $change): void {
            if ($pending) {
                $pending = false;
                $change();
            }
        });
    }

    private function driver(): DeliveryDriver
    {
        $driver = DeliveryDriver::factory()->create(['status' => 'active']);
        $driver->profile()->create(['approval_status' => 'approved', 'approved_at' => now(), 'is_available' => true]);

        return $driver;
    }

    private function vendorOrder(string $status, ?DeliveryDriver $driver = null): VendorOrder
    {
        $order = Order::query()->create([
            'number' => 'HF-'.uniqid(), 'customer_id' => Customer::factory()->create()->id,
            'status' => match ($status) {
                'pending' => 'pending',
                'completed' => 'completed',
                'rejected', 'cancelled' => 'cancelled',
                default => 'processing',
            },
            'payment_method' => 'cod', 'payment_status' => 'pending', 'subtotal' => 20, 'delivery_total' => 0, 'grand_total' => 20,
        ]);

        return VendorOrder::query()->create([
            'number' => 'VO-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => Vendor::factory()->approved()->create()->id,
            'delivery_driver_id' => in_array($status, ['assigned', 'out_for_delivery', 'delivered', 'completed'], true) ? $driver?->id : null,
            'status' => $status, 'subtotal' => 20, 'delivery_fee' => 0, 'commission_amount' => 0, 'total' => 20,
        ])->load('order');
    }

    private function attempt(VendorOrder $vendorOrder, DeliveryDriver $driver, string $status): DeliveryAssignment
    {
        return DeliveryAssignment::query()->create([
            'vendor_order_id' => $vendorOrder->id, 'delivery_driver_id' => $driver->id, 'status' => $status, 'assigned_at' => now(),
            'picked_up_at' => in_array($status, ['picked_up', 'out_for_delivery', 'delivered'], true) || ($status === 'failed' && $vendorOrder->status === 'out_for_delivery') ? now() : null,
            'failed_at' => $status === 'failed' ? now() : null,
            'failure_code' => $status === 'failed' ? 'customer_absent' : null,
        ]);
    }

    private function reservation(VendorOrder $vendorOrder, string $status): StockReservation
    {
        $product = Product::query()->create([
            'vendor_id' => $vendorOrder->vendor_id, 'name' => 'Clay Jar', 'slug' => 'clay-jar-'.uniqid(), 'price' => 20,
            'stock_quantity' => 7, 'low_stock_threshold' => 1, 'status' => 'active',
        ]);
        $item = $vendorOrder->order->items()->create(['vendor_order_id' => $vendorOrder->id, 'product_id' => $product->id, 'product_name' => 'Clay Jar',
            'quantity' => 1, 'unit_price' => 20, 'line_total' => 20]);

        return StockReservation::query()->create([
            'product_id' => $product->id, 'order_id' => $vendorOrder->order_id, 'vendor_order_id' => $vendorOrder->id, 'order_item_id' => $item->id,
            'quantity' => 1, 'status' => $status, 'reserved_at' => now(), 'committed_at' => $status === 'committed' ? now() : null,
        ])->load('product');
    }
}
