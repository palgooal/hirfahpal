<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\RoleUser;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_with_settings_view_can_open_settings(): void
    {
        $admin = $this->adminWithAbilities(['settings.view']);

        $this->actingAs($admin, 'admin')->get(route('dashboard.setting.index'))
            ->assertOk()->assertSee('Settings');

        $this->assertDatabaseCount('settings', 1);
    }

    public function test_super_admin_can_open_settings(): void
    {
        $admin = $this->createAdmin(superAdmin: true);

        $this->actingAs($admin, 'admin')->get(route('dashboard.setting.index'))->assertOk();
    }

    public function test_admin_without_settings_view_receives_forbidden(): void
    {
        $admin = $this->adminWithAbilities([]);

        $this->actingAs($admin, 'admin')->get(route('dashboard.setting.index'))->assertForbidden();
    }

    public function test_admin_with_settings_edit_can_update_settings(): void
    {
        $admin = $this->adminWithAbilities(['settings.edit']);

        $this->actingAs($admin, 'admin')
            ->put(route('dashboard.setting.update'), $this->validPayload([
                'site_name' => 'Core Site',
                'default_currency' => 'eur',
            ]))
            ->assertRedirect(route('dashboard.setting.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('settings', [
            'site_name' => 'Core Site',
            'default_currency' => 'EUR',
        ]);
    }

    public function test_admin_with_settings_view_only_cannot_update_settings(): void
    {
        $admin = $this->adminWithAbilities(['settings.view']);

        $this->actingAs($admin, 'admin')
            ->put(route('dashboard.setting.update'), $this->validPayload())
            ->assertForbidden();
    }

    public function test_save_button_is_hidden_from_view_only_admin(): void
    {
        $admin = $this->adminWithAbilities(['settings.view']);

        $this->actingAs($admin, 'admin')
            ->get(route('dashboard.setting.index'))
            ->assertOk()
            ->assertDontSee('>Save</button>', false);
    }

    public function test_guest_cannot_access_settings(): void
    {
        $this->get(route('dashboard.setting.index'))->assertRedirect(route('admin.login'));
    }

    public function test_logo_upload_is_stored_on_public_disk(): void
    {
        Storage::fake('public');
        $admin = $this->adminWithAbilities(['settings.edit']);

        $this->actingAs($admin, 'admin')
            ->put(route('dashboard.setting.update'), $this->validPayload([
                'logo' => UploadedFile::fake()->image('logo.png'),
            ]))
            ->assertRedirect(route('dashboard.setting.index'));

        Storage::disk('public')->assertExists(Setting::singleton()->logo);
    }

    public function test_favicon_upload_is_stored_on_public_disk(): void
    {
        Storage::fake('public');
        $admin = $this->adminWithAbilities(['settings.edit']);

        $this->actingAs($admin, 'admin')
            ->put(route('dashboard.setting.update'), $this->validPayload([
                'favicon' => UploadedFile::fake()->image('favicon.png'),
            ]))
            ->assertRedirect(route('dashboard.setting.index'));

        Storage::disk('public')->assertExists(Setting::singleton()->favicon);
    }

    public function test_replacing_logo_deletes_old_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/old-logo.png', 'old');
        Setting::create(array_merge(Setting::defaults(), ['logo' => 'settings/old-logo.png']));
        $admin = $this->adminWithAbilities(['settings.edit']);

        $this->actingAs($admin, 'admin')
            ->put(route('dashboard.setting.update'), $this->validPayload([
                'logo' => UploadedFile::fake()->image('new-logo.png'),
            ]))
            ->assertRedirect(route('dashboard.setting.index'));

        $setting = Setting::singleton();
        Storage::disk('public')->assertMissing('settings/old-logo.png');
        Storage::disk('public')->assertExists($setting->logo);
    }

    public function test_update_without_replacement_keeps_old_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/existing-logo.png', 'old');
        Setting::create(array_merge(Setting::defaults(), ['logo' => 'settings/existing-logo.png']));
        $admin = $this->adminWithAbilities(['settings.edit']);

        $this->actingAs($admin, 'admin')
            ->put(route('dashboard.setting.update'), $this->validPayload())
            ->assertRedirect(route('dashboard.setting.index'));

        $this->assertSame('settings/existing-logo.png', Setting::singleton()->logo);
        Storage::disk('public')->assertExists('settings/existing-logo.png');
    }

    public function test_update_keeps_exactly_one_settings_row(): void
    {
        Setting::singleton();
        $admin = $this->adminWithAbilities(['settings.edit']);

        $this->actingAs($admin, 'admin')
            ->put(route('dashboard.setting.update'), $this->validPayload(['site_name' => 'Updated once']))
            ->assertRedirect(route('dashboard.setting.index'));

        $this->assertDatabaseCount('settings', 1);
    }

    public function test_invalid_timezone_fails_validation(): void
    {
        $admin = $this->adminWithAbilities(['settings.edit']);

        $this->actingAs($admin, 'admin')
            ->from(route('dashboard.setting.index'))
            ->put(route('dashboard.setting.update'), $this->validPayload(['timezone' => 'Not/A-Timezone']))
            ->assertRedirect(route('dashboard.setting.index'))
            ->assertSessionHasErrors('timezone');
    }

    public function test_currency_that_is_not_three_characters_fails_validation(): void
    {
        $admin = $this->adminWithAbilities(['settings.edit']);

        $this->actingAs($admin, 'admin')
            ->from(route('dashboard.setting.index'))
            ->put(route('dashboard.setting.update'), $this->validPayload(['default_currency' => 'EU']))
            ->assertRedirect(route('dashboard.setting.index'))
            ->assertSessionHasErrors('default_currency');
    }

    public function test_public_view_renders_before_settings_table_exists(): void
    {
        Schema::dropIfExists('settings');

        $this->get(route('home'))->assertOk();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'site_name' => 'Site',
            'site_description' => 'Description',
            'email' => 'site@example.com',
            'phone' => '0599000000',
            'address' => 'Address',
            'timezone' => 'UTC',
            'default_locale' => 'en',
            'default_currency' => 'USD',
        ], $overrides);
    }

    private function adminWithAbilities(array $abilities): Admin
    {
        $this->createAdmin();
        $admin = $this->createAdmin();

        foreach ($abilities as $ability) {
            RoleUser::create([
                'role_name' => $ability,
                'user_id' => $admin->id,
                'ability' => 'allow',
            ]);
        }

        return $admin;
    }

    private function createAdmin(bool $superAdmin = false): Admin
    {
        $number = Admin::query()->count() + 1;

        return Admin::create([
            'name' => 'Admin '.$number,
            'email' => 'admin'.$number.'@example.com',
            'phone' => '05990000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'password' => 'password',
            'status' => 'active',
            'super_admin' => $superAdmin,
        ]);
    }
}
