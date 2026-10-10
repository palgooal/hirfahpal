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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Markup contract of the HIRFAH vendor registration page and the existing
 * registration behavior behind it (AccountRegisteredUserController + CreateAccountUser).
 */
class VendorRegisterPageTest extends TestCase
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

    public function test_vendor_register_page_loads_with_the_vendor_view(): void
    {
        $this->page()->assertOk()->assertViewIs('auth.vendor.register');
    }

    public function test_other_account_types_keep_the_shared_register_view(): void
    {
        $this->get(route('customer.register'))->assertOk()->assertViewIs('auth.accounts.register');
        $this->get(route('delivery-driver.register'))->assertOk()->assertViewIs('auth.accounts.register');
    }

    public function test_page_is_rtl_arabic_with_vendor_copy(): void
    {
        $this->page()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('حِــرْفَة')
            ->assertSee('بوابة البائعين')
            ->assertSee('أنشئ حساب البائع')
            ->assertSee('إنشاء الحساب')
            ->assertSee('أدر متجرك وابدأ رحلتك مع حرفة')
            ->assertDontSee('بوابة البائعين في منصة حرفة');
    }

    public function test_create_account_icon_is_mirrored_in_rtl(): void
    {
        $this->page()->assertSee('class="h-4 w-4 rtl:-scale-x-100"', false);
    }

    public function test_password_hint_keeps_its_text_and_uses_the_stronger_style(): void
    {
        $this->page()->assertSee('<p id="password-hint" class="mt-2 text-start text-xs font-medium leading-5 text-ink/75">ثمانية أحرف على الأقل.</p>', false);
    }

    public function test_switching_to_english_renders_ltr(): void
    {
        $this->get(route('vendor.register').'?change-locale=en')
            ->assertRedirect(route('vendor.register'));

        $this->page()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Create your vendor account')
            ->assertSee('Already have an account?')
            ->assertSee('Manage your store and start your journey with Hirfah');
    }

    public function test_only_vendor_auth_assets_are_loaded(): void
    {
        $this->page()
            ->assertSee('resources/css/vendor-auth.css', false)
            ->assertSee('resources/js/admin-auth.js', false)
            ->assertDontSee('resources/css/app.css', false);
    }

    public function test_page_uses_vendor_routes_only(): void
    {
        $response = $this->page()
            ->assertSee('method="POST" action="'.route('vendor.register.store').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('href="'.route('vendor.login').'"', false)
            ->assertDontSee(route('admin.login'), false)
            ->assertDontSee('/dashboard', false);

        $this->assertSame(1, substr_count($response->getContent(), '<form'));
    }

    public function test_only_backend_supported_fields_are_rendered(): void
    {
        $response = $this->page();

        foreach (['full_name', 'phone', 'email', 'password', 'password_confirmation', 'terms'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }

        $response->assertDontSee('name="store_name"', false);
        // _token + the six registration inputs.
        $this->assertSame(7, substr_count($response->getContent(), '<input'));
    }

    public function test_password_fields_have_accessible_toggles(): void
    {
        $this->page()
            ->assertSee('aria-controls="password"', false)
            ->assertSee('aria-controls="password_confirmation"', false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertSee('aria-label="إظهار كلمة المرور"', false);
    }

    public function test_validation_errors_are_linked_to_their_fields(): void
    {
        $this->from(route('vendor.register'))
            ->post(route('vendor.register.store'), [
                'full_name' => 'Store Owner',
                'phone' => '',
                'email' => 'not-an-email',
                'password' => 'password',
                'password_confirmation' => 'different',
            ])
            ->assertRedirect(route('vendor.register'))
            ->assertSessionHasErrors(['phone', 'email', 'password', 'terms']);

        $this->page()
            ->assertSee('id="phone-error" role="alert"', false)
            ->assertSee('id="email-error" role="alert"', false)
            ->assertSee('id="password-error" role="alert"', false)
            ->assertSee('id="terms-error" role="alert"', false)
            ->assertSee('aria-describedby="email-error"', false)
            ->assertSee('aria-describedby="password-hint password-error"', false)
            ->assertSee('value="Store Owner"', false)
            ->assertSee('value="not-an-email"', false)
            ->assertDontSee('id="full_name-error"', false);

        $this->assertSame(0, Vendor::count());
    }

    public function test_duplicate_email_from_another_account_type_is_rejected(): void
    {
        Admin::create([
            'name' => 'Admin',
            'email' => 'taken@example.com',
            'phone' => '0599000001',
            'password' => 'password',
            'status' => 'active',
        ]);

        $this->from(route('vendor.register'))
            ->post(route('vendor.register.store'), $this->validInput(['email' => 'taken@example.com']))
            ->assertSessionHasErrors('email');

        $this->assertSame(0, Vendor::count());
    }

    public function test_successful_registration_follows_the_current_contract(): void
    {
        $this->post(route('vendor.register.store'), $this->validInput())
            ->assertRedirect(route('vendor.login'))
            ->assertSessionHas('status', __('auth.vendor_registration_pending'));

        $vendor = Vendor::with('profile')->sole();

        $this->assertSame('vendor@example.com', $vendor->email);
        $this->assertSame('active', $vendor->status);
        $this->assertSame('pending', $vendor->profile->approval_status);
        $this->assertSame('Store Owner', $vendor->profile->store_name);
        $this->assertGuest('vendor');
        $this->assertGuest('admin');
    }

    public function test_authenticated_vendor_cannot_open_the_register_page(): void
    {
        $vendor = Vendor::factory()->approved()->create();

        $this->actingAs($vendor, 'vendor')->page()->assertRedirect();
    }

    public function test_visiting_register_never_auto_creates_translation_rows(): void
    {
        TranslationValue::where('key', 'like', 'vendor.%')->delete();
        $before = TranslationValue::count();

        $this->page()->assertOk();
        $this->withSession(['locale' => 'en'])->page()->assertOk();

        $this->assertSame($before, TranslationValue::count());
    }

    private function page(): TestResponse
    {
        return $this->get(route('vendor.register'));
    }

    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Store Owner',
            'phone' => '0591000009',
            'email' => 'vendor@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ], $overrides);
    }
}
