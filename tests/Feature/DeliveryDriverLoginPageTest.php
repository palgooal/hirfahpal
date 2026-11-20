<?php

namespace Tests\Feature;

use App\Models\DeliveryDriver;
use App\Models\Language;
use App\Models\TranslationValue;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\DeliveryDriverAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The delivery driver login page in the vendor portal's design (auth/accounts/login), its Arabic and English texts
 * (DeliveryDriverAuthTranslationSeeder, with the same texts as defaults until it runs) and the unchanged driver guard
 * behind it.
 */
class DeliveryDriverLoginPageTest extends TestCase
{
    use RefreshDatabase;

    private string $hotFile;

    protected function setUp(): void
    {
        parent::setUp();

        // The default locale is Arabic (RTL); the shared auth strings are seeded, as they are locally.
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

    public function test_the_driver_login_page_loads_in_arabic_rtl_with_the_driver_copy(): void
    {
        $this->seed(DeliveryDriverAuthTranslationSeeder::class);

        $this->page()->assertOk()->assertViewIs('auth.accounts.login')
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('<title>تسجيل الدخول | بوابة مندوبي التوصيل في حرفة</title>', false)
            ->assertSeeInOrder(['بوابة مندوبي التوصيل', 'مرحبًا بعودتك', 'سجّل دخولك لإدارة مهام التوصيل ومتابعة الشحنات على حِرفة'])
            ->assertSeeInOrder(['البريد الإلكتروني أو رقم الجوال', 'كلمة المرور', 'تذكرني', 'نسيت كلمة المرور؟', 'تسجيل الدخول'])
            ->assertSeeInOrder(['ليس لديك حساب؟', 'انضم كمندوب توصيل'])
            ->assertSee('أوصل إبداعات حِرفة إلى أصحابها')
            ->assertSee('حِــرْفَة')
            ->assertSee('سوق الصنعة الفلسطينية')
            ->assertDontSee('بوابة البائعين');
    }

    public function test_switching_to_english_renders_ltr_with_the_english_copy(): void
    {
        $this->seed(DeliveryDriverAuthTranslationSeeder::class);

        $this->get(route('delivery-driver.login').'?change-locale=en')->assertRedirect(route('delivery-driver.login'));

        $this->page()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Delivery driver portal')
            ->assertSee('Welcome back')
            ->assertSee('Sign in to manage your delivery tasks and track shipments on Hirfah.')
            ->assertSee('Join as a delivery driver')
            ->assertSee('data-loading-label="Signing in..."', false)
            ->assertDontSee('بوابة مندوبي التوصيل');

        // And back to Arabic.
        $this->get(route('delivery-driver.login').'?change-locale=ar')->assertRedirect(route('delivery-driver.login'));
        $this->page()->assertSee('<html lang="ar" dir="rtl">', false)->assertSee('بوابة مندوبي التوصيل');
    }

    public function test_before_the_seeder_runs_each_language_shows_its_own_text_and_no_row_is_created(): void
    {
        $before = TranslationValue::count();

        $this->page()->assertOk()
            ->assertSee('بوابة مندوبي التوصيل')
            ->assertSee('أوصل إبداعات حِرفة إلى أصحابها')
            ->assertSee('data-loading-label="جارٍ تسجيل الدخول..."', false)
            ->assertDontSee('Delivery driver portal');

        $this->withSession(['locale' => 'en'])->page()->assertOk()
            ->assertSee('Delivery driver portal')
            ->assertSee('Join as a delivery driver')
            ->assertDontSee('بوابة مندوبي التوصيل');

        $this->assertSame($before, TranslationValue::count(), 'Viewing the page creates no translation row.');
        $this->assertSame(0, TranslationValue::where('key', 'like', 'driver.%')->count());
    }

    public function test_visiting_the_seeded_page_or_its_guest_pages_never_auto_creates_translation_rows(): void
    {
        $this->seed(DeliveryDriverAuthTranslationSeeder::class);
        TranslationValue::where('key', 'dashboard.Remember_Me')->delete();
        $before = TranslationValue::count();

        $this->page()->assertOk();
        $this->withSession(['locale' => 'en'])->page()->assertOk();
        $this->get(route('delivery-driver.register'))->assertOk();
        $this->get(route('delivery-driver.password.request'))->assertOk();

        $this->assertSame($before, TranslationValue::count());
    }

    public function test_only_the_vendor_auth_design_assets_are_loaded(): void
    {
        $this->page()
            ->assertSee('resources/css/vendor-auth.css', false)
            ->assertSee('resources/js/admin-auth.js', false)
            ->assertSee('src="'.asset('images/brand/hirfah-logo.png').'"', false)
            ->assertDontSee('resources/css/app.css', false)
            ->assertDontSee('resources/js/app.js', false)
            ->assertDontSee('-blue-', false)
            ->assertDontSee('bg-slate-', false)
            ->assertDontSee('text-slate-', false)
            ->assertSee('<div class="grid min-h-screen lg:grid-cols-2">', false)
            ->assertSee('class="relative hidden min-w-0 overflow-hidden bg-olive lg:flex', false)
            ->assertSee('<div class="mb-8 flex items-center justify-center gap-3 lg:hidden">', false);

        $this->assertStringContainsString('@source "../views/auth/accounts/login.blade.php";', file_get_contents(resource_path('css/vendor-auth.css')));
    }

    public function test_the_language_switcher_offers_arabic_and_english(): void
    {
        $this->page()
            ->assertSee('<span'."\n".'                            aria-current="true"'."\n".'                            lang="ar"', false)
            ->assertSee('href="'.route('delivery-driver.login').'?change-locale=en"', false);
    }

    public function test_the_form_uses_the_driver_routes_and_the_existing_field_contract(): void
    {
        $response = $this->page()
            ->assertSee('method="POST" action="'.route('delivery-driver.login.store').'" class="mt-6 flex flex-col gap-5" data-submit-lock>', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="login"', false)
            ->assertSee('autocomplete="username"', false)
            ->assertSee('name="password"', false)
            ->assertSee('type="password"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('type="checkbox" name="remember" value="1"', false)
            ->assertSee('href="'.route('delivery-driver.password.request').'"', false)
            ->assertSee('href="'.route('delivery-driver.register').'"', false)
            ->assertSee('<p data-submit-status role="status" aria-live="polite" class="sr-only"></p>', false)
            ->assertDontSee(route('vendor.login.store'), false)
            ->assertDontSee(route('vendor.register'), false);

        $this->assertSame(1, substr_count($response->getContent(), '<form'));
    }

    public function test_the_password_toggle_is_present_and_accessible(): void
    {
        $this->page()
            ->assertSee('data-password-toggle', false)
            ->assertSee('aria-controls="password"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('aria-label="إظهار كلمة المرور"', false)
            ->assertSee('data-label-hide="إخفاء كلمة المرور"', false);
    }

    public function test_server_errors_and_the_typed_login_are_shown(): void
    {
        $this->page()->assertDontSee('role="alert"', false);

        foreach ([__('auth.failed'), __('auth.blocked'), __('auth.throttle', ['seconds' => 42])] as $message) {
            $this->withSession([
                'errors' => (new ViewErrorBag)->put('default', new MessageBag(['login' => $message])),
                '_old_input' => ['login' => 'driver@example.com', 'remember' => '1'],
            ])->page()
                ->assertSee('<p id="driver-login-error" role="alert"', false)
                ->assertSee($message)
                ->assertSee('aria-invalid="true" aria-describedby="driver-login-error"', false)
                ->assertSee('value="driver@example.com"', false)
                ->assertSee('name="remember" value="1" checked', false);
        }
    }

    public function test_a_status_message_is_shown(): void
    {
        $this->withSession(['status' => 'تم إرسال الرابط.'])->page()->assertSee('role="status" class="mt-6 flex', false)->assertSee('تم إرسال الرابط.');
    }

    public function test_driver_login_still_works_by_email_or_phone_with_remember_me(): void
    {
        $driver = DeliveryDriver::factory()->create();

        $this->post(route('delivery-driver.login.store'), ['login' => $driver->email, 'password' => 'password', 'remember' => '1'])
            ->assertRedirect(route('delivery-driver.dashboard'));
        $this->assertAuthenticatedAs($driver, 'delivery_driver');
        $this->assertNotNull($driver->fresh()->remember_token);

        $this->post(route('delivery-driver.logout'));
        $this->post(route('delivery-driver.login.store'), ['login' => $driver->phone, 'password' => 'password'])
            ->assertRedirect(route('delivery-driver.dashboard'));
        $this->assertAuthenticatedAs($driver, 'delivery_driver');
    }

    public function test_wrong_credentials_return_to_the_page_with_the_error_and_the_login_kept(): void
    {
        $driver = DeliveryDriver::factory()->create();

        $this->from(route('delivery-driver.login'))
            ->post(route('delivery-driver.login.store'), ['login' => $driver->email, 'password' => 'wrong-password'])
            ->assertRedirect(route('delivery-driver.login'))
            ->assertSessionHasErrors('login');
        $this->assertGuest('delivery_driver');

        $this->page()->assertSee('<p id="driver-login-error" role="alert"', false)->assertSee('value="'.$driver->email.'"', false);
    }

    public function test_a_blocked_driver_still_cannot_sign_in(): void
    {
        $driver = DeliveryDriver::factory()->create(['status' => 'blocked']);

        $this->post(route('delivery-driver.login.store'), ['login' => $driver->email, 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest('delivery_driver');
    }

    public function test_the_vendor_login_page_is_unchanged(): void
    {
        $this->seed(VendorAuthTranslationSeeder::class);
        $this->seed(DeliveryDriverAuthTranslationSeeder::class);

        $this->get(route('vendor.login'))->assertOk()->assertViewIs('auth.vendor.login')
            ->assertSee('بوابة البائعين')
            ->assertSee('أدر متجرك وابدأ رحلتك مع حرفة')
            ->assertSee('method="POST" action="'.route('vendor.login.store').'"', false)
            ->assertDontSee('بوابة مندوبي التوصيل')
            ->assertDontSee(route('delivery-driver.login.store'), false);
    }

    public function test_the_seeder_holds_arabic_and_english_only_and_inserts_missing_rows_only(): void
    {
        foreach (DeliveryDriverAuthTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertStringStartsWith('driver.', $key);
            $this->assertSame(['ar', 'en'], array_keys($values), $key);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], $key);
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $values['en'], $key);
        }

        TranslationValue::create(['key' => 'driver.Portal', 'locale' => 'ar', 'value' => 'قيمة معدّلة']);
        cache()->put('translation.en.driver.Portal', null, 60);
        $this->seed(DeliveryDriverAuthTranslationSeeder::class);
        $first = TranslationValue::where('key', 'like', 'driver.%')->orderBy('id')->get(['id', 'key', 'locale', 'value'])->toArray();
        $this->seed(DeliveryDriverAuthTranslationSeeder::class);

        $this->assertSame($first, TranslationValue::where('key', 'like', 'driver.%')->orderBy('id')->get(['id', 'key', 'locale', 'value'])->toArray());
        $this->assertCount(count(DeliveryDriverAuthTranslationSeeder::TRANSLATIONS) * 2, $first);
        $this->assertSame('قيمة معدّلة', TranslationValue::where('key', 'driver.Portal')->where('locale', 'ar')->value('value'), 'An edited row is kept.');
        $this->assertStringNotContainsString('DeliveryDriverAuthTranslationSeeder', file_get_contents(database_path('seeders/DatabaseSeeder.php')));

        $this->assertSame('بوابة مندوبي التوصيل', DeliveryDriverAuthTranslationSeeder::fallback('driver.Portal', 'ar'));
        $this->assertSame('Delivery driver portal', DeliveryDriverAuthTranslationSeeder::fallback('driver.Portal', 'en'));
        $this->assertSame('Delivery driver portal', DeliveryDriverAuthTranslationSeeder::fallback('driver.Portal', 'fr'), 'Any other locale uses English.');
    }

    public function test_every_driver_key_the_page_reads_is_seeded(): void
    {
        preg_match_all("/\\\$driverText\('([A-Za-z_.]+)'\)/", file_get_contents(resource_path('views/auth/accounts/login.blade.php')), $matches);
        $used = array_values(array_unique($matches[1]));
        sort($used);

        // The login page's own keys; the seeder also holds the registration page's (DeliveryDriverRegisterPageTest).
        $this->assertSame([
            'driver.Login_Description',
            'driver.Login_Panel_Line',
            'driver.Login_Title',
            'driver.No_Account',
            'driver.Portal',
            'driver.Register_As_Driver',
            'driver.Signing_In',
        ], $used);
        $this->assertSame([], array_diff($used, array_keys(DeliveryDriverAuthTranslationSeeder::TRANSLATIONS)), 'Every key the page reads is seeded.');
    }

    private function page(): TestResponse
    {
        return $this->get(route('delivery-driver.login'));
    }
}
