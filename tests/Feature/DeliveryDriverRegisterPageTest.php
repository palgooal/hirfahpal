<?php

namespace Tests\Feature;

use App\Models\DeliveryDriver;
use App\Models\Language;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\DeliveryDriverAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The delivery driver registration page in the vendor portal's design (auth/accounts/register), its Arabic and English
 * texts (DeliveryDriverAuthTranslationSeeder, with the same texts as defaults until it runs) and the unchanged
 * registration behind it (CreateAccountUser: an active driver with a pending profile, signed in).
 */
class DeliveryDriverRegisterPageTest extends TestCase
{
    use RefreshDatabase;

    private const FIELDS = ['full_name', 'phone', 'email', 'password', 'password_confirmation', 'terms'];

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

    public function test_the_page_loads_in_arabic_rtl_with_the_driver_copy(): void
    {
        $this->seed(DeliveryDriverAuthTranslationSeeder::class);

        $this->page()->assertOk()->assertViewIs('auth.accounts.register')
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('<title>إنشاء حساب مندوب توصيل | حرفة</title>', false)
            ->assertSeeInOrder(['بوابة مندوبي التوصيل', 'إنشاء حساب مندوب توصيل', 'انضم إلى فريق توصيل حِرفة، وساهم في إيصال إبداعات الحرفيين إلى أصحابها.'])
            ->assertSeeInOrder(['سيخضع حسابك للمراجعة قبل تفعيل خدمات التوصيل.', 'جميع الحقول مطلوبة.'])
            ->assertSeeInOrder(['الاسم الكامل', 'رقم الجوال', 'البريد الإلكتروني', 'كلمة المرور', 'ثمانية أحرف على الأقل.', 'تأكيد كلمة المرور', 'أوافق على الشروط والأحكام وسياسة الخصوصية.', 'إنشاء حساب'])
            ->assertSeeInOrder(['لديك حساب بالفعل؟', 'تسجيل الدخول'])
            ->assertSee('كن حلقة الوصل بين إبداعات الحرفيين وأصحابها.')
            ->assertSee('data-loading-label="جارٍ إنشاء الحساب..."', false)
            ->assertDontSee('بوابة البائعين');
    }

    public function test_switching_to_english_renders_ltr_and_back(): void
    {
        $this->seed(DeliveryDriverAuthTranslationSeeder::class);

        $this->get(route('delivery-driver.register').'?change-locale=en')->assertRedirect(route('delivery-driver.register'));
        $this->page()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Create a delivery driver account')
            ->assertSee('Your account will be reviewed before delivery services are activated.')
            ->assertSee('Already have an account?')
            ->assertSee('Create account')
            ->assertDontSee('إنشاء حساب مندوب توصيل');

        $this->get(route('delivery-driver.register').'?change-locale=ar')->assertRedirect(route('delivery-driver.register'));
        $this->page()->assertSee('<html lang="ar" dir="rtl">', false)->assertSee('إنشاء حساب مندوب توصيل');
    }

    public function test_the_language_switcher_links_to_this_page(): void
    {
        $this->page()
            ->assertSee('aria-current="true"'."\n".'                            lang="ar"', false)
            ->assertSee('href="'.route('delivery-driver.register').'?change-locale=en"', false);
    }

    public function test_before_the_seeder_runs_each_language_shows_its_own_text_and_no_row_is_created(): void
    {
        $before = TranslationValue::count();

        $this->page()->assertOk()->assertSee('إنشاء حساب مندوب توصيل')->assertSee('سيخضع حسابك للمراجعة قبل تفعيل خدمات التوصيل.')->assertDontSee('Create a delivery driver account');
        $this->withSession(['locale' => 'en'])->page()->assertOk()->assertSee('Create a delivery driver account')->assertDontSee('إنشاء حساب مندوب توصيل');

        $this->assertSame($before, TranslationValue::count(), 'Viewing the page creates no translation row.');
        $this->assertSame(0, TranslationValue::where('key', 'like', 'driver.%')->count());
    }

    public function test_the_vendor_auth_design_is_used_without_the_old_blue_page(): void
    {
        $this->page()
            ->assertSee('resources/css/vendor-auth.css', false)
            ->assertSee('resources/js/admin-auth.js', false)
            ->assertSee('src="'.asset('images/brand/hirfah-logo.png').'"', false)
            ->assertDontSee('resources/css/app.css', false)
            ->assertDontSee('-blue-', false)
            ->assertDontSee('bg-slate-', false)
            ->assertDontSee('text-slate-', false)
            ->assertSee('<div class="grid min-h-screen lg:grid-cols-2">', false)
            ->assertSee('<body class="min-h-screen overflow-x-hidden', false)
            ->assertSee('class="relative hidden min-w-0 overflow-hidden bg-olive lg:flex', false)
            ->assertSee('<div class="mb-8 flex items-center justify-center gap-3 lg:hidden">', false);

        $this->assertStringContainsString('@source "../views/auth/accounts/register.blade.php";', file_get_contents(resource_path('css/vendor-auth.css')));
    }

