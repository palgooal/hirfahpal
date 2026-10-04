<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_vendor_order_from_dashboard(): void
    {
        $admin = Admin::factory()->create([
            'super_admin' => true,
            'status' => 'active',
        ]);

        $customer = Customer::factory()->create();
        $vendor = Vendor::factory()->create();
        $vendor->profile()->create([
            'store_name' => 'Manual Store',
            'slug' => 'manual-store',
            'approval_status' => 'approved',
        ]);

        $order = Order::create([
            'number' => 'ORD-TEST-1',
            'customer_id' => $customer->id,
            'status' => 'pending',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'subtotal' => 100,
            'delivery_total' => 10,
            'discount_total' => 0,
            'grand_total' => 110,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('dashboard.vendor-orders.store'), [
                'order_id' => $order->id,
                'vendor_id' => $vendor->id,
                'status' => 'pending',
                'subtotal' => '100.00',
                'delivery_fee' => '10.00',
                'commission_amount' => '5.00',
                'total' => '110.00',
                'vendor_response_due_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $vendorOrder = VendorOrder::where('order_id', $order->id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $this->assertStringStartsWith('VO-', $vendorOrder->number);
        $this->assertSame('pending', $vendorOrder->status);
        $this->assertSame('100.00', $vendorOrder->subtotal);
        $this->assertSame('10.00', $vendorOrder->delivery_fee);
        $this->assertSame('5.00', $vendorOrder->commission_amount);
        $this->assertSame('110.00', $vendorOrder->total);
        $this->assertNotNull($vendorOrder->vendor_response_due_at);
    }

    public function test_admin_without_vendor_order_create_ability_cannot_create_vendor_order(): void
    {
        $admin = Admin::factory()->create([
            'super_admin' => false,
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('dashboard.vendor-orders.create'))
            ->assertForbidden();
    }
}
