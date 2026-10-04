<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_vendor_from_dashboard(): void
    {
        $admin = Admin::factory()->create([
            'super_admin' => true,
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('dashboard.vendors.store'), [
                'name' => 'Hirfah Vendor',
                'email' => 'vendor@example.com',
                'phone' => '0561234567',
                'password' => 'password',
                'password_confirmation' => 'password',
                'status' => 'active',
                'store_name' => 'Hirfah Store',
                'approval_status' => 'approved',
            ])
            ->assertRedirect();

        $vendor = Vendor::where('phone', '0561234567')->firstOrFail();

        $this->assertSame('Hirfah Vendor', $vendor->name);
        $this->assertSame('active', $vendor->status);
        $this->assertNotNull($vendor->profile);
        $this->assertSame('Hirfah Store', $vendor->profile->store_name);
        $this->assertSame('approved', $vendor->profile->approval_status);
        $this->assertSame($admin->id, $vendor->profile->approved_by);
        $this->assertNotNull($vendor->profile->approved_at);
    }

    public function test_admin_without_vendor_create_ability_cannot_create_vendor(): void
    {
        $admin = Admin::factory()->create([
            'super_admin' => false,
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('dashboard.vendors.create'))
            ->assertForbidden();
    }
}
