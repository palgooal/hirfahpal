<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\Setting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuperAdminResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_lowest_id_admin_without_flag_or_abilities_has_no_implicit_permissions(): void
    {
        $lowest = $this->createAdmin();
        $this->createAdmin(superAdmin: true);

        $this->assertNoGlobalAccess($lowest);

        $this->actingAs($lowest, 'admin')->get(route('dashboard.setting.index'))->assertForbidden();
        $this->actingAs($lowest, 'admin')->get(route('dashboard.admins.index'))->assertForbidden();
    }

    public function test_deleting_lowest_id_admin_does_not_promote_the_next_admin(): void
    {
        $first = $this->createAdmin(superAdmin: true);
        $second = $this->createAdmin();

        $first->delete();
        $second = $second->fresh();

        $this->assertFalse($second->isSuperAdmin());
        $this->assertNoGlobalAccess($second);
    }

    public function test_flagged_super_admin_gets_global_authorization(): void
    {
        $this->createAdmin();
        $superAdmin = $this->createAdmin(superAdmin: true);

        $this->assertTrue($superAdmin->can('edit', Setting::class));
        $this->assertTrue($superAdmin->can('delete', Admin::class));
        $this->assertTrue($superAdmin->can('create', Language::class));

        $this->actingAs($superAdmin, 'admin')->get(route('dashboard.setting.index'))->assertOk();
        $this->actingAs($superAdmin, 'admin')->get(route('dashboard.admins.create'))->assertOk();
    }

    public function test_flagged_super_admin_passes_a_raw_gate_ability(): void
    {
        $superAdmin = $this->createAdmin(superAdmin: true);
        $ordinary = $this->adminWithAbilities(['settings.view']);

        $this->assertTrue($superAdmin->can('any-undefined-ability'));
        $this->assertFalse($ordinary->can('any-undefined-ability'));
    }

    public function test_ordinary_admin_with_one_explicit_ability_gets_only_that_ability(): void
    {
        $this->createAdmin(superAdmin: true);
        $admin = $this->adminWithAbilities(['settings.view']);

        $this->assertTrue($admin->can('view', Setting::class));
        $this->assertFalse($admin->can('edit', Setting::class));
        $this->assertFalse($admin->can('view', Language::class));
        $this->assertFalse($admin->can('view', Admin::class));
    }

    public function test_ordinary_admin_without_abilities_is_denied(): void
    {
        $this->createAdmin(superAdmin: true);
        $admin = $this->createAdmin();

        $this->assertNoGlobalAccess($admin);
        $this->actingAs($admin, 'admin')->get(route('dashboard.languages.index'))->assertForbidden();
    }

    public function test_flagged_super_admin_remains_super_regardless_of_id_order(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->createAdmin();
        }

        $lateSuperAdmin = $this->createAdmin(superAdmin: true);

        $this->assertNotSame(Admin::min('id'), $lateSuperAdmin->id);
        $this->assertTrue($lateSuperAdmin->can('edit', Setting::class));
        $this->assertTrue($lateSuperAdmin->can('any-undefined-ability'));
    }

    public function test_multiple_flagged_super_admins_work(): void
    {
        $firstSuper = $this->createAdmin(superAdmin: true);
        $this->createAdmin();
        $secondSuper = $this->createAdmin(superAdmin: true);

        foreach ([$firstSuper, $secondSuper] as $superAdmin) {
            $this->assertTrue($superAdmin->can('edit', Setting::class));
            $this->actingAs($superAdmin, 'admin')->get(route('dashboard.setting.index'))->assertOk();
        }
    }

    public function test_legacy_admins_super_row_does_not_make_an_admin_super(): void
    {
        $this->createAdmin(superAdmin: true);
        $legacy = $this->adminWithAbilities(['admins.super']);

        $this->assertFalse($legacy->isSuperAdmin());
        $this->assertFalse($legacy->can('super', Admin::class));
        $this->assertNoGlobalAccess($legacy);
    }

    public function test_blocked_lowest_id_admin_gets_no_privilege_from_its_id(): void
    {
        $blocked = $this->createAdmin(status: 'blocked');
        $this->createAdmin(superAdmin: true);

        $this->assertNoGlobalAccess($blocked);
        $this->actingAs($blocked, 'admin')->get(route('dashboard.setting.index'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['login' => __('auth.inactive')]);
        $this->assertGuest('admin');
    }

    public function test_pending_lowest_id_admin_gets_no_privilege_from_its_id(): void
    {
        $pending = $this->createAdmin(status: 'pending');
        $this->createAdmin(superAdmin: true);

        $this->assertNoGlobalAccess($pending);
        $this->actingAs($pending, 'admin')->get(route('dashboard.setting.index'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['login' => __('auth.inactive')]);
        $this->assertGuest('admin');
    }

    public function test_authorization_does_not_look_up_the_lowest_admin(): void
    {
        $ordinary = $this->adminWithAbilities(['settings.view']);
        $superAdmin = $this->createAdmin(superAdmin: true);

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $ordinary->can('view', Setting::class);
        $ordinary->can('edit', Setting::class);
        $ordinary->can('any-undefined-ability');
        $superAdmin->can('edit', Setting::class);
        $superAdmin->can('any-undefined-ability');

        foreach ($queries as $sql) {
            $this->assertDoesNotMatchRegularExpression('/\bmin\s*\(/i', $sql);
            $this->assertDoesNotMatchRegularExpression('/from\s+["`]?admins["`]?/i', $sql);
        }
    }

    public function test_database_seeder_does_not_create_or_modify_admins(): void
    {
        $admin = $this->createAdmin(status: 'blocked');
        $before = $admin->fresh()->getAttributes();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Admin::count());
        $this->assertSame($before, $admin->fresh()->getAttributes());
        $this->assertFalse($admin->fresh()->isSuperAdmin());
    }

    public function test_make_super_command_promotes_a_selected_active_admin(): void
    {
        $this->createAdmin(superAdmin: true);
        $admin = $this->adminWithAbilities(['settings.view']);
        $admin->forceFill(['phone' => '0591234567'])->save();

        $this->artisan('admin:make-super', ['identifier' => '0591234567'])
            ->expectsConfirmation('Promote this admin to super admin?', 'yes')
            ->expectsOutputToContain("Admin #{$admin->id} is now a super admin.")
            ->assertSuccessful();

        $this->assertTrue($admin->fresh()->isSuperAdmin());
        $this->assertSame(2, Admin::count());
        $this->assertSame(['settings.view'], $admin->fresh()->abilityNames());
    }

    public function test_make_super_command_refuses_a_blocked_admin(): void
    {
        $admin = $this->createAdmin(status: 'blocked');

        $this->artisan('admin:make-super', ['identifier' => $admin->email])
            ->expectsOutputToContain('is not active (blocked)')
            ->assertFailed();

        $this->assertFalse($admin->fresh()->isSuperAdmin());
    }

    public function test_make_super_command_refuses_a_pending_admin(): void
    {
        $admin = $this->createAdmin(status: 'pending');

        $this->artisan('admin:make-super', ['identifier' => $admin->email])
            ->expectsOutputToContain('is not active (pending)')
            ->assertFailed();

        $this->assertFalse($admin->fresh()->isSuperAdmin());
    }

    public function test_make_super_command_is_idempotent_for_an_existing_super_admin(): void
    {
        $superAdmin = $this->createAdmin(superAdmin: true);
        $updatedAt = $superAdmin->fresh()->updated_at;

        $this->travel(5)->minutes();

        $this->artisan('admin:make-super', ['identifier' => $superAdmin->email])
            ->expectsOutputToContain('is already a super admin')
            ->assertSuccessful();

        $this->assertTrue($superAdmin->fresh()->isSuperAdmin());
        $this->assertEquals($updatedAt, $superAdmin->fresh()->updated_at);
    }

    public function test_declining_confirmation_changes_nothing(): void
    {
        $admin = $this->createAdmin();

        $this->artisan('admin:make-super', ['identifier' => $admin->email])
            ->expectsConfirmation('Promote this admin to super admin?', 'no')
            ->expectsOutputToContain('nothing was changed')
            ->assertFailed();

        $this->assertFalse($admin->fresh()->isSuperAdmin());
    }

    public function test_check_option_makes_no_database_changes(): void
    {
        $this->createAdmin(superAdmin: true);
        $this->createAdmin();

        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $this->artisan('admin:make-super', ['--check' => true])->assertSuccessful();

        $this->assertSame([], $writes);
        $this->assertSame(1, Admin::where('super_admin', true)->count());
    }

    public function test_check_option_reports_zero_active_super_admins(): void
    {
        $this->createAdmin();
        $this->createAdmin(superAdmin: true, status: 'blocked');

        $this->artisan('admin:make-super', ['--check' => true])
            ->expectsTable(['Admins', 'Super admins', 'Active super admins'], [[2, 1, 0]])
            ->expectsOutputToContain('No active super admin exists.')
            ->assertFailed();
    }

    public function test_check_option_reports_existing_active_super_admins(): void
    {
        $this->createAdmin(superAdmin: true);
        $this->createAdmin(superAdmin: true);
        $this->createAdmin(superAdmin: true, status: 'pending');
        $this->createAdmin();

        $this->artisan('admin:make-super', ['--check' => true])
            ->expectsTable(['Admins', 'Super admins', 'Active super admins'], [[4, 3, 2]])
            ->doesntExpectOutputToContain('No active super admin exists.')
            ->assertSuccessful();
    }

    public function test_make_super_command_fails_safely_for_an_unknown_identifier(): void
    {
        $admin = $this->createAdmin();

        $this->artisan('admin:make-super', ['identifier' => 'nobody@example.com'])
            ->expectsOutputToContain('No admin found')
            ->assertFailed();

        $this->artisan('admin:make-super')
            ->expectsOutputToContain('Provide the email or phone')
            ->assertFailed();

        $this->assertSame(1, Admin::count());
        $this->assertFalse($admin->fresh()->isSuperAdmin());
    }

    public function test_make_super_command_does_not_create_admins_or_ability_rows(): void
    {
        $admin = $this->adminWithAbilities(['languages.view']);
        $rolesBefore = RoleUser::orderBy('role_name')->get(['user_id', 'role_name', 'ability'])->toArray();

        $this->artisan('admin:make-super', ['identifier' => $admin->email])
            ->expectsConfirmation('Promote this admin to super admin?', 'yes')
            ->assertSuccessful();

        $this->artisan('admin:make-super', ['identifier' => 'new-person@example.com'])
            ->assertFailed();

        $this->assertSame(1, Admin::count());
        $this->assertSame($rolesBefore, RoleUser::orderBy('role_name')->get(['user_id', 'role_name', 'ability'])->toArray());
    }

    private function assertNoGlobalAccess(Admin $admin): void
    {
        $this->assertFalse($admin->can('edit', Setting::class));
        $this->assertFalse($admin->can('delete', Admin::class));
        $this->assertFalse($admin->can('create', Language::class));
        $this->assertFalse($admin->can('any-undefined-ability'));
    }

    private function adminWithAbilities(array $abilities): Admin
    {
        $admin = $this->createAdmin();

        foreach ($abilities as $ability) {
            RoleUser::create([
                'role_name' => $ability,
                'user_id' => $admin->id,
                'ability' => 'allow',
            ]);
        }

        return $admin;
    }

    private function createAdmin(bool $superAdmin = false, string $status = 'active'): Admin
    {
        $number = Admin::query()->count() + 1;

        return Admin::create([
            'name' => 'Admin '.$number,
            'email' => 'admin'.$number.'@example.com',
            'phone' => '05990000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'password' => 'password',
            'status' => $status,
            'super_admin' => $superAdmin,
        ]);
    }
}
