<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\RoleUser;
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

    public function test_approval_does_not_change_the_account_status(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);

        foreach (['blocked', 'pending', 'active'] as $accountStatus) {
            $vendor = Vendor::factory()->withProfile('pending')->create(['status' => $accountStatus]);

            $this->actingAs($admin, 'admin')
                ->patch(route('dashboard.vendors.approve', $vendor))
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $vendor->refresh();
            $this->assertSame($accountStatus, $vendor->status, "approval changed a {$accountStatus} account");
            $this->assertSame('approved', $vendor->profile->approval_status);
        }
    }

    public function test_approval_of_a_blocked_vendor_keeps_it_out_of_the_storefront(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
        $vendor = Vendor::factory()->withProfile('pending')->create(['status' => 'blocked']);

        $this->actingAs($admin, 'admin')->patch(route('dashboard.vendors.approve', $vendor))->assertRedirect();

        $this->assertSame('blocked', $vendor->fresh()->status);
        $this->assertFalse(Vendor::query()->storefrontVisible()->whereKey($vendor->id)->exists());
    }

    public function test_approval_stores_the_decision_data(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
        $vendor = Vendor::factory()->withProfile('rejected', 'Missing documents.')->create(['status' => 'active']);

        $this->travelTo(now()->startOfSecond());
        $this->actingAs($admin, 'admin')
            ->patch(route('dashboard.vendors.approve', $vendor))
            ->assertRedirect()
            ->assertSessionHas('success');

        $profile = $vendor->fresh()->profile;
        $this->assertSame('approved', $profile->approval_status);
        $this->assertSame($admin->id, $profile->approved_by);
        $this->assertNotNull($profile->approved_at);
        $this->assertTrue($profile->approved_at->equalTo(now()));
        $this->assertNull($profile->rejection_reason);
    }

    public function test_admin_with_vendor_edit_ability_can_approve_and_without_it_cannot(): void
    {
        $vendor = Vendor::factory()->withProfile('pending')->create(['status' => 'blocked']);
        $plain = Admin::factory()->create(['super_admin' => false, 'status' => 'active']);

        $this->actingAs($plain, 'admin')->patch(route('dashboard.vendors.approve', $vendor))->assertForbidden();
        $this->assertSame('pending', $vendor->fresh()->profile->approval_status);

        $editor = Admin::factory()->create(['super_admin' => false, 'status' => 'active']);
        RoleUser::create(['role_name' => 'vendors.edit', 'user_id' => $editor->id, 'ability' => 'allow']);

        $this->actingAs($editor, 'admin')->patch(route('dashboard.vendors.approve', $vendor))->assertRedirect();
        $vendor->refresh();
        $this->assertSame('approved', $vendor->profile->approval_status);
        $this->assertSame($editor->id, $vendor->profile->approved_by);
        $this->assertSame('blocked', $vendor->status);
    }

    public function test_rejection_does_not_change_the_account_status(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);

        foreach (['blocked', 'pending', 'active'] as $accountStatus) {
            $vendor = Vendor::factory()->withProfile('pending')->create(['status' => $accountStatus]);

            $this->actingAs($admin, 'admin')
                ->patch(route('dashboard.vendors.reject', $vendor), ['rejection_reason' => 'Incomplete profile.'])
                ->assertRedirect();

            $vendor->refresh();
            $this->assertSame($accountStatus, $vendor->status);
            $this->assertSame('rejected', $vendor->profile->approval_status);
            $this->assertSame('Incomplete profile.', $vendor->profile->rejection_reason);
        }
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
