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
use App\Support\Orders\DeliveryReasons;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * E2-MVP batch 03: (5) a driver declines a task before accepting it, with a reason from the list ("other" needs a text);
 * the attempt is kept and the order goes back to the admin for a manual reassignment; (6) a failed delivery takes a
 * reason from the list (payment_failed included) with an optional note, the driver cannot retry, and the admin sees it.
 */
class DeliveryTaskDeclineAndFailureTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
    }

    // ---------------------------------------------------------------- 5. decline before accepting

    public function test_a_task_not_accepted_yet_is_declined_kept_and_returned_to_the_admin(): void
    {
        $driver = $this->driver();
        $assignment = $this->task($driver);
        $vendorOrder = $assignment->vendorOrder;

        $this->decline($driver, $assignment, ['decline_code' => 'out_of_area'])->assertOk()
            ->assertJsonPath('assignment.status', 'cancelled')
            ->assertJsonPath('assignment.decline_code', 'out_of_area');

        $row = $assignment->fresh();
        $this->assertSame(['cancelled', 'out_of_area', null, $driver->id], [$row->status, $row->decline_code, $row->decline_note, $row->delivery_driver_id]);
        $this->assertNotNull($row->declined_at);
        $this->assertSame(['ready_for_delivery', null], [$vendorOrder->fresh()->status, $vendorOrder->fresh()->delivery_driver_id]);
        // Kept, and nothing assigned automatically.
        $this->assertSame([$assignment->id], DeliveryAssignment::where('vendor_order_id', $vendorOrder->id)->pluck('id')->all());

        $history = OrderStatusHistory::sole();
        $this->assertSame(['delivery_declined', 'delivery_driver', $driver->id, $vendorOrder->id], [$history->status, $history->actor_type, $history->actor_id, $history->vendor_order_id]);
        $this->assertSame('out_of_area — '.DeliveryReasons::DECLINE['out_of_area'], $history->note);
        $this->assertNotNull($history->created_at);
    }

    public function test_other_needs_a_text_and_an_unknown_reason_is_refused(): void
    {
        $driver = $this->driver();
        $assignment = $this->task($driver);

        foreach ([[], ['decline_code' => 'other'], ['decline_code' => 'other', 'decline_note' => '   '], ['decline_code' => 'tired'], ['decline_code' => 'out_of_area', 'decline_note' => str_repeat('a', 1001)]] as $input) {
            $this->decline($driver, $assignment, $input)->assertUnprocessable();
        }
        $this->assertSame(['assigned', 'assigned'], [$assignment->fresh()->status, $assignment->vendorOrder->fresh()->status]);
        $this->assertSame(0, OrderStatusHistory::count());

        $this->decline($driver, $assignment, ['decline_code' => 'other', 'decline_note' => 'Family matter.'])->assertOk();
        $this->assertSame(['other', 'Family matter.'], [$assignment->fresh()->decline_code, $assignment->fresh()->decline_note]);
        $this->assertSame('other — أخرى: Family matter.', OrderStatusHistory::sole()->note);
    }

    public function test_a_task_cannot_be_declined_once_accepted_picked_up_or_finished(): void
    {
        $driver = $this->driver();
        foreach ([['accepted', 'assigned'], ['picked_up', 'out_for_delivery'], ['out_for_delivery', 'out_for_delivery'], ['delivered', 'delivered'], ['failed', 'out_for_delivery'], ['cancelled', 'ready_for_delivery']] as [$status, $orderStatus]) {
            $assignment = $this->task($driver, $status, $orderStatus);

            $this->decline($driver, $assignment, ['decline_code' => 'out_of_area'])->assertUnprocessable();

            $this->assertSame([$status, null, $orderStatus], [$assignment->fresh()->status, $assignment->fresh()->declined_at, $assignment->vendorOrder->fresh()->status], $status);
        }
        $this->assertSame(0, OrderStatusHistory::count());
    }

    public function test_after_declining_the_driver_cannot_act_on_the_task_again(): void
    {
        $driver = $this->driver();
        $assignment = $this->task($driver);
        $this->decline($driver, $assignment, ['decline_code' => 'schedule_conflict'])->assertOk();

        foreach (['accept', 'pick-up', 'delivered', 'decline'] as $action) {
            $this->actingAs($driver, 'delivery_driver')->patchJson(route('delivery-driver.dashboard.assignments.'.$action, $assignment), ['decline_code' => 'out_of_area'])->assertUnprocessable();
        }
        $this->assertSame('cancelled', $assignment->fresh()->status);
        $this->assertSame(1, OrderStatusHistory::count());
    }

    public function test_only_the_assigned_and_approved_driver_can_decline(): void
    {
        $driver = $this->driver();
        $assignment = $this->task($driver);

        $this->decline($this->driver(), $assignment, ['decline_code' => 'out_of_area'])->assertNotFound();

        $pending = DeliveryDriver::factory()->create();
        $pending->profile()->create(['approval_status' => 'pending']);
        $this->decline($pending, $assignment, ['decline_code' => 'out_of_area'])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->patchJson(route('delivery-driver.dashboard.assignments.decline', $assignment), ['decline_code' => 'out_of_area'])->assertUnauthorized();
        $this->assertSame('assigned', $assignment->fresh()->status);
        $this->assertSame(0, OrderStatusHistory::count());
    }

    public function test_the_admin_reassigns_by_hand_and_the_declined_attempt_is_kept(): void
    {
        $driver = $this->driver();
        $assignment = $this->task($driver);
        $this->decline($driver, $assignment, ['decline_code' => 'vehicle_unavailable'])->assertOk();
        $vendorOrder = $assignment->vendorOrder;

        $this->assertSame([$vendorOrder->id], $this->issueList());
        $this->actingAs($this->admin, 'admin')->get(route('dashboard.vendor-orders.show', $vendorOrder))->assertOk()
            ->assertSee('data-delivery-issue="declined"', false)
            ->assertSee('The driver declined the task before accepting it')
            ->assertSee(DeliveryReasons::DECLINE['vehicle_unavailable']);

        $next = $this->driver();
        $this->actingAs($this->admin, 'admin')->patch(route('dashboard.vendor-orders.assign-driver', $vendorOrder), ['delivery_driver_id' => $next->id])->assertSessionHasNoErrors();

        $attempts = DeliveryAssignment::where('vendor_order_id', $vendorOrder->id)->orderBy('id')->get();
        $this->assertSame([[$driver->id, 'cancelled', 'vehicle_unavailable'], [$next->id, 'assigned', null]], $attempts->map(fn ($a) => [$a->delivery_driver_id, $a->status, $a->decline_code])->all());
        $this->assertSame([], $this->issueList(), 'The new attempt takes it off the list.');
    }

    /** A reassignment by the admin, committed while the driver declines: the decline is refused and changes nothing. */
    public function test_a_reassignment_committed_during_the_decline_wins(): void
    {
        $driver = $this->driver();
        $other = $this->driver();
        $assignment = $this->task($driver);
        $this->whenTheTransactionBegins(function () use ($assignment, $other): void {
            DB::table('delivery_assignments')->where('id', $assignment->id)->update(['delivery_driver_id' => $other->id]);
            DB::table('vendor_orders')->where('id', $assignment->vendor_order_id)->update(['delivery_driver_id' => $other->id]);
        });

        // The locked re-read saw the reassignment: 404. (In this SQLite simulation the reassignment is written inside the
        // decline's transaction, so the refusal rolls it back with it; only the decline's own effect is checked.)
        $this->decline($driver, $assignment, ['decline_code' => 'out_of_area'])->assertNotFound();

        $this->assertSame(['assigned', null, null], [$assignment->fresh()->status, $assignment->fresh()->declined_at, $assignment->fresh()->decline_code]);
        $this->assertSame('assigned', $assignment->vendorOrder->fresh()->status);
        $this->assertSame(0, OrderStatusHistory::count());
    }

    /** An accept committed while the same driver declines (two tabs): the locked row is accepted, so the decline is refused. */
    public function test_an_accept_committed_during_the_decline_wins(): void
    {
        $driver = $this->driver();
        $assignment = $this->task($driver);
        $this->whenTheTransactionBegins(fn () => DB::table('delivery_assignments')->where('id', $assignment->id)->update(['status' => 'accepted']));

        // The locked re-read saw the accept: 422 (the simulated accept is rolled back with the refusal, see above).
        $this->decline($driver, $assignment, ['decline_code' => 'out_of_area'])->assertUnprocessable();

        $this->assertSame([null, 'assigned', $driver->id], [$assignment->fresh()->declined_at, $assignment->vendorOrder->fresh()->status, $assignment->vendorOrder->fresh()->delivery_driver_id]);
        $this->assertSame(0, OrderStatusHistory::count());
    }

    public function test_the_browser_page_offers_the_decline_form_and_shows_the_decision(): void
    {
        $driver = $this->driver();
        $assignment = $this->task($driver);

        $html = $this->actingAs($driver, 'delivery_driver')->get(route('delivery-driver.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('action="'.route('delivery-driver.dashboard.assignments.decline', $assignment).'"', $html);
        foreach (DeliveryReasons::DECLINE as $code => $label) {
            $this->assertStringContainsString('<option value="'.$code.'">'.$label.'</option>', $html);
        }

        $this->actingAs($driver, 'delivery_driver')->from(route('delivery-driver.dashboard'))
            ->patch(route('delivery-driver.dashboard.assignments.decline', $assignment), ['decline_code' => 'other', 'decline_note' => 'Too far tonight.'])
            ->assertRedirect(route('delivery-driver.dashboard'))->assertSessionHas('success');

        $this->actingAs($driver, 'delivery_driver')->get(route('delivery-driver.dashboard'))->assertOk()
            ->assertSee('رفضتَ هذه المهمة')
            ->assertSee('أخرى: Too far tonight.')
            ->assertDontSee('action="'.route('delivery-driver.dashboard.assignments.decline', $assignment).'"', false);
    }

    // ---------------------------------------------------------------- 6. failed delivery

    public function test_a_failure_takes_a_listed_reason_and_an_optional_note(): void
    {
        $driver = $this->driver();
        $withNote = $this->task($driver, 'picked_up', 'out_for_delivery');
        $withoutNote = $this->task($driver, 'out_for_delivery', 'out_for_delivery');

        $this->fail_($driver, $withNote, ['failure_code' => 'payment_failed', 'failure_reason' => 'Paid 30 of 50.'])->assertOk()
            ->assertJsonPath('assignment.failure_code', 'payment_failed');
        $this->fail_($driver, $withoutNote, ['failure_code' => 'customer_unreachable'])->assertOk();

        $this->assertSame(['failed', 'payment_failed', 'Paid 30 of 50.'], [$withNote->fresh()->status, $withNote->fresh()->failure_code, $withNote->fresh()->failure_reason]);
        $this->assertSame(['failed', 'customer_unreachable', null], [$withoutNote->fresh()->status, $withoutNote->fresh()->failure_code, $withoutNote->fresh()->failure_reason]);
        $this->assertNotNull($withNote->fresh()->failed_at);

        $notes = OrderStatusHistory::where('status', 'delivery_failed')->where('actor_type', 'delivery_driver')->where('actor_id', $driver->id)->orderBy('id')->pluck('note')->all();
        $this->assertSame(['payment_failed — تعذّر الدفع (لم يُحصَّل المبلغ كاملًا): Paid 30 of 50.', 'customer_unreachable — تعذّر التواصل مع المستلم'], $notes);
        // The custody rule is unchanged: after pickup the order stays out for delivery until the return.
        $this->assertSame('out_for_delivery', $withNote->vendorOrder->fresh()->status);
    }

    public function test_a_failure_without_a_listed_reason_is_refused(): void
    {
        $driver = $this->driver();
        $assignment = $this->task($driver, 'picked_up', 'out_for_delivery');

        foreach ([['failure_reason' => 'Free text only.'], ['failure_code' => 'bad_weather'], ['failure_code' => 'other'], ['failure_code' => 'other', 'failure_reason' => ''], ['failure_code' => 'wrong_address', 'failure_reason' => str_repeat('a', 1001)]] as $input) {
            $this->fail_($driver, $assignment, $input)->assertUnprocessable();
        }
        $this->assertSame(['picked_up', null], [$assignment->fresh()->status, $assignment->fresh()->failure_code]);
        $this->assertSame(0, OrderStatusHistory::count());

        $this->fail_($driver, $assignment, ['failure_code' => 'other', 'failure_reason' => 'Road closed.'])->assertOk();
        $this->assertSame(['other', 'Road closed.'], [$assignment->fresh()->failure_code, $assignment->fresh()->failure_reason]);
    }

    public function test_after_a_failure_the_driver_cannot_retry_or_change_the_task(): void
    {
        $driver = $this->driver();
        $assignment = $this->task($driver, 'picked_up', 'out_for_delivery');
        $this->fail_($driver, $assignment, ['failure_code' => 'customer_absent'])->assertOk();

        foreach (['accept', 'pick-up', 'delivered', 'failed', 'decline'] as $action) {
            $this->actingAs($driver, 'delivery_driver')
                ->patchJson(route('delivery-driver.dashboard.assignments.'.$action, $assignment), ['failure_code' => 'customer_absent', 'decline_code' => 'out_of_area'])
                ->assertUnprocessable();
        }
        $this->assertSame(['failed', 'customer_absent', 'out_for_delivery'], [$assignment->fresh()->status, $assignment->fresh()->failure_code, $assignment->vendorOrder->fresh()->status]);
        $this->assertSame(1, OrderStatusHistory::count());
    }

    public function test_the_admin_sees_the_failure_and_the_list_filter_finds_it(): void
    {
        $driver = $this->driver();
        $failed = $this->task($driver, 'picked_up', 'out_for_delivery');
        $failedBeforePickup = $this->task($driver, 'accepted', 'assigned');
        $normal = $this->task($driver, 'out_for_delivery', 'out_for_delivery');
        $this->fail_($driver, $failed, ['failure_code' => 'payment_failed', 'failure_reason' => 'Paid 30 of 50.'])->assertOk();
        $this->fail_($driver, $failedBeforePickup, ['failure_code' => 'vehicle_issue'])->assertOk();

        $ids = $this->issueList();
        sort($ids);
        $this->assertSame([$failed->vendor_order_id, $failedBeforePickup->vendor_order_id], $ids);
        $this->assertNotContains($normal->vendor_order_id, $ids);

        $this->actingAs($this->admin, 'admin')->get(route('dashboard.vendor-orders.show', $failed->vendorOrder))->assertOk()
            ->assertSee('data-delivery-issue="failed"', false)
            ->assertSee('Delivery failure reported by the driver')
            ->assertSee('تعذّر الدفع (لم يُحصَّل المبلغ كاملًا): Paid 30 of 50.');
    }

    public function test_the_browser_page_offers_the_listed_failure_reasons_and_shows_the_reason(): void
    {
        $driver = $this->driver();
        $assignment = $this->task($driver, 'picked_up', 'out_for_delivery');

        $html = $this->actingAs($driver, 'delivery_driver')->get(route('delivery-driver.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('name="failure_code" required', $html);
        foreach (DeliveryReasons::FAILURE as $code => $label) {
            $this->assertStringContainsString('<option value="'.$code.'">'.$label.'</option>', $html);
        }

        $this->actingAs($driver, 'delivery_driver')->from(route('delivery-driver.dashboard'))
            ->patch(route('delivery-driver.dashboard.assignments.failed', $assignment), ['failure_code' => 'wrong_address', 'failure_reason' => 'No building number.'])
            ->assertRedirect(route('delivery-driver.dashboard'));

        $this->actingAs($driver, 'delivery_driver')->get(route('delivery-driver.dashboard'))->assertOk()
            ->assertSee('سبب التعذر')
            ->assertSee('العنوان غير صحيح أو غير مكتمل: No building number.');
    }

    // ---------------------------------------------------------------- helpers

    private function decline(DeliveryDriver $driver, DeliveryAssignment $assignment, array $input)
    {
        return $this->actingAs($driver, 'delivery_driver')->patchJson(route('delivery-driver.dashboard.assignments.decline', $assignment), $input);
    }

    private function fail_(DeliveryDriver $driver, DeliveryAssignment $assignment, array $input)
    {
        return $this->actingAs($driver, 'delivery_driver')->patchJson(route('delivery-driver.dashboard.assignments.failed', $assignment), $input);
    }

    /** @return array<int, int> the vendor orders the admin list shows with the delivery issue filter */
    private function issueList(): array
    {
        return $this->actingAs($this->admin, 'admin')->get(route('dashboard.vendor-orders.index', ['delivery_issue' => 1]))->assertOk()
            ->viewData('vendorOrders')->getCollection()->pluck('id')->all();
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

    private function task(DeliveryDriver $driver, string $status = 'assigned', string $vendorOrderStatus = 'assigned'): DeliveryAssignment
    {
        $order = Order::query()->create([
            'number' => 'HF-'.uniqid(), 'customer_id' => Customer::factory()->create()->id, 'status' => 'processing',
            'payment_method' => 'cod', 'payment_status' => 'pending', 'subtotal' => 50, 'delivery_total' => 0, 'grand_total' => 50,
        ]);
        $vendorOrder = VendorOrder::query()->create([
            'number' => 'VO-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => Vendor::factory()->approved()->create()->id,
            'delivery_driver_id' => $driver->id, 'status' => $vendorOrderStatus, 'subtotal' => 50, 'delivery_fee' => 0, 'commission_amount' => 0,
            'total' => 50, 'ready_at' => now(),
        ]);

        return DeliveryAssignment::query()->create([
            'vendor_order_id' => $vendorOrder->id, 'delivery_driver_id' => $driver->id, 'status' => $status, 'assigned_at' => now(),
            'picked_up_at' => in_array($status, ['picked_up', 'out_for_delivery', 'delivered'], true) || $vendorOrderStatus === 'out_for_delivery' ? now() : null,
            'failed_at' => $status === 'failed' ? now() : null,
        ]);
    }
}
