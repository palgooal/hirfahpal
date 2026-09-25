<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\RoleUser;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Every test creates a flagged super admin first, so the lowest-id rule in
 * Gate::before (SEC-02) never belongs to the admin under test.
 */
class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_delegated_admin_cannot_self_grant_an_ability_they_lack(): void
    {
        $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.edit']);

        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $delegated), $this->payload($delegated, [
                'abilities' => ['admins.edit', 'settings.edit'],
            ]))
            ->assertForbidden();

        $this->assertSame(['admins.edit'], $this->abilitiesOf($delegated));
    }

    public function test_delegated_admin_cannot_grant_another_admin_an_ability_they_lack(): void
    {
        $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.edit', 'settings.view']);
        $target = $this->createAdmin();

        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $target), $this->payload($target, [
                'abilities' => ['settings.view', 'languages.delete'],
            ]))
            ->assertForbidden();

        $this->assertSame([], $this->abilitiesOf($target));
    }

    public function test_editing_an_ordinary_admin_preserves_abilities_outside_actor_scope(): void
    {
        $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.edit', 'settings.view']);
        $target = $this->adminWithAbilities(['languages.delete', 'settings.view']);

        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $target), $this->payload($target, ['abilities' => []]))
            ->assertRedirect(route('dashboard.admins.edit', $target));

        $this->assertSame(['languages.delete'], $this->abilitiesOf($target));

        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $target), $this->payload($target, ['abilities' => ['settings.view']]))
            ->assertRedirect(route('dashboard.admins.edit', $target));

        $this->assertSame(['languages.delete', 'settings.view'], $this->abilitiesOf($target));
    }

    public function test_delegated_admin_cannot_open_super_admin_edit_page(): void
    {
        $superAdmin = $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.edit']);

        $this->actingAs($delegated, 'admin')
            ->get(route('dashboard.admins.edit', $superAdmin))
            ->assertForbidden();
    }

    public function test_delegated_admin_cannot_update_super_admin_profile_email_or_abilities(): void
    {
        $superAdmin = $this->createAdmin(superAdmin: true);
        RoleUser::create(['role_name' => 'settings.view', 'user_id' => $superAdmin->id, 'ability' => 'allow']);
        $delegated = $this->adminWithAbilities(['admins.edit', 'settings.view']);

        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $superAdmin), $this->payload($superAdmin, [
                'name' => 'Hijacked',
                'email' => 'attacker@example.com',
                'abilities' => [],
            ]))
            ->assertForbidden();

        $superAdmin->refresh();
        $this->assertSame('Admin 1', $superAdmin->name);
        $this->assertSame('admin1@example.com', $superAdmin->email);
        $this->assertSame(['settings.view'], $this->abilitiesOf($superAdmin));
    }

    public function test_delegated_admin_cannot_change_super_admin_password(): void
    {
        $superAdmin = $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.edit']);

        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $superAdmin), $this->payload($superAdmin, [
                'password' => 'attacker-password',
                'password_confirmation' => 'attacker-password',
            ]))
            ->assertForbidden();

        $this->assertTrue(Hash::check('password', $superAdmin->fresh()->password));
    }

    public function test_delegated_admin_cannot_block_super_admin(): void
    {
        $superAdmin = $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.edit']);

        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $superAdmin), $this->payload($superAdmin, ['status' => 'blocked']))
            ->assertForbidden();

        $this->assertSame('active', $superAdmin->fresh()->status);
    }

    public function test_delegated_admin_cannot_delete_super_admin(): void
    {
        $superAdmin = $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.delete']);

        $this->actingAs($delegated, 'admin')
            ->delete(route('dashboard.admins.destroy', $superAdmin))
            ->assertForbidden();

        $this->assertNotNull($superAdmin->fresh());
    }

    public function test_create_only_admin_cannot_create_an_admin_above_their_scope(): void
    {
        $this->createAdmin(superAdmin: true);
        $creator = $this->adminWithAbilities(['admins.create']);

        $this->actingAs($creator, 'admin')
            ->post(route('dashboard.admins.store'), $this->newAdminPayload(['abilities' => ['admins.create', 'admins.edit']]))
            ->assertForbidden();

        $this->actingAs($creator, 'admin')
            ->post(route('dashboard.admins.store'), $this->newAdminPayload(['super_admin' => 1]))
            ->assertForbidden();

        $this->assertDatabaseMissing('admins', ['email' => 'new-admin@example.com']);

        $this->actingAs($creator, 'admin')
            ->post(route('dashboard.admins.store'), $this->newAdminPayload(['abilities' => ['admins.create']]))
            ->assertRedirect(route('dashboard.admins.index'));

        $created = Admin::where('email', 'new-admin@example.com')->firstOrFail();
        $this->assertFalse($created->isSuperAdmin());
        $this->assertSame(['admins.create'], $this->abilitiesOf($created));
    }

    public function test_legacy_admins_super_role_row_does_not_make_an_admin_super(): void
    {
        $superAdmin = $this->createAdmin(superAdmin: true);
        $legacy = $this->adminWithAbilities(['admins.super', 'admins.edit']);

        $this->assertFalse($legacy->isSuperAdmin());
        $this->assertFalse($legacy->can('super', Admin::class));
        $this->assertFalse($legacy->can('edit', Setting::class));

        $this->actingAs($legacy, 'admin')
            ->get(route('dashboard.admins.edit', $superAdmin))
            ->assertForbidden();

        $this->actingAs($legacy, 'admin')
            ->get(route('dashboard.setting.index'))
            ->assertForbidden();
    }

    public function test_legacy_admins_super_role_row_cannot_set_super_admin_flag(): void
    {
        $this->createAdmin(superAdmin: true);
        $legacy = $this->adminWithAbilities(['admins.super', 'admins.edit', 'admins.create']);
        $target = $this->createAdmin();

        $this->actingAs($legacy, 'admin')
            ->put(route('dashboard.admins.update', $legacy), $this->payload($legacy, ['super_admin' => 1]))
            ->assertForbidden();

        $this->actingAs($legacy, 'admin')
            ->put(route('dashboard.admins.update', $target), $this->payload($target, ['super_admin' => 1]))
            ->assertForbidden();

        $this->actingAs($legacy, 'admin')
            ->post(route('dashboard.admins.store'), $this->newAdminPayload(['super_admin' => 1]))
            ->assertForbidden();

        $this->assertFalse($legacy->fresh()->isSuperAdmin());
        $this->assertFalse($target->fresh()->isSuperAdmin());
        $this->assertDatabaseMissing('admins', ['email' => 'new-admin@example.com']);
    }

    public function test_admins_super_is_no_longer_a_grantable_ability(): void
    {
        $superAdmin = $this->createAdmin(superAdmin: true);
        $target = $this->createAdmin();

        $this->actingAs($superAdmin, 'admin')
            ->put(route('dashboard.admins.update', $target), $this->payload($target, ['abilities' => ['admins.super']]))
            ->assertSessionHasErrors('abilities.0');

        $this->assertSame([], $this->abilitiesOf($target));
    }

    public function test_non_super_self_edit_preserves_abilities_and_status(): void
    {
        $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.edit', 'settings.view']);

        $payload = $this->payload($delegated);
        unset($payload['status']);

        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $delegated), $payload)
            ->assertRedirect(route('dashboard.admins.edit', $delegated));

        $this->assertSame(['admins.edit', 'settings.view'], $this->abilitiesOf($delegated));
        $this->assertSame('active', $delegated->fresh()->status);

        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $delegated), $this->payload($delegated, ['status' => 'pending']))
            ->assertForbidden();

        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $delegated), $this->payload($delegated, ['abilities' => ['admins.edit']]))
            ->assertForbidden();

        $this->assertSame(['admins.edit', 'settings.view'], $this->abilitiesOf($delegated));
        $this->assertSame('active', $delegated->fresh()->status);
    }

    public function test_non_super_self_edit_can_update_personal_fields_and_password(): void
    {
        $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.edit']);

        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $delegated), [
                'name' => 'Renamed Admin',
                'email' => 'renamed@example.com',
                'phone' => '0591112222',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect(route('dashboard.admins.edit', $delegated));

        $delegated->refresh();
        $this->assertSame('Renamed Admin', $delegated->name);
        $this->assertSame('renamed@example.com', $delegated->email);
        $this->assertSame('0591112222', $delegated->phone);
        $this->assertTrue(Hash::check('new-password', $delegated->password));
        $this->assertSame(['admins.edit'], $this->abilitiesOf($delegated));
    }

    public function test_super_admin_can_create_edit_and_delete_ordinary_admins(): void
    {
        $superAdmin = $this->createAdmin(superAdmin: true);

        $this->actingAs($superAdmin, 'admin')
            ->post(route('dashboard.admins.store'), $this->newAdminPayload(['abilities' => ['settings.edit', 'languages.delete']]))
            ->assertRedirect(route('dashboard.admins.index'));

        $created = Admin::where('email', 'new-admin@example.com')->firstOrFail();
        $this->assertSame(['languages.delete', 'settings.edit'], $this->abilitiesOf($created));

        $this->actingAs($superAdmin, 'admin')
            ->put(route('dashboard.admins.update', $created), $this->payload($created, [
                'name' => 'Edited By Super',
                'status' => 'blocked',
                'password' => 'reset-password',
                'password_confirmation' => 'reset-password',
                'abilities' => ['admins.view'],
            ]))
            ->assertRedirect(route('dashboard.admins.edit', $created));

        $created->refresh();
        $this->assertSame('Edited By Super', $created->name);
        $this->assertSame('blocked', $created->status);
        $this->assertTrue(Hash::check('reset-password', $created->password));
        $this->assertSame(['admins.view'], $this->abilitiesOf($created));

        $this->actingAs($superAdmin, 'admin')
            ->delete(route('dashboard.admins.destroy', $created))
            ->assertRedirect(route('dashboard.admins.index'));

        $this->assertNull($created->fresh());
    }

    public function test_super_admin_can_manage_another_super_admin_while_one_active_super_remains(): void
    {
        $superAdmin = $this->createAdmin(superAdmin: true);
        $otherSuper = $this->createAdmin(superAdmin: true);
        $thirdSuper = $this->createAdmin(superAdmin: true);

        $this->actingAs($superAdmin, 'admin')
            ->put(route('dashboard.admins.update', $otherSuper), $this->payload($otherSuper, [
                'status' => 'blocked',
                'super_admin' => 1,
            ]))
            ->assertRedirect(route('dashboard.admins.edit', $otherSuper));

        $this->assertSame('blocked', $otherSuper->fresh()->status);

        $this->actingAs($superAdmin, 'admin')
            ->put(route('dashboard.admins.update', $otherSuper), $this->payload($otherSuper->fresh(), [
                'status' => 'active',
                'super_admin' => 0,
            ]))
            ->assertRedirect(route('dashboard.admins.edit', $otherSuper));

        $this->assertFalse($otherSuper->fresh()->isSuperAdmin());

        $this->actingAs($superAdmin, 'admin')
            ->delete(route('dashboard.admins.destroy', $thirdSuper))
            ->assertRedirect(route('dashboard.admins.index'));

        $this->assertNull($thirdSuper->fresh());
        $this->assertTrue($superAdmin->fresh()->isActiveSuperAdmin());
    }

    public function test_last_active_super_admin_cannot_be_demoted(): void
    {
        $superAdmin = $this->createAdmin(superAdmin: true);

        $this->actingAs($superAdmin, 'admin')
            ->put(route('dashboard.admins.update', $superAdmin), $this->payload($superAdmin, [
                'name' => 'Should Not Persist',
                'super_admin' => 0,
            ]))
            ->assertSessionHasErrors('super_admin');

        $superAdmin->refresh();
        $this->assertTrue($superAdmin->isActiveSuperAdmin());
        $this->assertSame('Admin 1', $superAdmin->name);
    }

    public function test_last_active_super_admin_cannot_be_blocked(): void
    {
        $superAdmin = $this->createAdmin(superAdmin: true);
        $inactiveSuper = $this->createAdmin(superAdmin: true, status: 'pending');

        $this->actingAs($superAdmin, 'admin')
            ->put(route('dashboard.admins.update', $superAdmin), $this->payload($superAdmin, ['status' => 'blocked']))
            ->assertSessionHasErrors('super_admin');

        $this->actingAs($inactiveSuper, 'admin')
            ->put(route('dashboard.admins.update', $superAdmin), $this->payload($superAdmin, ['status' => 'blocked']))
            ->assertSessionHasErrors('super_admin');

        $this->assertTrue($superAdmin->fresh()->isActiveSuperAdmin());
    }

    public function test_last_active_super_admin_cannot_be_deleted(): void
    {
        $superAdmin = $this->createAdmin(superAdmin: true);
        $inactiveSuper = $this->createAdmin(superAdmin: true, status: 'pending');

        $this->actingAs($inactiveSuper, 'admin')
            ->delete(route('dashboard.admins.destroy', $superAdmin))
            ->assertRedirect(route('dashboard.admins.index'))
            ->assertSessionHas('danger');

        $this->assertNotNull($superAdmin->fresh());
    }

    public function test_sequential_demotions_cannot_remove_every_active_super_admin(): void
    {
        $firstSuper = $this->createAdmin(superAdmin: true);
        $secondSuper = $this->createAdmin(superAdmin: true);

        $this->actingAs($firstSuper, 'admin')
            ->put(route('dashboard.admins.update', $secondSuper), $this->payload($secondSuper, ['super_admin' => 0]))
            ->assertRedirect(route('dashboard.admins.edit', $secondSuper));

        $this->actingAs($firstSuper, 'admin')
            ->put(route('dashboard.admins.update', $firstSuper), $this->payload($firstSuper, ['super_admin' => 0]))
            ->assertSessionHasErrors('super_admin');

        $this->assertSame(1, Admin::where('super_admin', true)->where('status', 'active')->count());
    }

    public function test_self_delete_remains_prohibited(): void
    {
        $superAdmin = $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.delete']);

        $this->actingAs($superAdmin, 'admin')
            ->delete(route('dashboard.admins.destroy', $superAdmin))
            ->assertSessionHas('danger');

        $this->actingAs($delegated, 'admin')
            ->delete(route('dashboard.admins.destroy', $delegated))
            ->assertSessionHas('danger');

        $this->assertNotNull($superAdmin->fresh());
        $this->assertNotNull($delegated->fresh());
    }

    public function test_delegated_admin_can_manage_ordinary_admins_within_their_scope(): void
    {
        $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.create', 'admins.edit', 'admins.delete', 'settings.view']);
        $target = $this->createAdmin();

        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $target), $this->payload($target, [
                'name' => 'Managed Admin',
                'status' => 'pending',
                'password' => 'managed-password',
                'password_confirmation' => 'managed-password',
                'abilities' => ['settings.view'],
            ]))
            ->assertRedirect(route('dashboard.admins.edit', $target));

        $target->refresh();
        $this->assertSame('Managed Admin', $target->name);
        $this->assertSame('pending', $target->status);
        $this->assertTrue(Hash::check('managed-password', $target->password));
        $this->assertSame(['settings.view'], $this->abilitiesOf($target));

        $this->actingAs($delegated, 'admin')
            ->post(route('dashboard.admins.store'), $this->newAdminPayload(['abilities' => ['settings.view', 'admins.edit']]))
            ->assertRedirect(route('dashboard.admins.index'));

        $this->assertSame(['admins.edit', 'settings.view'], $this->abilitiesOf(Admin::where('email', 'new-admin@example.com')->firstOrFail()));

        $this->actingAs($delegated, 'admin')
            ->delete(route('dashboard.admins.destroy', $target))
            ->assertRedirect(route('dashboard.admins.index'));

        $this->assertNull($target->fresh());
    }

    public function test_view_only_admin_cannot_create_update_or_delete(): void
    {
        $this->createAdmin(superAdmin: true);
        $viewer = $this->adminWithAbilities(['admins.view']);
        $target = $this->createAdmin();

        $this->actingAs($viewer, 'admin')->get(route('dashboard.admins.index'))->assertOk();
        $this->actingAs($viewer, 'admin')->post(route('dashboard.admins.store'), $this->newAdminPayload())->assertForbidden();
        $this->actingAs($viewer, 'admin')->put(route('dashboard.admins.update', $target), $this->payload($target))->assertForbidden();
        $this->actingAs($viewer, 'admin')->delete(route('dashboard.admins.destroy', $target))->assertForbidden();

        $this->assertNotNull($target->fresh());
        $this->assertDatabaseMissing('admins', ['email' => 'new-admin@example.com']);
    }

    public function test_out_of_scope_request_is_rejected_without_partial_changes(): void
    {
        $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.edit', 'settings.view']);
        $target = $this->adminWithAbilities(['settings.view']);

        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $target), $this->payload($target, [
                'name' => 'Partially Changed',
                'email' => 'partial@example.com',
                'status' => 'blocked',
                'password' => 'partial-password',
                'password_confirmation' => 'partial-password',
                'abilities' => ['settings.view', 'settings.edit'],
            ]))
            ->assertForbidden();

        $fresh = $target->fresh();
        $this->assertSame($target->name, $fresh->name);
        $this->assertSame($target->email, $fresh->email);
        $this->assertSame('active', $fresh->status);
        $this->assertTrue(Hash::check('password', $fresh->password));
        $this->assertSame(['settings.view'], $this->abilitiesOf($target));
    }

    public function test_form_only_lists_abilities_the_actor_can_grant(): void
    {
        $superAdmin = $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.edit', 'settings.view']);
        $target = $this->createAdmin();

        $this->actingAs($delegated, 'admin')
            ->get(route('dashboard.admins.edit', $target))
            ->assertOk()
            ->assertSee('value="settings.view"', false)
            ->assertDontSee('value="settings.edit"', false)
            ->assertDontSee('value="admins.super"', false)
            ->assertDontSee('name="super_admin"', false);

        $this->actingAs($delegated, 'admin')
            ->get(route('dashboard.admins.edit', $delegated))
            ->assertOk()
            ->assertDontSee('name="status"', false)
            ->assertDontSee('name="abilities[]"', false);

        $this->actingAs($superAdmin, 'admin')
            ->get(route('dashboard.admins.edit', $target))
            ->assertOk()
            ->assertSee('value="settings.edit"', false)
            ->assertSee('name="super_admin"', false)
            ->assertDontSee('value="admins.super"', false);
    }

    public function test_index_hides_super_admin_actions_from_delegated_admin(): void
    {
        $superAdmin = $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.view', 'admins.edit', 'admins.delete']);
        $target = $this->createAdmin();

        $this->actingAs($delegated, 'admin')
            ->get(route('dashboard.admins.index'))
            ->assertOk()
            ->assertSee(route('dashboard.admins.edit', $target), false)
            ->assertDontSee(route('dashboard.admins.edit', $superAdmin), false)
            ->assertDontSee(route('dashboard.admins.destroy', $superAdmin).'"', false);
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

    private function abilitiesOf(Admin $admin): array
    {
        return RoleUser::where('user_id', $admin->id)
            ->orderBy('role_name')
            ->pluck('role_name')
            ->all();
    }
}
