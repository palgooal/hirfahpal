<?php

namespace Tests\Feature;

use App\Models\DeliveryDriver;
use App\Models\Language;
use App\Models\TranslationValue;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\DeliveryDriverAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * The delivery driver password recovery pages in the vendor portal's design (auth/accounts/forgot-password and
 * reset-password), their Arabic and English texts (DeliveryDriverAuthTranslationSeeder, with the same texts as defaults
 * until it runs) and the unchanged recovery behind them (the delivery_drivers broker).
 */
class DeliveryDriverPasswordRecoveryPageTest extends TestCase
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

    // ---------------------------------------------------------------- forgot password

    public function test_the_forgot_page_is_arabic_rtl_with_the_driver_copy_and_switches_to_english(): void
    {
        $this->seed(DeliveryDriverAuthTranslationSeeder::class);

        $this->forgotPage()->assertOk()->assertViewIs('auth.accounts.forgot-password')
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('<title>نسيت كلمة المرور | بوابة مندوبي التوصيل في حرفة</title>', false)
            ->assertSeeInOrder(['بوابة مندوبي التوصيل', 'نسيت كلمة المرور؟', 'أدخل بريدك الإلكتروني، وسنرسل إليك رابطًا لإعادة تعيين كلمة المرور.'])
            ->assertSeeInOrder(['البريد الإلكتروني', 'إرسال رابط إعادة التعيين', 'تذكرت كلمة المرور؟', 'تسجيل الدخول'])
            ->assertSee('أوصل إبداعات حِرفة إلى أصحابها')
            ->assertSee('data-loading-label="جارٍ إرسال الرابط..."', false)
            ->assertSee('href="'.route('delivery-driver.password.request').'?change-locale=en"', false)
            ->assertDontSee('بوابة البائعين');

        $this->get(route('delivery-driver.password.request').'?change-locale=en')->assertRedirect(route('delivery-driver.password.request'));
        $this->forgotPage()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Delivery driver portal')
            ->assertSee('Enter your email and we&#039;ll send you a link to reset your password.', false)
            ->assertSee('Send reset link')
            ->assertSee('Remembered your password?')
            ->assertDontSee('بوابة مندوبي التوصيل');

        $this->get(route('delivery-driver.password.request').'?change-locale=ar')->assertRedirect(route('delivery-driver.password.request'));
        $this->forgotPage()->assertSee('<html lang="ar" dir="rtl">', false);
    }

    public function test_the_forgot_form_keeps_the_existing_contract(): void
    {
        $xpath = $this->xpath($this->forgotPage()->getContent());
        $form = $xpath->query('//form')->item(0);

        $this->assertSame(1, $xpath->query('//form')->length);
        $this->assertSame(['POST', route('delivery-driver.password.email')], [$form->getAttribute('method'), $form->getAttribute('action')]);
        $this->assertTrue($form->hasAttribute('data-submit-lock'));
        $this->assertSame(['_token', 'email'], array_map(fn ($input) => $input->getAttribute('name'), iterator_to_array($xpath->query('.//input[@name]', $form))));
        $email = $xpath->query('//input[@name="email"]')->item(0);
        $this->assertSame(['email', 'email', 'email', 'ltr'], [$email->getAttribute('id'), $email->getAttribute('type'), $email->getAttribute('autocomplete'), $email->getAttribute('dir')]);
        $this->assertTrue($email->hasAttribute('required'));
        $this->assertSame(1, $xpath->query('//label[@for="email"]')->length);
        $this->assertSame(route('delivery-driver.login'), $xpath->query('//section//a[last()]')->item(0)->getAttribute('href'));
        $this->assertSame(0, $xpath->query('//*[@role="alert"]')->length);
    }

    public function test_a_known_email_receives_the_link_and_the_page_confirms_it(): void
    {
        Notification::fake();
        $driver = DeliveryDriver::factory()->create(['email' => 'driver@example.com']);

        $this->from(route('delivery-driver.password.request'))
            ->post(route('delivery-driver.password.email'), ['email' => 'driver@example.com'])
            ->assertRedirect(route('delivery-driver.password.request'))
            ->assertSessionHas('status', __(Password::RESET_LINK_SENT));
        Notification::assertSentTo($driver, ResetPassword::class);

        $xpath = $this->xpath($this->forgotPage()->getContent());
        $status = $xpath->query('//div[@role="status"]')->item(0);
        $this->assertNotNull($status);
        $this->assertSame(__(Password::RESET_LINK_SENT), trim($status->textContent));
    }

    public function test_errors_show_under_the_email_and_a_validation_error_keeps_the_typed_email(): void
    {
        // A validation error flashes the input; the broker's refusal (unknown email) does not (the controller's
        // back()->withErrors(), unchanged), so that page shows the error with an empty field.
        foreach ([
            'invalid email' => ['not-an-email', 'not-an-email'],
            'unknown email' => ['nobody@example.com', ''],
        ] as $label => [$email, $refilled]) {
            $this->from(route('delivery-driver.password.request'))
                ->post(route('delivery-driver.password.email'), ['email' => $email])
                ->assertRedirect(route('delivery-driver.password.request'))
                ->assertSessionHasErrors('email');
            $message = session('errors')->first('email');

            $xpath = $this->xpath($this->forgotPage()->getContent());
            $error = $xpath->query('//p[@id="email-error"]')->item(0);
            $input = $xpath->query('//input[@name="email"]')->item(0);
            $this->assertSame([$message, 'alert'], [trim($error->textContent), $error->getAttribute('role')], $label);
            $this->assertSame(['true', 'email-error', $refilled], [$input->getAttribute('aria-invalid'), $input->getAttribute('aria-describedby'), $input->getAttribute('value')], $label);
            $this->assertSame(1, $xpath->query('//input[@name="email"]/ancestor::div[p[@id="email-error"]]')->length, "{$label}: under its field.");
        }
    }

    // ---------------------------------------------------------------- reset password

    public function test_the_reset_page_is_arabic_rtl_with_the_driver_copy_and_switches_to_english(): void
    {
        $this->seed(DeliveryDriverAuthTranslationSeeder::class);

        $this->resetPage('a-token')->assertOk()->assertViewIs('auth.accounts.reset-password')
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('<title>تعيين كلمة مرور جديدة | بوابة مندوبي التوصيل في حرفة</title>', false)
            ->assertSeeInOrder(['بوابة مندوبي التوصيل', 'تعيين كلمة مرور جديدة', 'أنشئ كلمة مرور جديدة لحساب مندوب التوصيل.'])
            ->assertSeeInOrder(['البريد الإلكتروني', 'كلمة المرور الجديدة', 'ثمانية أحرف على الأقل.', 'تأكيد كلمة المرور', 'حفظ كلمة المرور', 'العودة إلى تسجيل الدخول'])
            ->assertSee('data-loading-label="جارٍ حفظ كلمة المرور..."', false)
            ->assertDontSee('بوابة البائعين');

        $url = route('delivery-driver.password.reset', ['token' => 'a-token']);
        $this->get($url.'?change-locale=en')->assertRedirect($url);
        $this->get($url)
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Set a new password')
            ->assertSee('Create a new password for your delivery driver account.')
            ->assertSee('Save password')
            ->assertSee('Back to sign in')
            ->assertDontSee('تعيين كلمة مرور جديدة');
    }

    public function test_the_reset_form_keeps_the_token_the_email_and_the_existing_fields(): void
    {
        $xpath = $this->xpath($this->resetPage('the-token', 'driver@example.com')->getContent());
        $form = $xpath->query('//form')->item(0);

        $this->assertSame(['POST', route('delivery-driver.password.update')], [$form->getAttribute('method'), $form->getAttribute('action')]);
        $this->assertTrue($form->hasAttribute('data-submit-lock'));
        $this->assertSame(['_token', 'token', 'email', 'password', 'password_confirmation'], array_map(fn ($input) => $input->getAttribute('name'), iterator_to_array($xpath->query('.//input[@name]', $form))));
        $this->assertSame(['hidden', 'the-token'], [$xpath->query('//input[@name="token"]')->item(0)->getAttribute('type'), $xpath->query('//input[@name="token"]')->item(0)->getAttribute('value')]);
        $this->assertSame('driver@example.com', $xpath->query('//input[@name="email"]')->item(0)->getAttribute('value'));

        foreach (['email', 'password', 'password_confirmation'] as $field) {
            $this->assertSame(1, $xpath->query('//label[@for="'.$field.'"]')->length, $field);
            $this->assertTrue($xpath->query('//input[@name="'.$field.'"]')->item(0)->hasAttribute('required'), $field);
        }
        foreach (['password', 'password_confirmation'] as $field) {
            $toggle = $xpath->query('//button[@data-password-toggle and @aria-controls="'.$field.'"]')->item(0);
            $this->assertSame(['button', 'false', 'إظهار كلمة المرور'], [$toggle->getAttribute('type'), $toggle->getAttribute('aria-pressed'), $toggle->getAttribute('aria-label')], $field);
            $this->assertSame('new-password', $xpath->query('//input[@name="'.$field.'"]')->item(0)->getAttribute('autocomplete'));
        }
        $this->assertSame('password-hint', $xpath->query('//input[@name="password"]')->item(0)->getAttribute('aria-describedby'));
        $this->assertSame(route('delivery-driver.login'), $xpath->query('//section//a[last()]')->item(0)->getAttribute('href'));
        $this->assertSame(0, $xpath->query('//*[@role="alert"]')->length);
    }

    public function test_a_valid_token_resets_the_password_and_the_login_page_confirms_it(): void
    {
        $driver = DeliveryDriver::factory()->create(['email' => 'driver@example.com']);
        $token = Password::broker('delivery_drivers')->createToken($driver);

        $this->post(route('delivery-driver.password.update'), [
            'token' => $token,
            'email' => 'driver@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertRedirect(route('delivery-driver.login'))->assertSessionHas('status', __(Password::PASSWORD_RESET));

        $this->assertTrue(Hash::check('brand-new-password', $driver->fresh()->password));
        $this->assertGuest('delivery_driver');
        $this->get(route('delivery-driver.login'))->assertSee(__(Password::PASSWORD_RESET));
    }

    public function test_reset_errors_show_under_their_fields_and_the_passwords_are_never_refilled(): void
    {
        $driver = DeliveryDriver::factory()->create(['email' => 'driver@example.com']);
        $token = Password::broker('delivery_drivers')->createToken($driver);
        // The page as the reset link opens it: the email is in the query string, so it is shown again after any error.
        $page = route('delivery-driver.password.reset', ['token' => $token, 'email' => 'driver@example.com']);

        foreach ([
            'short and mismatched password' => [['token' => $token, 'password' => 'short12', 'password_confirmation' => 'other12'], 'password'],
            'invalid token' => [['token' => 'not-a-real-token', 'password' => 'brand-new-password', 'password_confirmation' => 'brand-new-password'], 'email'],
        ] as $label => [$input, $field]) {
            $this->from($page)->post(route('delivery-driver.password.update'), $input + ['email' => 'driver@example.com'])
                ->assertRedirect($page)
                ->assertSessionHasErrors($field)
                ->assertSessionMissing('_old_input.password')
                ->assertSessionMissing('_old_input.password_confirmation');
            $message = session('errors')->first($field);

            $html = $this->get($page)->assertOk()->getContent();
            $xpath = $this->xpath($html);
            $error = $xpath->query('//p[@id="'.$field.'-error"]')->item(0);
            $this->assertSame([$message, 'alert'], [trim($error->textContent), $error->getAttribute('role')], $label);
            $this->assertSame('true', $xpath->query('//input[@name="'.$field.'"]')->item(0)->getAttribute('aria-invalid'), $label);
            $this->assertSame(1, $xpath->query('//input[@name="'.$field.'"]/ancestor::div[p[@id="'.$field.'-error"]]')->length, "{$label}: under its field.");
            $this->assertSame('driver@example.com', $xpath->query('//input[@name="email"]')->item(0)->getAttribute('value'));
            foreach (['password', 'password_confirmation'] as $password) {
                $this->assertFalse($xpath->query('//input[@name="'.$password.'"]')->item(0)->hasAttribute('value'), "{$label}: {$password} is never refilled.");
            }
            $this->assertStringNotContainsString('brand-new-password', $html);
            $this->assertStringNotContainsString('short12', $html);
        }

        $this->assertTrue(Hash::check('password', $driver->fresh()->password), 'Nothing was reset.');
    }

    // ---------------------------------------------------------------- both pages

    public function test_both_pages_use_the_vendor_auth_design_without_the_old_blue_page(): void
    {
        foreach ([$this->forgotPage(), $this->resetPage('a-token')] as $response) {
            $response->assertOk()
                ->assertSee('resources/css/vendor-auth.css', false)
                ->assertSee('resources/js/admin-auth.js', false)
                ->assertSee('src="'.asset('images/brand/hirfah-logo.png').'"', false)
                ->assertDontSee('resources/css/app.css', false)
                ->assertDontSee('-blue-', false)
                ->assertDontSee('bg-slate-', false)
                ->assertDontSee('text-slate-', false)
                ->assertSee('<body class="min-h-screen overflow-x-hidden', false)
                ->assertSee('<div class="grid min-h-screen lg:grid-cols-2">', false)
                ->assertSee('class="relative hidden min-w-0 overflow-hidden bg-olive lg:flex', false)
                ->assertSee('<div class="mb-8 flex items-center justify-center gap-3 lg:hidden">', false);
        }

        $css = file_get_contents(resource_path('css/vendor-auth.css'));
        $this->assertStringContainsString('@source "../views/auth/accounts/forgot-password.blade.php";', $css);
        $this->assertStringContainsString('@source "../views/auth/accounts/reset-password.blade.php";', $css);
    }

    public function test_before_the_seeder_runs_each_language_shows_its_own_text_and_no_row_is_created(): void
    {
        $before = TranslationValue::count();

        $this->forgotPage()->assertSee('إرسال رابط إعادة التعيين')->assertDontSee('Send reset link');
        $this->resetPage('a-token')->assertSee('حفظ كلمة المرور')->assertDontSee('Save password');
        $this->withSession(['locale' => 'en'])->get(route('delivery-driver.password.request'))->assertSee('Send reset link')->assertDontSee('إرسال رابط إعادة التعيين');
        $this->withSession(['locale' => 'en'])->get(route('delivery-driver.password.reset', ['token' => 'a-token']))->assertSee('Save password');

        $this->assertSame($before, TranslationValue::count(), 'Viewing the pages creates no translation row.');
        $this->assertSame(0, TranslationValue::where('key', 'like', 'driver.%')->count());
    }

    public function test_visiting_the_seeded_pages_never_auto_creates_translation_rows(): void
    {
        $this->seed(DeliveryDriverAuthTranslationSeeder::class);
        TranslationValue::where('key', 'driver.Send_Reset_Link')->delete();
        TranslationValue::where('key', 'dashboard.Forgot_Password')->delete();
        $before = TranslationValue::count();

        $this->forgotPage()->assertOk();
        $this->resetPage('a-token')->assertOk();
        $this->withSession(['locale' => 'en'])->get(route('delivery-driver.password.request'))->assertOk();

        $this->assertSame($before, TranslationValue::count());
    }

    public function test_the_vendor_recovery_pages_and_the_driver_login_and_register_pages_are_unchanged(): void
    {
        $this->seed(VendorAuthTranslationSeeder::class);
        $this->seed(DeliveryDriverAuthTranslationSeeder::class);

        $this->get(route('vendor.password.request'))->assertOk()->assertViewIs('auth.vendor.forgot-password')
            ->assertSee('بوابة البائعين')->assertSee('method="POST" action="'.route('vendor.password.email').'"', false)->assertDontSee('بوابة مندوبي التوصيل');
        $this->get(route('vendor.password.reset', ['token' => 'a-token']))->assertOk()->assertViewIs('auth.vendor.reset-password')
            ->assertSee('بوابة البائعين')->assertDontSee('بوابة مندوبي التوصيل');

        $this->get(route('delivery-driver.login'))->assertOk()->assertViewIs('auth.accounts.login')->assertSee('مرحبًا بعودتك')->assertDontSee('إرسال رابط إعادة التعيين');
        $this->get(route('delivery-driver.register'))->assertOk()->assertViewIs('auth.accounts.register')->assertSee('إنشاء حساب مندوب توصيل')->assertDontSee('حفظ كلمة المرور');
    }

    private function forgotPage()
    {
        return $this->get(route('delivery-driver.password.request'));
    }

    private function resetPage(string $token, ?string $email = null)
    {
        return $this->get(route('delivery-driver.password.reset', ['token' => $token] + ($email ? ['email' => $email] : [])));
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }
}
