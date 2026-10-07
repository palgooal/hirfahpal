<?php

namespace Tests\Feature;

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
 * Markup contract of the HIRFAH vendor reset-password page. The reset itself
 * is covered by VendorPasswordResetFlowTest.
 */
class VendorResetPasswordPageTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'reset-token-123';

    private const EMAIL = 'vendor@example.com';

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

    public function test_vendor_reset_url_renders_the_vendor_view(): void
    {
        $this->page()
            ->assertOk()
            ->assertViewIs('auth.vendor.reset-password')
            ->assertViewHas('token', self::TOKEN)
            ->assertViewHas('email', self::EMAIL);
    }

    public function test_other_account_types_keep_the_shared_view(): void
    {
        $this->get(route('customer.password.reset', ['token' => self::TOKEN]))->assertOk()->assertViewIs('auth.accounts.reset-password');
        $this->get(route('delivery-driver.password.reset', ['token' => self::TOKEN]))->assertOk()->assertViewIs('auth.accounts.reset-password');
    }

    public function test_token_and_email_from_the_link_are_carried_into_the_form(): void
    {
        $this->page()
            ->assertSee('<input type="hidden" name="token" value="'.self::TOKEN.'" />', false)
            ->assertSee('name="email"', false)
            ->assertSee('value="'.self::EMAIL.'"', false);
    }

    public function test_form_posts_to_vendor_password_update_only(): void
    {
        $response = $this->page()
            ->assertSee('method="POST" action="'.route('vendor.password.update').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('href="'.route('vendor.login').'"', false)
            ->assertDontSee(route('password.update'), false)
            ->assertDontSee(route('admin.login'), false)
            ->assertDontSee('/admin/', false)
            ->assertDontSee('/dashboard', false);

        $this->assertSame(1, substr_count($response->getContent(), '<form'));
        // _token + token + email + password + password_confirmation.
        $this->assertSame(5, substr_count($response->getContent(), '<input'));
    }

    public function test_password_and_confirmation_have_show_hide_controls(): void
    {
        $response = $this->page()
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('aria-controls="password"', false)
            ->assertSee('aria-controls="password_confirmation"', false)
            ->assertSee('aria-label="إظهار كلمة المرور"', false)
            ->assertSee('resources/js/admin-auth.js', false);

        $this->assertSame(2, substr_count($response->getContent(), 'data-password-toggle'));
        $this->assertSame(2, substr_count($response->getContent(), 'autocomplete="new-password"'));
    }

    public function test_page_is_rtl_arabic_with_vendor_copy(): void
    {
        $this->page()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('حِــرْفَة')
            ->assertSee('بوابة البائعين')
            ->assertSee('تعيين كلمة مرور جديدة')
            ->assertSee('أنشئ كلمة مرور جديدة لحساب البائع الخاص بك.')
            ->assertSee('كلمة المرور الجديدة')
            ->assertSee('تأكيد كلمة المرور')
            ->assertSee('تعيين كلمة المرور')
            ->assertSee('العودة إلى تسجيل الدخول')
            ->assertSee('أدر متجرك وابدأ رحلتك مع حرفة');
    }

    public function test_switching_to_english_renders_ltr(): void
    {
        $url = route('vendor.password.reset', ['token' => self::TOKEN]);

        $this->get($url.'?change-locale=en')->assertRedirect($url);

        $this->page()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Set a new password')
            ->assertSee('New password')
            ->assertSee('Back to sign in')
            ->assertSee('Manage your store and start your journey with Hirfah');
    }

    public function test_language_switcher_is_present(): void
    {
        $this->page()
            ->assertSee('aria-label="اختيار اللغة"', false)
            ->assertSee('change-locale=en', false);
    }

    public function test_only_vendor_auth_css_is_loaded(): void
    {
        $this->page()
            ->assertSee('resources/css/vendor-auth.css', false)
            ->assertDontSee('resources/css/app.css', false)
            ->assertDontSee('resources/css/admin-auth.css', false);
    }

    public function test_submit_icon_is_mirrored_in_rtl(): void
    {
        $this->page()->assertSee('class="h-4 w-4 rtl:-scale-x-100"', false);
    }

    public function test_password_validation_errors_are_linked_to_the_field(): void
    {
        $this->from($this->url())
            ->post(route('vendor.password.update'), [
                'token' => self::TOKEN,
                'email' => self::EMAIL,
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertRedirect($this->url())
            ->assertSessionHasErrors('password');

        $this->page()
            ->assertSee('id="password-error" role="alert"', false)
            ->assertSee('aria-describedby="password-hint password-error"', false)
            ->assertDontSee('id="email-error"', false);
    }

    public function test_broker_errors_are_shown_on_the_email_field(): void
    {
        Vendor::create([
            'name' => 'Vendor',
            'email' => self::EMAIL,
            'phone' => '0591000002',
            'password' => 'original-password',
            'status' => 'active',
        ]);

        $this->from($this->url())
            ->post(route('vendor.password.update'), [
                'token' => 'not-a-real-token',
                'email' => self::EMAIL,
                'password' => 'Brand-new-pass-1',
                'password_confirmation' => 'Brand-new-pass-1',
            ])
            ->assertRedirect($this->url())
            ->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->page()
            ->assertSee('id="email-error" role="alert"', false)
            ->assertSee(__('passwords.token'))
            ->assertSee('aria-invalid="true" aria-describedby="email-error"', false);
    }

    public function test_no_error_markup_without_errors(): void
    {
        $this->page()->assertDontSee('role="alert"', false)->assertDontSee('aria-invalid', false);
    }

    public function test_visiting_the_page_never_auto_creates_translation_rows(): void
    {
        TranslationValue::where('key', 'like', 'vendor.%')->delete();
        $before = TranslationValue::count();

        $this->page()->assertOk();
        $this->withSession(['locale' => 'en'])->page()->assertOk();

        $this->assertSame($before, TranslationValue::count());
    }

    private function url(): string
    {
        return route('vendor.password.reset', ['token' => self::TOKEN, 'email' => self::EMAIL]);
    }

    private function page(): TestResponse
    {
        return $this->get($this->url());
    }
}
