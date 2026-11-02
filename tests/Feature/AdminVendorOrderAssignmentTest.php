<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\VendorOrderManagementController;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Language;
use App\Models\Order;
use App\Models\TranslationValue;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Database\Seeders\AdminVendorOrderTranslationSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * VEN-BE-020 (slice 2A): Admin driver assignment (dashboard.vendor-orders.assign-driver) only from the statuses SRS §4.3,
 * §4.5 and FR-ADM-019 allow, plus the current reassignment from Assigned and Out for Delivery, kept for now as the only
 * recovery paths. Every other status is refused with 422 and changes nothing. The vendor order is locked and re-read
 * inside the existing transaction. These tests run sequentially on SQLite: they cannot prove the row lock under real
 * concurrency (SQLite ignores FOR UPDATE; see VEN-BE-029).
 */
class AdminVendorOrderAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private const ALL_STATUSES = ['pending', 'accepted', 'preparing', 'ready_for_delivery', 'assigned', 'out_for_delivery', 'delivered', 'completed', 'rejected', 'cancelled'];

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
    }

    // ---------------------------------------------------------------- the rule

    public function test_the_assignable_statuses_are_ready_assigned_and_out_for_delivery(): void
    {
        $this->assertSame(['ready_for_delivery', 'assigned', 'out_for_delivery'], VendorOrderManagementController::ASSIGNABLE_STATUSES);
        $this->assertSame(['pending', 'accepted', 'preparing'], VendorOrderManagementController::NOT_READY_STATUSES);
    }

    public function test_initial_assignment_from_ready_moves_the_order_to_assigned(): void
    {
        $order = $this->vendorOrder('ready_for_delivery');
        $driver = $this->driver();

        $this->assign($order, $driver, notes: 'Fragile.')->assertRedirect()->assertSessionHas('success');

        $assignment = DeliveryAssignment::sole();
        $this->assertSame([$order->id, $driver->id, $this->admin->id, 'assigned', 'Fragile.'], [$assignment->vendor_order_id, $assignment->delivery_driver_id, $assignment->assigned_by, $assignment->status, $assignment->notes]);
        $this->assertNotNull($assignment->assigned_at);
        $this->assertSame(['assigned', $driver->id], [$order->fresh()->status, $order->fresh()->delivery_driver_id]);
        $this->assertSame('processing', $order->order->fresh()->status, 'The parent order is unchanged.');
    }

    public function test_every_status_has_one_outcome_and_a_refusal_changes_nothing(): void
    {
        $outcomes = [];
        foreach (self::ALL_STATUSES as $status) {
            $order = $this->vendorOrder($status);
            $parentStatus = $order->order->status;

            $outcomes[$status] = $this->assign($order, $this->driver())->getStatusCode();

            if ($outcomes[$status] === 422) {
                $this->assertSame([$status, null], [$order->fresh()->status, $order->fresh()->delivery_driver_id], $status);
                $this->assertSame(0, DeliveryAssignment::where('vendor_order_id', $order->id)->count(), "{$status}: no assignment row.");
                $this->assertSame($parentStatus, $order->order->fresh()->status, "{$status}: the parent order is unchanged.");
            }
        }

        $this->assertSame([
            'pending' => 422, 'accepted' => 422, 'preparing' => 422, 'ready_for_delivery' => 302, 'assigned' => 302,
            'out_for_delivery' => 302, 'delivered' => 422, 'completed' => 422, 'rejected' => 422, 'cancelled' => 422,
        ], $outcomes);
    }

    public function test_a_delivered_order_keeps_its_delivered_assignment(): void
    {
        $driver = $this->driver();
        $order = $this->vendorOrder('delivered', $driver);
        $assignment = $this->assignmentRow($order, $driver, 'delivered', ['picked_up_at' => '2026-10-02 10:00:00', 'delivered_at' => '2026-10-02 11:00:00']);
        $before = $assignment->fresh()->getAttributes();

        $this->assign($order, $this->driver())->assertStatus(422);

        $this->assertSame($before, $assignment->fresh()->getAttributes(), 'The delivered assignment is untouched.');
        $this->assertSame(['delivered', $driver->id], [$order->fresh()->status, $order->fresh()->delivery_driver_id]);
    }

    // ---------------------------------------------------------------- kept for now (transitional)

    public function test_reassignment_from_assigned_is_kept_and_reuses_the_row(): void
    {
        $first = $this->driver();
        $second = $this->driver();
        $order = $this->vendorOrder('assigned', $first);
        $assignment = $this->assignmentRow($order, $first, 'accepted');

        $this->assign($order, $second)->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, DeliveryAssignment::count(), 'The row is overwritten (no history yet: slice 2D).');
        $this->assertSame([$assignment->id, $second->id, 'assigned'], [DeliveryAssignment::sole()->id, DeliveryAssignment::sole()->delivery_driver_id, DeliveryAssignment::sole()->status]);
        $this->assertSame(['assigned', $second->id], [$order->fresh()->status, $order->fresh()->delivery_driver_id]);
    }

    public function test_reassignment_from_out_for_delivery_is_kept_as_today_including_its_known_risks(): void
    {
        // The recovery path after a failed delivery (slice 2C is not decided).
        $first = $this->driver();
        $second = $this->driver();
        $failed = $this->vendorOrder('out_for_delivery', $first);
        $this->assignmentRow($failed, $first, 'failed', ['picked_up_at' => '2026-10-02 10:00:00', 'failed_at' => '2026-10-02 11:00:00', 'failure_reason' => 'Customer unreachable.']);

        $this->assign($failed, $second)->assertRedirect()->assertSessionHas('success');

        $row = DeliveryAssignment::where('vendor_order_id', $failed->id)->sole();
        $this->assertSame([$second->id, 'assigned', 'Customer unreachable.'], [$row->delivery_driver_id, $row->status, $row->failure_reason], 'The failure fields are left as they were.');
        $this->assertNotNull($row->failed_at);
        $this->assertSame(['out_for_delivery', $second->id], [$failed->fresh()->status, $failed->fresh()->delivery_driver_id]);

        // UNRESOLVED (slice 2B): a picked-up delivery can still be moved to another driver; the goods are with the first.
        $pickedUp = $this->vendorOrder('out_for_delivery', $first);
        $this->assignmentRow($pickedUp, $first, 'picked_up', ['picked_up_at' => '2026-10-02 10:00:00']);

        $this->assign($pickedUp, $second)->assertRedirect();

        $this->assertSame([$second->id, 'assigned'], [DeliveryAssignment::where('vendor_order_id', $pickedUp->id)->sole()->delivery_driver_id, DeliveryAssignment::where('vendor_order_id', $pickedUp->id)->sole()->status]);
        $this->assertSame('out_for_delivery', $pickedUp->fresh()->status);
    }

    public function test_an_early_assigned_order_is_refused_until_ready_then_assigned(): void
    {
        // An assignment made before slice 2A, while the order was still being prepared.
        $first = $this->driver();
        $order = $this->vendorOrder('preparing', $first);
        $early = $this->assignmentRow($order, $first, 'assigned');
        $before = $early->fresh()->getAttributes();

        $this->assign($order, $this->driver())->assertStatus(422);
        $this->assertSame($before, $early->fresh()->getAttributes());
        $this->assertSame('preparing', $order->fresh()->status);

        $order->update(['status' => 'ready_for_delivery', 'ready_at' => now()]);
        $this->assign($order, $first)->assertRedirect();

        $this->assertSame(1, DeliveryAssignment::count());
        $this->assertSame([$early->id, $first->id, 'assigned'], [DeliveryAssignment::sole()->id, DeliveryAssignment::sole()->delivery_driver_id, DeliveryAssignment::sole()->status]);
        $this->assertSame('assigned', $order->fresh()->status, 'The order now reaches Assigned, so the driver can pick it up.');
    }

    public function test_repeated_sequential_requests_keep_one_assignment_row(): void
    {
        $order = $this->vendorOrder('ready_for_delivery');
        $driver = $this->driver();

        $this->assign($order, $driver)->assertRedirect();
        $this->assign($order, $driver)->assertRedirect();

        $this->assertSame(1, DeliveryAssignment::count());
        $this->assertSame('assigned', $order->fresh()->status);
    }

    public function test_the_status_is_re_read_inside_the_transaction_before_any_write(): void
    {
        // Sequential evidence only: the vendor order is read again (with lockForUpdate, a no-op on SQLite) inside the
        // transaction, before the assignment is written. Real lock behavior needs MySQL/InnoDB (VEN-BE-029).
        $order = $this->vendorOrder('ready_for_delivery');
        $driver = $this->driver();
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = [DB::transactionLevel(), $query->sql];
        });

        $this->assign($order, $driver)->assertRedirect();

        $inTransaction = array_values(array_filter($queries, fn ($q) => $q[0] > 0));
        $this->assertMatchesRegularExpression('/^select \* from "vendor_orders" where "id" = \? limit 1$/', $inTransaction[0][1] ?? '', 'The first statement in the transaction re-reads the vendor order.');
        $firstWrite = collect($inTransaction)->search(fn ($q) => preg_match('/^(insert|update)/', $q[1]) === 1);
        $this->assertGreaterThan(0, $firstWrite);
    }

    // ---------------------------------------------------------------- access and drivers

    public function test_only_admins_allowed_to_assign_can_assign(): void
    {
        $order = $this->vendorOrder('ready_for_delivery');
        $driver = $this->driver();

        $viewer = Admin::factory()->create(['super_admin' => false, 'status' => 'active']);
        $viewer->roles()->create(['role_name' => 'vendor-orders.view', 'ability' => 'allow']);
        $this->actingAs($viewer, 'admin')->patch(route('dashboard.vendor-orders.assign-driver', $order), ['delivery_driver_id' => $driver->id])->assertForbidden();
        $this->assertSame(0, DeliveryAssignment::count());
        $this->assertSame('ready_for_delivery', $order->fresh()->status);

        $assigner = Admin::factory()->create(['super_admin' => false, 'status' => 'active']);
        $assigner->roles()->create(['role_name' => 'vendor-orders.assign-driver', 'ability' => 'allow']);
        $this->actingAs($assigner, 'admin')->patch(route('dashboard.vendor-orders.assign-driver', $order), ['delivery_driver_id' => $driver->id])->assertRedirect();
        $this->assertSame([$assigner->id, 'assigned'], [DeliveryAssignment::sole()->assigned_by, $order->fresh()->status]);
    }

    public function test_a_guest_is_sent_to_the_admin_login(): void
    {
        $order = $this->vendorOrder('ready_for_delivery');

        $this->patch(route('dashboard.vendor-orders.assign-driver', $order), ['delivery_driver_id' => $this->driver()->id])->assertRedirect();

        $this->assertSame(0, DeliveryAssignment::count());
        $this->assertSame('ready_for_delivery', $order->fresh()->status);
    }

    public function test_an_inactive_driver_is_refused_and_nothing_changes(): void
    {
        foreach (['blocked', 'pending'] as $status) {
            $order = $this->vendorOrder('ready_for_delivery');

            $this->assign($order, $this->driver($status))->assertSessionHasErrors('delivery_driver_id');

            $this->assertSame(['ready_for_delivery', null], [$order->fresh()->status, $order->fresh()->delivery_driver_id], $status);
        }
        $this->assertSame(0, DeliveryAssignment::count());
    }

    // ---------------------------------------------------------------- the order page

    public function test_the_page_shows_the_form_only_where_assignment_is_accepted(): void
    {
        $this->seedTexts();
        $expected = [
            'pending' => 'not_ready', 'accepted' => 'not_ready', 'preparing' => 'not_ready', 'ready_for_delivery' => 'form', 'assigned' => 'form',
            'out_for_delivery' => 'form', 'delivered' => 'delivered', 'completed' => 'closed', 'rejected' => 'closed', 'cancelled' => 'closed',
        ];

        foreach ($expected as $status => $shown) {
            $order = $this->vendorOrder($status);
            $html = $this->page($order, 'en');
            $actual = match (true) {
                str_contains($html, 'action="'.e(route('dashboard.vendor-orders.assign-driver', $order)).'"') => 'form',
                str_contains($html, 'A driver can be assigned once the vendor marks this order ready for delivery.') => 'not_ready',
                str_contains($html, 'This order has been delivered, so a driver can no longer be assigned.') => 'delivered',
                str_contains($html, 'Closed orders cannot be assigned to a driver.') => 'closed',
                default => 'none',
            };
            $this->assertSame($shown, $actual, $status);
        }
    }

    public function test_the_explanations_are_translated_in_arabic_and_english(): void
    {
        $this->seedTexts();
        $t = AdminVendorOrderTranslationSeeder::TRANSLATIONS;

        foreach (['ar', 'en'] as $locale) {
            $this->assertStringContainsString(e($t['dashboard.Vendor_Order_Assignment_Not_Ready'][$locale]), $this->page($this->vendorOrder('accepted'), $locale), $locale);
            $this->assertStringContainsString(e($t['dashboard.Vendor_Order_Assignment_Delivered'][$locale]), $this->page($this->vendorOrder('delivered'), $locale), $locale);
        }
    }

    public function test_an_admin_who_cannot_assign_sees_no_form(): void
    {
        $viewer = Admin::factory()->create(['super_admin' => false, 'status' => 'active']);
        $viewer->roles()->create(['role_name' => 'vendor-orders.view', 'ability' => 'allow']);
        $order = $this->vendorOrder('ready_for_delivery');

        $html = $this->actingAs($viewer, 'admin')->get(route('dashboard.vendor-orders.show', $order))->assertOk()->getContent();

        $this->assertStringNotContainsString('action="'.e(route('dashboard.vendor-orders.assign-driver', $order)).'"', $html);
        $this->assertStringContainsString('You do not have permission to assign drivers.', $html);
    }

    // ---------------------------------------------------------------- the texts

    public function test_the_seeder_holds_exactly_the_two_texts_with_the_pages_english_defaults(): void
    {
        $view = file_get_contents(resource_path('views/dashboard/vendor-orders/show.blade.php'));
        preg_match_all("/t\\('(dashboard\\.Vendor_Order_Assignment_[A-Za-z_]+)', '([^']+)'\\)/", $view, $matches, PREG_SET_ORDER);
        $defaults = [];
        foreach ($matches as [, $key, $default]) {
            $defaults[$key] = $default;
        }

        $this->assertSame(array_keys(AdminVendorOrderTranslationSeeder::TRANSLATIONS), array_keys($defaults));
        foreach (AdminVendorOrderTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame(['ar', 'en'], array_keys($values), $key);
            $this->assertSame($defaults[$key], $values['en'], "{$key}: en is the page's default.");
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], "{$key}: ar is Arabic.");
        }
    }

    public function test_the_seeder_only_inserts_missing_rows_and_is_not_called_from_database_seeder(): void
    {
        TranslationValue::query()->create(['key' => 'dashboard.Vendor_Order_Assignment_Not_Ready', 'locale' => 'ar', 'value' => 'نص معدّل']);

        $this->seed(AdminVendorOrderTranslationSeeder::class);
        $this->seed(AdminVendorOrderTranslationSeeder::class);

        $this->assertSame(4, TranslationValue::where('key', 'like', 'dashboard.Vendor_Order_Assignment_%')->count());
        $this->assertSame('نص معدّل', TranslationValue::where('key', 'dashboard.Vendor_Order_Assignment_Not_Ready')->where('locale', 'ar')->value('value'), 'An edited value is kept.');
        $this->assertStringNotContainsString('AdminVendorOrderTranslationSeeder', file_get_contents((new \ReflectionClass(DatabaseSeeder::class))->getFileName()));
    }

    // ---------------------------------------------------------------- helpers

    private function assign(VendorOrder $order, DeliveryDriver $driver, ?string $notes = null)
    {
        return $this->actingAs($this->admin, 'admin')->patch(route('dashboard.vendor-orders.assign-driver', $order), array_filter(['delivery_driver_id' => $driver->id, 'notes' => $notes]));
    }

    private function page(VendorOrder $order, string $locale): string
    {
        return $this->actingAs($this->admin, 'admin')->withSession(['locale' => $locale])->get(route('dashboard.vendor-orders.show', $order))->assertOk()->getContent();
    }

    private function seedTexts(): void
    {
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->seed(AdminVendorOrderTranslationSeeder::class);
    }

    private function driver(string $status = 'active'): DeliveryDriver
    {
        $driver = DeliveryDriver::factory()->create(['status' => $status]);
        $driver->profile()->create(['approval_status' => 'approved', 'approved_at' => now(), 'is_available' => true]);

        return $driver;
    }

    private function assignmentRow(VendorOrder $order, DeliveryDriver $driver, string $status, array $attributes = []): DeliveryAssignment
    {
        return DeliveryAssignment::query()->create(array_merge([
            'vendor_order_id' => $order->id,
            'delivery_driver_id' => $driver->id,
            'status' => $status,
            'assigned_at' => '2026-10-02 09:00:00',
        ], $attributes));
    }

    private function vendorOrder(string $status, ?DeliveryDriver $driver = null): VendorOrder
    {
        $order = Order::query()->create([
            'number' => 'HF-'.uniqid(),
            'customer_id' => Customer::factory()->create()->id,
            'status' => match ($status) {
                'pending' => 'pending',
                'completed' => 'completed',
                'rejected', 'cancelled' => 'cancelled',
                default => 'processing',
            },
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
            'vendor_id' => Vendor::factory()->approved()->create()->id,
            'delivery_driver_id' => $driver?->id,
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
