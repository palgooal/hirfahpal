<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryDriverDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_unapproved_driver_cannot_access_delivery_operations(): void
    {
        $driver = DeliveryDriver::factory()->create();
        $driver->profile()->create(['approval_status' => 'pending']);

        $this->actingAs($driver, 'delivery_driver')
            ->getJson(route('delivery-driver.dashboard.overview'))
            ->assertForbidden()
            ->assertJsonPath('approval_status', 'pending');
    }

    public function test_approved_driver_can_view_overview_and_only_their_assignments(): void
    {
        $driver = $this->approvedDriver();
        $otherDriver = $this->approvedDriver();
        $assignment = $this->assignmentFor($driver);
        $this->assignmentFor($otherDriver);

        $this->actingAs($driver, 'delivery_driver')
            ->getJson(route('delivery-driver.dashboard.overview'))
            ->assertOk()
            ->assertJsonPath('stats.assigned', 1)
            ->assertJsonPath('recent_assignments.0.id', $assignment->id);

        $this->actingAs($driver, 'delivery_driver')
            ->getJson(route('delivery-driver.dashboard.assignments.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assignment->id);
    }

    public function test_approved_driver_can_manage_an_assigned_delivery_from_the_browser_dashboard(): void
    {
        $driver = $this->approvedDriver();
        $assignment = $this->assignmentFor($driver);

        $this->actingAs($driver, 'delivery_driver')
            ->get(route('delivery-driver.dashboard'))
            ->assertOk()
            ->assertSee($assignment->vendorOrder->number)
            ->assertSee('قبول المهمة');

        $this->actingAs($driver, 'delivery_driver')
            ->from(route('delivery-driver.dashboard'))
            ->patch(route('delivery-driver.dashboard.assignments.accept', $assignment))
            ->assertRedirect(route('delivery-driver.dashboard'));

        $this->assertSame('accepted', $assignment->fresh()->status);
    }

    public function test_driver_can_update_own_profile_and_availability(): void
    {
        $driver = $this->approvedDriver();

        $this->actingAs($driver, 'delivery_driver')
            ->putJson(route('delivery-driver.dashboard.profile.update'), [
                'name' => 'Updated Driver',
                'vehicle_type' => 'Motorcycle',
                'vehicle_plate' => 'ABC-123',
                'is_available' => false,
            ])
            ->assertOk()
            ->assertJsonPath('delivery_driver.name', 'Updated Driver')
            ->assertJsonPath('delivery_driver.profile.is_available', false);

        $this->assertDatabaseHas('delivery_driver_profiles', [
            'delivery_driver_id' => $driver->id,
            'vehicle_type' => 'Motorcycle',
            'is_available' => false,
        ]);
    }

    public function test_driver_can_report_an_active_delivery_as_failed_with_an_audit_entry(): void
    {
        $driver = $this->approvedDriver();
        $assignment = $this->assignmentFor($driver, 'picked_up', 'out_for_delivery');

        $this->actingAs($driver, 'delivery_driver')
            ->patchJson(route('delivery-driver.dashboard.assignments.failed', $assignment), [
                'failure_reason' => 'Customer was unavailable.',
            ])
            ->assertOk()
            ->assertJsonPath('assignment.status', 'failed');

        $this->assertDatabaseHas('delivery_assignments', [
            'id' => $assignment->id,
            'status' => 'failed',
            'failure_reason' => 'Customer was unavailable.',
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'vendor_order_id' => $assignment->vendor_order_id,
            'status' => 'delivery_failed',
            'actor_type' => 'delivery_driver',
            'actor_id' => $driver->id,
        ]);
        $this->assertSame('out_for_delivery', $assignment->vendorOrder->fresh()->status);
        $this->assertSame(1, OrderStatusHistory::count());
    }

    public function test_failed_delivery_after_pickup_must_be_returned_before_reassignment(): void
    {
        $driver = $this->approvedDriver();
        $assignment = $this->assignmentFor($driver, 'picked_up', 'out_for_delivery');
        $assignment->update(['picked_up_at' => now()]);

        $this->actingAs($driver, 'delivery_driver')
            ->patchJson(route('delivery-driver.dashboard.assignments.failed', $assignment), ['failure_reason' => 'Customer unavailable.'])
            ->assertOk();

        $this->assertSame('out_for_delivery', $assignment->vendorOrder->fresh()->status);

        $this->actingAs($driver, 'delivery_driver')
            ->patchJson(route('delivery-driver.dashboard.assignments.return-to-vendor', $assignment))
            ->assertOk()
            ->assertJsonPath('assignment.status', 'cancelled');

        $this->assertNotNull($assignment->fresh()->returned_at);
        $this->assertSame(['ready_for_delivery', null], [$assignment->vendorOrder->fresh()->status, $assignment->vendorOrder->fresh()->delivery_driver_id]);
        $this->assertDatabaseHas('order_status_histories', ['vendor_order_id' => $assignment->vendor_order_id, 'status' => 'returned_to_vendor']);
    }

    public function test_driver_cannot_change_another_drivers_assignment(): void
    {
        $driver = $this->approvedDriver();
        $assignment = $this->assignmentFor($this->approvedDriver());

        $this->actingAs($driver, 'delivery_driver')
            ->patchJson(route('delivery-driver.dashboard.assignments.accept', $assignment))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------- the profile location (H1-E, Governorate → City)

    public function test_profile_location_omitted_or_cleared(): void
    {
        [$gaza, $gazaCity] = $this->locations();
        $driver = $this->approvedDriver(['governorate_id' => $gaza->id, 'city_id' => $gazaCity->id, 'vehicle_type' => 'Van', 'vehicle_plate' => 'P-1']);

        $this->updateProfile($driver, ['name' => 'Renamed'])->assertOk()
            ->assertJsonPath('delivery_driver.profile.governorate_id', $gaza->id)
            ->assertJsonPath('delivery_driver.profile.city_id', $gazaCity->id);
        $this->assertLocation($driver, $gaza->id, $gazaCity->id, 'Both omitted: kept.');

        $this->updateProfile($driver, ['governorate_id' => null, 'city_id' => null])->assertOk()
            ->assertJsonPath('delivery_driver.profile.governorate_id', null)
            ->assertJsonPath('delivery_driver.profile.city_id', null);
        $this->assertLocation($driver, null, null, 'Both null: cleared.');

        $profile = $driver->profile()->first();
        $this->assertSame(['Renamed', 'Van', 'P-1', true], [$driver->fresh()->name, $profile->vehicle_type, $profile->vehicle_plate, $profile->is_available], 'Nothing else changed.');
    }

    public function test_profile_location_changing_one_field_checks_the_stored_other(): void
    {
        [$gaza, $gazaCity, $rafah, $nablus, $nablusCity] = $this->locations();
        $driver = $this->approvedDriver(['governorate_id' => $gaza->id, 'city_id' => $gazaCity->id]);

        // The governorate alone, against the stored city: refused, nothing saved.
        $this->updateProfile($driver, ['governorate_id' => $nablus->id])->assertUnprocessable()
            ->assertJsonValidationErrors(['city_id' => 'The selected city does not belong to the selected governorate.'])
            ->assertJsonMissingValidationErrors('governorate_id');
        $this->assertLocation($driver, $gaza->id, $gazaCity->id);

        // The city alone, against the stored governorate.
        $this->updateProfile($driver, ['city_id' => $nablusCity->id])->assertUnprocessable()->assertJsonValidationErrors('city_id');
        $this->assertLocation($driver, $gaza->id, $gazaCity->id);
        $this->updateProfile($driver, ['city_id' => $rafah->id])->assertOk();
        $this->assertLocation($driver, $gaza->id, $rafah->id);

        // The city cleared, the governorate kept; then, with no stored city, the governorate can change alone.
        $this->updateProfile($driver, ['city_id' => null])->assertOk();
        $this->assertLocation($driver, $gaza->id, null);
        $this->updateProfile($driver, ['governorate_id' => $nablus->id])->assertOk();
        $this->assertLocation($driver, $nablus->id, null);
    }

    public function test_profile_location_governorate_cannot_be_cleared_under_a_city(): void
    {
        [$gaza, $gazaCity] = $this->locations();
        $driver = $this->approvedDriver(['governorate_id' => $gaza->id, 'city_id' => $gazaCity->id]);

        $this->updateProfile($driver, ['governorate_id' => null])->assertUnprocessable()
            ->assertJsonValidationErrors('governorate_id')->assertJsonMissingValidationErrors('city_id');
        $this->updateProfile($driver, ['governorate_id' => null, 'city_id' => $gazaCity->id])->assertUnprocessable()->assertJsonValidationErrors('governorate_id');
        $this->assertLocation($driver, $gaza->id, $gazaCity->id);
    }

    public function test_profile_location_legacy_and_inactive_stored_pairs_survive_unrelated_updates(): void
    {
        [, $gazaCity, , $nablus] = $this->locations();
        $closedGovernorate = Governorate::query()->create(['name' => 'Closed Governorate', 'slug' => 'closed-governorate', 'status' => 'inactive']);
        $closedCity = City::query()->create(['governorate_id' => $closedGovernorate->id, 'name' => 'Closed', 'slug' => 'closed', 'status' => 'inactive']);
        $orphan = City::query()->create(['governorate_id' => null, 'name' => 'Orphan', 'slug' => 'orphan']);

        foreach ([
            'mismatched' => [$nablus->id, $gazaCity->id],
            'inactive' => [$closedGovernorate->id, $closedCity->id],
            'city without governorate' => [null, $orphan->id],
        ] as $label => [$governorateId, $cityId]) {
            $driver = $this->approvedDriver(['governorate_id' => $governorateId, 'city_id' => $cityId]);

            $this->updateProfile($driver, ['vehicle_type' => 'Bike', 'is_available' => false])->assertOk();
            $this->updateProfile($driver, ['vehicle_plate' => 'X-9', 'governorate_id' => $governorateId, 'city_id' => $cityId])->assertOk();

            $this->assertLocation($driver, $governorateId, $cityId, $label);
            $profile = $driver->profile()->first();
            $this->assertSame(['Bike', 'X-9', false], [$profile->vehicle_type, $profile->vehicle_plate, $profile->is_available], $label);
        }
    }

    public function test_profile_location_newly_selected_values_must_be_active_and_match(): void
    {
        [$gaza, $gazaCity, , $nablus] = $this->locations();
        $closedGovernorate = Governorate::query()->create(['name' => 'Closed Governorate', 'slug' => 'closed-governorate', 'status' => 'inactive']);
        $closedCity = City::query()->create(['governorate_id' => $gaza->id, 'name' => 'Closed', 'slug' => 'closed', 'status' => 'inactive']);
        $driver = $this->approvedDriver();

        $this->updateProfile($driver, ['governorate_id' => $closedGovernorate->id])->assertUnprocessable()->assertJsonValidationErrors('governorate_id');
        $this->updateProfile($driver, ['governorate_id' => $gaza->id, 'city_id' => $closedCity->id])->assertUnprocessable()
            ->assertJsonValidationErrors('city_id')->assertJsonMissingValidationErrors('governorate_id');
        $this->updateProfile($driver, ['governorate_id' => $nablus->id, 'city_id' => $gazaCity->id])->assertUnprocessable()
            ->assertJsonValidationErrors(['city_id' => 'The selected city does not belong to the selected governorate.']);
        $this->updateProfile($driver, ['city_id' => $gazaCity->id])->assertUnprocessable()->assertJsonValidationErrors('governorate_id');
        $this->updateProfile($driver, ['governorate_id' => 999, 'city_id' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors(['governorate_id', 'city_id']);
        $this->assertLocation($driver, null, null, 'Nothing refused is saved.');

        $this->updateProfile($driver, ['governorate_id' => $gaza->id, 'city_id' => $gazaCity->id])->assertOk()
            ->assertJsonPath('message', 'Delivery driver profile updated successfully.')
            ->assertJsonPath('delivery_driver.profile.city.name', 'Gaza');
        $this->assertLocation($driver, $gaza->id, $gazaCity->id);
    }

    public function test_profile_location_uses_the_signed_in_drivers_own_stored_pair(): void
    {
        [$gaza, $gazaCity, , $nablus, $nablusCity] = $this->locations();
        $driver = $this->approvedDriver(['governorate_id' => $gaza->id, 'city_id' => $gazaCity->id]);
        $other = $this->approvedDriver(['governorate_id' => $nablus->id, 'city_id' => $nablusCity->id]);

        // Nablus fits the other driver's stored city, not this driver's.
        $this->updateProfile($driver, ['governorate_id' => $nablus->id])->assertUnprocessable()->assertJsonValidationErrors('city_id');
        $this->updateProfile($other, ['name' => 'Other'])->assertOk();

        $this->assertLocation($driver, $gaza->id, $gazaCity->id);
        $this->assertLocation($other, $nablus->id, $nablusCity->id);
    }

    private function approvedDriver(array $profile = []): DeliveryDriver
    {
        $driver = DeliveryDriver::factory()->create();
        $driver->profile()->create(['approval_status' => 'approved', 'approved_at' => now(), 'is_available' => true] + $profile);

        return $driver;
    }

    private function assignmentFor(DeliveryDriver $driver, string $assignmentStatus = 'assigned', string $vendorOrderStatus = 'assigned'): DeliveryAssignment
    {
        $customer = Customer::factory()->create();
        $vendor = Vendor::factory()->approved()->create();
        $order = Order::query()->create([
            'number' => 'HF-'.uniqid(),
            'customer_id' => $customer->id,
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
            'vendor_id' => $vendor->id,
            'delivery_driver_id' => $driver->id,
            'status' => $vendorOrderStatus,
            'subtotal' => 25,
            'delivery_fee' => 0,
            'commission_amount' => 0,
            'total' => 25,
        ]);

        return DeliveryAssignment::query()->create([
            'vendor_order_id' => $vendorOrder->id,
            'delivery_driver_id' => $driver->id,
            'status' => $assignmentStatus,
            'assigned_at' => now(),
        ]);
    }

    private function updateProfile(DeliveryDriver $driver, array $payload)
    {
        return $this->actingAs($driver, 'delivery_driver')->putJson(route('delivery-driver.dashboard.profile.update'), $payload);
    }

    private function assertLocation(DeliveryDriver $driver, ?int $governorateId, ?int $cityId, string $message = ''): void
    {
        $profile = $driver->profile()->first();

        $this->assertSame([$governorateId, $cityId], [$profile->governorate_id, $profile->city_id], $message);
    }

    /**
     * Governorate → City fixtures: Gaza Governorate with Gaza and Rafah, Nablus Governorate with Nablus.
     *
     * @return array{0: Governorate, 1: City, 2: City, 3: Governorate, 4: City}
     */
    private function locations(): array
    {
        $gaza = Governorate::query()->create(['name' => 'Gaza Governorate', 'slug' => 'gaza-governorate']);
        $nablus = Governorate::query()->create(['name' => 'Nablus Governorate', 'slug' => 'nablus-governorate']);

        return [
            $gaza,
            City::query()->create(['governorate_id' => $gaza->id, 'name' => 'Gaza', 'slug' => 'gaza']),
            City::query()->create(['governorate_id' => $gaza->id, 'name' => 'Rafah', 'slug' => 'rafah']),
            $nablus,
            City::query()->create(['governorate_id' => $nablus->id, 'name' => 'Nablus', 'slug' => 'nablus']),
        ];
    }
}
