<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\RoleUser;
use App\Support\SiteAvailability\SiteAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SITE-AVAILABILITY Phase 3A (docs/deployment/HIRFAH-SITE-AVAILABILITY-DESIGN.md §4.2): `maintenance.bypass` is
 * registered in data/abilities.php and only a super admin (admins.super_admin) may grant or revoke it. A non-super
 * admin who holds it can neither pass it on nor take it away; every other ability keeps the existing scope rules.
 */
class MaintenanceBypassAbilityTest extends TestCase
{
    use RefreshDatabase;

    private const BYPASS = 'maintenance.bypass';

    public function test_the_ability_is_registered_in_the_existing_catalogue(): void
    {
        $this->assertSame(SiteAvailability::ABILITY_BYPASS, self::BYPASS);
        $this->assertSame(['name', 'bypass'], array_keys(app('abilities')['maintenance']));
    }

    public function test_super_admin_can_grant_and_revoke_it(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $target = $this->adminWithAbilities(['settings.view']);

        $this->actingAs($super, 'admin')
            ->put(route('dashboard.admins.update', $target), $this->payload($target, ['abilities' => ['settings.view', self::BYPASS]]))
            ->assertRedirect(route('dashboard.admins.edit', $target));
        $this->assertSame([self::BYPASS, 'settings.view'], $this->abilitiesOf($target));
        $this->assertTrue($target->fresh()->hasAbility(self::BYPASS));

        $this->actingAs($super, 'admin')
            ->put(route('dashboard.admins.update', $target), $this->payload($target, ['abilities' => ['settings.view']]))
            ->assertRedirect(route('dashboard.admins.edit', $target));
        $this->assertSame(['settings.view'], $this->abilitiesOf($target));
    }

    public function test_super_admin_can_create_an_admin_with_it(): void
    {
        $super = $this->createAdmin(superAdmin: true);

        $this->actingAs($super, 'admin')
            ->post(route('dashboard.admins.store'), $this->newAdminPayload(['abilities' => [self::BYPASS]]))
            ->assertRedirect(route('dashboard.admins.index'));

        $this->assertSame([self::BYPASS], $this->abilitiesOf(Admin::where('phone', '0598887777')->firstOrFail()));
    }

