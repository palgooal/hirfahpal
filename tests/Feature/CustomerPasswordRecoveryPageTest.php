<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\TranslationValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * CUSTOMER-AUTH-B2-02: the customer forgot-password and reset-password pages use the storefront design of the
 * customer login page, keep the existing form contract (CSRF, field names, token, endpoints) and show the
 * broker's and the validation's errors. Delivery drivers keep the shared auth.accounts.* pages.
 */
class CustomerPasswordRecoveryPageTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'customer@example.com';

    private const TOKEN = 'reset-token-from-the-email';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->withoutVite();
    }

    // ---------------------------------------------------------------- forgot password

    public function test_forgot_page_is_the_storefront_page_in_arabic(): void
    {
        $html = $this->get(route('customer.password.request'))
            ->assertOk()
            ->assertViewIs('pages.customer-forgot-password')
            ->getContent();

        $this->assertMatchesRegularExpression('#<html lang="ar" dir="rtl"#', $html);
        $this->assertStringContainsString('<title>استعادة كلمة المرور | حرفة</title>', $html);
        $this->assertStringContainsString('<header class="relative z-50 bg-surface"', $html, 'The storefront layout.');
        $this->assertStringContainsString('data-auth-page', $html);
        foreach (['نسيت كلمة المرور؟', 'أدخل بريدك الإلكتروني المسجّل', 'البريد الإلكتروني', 'إرسال رابط الاستعادة', 'العودة إلى تسجيل الدخول'] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
        foreach (['Reset Password', 'Send reset link', 'bg-blue-600', 'bg-slate-50'] as $old) {
            $this->assertStringNotContainsString($old, $html, $old);
        }
    }

    public function test_forgot_form_keeps_its_contract(): void
    {
        $html = $this->get(route('customer.password.request'))->getContent();

        $this->assertStringContainsString('method="POST" action="'.route('customer.password.email').'"', $html);
        $this->assertMatchesRegularExpression('#<input type="hidden" name="_token" value="[^"]+"#', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('href="'.route('customer.login').'"', $html);
    }

    public function test_forgot_page_shows_the_sent_message(): void
    {
        $this->customer();

        $this->from(route('customer.password.request'))->followingRedirects()
            ->post(route('customer.password.email'), ['email' => self::EMAIL])
            ->assertOk()
            ->assertViewIs('pages.customer-forgot-password')
            ->assertSee(__('passwords.sent'));
    }

    public function test_forgot_page_shows_the_email_errors(): void
    {
        // A broker error (unknown email): the controller returns errors only, without the input (unchanged here).
        $this->from(route('customer.password.request'))->followingRedirects()
            ->post(route('customer.password.email'), ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertSee(__('passwords.user'));

        // A validation error keeps the input.
        $this->from(route('customer.password.request'))->followingRedirects()
            ->post(route('customer.password.email'), ['email' => 'not-an-email'])
            ->assertOk()
            ->assertSee(__('validation.email', ['attribute' => __('validation.attributes.email')]))
            ->assertSee('value="not-an-email"', false);
    }

    // ---------------------------------------------------------------- reset password

    public function test_reset_page_is_the_storefront_page_in_arabic(): void
    {
        $html = $this->resetPage()->assertOk()->assertViewIs('pages.customer-reset-password')->getContent();

        $this->assertMatchesRegularExpression('#<html lang="ar" dir="rtl"#', $html);
        $this->assertStringContainsString('<title>تعيين كلمة مرور جديدة | حرفة</title>', $html);
        $this->assertStringContainsString('<header class="relative z-50 bg-surface"', $html);
        foreach (['تعيين كلمة مرور جديدة', 'البريد الإلكتروني', 'كلمة المرور الجديدة', 'تأكيد كلمة المرور الجديدة', 'حفظ كلمة المرور', 'العودة إلى تسجيل الدخول'] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
        foreach (['Set New Password', 'Save password', 'bg-blue-600'] as $old) {
            $this->assertStringNotContainsString($old, $html, $old);
        }
        $this->assertStringNotContainsString('طلب رابط جديد', $html, 'Only after a token error.');
    }

    public function test_reset_form_keeps_its_contract(): void
    {
        $html = $this->resetPage()->getContent();

        $this->assertStringContainsString('method="POST" action="'.route('customer.password.update').'"', $html);
        $this->assertMatchesRegularExpression('#<input type="hidden" name="_token" value="[^"]+"#', $html);
        $this->assertStringContainsString('<input type="hidden" name="token" value="'.self::TOKEN.'">', $html);
        $this->assertMatchesRegularExpression('#<input type="email" name="email" value="'.preg_quote(self::EMAIL, '#').'"#', $html, 'The email from the link, editable.');
        $this->assertSame(1, substr_count($html, 'name="password"'));
        $this->assertSame(1, substr_count($html, 'name="password_confirmation"'));
        // The current storefront-login.js toggles .password-input inside [data-auth-page].
        $this->assertSame(2, substr_count($html, 'data-toggle-password'));
        $this->assertSame(2, substr_count($html, 'class="password-input'));
        $this->assertStringContainsString('data-auth-page', $html);
        $this->assertStringContainsString('href="'.route('customer.login').'"', $html);
    }

    public function test_a_bad_token_is_shown_with_a_link_to_request_a_new_one(): void
    {
        $this->customer();

        $html = $this->from($this->resetUrl())->followingRedirects()
            ->post(route('customer.password.update'), $this->resetInput('not-a-real-token'))
            ->assertOk()
            ->assertViewIs('pages.customer-reset-password')
            ->getContent();

        $this->assertStringContainsString(__('passwords.token'), $html);
        $this->assertStringContainsString('href="'.route('customer.password.request').'"', $html);
        $this->assertStringContainsString('طلب رابط جديد', $html);
    }

    public function test_password_errors_are_shown(): void
    {
        $this->customer();

        $mismatch = $this->from($this->resetUrl())->followingRedirects()
            ->post(route('customer.password.update'), ['password_confirmation' => 'Something-else-1'] + $this->resetInput(self::TOKEN))
            ->assertOk()->getContent();
        $this->assertStringContainsString(__('validation.confirmed', ['attribute' => __('validation.attributes.password')]), $mismatch);
        $this->assertStringNotContainsString('طلب رابط جديد', $mismatch);

        $short = $this->from($this->resetUrl())->followingRedirects()
            ->post(route('customer.password.update'), $this->resetInput(self::TOKEN, 'short'))
            ->assertOk()->getContent();
        $this->assertStringContainsString(__('validation.min.string', ['attribute' => __('validation.attributes.password'), 'min' => 8]), $short);
    }

    public function test_the_email_is_escaped(): void
    {
        $payload = '"><script>alert(1)</script>';

        $reset = $this->get(route('customer.password.reset', ['token' => self::TOKEN, 'email' => $payload]))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $reset);
        $this->assertStringContainsString('value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $reset);

        $forgot = $this->from(route('customer.password.request'))->followingRedirects()
            ->post(route('customer.password.email'), ['email' => $payload])->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $forgot);
    }

    // ---------------------------------------------------------------- scope

    public function test_rendering_the_pages_writes_no_translation_row(): void
    {
        $before = TranslationValue::count();

        $this->get(route('customer.password.request'))->assertOk();
        $this->resetPage()->assertOk();

        $this->assertSame($before, TranslationValue::count());
    }

    public function test_delivery_drivers_and_vendors_keep_their_own_pages(): void
    {
        $this->get(route('delivery-driver.password.request'))->assertOk()->assertViewIs('auth.accounts.forgot-password');
        $this->get(route('delivery-driver.password.reset', ['token' => self::TOKEN]))->assertOk()->assertViewIs('auth.accounts.reset-password');
        $this->get(route('vendor.password.request'))->assertOk()->assertViewIs('auth.vendor.forgot-password');
        $this->get(route('vendor.password.reset', ['token' => self::TOKEN]))->assertOk()->assertViewIs('auth.vendor.reset-password');
    }

    // ---------------------------------------------------------------- helpers

    private function resetUrl(): string
    {
        return route('customer.password.reset', ['token' => self::TOKEN, 'email' => self::EMAIL]);
    }

    private function resetPage()
    {
        return $this->get($this->resetUrl());
    }

    private function resetInput(string $token, string $password = 'Brand-new-pass-1'): array
    {
        return ['token' => $token, 'email' => self::EMAIL, 'password' => $password, 'password_confirmation' => $password];
    }

    private function customer(): Customer
    {
        return Customer::create(['name' => 'Customer', 'email' => self::EMAIL, 'phone' => '0591000001', 'password' => 'original-password', 'status' => 'active']);
    }
}
