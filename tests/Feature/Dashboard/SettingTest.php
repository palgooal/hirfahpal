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

    public function test_super_admin_can_view_and_update_settings(): void
    {
        Storage::fake('public');

        $admin = Admin::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '0599000000',
            'password' => 'password',
            'status' => 'active',
            'super_admin' => true,
        ]);

        Setting::create([
            'site_name' => 'Old title',
            'email' => 'old@example.com',
            'phone' => '0599111111',
            'address' => 'Old address',
            'timezone' => 'UTC',
            'default_locale' => 'en',
            'default_currency' => 'USD',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('dashboard.setting.index'))
            ->assertOk()
            ->assertSee('Old title');

        $response = $this->actingAs($admin, 'admin')
            ->put(route('dashboard.setting.update'), [
                'site_name' => 'New title',
                'site_description' => 'New description',
                'logo' => UploadedFile::fake()->image('logo.png'),
                'favicon' => UploadedFile::fake()->image('favicon.png'),
                'email' => 'new@example.com',
                'phone' => '0599222222',
                'address' => 'New address',
                'timezone' => 'Asia/Jerusalem',
                'default_locale' => 'ar',
                'default_currency' => 'ILS',
            ]);

        $response->assertRedirect();

        $setting = Setting::firstOrFail();
        $this->assertSame('New title', $setting->site_name);
        $this->assertSame('New description', $setting->site_description);
        $this->assertSame('new@example.com', $setting->email);
        $this->assertSame('Asia/Jerusalem', $setting->timezone);
        $this->assertSame('ar', $setting->default_locale);
        $this->assertSame('ILS', $setting->default_currency);
        Storage::disk('public')->assertExists($setting->logo);
        Storage::disk('public')->assertExists($setting->favicon);
    }

    public function test_guest_cannot_access_settings(): void
    {
        $this->get(route('dashboard.setting.index'))
            ->assertRedirect();
    }
}