    public function test_an_admin_holding_it_cannot_grant_it_to_another_admin(): void
    {
        $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.edit', 'settings.view', self::BYPASS]);
        $target = $this->adminWithAbilities(['settings.view']);

        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $target), $this->payload($target, ['abilities' => ['settings.view', self::BYPASS]]))
            ->assertForbidden();

        $this->assertSame(['settings.view'], $this->abilitiesOf($target));
    }

    public function test_an_admin_holding_it_cannot_create_an_admin_with_it(): void
    {
        $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.create', self::BYPASS]);

        $this->actingAs($delegated, 'admin')
            ->post(route('dashboard.admins.store'), $this->newAdminPayload(['abilities' => [self::BYPASS]]))
            ->assertForbidden();

        $this->assertFalse(Admin::where('phone', '0598887777')->exists(), 'Nothing is created.');
    }

    public function test_an_ordinary_admin_cannot_revoke_it_and_saving_without_it_keeps_it(): void
    {
        $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.edit', 'settings.view', self::BYPASS]);
        $target = $this->adminWithAbilities(['settings.view', self::BYPASS]);

        // The form never shows it to this actor, so a normal save omits it: it is kept, the in-scope change applies.
        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $target), $this->payload($target, ['abilities' => []]))
            ->assertRedirect(route('dashboard.admins.edit', $target));
        $this->assertSame([self::BYPASS], $this->abilitiesOf($target));

        // Re-submitting what the target already holds is not a grant.
        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $target), $this->payload($target, ['abilities' => ['settings.view', self::BYPASS]]))
            ->assertRedirect(route('dashboard.admins.edit', $target));
        $this->assertSame([self::BYPASS, 'settings.view'], $this->abilitiesOf($target));
    }

    public function test_crafted_requests_cannot_get_around_the_restriction(): void
    {
        $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.edit', 'settings.view', self::BYPASS]);
        $target = $this->adminWithAbilities(['settings.view']);
        $before = $target->fresh()->only(['name', 'email', 'phone', 'status']);

        $crafted = [
            ['abilities' => [self::BYPASS]],
            ['abilities' => ['settings.view', self::BYPASS], 'name' => 'Changed'],
            ['abilities' => ['settings.view', self::BYPASS, self::BYPASS]],
            ['abilities' => [self::BYPASS], 'super_admin' => '0'],
        ];

        foreach ($crafted as $overrides) {
            $this->actingAs($delegated, 'admin')
                ->put(route('dashboard.admins.update', $target), $this->payload($target, $overrides))
                ->assertForbidden();
        }

        // Case and wildcard variants are not catalogue abilities: rejected by validation.
        foreach (['MAINTENANCE.BYPASS', 'maintenance.*', 'maintenance'] as $variant) {
            $this->actingAs($delegated, 'admin')
                ->put(route('dashboard.admins.update', $target), $this->payload($target, ['abilities' => ['settings.view', $variant]]))
                ->assertSessionHasErrors('abilities.1');
        }

        // Padding is trimmed by the TrimStrings middleware into the real name, which is then outside the scope: 403.
        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $target), $this->payload($target, ['abilities' => ['settings.view', ' maintenance.bypass ']]))
            ->assertForbidden();

        $this->assertSame(['settings.view'], $this->abilitiesOf($target));
        $this->assertSame($before, $target->fresh()->only(['name', 'email', 'phone', 'status']), 'A rejected request changes nothing.');
    }

    public function test_an_admin_holding_it_cannot_change_it_on_their_own_account(): void
    {
        $this->createAdmin(superAdmin: true);
        $self = $this->adminWithAbilities(['admins.edit', self::BYPASS]);

        $this->actingAs($self, 'admin')
            ->put(route('dashboard.admins.update', $self), $this->payload($self, ['abilities' => ['admins.edit']]))
            ->assertForbidden();

        $this->assertSame(['admins.edit', self::BYPASS], $this->abilitiesOf($self));
    }

    public function test_the_form_lists_it_for_a_super_admin_only(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.edit', 'admins.create', 'settings.view', self::BYPASS]);
        $target = $this->createAdmin();

        $this->actingAs($super, 'admin')->get(route('dashboard.admins.edit', $target))
            ->assertOk()->assertSee('value="'.self::BYPASS.'"', false);
        $this->actingAs($super, 'admin')->get(route('dashboard.admins.create'))
            ->assertOk()->assertSee('value="'.self::BYPASS.'"', false);

        $this->actingAs($delegated, 'admin')->get(route('dashboard.admins.edit', $target))
            ->assertOk()->assertSee('value="settings.view"', false)->assertDontSee('value="'.self::BYPASS.'"', false);
        $this->actingAs($delegated, 'admin')->get(route('dashboard.admins.create'))
            ->assertOk()->assertDontSee('value="'.self::BYPASS.'"', false);
    }

    public function test_unrelated_abilities_keep_the_existing_scope_rules(): void
    {
        $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.edit', 'settings.view', 'orders.view', self::BYPASS]);
        $target = $this->adminWithAbilities(['languages.delete']);

        // Within scope: granted.
        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $target), $this->payload($target, ['abilities' => ['settings.view', 'orders.view']]))
            ->assertRedirect(route('dashboard.admins.edit', $target));
        $this->assertSame(['languages.delete', 'orders.view', 'settings.view'], $this->abilitiesOf($target));

        // Outside scope (not held): still rejected.
        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $target), $this->payload($target, ['abilities' => ['settings.edit']]))
            ->assertForbidden();
        $this->assertSame(['languages.delete', 'orders.view', 'settings.view'], $this->abilitiesOf($target));
    }

    public function test_existing_admins_are_not_granted_it_automatically(): void
    {
        $this->createAdmin(superAdmin: true);
        $ordinary = $this->adminWithAbilities(['settings.view', 'settings.edit', 'admins.edit']);

        $this->assertFalse($ordinary->hasAbility(self::BYPASS));
        $this->assertSame(0, RoleUser::where('role_name', self::BYPASS)->count());
        $this->assertFalse(app(SiteAvailability::class)->canBypass($ordinary));
    }

    public function test_the_granted_ability_is_what_the_gate_reads(): void
    {
        $this->createAdmin(superAdmin: true);
        $holder = $this->adminWithAbilities([self::BYPASS]);
        $blocked = $this->adminWithAbilities([self::BYPASS]);
        $blocked->update(['status' => 'blocked']);

        $this->assertTrue(app(SiteAvailability::class)->canBypass($holder));
        $this->assertFalse(app(SiteAvailability::class)->canBypass($blocked->fresh()), 'Only an active admin bypasses.');
    }

    private function adminWithAbilities(array $abilities): Admin
    {
        $admin = $this->createAdmin();

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
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

    private function payload(Admin $admin, array $overrides = []): array
    {
        return array_merge([
            'name' => $admin->name,
            'email' => $admin->email,
            'phone' => $admin->phone,
            'status' => $admin->status,
        ], $overrides);
    }

    private function newAdminPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Admin',
            'email' => 'new-admin@example.com',
            'phone' => '0598887777',
            'password' => 'new-admin-password',
            'password_confirmation' => 'new-admin-password',
            'status' => 'active',
        ], $overrides);
    }

    /** @return list<string> */
    private function abilitiesOf(Admin $admin): array
    {
        return RoleUser::where('user_id', $admin->id)->orderBy('role_name')->pluck('role_name')->all();
    }
}
