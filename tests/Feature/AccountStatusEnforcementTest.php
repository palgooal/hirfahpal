<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Between requests the cached guards are forgotten, so every request loads
 * the account from the session (or remember cookie) the way production does.
 */
class AccountStatusEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-password';

    /**
     * guard => [model, login POST route, protected page, login page]
     */
    private const GUARDS = [
        'admin' => [Admin::class, 'admin.login.store', 'dashboard.setting.index', 'admin.login'],
        'customer' => [Customer::class, 'customer.login.store', 'customer.dashboard', 'customer.login'],
        'vendor' => [Vendor::class, 'vendor.login.store', 'vendor.dashboard', 'vendor.login'],
        'delivery_driver' => [DeliveryDriver::class, 'delivery-driver.login.store', 'delivery-driver.dashboard', 'delivery-driver.login'],
    ];

    public function test_active_admin_remains_authenticated(): void
    {
        $this->assertActiveAccountStaysSignedIn('admin');
    }

    public function test_blocked_admin_is_forced_out(): void
    {
        $this->assertForcedOutAfterStatusChange('admin', 'blocked');
    }

    public function test_pending_admin_is_forced_out(): void
    {
        $this->assertForcedOutAfterStatusChange('admin', 'pending');
    }

    public function test_dashboard_home_is_covered_without_auth_middleware(): void
    {
        $admin = $this->signIn('admin');
        $this->get(route('dashboard.home'))->assertOk();

        $this->changeStatus($admin, 'blocked');

        $this->assertInactiveRedirect($this->get(route('dashboard.home')), 'admin.login');
        $this->assertGuest('admin');
    }

    public function test_media_library_routes_are_covered(): void
    {
        $admin = $this->signIn('admin');
        $this->getJson(route('media-library.media.index'))->assertOk();

        $this->changeStatus($admin, 'blocked');
        $this->assertInactiveRedirect($this->get(route('media-library.page')), 'admin.login');
        $this->assertGuest('admin');
    }

    public function test_active_customer_remains_authenticated(): void
    {
        $this->assertActiveAccountStaysSignedIn('customer');
    }

    public function test_blocked_customer_is_forced_out(): void
    {
        $this->assertForcedOutAfterStatusChange('customer', 'blocked');
    }

    public function test_pending_customer_is_forced_out(): void
    {
        $this->assertForcedOutAfterStatusChange('customer', 'pending');
    }

    public function test_vendor_active_blocked_and_pending(): void
    {
        $this->assertActiveAccountStaysSignedIn('vendor');
        $this->assertForcedOutAfterStatusChange('vendor', 'blocked', 'blocked-vendor@example.com');
        $this->assertForcedOutAfterStatusChange('vendor', 'pending', 'pending-vendor@example.com');
    }

    public function test_delivery_driver_active_blocked_and_pending(): void
    {
        $this->assertActiveAccountStaysSignedIn('delivery_driver');
        $this->assertForcedOutAfterStatusChange('delivery_driver', 'blocked', 'blocked-driver@example.com');
        $this->assertForcedOutAfterStatusChange('delivery_driver', 'pending', 'pending-driver@example.com');
    }

    public function test_protected_controller_does_not_execute_after_invalidation(): void
    {
        $admin = $this->signIn('admin', superAdmin: true);
        $this->changeStatus($admin, 'blocked');

        $this->post(route('dashboard.admins.store'), [
            'name' => 'Should Not Exist',
            'email' => 'ghost-admin@example.com',
            'phone' => '0598888888',
            'password' => 'ghost-password',
            'password_confirmation' => 'ghost-password',
            'status' => 'active',
        ])->assertRedirect(route('admin.login'));

        $this->assertDatabaseMissing('admins', ['email' => 'ghost-admin@example.com']);
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_each_guard_redirects_to_its_own_login_and_never_to_fortify(): void
    {
        foreach (self::GUARDS as $guard => [, , $protected, $loginPage]) {
            $account = $this->signIn($guard, email: "$guard@example.com");
            $this->changeStatus($account, 'blocked');

            $response = $this->get(route($protected));

            $this->assertInactiveRedirect($response, $loginPage);
            $this->assertNotSame(url('/login'), $response->headers->get('Location'), $guard);
            $this->assertGuest($guard);
            $this->nextRequest();
        }
    }

    public function test_session_id_migrates_and_unrelated_session_data_is_kept(): void
    {
        $sessionId = $this->useSessionCookie();
        $admin = $this->account(Admin::class, 'admin@example.com', status: 'blocked');

        $this->withSession(['cart' => 'kept'])
            ->actingAs($admin, 'admin')
            ->get(route('dashboard.setting.index'))
            ->assertRedirect(route('admin.login'));

        $this->assertNotSame($sessionId, session()->getId());
        $this->assertSame('kept', session('cart'));
    }

    public function test_session_id_is_unchanged_for_active_accounts(): void
    {
        $sessionId = $this->useSessionCookie();
        $admin = $this->account(Admin::class, 'admin@example.com');

        $this->actingAs($admin, 'admin')->get(route('dashboard.home'))->assertOk();

        $this->assertSame($sessionId, session()->getId());
    }

    public function test_csrf_token_is_regenerated_on_forced_logout_only(): void
    {
        $active = $this->signIn('customer', email: 'active@example.com');
        $token = session()->token();

        $this->get(route('customer.dashboard'))->assertOk();
        $this->assertSame($token, session()->token());

        $this->changeStatus($active, 'blocked');
        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));

        $this->assertNotSame($token, session()->token());
    }

    public function test_remember_cookie_cannot_restore_a_blocked_account(): void
    {
        $admin = $this->account(Admin::class, 'admin@example.com');
        $response = $this->post(route('admin.login.store'), ['login' => 'admin@example.com', 'password' => self::PASSWORD, 'remember' => '1']);
        $recaller = Auth::guard('admin')->getRecallerName();
        $cookie = $response->getCookie($recaller);
        $tokenBefore = $admin->fresh()->getRememberToken();

        $this->assertNotNull($cookie);
        $this->assertNotEmpty($tokenBefore);

        $this->changeStatus($admin, 'blocked');

        // A fresh browser session: only the remember cookie can authenticate.
        $this->flushSession();
        $this->nextRequest();
        $forced = $this->withCookie($recaller, $cookie->getValue())->get(route('dashboard.setting.index'));

        $this->assertInactiveRedirect($forced, 'admin.login');
        $forced->assertCookieExpired($recaller);
        $this->assertGuest('admin');
        $this->assertNotSame($tokenBefore, $admin->fresh()->getRememberToken());

        // Even if the browser keeps sending the old cookie, it no longer matches.
        $this->flushSession();
        $this->nextRequest();
        $this->withCookie($recaller, $cookie->getValue())->get(route('dashboard.setting.index'))
            ->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_remember_token_cycles_on_forced_logout(): void
    {
        $customer = $this->account(Customer::class, 'customer@example.com');
        $customer->forceFill(['remember_token' => Str::random(60)])->save();
        $tokenBefore = $customer->fresh()->getRememberToken();

        $this->signIn('customer', email: 'customer@example.com', existing: true);
        $this->changeStatus($customer, 'pending');
        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));

        $this->assertNotSame($tokenBefore, $customer->fresh()->getRememberToken());
    }

    public function test_active_second_guard_survives_another_guards_forced_logout(): void
    {
        $this->signIn('admin', email: 'admin@example.com');
        $customer = $this->signIn('customer', email: 'customer@example.com');
        session()->put('cart', 'kept');

        $this->changeStatus($customer, 'blocked');

        $this->assertInactiveRedirect($this->get(route('dashboard.setting.index')), 'customer.login');
        $this->nextRequest();

        $this->get(route('dashboard.setting.index'))->assertOk();
        $this->assertTrue(Auth::guard('admin')->check());
        $this->assertFalse(Auth::guard('customer')->check());
        $this->assertSame('kept', session('cart'));
    }

    public function test_first_invalidated_guard_decides_the_redirect(): void
    {
        $admin = $this->signIn('admin', email: 'admin@example.com');
        $vendor = $this->signIn('vendor', email: 'vendor@example.com');

        $this->changeStatus($vendor, 'blocked');
        $this->changeStatus($admin, 'pending');

        $this->assertInactiveRedirect($this->get(route('vendor.dashboard')), 'admin.login');
        $this->assertGuest('admin');
        $this->assertGuest('vendor');
    }

    public function test_deleted_account_stays_a_guest(): void
    {
        foreach (['admin', 'customer'] as $guard) {
            $account = $this->signIn($guard, email: "$guard@example.com");
            $account->delete();
            $this->nextRequest();

            $this->get(route(self::GUARDS[$guard][2]))->assertRedirect();
            $this->assertGuest($guard);
            $this->nextRequest();
        }
    }

    public function test_json_request_gets_401_with_only_the_generic_message(): void
    {
        $admin = $this->signIn('admin');
        $this->changeStatus($admin, 'blocked');

        $this->getJson(route('media-library.media.index'))
            ->assertUnauthorized()
            ->assertExactJson(['message' => __('auth.inactive')]);

        $this->assertGuest('admin');
    }

    public function test_forced_logout_message_does_not_reveal_which_status_applied(): void
    {
        $messages = [];

        foreach (['blocked', 'pending'] as $status) {
            $customer = $this->signIn('customer', email: "$status@example.com");
            $this->changeStatus($customer, $status);

            $this->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));
            $messages[$status] = session('errors')->first('login');
            $this->nextRequest();
        }

        $this->assertSame([__('auth.inactive')], array_values(array_unique($messages)));
        $this->assertStringNotContainsString(__('auth.blocked'), $messages['blocked']);
        $this->assertStringNotContainsString(__('auth.pending'), $messages['pending']);
    }

    public function test_blocked_admin_visiting_the_login_page_does_not_loop_to_the_dashboard(): void
    {
        $admin = $this->signIn('admin');
        $this->changeStatus($admin, 'blocked');

        $this->assertInactiveRedirect($this->get(route('admin.login')), 'admin.login');
        $this->nextRequest();

        $this->get(route('admin.login'))->assertOk();
        $this->assertGuest('admin');
    }

    public function test_repeated_requests_do_not_sign_out_active_accounts(): void
    {
        $this->signIn('vendor');

        foreach (range(1, 8) as $request) {
            $this->get(route('vendor.dashboard'))->assertOk();
            $this->nextRequest();
        }

        $this->assertTrue(Auth::guard('vendor')->check());
    }

    public function test_forced_logout_adds_no_rate_limiter_state(): void
    {
        $customer = $this->signIn('customer');
        $this->changeStatus($customer, 'blocked');
        $limiterKeys = fn () => array_filter(array_keys(Cache::store()->getStore()->all(false)), fn ($key) => str_ends_with($key, ':timer'));
        $before = $limiterKeys();

        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));

        $this->assertSame($before, $limiterKeys());
    }

    public function test_non_active_super_admin_never_reaches_the_admin_controller(): void
    {
        $lastActiveSuper = $this->account(Admin::class, 'super@example.com', superAdmin: true);
        $pendingSuper = $this->account(Admin::class, 'pending-super@example.com', status: 'pending', superAdmin: true);

        $this->actingAs($pendingSuper, 'admin')
            ->put(route('dashboard.admins.update', $lastActiveSuper), [
                'name' => 'Changed', 'email' => 'super@example.com', 'phone' => $lastActiveSuper->phone, 'status' => 'blocked',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['login' => __('auth.inactive')]);

        $this->assertTrue($lastActiveSuper->fresh()->isActiveSuperAdmin());
        $this->assertSame($lastActiveSuper->name, $lastActiveSuper->fresh()->name);
    }

    public function test_blocking_one_of_two_super_admins_signs_out_only_that_super_admin(): void
    {
        $first = $this->signIn('admin', email: 'first@example.com', superAdmin: true);
        $second = $this->account(Admin::class, 'second@example.com', superAdmin: true);

        $this->put(route('dashboard.admins.update', $second), [
            'name' => $second->name, 'email' => $second->email, 'phone' => $second->phone, 'status' => 'blocked',
        ])->assertRedirect(route('dashboard.admins.edit', $second));
        $this->nextRequest();

        $this->assertSame('blocked', $second->fresh()->status);
        $this->get(route('dashboard.admins.index'))->assertOk();
        $this->assertTrue($first->fresh()->isActiveSuperAdmin());

        Auth::guard('admin')->logout();
        $this->actingAs($second->fresh(), 'admin')->get(route('dashboard.admins.index'))->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_legacy_web_user_is_not_affected(): void
    {
        $user = User::create([
            'name' => 'Legacy', 'email' => 'legacy@example.com', 'phone' => '0592222222',
            'password' => self::PASSWORD, 'type' => 'user', 'status' => 'blocked',
        ]);

        $this->actingAs($user, 'web')->get(route('home'))->assertOk();

        $this->assertAuthenticatedAs($user, 'web');
    }

    private function assertActiveAccountStaysSignedIn(string $guard): void
    {
        [$model, , $protected] = self::GUARDS[$guard];
        $account = $this->signIn($guard, email: "active-$guard@example.com");

        $this->get(route($protected))->assertOk();
        $this->nextRequest();
        $this->get(route($protected))->assertOk();

        $this->assertInstanceOf($model, Auth::guard($guard)->user());
        $this->assertTrue($account->is(Auth::guard($guard)->user()));
        Auth::guard($guard)->logout();
        $this->nextRequest();
    }

    private function assertForcedOutAfterStatusChange(string $guard, string $status, ?string $email = null): void
    {
        [, , $protected, $loginPage] = self::GUARDS[$guard];
        $account = $this->signIn($guard, email: $email ?? "$status-$guard@example.com");
        $this->get(route($protected))->assertOk();

        $this->changeStatus($account, $status);

        $this->assertInactiveRedirect($this->get(route($protected)), $loginPage);
        $this->assertGuest($guard);
        $this->nextRequest();
        $this->assertGuest($guard);
    }

    private function assertInactiveRedirect(TestResponse $response, string $loginRoute): void
    {
        $response->assertRedirect(route($loginRoute))
            ->assertSessionHasErrors(['login' => __('auth.inactive')]);
    }

    /**
     * Signs in through the real login route so the session holds the guard key.
     */
    private function signIn(string $guard, ?string $email = null, ?bool $superAdmin = null, bool $existing = false)
    {
        [$model, $loginRoute] = self::GUARDS[$guard];
        $email ??= "$guard@example.com";
        // Admins default to flagged supers so the protected pages need no extra abilities.
        $superAdmin ??= $guard === 'admin';
        $account = $existing ? $model::where('email', $email)->sole() : $this->account($model, $email, superAdmin: $superAdmin);

        $this->post(route($loginRoute), ['login' => $email, 'password' => self::PASSWORD])->assertRedirect();
        $this->nextRequest();

        return $account;
    }

    private function changeStatus($account, string $status): void
    {
        $account->forceFill(['status' => $status])->save();
        $this->nextRequest();
    }

    private function nextRequest(): void
    {
        Auth::forgetGuards();
    }

    private function useSessionCookie(): string
    {
        $sessionId = Str::random(40);
        $this->withCookie(config('session.cookie'), $sessionId);

        return $sessionId;
    }

    private function account(string $model, string $email, string $status = 'active', bool $superAdmin = false)
    {
        $attributes = [
            'name' => 'Account '.$email,
            'email' => $email,
            'phone' => '059'.substr((string) crc32($email), 0, 7),
            'password' => self::PASSWORD,
            'status' => $status,
        ];

        if ($model === Admin::class) {
            $attributes['super_admin'] = $superAdmin;
        }

        return $model::create($attributes);
    }
}
