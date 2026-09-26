<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Tokens are issued through the brokers and captured from faked notifications;
 * resets go through each type's own endpoint because the emailed link is ARCH-03.
 */
class PasswordResetTokenIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'shared@example.com';

    private const ORIGINAL_PASSWORD = 'original-password';

    /**
     * type => [model, broker, token table, reset POST route]
     */
    private const TYPES = [
        'admin' => [Admin::class, 'admins', 'admin_password_reset_tokens', 'admin.password.update'],
        'customer' => [Customer::class, 'customers', 'customer_password_reset_tokens', 'customer.password.update'],
        'vendor' => [Vendor::class, 'vendors', 'vendor_password_reset_tokens', 'vendor.password.update'],
        'delivery_driver' => [DeliveryDriver::class, 'delivery_drivers', 'delivery_driver_password_reset_tokens', 'delivery-driver.password.update'],
    ];

    private const LEGACY_TABLE = 'password_reset_tokens';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->freezeTime();
    }

    public function test_admin_token_is_stored_only_in_the_admin_table(): void
    {
        $this->assertTokenStoredOnlyInOwnTable('admin');
    }

    public function test_customer_token_is_stored_only_in_the_customer_table(): void
    {
        $this->assertTokenStoredOnlyInOwnTable('customer');
    }

    public function test_vendor_token_is_stored_only_in_the_vendor_table(): void
    {
        $this->assertTokenStoredOnlyInOwnTable('vendor');
    }

    public function test_delivery_driver_token_is_stored_only_in_the_delivery_driver_table(): void
    {
        $this->assertTokenStoredOnlyInOwnTable('delivery_driver');
    }

    public function test_legacy_users_broker_still_uses_the_shared_table(): void
    {
        $user = $this->legacyUser();

        $this->assertSame(self::LEGACY_TABLE, config('auth.passwords.users.table'));

        $token = $this->issue('users', $user);

        $this->assertSame(1, $this->rows(self::LEGACY_TABLE));
        foreach (self::TYPES as [, , $table]) {
            $this->assertSame(0, $this->rows($table));
        }
        $this->assertTrue(Password::broker('users')->tokenExists($user, $token));
    }

    public function test_custom_brokers_no_longer_use_the_shared_table(): void
    {
        foreach (self::TYPES as [, $broker, $table]) {
            $this->assertSame($table, config("auth.passwords.{$broker}.table"));
            $this->assertNotSame(self::LEGACY_TABLE, config("auth.passwords.{$broker}.table"));
        }
    }

    public function test_same_email_can_exist_in_customer_and_vendor(): void
    {
        $this->account('customer');
        $this->account('vendor');

        $this->assertSame(1, Customer::where('email', self::EMAIL)->count());
        $this->assertSame(1, Vendor::where('email', self::EMAIL)->count());
    }

    public function test_same_email_can_exist_in_admin_and_customer(): void
    {
        $this->account('admin');
        $this->account('customer');

        $this->assertSame(1, Admin::where('email', self::EMAIL)->count());
        $this->assertSame(1, Customer::where('email', self::EMAIL)->count());
    }

    public function test_customer_request_does_not_delete_the_vendor_token(): void
    {
        $vendor = $this->account('vendor');
        $customer = $this->account('customer');

        $vendorToken = $this->issue('vendors', $vendor);
        $this->issue('customers', $customer);

        $this->assertResetSucceeds($this->reset('vendor', $vendorToken, 'Vendor-new-pass-1'), 'vendor');
        $this->assertTrue(Hash::check('Vendor-new-pass-1', $vendor->fresh()->password));
    }

    public function test_vendor_request_does_not_delete_the_customer_token(): void
    {
        $customer = $this->account('customer');
        $vendor = $this->account('vendor');

        $customerToken = $this->issue('customers', $customer);
        $this->issue('vendors', $vendor);

        $this->assertResetSucceeds($this->reset('customer', $customerToken, 'Customer-new-pass-1'), 'customer');
        $this->assertTrue(Hash::check('Customer-new-pass-1', $customer->fresh()->password));
    }

    public function test_customer_request_does_not_trigger_the_vendor_broker_throttle(): void
    {
        $customer = $this->account('customer');
        $this->account('vendor');

        $this->issue('customers', $customer);

        $this->assertSame(Password::RESET_LINK_SENT, Password::broker('vendors')->sendResetLink(['email' => self::EMAIL]));
    }

    public function test_admin_request_does_not_trigger_the_customer_broker_throttle(): void
    {
        $admin = $this->account('admin');
        $this->account('customer');

        $this->issue('admins', $admin);

        $this->assertSame(Password::RESET_LINK_SENT, Password::broker('customers')->sendResetLink(['email' => self::EMAIL]));
    }

    public function test_customer_token_cannot_reset_vendor(): void
    {
        $this->assertCrossBrokerTokenRejected('customer', 'vendor');
    }

    public function test_vendor_token_cannot_reset_customer(): void
    {
        $this->assertCrossBrokerTokenRejected('vendor', 'customer');
    }

    public function test_customer_token_cannot_reset_admin(): void
    {
        $this->assertCrossBrokerTokenRejected('customer', 'admin');
    }

    public function test_admin_token_cannot_reset_customer(): void
    {
        $this->assertCrossBrokerTokenRejected('admin', 'customer');
    }

    public function test_delivery_driver_token_cannot_reset_other_account_types(): void
    {
        foreach (['admin', 'customer', 'vendor'] as $target) {
            $this->assertCrossBrokerTokenRejected('delivery_driver', $target);
            $this->resetState();
        }
    }

    public function test_other_account_tokens_cannot_reset_delivery_driver(): void
    {
        foreach (['admin', 'customer', 'vendor'] as $issuer) {
            $this->assertCrossBrokerTokenRejected($issuer, 'delivery_driver');
            $this->resetState();
        }
    }

    public function test_failed_cross_broker_attempt_changes_neither_password_nor_token(): void
    {
        $customer = $this->account('customer');
        $admin = $this->account('admin');
        $customerToken = $this->issue('customers', $customer);

        $this->assertResetRejected($this->reset('admin', $customerToken, 'Hijack-attempt-1'));

        $this->assertTrue(Hash::check(self::ORIGINAL_PASSWORD, $customer->fresh()->password));
        $this->assertTrue(Hash::check(self::ORIGINAL_PASSWORD, $admin->fresh()->password));
        $this->assertTrue(Password::broker('customers')->tokenExists($customer, $customerToken));
    }

    public function test_correct_broker_token_resets_its_intended_account(): void
    {
        $accounts = [];
        foreach (array_keys(self::TYPES) as $type) {
            $accounts[$type] = $this->account($type);
        }

        foreach (self::TYPES as $type => [, $broker, $table]) {
            $token = $this->issue($broker, $accounts[$type]);

            $this->assertResetSucceeds($this->reset($type, $token, "New-{$type}-pass-1"), $type);
            $this->assertTrue(Hash::check("New-{$type}-pass-1", $accounts[$type]->fresh()->password), $type);
            $this->assertSame(0, $this->rows($table), $type);
        }

        foreach ($accounts as $type => $account) {
            $this->assertTrue(Hash::check("New-{$type}-pass-1", $account->fresh()->password), $type);
        }
    }

    public function test_second_same_broker_token_replaces_the_first(): void
    {
        $customer = $this->account('customer');

        $first = $this->issue('customers', $customer);
        $this->assertSame(Password::RESET_THROTTLED, Password::broker('customers')->sendResetLink(['email' => self::EMAIL]));

        $this->travel(61)->seconds();
        $second = $this->issue('customers', $customer);

        $this->assertSame(1, $this->rows('customer_password_reset_tokens'));
        $this->assertFalse(Password::broker('customers')->tokenExists($customer, $first));
        $this->assertTrue(Password::broker('customers')->tokenExists($customer, $second));
    }

    public function test_first_same_broker_token_is_rejected_after_replacement(): void
    {
        $customer = $this->account('customer');

        $first = $this->issue('customers', $customer);
        $this->travel(61)->seconds();
        $this->issue('customers', $customer);

        $this->assertResetRejected($this->reset('customer', $first, 'Customer-new-pass-1'));
        $this->assertTrue(Hash::check(self::ORIGINAL_PASSWORD, $customer->fresh()->password));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $vendor = $this->account('vendor');
        $this->issue('vendors', $vendor);

        $this->assertResetRejected($this->reset('vendor', str_repeat('a', 64), 'Vendor-new-pass-1'));
        $this->assertTrue(Hash::check(self::ORIGINAL_PASSWORD, $vendor->fresh()->password));
    }

    public function test_expired_token_is_rejected_after_sixty_minutes(): void
    {
        $admin = $this->account('admin');
        $token = $this->issue('admins', $admin);

        $this->travel(61)->minutes();

        $this->assertResetRejected($this->reset('admin', $token, 'Admin-new-pass-1'));
        $this->assertTrue(Hash::check(self::ORIGINAL_PASSWORD, $admin->fresh()->password));
    }

    public function test_token_is_stored_hashed(): void
    {
        foreach (self::TYPES as $type => [, $broker, $table]) {
            $token = $this->issue($broker, $this->account($type));
            $stored = DB::table($table)->where('email', self::EMAIL)->value('token');

            $this->assertNotSame($token, $stored, $type);
            $this->assertStringNotContainsString($token, $stored, $type);
            $this->assertTrue(Hash::check($token, $stored), $type);
        }
    }

    public function test_each_table_keeps_an_independent_row_for_the_same_email(): void
    {
        $tokens = [];
        foreach (self::TYPES as $type => [, $broker]) {
            $tokens[$type] = $this->issue($broker, $this->account($type));
        }

        foreach (self::TYPES as $type => [$model, $broker, $table]) {
            $this->assertSame(1, $this->rows($table), $type);
            $this->assertTrue(Password::broker($broker)->tokenExists($model::where('email', self::EMAIL)->sole(), $tokens[$type]), $type);
        }

        $this->assertCount(4, array_unique($tokens));
    }

    public function test_legacy_table_is_unaffected_by_custom_broker_requests(): void
    {
        $user = $this->legacyUser();
        $legacyToken = $this->issue('users', $user);
        $legacyRow = (array) DB::table(self::LEGACY_TABLE)->where('email', self::EMAIL)->first();

        foreach (self::TYPES as $type => [, $broker]) {
            $this->issue($broker, $this->account($type));
        }

        $this->assertSame(1, $this->rows(self::LEGACY_TABLE));
        $this->assertSame($legacyRow, (array) DB::table(self::LEGACY_TABLE)->where('email', self::EMAIL)->first());
        $this->assertTrue(Password::broker('users')->tokenExists($user, $legacyToken));
    }

    public function test_emailed_reset_link_is_still_the_known_arch_03_route(): void
    {
        // Documents ARCH-03 (still open): custom account emails point at Fortify's reset route.
        $customer = $this->account('customer');
        $token = $this->issue('customers', $customer);

        $link = Notification::sent($customer, ResetPassword::class)->last()->toMail($customer)->actionUrl;

        $this->assertSame('/reset-password/'.$token, parse_url($link, PHP_URL_PATH));
    }

    public function test_sec_03_http_limits_still_apply_on_top_of_broker_isolation(): void
    {
        $this->account('customer');

        foreach (range(1, 3) as $attempt) {
            $this->post(route('customer.password.email'), ['email' => self::EMAIL])->assertStatus(302);
        }

        $this->post(route('customer.password.email'), ['email' => self::EMAIL])->assertStatus(429);
        $this->assertSame(1, $this->rows('customer_password_reset_tokens'));
    }

    private function assertTokenStoredOnlyInOwnTable(string $type): void
    {
        [, $broker, $ownTable] = self::TYPES[$type];

        $this->issue($broker, $this->account($type));

        $this->assertSame(1, $this->rows($ownTable));
        $this->assertSame(0, $this->rows(self::LEGACY_TABLE));

        foreach (self::TYPES as [, , $table]) {
            if ($table !== $ownTable) {
                $this->assertSame(0, $this->rows($table), $table);
            }
        }
    }

    private function assertCrossBrokerTokenRejected(string $issuer, string $target): void
    {
        $issuingAccount = $this->account($issuer);
        $targetAccount = $this->account($target);
        [, $broker] = self::TYPES[$issuer];

        $token = $this->issue($broker, $issuingAccount);

        $this->assertResetRejected($this->reset($target, $token, 'Cross-broker-pass-1'));
        $this->assertTrue(Hash::check(self::ORIGINAL_PASSWORD, $targetAccount->fresh()->password), "$issuer -> $target");
        $this->assertTrue(Hash::check(self::ORIGINAL_PASSWORD, $issuingAccount->fresh()->password), "$issuer -> $target");
    }

    private function assertResetSucceeds(TestResponse $response, string $type): void
    {
        $response->assertSessionHasNoErrors()->assertSessionHas('status', __('passwords.reset'));
    }

    private function assertResetRejected(TestResponse $response): void
    {
        $response->assertStatus(302)->assertSessionHasErrors(['email' => __('passwords.token')]);
    }

    private function issue(string $broker, $notifiable): string
    {
        $this->assertSame(Password::RESET_LINK_SENT, Password::broker($broker)->sendResetLink(['email' => self::EMAIL]));

        return Notification::sent($notifiable, ResetPassword::class)->last()->token;
    }

    private function reset(string $type, string $token, string $password): TestResponse
    {
        return $this->post(route(self::TYPES[$type][3]), [
            'token' => $token,
            'email' => self::EMAIL,
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    private function rows(string $table): int
    {
        return DB::table($table)->where('email', self::EMAIL)->count();
    }

    private function account(string $type)
    {
        [$model] = self::TYPES[$type];
        static $sequence = 0;
        $sequence++;

        return $model::create([
            'name' => ucfirst($type),
            'email' => self::EMAIL,
            'phone' => '0591'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'password' => self::ORIGINAL_PASSWORD,
            'status' => 'active',
        ]);
    }

    private function legacyUser(): User
    {
        return User::create([
            'name' => 'Legacy',
            'email' => self::EMAIL,
            'phone' => '0592222222',
            'password' => self::ORIGINAL_PASSWORD,
            'type' => 'user',
            'status' => 'active',
        ]);
    }

    private function resetState(): void
    {
        foreach (self::TYPES as [$model, , $table]) {
            $model::query()->delete();
            DB::table($table)->delete();
        }

        $this->travel(2)->minutes();
    }
}
