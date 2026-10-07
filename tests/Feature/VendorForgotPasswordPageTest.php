<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Markup contract of the HIRFAH vendor forgot-password page and the existing
 * reset-link behavior behind it (AccountPasswordResetLinkController).
 */
class VendorForgotPasswordPageTest extends TestCase
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

    public function test_vendor_forgot_password_page_loads_with_the_vendor_view(): void
    {
        $this->page()->assertOk()->assertViewIs('auth.vendor.forgot-password');
    }

    public function test_other_account_types_keep_the_shared_view(): void
    {
        $this->get(route('customer.password.request'))->assertOk()->assertViewIs('auth.accounts.forgot-password');
        $this->get(route('delivery-driver.password.request'))->assertOk()->assertViewIs('auth.accounts.forgot-password');
    }

    public function test_page_is_rtl_arabic_with_vendor_copy(): void
    {
        $this->page()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('حِــرْفَة')
            ->assertSee('بوابة البائعين')
            ->assertSee('نسيت كلمة المرور؟')
            ->assertSee('أدخل بريدك الإلكتروني وسنرسل لك رابطًا لإعادة تعيين كلمة المرور.')
            ->assertSee('إرسال رابط إعادة التعيين')
            ->assertSee('تذكرت كلمة المرور؟')
            ->assertSee('أدر متجرك وابدأ رحلتك مع حرفة');
    }

    public function test_switching_to_english_renders_ltr(): void
    {
        $this->get(route('vendor.password.request').'?change-locale=en')
            ->assertRedirect(route('vendor.password.request'));

        $this->page()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Forgot your password?')
            ->assertSee('Send reset link')
            ->assertSee('Remembered your password?')
            ->assertSee('Manage your store and start your journey with Hirfah');
    }

    public function test_only_vendor_auth_css_is_loaded(): void
    {
        $this->page()
            ->assertSee('resources/css/vendor-auth.css', false)
            ->assertDontSee('resources/css/app.css', false)
            ->assertDontSee('resources/css/admin-auth.css', false);
    }

    public function test_page_uses_vendor_routes_only(): void
    {
        $response = $this->page()
            ->assertSee('method="POST" action="'.route('vendor.password.email').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('href="'.route('vendor.login').'"', false)
            ->assertDontSee(route('admin.login'), false)
            ->assertDontSee('/dashboard', false);

        $this->assertSame(1, substr_count($response->getContent(), '<form'));
    }

    public function test_only_the_email_field_is_rendered(): void
    {
        $response = $this->page()
            ->assertSee('name="email"', false)
            ->assertSee('type="email"', false)
            ->assertSee('autocomplete="email"', false)
            ->assertDontSee('name="phone"', false)
            ->assertDontSee('name="login"', false);

        // _token + email.
        $this->assertSame(2, substr_count($response->getContent(), '<input'));
    }

    public function test_send_icon_is_mirrored_in_rtl(): void
    {
        $this->page()->assertSee('class="h-4 w-4 rtl:-scale-x-100"', false);
    }

    public function test_invalid_email_error_is_linked_to_the_field(): void
    {
        $this->from(route('vendor.password.request'))
            ->post(route('vendor.password.email'), ['email' => 'not-an-email'])
            ->assertRedirect(route('vendor.password.request'))
            ->assertSessionHasErrors('email');

        $this->page()
            ->assertSee('id="email-error" role="alert"', false)
            ->assertSee('aria-invalid="true" aria-describedby="email-error"', false)
            ->assertSee('value="not-an-email"', false);
    }

    public function test_existing_vendor_gets_the_success_status_and_a_reset_notification(): void
    {
        Notification::fake();
        $vendor = $this->vendor();

        $this->from(route('vendor.password.request'))
            ->post(route('vendor.password.email'), ['email' => $vendor->email])
            ->assertRedirect(route('vendor.password.request'))
            ->assertSessionHas('status', __('passwords.sent'));

        Notification::assertSentTo($vendor, ResetPassword::class);

        $this->page()
            ->assertSee('role="status"', false)
            ->assertSee(__('passwords.sent'))
            ->assertDontSee('role="alert"', false);
    }

    public function test_unknown_email_keeps_the_current_broker_error(): void
    {
        Notification::fake();

        $this->from(route('vendor.password.request'))
            ->post(route('vendor.password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors(['email' => __('passwords.user')]);

        Notification::assertNothingSent();
    }

    public function test_authenticated_vendor_cannot_open_the_page(): void
    {
        $this->actingAs($this->vendor(), 'vendor')
            ->get(route('vendor.password.request'))
            ->assertRedirect();
    }

    public function test_visiting_the_page_never_auto_creates_translation_rows(): void
    {
        TranslationValue::where('key', 'like', 'vendor.%')->delete();
        $before = TranslationValue::count();

        $this->page()->assertOk();
        $this->withSession(['locale' => 'en'])->page()->assertOk();

        $this->assertSame($before, TranslationValue::count());
    }

    private function page(): TestResponse
    {
        return $this->get(route('vendor.password.request'));
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
