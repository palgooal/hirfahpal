<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * VEN-BE-020 (slice 2B-1): driver actions (accept, pick up, delivered, failed) lock the vendor order, then the assignment
 * (the order Admin assignment uses), and re-check on the locked row that the assignment still belongs to the driver;
 * otherwise 404 with nothing written. What a driver may do, from which statuses, is unchanged.
 *
 * These tests are sequential. The stale-request tests reproduce the race deterministically (an Admin reassignment is
 * committed between the route check and the lock) but not real concurrency; SQLite ignores FOR UPDATE, so lock waits and
 * deadlocks can only be observed on MySQL/InnoDB (VEN-BE-029).
 */
class DeliveryAssignmentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const ACTIONS = ['accept', 'pick-up', 'delivered', 'failed'];

    // ---------------------------------------------------------------- pickup (first tests of this action)

    public function test_pickup_from_assigned(): void
    {
        $driver = $this->driver();
        $assignment = $this->assignment($driver, 'assigned', 'assigned');

        $this->act($driver, 'pick-up', $assignment)->assertOk()->assertJsonPath('assignment.status', 'picked_up');

        $this->assertPickedUp($assignment, $driver);
    }

    public function test_pickup_from_accepted(): void
    {
        $driver = $this->driver();
        $assignment = $this->assignment($driver, 'accepted', 'assigned');

        $this->act($driver, 'pick-up', $assignment)->assertOk();

        $this->assertPickedUp($assignment, $driver);
    }

    public function test_repeated_pickup_is_accepted_as_before_and_keeps_the_first_pickup_time(): void
    {
        $driver = $this->driver();
        $assignment = $this->assignment($driver, 'assigned', 'assigned');
        $this->act($driver, 'pick-up', $assignment)->assertOk();
        $first = $assignment->fresh()->picked_up_at->toJSON();
        $this->travel(5)->minutes();

        $this->act($driver, 'pick-up', $assignment)->assertOk();

        $this->assertSame(['picked_up', $first], [$assignment->fresh()->status, $assignment->fresh()->picked_up_at->toJSON()]);
        $this->assertSame('out_for_delivery', $assignment->vendorOrder->fresh()->status);
        $this->assertSame(2, OrderStatusHistory::where('status', 'out_for_delivery')->count(), 'Unchanged: a repeated pickup is recorded again.');
    }

    public function test_pickup_is_still_refused_while_the_vendor_order_is_not_assigned(): void
    {
        $driver = $this->driver();
        $assignment = $this->assignment($driver, 'assigned', 'ready_for_delivery');
        $before = $this->snapshot($assignment);

        $this->act($driver, 'pick-up', $assignment)->assertStatus(422);

        $this->assertSame($before, $this->snapshot($assignment));
    }

    // ---------------------------------------------------------------- the other actions, unchanged

    public function test_the_driver_actions_behave_as_before(): void
    {
        $driver = $this->driver();
        $assignment = $this->assignment($driver, 'assigned', 'assigned');

        $this->act($driver, 'accept', $assignment)->assertOk()->assertJsonPath('assignment.status', 'accepted');
        $this->act($driver, 'accept', $assignment)->assertStatus(422);
        $this->act($driver, 'delivered', $assignment)->assertStatus(422);
        $this->act($driver, 'pick-up', $assignment)->assertOk();
        $this->act($driver, 'delivered', $assignment)->assertOk()->assertJsonPath('assignment.vendor_order.status', 'delivered');

        $this->assertSame(['delivered', 'delivered'], [$assignment->fresh()->status, $assignment->vendorOrder->fresh()->status]);
        $this->assertNotNull($assignment->fresh()->delivered_at);
        $this->assertSame(['delivery_accepted', 'out_for_delivery', 'delivered'], OrderStatusHistory::orderBy('id')->pluck('status')->all());
        $this->assertSame([$driver->id], OrderStatusHistory::distinct()->pluck('actor_id')->all());

        $failing = $this->assignment($driver, 'accepted', 'assigned');
        $this->act($driver, 'failed', $failing, ['failure_reason' => 'Vehicle broke down.'])->assertOk();
        $this->assertSame(['failed', 'Vehicle broke down.', 'assigned'], [$failing->fresh()->status, $failing->fresh()->failure_reason, $failing->vendorOrder->fresh()->status], 'Failure still leaves the vendor order as it was.');
    }

    // ---------------------------------------------------------------- ownership

    public function test_another_driver_gets_404_for_every_action_and_nothing_changes(): void
    {
        $owner = $this->driver();
        $other = $this->driver();
        $statuses = ['accept' => ['assigned', 'assigned'], 'pick-up' => ['accepted', 'assigned'], 'delivered' => ['picked_up', 'out_for_delivery'], 'failed' => ['picked_up', 'out_for_delivery']];

        foreach ($statuses as $action => [$assignmentStatus, $orderStatus]) {
            $assignment = $this->assignment($owner, $assignmentStatus, $orderStatus);
            $before = $this->snapshot($assignment);

            $this->act($other, $action, $assignment, ['failure_reason' => 'x'])->assertNotFound();

            $this->assertSame($before, $this->snapshot($assignment), $action);
        }
    }

    public function test_a_stale_request_after_reassignment_is_refused_with_nothing_written(): void
    {
        // The original driver's request passes the route check, then an Admin reassignment to another driver commits
        // before the action locks the rows (the race window). Each action must then refuse.
        $statuses = ['accept' => ['assigned', 'assigned'], 'pick-up' => ['accepted', 'assigned'], 'delivered' => ['picked_up', 'out_for_delivery'], 'failed' => ['picked_up', 'out_for_delivery']];

        foreach ($statuses as $action => [$assignmentStatus, $orderStatus]) {
            $original = $this->driver();
            $replacement = $this->driver();
            $assignment = $this->assignment($original, $assignmentStatus, $orderStatus);
            $this->reassignAfterTheRouteCheck($assignment, $replacement);

            $this->act($original, $action, $assignment, ['failure_reason' => 'x'])->assertNotFound();
            DeliveryAssignment::flushEventListeners();

            $after = $assignment->fresh();
            $this->assertSame([$replacement->id, 'assigned'], [$after->delivery_driver_id, $after->status], "{$action}: the replacement's assignment is untouched.");
            $this->assertSame([$orderStatus, $replacement->id], [$assignment->vendorOrder->fresh()->status, $assignment->vendorOrder->fresh()->delivery_driver_id], $action);
            $this->assertSame(0, OrderStatusHistory::where('vendor_order_id', $assignment->vendor_order_id)->count(), "{$action}: no history written.");
            $this->assertSame('processing', $assignment->vendorOrder->order->fresh()->status, $action);
        }
    }

    public function test_after_a_reassignment_the_replacement_can_pick_up_and_the_original_cannot(): void
    {
        $original = $this->driver();
        $replacement = $this->driver();
        $assignment = $this->assignment($original, 'accepted', 'assigned');
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);

        $this->actingAs($admin, 'admin')->patch(route('dashboard.vendor-orders.assign-driver', $assignment->vendorOrder), ['delivery_driver_id' => $replacement->id])->assertRedirect();

        $this->act($original, 'pick-up', $assignment)->assertNotFound();
        $this->act($replacement, 'pick-up', $assignment)->assertOk();
        $this->assertSame([$replacement->id, 'picked_up'], [$assignment->fresh()->delivery_driver_id, $assignment->fresh()->status]);
        $this->assertSame([$replacement->id], OrderStatusHistory::pluck('actor_id')->all());
    }

    // ---------------------------------------------------------------- lock order (sequential evidence)

    public function test_every_action_reads_the_vendor_order_before_the_assignment_inside_its_transaction(): void
    {
        $statuses = ['accept' => ['assigned', 'assigned'], 'pick-up' => ['accepted', 'assigned'], 'delivered' => ['picked_up', 'out_for_delivery'], 'failed' => ['picked_up', 'out_for_delivery']];
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);

        foreach ($statuses as $action => [$assignmentStatus, $orderStatus]) {
            $driver = $this->driver();
            $assignment = $this->assignment($driver, $assignmentStatus, $orderStatus);
            $this->assertSame(['vendor_orders', 'delivery_assignments'], $this->firstTablesReadInTransaction(fn () => $this->act($driver, $action, $assignment, ['failure_reason' => 'x'])->assertOk()), $action);
        }

        $assignment = $this->assignment($this->driver(), 'assigned', 'assigned');
        $this->assertSame(['vendor_orders', 'delivery_assignments'], $this->firstTablesReadInTransaction(
            fn () => $this->actingAs($admin, 'admin')->patch(route('dashboard.vendor-orders.assign-driver', $assignment->vendorOrder), ['delivery_driver_id' => $this->driver()->id])->assertRedirect()
        ), 'admin reassignment');
    }

    // ---------------------------------------------------------------- helpers

    /** Commits an Admin reassignment right after the request's route binding has read the assignment (once). */
    private function reassignAfterTheRouteCheck(DeliveryAssignment $assignment, DeliveryDriver $replacement): void
    {
        $done = false;
        DeliveryAssignment::retrieved(function (DeliveryAssignment $retrieved) use ($assignment, $replacement, &$done) {
            if ($done || $retrieved->id !== $assignment->id) {
                return;
            }
            $done = true;
            DB::table('delivery_assignments')->where('id', $assignment->id)->update(['delivery_driver_id' => $replacement->id, 'status' => 'assigned', 'assigned_at' => now()]);
            DB::table('vendor_orders')->where('id', $assignment->vendor_order_id)->update(['delivery_driver_id' => $replacement->id]);
        });
    }

    /** @return list<string> the first two tables selected from inside the request's transaction */
    private function firstTablesReadInTransaction(callable $request): array
    {
        $tables = [];
        DB::listen(function ($query) use (&$tables) {
            if (DB::transactionLevel() > 1 && preg_match('/^select .* from "([a-z_]+)"/', $query->sql, $m)) {
                $tables[] = $m[1];
            }
        });
        $request();
        DB::flushQueryLog();

        return array_slice($tables, 0, 2);
    }

    private function act(DeliveryDriver $driver, string $action, DeliveryAssignment $assignment, array $data = [])
    {
        return $this->actingAs($driver, 'delivery_driver')->patchJson(route("delivery-driver.dashboard.assignments.{$action}", $assignment), $action === 'failed' ? $data : []);
    }

    private function assertPickedUp(DeliveryAssignment $assignment, DeliveryDriver $driver): void
    {
        $this->assertSame('picked_up', $assignment->fresh()->status);
        $this->assertNotNull($assignment->fresh()->picked_up_at);
        $this->assertSame('out_for_delivery', $assignment->vendorOrder->fresh()->status);
        $this->assertSame('processing', $assignment->vendorOrder->order->fresh()->status);
        $history = OrderStatusHistory::sole();
        $this->assertSame(['out_for_delivery', 'delivery_driver', $driver->id, $assignment->vendor_order_id], [$history->status, $history->actor_type, $history->actor_id, $history->vendor_order_id]);
    }

    /** @return array<string, mixed> */
    private function snapshot(DeliveryAssignment $assignment): array
    {
        return [
            'assignment' => $assignment->fresh()->getAttributes(),
            'vendor_order' => $assignment->vendorOrder->fresh()->getAttributes(),
            'order' => $assignment->vendorOrder->order->fresh()->getAttributes(),
            'history' => OrderStatusHistory::count(),
        ];
    }

    private function driver(): DeliveryDriver
    {
        $driver = DeliveryDriver::factory()->create(['status' => 'active']);
        $driver->profile()->create(['approval_status' => 'approved', 'approved_at' => now(), 'is_available' => true]);

        return $driver;
    }

    private function assignment(DeliveryDriver $driver, string $assignmentStatus, string $orderStatus): DeliveryAssignment
    {
        $order = Order::query()->create([
            'number' => 'HF-'.uniqid(),
            'customer_id' => Customer::factory()->create()->id,
            'status' => 'processing',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'subtotal' => 25,
            'delivery_total' => 0,
            'grand_total' => 25,
        ]);
        $vendorOrder = VendorOrder::query()->create([
            'number' => 'VO-'.uniqid(),
            'order_id' => $order->id,
            'vendor_id' => Vendor::factory()->approved()->create()->id,
            'delivery_driver_id' => $driver->id,
            'status' => $orderStatus,
            'subtotal' => 25,
            'delivery_fee' => 0,
            'commission_amount' => 0,
            'total' => 25,
            'accepted_at' => now(),
            'ready_at' => now(),
        ]);

        return DeliveryAssignment::query()->create([
            'vendor_order_id' => $vendorOrder->id,
            'delivery_driver_id' => $driver->id,
            'status' => $assignmentStatus,
            'assigned_at' => now(),
            'picked_up_at' => in_array($assignmentStatus, ['picked_up'], true) ? now() : null,
        ])->load('vendorOrder.order');
    }
}