    public function test_the_form_posts_the_existing_fields_to_the_driver_route(): void
    {
        $xpath = $this->xpath($this->page()->getContent());
        $form = $xpath->query('//form')->item(0);

        $this->assertSame(1, $xpath->query('//form')->length);
        $this->assertSame(['POST', route('delivery-driver.register.store')], [$form->getAttribute('method'), $form->getAttribute('action')]);
        $this->assertTrue($form->hasAttribute('data-submit-lock'));
        $this->assertSame(1, $xpath->query('.//input[@name="_token"]', $form)->length);

        $names = [];
        foreach ($xpath->query('.//input[@name and @name!="_token"]', $form) as $input) {
            $names[] = $input->getAttribute('name');
            $this->assertTrue($input->hasAttribute('required'), $input->getAttribute('name').' is required.');
            // Every field has a label pointing to it.
            $this->assertSame(1, $xpath->query('//label[@for="'.$input->getAttribute('id').'"]')->length, $input->getAttribute('name'));
            $this->assertSame($input->getAttribute('name'), $input->getAttribute('id'));
        }
        $this->assertSame(self::FIELDS, $names);
        $this->assertSame(['password', 'password'], [$xpath->query('//input[@name="password"]')->item(0)->getAttribute('type'), $xpath->query('//input[@name="password_confirmation"]')->item(0)->getAttribute('type')]);
        $this->assertSame('1', $xpath->query('//input[@name="terms"]')->item(0)->getAttribute('value'));

        // The visual required marks are hidden from screen readers.
        $this->assertSame(7, $xpath->query('//form//span[@aria-hidden="true" and contains(@class, "text-error")]')->length);
    }

    public function test_both_password_fields_have_an_accessible_toggle(): void
    {
        $xpath = $this->xpath($this->page()->getContent());

        foreach (['password', 'password_confirmation'] as $field) {
            $toggle = $xpath->query('//button[@data-password-toggle and @aria-controls="'.$field.'"]')->item(0);
            $this->assertNotNull($toggle, $field);
            $this->assertSame(['button', 'false', 'إظهار كلمة المرور', 'إخفاء كلمة المرور'], [
                $toggle->getAttribute('type'), $toggle->getAttribute('aria-pressed'), $toggle->getAttribute('aria-label'), $toggle->getAttribute('data-label-hide'),
            ]);
        }
        $this->assertSame('password-hint', $xpath->query('//input[@name="password"]')->item(0)->getAttribute('aria-describedby'));
    }

    public function test_errors_show_under_their_fields_and_the_input_is_kept_except_the_passwords(): void
    {
        Vendor::factory()->create(['email' => 'taken@example.com']);
        $input = ['full_name' => 'سائق جديد', 'phone' => '0591234567', 'email' => 'taken@example.com', 'password' => 'short12', 'password_confirmation' => 'other12'];

        $this->from(route('delivery-driver.register'))->post(route('delivery-driver.register.store'), $input)
            ->assertRedirect(route('delivery-driver.register'))
            ->assertSessionHasErrors(['email', 'password', 'terms'])
            ->assertSessionDoesntHaveErrors(['full_name', 'phone'])
            ->assertSessionHasInput(['full_name' => 'سائق جديد', 'phone' => '0591234567', 'email' => 'taken@example.com'])
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');
        $errors = session('errors')->getBag('default');
        $this->assertSame(0, DeliveryDriver::count());
        $this->assertGuest('delivery_driver');

        $html = $this->page()->assertOk()->getContent();
        $xpath = $this->xpath($html);
        foreach (['email', 'password', 'terms'] as $field) {
            $error = $xpath->query('//p[@id="'.$field.'-error"]')->item(0);
            $this->assertNotNull($error, $field);
            $this->assertSame([$errors->first($field), 'alert'], [trim($error->textContent), $error->getAttribute('role')], $field);
            $this->assertSame('true', $xpath->query('//input[@name="'.$field.'"]')->item(0)->getAttribute('aria-invalid'), $field);
            // Right under its own field: the error follows the field inside the same block.
            $this->assertSame(1, $xpath->query('//input[@name="'.$field.'"]/ancestor::div[p[@id="'.$field.'-error"]]')->length, $field);
        }
        $this->assertSame('password-hint password-error', $xpath->query('//input[@name="password"]')->item(0)->getAttribute('aria-describedby'));
        foreach (['full_name', 'phone'] as $field) {
            $this->assertSame(0, $xpath->query('//p[@id="'.$field.'-error"]')->length, $field);
            $this->assertFalse($xpath->query('//input[@name="'.$field.'"]')->item(0)->hasAttribute('aria-invalid'), $field);
        }

        $this->assertSame(['سائق جديد', '0591234567', 'taken@example.com'], array_map(fn ($name) => $xpath->query('//input[@name="'.$name.'"]')->item(0)->getAttribute('value'), ['full_name', 'phone', 'email']));
        foreach (['password', 'password_confirmation'] as $field) {
            $this->assertFalse($xpath->query('//input[@name="'.$field.'"]')->item(0)->hasAttribute('value'), "{$field} is never refilled.");
        }
        $this->assertStringNotContainsString('short12', $html);
        $this->assertStringNotContainsString('other12', $html);
    }

