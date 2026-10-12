<?php

namespace Tests\Feature;

use App\Models\Admin;
use Database\Seeders\AdminHeaderTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_admin_dashboard(): void
    {
        $admin = Admin::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '0599000001',
            'password' => 'password',
            'status' => 'active',
        ]);

        // The Arabic heading comes from its seeded translation (the code default is English since AD-UI-DASH-04).
        $this->seed(AdminHeaderTranslationSeeder::class);

        $this->actingAs($admin, 'admin')
            ->get(route('dashboard.home'))
            ->assertOk()
            ->assertSee('لوحة الإدارة');
    }

    public function test_non_admin_cannot_view_admin_dashboard(): void
    {
        $this->get(route('dashboard.home'))
            ->assertRedirect(route('admin.login'));
    }
}
