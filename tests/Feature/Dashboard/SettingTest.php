<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_update_settings(): void
    {
        Storage::fake('public');

        $admin = Admin::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '0599000000',
            'password' => 'password',
            'status' => 'active',
        ]);

        Setting::create([
            'title' => 'Old title',
            'email' => 'old@example.com',
            'phone' => '0599111111',
            'address' => 'Old address',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('dashboard.setting.index'))
            ->assertOk()
            ->assertSee('Old title');

        $response = $this->actingAs($admin, 'admin')
            ->put(route('dashboard.setting.update'), [
                'title' => 'New title',
                'description' => 'New description',
                'logo' => UploadedFile::fake()->create('logo.png', 10, 'image/png'),
                'email' => 'new@example.com',
                'phone' => '0599222222',
                'address' => 'New address',
            ]);

        $response->assertRedirect(route('dashboard.setting.index'));

        $setting = Setting::firstOrFail();
        $this->assertSame('New title', $setting->title);
        $this->assertSame('new@example.com', $setting->email);
        Storage::disk('public')->assertExists($setting->logo);
    }

    public function test_guest_cannot_access_settings(): void
    {
        $this->get(route('dashboard.setting.index'))
            ->assertRedirect();
    }
}
