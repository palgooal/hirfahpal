<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\RoleUser;
use App\Models\Setting;
use App\Support\SiteAvailability\SiteAvailability;
use App\Support\SiteAvailability\SiteAvailabilityState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * SITE-AVAILABILITY §6.3, §6.5: precedence (environment override, then settings, then open), every override value,
 * fail-open on a missing table, column or row, an unknown value and a query error, per-request memoization, and the
 * bypass rule (SA-04, SA-05).
 */
class SiteAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_is_open_without_a_settings_row(): void
    {
        $this->assertSame(SiteAvailabilityState::Open, $this->service()->current());
        $this->assertSame('default', $this->service()->source());
    }

    public function test_the_stored_state_is_used_with_auto(): void
    {
        foreach (SiteAvailabilityState::cases() as $state) {
            $this->store($state->value);
            $service = $this->service();

            $this->assertSame($state, $service->current());
            $this->assertSame('database', $service->source());
        }
    }

    public function test_the_environment_override_wins_over_the_database(): void
    {
        $this->store('open');

        foreach (['coming_soon' => SiteAvailabilityState::ComingSoon, 'maintenance' => SiteAvailabilityState::Maintenance, ' Maintenance ' => SiteAvailabilityState::Maintenance] as $override => $expected) {
            config(['site-availability.override' => $override]);
            $service = $this->service();

            $this->assertSame($expected, $service->current(), $override);
            $this->assertSame('environment', $service->source());
        }

        $this->store('maintenance');
        config(['site-availability.override' => 'open']);
        $this->assertSame(SiteAvailabilityState::Open, $this->service()->current());
        $this->assertSame('environment', $this->service()->source());
    }

    public function test_auto_and_invalid_override_values_fall_back_to_the_stored_state(): void
    {
        $this->store('coming_soon');
        Log::spy();

        foreach (['auto', 'AUTO', '', null, 'closed', 'on'] as $override) {
            config(['site-availability.override' => $override]);
            $this->assertSame(SiteAvailabilityState::ComingSoon, $this->service()->current(), var_export($override, true));
            $this->assertSame('database', $this->service()->source());
        }

        Log::shouldHaveReceived('warning')->with('Ignoring an invalid HIRFAH_SITE_AVAILABILITY value; using auto.', \Mockery::any())->atLeast()->once();
    }

    public function test_a_forced_state_needs_no_database(): void
    {
        Schema::drop('settings');
        config(['site-availability.override' => 'coming_soon']);
        DB::enableQueryLog();

        $this->assertSame(SiteAvailabilityState::ComingSoon, $this->service()->current());
        $this->assertSame([], DB::getQueryLog(), 'A forced state reads nothing.');
    }

    public function test_a_missing_table_column_or_row_and_an_unknown_value_resolve_to_open(): void
    {
        // Row missing.
        $this->assertSame(SiteAvailabilityState::Open, $this->service()->current());

        // Unknown stored value (bypassing the CHECK constraint, which normally prevents it).
        Setting::singleton();
        DB::statement('PRAGMA ignore_check_constraints = ON');
        DB::table('settings')->update(['site_availability_mode' => 'closed']);
        DB::statement('PRAGMA ignore_check_constraints = OFF');
        Log::spy();
        $service = $this->service();
        $this->assertSame(SiteAvailabilityState::Open, $service->current());
        $this->assertSame('default', $service->source());
        Log::shouldHaveReceived('warning')->with('Unknown settings.site_availability_mode value; using open.')->once();

        // Column missing (code deployed before the migration).
        Schema::table('settings', fn ($table) => $table->dropColumn('site_availability_mode'));
        $this->assertSame(SiteAvailabilityState::Open, $this->service()->current());

        // Table missing.
        Schema::drop('settings');
        $this->assertSame(SiteAvailabilityState::Open, $this->service()->current());
        $this->assertSame('default', $this->service()->source());
    }

    public function test_the_result_is_memoized_for_the_request_only(): void
    {
        $this->store('coming_soon');
        $request = Request::create('/');
        $service = new SiteAvailability($request);

        $this->assertSame(SiteAvailabilityState::ComingSoon, $service->current());
        $this->store('maintenance');
        DB::enableQueryLog();
        $this->assertSame(SiteAvailabilityState::ComingSoon, $service->current(), 'Same request: memoized.');
        $this->assertSame([], DB::getQueryLog());

        $this->assertSame(SiteAvailabilityState::Maintenance, (new SiteAvailability(Request::create('/')))->current(), 'A new request reads again.');

        $service->forget();
        $this->assertSame(SiteAvailabilityState::Maintenance, $service->current());
    }

    public function test_bypass_is_for_active_super_admins_and_active_admins_with_the_ability_only(): void
    {
        $service = $this->service();

        $this->assertFalse($service->canBypass(null));
        $this->assertTrue($service->canBypass($this->admin(super: true)));
        $this->assertTrue($service->canBypass($this->admin(abilities: ['maintenance.bypass'])));
        $this->assertFalse($service->canBypass($this->admin(abilities: ['settings.edit', 'customers.view'])));
        $this->assertFalse($service->canBypass($this->admin(abilities: ['maintenance.bypass'], status: 'pending')));
        $this->assertFalse($service->canBypass($this->admin(super: true, status: 'blocked')));

        $denied = $this->admin();
        RoleUser::create(['role_name' => 'maintenance.bypass', 'user_id' => $denied->id, 'ability' => 'deny']);
        $this->assertFalse($service->canBypass($denied), 'Only an "allow" row grants the ability.');
    }

    private function service(): SiteAvailability
    {
        return new SiteAvailability(Request::create('/'));
    }

    private function store(string $mode): void
    {
        Setting::singleton();
        DB::table('settings')->update(['site_availability_mode' => $mode]);
    }

    private function admin(bool $super = false, array $abilities = [], string $status = 'active'): Admin
    {
        $number = Admin::query()->count() + 1;
        $admin = Admin::create([
            'name' => 'Admin '.$number, 'email' => 'admin'.$number.'@example.com', 'phone' => '05990000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'password' => 'password', 'status' => $status, 'super_admin' => $super,
        ]);
        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }
}
