<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\City;
use App\Models\Customer;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * DRIVER-E1 (SRS §2.3): the delivery driver's JSON responses (assignments index / show, overview and every assignment
 * action) carry only what the delivery needs, at every nesting level: never the platform commission, the store's or
 * the driver's own approval records, the customer's email, the payment gateway data, prices or totals.
 */
class DeliveryDriverDataExposureTest extends TestCase
{
    use RefreshDatabase;

    /** Keys that must not appear anywhere in an assignment payload. */
    private const FORBIDDEN_KEYS = [
        'commission_amount', 'commission_rate', 'approval_status', 'approved_at', 'approved_by', 'rejection_reason',
        'vendor_response_due_at', 'assigned_by', 'provider', 'provider_reference', 'payload', 'refunded_amount', 'amount',
        'password', 'remember_token', 'email', 'email_verified_at', 'phone_verified_at', 'last_login_at', 'product_sku',
        'subtotal', 'delivery_fee', 'total', 'delivery_total', 'discount_total', 'grand_total', 'unit_price', 'line_total',
    ];

    private const CUSTOMER_EMAIL = 'buyer-private@example.com';

    private const VENDOR_EMAIL = 'store-owner-private@example.com';

    private const SECRETS = [self::CUSTOMER_EMAIL, self::VENDOR_EMAIL, 'GATEWAY-REF-123', 'gateway-secret', 'SKU-PRIVATE', 'Old store rejection', 'Old order rejection'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_assignments_index_carries_no_forbidden_data(): void
    {
        $driver = $this->approvedDriver();
        $this->assignmentFor($driver);

        $payload = $this->actingAs($driver, 'delivery_driver')->getJson(route('delivery-driver.dashboard.assignments.index'))->assertOk()->json();

        $this->assertSame(1, $payload['total'], 'The pagination shape is kept.');
        $this->assertClean($payload['data'], 'index');
        $this->assertDeliveryData($payload['data'][0], 'index');
    }

    public function test_the_assignment_detail_carries_no_forbidden_data(): void
    {
        $driver = $this->approvedDriver();
        $assignment = $this->assignmentFor($driver);

        $payload = $this->actingAs($driver, 'delivery_driver')->getJson(route('delivery-driver.dashboard.assignments.show', $assignment))->assertOk()->json();

        $this->assertClean($payload, 'show');
        $this->assertDeliveryData($payload, 'show');
        $this->assertEqualsCanonicalizing(['id', 'vendor_order_id', 'product_id', 'product_name', 'quantity', 'product'], array_keys($payload['vendor_order']['items'][0]));
    }

    public function test_the_overview_carries_no_forbidden_data_nor_the_drivers_own_approval_records(): void
    {
        $driver = $this->approvedDriver(['vehicle_type' => 'Motorbike', 'rejection_reason' => 'Old driver rejection']);
        $this->assignmentFor($driver);

        $payload = $this->actingAs($driver, 'delivery_driver')->getJson(route('delivery-driver.dashboard.overview'))->assertOk()->json();

        $this->assertClean($payload['recent_assignments'], 'overview assignments');
        $this->assertDeliveryData($payload['recent_assignments'][0], 'overview');
        $this->assertSame(1, $payload['stats']['assigned']);

        // The driver's own account: their own email is fine, their approval records are not.
        $profile = $payload['profile'];
        $this->assertSame([$driver->id, $driver->email, 'Motorbike', true], [$profile['id'], $profile['email'], $profile['profile']['vehicle_type'], $profile['profile']['is_available']]);
        foreach (['approval_status', 'approved_at', 'approved_by', 'rejection_reason', 'password', 'remember_token'] as $key) {
            $this->assertNotContains($key, $this->keys($profile), "overview profile: {$key}");
        }
        $this->assertStringNotContainsString('Old driver rejection', json_encode($payload));
    }

    public function test_every_assignment_action_response_carries_no_forbidden_data(): void
    {
        $driver = $this->approvedDriver();

        // assigned → accepted → picked_up → delivered
        $assignment = $this->assignmentFor($driver);
        foreach (['accept' => 'accepted', 'pick-up' => 'picked_up', 'delivered' => 'delivered'] as $action => $status) {
            $payload = $this->act($driver, $action, $assignment)->assertOk()->assertJsonPath('assignment.status', $status)->json();
            $this->assertClean($payload, $action);
            $this->assertSame('cod', $payload['assignment']['vendor_order']['order']['payment']['method'], $action);
        }

        // picked_up → failed → returned to the vendor
        $failed = $this->assignmentFor($driver, 'picked_up', 'out_for_delivery');
        $this->assertClean($this->act($driver, 'failed', $failed, ['failure_code' => 'customer_absent', 'failure_reason' => 'Customer unavailable.'])->assertOk()->json(), 'failed');
        $this->assertClean($this->act($driver, 'return-to-vendor', $failed)->assertOk()->json(), 'return-to-vendor');
    }

    public function test_the_lifecycle_is_unchanged(): void
    {
        $driver = $this->approvedDriver();
        $assignment = $this->assignmentFor($driver);

        $this->act($driver, 'accept', $assignment)->assertOk();
        $this->act($driver, 'pick-up', $assignment)->assertOk()->assertJsonPath('assignment.vendor_order.status', 'out_for_delivery');
        $this->act($driver, 'delivered', $assignment)->assertOk()->assertJsonPath('assignment.vendor_order.status', 'delivered');

        $assignment->refresh();
        $this->assertSame('delivered', $assignment->status);
        $this->assertNotNull($assignment->picked_up_at);
        $this->assertNotNull($assignment->delivered_at);
        $this->assertSame('delivered', $assignment->vendorOrder->status);
        $this->assertNull($assignment->vendorOrder->completed_at, 'Delivered is not completed.');
        $this->assertSame('pending', Payment::query()->where('order_id', $assignment->vendorOrder->order_id)->value('status'));

        // A failure after pickup keeps the order out for delivery until the return.
        $failed = $this->assignmentFor($driver, 'picked_up', 'out_for_delivery');
        $this->act($driver, 'failed', $failed, ['failure_code' => 'customer_absent', 'failure_reason' => 'Customer unavailable.'])->assertOk()->assertJsonPath('assignment.failure_reason', 'Customer unavailable.');
        $this->assertSame(['failed', 'out_for_delivery'], [$failed->fresh()->status, $failed->vendorOrder->fresh()->status]);
        // E2-MVP custody fix: the driver declares the return; the admin confirmation alone closes the custody.
        $this->act($driver, 'return-to-vendor', $failed)->assertOk();
        $this->assertSame(['failed', 'out_for_delivery'], [$failed->fresh()->status, $failed->vendorOrder->fresh()->status]);
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
        $this->actingAs($admin, 'admin')->patch(route('dashboard.vendor-orders.confirm-return', $failed->vendorOrder))->assertRedirect();
        $this->assertSame(['cancelled', 'ready_for_delivery'], [$failed->fresh()->status, $failed->vendorOrder->fresh()->status]);
    }

    public function test_a_driver_cannot_see_or_act_on_another_drivers_assignment(): void
    {
        $driver = $this->approvedDriver();
        $other = $this->approvedDriver();
        $own = $this->assignmentFor($driver);
        $theirs = $this->assignmentFor($other);

        $this->actingAs($driver, 'delivery_driver')->getJson(route('delivery-driver.dashboard.assignments.index'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->actingAs($driver, 'delivery_driver')->getJson(route('delivery-driver.dashboard.overview'))
            ->assertOk()->assertJsonCount(1, 'recent_assignments')->assertJsonPath('recent_assignments.0.id', $own->id);
        $this->actingAs($driver, 'delivery_driver')->getJson(route('delivery-driver.dashboard.assignments.show', $theirs))->assertNotFound();

        foreach (['accept', 'pick-up', 'delivered', 'failed', 'return-to-vendor'] as $action) {
            $this->act($driver, $action, $theirs, ['failure_reason' => 'x'])->assertNotFound();
        }
        $this->assertSame(['assigned', 'assigned'], [$theirs->fresh()->status, $theirs->vendorOrder->fresh()->status]);
    }

    public function test_the_profile_show_carries_no_approval_records_and_keeps_the_profile_fields(): void
    {
        [$governorate, $city] = $this->place();
        $driver = $this->approvedDriver([
            'vehicle_type' => 'Motorbike', 'vehicle_plate' => 'PL-123', 'governorate_id' => $governorate->id, 'city_id' => $city->id,
            'approved_by' => null, 'rejection_reason' => 'Old driver rejection',
        ]);

        $payload = $this->actingAs($driver, 'delivery_driver')->getJson(route('delivery-driver.dashboard.profile.show'))->assertOk()->json();

        $this->assertSame(['delivery_driver'], array_keys($payload), 'The response envelope is kept.');
        $this->assertCleanProfile($payload, 'profile.show');
        $profile = $payload['delivery_driver'];
        $this->assertSame([$driver->id, $driver->name, $driver->email, $driver->phone, 'active'], [$profile['id'], $profile['name'], $profile['email'], $profile['phone'], $profile['status']]);
        $this->assertSame(['Motorbike', 'PL-123', true, $governorate->id, $city->id], [
            $profile['profile']['vehicle_type'], $profile['profile']['vehicle_plate'], $profile['profile']['is_available'],
            $profile['profile']['governorate_id'], $profile['profile']['city_id'],
        ]);
        $this->assertSame(['id' => $governorate->id, 'name' => 'Gaza Governorate'], $profile['profile']['governorate']);
        $this->assertSame(['id' => $city->id, 'name' => 'Gaza'], $profile['profile']['city']);
    }

    public function test_the_profile_update_saves_and_its_response_carries_no_approval_records(): void
    {
        [$governorate, $city] = $this->place();
        $driver = $this->approvedDriver(['rejection_reason' => 'Old driver rejection']);

        $payload = $this->actingAs($driver, 'delivery_driver')->putJson(route('delivery-driver.dashboard.profile.update'), [
            'name' => 'Updated Driver', 'phone' => '0599000777', 'vehicle_type' => 'Car', 'vehicle_plate' => 'NEW-1',
            'governorate_id' => $governorate->id, 'city_id' => $city->id, 'is_available' => false,
        ])->assertOk()->json();

        $this->assertSame(['message', 'delivery_driver'], array_keys($payload), 'The response envelope is kept.');
        $this->assertCleanProfile($payload, 'profile.update');
        $this->assertSame(['Updated Driver', '0599000777', 'Car', 'NEW-1', false, 'Gaza'], [
            $payload['delivery_driver']['name'], $payload['delivery_driver']['phone'], $payload['delivery_driver']['profile']['vehicle_type'],
            $payload['delivery_driver']['profile']['vehicle_plate'], $payload['delivery_driver']['profile']['is_available'],
            $payload['delivery_driver']['profile']['city']['name'],
        ]);

        // Saved as before; the approval records are untouched.
        $driver->refresh();
        $this->assertSame(['Updated Driver', '0599000777'], [$driver->name, $driver->phone]);
        $this->assertSame(['Car', 'NEW-1', false, $governorate->id, $city->id, 'approved', 'Old driver rejection'], [
            $driver->profile->vehicle_type, $driver->profile->vehicle_plate, (bool) $driver->profile->is_available,
            $driver->profile->governorate_id, $driver->profile->city_id, $driver->profile->approval_status, $driver->profile->rejection_reason,
        ]);

        // Validation is unchanged: approval fields sent by the driver are ignored, a bad city is refused.
        $this->actingAs($driver, 'delivery_driver')->putJson(route('delivery-driver.dashboard.profile.update'), ['approval_status' => 'pending', 'rejection_reason' => 'x'])->assertOk();
        $this->assertSame(['approved', 'Old driver rejection'], [$driver->profile()->value('approval_status'), $driver->profile()->value('rejection_reason')]);
        $this->actingAs($driver, 'delivery_driver')->putJson(route('delivery-driver.dashboard.profile.update'), ['city_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('city_id');
    }

    public function test_the_browser_dashboard_still_shows_the_delivery(): void
    {
        $driver = $this->approvedDriver();
        $assignment = $this->assignmentFor($driver);

        $this->actingAs($driver, 'delivery_driver')->get(route('delivery-driver.dashboard'))->assertOk()
            ->assertSee($assignment->vendorOrder->number)
            ->assertSee('Delivery Store')
            ->assertSee('Lamp')
            ->assertDontSee(self::CUSTOMER_EMAIL)
            ->assertDontSee('GATEWAY-REF-123');
    }

    /** No forbidden key at any level, and none of the private values anywhere. */
    private function assertClean(array $payload, string $context): void
    {
        $keys = $this->keys($payload);
        foreach (self::FORBIDDEN_KEYS as $key) {
            $this->assertNotContains($key, $keys, "{$context}: {$key}");
        }

        $json = json_encode($payload);
        foreach (self::SECRETS as $secret) {
            $this->assertStringNotContainsString($secret, $json, "{$context}: {$secret}");
        }
    }

    /** The driver's own profile: their own email is allowed; approval records, secrets and verification data are not. */
    private function assertCleanProfile(array $payload, string $context): void
    {
        $keys = $this->keys($payload);
        foreach (['approval_status', 'approved_at', 'approved_by', 'rejection_reason', 'password', 'remember_token',
            'email_verified_at', 'phone_verified_at', 'last_login_at'] as $key) {
            $this->assertNotContains($key, $keys, "{$context}: {$key}");
        }
        $this->assertStringNotContainsString('Old driver rejection', json_encode($payload), $context);
    }

    /** @return array{0: Governorate, 1: City} */
    private function place(): array
    {
        $governorate = Governorate::query()->create(['name' => 'Gaza Governorate', 'slug' => 'gaza-governorate']);

        return [$governorate, City::query()->create(['governorate_id' => $governorate->id, 'name' => 'Gaza', 'slug' => 'gaza'])];
    }

    /** What the delivery needs is still there. */
    private function assertDeliveryData(array $assignment, string $context): void
    {
        $vendorOrder = $assignment['vendor_order'];
        $order = $vendorOrder['order'];

        $this->assertSame('assigned', $assignment['status'], $context);
        $this->assertSame('assigned', $vendorOrder['status'], $context);
        $this->assertSame(['name' => 'Recipient Name', 'phone' => '0599111222', 'line' => 'Main Street 4', 'city' => 'Gaza'], [
            'name' => $order['delivery_address']['recipient_name'],
            'phone' => $order['delivery_address']['phone'],
            'line' => $order['delivery_address']['address_line'],
            'city' => $order['delivery_address']['city_name'],
        ], $context);
        $this->assertSame($order['delivery_address']['phone'], $order['address']['phone'], "{$context}: the deprecated key is kept.");
        $this->assertSame(['cod', 'pending'], [$order['payment_method'], $order['payment_status']], $context);
        // The overview never loaded the customer; where it is loaded, only the id and name.
        if (array_key_exists('customer', $order)) {
            $this->assertSame(['id', 'name'], array_keys($order['customer']), $context);
        }
        $this->assertSame('Delivery Store', $vendorOrder['vendor']['profile']['store_name'], $context);
        $this->assertSame('Vendor Street 1', $vendorOrder['vendor']['profile']['address_line'], $context);
        // The overview never loaded the items; where they are loaded, the name and quantity are there.
        if (array_key_exists('items', $vendorOrder)) {
            $this->assertSame(['Lamp', 2], [$vendorOrder['items'][0]['product_name'], $vendorOrder['items'][0]['quantity']], $context);
        }
    }

    /** @return list<string> every key at every level */
    private function keys(array $payload): array
    {
        $keys = [];
        $walk = function (array $data) use (&$walk, &$keys): void {
            foreach ($data as $key => $value) {
                if (is_string($key)) {
                    $keys[] = $key;
                }
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($payload);

        return array_values(array_unique($keys));
    }

    private function act(DeliveryDriver $driver, string $action, DeliveryAssignment $assignment, array $data = []): TestResponse
    {
        return $this->actingAs($driver, 'delivery_driver')
            // E2-MVP batch 05: a cash-on-delivery delivery confirms the full amount collected.
            ->patchJson(route("delivery-driver.dashboard.assignments.{$action}", $assignment), match ($action) {
                'failed' => $data,
                'delivered' => ['collected_amount' => (string) $assignment->vendorOrder->total],
                default => [],
            });
    }

    private function approvedDriver(array $profile = []): DeliveryDriver
    {
        $driver = DeliveryDriver::factory()->create();
        $driver->profile()->create(['approval_status' => 'approved', 'approved_at' => now(), 'is_available' => true] + $profile);

        return $driver;
    }

    /** A COD order with a checkout address snapshot, a gateway-like payment row and moderation data on the store. */
    private function assignmentFor(DeliveryDriver $driver, string $assignmentStatus = 'assigned', string $vendorOrderStatus = 'assigned'): DeliveryAssignment
    {
        $customer = Customer::factory()->create(['email' => uniqid().self::CUSTOMER_EMAIL]);
        $vendor = Vendor::factory()->approved()->create(['email' => uniqid().self::VENDOR_EMAIL]);
        $vendor->profile->update(['store_name' => 'Delivery Store', 'address_line' => 'Vendor Street 1', 'commission_rate' => 12.5, 'rejection_reason' => 'Old store rejection']);
        $order = Order::query()->create([
            'number' => 'HF-'.uniqid(), 'customer_id' => $customer->id, 'status' => 'processing',
            'payment_method' => 'cod', 'payment_status' => 'pending', 'subtotal' => 40, 'delivery_total' => 10, 'grand_total' => 50,
            'delivery_address_source' => 'checkout', 'delivery_recipient_name' => 'Recipient Name', 'delivery_phone' => '0599111222',
            'delivery_city_name' => 'Gaza', 'delivery_address_line' => 'Main Street 4',
        ]);
        Payment::query()->create([
            'order_id' => $order->id, 'method' => 'cod', 'status' => 'pending', 'amount' => 50, 'refunded_amount' => 0,
            'provider' => 'gateway', 'provider_reference' => 'GATEWAY-REF-123', 'payload' => ['token' => 'gateway-secret'],
        ]);
        $vendorOrder = VendorOrder::query()->create([
            'number' => 'VO-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => $vendor->id, 'delivery_driver_id' => $driver->id,
            'status' => $vendorOrderStatus, 'subtotal' => 40, 'delivery_fee' => 10, 'commission_amount' => 5, 'total' => 50,
            'rejection_reason' => 'Old order rejection', 'vendor_response_due_at' => now(),
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id, 'vendor_order_id' => $vendorOrder->id, 'product_name' => 'Lamp', 'product_sku' => 'SKU-PRIVATE',
            'quantity' => 2, 'unit_price' => 20, 'line_total' => 40,
        ]);

        return DeliveryAssignment::query()->create([
            'vendor_order_id' => $vendorOrder->id, 'delivery_driver_id' => $driver->id, 'status' => $assignmentStatus,
            'assigned_at' => now(), 'assigned_by' => null, 'notes' => 'Call before arriving.',
            'picked_up_at' => $assignmentStatus === 'picked_up' ? now() : null,
        ]);
    }
}
