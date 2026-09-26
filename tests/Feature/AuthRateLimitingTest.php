<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-password';

    /**
     * login route => [model, guard, dashboard route]
     */
    private const LOGINS = [
        'admin.login.store' => [Admin::class, 'admin', 'dashboard.home'],
        'customer.login.store' => [Customer::class, 'customer', 'customer.dashboard'],
        'vendor.login.store' => [Vendor::class, 'vendor', 'vendor.dashboard'],
        'delivery-driver.login.store' => [DeliveryDriver::class, 'delivery_driver', 'delivery-driver.dashboard'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
    }

    public function test_admin_login_allows_five_failures_then_throttles(): void
    {
        $this->assertFiveFailuresThenThrottled('admin.login.store');
    }

    public function test_customer_login_allows_five_failures_then_throttles(): void
    {
        $this->assertFiveFailuresThenThrottled('customer.login.store');
    }

    public function test_vendor_login_allows_five_failures_then_throttles(): void
    {
        $this->assertFiveFailuresThenThrottled('vendor.login.store');
    }

    public function test_delivery_driver_login_allows_five_failures_then_throttles(): void
    {
        $this->assertFiveFailuresThenThrottled('delivery-driver.login.store');
    }

    public function test_successful_login_below_the_limit_works(): void
    {
        $customer = $this->account(Customer::class, 'customer@example.com');

        $this->failLogins('customer.login.store', 'customer@example.com', 2);

        $this->login('customer.login.store', 'customer@example.com', self::PASSWORD)
            ->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_successful_login_clears_the_failure_counter(): void
    {
        $this->account(Customer::class, 'customer@example.com');

        $this->failLogins('customer.login.store', 'customer@example.com', 4);
        $this->login('customer.login.store', 'customer@example.com', self::PASSWORD)
            ->assertRedirect(route('customer.dashboard'));
        auth('customer')->logout();

        foreach (range(1, 5) as $attempt) {
            $this->assertCredentialFailure($this->login('customer.login.store', 'customer@example.com', 'wrong'));
        }

        $this->assertThrottled($this->login('customer.login.store', 'customer@example.com', 'wrong'));
    }

    public function test_correct_password_is_refused_while_throttled(): void
    {
        $this->account(Vendor::class, 'vendor@example.com');

        $this->failLogins('vendor.login.store', 'vendor@example.com', 5);

        $this->assertThrottled($this->login('vendor.login.store', 'vendor@example.com', self::PASSWORD));
        $this->assertGuest('vendor');
    }

    public function test_correct_login_works_after_the_window_expires(): void
    {
        $admin = $this->account(Admin::class, 'admin@example.com');

        $this->failLogins('admin.login.store', 'admin@example.com', 5);
        $this->assertThrottled($this->login('admin.login.store', 'admin@example.com', self::PASSWORD));

        $this->travel(61)->seconds();

        $this->login('admin.login.store', 'admin@example.com', self::PASSWORD)
            ->assertRedirect(route('dashboard.home'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_missing_account_counts_exactly_like_a_wrong_password(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->assertCredentialFailure($this->login('customer.login.store', 'ghost@example.com', 'anything'));
        }

        $this->assertThrottled($this->login('customer.login.store', 'ghost@example.com', 'anything'));
    }

    public function test_blocked_account_attempts_count(): void
    {
        $this->account(Customer::class, 'blocked@example.com', status: 'blocked');

        foreach (range(1, 5) as $attempt) {
            $this->login('customer.login.store', 'blocked@example.com', self::PASSWORD)
                ->assertSessionHasErrors(['login' => __('auth.blocked')]);
        }

        $this->assertThrottled($this->login('customer.login.store', 'blocked@example.com', self::PASSWORD));
    }

    public function test_pending_account_attempts_count(): void
    {
        $this->account(Vendor::class, 'pending@example.com', status: 'pending');

        foreach (range(1, 5) as $attempt) {
            $this->login('vendor.login.store', 'pending@example.com', self::PASSWORD)
                ->assertSessionHasErrors(['login' => __('auth.pending')]);
        }

        $this->assertThrottled($this->login('vendor.login.store', 'pending@example.com', self::PASSWORD));
    }

    public function test_throttle_response_does_not_expose_account_state(): void
    {
        $this->account(Customer::class, 'active@example.com');
        $this->account(Customer::class, 'blocked@example.com', status: 'blocked');
        $this->account(Customer::class, 'pending@example.com', status: 'pending');

        $messages = [];

        // blocked/pending fail even with the right password, which is how they reveal their state today
        foreach ([
            'ghost@example.com' => 'wrong',
            'active@example.com' => 'wrong',
            'blocked@example.com' => self::PASSWORD,
            'pending@example.com' => self::PASSWORD,
        ] as $identifier => $failingPassword) {
            foreach (range(1, 5) as $attempt) {
                $this->login('customer.login.store', $identifier, $failingPassword);
            }

            $response = $this->login('customer.login.store', $identifier, self::PASSWORD);
            $response->assertStatus(302);
            $messages[$identifier] = session('errors')->first('login');
        }

        $this->assertCount(1, array_unique($messages));
        $this->assertSame(__('auth.throttle', ['seconds' => 60]), reset($messages));
        $this->assertStringNotContainsString(__('auth.blocked'), reset($messages));
        $this->assertStringNotContainsString(__('auth.pending'), reset($messages));
        $this->assertStringNotContainsString(__('auth.failed'), reset($messages));
    }

    public function test_different_identifiers_have_separate_account_buckets(): void
    {
        $this->account(Customer::class, 'first@example.com');
        $second = $this->account(Customer::class, 'second@example.com');

        $this->failLogins('customer.login.store', 'first@example.com', 5);
        $this->assertThrottled($this->login('customer.login.store', 'first@example.com', 'wrong'));

        $this->login('customer.login.store', 'second@example.com', self::PASSWORD)
            ->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($second, 'customer');
    }

    public function test_same_identifier_under_different_account_types_has_separate_buckets(): void
    {
        $this->account(Customer::class, 'shared@example.com');
        $vendor = $this->account(Vendor::class, 'shared@example.com', phone: '0591111111');

        $this->failLogins('customer.login.store', 'shared@example.com', 5);
        $this->assertThrottled($this->login('customer.login.store', 'shared@example.com', 'wrong'));

        $this->login('vendor.login.store', 'shared@example.com', self::PASSWORD)
            ->assertRedirect(route('vendor.dashboard'));
        $this->assertAuthenticatedAs($vendor, 'vendor');
    }

    public function test_email_case_variants_share_the_same_bucket(): void
    {
        $this->account(Customer::class, 'user@example.com');

        $this->failLogins('customer.login.store', 'User@Example.com', 3);
        $this->failLogins('customer.login.store', 'user@example.com', 2);

        $this->assertThrottled($this->login('customer.login.store', 'USER@EXAMPLE.COM', 'wrong'));
    }

    public function test_different_ips_have_separate_account_buckets(): void
    {
        $customer = $this->account(Customer::class, 'customer@example.com');

        $this->fromIp('10.0.0.1');
        $this->failLogins('customer.login.store', 'customer@example.com', 5);
        $this->assertThrottled($this->login('customer.login.store', 'customer@example.com', self::PASSWORD));

        $this->fromIp('10.0.0.2');
        $this->login('customer.login.store', 'customer@example.com', self::PASSWORD)
            ->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_ip_ceiling_trips_across_identifiers_and_account_types(): void
    {
        $this->fromIp('10.0.0.1');
        $routes = array_keys(self::LOGINS);

        foreach (range(0, 29) as $attempt) {
            $this->login($routes[$attempt % 4], "stuffing{$attempt}@example.com", 'wrong')
                ->assertStatus(302);
        }

        $this->login('vendor.login.store', 'fresh@example.com', 'wrong')->assertStatus(429);
        $this->login('admin.login.store', 'fresh@example.com', 'wrong')->assertStatus(429);

        $this->fromIp('10.0.0.2');
        $this->assertCredentialFailure($this->login('vendor.login.store', 'fresh@example.com', 'wrong'));

        $this->fromIp('10.0.0.1');
        $this->post('/login', ['login' => 'fresh@example.com', 'password' => 'wrong'])->assertStatus(302);
    }

    public function test_fortify_login_bucket_does_not_affect_custom_login(): void
    {
        $this->webUser('shared@example.com');
        $customer = $this->account(Customer::class, 'shared@example.com', phone: '0591111111');

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['login' => 'shared@example.com', 'password' => 'wrong'])->assertStatus(302);
        }
        $this->post('/login', ['login' => 'shared@example.com', 'password' => 'wrong'])->assertStatus(429);

        $this->login('customer.login.store', 'shared@example.com', self::PASSWORD)
            ->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_custom_login_buckets_do_not_affect_fortify_login(): void
    {
        $user = $this->webUser('shared@example.com');
        $this->account(Customer::class, 'shared@example.com', phone: '0591111111');

        $this->failLogins('customer.login.store', 'shared@example.com', 5);
        $this->assertThrottled($this->login('customer.login.store', 'shared@example.com', 'wrong'));

        $this->post('/login', ['login' => 'shared@example.com', 'password' => self::PASSWORD])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_customer_registration_is_throttled_after_ten_submissions(): void
    {
        $this->assertRegistrationThrottled('customer.register.store');
    }

    public function test_vendor_registration_is_throttled_after_ten_submissions(): void
    {
        $this->assertRegistrationThrottled('vendor.register.store');
    }

    public function test_delivery_driver_registration_is_throttled_after_ten_submissions(): void
    {
        $this->assertRegistrationThrottled('delivery-driver.register.store');
    }

    public function test_registration_buckets_are_separated_by_account_type(): void
    {
        $this->assertRegistrationThrottled('customer.register.store');

        $this->post(route('vendor.register.store'), [])->assertStatus(302);
        $this->post(route('delivery-driver.register.store'), [])->assertStatus(302);
    }

    public function test_forgot_password_ip_limit(): void
    {
        $this->fromIp('10.0.0.1');

        foreach (range(1, 5) as $attempt) {
            $this->post(route('customer.password.email'), ['email' => "someone{$attempt}@example.com"])->assertStatus(302);
        }

        $this->post(route('vendor.password.email'), ['email' => 'another@example.com'])->assertStatus(429);

        $this->fromIp('10.0.0.2');
        $this->post(route('vendor.password.email'), ['email' => 'another@example.com'])->assertStatus(302);
    }

    public function test_forgot_password_identifier_limit(): void
    {
        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $ip) {
            $this->fromIp($ip);
            $this->post(route('customer.password.email'), ['email' => 'target@example.com'])->assertStatus(302);
        }

        $this->fromIp('10.0.0.4');
        $this->post(route('customer.password.email'), ['email' => 'Target@Example.com'])->assertStatus(429);

        $this->post(route('vendor.password.email'), ['email' => 'target@example.com'])->assertStatus(302);
    }

    public function test_unknown_email_is_counted_by_the_http_limiter(): void
    {
        foreach (range(1, 3) as $attempt) {
            $this->post(route('admin.password.email'), ['email' => 'nobody@example.com'])->assertStatus(302);
        }

        $this->post(route('admin.password.email'), ['email' => 'nobody@example.com'])->assertStatus(429);
    }

    public function test_existing_email_is_counted_by_the_same_rules(): void
    {
        $this->account(DeliveryDriver::class, 'driver@example.com');

        foreach (range(1, 3) as $attempt) {
            $this->post(route('delivery-driver.password.email'), ['email' => 'driver@example.com'])->assertStatus(302);
        }

        $this->post(route('delivery-driver.password.email'), ['email' => 'driver@example.com'])->assertStatus(429);
    }

    public function test_broker_token_throttle_stays_distinct_from_the_http_limiter(): void
    {
        $this->account(Customer::class, 'customer@example.com');

        $this->post(route('customer.password.email'), ['email' => 'customer@example.com'])
            ->assertStatus(302)
            ->assertSessionHas('status', __('passwords.sent'));

        $this->post(route('customer.password.email'), ['email' => 'customer@example.com'])
            ->assertStatus(302)
            ->assertSessionHasErrors(['email' => __('passwords.throttled')]);
    }

    public function test_reset_password_post_reaches_its_ip_limit(): void
    {
        $payload = [
            'token' => 'invalid-token',
            'email' => 'customer@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ];

        foreach (range(1, 10) as $attempt) {
            $route = $attempt % 2 ? 'customer.password.update' : 'admin.password.update';
            $this->post(route($route), $payload)->assertStatus(302);
        }

        $this->post(route('vendor.password.update'), $payload)->assertStatus(429);
    }

    public function test_logout_is_not_throttled(): void
    {
        foreach (['admin.logout', 'customer.logout', 'vendor.logout', 'delivery-driver.logout'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();

            $this->assertEmpty(array_filter($middleware, fn ($item) => str_starts_with($item, 'throttle')), $name);
        }

        foreach (range(0, 30) as $attempt) {
            $this->login('vendor.login.store', "stuffing{$attempt}@example.com", 'wrong');
        }

        $customer = $this->account(Customer::class, 'customer@example.com');

        $this->actingAs($customer, 'customer')
            ->post(route('customer.logout'))
            ->assertRedirect(route('customer.login'));
        $this->assertGuest('customer');
    }

    public function test_limiter_keys_do_not_contain_raw_identifiers(): void
    {
        $this->failLogins('customer.login.store', 'Private.Person@Example.com', 1);
        $this->failLogins('vendor.login.store', '0597654321', 1);
        $this->post(route('customer.password.email'), ['email' => 'Private.Person@Example.com']);

        $keys = array_map('strtolower', array_keys(Cache::store()->getStore()->all(false)));
        $hashedEmail = hash('sha256', 'private.person@example.com');

        $this->assertNotEmpty($keys);
        $this->assertNotEmpty(array_filter($keys, fn ($key) => str_contains($key, $hashedEmail)));

        foreach ($keys as $key) {
            $this->assertStringNotContainsString('private.person@example.com', $key);
            $this->assertStringNotContainsString('0597654321', $key);
        }
    }

    private function assertFiveFailuresThenThrottled(string $route): void
    {
        [$model] = self::LOGINS[$route];
        $this->account($model, 'target@example.com');

        foreach (range(1, 5) as $attempt) {
            $this->assertCredentialFailure($this->login($route, 'target@example.com', 'wrong'));
        }

        $this->assertThrottled($this->login($route, 'target@example.com', 'wrong'));
    }

    private function assertRegistrationThrottled(string $route): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->post(route($route), ['full_name' => ''])->assertStatus(302);
        }

        $this->post(route($route), ['full_name' => ''])->assertStatus(429);
    }

    private function assertThrottled(TestResponse $response): void
    {
        $response->assertStatus(302)
            ->assertSessionHasErrors(['login' => __('auth.throttle', ['seconds' => 60])]);
    }

    private function assertCredentialFailure(TestResponse $response): void
    {
        $response->assertStatus(302)
            ->assertSessionHasErrors(['login' => __('auth.failed')]);
    }

    private function failLogins(string $route, string $login, int $times): void
    {
        foreach (range(1, $times) as $attempt) {
            $this->login($route, $login, 'wrong');
        }
    }

    private function login(string $route, string $login, string $password): TestResponse
    {
        return $this->post(route($route), ['login' => $login, 'password' => $password]);
    }

    private function fromIp(string $ip): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    private function account(string $model, string $email, string $status = 'active', ?string $phone = null)
    {
        static $sequence = 0;
        $sequence++;

        return $model::create([
            'name' => 'Account '.$sequence,
            'email' => $email,
            'phone' => $phone ?? '05900'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT),
            'password' => self::PASSWORD,
            'status' => $status,
        ]);
    }

    private function webUser(string $email): User
    {
        return User::create([
            'name' => 'Web User',
            'email' => $email,
            'phone' => '0592222222',
            'password' => self::PASSWORD,
            'type' => 'user',
            'status' => 'active',
        ]);
    }
}
