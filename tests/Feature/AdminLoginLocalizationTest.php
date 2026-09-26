<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Language;
use App\Models\TranslationValue;
use Database\Seeders\AdminAuthTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Admin login localization: t() strings, DB-driven lang/dir, the
 * ?change-locale switcher and Laravel system messages per locale.
 */
class AdminLoginLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->language('ar', 'العربية', rtl: true);
        $this->language('en', 'English', rtl: false);
        $this->seed(AdminAuthTranslationSeeder::class);
    }

    public function test_default_arabic_renders_lang_ar_and_rtl(): void
    {
        $this->loginPage()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('مرحبًا بعودتك')
            ->assertSee('تسجيل الدخول');
    }

    public function test_switching_to_english_persists_in_session_and_renders_ltr(): void
    {
        $this->get(route('admin.login').'?change-locale=en')
            ->assertRedirect(route('admin.login'));

        $this->assertSame('en', session('locale'));

        $this->loginPage()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Welcome back')
            ->assertSee('Sign in to access the Hirfah admin panel.')
            ->assertDontSee('مرحبًا بعودتك');
    }

    public function test_switching_back_to_arabic_works(): void
    {
        $this->get(route('admin.login').'?change-locale=en');
        $this->get(route('admin.login').'?change-locale=ar')->assertRedirect(route('admin.login'));

        $this->assertSame('ar', session('locale'));
        $this->loginPage()->assertSee('<html lang="ar" dir="rtl">', false);
    }

    public function test_switcher_lists_every_active_language_and_marks_the_current_one(): void
    {
        $response = $this->loginPage()
            ->assertSee('aria-label="اختيار اللغة"', false)
            ->assertSee('aria-current="true"', false)
            ->assertSee('change-locale=en', false)
            ->assertDontSee('change-locale=ar', false);

        $this->assertSame(1, substr_count($response->getContent(), 'aria-current="true"'));
        $this->assertStringContainsString('>العربية</span>', $response->getContent());
        $this->assertStringContainsString('>English</a>', $response->getContent());
    }

    public function test_inactive_language_is_hidden_and_cannot_be_selected(): void
    {
        $this->language('fr', 'Français', rtl: false, active: false);

        $this->loginPage()->assertDontSee('Français')->assertDontSee('change-locale=fr', false);

        $this->get(route('admin.login').'?change-locale=fr');
        $this->assertNotSame('fr', session('locale'));
        $this->loginPage()->assertSee('<html lang="ar" dir="rtl">', false);
    }

    public function test_arabic_ui_comes_from_seeded_translation_values(): void
    {
        TranslationValue::where('key', 'dashboard.Welcome_Back')->where('locale', 'ar')->update(['value' => 'أهلًا بك مجددًا']);

        $this->loginPage()->assertSee('أهلًا بك مجددًا')->assertDontSee('مرحبًا بعودتك');
    }

    public function test_english_ui_comes_from_seeded_translation_values(): void
    {
        TranslationValue::where('key', 'dashboard.Sign_In')->where('locale', 'en')->update(['value' => 'Log in to Hirfah']);

        $this->withSession(['locale' => 'en'])->loginPage()
            ->assertSee('Log in to Hirfah')
            ->assertSee('Remember me')
            ->assertSee('Forgot your password?')
            ->assertSee('data-label-hide="Hide password"', false);
    }

    public function test_visiting_login_never_auto_creates_translation_rows(): void
    {
        $this->language('fr', 'Français', rtl: false);
        $before = TranslationValue::count();

        $this->loginPage();
        $this->withSession(['locale' => 'en'])->loginPage();
        $this->withSession(['locale' => 'fr'])->loginPage();

        $this->assertSame($before, TranslationValue::count());
    }

    public function test_third_active_language_appears_without_hardcoding_and_uses_its_direction(): void
    {
        $this->language('fr', 'Français', rtl: false);
        $this->language('ur', 'اردو', rtl: true);

        $this->loginPage()->assertSee('Français')->assertSee('change-locale=fr', false)->assertSee('change-locale=ur', false);

        $this->get(route('admin.login').'?change-locale=ur');
        $this->loginPage()->assertSee('<html lang="ur" dir="rtl">', false);

        $this->get(route('admin.login').'?change-locale=fr');
        $this->loginPage()->assertSee('<html lang="fr" dir="ltr">', false);
    }

    public function test_missing_translation_follows_the_fallback_contract_without_db_writes(): void
    {
        $this->language('fr', 'Français', rtl: false);

        // fr has no rows: t() falls back to the fallback locale (en), and nothing is inserted.
        $this->withSession(['locale' => 'fr'])->loginPage()
            ->assertSee('Welcome back')
            ->assertSee('Sign in');

        $this->assertSame(0, TranslationValue::where('locale', 'fr')->count());
    }

    public function test_direction_is_read_from_the_database_not_from_the_locale_code(): void
    {
        Language::where('code', 'ar')->update(['is_rtl' => false]);

        $this->loginPage()->assertSee('<html lang="ar" dir="ltr">', false);
    }

    public function test_login_validation_follows_the_locale(): void
    {
        $this->from(route('admin.login'))->post(route('admin.login.store'), [])
            ->assertSessionHasErrors(['password' => 'حقل كلمة المرور مطلوب.']);

        $this->withSession(['locale' => 'en'])->from(route('admin.login'))->post(route('admin.login.store'), [])
            ->assertSessionHasErrors(['password' => 'The password field is required.']);
    }

    public function test_authentication_errors_follow_the_locale(): void
    {
        $credentials = ['login' => 'nobody@example.com', 'password' => 'wrong-password'];

        $this->from(route('admin.login'))->post(route('admin.login.store'), $credentials)
            ->assertSessionHasErrors(['login' => 'بيانات الدخول غير صحيحة.']);

        $this->withSession(['locale' => 'en'])->from(route('admin.login'))->post(route('admin.login.store'), $credentials)
            ->assertSessionHasErrors(['login' => 'These credentials do not match our records.']);
    }

    public function test_password_broker_messages_follow_the_locale(): void
    {
        $this->from(route('admin.password.request'))->post(route('admin.password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors(['email' => 'لا يوجد مستخدم بهذا البريد الإلكتروني.']);

        $this->withSession(['locale' => 'en'])->from(route('admin.password.request'))->post(route('admin.password.email'), ['email' => 'nobody2@example.com'])
            ->assertSessionHasErrors(['email' => "We can't find a user with that email address."]);
    }

    public function test_rate_limiting_still_applies_and_speaks_the_locale(): void
    {
        $this->freezeTime();
        $this->withSession(['locale' => 'en']);

        foreach (range(1, 5) as $attempt) {
            $this->from(route('admin.login'))->post(route('admin.login.store'), ['login' => 'target@example.com', 'password' => 'wrong']);
        }

        $this->from(route('admin.login'))->post(route('admin.login.store'), ['login' => 'target@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors(['login' => 'Too many login attempts. Please try again in 60 seconds.']);
    }

    public function test_inactive_admin_session_is_still_forced_out(): void
    {
        $admin = Admin::create(['name' => 'A', 'email' => 'a@example.com', 'phone' => '0590000001', 'password' => 'password', 'status' => 'blocked', 'super_admin' => true]);

        $this->actingAs($admin, 'admin')->get(route('admin.login'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('login');
        $this->assertGuest('admin');
    }

    public function test_forgot_and_reset_pages_still_work(): void
    {
        $this->get(route('admin.password.request'))->assertOk();
        $this->get(route('admin.password.reset', ['token' => 'some-token']))->assertOk();
    }

    public function test_admin_routes_carry_the_expected_middleware(): void
    {
        $middleware = fn (string $name) => Route::getRoutes()->getByName($name)->gatherMiddleware();

        foreach (['admin.login', 'admin.login.store', 'admin.password.request', 'admin.password.email', 'admin.password.reset', 'admin.password.update'] as $name) {
            $this->assertContains('setLocale', $middleware($name), $name);
            $this->assertContains('guest:admin', $middleware($name), $name);
            $this->assertContains('disableTranslationAutoCreate', $middleware($name), $name);
        }

        $this->assertContains('throttle:account-login-ip', $middleware('admin.login.store'));
        $this->assertContains('auth:admin', $middleware('admin.logout'));
        $this->assertContains('setLocale', $middleware('admin.logout'));
        $this->assertNotContains('disableTranslationAutoCreate', $middleware('admin.logout'));
        $this->assertNotContains('disableTranslationAutoCreate', $middleware('dashboard.home'));
        $this->assertNotContains('disableTranslationAutoCreate', $middleware('dashboard.admins.index'));
    }

    public function test_dashboard_language_switching_still_works(): void
    {
        $admin = Admin::create(['name' => 'A', 'email' => 'a@example.com', 'phone' => '0590000001', 'password' => 'password', 'status' => 'active', 'super_admin' => true]);

        $this->actingAs($admin, 'admin')->get(route('dashboard.home').'?change-locale=en')
            ->assertRedirect(route('dashboard.home'));

        $this->assertSame('en', session('locale'));
        $this->actingAs($admin, 'admin')->get(route('dashboard.home'))->assertOk();
    }

    private function loginPage(): TestResponse
    {
        return $this->get(route('admin.login'))->assertOk();
    }

    private function language(string $code, string $native, bool $rtl, bool $active = true): Language
    {
        return Language::create(['name' => strtoupper($code), 'native' => $native, 'code' => $code, 'is_rtl' => $rtl, 'is_active' => $active]);
    }
}
