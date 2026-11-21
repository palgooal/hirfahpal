<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\City;
use App\Models\Customer;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Governorate;
use App\Models\Language;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\DeliveryDriverAuthTranslationSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * E2-MVP batch 01: (1) a vendor order is assigned only to an approved, active and available driver, checked on the
 * server; (2) a driver who is not approved gets the application status page in the browser (pending, or rejected with
 * its reason) and JSON keeps the 403; (3) a driver applies with a governorate, a city of that governorate and a vehicle
 * type, shown to the admin for the review.
 */
class DeliveryDriverApplicationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private Governorate $governorate;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->seed(AdminAuthTranslationSeeder::class);
        $this->seed(DeliveryDriverAuthTranslationSeeder::class);

        $this->admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active', 'name' => 'Reviewer Admin Secret', 'email' => 'reviewer-secret@example.com']);
        $this->governorate = Governorate::create(['name' => 'Gaza Governorate', 'slug' => 'gaza-governorate', 'status' => 'active']);
        $this->city = City::create(['governorate_id' => $this->governorate->id, 'name' => 'Gaza City', 'slug' => 'gaza-city', 'status' => 'active']);
    }

    // ---------------------------------------------------------------- 1. assignment

    public function test_an_approved_active_available_driver_is_assigned(): void
    {
        $order = $this->vendorOrder();
        $driver = $this->driver();

        $this->assign($order, $driver)->assertRedirect()->assertSessionHas('success')->assertSessionHasNoErrors();

        $this->assertSame(['assigned', $driver->id], [$order->fresh()->status, $order->fresh()->delivery_driver_id]);
        $this->assertSame($driver->id, DeliveryAssignment::sole()->delivery_driver_id);
    }

    public function test_a_driver_not_eligible_is_refused_on_the_server_and_nothing_changes(): void
    {
        $cases = [
            'under review' => $this->driver(approval: 'pending'),
            'rejected' => $this->driver(approval: 'rejected'),
            'blocked account' => $this->driver(status: 'blocked'),
            'pending account' => $this->driver(status: 'pending'),
            'unavailable' => $this->driver(available: false),
            'no profile' => DeliveryDriver::factory()->create(['status' => 'active']),
        ];

        foreach ($cases as $case => $driver) {
            $order = $this->vendorOrder();

            $this->assign($order, $driver)->assertRedirect()->assertSessionHasErrors('delivery_driver_id');

            $fresh = $order->fresh();
            $this->assertSame(['ready_for_delivery', null], [$fresh->status, $fresh->delivery_driver_id], $case);
        }
        $this->assertSame(0, DeliveryAssignment::count());
    }

    public function test_a_self_registered_driver_cannot_be_assigned_until_approved(): void
    {
        $this->register();
        $driver = DeliveryDriver::where('email', 'new-driver@example.com')->sole();
        $order = $this->vendorOrder();

        $this->assign($order, $driver)->assertSessionHasErrors('delivery_driver_id');
        $this->assertSame(0, DeliveryAssignment::count());

        $this->actingAs($this->admin, 'admin')->patch(route('dashboard.delivery-drivers.approve', $driver))->assertRedirect();
        $this->assign($order, $driver->fresh())->assertSessionHasNoErrors();
        $this->assertSame($driver->id, DeliveryAssignment::sole()->delivery_driver_id);
    }

    public function test_a_reassignment_to_an_ineligible_driver_keeps_the_current_assignment(): void
    {
        $order = $this->vendorOrder();
        $first = $this->driver();
        $this->assign($order, $first)->assertSessionHasNoErrors();

        $this->assign($order, $this->driver(approval: 'pending'))->assertSessionHasErrors('delivery_driver_id');

        $this->assertSame(['assigned', $first->id], [$order->fresh()->status, $order->fresh()->delivery_driver_id]);
        $this->assertSame([$first->id, 'assigned'], [DeliveryAssignment::sole()->delivery_driver_id, DeliveryAssignment::sole()->status]);
    }

    /**
     * The eligibility changes between the validation and the transaction: the write each admin path makes (reject,
     * block, availability off) is committed when assignDriver's transaction begins, after the validation passed.
     * The locked re-check inside the transaction refuses the assignment, for a first assignment and a reassignment.
     */
    public function test_an_eligibility_change_after_the_validation_refuses_the_assignment(): void
    {
        $changes = [
            'rejected' => fn (DeliveryDriver $driver) => DB::table('delivery_driver_profiles')->where('delivery_driver_id', $driver->id)
                ->update(['approval_status' => 'rejected', 'approved_at' => null, 'rejection_reason' => 'Late rejection.', 'is_available' => false]),
            'blocked' => fn (DeliveryDriver $driver) => DB::table('delivery_drivers')->where('id', $driver->id)->update(['status' => 'blocked']),
            'unavailable' => fn (DeliveryDriver $driver) => DB::table('delivery_driver_profiles')->where('delivery_driver_id', $driver->id)->update(['is_available' => false]),
            'back to review' => fn (DeliveryDriver $driver) => DB::table('delivery_driver_profiles')->where('delivery_driver_id', $driver->id)->update(['approval_status' => 'pending']),
        ];

        foreach ($changes as $case => $change) {
            // A first assignment.
            $order = $this->vendorOrder();
            $driver = $this->driver();
            $this->changeWhenTheTransactionBegins(fn () => $change($driver));

            $this->assign($order, $driver)->assertSessionHasErrors('delivery_driver_id');

            $this->assertSame(['ready_for_delivery', null], [$order->fresh()->status, $order->fresh()->delivery_driver_id], $case);
            $this->assertSame(0, DeliveryAssignment::where('vendor_order_id', $order->id)->count(), $case);

            // A reassignment: the current assignment is kept as it is.
            $first = $this->driver();
            $this->assign($order, $first)->assertSessionHasNoErrors();
            $current = DeliveryAssignment::where('vendor_order_id', $order->id)->sole();
            $next = $this->driver();
            $this->changeWhenTheTransactionBegins(fn () => $change($next));

            $this->assign($order, $next)->assertSessionHasErrors('delivery_driver_id');

            $this->assertSame(['assigned', $first->id], [$order->fresh()->status, $order->fresh()->delivery_driver_id], $case);
            $this->assertSame([$current->id, $first->id, 'assigned', $current->updated_at->toJSON()], [
                DeliveryAssignment::where('vendor_order_id', $order->id)->sole()->id,
                $current->fresh()->delivery_driver_id, $current->fresh()->status, $current->fresh()->updated_at->toJSON(),
            ], $case);
        }
    }

    /** The re-check reads the driver and the profile inside the transaction, after the order locks, before any write. */
    public function test_the_driver_rows_are_read_inside_the_transaction_after_the_order_locks_and_before_any_write(): void
    {
        $order = $this->vendorOrder();
        $driver = $this->driver();
        $this->actingAs($this->admin, 'admin');
        $baseLevel = DB::transactionLevel();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = [strtolower($query->sql), DB::transactionLevel()];
        });

        $this->assign($order, $driver)->assertSessionHasNoErrors();

        $position = function (string $pattern) use ($queries, $baseLevel): int {
            foreach ($queries as $index => [$sql, $level]) {
                if ($level > $baseLevel && preg_match($pattern, $sql)) {
                    return $index;
                }
            }
            $this->fail("No query in the transaction matches {$pattern}.");
        };
        $order = [
            $position('/^select .* from "vendor_orders"/'),
            $position('/^select .* from "delivery_assignments"/'),
            $position('/^select "id", "status" from "delivery_drivers"/'),
            $position('/^select "id", "approval_status", "is_available" from "delivery_driver_profiles"/'),
            $position('/^insert into "delivery_assignments"/'),
        ];
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order, 'vendor order → assignment → driver → profile → write.');
    }

    // ---------------------------------------------------------------- 2. status page

    public function test_a_newly_registered_driver_sees_under_review(): void
    {
        $this->register()->assertRedirect(route('delivery-driver.dashboard'));

        $this->get(route('delivery-driver.dashboard'))->assertRedirect(route('delivery-driver.approval-status'));
        $this->get(route('delivery-driver.approval-status'))->assertOk()
            ->assertViewIs('auth.accounts.approval-status')
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('طلبك قيد المراجعة')
            ->assertSee('data-driver-status="pending"', false)
            ->assertSee('New Driver')
            ->assertSee('action="'.route('delivery-driver.logout').'"', false)
            ->assertDontSee('rejection-reason', false);
    }

    public function test_a_rejected_driver_signs_in_and_sees_the_rejection_and_its_reason(): void
    {
        $this->register();
        $driver = DeliveryDriver::where('email', 'new-driver@example.com')->sole();
        $this->post(route('delivery-driver.logout'));

        $this->actingAs($this->admin, 'admin')
            ->patch(route('dashboard.delivery-drivers.reject', $driver), ['rejection_reason' => 'Vehicle papers are missing.'])
            ->assertRedirect();
        $this->assertSame(['active', 'rejected', false], [$driver->fresh()->status, $driver->fresh()->profile->approval_status, $driver->fresh()->profile->is_available]);

        $this->post(route('delivery-driver.login.store'), ['login' => 'new-driver@example.com', 'password' => 'secret-password'])
            ->assertRedirect(route('delivery-driver.dashboard'));
        $this->assertAuthenticatedAs($driver, 'delivery_driver');

        $this->get(route('delivery-driver.dashboard'))->assertRedirect(route('delivery-driver.approval-status'));
        $this->get(route('delivery-driver.approval-status'))->assertOk()
            ->assertSee('لم تتم الموافقة على طلبك')
            ->assertSee('data-driver-status="rejected"', false)
            ->assertSee('سبب الرفض')
            ->assertSee('Vehicle papers are missing.')
            ->assertDontSee('طلبك قيد المراجعة')
            // No internal review data.
            ->assertDontSee('Reviewer Admin Secret')
            ->assertDontSee('reviewer-secret@example.com');
    }

    public function test_an_approved_driver_reaches_the_dashboard_and_the_status_page_sends_them_there(): void
    {
        $driver = $this->driver();

        $this->actingAs($driver, 'delivery_driver')->get(route('delivery-driver.dashboard'))->assertOk()->assertViewIs('delivery-driver.dashboard');
        $this->actingAs($driver, 'delivery_driver')->get(route('delivery-driver.approval-status'))->assertRedirect(route('delivery-driver.dashboard'));
    }

    public function test_json_requests_keep_the_403_without_internal_data(): void
    {
        $driver = $this->driver(approval: 'rejected');

        foreach ([route('delivery-driver.dashboard'), route('delivery-driver.dashboard.overview'), route('delivery-driver.dashboard.assignments.index'), route('delivery-driver.dashboard.profile.show')] as $url) {
            $response = $this->actingAs($driver, 'delivery_driver')->getJson($url)->assertForbidden();

            $this->assertSame(['message', 'approval_status'], array_keys($response->json()), $url);
            $this->assertSame('rejected', $response->json('approval_status'));
            $this->assertStringNotContainsString('Reviewer Admin Secret', $response->getContent());
        }
        $this->actingAs($driver, 'delivery_driver')->putJson(route('delivery-driver.dashboard.profile.update'), ['name' => 'Changed'])->assertForbidden();
        $this->assertNotSame('Changed', $driver->fresh()->name);
    }

    public function test_the_status_json_carries_only_the_drivers_own_decision(): void
    {
        $driver = $this->driver(approval: 'rejected');

        $this->actingAs($driver, 'delivery_driver')->getJson(route('delivery-driver.approval-status'))->assertOk()
            ->assertExactJson(['approval_status' => 'rejected', 'rejection_reason' => 'Rejected for the test.', 'dashboard_url' => null]);
    }

    public function test_there_is_no_redirect_loop_and_guests_go_to_the_login_page(): void
    {
        $driver = $this->driver(approval: 'pending');

        // The status page answers directly, never by redirecting to the gated dashboard.
        $this->actingAs($driver, 'delivery_driver')->get(route('delivery-driver.approval-status'))->assertOk();
        // A browser action from a driver not approved goes to the status page, and changes nothing.
        $this->actingAs($driver, 'delivery_driver')->put(route('delivery-driver.dashboard.profile.update'), ['name' => 'Changed'])
            ->assertRedirect(route('delivery-driver.approval-status'));
        $this->assertNotSame('Changed', $driver->fresh()->name);

        $this->app['auth']->forgetGuards();
        $this->get(route('delivery-driver.approval-status'))->assertRedirect();
        $this->assertGuest('delivery_driver');
    }

    // ---------------------------------------------------------------- 3. registration

    public function test_the_registration_page_offers_the_active_governorates_cities_and_the_vehicle_type(): void
    {
        $inactiveGovernorate = Governorate::create(['name' => 'Closed Governorate', 'slug' => 'closed-governorate', 'status' => 'inactive']);
        City::create(['governorate_id' => $this->governorate->id, 'name' => 'Closed City', 'slug' => 'closed-city', 'status' => 'inactive']);

        $html = $this->get(route('delivery-driver.register'))->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $governorates = $xpath->query('//select[@name="governorate_id" and @required]/option');
        $this->assertSame(['', (string) $this->governorate->id], array_map(fn ($option) => $option->getAttribute('value'), iterator_to_array($governorates)));
        $this->assertSame('Gaza Governorate', $xpath->query('//select[@name="city_id" and @required]/optgroup')->item(0)->getAttribute('label'));
        $this->assertSame(['', (string) $this->city->id], array_map(fn ($option) => $option->getAttribute('value'), iterator_to_array($xpath->query('//select[@name="city_id"]//option'))));
        foreach (['governorate_id', 'city_id', 'vehicle_type'] as $field) {
            $this->assertSame(1, $xpath->query('//label[@for="'.$field.'"]')->length, $field);
        }
        $this->assertStringNotContainsString('Closed Governorate', $html);
        $this->assertStringNotContainsString('Closed City', $html);
        $this->assertNotNull($inactiveGovernorate);
    }

    public function test_registration_saves_the_area_and_vehicle_and_stays_pending_and_unavailable(): void
    {
        $this->register()->assertRedirect(route('delivery-driver.dashboard'))->assertSessionHasNoErrors();

        $driver = DeliveryDriver::where('email', 'new-driver@example.com')->sole();
        $profile = $driver->profile;
        $this->assertSame(
            ['active', 'pending', false, $this->governorate->id, $this->city->id, 'Motorcycle', null, null],
            [$driver->status, $profile->approval_status, (bool) $profile->is_available, $profile->governorate_id, $profile->city_id, $profile->vehicle_type, $profile->approved_at, $profile->approved_by],
        );
    }

    public function test_a_city_outside_the_governorate_is_refused(): void
    {
        $other = Governorate::create(['name' => 'Khan Younis Governorate', 'slug' => 'khan-younis-governorate', 'status' => 'active']);

        $this->register(['governorate_id' => $other->id])
            ->assertSessionHasErrors(['city_id' => 'المدينة المختارة لا تتبع المحافظة المختارة.'])
            ->assertSessionHasInput('vehicle_type', 'Motorcycle');
        $this->assertSame(0, DeliveryDriver::count());
    }

    public function test_invalid_area_or_vehicle_input_is_refused_and_nothing_is_created(): void
    {
        $inactiveCity = City::create(['governorate_id' => $this->governorate->id, 'name' => 'Closed City', 'slug' => 'closed-city', 'status' => 'inactive']);
        $inactiveGovernorate = Governorate::create(['name' => 'Closed Governorate', 'slug' => 'closed-governorate', 'status' => 'inactive']);
        $closedGovernorateCity = City::create(['governorate_id' => $inactiveGovernorate->id, 'name' => 'Far City', 'slug' => 'far-city', 'status' => 'active']);

        $cases = [
            'missing governorate' => [['governorate_id' => ''], 'governorate_id'],
            'missing city' => [['city_id' => ''], 'city_id'],
            'missing vehicle' => [['vehicle_type' => '   '], 'vehicle_type'],
            'vehicle too long' => [['vehicle_type' => str_repeat('a', 256)], 'vehicle_type'],
            'unknown governorate' => [['governorate_id' => 999999], 'governorate_id'],
            'unknown city' => [['city_id' => 999999], 'city_id'],
            'not a number' => [['governorate_id' => 'gaza'], 'governorate_id'],
            'inactive city' => [['city_id' => $inactiveCity->id], 'city_id'],
            'inactive governorate' => [['governorate_id' => $inactiveGovernorate->id, 'city_id' => $closedGovernorateCity->id], 'governorate_id'],
        ];

        foreach ($cases as $case => [$override, $field]) {
            $this->from(route('delivery-driver.register'))->register($override)
                ->assertRedirect(route('delivery-driver.register'))
                ->assertSessionHasErrors($field);
            $this->assertSame(0, DeliveryDriver::count(), $case);
            $this->assertGuest('delivery_driver');
        }
    }

    public function test_the_errors_show_under_the_new_fields_with_the_input_kept(): void
    {
        $this->from(route('delivery-driver.register'))->register(['city_id' => '', 'vehicle_type' => '']);

        $xpath = $this->xpath($this->get(route('delivery-driver.register'))->getContent());
        foreach (['city_id', 'vehicle_type'] as $field) {
            $this->assertSame(1, $xpath->query('//*[@name="'.$field.'" and @aria-invalid="true"]/ancestor::div[p[@id="'.$field.'-error" and @role="alert"]]')->length, $field);
        }
        $this->assertSame('selected', $xpath->query('//select[@name="governorate_id"]/option[@value="'.$this->governorate->id.'"]')->item(0)->getAttribute('selected'));
    }

    public function test_the_admin_sees_the_application_for_the_review(): void
    {
        $this->register();
        $driver = DeliveryDriver::where('email', 'new-driver@example.com')->sole();

        $this->actingAs($this->admin, 'admin')->get(route('dashboard.delivery-drivers.show', $driver))->assertOk()
            ->assertSee('data-driver-application', false)
            ->assertSeeInOrder(['pending', 'Gaza Governorate', 'Gaza City', 'Motorcycle']);
    }

    // ---------------------------------------------------------------- helpers

    /** Runs the change once, when the next transaction begins (assignDriver's, after its validation passed). */
    private function changeWhenTheTransactionBegins(callable $change): void
    {
        $pending = true;
        Event::listen(TransactionBeginning::class, function () use (&$pending, $change): void {
            if ($pending) {
                $pending = false;
                $change();
            }
        });
    }

    private function register(array $override = [])
    {
        return $this->post(route('delivery-driver.register.store'), array_merge([
            'full_name' => 'New Driver',
            'phone' => '0597000111',
            'email' => 'new-driver@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'terms' => '1',
            'governorate_id' => $this->governorate->id,
            'city_id' => $this->city->id,
            'vehicle_type' => 'Motorcycle',
        ], $override));
    }

    private function driver(string $status = 'active', string $approval = 'approved', bool $available = true): DeliveryDriver
    {
        $driver = DeliveryDriver::factory()->create(['status' => $status]);
        $driver->profile()->create([
            'approval_status' => $approval,
            'approved_at' => $approval === 'approved' ? now() : null,
            'approved_by' => $approval === 'pending' ? null : $this->admin->id,
            'rejection_reason' => $approval === 'rejected' ? 'Rejected for the test.' : null,
            'is_available' => $available,
        ]);

        return $driver;
    }

    private function assign(VendorOrder $order, DeliveryDriver $driver)
    {
        return $this->actingAs($this->admin, 'admin')->from(route('dashboard.vendor-orders.show', $order))
            ->patch(route('dashboard.vendor-orders.assign-driver', $order), ['delivery_driver_id' => $driver->id]);
    }

    private function vendorOrder(): VendorOrder
    {
        $order = Order::query()->create([
            'number' => 'HF-'.uniqid(),
            'customer_id' => Customer::factory()->create()->id,
            'status' => 'processing',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'subtotal' => 10,
            'delivery_total' => 0,
            'grand_total' => 10,
        ]);

        return VendorOrder::query()->create([
            'number' => 'VO-'.uniqid(),
            'order_id' => $order->id,
            'vendor_id' => Vendor::factory()->approved()->create()->id,
            'status' => 'ready_for_delivery',
            'subtotal' => 10,
            'delivery_fee' => 0,
            'commission_amount' => 0,
            'total' => 10,
            'ready_at' => now(),
        ]);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }
}
