<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * CUSTOMER-AUTH-B2-01 / ADM-BE-001 (customers): the customer reset email opens the customer reset flow, and the
 * whole journey stays on the "customers" broker. Admin and delivery driver links are out of this scope.
 */
class CustomerPasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'shared@example.com';

    private const OLD_PASSWORD = 'original-password';

    private const NEW_PASSWORD = 'Brand-new-pass-1';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->withoutVite();
    }

    public function test_full_customer_reset_journey_from_request_to_login(): void
    {
        $customer = $this->customer();

        // 1. Request the link from the customer forgot-password page.
        $this->get(route('customer.password.request'))->assertOk()->assertViewIs('auth.accounts.forgot-password')
            ->assertSee('action="'.route('customer.password.email').'"', false);
        $this->from(route('customer.password.request'))
            ->post(route('customer.password.email'), ['email' => self::EMAIL])
            ->assertRedirect(route('customer.password.request'))
            ->assertSessionHas('status', __('passwords.sent'));

        // 2. The emailed link is the customer reset route, carrying token and email.
        [$token, $link] = $this->sentLink($customer);

        $this->assertSame(url(route('customer.password.reset', ['token' => $token, 'email' => self::EMAIL], false)), $link);
        $this->assertStringStartsWith(url('/customer/reset-password/'), $link);

        // 3. Opening the link reaches the customer reset form, which posts to the customer endpoint.
        $this->get($link)
            ->assertOk()
            ->assertViewIs('auth.accounts.reset-password')
            ->assertViewHas('account', fn (array $account) => $account['guard'] === 'customer')
            ->assertViewHas('token', $token)
            ->assertViewHas('email', self::EMAIL)
            ->assertSee('action="'.route('customer.password.update').'"', false);

        // 4. Submitting it resets the customer password and returns to customer.login.
        $this->post(route('customer.password.update'), $this->resetInput($token))
            ->assertRedirect(route('customer.login'))
            ->assertSessionHas('status', __('passwords.reset'));

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $customer->fresh()->password));
        $this->assertFalse(Password::broker('customers')->tokenExists($customer->fresh(), $token));

        // 5. The old password no longer works; the new one signs in on customer.login.
        $this->from(route('customer.login'))
            ->post(route('customer.login.store'), ['login' => self::EMAIL, 'password' => self::OLD_PASSWORD])
            ->assertSessionHasErrors(['login' => __('auth.failed')]);
        $this->assertGuest('customer');

        $this->post(route('customer.login.store'), ['login' => self::EMAIL, 'password' => self::NEW_PASSWORD])
            ->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($customer->fresh(), 'customer');
    }

    public function test_used_token_cannot_be_reused(): void
    {
        $customer = $this->customer();
        $token = $this->requestToken($customer);

        $this->post(route('customer.password.update'), $this->resetInput($token))->assertSessionHasNoErrors();

        $this->from(route('customer.password.reset', ['token' => $token]))
            ->post(route('customer.password.update'), $this->resetInput($token, 'Second-attempt-1'))
            ->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $customer->fresh()->password));
    }

    public function test_invalid_token_fails_safely(): void
    {
        $customer = $this->customer();
        $this->requestToken($customer);

        $this->from(route('customer.password.reset', ['token' => 'not-a-real-token']))
            ->post(route('customer.password.update'), $this->resetInput('not-a-real-token'))
            ->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $customer->fresh()->password));
    }

    public function test_valid_token_with_another_email_fails_safely(): void
    {
        $customer = $this->customer();
        $other = $this->customer('other@example.com', '0591000099');
        $token = $this->requestToken($customer);

        $this->from(route('customer.password.reset', ['token' => $token]))
            ->post(route('customer.password.update'), ['email' => $other->email] + $this->resetInput($token))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $customer->fresh()->password));
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $other->fresh()->password));
        $this->assertTrue(Password::broker('customers')->tokenExists($customer, $token));
    }

    public function test_customer_token_cannot_reset_other_account_types(): void
    {
        $customer = $this->customer();
        $others = [
            'admin.password.update' => Admin::create($this->accountData('0599000001')),
            'vendor.password.update' => Vendor::factory()->approved()->create($this->accountData('0591000002')),
            'delivery-driver.password.update' => DeliveryDriver::create($this->accountData('0591000003')),
        ];
        $token = $this->requestToken($customer);

        foreach ($others as $route => $account) {
            $this->from(route('customer.login'))
                ->post(route($route), $this->resetInput($token))
                ->assertSessionHasErrors(['email' => __('passwords.token')]);

            $this->assertTrue(Hash::check(self::OLD_PASSWORD, $account->fresh()->password), $route);
        }

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $customer->fresh()->password));
        $this->assertTrue(Password::broker('customers')->tokenExists($customer, $token));
    }

    public function test_customer_token_cannot_reset_a_web_user_through_the_general_route(): void
    {
        $customer = $this->customer();
        $user = User::create(['type' => 'user'] + $this->accountData('0591000004'));
        $token = $this->requestToken($customer);

        $this->from('/reset-password/'.$token)
            ->post(route('password.update'), $this->resetInput($token))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $customer->fresh()->password));
        $this->assertTrue(Password::broker('customers')->tokenExists($customer, $token));
    }

    public function test_other_account_tokens_cannot_reset_the_customer(): void
    {
        $customer = $this->customer();
        $others = [
            'admins' => Admin::create($this->accountData('0599000001')),
            'vendors' => Vendor::factory()->approved()->create($this->accountData('0591000002')),
            'delivery_drivers' => DeliveryDriver::create($this->accountData('0591000003')),
        ];

        foreach ($others as $broker => $account) {
            $this->assertSame(Password::RESET_LINK_SENT, Password::broker($broker)->sendResetLink(['email' => self::EMAIL]));
            [$foreignToken] = $this->sentLink($account);

            $this->from(route('customer.login'))
                ->post(route('customer.password.update'), $this->resetInput($foreignToken))
                ->assertSessionHasErrors(['email' => __('passwords.token')]);

            $this->assertTrue(Hash::check(self::OLD_PASSWORD, $customer->fresh()->password), $broker);
        }
    }

    public function test_the_customer_token_is_kept_in_the_customer_table_only(): void
    {
        $customer = $this->customer();
        $this->requestToken($customer);

        $this->assertDatabaseCount('customer_password_reset_tokens', 1);
        foreach (['password_reset_tokens', 'vendor_password_reset_tokens', 'admin_password_reset_tokens', 'delivery_driver_password_reset_tokens'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    private function requestToken(Customer $customer): string
    {
        $this->post(route('customer.password.email'), ['email' => $customer->email])
            ->assertSessionHas('status', __('passwords.sent'));

        return $this->sentLink($customer)[0];
    }

    /**
     * @return array{0: string, 1: string} token and the URL rendered in the email
     */
    private function sentLink($notifiable): array
    {
        $notification = Notification::sent($notifiable, ResetPassword::class)->last();

        $this->assertNotNull($notification);

        return [$notification->token, $notification->toMail($notifiable)->actionUrl];
    }

    private function resetInput(string $token, string $password = self::NEW_PASSWORD): array
    {
        return [
            'token' => $token,
            'email' => self::EMAIL,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }

    private function customer(string $email = self::EMAIL, string $phone = '0591000001'): Customer
    {
        return Customer::create(['email' => $email] + $this->accountData($phone));
    }

    private function accountData(string $phone): array
    {
        return [
            'name' => 'Account',
            'email' => self::EMAIL,
            'phone' => $phone,
            'password' => self::OLD_PASSWORD,
            'status' => 'active',
        ];
    }
}
