<?php

namespace Tests\Feature;

use App\Models\Admin;
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
