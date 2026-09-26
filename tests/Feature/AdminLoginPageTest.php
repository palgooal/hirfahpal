<?php

namespace Tests\Feature;

use App\Models\Language;
use Database\Seeders\AdminAuthTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Markup contract of the HIRFAH admin login page. Authentication behavior
 * itself is covered by AuthRateLimitingTest and AccountStatusEnforcementTest.
 */
class AdminLoginPageTest extends TestCase
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

    public function test_admin_login_page_loads(): void
    {
        $this->page()->assertOk();
    }

    public function test_page_is_rtl_arabic(): void
    {
        $this->page()->assertSee('<html lang="ar" dir="rtl">', false);
    }

    public function test_hirfah_branding_is_shown(): void
    {
        $this->page()
            ->assertSee('حِــرْفَة')
            ->assertSee('سوق الصنعة الفلسطينية')
            ->assertSee('مرحبًا بعودتك')
            ->assertSee('سجّل دخولك للوصول إلى لوحة إدارة حرفة.');
    }

    public function test_hirfah_logo_asset_is_referenced(): void
    {
        $this->page()->assertSee('src="'.asset('images/brand/hirfah-logo.png').'"', false);
        $this->assertFileExists(public_path('images/brand/hirfah-logo.png'));
    }

    public function test_only_admin_auth_assets_are_loaded(): void
    {
        $this->page()
            ->assertSee('resources/css/admin-auth.css', false)
            ->assertSee('resources/js/admin-auth.js', false)
            ->assertDontSee('resources/css/app.css', false)
            ->assertDontSee('resources/js/app.js', false);
    }

    public function test_form_posts_to_admin_login_store_with_csrf(): void
    {
        $response = $this->page()
            ->assertSee('method="POST" action="'.route('admin.login.store').'"', false)
            ->assertSee('name="_token"', false);

        $this->assertSame(1, substr_count($response->getContent(), '<form'));
    }

    public function test_login_field_contract(): void
    {
        $this->page()
            ->assertSee('name="login"', false)
            ->assertSee('dir="ltr"', false)
            ->assertSee('autocomplete="username"', false);
    }

    public function test_login_field_preserves_old_value(): void
    {
        $this->withSession(['_old_input' => ['login' => 'admin@example.com']])
            ->page()
            ->assertSee('value="admin@example.com"', false);
    }

    public function test_password_field_contract(): void
    {
        $this->page()
            ->assertSee('name="password"', false)
            ->assertSee('type="password"', false)
            ->assertSee('autocomplete="current-password"', false);
    }

    public function test_remember_field_sends_value_one(): void
    {
        $this->page()->assertSee('type="checkbox" name="remember" value="1"', false);
    }

    public function test_forgot_password_link_points_to_admin_password_request(): void
    {
        $this->page()
            ->assertSee('href="'.route('admin.password.request').'"', false)
            ->assertSee('نسيت كلمة المرور؟');
    }

    public function test_no_registration_otp_or_social_login(): void
    {
        $this->page()
            ->assertDontSee('register', false)
            ->assertDontSee('إنشاء حساب')
            ->assertDontSee('otp', false)
            ->assertDontSee('OTP', false)
            ->assertDontSee('رمز التحقق')
            ->assertDontSee('Google')
            ->assertDontSee('Facebook')
            ->assertDontSee('Apple');
    }

    public function test_session_status_is_rendered(): void
    {
        $this->withSession(['status' => __('passwords.reset')])
            ->page()
            ->assertSee('role="status"', false)
            ->assertSee(__('passwords.reset'));
    }

    public function test_server_errors_are_rendered_as_given(): void
    {
        $messages = [
            __('auth.failed'),
            __('auth.blocked'),
            __('auth.pending'),
            __('auth.inactive'),
            __('auth.throttle', ['seconds' => 42]),
        ];

        foreach ($messages as $message) {
            $this->withLoginError($message)
                ->page()
                ->assertSee('role="alert"', false)
                ->assertSee($message)
                ->assertSee('aria-invalid="true"', false);
        }
    }

    public function test_validation_error_is_rendered(): void
    {
        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), ['login' => 'someone@example.com'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('password');

        $this->page()
            ->assertSee('role="alert"', false)
            ->assertSee('value="someone@example.com"', false);
    }

    public function test_no_error_markup_without_errors(): void
    {
        $this->page()
            ->assertDontSee('role="alert"', false)
            ->assertDontSee('aria-invalid', false);
    }

    public function test_password_toggle_is_present_and_accessible(): void
    {
        $this->page()
            ->assertSee('type="button"', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('aria-controls="password"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('aria-label="إظهار كلمة المرور"', false)
            ->assertSee('data-label-hide="إخفاء كلمة المرور"', false);
    }

    private function page(): TestResponse
    {
        return $this->get(route('admin.login'));
    }

    /**
     * Puts an error in the session the same way a redirect back from the
     * login controller or EnsureAccountIsActive does.
     */
    private function withLoginError(string $message): static
    {
        return $this->withSession([
            'errors' => (new ViewErrorBag)->put('default', new MessageBag(['login' => $message])),
        ]);
    }
}
