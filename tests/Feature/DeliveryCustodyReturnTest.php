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
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * E2-MVP custody fix: after a delivery failure following pickup, the driver's "returned to the vendor" only declares the
 * return (return_declared_at, return_declared); the shipment stays in the driver's custody and the order cannot be
 * reassigned. The admin confirmation (vendor-orders.confirm-return), with or without the declaration, alone closes the
 * custody: returned_at, return_confirmed_by, return_confirmed, and the order back to Ready for Delivery.
 */
class DeliveryCustodyReturnTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
    }

    // ---------------------------------------------------------------- the driver's declaration

    public function test_the_driver_declares_the_return_and_the_custody_stays_open(): void
    {
        $driver = $this->driver();
        $assignment = $this->failedAfterPickup($driver);

        $this->declare($driver, $assignment)->assertOk()
            ->assertJsonPath('assignment.status', 'failed')
            ->assertJsonPath('assignment.returned_at', null);

        $row = $assignment->fresh();
        $this->assertNotNull($row->return_declared_at);
        $this->assertSame(['failed', null, null], [$row->status, $row->returned_at, $row->return_confirmed_by]);
        $this->assertSame(['out_for_delivery', $driver->id], [$assignment->vendorOrder->fresh()->status, $assignment->vendorOrder->fresh()->delivery_driver_id]);

        $history = OrderStatusHistory::sole();
        $this->assertSame(['return_declared', 'delivery_driver', $driver->id], [$history->status, $history->actor_type, $history->actor_id]);
    }

    public function test_a_second_declaration_is_refused_and_changes_nothing(): void
    {
        $driver = $this->driver();
        $assignment = $this->failedAfterPickup($driver);
        $this->declare($driver, $assignment)->assertOk();
        $declaredAt = $assignment->fresh()->return_declared_at->toJSON();

        $this->travel(5)->minutes();
        $this->declare($driver, $assignment)->assertUnprocessable();

        $this->assertSame($declaredAt, $assignment->fresh()->return_declared_at->toJSON());
        $this->assertSame(1, OrderStatusHistory::count());
    }

    public function test_the_order_cannot_be_reassigned_before_the_confirmation(): void
    {
        $driver = $this->driver();
        $undeclared = $this->failedAfterPickup($driver);
        $declared = $this->failedAfterPickup($driver);
        $this->declare($driver, $declared)->assertOk();

        foreach ([$undeclared, $declared] as $assignment) {
            $this->actingAs($this->admin, 'admin')
                ->patch(route('dashboard.vendor-orders.assign-driver', $assignment->vendorOrder), ['delivery_driver_id' => $this->driver()->id])
                ->assertStatus(422);

            $this->assertSame(['out_for_delivery', $driver->id], [$assignment->vendorOrder->fresh()->status, $assignment->vendorOrder->fresh()->delivery_driver_id]);
            $this->assertSame([$assignment->id], DeliveryAssignment::where('vendor_order_id', $assignment->vendor_order_id)->pluck('id')->all());
        }
    }

    public function test_the_driver_card_shows_the_pending_confirmation_and_no_second_button(): void
    {
        $driver = $this->driver();
        $assignment = $this->failedAfterPickup($driver);
        $button = 'action="'.route('delivery-driver.dashboard.assignments.return-to-vendor', $assignment).'"';

        $this->actingAs($driver, 'delivery_driver')->get(route('delivery-driver.dashboard'))->assertOk()->assertSee($button, false);
        $this->declare($driver, $assignment)->assertOk();

        $this->actingAs($driver, 'delivery_driver')->get(route('delivery-driver.dashboard'))->assertOk()
            ->assertDontSee($button, false)
            ->assertSee('data-return-declared', false)
            ->assertSee('تبقى الشحنة في عهدتك حتى تؤكد الإدارة');
    }

    // ---------------------------------------------------------------- the admin confirmation

    public function test_the_admin_confirmation_closes_the_custody_with_or_without_the_declaration(): void
    {
        $driver = $this->driver();
        $declared = $this->failedAfterPickup($driver);
        $this->declare($driver, $declared)->assertOk();
        $undeclared = $this->failedAfterPickup($driver);

        foreach (['declared' => $declared, 'undeclared' => $undeclared] as $case => $assignment) {
            $this->confirm($this->admin, $assignment)->assertRedirect()->assertSessionHas('success');

            $row = $assignment->fresh();
            $this->assertSame(['cancelled', $this->admin->id], [$row->status, $row->return_confirmed_by], $case);
            $this->assertNotNull($row->returned_at, $case);
            $this->assertSame(['ready_for_delivery', null], [$assignment->vendorOrder->fresh()->status, $assignment->vendorOrder->fresh()->delivery_driver_id], $case);
            $history = OrderStatusHistory::where('vendor_order_id', $assignment->vendor_order_id)->where('status', 'return_confirmed')->sole();
            $this->assertSame(['admin', $this->admin->id], [$history->actor_type, $history->actor_id], $case);
        }
        $this->assertNull($undeclared->fresh()->return_declared_at, 'The declaration is not required and not invented.');
    }

    public function test_after_the_confirmation_the_order_is_reassigned_by_hand_and_the_attempt_is_kept(): void
    {
        $driver = $this->driver();
        $assignment = $this->failedAfterPickup($driver);
        $this->confirm($this->admin, $assignment)->assertRedirect();
        $next = $this->driver();

        $this->actingAs($this->admin, 'admin')
            ->patch(route('dashboard.vendor-orders.assign-driver', $assignment->vendorOrder), ['delivery_driver_id' => $next->id])
            ->assertSessionHasNoErrors();

        $attempts = DeliveryAssignment::where('vendor_order_id', $assignment->vendor_order_id)->orderBy('id')->get();
        $this->assertSame([[$driver->id, 'cancelled', $this->admin->id], [$next->id, 'assigned', null]], $attempts->map(fn ($a) => [$a->delivery_driver_id, $a->status, $a->return_confirmed_by])->all());
    }

    public function test_a_second_confirmation_or_a_confirmation_in_another_state_is_refused(): void
    {
        $driver = $this->driver();
        $assignment = $this->failedAfterPickup($driver);
        $this->confirm($this->admin, $assignment)->assertRedirect();
        $returnedAt = $assignment->fresh()->returned_at->toJSON();

        $this->travel(5)->minutes();
        $this->confirm($this->admin, $assignment)->assertStatus(422);
        $this->assertSame($returnedAt, $assignment->fresh()->returned_at->toJSON());
        $this->assertSame(1, OrderStatusHistory::where('status', 'return_confirmed')->count());

        foreach ([['assigned', 'assigned'], ['picked_up', 'out_for_delivery'], ['delivered', 'delivered'], ['failed', 'ready_for_delivery']] as [$status, $orderStatus]) {
            $other = $this->task($driver, $status, $orderStatus);

            $this->confirm($this->admin, $other)->assertStatus(422);

            $this->assertSame([$status, null, null, $orderStatus], [$other->fresh()->status, $other->fresh()->returned_at, $other->fresh()->return_confirmed_by, $other->vendorOrder->fresh()->status], $status);
        }
        $this->assertSame(1, OrderStatusHistory::where('status', 'return_confirmed')->count());
    }

    public function test_the_driver_cannot_declare_after_the_confirmation(): void
    {
        $driver = $this->driver();
        $assignment = $this->failedAfterPickup($driver);
        $this->confirm($this->admin, $assignment)->assertRedirect();

        $this->declare($driver, $assignment)->assertUnprocessable();
        $this->assertNull($assignment->fresh()->return_declared_at);
    }

    public function test_only_an_admin_with_the_confirm_return_ability_confirms(): void
    {
        $assignment = $this->failedAfterPickup($this->driver());
        $viewer = Admin::factory()->create(['status' => 'active']);
        $viewer->roles()->create(['role_name' => 'vendor-orders.view', 'ability' => 'allow']);
        $assigner = Admin::factory()->create(['status' => 'active']);
        $assigner->roles()->createMany([['role_name' => 'vendor-orders.view', 'ability' => 'allow'], ['role_name' => 'vendor-orders.assign-driver', 'ability' => 'allow']]);
        $confirmer = Admin::factory()->create(['status' => 'active']);
        $confirmer->roles()->createMany([['role_name' => 'vendor-orders.view', 'ability' => 'allow'], ['role_name' => 'vendor-orders.confirm-return', 'ability' => 'allow']]);
        $form = 'action="'.route('dashboard.vendor-orders.confirm-return', $assignment->vendorOrder).'"';

        foreach ([$viewer, $assigner] as $admin) {
            $this->actingAs($admin, 'admin')->get(route('dashboard.vendor-orders.show', $assignment->vendorOrder))->assertOk()->assertDontSee($form, false);
            $this->confirm($admin, $assignment)->assertForbidden();
        }
        $this->assertSame('failed', $assignment->fresh()->status);

        $this->actingAs($confirmer, 'admin')->get(route('dashboard.vendor-orders.show', $assignment->vendorOrder))->assertOk()->assertSee($form, false);
        $this->confirm($confirmer, $assignment)->assertRedirect();
        $this->assertSame(['cancelled', $confirmer->id], [$assignment->fresh()->status, $assignment->fresh()->return_confirmed_by]);
    }

    public function test_the_admin_page_shows_the_custody_state(): void
    {
        $driver = $this->driver();
        $assignment = $this->failedAfterPickup($driver);
        $page = fn () => $this->actingAs($this->admin, 'admin')->get(route('dashboard.vendor-orders.show', $assignment->vendorOrder))->assertOk();

        $page()->assertSee('data-custody="with-driver"', false)->assertSee('With the driver');
        $this->declare($driver, $assignment)->assertOk();
        $page()->assertSee('data-custody="return-pending"', false)->assertSee('Return pending confirmation');
        $this->confirm($this->admin, $assignment)->assertRedirect();
        $page()->assertDontSee('data-custody=', false)->assertDontSee('data-confirm-return', false);
    }

    public function test_the_driver_json_carries_the_declaration_but_never_the_confirming_admin(): void
    {
        $driver = $this->driver();
        $assignment = $this->failedAfterPickup($driver);
        $this->declare($driver, $assignment)->assertOk();
        $this->confirm($this->admin, $assignment)->assertRedirect();

        $json = $this->actingAs($driver, 'delivery_driver')->getJson(route('delivery-driver.dashboard.assignments.show', $assignment))->assertOk()->json();

        $this->assertNotNull($json['return_declared_at']);
        $this->assertNotNull($json['returned_at']);
        $this->assertArrayNotHasKey('return_confirmed_by', $json);
        $this->assertArrayNotHasKey('assigned_by', $json);
    }

    // ---------------------------------------------------------------- concurrency (sequential evidence on SQLite)

    /** A confirmation committed while the driver declares: the locked re-read sees the closed custody and refuses. */
    public function test_a_confirmation_committed_during_the_declaration_wins(): void
    {
        $driver = $this->driver();
        $assignment = $this->failedAfterPickup($driver);
        $this->whenTheTransactionBegins(function () use ($assignment): void {
            DB::table('delivery_assignments')->where('id', $assignment->id)->update(['status' => 'cancelled', 'returned_at' => now()]);
            DB::table('vendor_orders')->where('id', $assignment->vendor_order_id)->update(['status' => 'ready_for_delivery', 'delivery_driver_id' => null]);
        });

        // In this SQLite simulation the confirmation is written inside the declaration's transaction, so the refusal
        // rolls it back with it; only the declaration's own effect is checked.
        $this->declare($driver, $assignment)->assertUnprocessable();

        $this->assertNull($assignment->fresh()->return_declared_at);
        $this->assertSame(0, OrderStatusHistory::where('status', 'return_declared')->count());
    }

    /** A first confirmation committed while a second one runs: the second sees Ready for Delivery and is refused. */
    public function test_a_confirmation_committed_during_another_confirmation_wins(): void
    {
        $assignment = $this->failedAfterPickup($this->driver());
        $this->whenTheTransactionBegins(fn () => DB::table('vendor_orders')->where('id', $assignment->vendor_order_id)->update(['status' => 'ready_for_delivery', 'delivery_driver_id' => null]));

        $this->confirm($this->admin, $assignment)->assertStatus(422);

        $this->assertSame([null, null], [$assignment->fresh()->returned_at, $assignment->fresh()->return_confirmed_by]);
        $this->assertSame(0, OrderStatusHistory::where('status', 'return_confirmed')->count());
    }

    public function test_the_confirmation_locks_the_vendor_order_then_the_assignment_before_any_write(): void
    {
        $assignment = $this->failedAfterPickup($this->driver());
        $this->actingAs($this->admin, 'admin');
        $baseLevel = DB::transactionLevel();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = [strtolower($query->sql), DB::transactionLevel()];
        });

        $this->confirm($this->admin, $assignment)->assertRedirect();

        $inTransaction = array_values(array_filter($queries, fn ($query) => $query[1] > $baseLevel));
        $this->assertMatchesRegularExpression('/^select .* from "vendor_orders"/', $inTransaction[0][0]);
        $this->assertMatchesRegularExpression('/^select .* from "delivery_assignments"/', $inTransaction[1][0]);
        $this->assertMatchesRegularExpression('/^update "delivery_assignments"/', $inTransaction[2][0]);
    }

    // ---------------------------------------------------------------- helpers

    private function declare(DeliveryDriver $driver, DeliveryAssignment $assignment)
    {
        return $this->actingAs($driver, 'delivery_driver')->patchJson(route('delivery-driver.dashboard.assignments.return-to-vendor', $assignment));
    }

    private function confirm(Admin $admin, DeliveryAssignment $assignment)
    {
        return $this->actingAs($admin, 'admin')->from(route('dashboard.vendor-orders.show', $assignment->vendorOrder))
            ->patch(route('dashboard.vendor-orders.confirm-return', $assignment->vendorOrder));
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

    private function failedAfterPickup(DeliveryDriver $driver): DeliveryAssignment
    {
        return $this->task($driver, 'failed', 'out_for_delivery');
    }

    private function task(DeliveryDriver $driver, string $status, string $vendorOrderStatus): DeliveryAssignment
    {
        $order = Order::query()->create([
            'number' => 'HF-'.uniqid(), 'customer_id' => Customer::factory()->create()->id, 'status' => 'processing',
            'payment_method' => 'cod', 'payment_status' => 'pending', 'subtotal' => 50, 'delivery_total' => 0, 'grand_total' => 50,
        ]);
        $vendorOrder = VendorOrder::query()->create([
            'number' => 'VO-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => Vendor::factory()->approved()->create()->id,
            'delivery_driver_id' => $vendorOrderStatus === 'ready_for_delivery' ? null : $driver->id, 'status' => $vendorOrderStatus,
            'subtotal' => 50, 'delivery_fee' => 0, 'commission_amount' => 0, 'total' => 50, 'ready_at' => now(),
        ]);

        return DeliveryAssignment::query()->create([
            'vendor_order_id' => $vendorOrder->id, 'delivery_driver_id' => $driver->id, 'status' => $status, 'assigned_at' => now(),
            'picked_up_at' => $vendorOrderStatus === 'out_for_delivery' || in_array($status, ['picked_up', 'delivered'], true) ? now() : null,
            'failed_at' => $status === 'failed' ? now() : null,
            'failure_code' => $status === 'failed' ? 'customer_absent' : null,
        ]);
    }
}
