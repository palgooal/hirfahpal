<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\Vendor;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * ADM-BE-001 (fixed for delivery drivers): the emailed reset link must open the driver reset page, carrying the
 * token and email of the delivery_drivers broker, while vendor and customer links keep their own routes.
 */
class DeliveryDriverPasswordResetLinkTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'driver@example.com';

    private const NEW_PASSWORD = 'Brand-new-pass-1';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->withoutVite();
    }

    public function test_the_driver_email_links_to_the_driver_reset_route_with_its_token_and_email(): void
    {
        $driver = DeliveryDriver::factory()->create(['email' => self::EMAIL]);

        $this->post(route('delivery-driver.password.email'), ['email' => self::EMAIL])
            ->assertSessionHas('status', __(Password::RESET_LINK_SENT));

        [$token, $link] = $this->sentLink($driver);

        $this->assertSame(url(route('delivery-driver.password.reset', ['token' => $token, 'email' => self::EMAIL], false)), $link);
        $this->assertSame('/delivery-driver/reset-password/'.$token, parse_url($link, PHP_URL_PATH));
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        $this->assertSame(['email' => self::EMAIL], $query);
        $this->assertTrue(Password::broker('delivery_drivers')->tokenExists($driver, $token));
    }

    public function test_the_emailed_driver_link_opens_the_driver_page_and_its_token_resets_the_password(): void
    {
        $driver = DeliveryDriver::factory()->create(['email' => self::EMAIL]);
        $this->post(route('delivery-driver.password.email'), ['email' => self::EMAIL]);
        [$token, $link] = $this->sentLink($driver);

        $this->get($link)
            ->assertOk()
            ->assertViewIs('auth.accounts.reset-password')
            ->assertViewHas('account', fn (array $account) => $account['guard'] === 'delivery_driver')
            ->assertViewHas('token', $token)
            ->assertViewHas('email', self::EMAIL);

        $this->from($link)
            ->post(route('delivery-driver.password.update'), [
                'token' => $token,
                'email' => self::EMAIL,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('delivery-driver.login'))
            ->assertSessionHas('status', __(Password::PASSWORD_RESET));

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $driver->fresh()->password));
        $this->assertFalse(Password::broker('delivery_drivers')->tokenExists($driver->fresh(), $token));
    }

    public function test_vendor_and_customer_links_keep_their_own_reset_routes(): void
    {
        $vendor = Vendor::factory()->create(['email' => 'vendor@example.com']);
        $customer = Customer::factory()->create(['email' => 'customer@example.com']);

        $this->assertSame(Password::RESET_LINK_SENT, Password::broker('vendors')->sendResetLink(['email' => 'vendor@example.com']));
        $this->assertSame(Password::RESET_LINK_SENT, Password::broker('customers')->sendResetLink(['email' => 'customer@example.com']));
        [$vendorToken, $vendorLink] = $this->sentLink($vendor);
        [$customerToken, $customerLink] = $this->sentLink($customer);

        $this->assertSame(url(route('vendor.password.reset', ['token' => $vendorToken, 'email' => 'vendor@example.com'], false)), $vendorLink);
        $this->assertSame(url(route('customer.password.reset', ['token' => $customerToken, 'email' => 'customer@example.com'], false)), $customerLink);
        $this->assertStringNotContainsString('/delivery-driver/', $vendorLink.$customerLink);
    }

    /**
     * @return array{0: string, 1: string} the token and the URL rendered in the email
     */
    private function sentLink($notifiable): array
    {
        $notification = Notification::sent($notifiable, ResetPassword::class)->last();

        $this->assertNotNull($notification);

        return [$notification->token, $notification->toMail($notifiable)->actionUrl];
    }
}
