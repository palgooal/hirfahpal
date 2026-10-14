<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
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

    public function test_driver_cannot_change_another_drivers_assignment(): void
    {
        $driver = $this->approvedDriver();
        $assignment = $this->assignmentFor($this->approvedDriver());

        $this->actingAs($driver, 'delivery_driver')
            ->patchJson(route('delivery-driver.dashboard.assignments.accept', $assignment))
            ->assertNotFound();
    }

    private function approvedDriver(): DeliveryDriver
    {
        $driver = DeliveryDriver::factory()->create();
        $driver->profile()->create(['approval_status' => 'approved', 'approved_at' => now(), 'is_available' => true]);

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
}
