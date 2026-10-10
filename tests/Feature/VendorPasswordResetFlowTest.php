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
 * VEN-BE-002 / ARCH-03: the vendor reset email must open the vendor reset
 * flow, and the whole journey must stay on the "vendors" broker.
 */
class VendorPasswordResetFlowTest extends TestCase
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

    public function test_full_vendor_reset_journey_from_request_to_login(): void
    {
        $vendor = $this->vendor();

        // 1. Request the link from the vendor forgot-password page.
        $this->from(route('vendor.password.request'))
            ->post(route('vendor.password.email'), ['email' => self::EMAIL])
            ->assertRedirect(route('vendor.password.request'))
            ->assertSessionHas('status', __('passwords.sent'));

        // 2. The emailed link is the vendor reset route, carrying token and email.
        [$token, $link] = $this->sentLink($vendor);

        $this->assertSame(route('vendor.password.reset', ['token' => $token, 'email' => self::EMAIL]), $link);

        // 3. Opening the link reaches the vendor reset form, which posts to the vendor endpoint.
        $this->get($link)
            ->assertOk()
            ->assertViewIs('auth.vendor.reset-password')
            ->assertViewHas('account', fn (array $account) => $account['guard'] === 'vendor')
            ->assertViewHas('token', $token)
            ->assertViewHas('email', self::EMAIL)
            ->assertSee('action="'.route('vendor.password.update').'"', false);

        // 4. Submitting it resets the vendor password and returns to vendor.login.
        $this->post(route('vendor.password.update'), $this->resetInput($token))
            ->assertRedirect(route('vendor.login'))
            ->assertSessionHas('status', __('passwords.reset'));

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $vendor->fresh()->password));
        $this->assertFalse(Password::broker('vendors')->tokenExists($vendor->fresh(), $token));

        // 5. The old password no longer works; the new one signs in on vendor.login.
        $this->from(route('vendor.login'))
            ->post(route('vendor.login.store'), ['login' => self::EMAIL, 'password' => self::OLD_PASSWORD])
            ->assertSessionHasErrors(['login' => __('auth.failed')]);
        $this->assertGuest('vendor');

        $this->post(route('vendor.login.store'), ['login' => self::EMAIL, 'password' => self::NEW_PASSWORD])
            ->assertRedirect(route('vendor.dashboard'));
        $this->assertAuthenticatedAs($vendor->fresh(), 'vendor');
    }

    public function test_used_token_cannot_be_reused(): void
    {
        $vendor = $this->vendor();
        $token = $this->requestToken($vendor);

        $this->post(route('vendor.password.update'), $this->resetInput($token))->assertSessionHasNoErrors();

        $this->from(route('vendor.password.reset', ['token' => $token]))
            ->post(route('vendor.password.update'), $this->resetInput($token, 'Second-attempt-1'))
            ->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $vendor->fresh()->password));
    }

    public function test_invalid_token_fails_safely(): void
    {
        $vendor = $this->vendor();
        $this->requestToken($vendor);

        $this->from(route('vendor.password.reset', ['token' => 'not-a-real-token']))
            ->post(route('vendor.password.update'), $this->resetInput('not-a-real-token'))
            ->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $vendor->fresh()->password));
    }

    public function test_valid_token_with_another_email_fails_safely(): void
    {
        $vendor = $this->vendor();
        $other = $this->vendor('other@example.com', '0591000099');
        $token = $this->requestToken($vendor);

        $this->from(route('vendor.password.reset', ['token' => $token]))
            ->post(route('vendor.password.update'), ['email' => $other->email] + $this->resetInput($token))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $vendor->fresh()->password));
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $other->fresh()->password));
        $this->assertTrue(Password::broker('vendors')->tokenExists($vendor, $token));
    }

    public function test_vendor_token_cannot_reset_other_account_types(): void
    {
        $vendor = $this->vendor();
        $others = [
            'admin.password.update' => Admin::create($this->accountData('0599000001')),
            'customer.password.update' => Customer::create($this->accountData('0591000001')),
            'delivery-driver.password.update' => DeliveryDriver::create($this->accountData('0591000003')),
        ];
        $token = $this->requestToken($vendor);

        foreach ($others as $route => $account) {
            $this->from(route('vendor.login'))
                ->post(route($route), $this->resetInput($token))
                ->assertSessionHasErrors(['email' => __('passwords.token')]);

            $this->assertTrue(Hash::check(self::OLD_PASSWORD, $account->fresh()->password), $route);
        }

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $vendor->fresh()->password));
        $this->assertTrue(Password::broker('vendors')->tokenExists($vendor, $token));
    }

    public function test_vendor_token_cannot_reset_a_web_user_through_the_general_route(): void
    {
        $vendor = $this->vendor();
        $user = User::create(['type' => 'user'] + $this->accountData('0591000004'));
        $token = $this->requestToken($vendor);

        $this->from('/reset-password/'.$token)
            ->post(route('password.update'), $this->resetInput($token))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $vendor->fresh()->password));
    }

    public function test_other_account_tokens_cannot_reset_the_vendor(): void
    {
        $vendor = $this->vendor();
        $customer = Customer::create($this->accountData('0591000001'));
        $this->assertSame(Password::RESET_LINK_SENT, Password::broker('customers')->sendResetLink(['email' => self::EMAIL]));
        $customerToken = Notification::sent($customer, ResetPassword::class)->last()->token;

        $this->from(route('vendor.login'))
            ->post(route('vendor.password.update'), $this->resetInput($customerToken))
            ->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $vendor->fresh()->password));
    }

    public function test_other_account_types_keep_their_current_reset_link(): void
    {
        $accounts = [
            'admins' => Admin::create($this->accountData('0599000001')),
            'customers' => Customer::create($this->accountData('0591000001')),
            'delivery_drivers' => DeliveryDriver::create($this->accountData('0591000003')),
        ];

        foreach ($accounts as $broker => $account) {
            $this->assertSame(Password::RESET_LINK_SENT, Password::broker($broker)->sendResetLink(['email' => self::EMAIL]));
            [$token, $link] = $this->sentLink($account);

            $this->assertSame(url(route('password.reset', ['token' => $token, 'email' => self::EMAIL], false)), $link, $broker);
        }
    }

    private function requestToken(Vendor $vendor): string
    {
        $this->post(route('vendor.password.email'), ['email' => $vendor->email])
            ->assertSessionHas('status', __('passwords.sent'));

        return $this->sentLink($vendor)[0];
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

    private function vendor(string $email = self::EMAIL, string $phone = '0591000002'): Vendor
    {
        return Vendor::factory()->approved()->create(['email' => $email] + $this->accountData($phone));
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
