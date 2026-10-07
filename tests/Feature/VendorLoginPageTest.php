<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Language;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Markup contract of the HIRFAH vendor login page and the existing vendor
 * guard behavior behind it (AccountAuthenticatedSessionController).
 */
class VendorLoginPageTest extends TestCase
{
    use RefreshDatabase;

    private string $hotFile;

    protected function setUp(): void
    {
        parent::setUp();

        // UI strings come from seeded translation_values; the default locale is Arabic (RTL).
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->seed(AdminAuthTranslationSeeder::class);
        $this->seed(VendorAuthTranslationSeeder::class);

        // A hot file makes @vite print the entry names without needing a build.
        $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot');
        file_put_contents($this->hotFile, 'http://localhost:5173');
        Vite::useHotFile($this->hotFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    public function test_vendor_login_page_loads(): void
    {
        $this->page()->assertOk();
    }

    public function test_page_is_rtl_arabic_with_vendor_copy(): void
    {
        $this->page()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('حِــرْفَة')
            ->assertSee('بوابة البائعين')
            ->assertSee('سجّل دخولك لإدارة متجرك ومنتجاتك وطلباتك على حرفة.')
            ->assertSee('أدر متجرك وابدأ رحلتك مع حرفة')
            ->assertDontSee('بوابة البائعين في منصة حرفة');
    }

    public function test_sign_in_icon_is_mirrored_in_rtl(): void
    {
        $this->page()->assertSee('class="h-4 w-4 rtl:-scale-x-100"', false);
    }

    public function test_switching_to_english_renders_ltr(): void
    {
        $this->get(route('vendor.login').'?change-locale=en')
            ->assertRedirect(route('vendor.login'));

        $this->page()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Vendor portal')
            ->assertSee('Register as a vendor')
            ->assertSee('Manage your store and start your journey with Hirfah');
    }

    public function test_hirfah_logo_asset_is_referenced(): void
    {
        $this->page()->assertSee('src="'.asset('images/brand/hirfah-logo.png').'"', false);
    }

    public function test_only_vendor_auth_assets_are_loaded(): void
    {
        $this->page()
            ->assertSee('resources/css/vendor-auth.css', false)
            ->assertSee('resources/js/admin-auth.js', false)
            ->assertDontSee('resources/css/admin-auth.css', false)
            ->assertDontSee('resources/css/app.css', false);
    }

    public function test_page_uses_vendor_routes_only(): void
    {
        $response = $this->page()
            ->assertSee('method="POST" action="'.route('vendor.login.store').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('href="'.route('vendor.password.request').'"', false)
            ->assertSee('href="'.route('vendor.register').'"', false)
            ->assertDontSee(route('admin.login'), false)
            ->assertDontSee(route('admin.password.request'), false)
            ->assertDontSee('/dashboard', false);

        $this->assertSame(1, substr_count($response->getContent(), '<form'));
    }

    public function test_field_contract_matches_the_backend(): void
    {
        $this->page()
            ->assertSee('name="login"', false)
            ->assertSee('autocomplete="username"', false)
            ->assertSee('placeholder="name@example.com / 05xxxxxxxx"', false)
            ->assertDontSee('placeholder="example@email.com"', false)
            ->assertSee('name="password"', false)
            ->assertSee('type="password"', false)
            ->assertSee('type="checkbox" name="remember" value="1"', false);
    }

    public function test_password_toggle_is_present_and_accessible(): void
    {
        $this->page()
            ->assertSee('data-password-toggle', false)
            ->assertSee('aria-controls="password"', false)
            ->assertSee('aria-label="إظهار كلمة المرور"', false);
    }

    public function test_linked_vendor_password_and_register_pages_work(): void
    {
        $this->get(route('vendor.password.request'))->assertOk();
        $this->get(route('vendor.register'))->assertOk();
    }

    public function test_server_errors_are_rendered_as_given(): void
    {
        foreach ([__('auth.failed'), __('auth.blocked'), __('auth.pending'), __('auth.throttle', ['seconds' => 42])] as $message) {
            $this->withSession([
                'errors' => (new ViewErrorBag)->put('default', new MessageBag(['login' => $message])),
            ])
                ->page()
                ->assertSee('role="alert"', false)
                ->assertSee($message)
                ->assertSee('aria-invalid="true"', false);
        }
    }

    public function test_no_error_markup_without_errors(): void
    {
        $this->page()->assertDontSee('role="alert"', false);
    }

    public function test_visiting_login_never_auto_creates_translation_rows(): void
    {
        TranslationValue::where('key', 'like', 'vendor.%')->delete();
        $before = TranslationValue::count();

        $this->page()->assertOk();
        $this->withSession(['locale' => 'en'])->page()->assertOk();

        $this->assertSame($before, TranslationValue::count());
    }

    public function test_vendor_can_login_with_valid_credentials(): void
    {
        $vendor = $this->vendor();

        $this->post(route('vendor.login.store'), [
            'login' => $vendor->email,
            'password' => 'password',
        ])->assertRedirect(route('vendor.dashboard'));

        $this->assertAuthenticatedAs($vendor, 'vendor');
    }

    public function test_vendor_can_login_with_phone(): void
    {
        $vendor = $this->vendor();

        $this->post(route('vendor.login.store'), [
            'login' => $vendor->phone,
            'password' => 'password',
        ])->assertRedirect(route('vendor.dashboard'));

        $this->assertAuthenticatedAs($vendor, 'vendor');
    }

    public function test_wrong_credentials_show_the_error_on_the_page(): void
    {
        $vendor = $this->vendor();

        $this->from(route('vendor.login'))
            ->post(route('vendor.login.store'), [
                'login' => $vendor->email,
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('vendor.login'))
            ->assertSessionHasErrors(['login' => __('auth.failed')]);

        $this->assertGuest('vendor');

        $this->page()
            ->assertSee(__('auth.failed'))
            ->assertSee('value="'.$vendor->email.'"', false);
    }

    public function test_admin_credentials_do_not_grant_a_vendor_session(): void
    {
        $admin = Admin::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '0599000001',
            'password' => 'password',
            'status' => 'active',
        ]);

        $this->from(route('vendor.login'))
            ->post(route('vendor.login.store'), [
                'login' => $admin->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('vendor.login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest('vendor');
        $this->assertGuest('admin');
    }

    public function test_authenticated_vendor_cannot_open_the_login_page(): void
    {
        $this->actingAs($this->vendor(), 'vendor')
            ->get(route('vendor.login'))
            ->assertRedirect();
    }

    private function page(): TestResponse
    {
        return $this->get(route('vendor.login'));
    }

    private function vendor(): Vendor
    {
        return Vendor::create([
            'name' => 'Vendor',
            'email' => 'vendor@example.com',
            'phone' => '0591000002',
            'password' => 'password',
            'status' => 'active',
        ]);
    }
}