    public function test_no_error_markup_without_errors(): void
    {
        $this->page()->assertDontSee('role="alert"', false)->assertDontSee('aria-invalid', false);
    }

    public function test_a_checked_terms_box_is_kept_after_another_error(): void
    {
        $this->from(route('delivery-driver.register'))->post(route('delivery-driver.register.store'), ['full_name' => '', 'terms' => '1'])->assertSessionHasErrors('full_name');

        $this->assertTrue($this->xpath($this->page()->getContent())->query('//input[@name="terms"]')->item(0)->hasAttribute('checked'));
    }

    public function test_the_sign_in_link_returns_to_the_driver_login_page(): void
    {
        $this->page()
            ->assertSee('href="'.route('delivery-driver.login').'"', false)
            ->assertDontSee(route('vendor.login'), false)
            ->assertDontSee(route('vendor.register.store'), false);
    }

    public function test_registration_still_creates_an_active_driver_with_a_pending_profile_and_signs_in(): void
    {
        $this->post(route('delivery-driver.register.store'), [
            'full_name' => 'Driver One',
            'phone' => '0591112223',
            'email' => 'driver-one@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'terms' => '1',
        ])->assertRedirect(route('delivery-driver.dashboard'));

        $driver = DeliveryDriver::where('email', 'driver-one@example.com')->sole();
        $this->assertSame(['Driver One', '0591112223', 'active'], [$driver->name, $driver->phone, $driver->status]);
        $this->assertSame('pending', $driver->profile->approval_status, 'The account is reviewed, as the page says.');
        $this->assertAuthenticatedAs($driver, 'delivery_driver');
    }

    public function test_visiting_the_page_never_auto_creates_translation_rows(): void
    {
        $this->seed(DeliveryDriverAuthTranslationSeeder::class);
        TranslationValue::where('key', 'driver.Review_Notice')->delete();
        TranslationValue::where('key', 'dashboard.Show_Password')->delete();
        $before = TranslationValue::count();

        $this->page()->assertOk();
        $this->withSession(['locale' => 'en'])->page()->assertOk();

        $this->assertSame($before, TranslationValue::count());
    }

    public function test_the_driver_login_page_and_the_vendor_pages_are_unchanged(): void
    {
        $this->seed(VendorAuthTranslationSeeder::class);
        $this->seed(DeliveryDriverAuthTranslationSeeder::class);

        $this->get(route('delivery-driver.login'))->assertOk()->assertViewIs('auth.accounts.login')
            ->assertSee('مرحبًا بعودتك')
            ->assertSee('أوصل إبداعات حِرفة إلى أصحابها')
            ->assertDontSee('إنشاء حساب مندوب توصيل');

        $this->get(route('vendor.register'))->assertOk()->assertViewIs('auth.vendor.register')
            ->assertSee('بوابة البائعين')
            ->assertSee('method="POST" action="'.route('vendor.register.store').'"', false)
            ->assertDontSee('بوابة مندوبي التوصيل');
        $this->get(route('vendor.login'))->assertOk()->assertViewIs('auth.vendor.login')->assertDontSee('بوابة مندوبي التوصيل');
    }

    public function test_the_seeder_covers_the_driver_auth_pages_exactly(): void
    {
        $used = [];
        foreach (['login', 'register', 'forgot-password', 'reset-password'] as $page) {
            preg_match_all("/\\\$driverText\('([A-Za-z_.]+)'\)/", file_get_contents(resource_path("views/auth/accounts/{$page}.blade.php")), $matches);
            $used = [...$used, ...$matches[1]];
        }
        $used = array_values(array_unique($used));
        sort($used);
        $seeded = array_keys(DeliveryDriverAuthTranslationSeeder::TRANSLATIONS);
        sort($seeded);

        $this->assertSame($seeded, $used, 'Every seeded key is read by a driver page, and every key read is seeded.');
        // 7 login, 15 registration and 12 password recovery keys.
        $this->assertCount(34, $seeded);

        // The approved login and registration values are unchanged.
        $this->assertSame(['ar' => 'إنشاء حساب مندوب توصيل', 'en' => 'Create a delivery driver account'], DeliveryDriverAuthTranslationSeeder::TRANSLATIONS['driver.Register_Heading']);
        $this->assertSame(['ar' => 'سيخضع حسابك للمراجعة قبل تفعيل خدمات التوصيل.', 'en' => 'Your account will be reviewed before delivery services are activated.'], DeliveryDriverAuthTranslationSeeder::TRANSLATIONS['driver.Review_Notice']);
        $this->assertSame(['ar' => 'بوابة مندوبي التوصيل', 'en' => 'Delivery driver portal'], DeliveryDriverAuthTranslationSeeder::TRANSLATIONS['driver.Portal']);
        $this->assertSame(['ar' => 'أوصل إبداعات حِرفة إلى أصحابها', 'en' => 'Deliver Hirfah’s crafts to the people waiting for them'], DeliveryDriverAuthTranslationSeeder::TRANSLATIONS['driver.Login_Panel_Line']);
    }

    private function page(): TestResponse
    {
        return $this->get(route('delivery-driver.register'));
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
