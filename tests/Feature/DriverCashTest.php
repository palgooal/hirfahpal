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
use App\Support\Orders\DriverCash;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * E2-MVP batch 05: cash on delivery (the amount captured at assignment, the full amount confirmed at delivery, never
 * collected twice), online orders assigned and delivered only once marked paid, the driver's cash custody (computed in
 * cents), the admin settlements (partial, never above the balance) and their reversals (once, never of a reversal), the
 * abilities and privacy, and the admin-created vendor order (Pending only, amounts consistent with the parent order).
 */
class DriverCashTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
    }

    // ---------------------------------------------------------------- assignment and payment

    public function test_a_cash_on_delivery_assignment_captures_the_vendor_order_total(): void
    {
        $vendorOrder = $this->readyOrder('cod', 'pending', '42.50');
        $this->assign($vendorOrder, $driver = $this->driver())->assertSessionHasNoErrors();

        $attempt = DeliveryAssignment::sole();
        $this->assertSame(['42.50', null, null], [$attempt->cod_amount, $attempt->cod_collected_amount, $attempt->cod_collected_at]);
        $this->assertSame($driver->id, $attempt->delivery_driver_id);
    }

    public function test_an_online_order_is_assigned_only_once_marked_paid(): void
    {
        foreach (['pending', 'partially_refunded', 'failed', 'refunded'] as $paymentStatus) {
            $vendorOrder = $this->readyOrder('online', $paymentStatus);

            $this->assign($vendorOrder, $this->driver())->assertSessionHasErrors('delivery_driver_id');

            $this->assertSame(['ready_for_delivery', null], [$vendorOrder->fresh()->status, $vendorOrder->fresh()->delivery_driver_id], $paymentStatus);
            $this->actingAs($this->admin, 'admin')->get(route('dashboard.vendor-orders.show', $vendorOrder))->assertOk()->assertSee('data-awaiting-payment', false);
        }
        $this->assertSame(0, DeliveryAssignment::count());

        $paid = $this->readyOrder('online', 'paid');
        $this->assign($paid, $this->driver())->assertSessionHasNoErrors();
        $this->assertNull(DeliveryAssignment::sole()->cod_amount, 'Nothing to collect on a prepaid order.');
    }

    // ---------------------------------------------------------------- delivery and collection

    public function test_a_cash_on_delivery_task_is_delivered_only_with_the_full_amount(): void
    {
        [$driver, $attempt] = $this->pickedUp('cod', 'pending', '42.50');

        foreach ([[], ['collected_amount' => '42.49'], ['collected_amount' => '42.51'], ['collected_amount' => '0'], ['collected_amount' => '42.505'], ['collected_amount' => '-42.50'], ['collected_amount' => 'abc']] as $input) {
            $this->deliver($driver, $attempt, $input)->assertUnprocessable()->assertJsonValidationErrors('collected_amount');
        }
        $this->assertSame(['picked_up', null], [$attempt->fresh()->status, $attempt->fresh()->cod_collected_at]);

        $this->deliver($driver, $attempt, ['collected_amount' => '42.5'])->assertOk()->assertJsonPath('assignment.cod_collected_amount', '42.50');

        $row = $attempt->fresh();
        $this->assertSame(['delivered', '42.50'], [$row->status, $row->cod_collected_amount]);
        $this->assertNotNull($row->cod_collected_at);
        $this->assertSame(['delivered', 'pending'], [$attempt->vendorOrder->fresh()->status, $attempt->vendorOrder->order->fresh()->payment_status], 'The payment status stays the admin\'s.');
        $this->assertSame(4250, DriverCash::summary($driver->id)['balance']);
    }

    public function test_a_prepaid_online_task_is_delivered_without_collection_and_an_unpaid_one_is_refused(): void
    {
        [$driver, $paid] = $this->pickedUp('online', 'paid', '30.00');
        $this->deliver($driver, $paid, [])->assertOk();
        $this->assertSame(['delivered', null, null], [$paid->fresh()->status, $paid->fresh()->cod_collected_amount, $paid->fresh()->cod_amount]);

        // Marked paid at assignment, set back before delivery: refused, the driver records a payment failure instead.
        [$driver, $reverted] = $this->pickedUp('online', 'paid', '30.00');
        $reverted->vendorOrder->order->update(['payment_status' => 'pending']);
        $this->deliver($driver, $reverted, [])->assertUnprocessable();
        $this->assertSame('picked_up', $reverted->fresh()->status);

        $this->assertSame(0, DriverCash::summary($driver->id)['balance']);
    }

    public function test_an_attempt_assigned_before_this_batch_collects_the_vendor_order_total(): void
    {
        [$driver, $attempt] = $this->pickedUp('cod', 'pending', '18.00');
        $attempt->update(['cod_amount' => null]);

        $this->deliver($driver, $attempt, ['collected_amount' => '17.00'])->assertUnprocessable();
        $this->deliver($driver, $attempt, ['collected_amount' => '18.00'])->assertOk();
        $this->assertSame('18.00', $attempt->fresh()->cod_collected_amount);
    }

    public function test_the_cash_of_a_vendor_order_is_never_collected_twice(): void
    {
        // A failed attempt (payment not collected), the return confirmed, a new attempt: collected once, by the second.
        $vendorOrder = $this->readyOrder('cod', 'pending', '25.00');
        $first = $this->driver();
        $this->assign($vendorOrder, $first)->assertSessionHasNoErrors();
        $firstAttempt = DeliveryAssignment::sole();
        $this->act($first, 'accept', $firstAttempt)->assertOk();
        $this->act($first, 'pick-up', $firstAttempt)->assertOk();
        $this->act($first, 'failed', $firstAttempt, ['failure_code' => 'payment_failed', 'failure_reason' => 'Paid 10 of 25.'])->assertOk();
        $this->actingAs($this->admin, 'admin')->patch(route('dashboard.vendor-orders.confirm-return', $vendorOrder))->assertRedirect();

        $second = $this->driver();
        $this->assign($vendorOrder, $second)->assertSessionHasNoErrors();
        $secondAttempt = DeliveryAssignment::query()->latest('id')->first();
        $this->assertSame('25.00', $secondAttempt->cod_amount);
        $this->act($second, 'accept', $secondAttempt)->assertOk();
        $this->act($second, 'pick-up', $secondAttempt)->assertOk();
        $this->deliver($second, $secondAttempt, ['collected_amount' => '25.00'])->assertOk();

        $this->assertNull($firstAttempt->fresh()->cod_collected_at);
        $this->assertSame([0, 2500], [DriverCash::summary($first->id)['balance'], DriverCash::summary($second->id)['balance']]);
        $this->assertSame(1, DeliveryAssignment::where('vendor_order_id', $vendorOrder->id)->whereNotNull('cod_collected_at')->count());
    }

    /** Another attempt of the same vendor order recorded as collected while this delivery runs: refused, nothing written. */
    public function test_a_collection_committed_by_another_attempt_during_the_delivery_wins(): void
    {
        [$driver, $attempt] = $this->pickedUp('cod', 'pending', '25.00');
        $other = DeliveryAssignment::query()->create(['vendor_order_id' => $attempt->vendor_order_id, 'delivery_driver_id' => $this->driver()->id,
            'status' => 'cancelled', 'assigned_at' => now()]);
        $this->whenTheTransactionBegins(fn () => DB::table('delivery_assignments')->where('id', $other->id)->update(['cod_collected_amount' => 25, 'cod_collected_at' => now()]));

        $this->deliver($driver, $attempt, ['collected_amount' => '25.00'])->assertUnprocessable();

        $this->assertSame(['picked_up', null], [$attempt->fresh()->status, $attempt->fresh()->cod_collected_at]);
    }

    public function test_each_vendor_order_of_a_multi_vendor_order_is_collected_on_its_own(): void
    {
        $order = $this->order('cod', 'pending', '50.00');
        $first = $this->vendorOrderOf($order, '30.00');
        $second = $this->vendorOrderOf($order, '20.00');
        [$driverA, $driverB] = [$this->driver(), $this->driver()];
        $this->assign($first, $driverA)->assertSessionHasNoErrors();
        $this->assign($second, $driverB)->assertSessionHasNoErrors();
        $attemptA = DeliveryAssignment::where('vendor_order_id', $first->id)->sole();
        $attemptB = DeliveryAssignment::where('vendor_order_id', $second->id)->sole();
        $this->assertSame(['30.00', '20.00'], [$attemptA->cod_amount, $attemptB->cod_amount]);

        foreach ([[$driverA, $attemptA], [$driverB, $attemptB]] as [$driver, $attempt]) {
            $this->act($driver, 'accept', $attempt)->assertOk();
            $this->act($driver, 'pick-up', $attempt)->assertOk();
        }
        $this->deliver($driverA, $attemptA, ['collected_amount' => '50.00'])->assertUnprocessable();
        $this->deliver($driverA, $attemptA, ['collected_amount' => '30.00'])->assertOk();
        $this->deliver($driverB, $attemptB, ['collected_amount' => '20.00'])->assertOk();

        $this->assertSame([3000, 2000], [DriverCash::summary($driverA->id)['balance'], DriverCash::summary($driverB->id)['balance']]);
    }

    // ---------------------------------------------------------------- the driver's view

    public function test_the_driver_sees_the_amount_to_collect_and_only_their_own_balance(): void
    {
        [$driver, $attempt] = $this->pickedUp('cod', 'pending', '42.50');
        [$other, $otherAttempt] = $this->pickedUp('cod', 'pending', '99.10');
        $this->deliver($other, $otherAttempt, ['collected_amount' => '99.10'])->assertOk();

        $html = $this->actingAs($driver, 'delivery_driver')->get(route('delivery-driver.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-cod-amount>[^<]*<span[^>]*>42\.50</', $html);
        $this->assertStringContainsString('name="collected_amount" required', $html);
        $this->assertMatchesRegularExpression('/data-cash-balance>.*?>0\.00</s', $html);
        $this->assertStringNotContainsString('99.10', $html);

        $this->deliver($driver, $attempt, ['collected_amount' => '42.50'])->assertOk();
        $this->actingAs($driver, 'delivery_driver')->getJson(route('delivery-driver.dashboard.overview'))->assertOk()->assertJsonPath('cash_balance', '42.50');
        $show = $this->actingAs($driver, 'delivery_driver')->getJson(route('delivery-driver.dashboard.assignments.show', $attempt))->assertOk();
        $show->assertJsonPath('cod_amount', '42.50')->assertJsonPath('cod_collected_amount', '42.50');
        $this->assertStringNotContainsString('99.10', $show->getContent());
    }

    // ---------------------------------------------------------------- settlements

    public function test_partial_settlements_reduce_the_balance_and_never_exceed_it(): void
    {
        $driver = $this->driverHolding('50.00');

        $this->settle($driver, '20')->assertSessionHasNoErrors();
        $this->assertSame(3000, DriverCash::summary($driver->id)['balance']);

        foreach (['30.01', '0', '0.00', '-5', 'abc', '10.005', ''] as $amount) {
            $this->settle($driver, $amount)->assertSessionHasErrors('amount');
        }
        $this->assertSame(1, DriverCashSettlement::count());

        $this->settle($driver, '30.00', 'Handed over at the office.')->assertSessionHasNoErrors();
        $this->assertSame(0, DriverCash::summary($driver->id)['balance']);
        $this->settle($driver, '0.01')->assertSessionHasErrors('amount');

        $entries = DriverCashSettlement::orderBy('id')->get();
        $this->assertSame([['settlement', '20.00', $this->admin->id, null], ['settlement', '30.00', $this->admin->id, 'Handed over at the office.']],
            $entries->map(fn ($e) => [$e->kind, $e->amount, $e->recorded_by, $e->note])->all());
    }

    public function test_a_settlement_is_corrected_once_by_a_reversal_and_the_original_is_kept(): void
    {
        $driver = $this->driverHolding('50.00');
        $this->settle($driver, '40.00')->assertSessionHasNoErrors();
        $original = DriverCashSettlement::sole();
        $snapshot = $original->getAttributes();

        $this->reverse($driver, $original, '')->assertSessionHasErrors('note');
        $this->reverse($driver, $original, 'Counted 4.00, not 40.00.')->assertSessionHasNoErrors();

        $reversal = DriverCashSettlement::where('kind', 'reversal')->sole();
        $this->assertSame(['40.00', $original->id, $this->admin->id], [$reversal->amount, $reversal->reverses_id, $reversal->recorded_by]);
        $this->assertSame($snapshot, $original->fresh()->getAttributes(), 'The original entry is never changed.');
        $this->assertSame(5000, DriverCash::summary($driver->id)['balance']);

        // Not twice, never a reversal, never through another driver.
        $this->reverse($driver, $original, 'Again.')->assertSessionHasErrors('note');
        $this->reverse($driver, $reversal, 'Reverse the reversal.')->assertSessionHasErrors('note');
        $otherDriver = $this->driverHolding('10.00');
        $this->reverse($otherDriver, $original, 'Wrong driver.')->assertNotFound();
        $this->assertSame(2, DriverCashSettlement::count());

        // The right amount is then recorded as a new settlement.
        $this->settle($driver, '4.00')->assertSessionHasNoErrors();
        $this->assertSame(4600, DriverCash::summary($driver->id)['balance']);
    }

    public function test_the_database_refuses_a_second_reversal_of_the_same_settlement(): void
    {
        $driver = $this->driverHolding('10.00');
        $this->settle($driver, '10.00')->assertSessionHasNoErrors();
        $original = DriverCashSettlement::sole();
        $this->reverse($driver, $original, 'First.')->assertSessionHasNoErrors();

        $this->expectException(UniqueConstraintViolationException::class);
        DriverCashSettlement::query()->create(['delivery_driver_id' => $driver->id, 'kind' => 'reversal', 'amount' => '10.00',
            'reverses_id' => $original->id, 'recorded_by' => $this->admin->id]);
    }

    /** A settlement committed by another admin while this one runs: the locked balance is re-read and the excess refused. */
    public function test_a_settlement_committed_during_another_is_counted_before_the_check(): void
    {
        $driver = $this->driverHolding('50.00');
        $this->whenTheTransactionBegins(fn () => DB::table('driver_cash_settlements')->insert(['delivery_driver_id' => $driver->id, 'kind' => 'settlement',
            'amount' => 40, 'recorded_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]));

        $this->settle($driver, '20.00')->assertSessionHasErrors('amount');
    }

    public function test_a_settlement_locks_the_driver_row_first(): void
    {
        $driver = $this->driverHolding('50.00');
        $this->actingAs($this->admin, 'admin');
        $baseLevel = DB::transactionLevel();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = [strtolower($query->sql), DB::transactionLevel()];
        });

        $this->settle($driver, '5.00')->assertSessionHasNoErrors();

        $inTransaction = array_values(array_filter($queries, fn ($query) => $query[1] > $baseLevel));
        $this->assertMatchesRegularExpression('/^select .* from "delivery_drivers"/', $inTransaction[0][0]);
    }

    // ---------------------------------------------------------------- abilities and privacy

    public function test_viewing_and_settling_cash_need_their_own_abilities(): void
    {
        $driver = $this->driverHolding('50.00');
        $page = fn (Admin $admin) => $this->actingAs($admin, 'admin')->get(route('dashboard.delivery-drivers.show', $driver))->assertOk();

        $viewer = $this->adminWith(['delivery-drivers.view', 'delivery-drivers.edit']);
        $page($viewer)->assertDontSee('data-driver-cash', false)->assertDontSee('50.00');
        $this->settle($driver, '5.00', null, $viewer)->assertForbidden();

        $cashViewer = $this->adminWith(['delivery-drivers.view', 'delivery-drivers.view-cash']);
        $page($cashViewer)->assertSee('data-driver-cash', false)->assertSee('data-cash-balance', false)->assertDontSee('data-cash-settlement-form', false);
        $this->settle($driver, '5.00', null, $cashViewer)->assertForbidden();

        $settler = $this->adminWith(['delivery-drivers.view', 'delivery-drivers.view-cash', 'delivery-drivers.settle-cash']);
        $page($settler)->assertSee('data-cash-settlement-form', false);
        $this->settle($driver, '5.00', null, $settler)->assertSessionHasNoErrors();
        $entry = DriverCashSettlement::sole();
        $this->assertSame($settler->id, $entry->recorded_by);
        $this->reverse($driver, $entry, 'Not allowed.', $cashViewer)->assertForbidden();
        $this->reverse($driver, $entry, 'Allowed.', $settler)->assertSessionHasNoErrors();

        // Granted to nobody automatically.
        $this->assertFalse($this->adminWith(['delivery-drivers.view'])->hasAbility('delivery-drivers.view-cash'));
    }

    public function test_the_customer_and_the_vendor_never_see_the_cash_custody(): void
    {
        [$driver, $attempt] = $this->pickedUp('cod', 'pending', '42.50');
        $this->deliver($driver, $attempt, ['collected_amount' => '42.50'])->assertOk();
        $this->settle($driver, '12.34', 'SETTLEMENT-NOTE-77')->assertSessionHasNoErrors();

        $vendorOrder = $attempt->vendorOrder;
        $customerJson = $this->actingAs($vendorOrder->order->customer, 'customer')->getJson(route('customer.orders.show', $vendorOrder->order))->assertOk()->getContent();
        $vendorJson = $this->actingAs($vendorOrder->vendor, 'vendor')->getJson(route('vendor.dashboard.orders.show', $vendorOrder))->assertOk()->getContent();

        foreach ([$customerJson, $vendorJson] as $body) {
            foreach (['cod_amount', 'cod_collected', '12.34', 'SETTLEMENT-NOTE-77', 'cash_balance'] as $secret) {
                $this->assertStringNotContainsString($secret, $body, $secret);
            }
        }
    }

    // ---------------------------------------------------------------- admin-created vendor order

    public function test_an_admin_created_vendor_order_starts_pending_with_consistent_amounts(): void
    {
        $order = $this->order('cod', 'pending', '50.00');
        $this->vendorOrderOf($order, '30.00');

        foreach (['assigned', 'out_for_delivery', 'delivered', 'completed'] as $status) {
            $this->create($order, ['status' => $status, 'subtotal' => '20.00', 'total' => '20.00'])->assertSessionHasErrors('status');
        }
        $this->create($order, ['subtotal' => '15.00', 'delivery_fee' => '4.00', 'total' => '20.00'])->assertSessionHasErrors('total');
        $this->create($order, ['subtotal' => '20.01', 'total' => '20.01'])->assertSessionHasErrors('total');
        $this->create($order, ['subtotal' => '19.999', 'total' => '19.999'])->assertSessionHasErrors(['subtotal', 'total']);
        $this->assertSame(1, VendorOrder::where('order_id', $order->id)->count());

        $this->create($order, ['subtotal' => '16.00', 'delivery_fee' => '4.00', 'total' => '20.00'])->assertSessionHasNoErrors();
        $created = VendorOrder::where('order_id', $order->id)->latest('id')->first();
        $this->assertSame(['pending', '20.00'], [$created->status, $created->total]);
    }

    public function test_a_cancelled_vendor_order_frees_its_share_and_a_closed_parent_takes_none(): void
    {
        $order = $this->order('cod', 'pending', '50.00');
        $cancelled = $this->vendorOrderOf($order, '50.00');
        $this->create($order, ['subtotal' => '10.00', 'total' => '10.00'])->assertSessionHasErrors('total');

        $cancelled->update(['status' => 'cancelled']);
        $this->create($order, ['subtotal' => '50.00', 'total' => '50.00'])->assertSessionHasNoErrors();

        foreach (['cancelled', 'completed'] as $status) {
            $closed = $this->order('cod', 'pending', '50.00');
            $closed->update(['status' => $status]);
            $this->create($closed, ['subtotal' => '10.00', 'total' => '10.00'])->assertSessionHasErrors('order_id');
            $this->assertSame(0, VendorOrder::where('order_id', $closed->id)->count(), $status);
        }
    }

    /** Another vendor order committed for the same parent while this one is created: the second is refused. */
    public function test_concurrent_creations_never_exceed_the_grand_total(): void
    {
        $order = $this->order('cod', 'pending', '50.00');
        $this->whenTheTransactionBegins(fn () => DB::table('vendor_orders')->insert(['number' => 'VO-RACE-1', 'order_id' => $order->id,
            'vendor_id' => Vendor::factory()->approved()->create()->id, 'status' => 'pending', 'subtotal' => 30, 'delivery_fee' => 0,
            'commission_amount' => 0, 'total' => 30, 'created_at' => now(), 'updated_at' => now()]));

        $this->create($order, ['subtotal' => '30.00', 'total' => '30.00'])->assertSessionHasErrors('total');
    }

    // ---------------------------------------------------------------- helpers

    private function driverHolding(string $amount): DeliveryDriver
    {
        [$driver, $attempt] = $this->pickedUp('cod', 'pending', $amount);
        $this->deliver($driver, $attempt, ['collected_amount' => $amount])->assertOk();

        return $driver;
    }

    /** @return array{0: DeliveryDriver, 1: DeliveryAssignment} an attempt picked up through the real routes */
    private function pickedUp(string $method, string $paymentStatus, string $total): array
    {
        $vendorOrder = $this->readyOrder($method, $paymentStatus, $total);
        $driver = $this->driver();
        $this->assign($vendorOrder, $driver)->assertSessionHasNoErrors();
        $attempt = DeliveryAssignment::where('vendor_order_id', $vendorOrder->id)->sole();
        $this->act($driver, 'accept', $attempt)->assertOk();
        $this->act($driver, 'pick-up', $attempt)->assertOk();

        return [$driver, $attempt->fresh()];
    }

    private function deliver(DeliveryDriver $driver, DeliveryAssignment $attempt, array $input)
    {
        return $this->actingAs($driver, 'delivery_driver')->patchJson(route('delivery-driver.dashboard.assignments.delivered', $attempt), $input);
    }

    private function act(DeliveryDriver $driver, string $action, DeliveryAssignment $attempt, array $input = [])
    {
        return $this->actingAs($driver, 'delivery_driver')->patchJson(route('delivery-driver.dashboard.assignments.'.$action, $attempt), $input);
    }

    private function assign(VendorOrder $vendorOrder, DeliveryDriver $driver)
    {
        return $this->actingAs($this->admin, 'admin')->from(route('dashboard.vendor-orders.show', $vendorOrder))
            ->patch(route('dashboard.vendor-orders.assign-driver', $vendorOrder), ['delivery_driver_id' => $driver->id]);
    }

    private function settle(DeliveryDriver $driver, string $amount, ?string $note = null, ?Admin $admin = null)
    {
        return $this->actingAs($admin ?? $this->admin, 'admin')->from(route('dashboard.delivery-drivers.show', $driver))
            ->post(route('dashboard.delivery-drivers.cash-settlements.store', $driver), array_filter(['amount' => $amount, 'note' => $note], fn ($v) => $v !== null));
    }

    private function reverse(DeliveryDriver $driver, DriverCashSettlement $settlement, string $note, ?Admin $admin = null)
    {
        return $this->actingAs($admin ?? $this->admin, 'admin')->from(route('dashboard.delivery-drivers.show', $driver))
            ->post(route('dashboard.delivery-drivers.cash-settlements.reverse', [$driver, $settlement]), ['note' => $note]);
    }

    private function create(Order $order, array $input)
    {
        return $this->actingAs($this->admin, 'admin')->from(route('dashboard.vendor-orders.create'))
            ->post(route('dashboard.vendor-orders.store'), array_merge(['order_id' => $order->id, 'vendor_id' => Vendor::factory()->approved()->create()->id, 'status' => 'pending'], $input));
    }

    private function adminWith(array $abilities): Admin
    {
        $admin = Admin::factory()->create(['super_admin' => false, 'status' => 'active']);
        $admin->roles()->createMany(array_map(fn ($ability) => ['role_name' => $ability, 'ability' => 'allow'], $abilities));

        return $admin;
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

    private function order(string $method, string $paymentStatus, string $grandTotal): Order
    {
        return Order::query()->create([
            'number' => 'HF-'.uniqid(), 'customer_id' => Customer::factory()->create()->id, 'status' => 'processing',
            'payment_method' => $method, 'payment_status' => $paymentStatus, 'subtotal' => $grandTotal, 'delivery_total' => 0, 'grand_total' => $grandTotal,
        ]);
    }

    private function vendorOrderOf(Order $order, string $total): VendorOrder
    {
        return VendorOrder::query()->create([
            'number' => 'VO-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => Vendor::factory()->approved()->create()->id,
            'status' => 'ready_for_delivery', 'subtotal' => $total, 'delivery_fee' => 0, 'commission_amount' => 0, 'total' => $total, 'ready_at' => now(),
        ])->load('order');
    }

    private function readyOrder(string $method, string $paymentStatus, string $total = '20.00'): VendorOrder
    {
        return $this->vendorOrderOf($this->order($method, $paymentStatus, $total), $total);
    }
}
