<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\RoleUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * VEN-BE-020 (Release A): `vendor-orders.record-delivery-failure` and `vendor-orders.confirm-return` are registered in
 * data/abilities.php ahead of the delivery custody workflow. They follow the existing scope rules (a super admin grants
 * any ability; another admin only those it holds), nobody receives them automatically, the rest of the catalogue is
 * unchanged, and nothing in the application checks them yet.
 */
class DeliveryCustodyAbilitiesTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_ABILITIES = ['vendor-orders.record-delivery-failure', 'vendor-orders.confirm-return'];

    public function test_both_abilities_are_registered_in_the_vendor_orders_group(): void
    {
        $this->assertSame(
            ['name', 'view', 'create', 'assign-driver', 'record-delivery-failure', 'confirm-return'],
            array_keys(app('abilities')['vendor-orders'])
        );
        $this->assertSame('Record Delivery Failure', app('abilities')['vendor-orders']['record-delivery-failure']);
        $this->assertSame('Confirm Returned Goods', app('abilities')['vendor-orders']['confirm-return']);
    }

    public function test_the_rest_of_the_catalogue_is_unchanged(): void
    {
        $before = [
            'admins.view', 'admins.create', 'admins.edit', 'admins.delete',
            'languages.view', 'languages.create', 'languages.edit', 'languages.delete',
            'translation-values.view', 'translation-values.create', 'translation-values.edit', 'translation-values.delete',
            'settings.view', 'settings.edit',
            'vendors.view', 'vendors.create', 'vendors.edit',
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
            'media.view', 'media.create', 'media.edit', 'media.delete',
            'products.view', 'products.create', 'products.edit', 'products.delete', 'products.audit', 'products.suspend',
            'products.unsuspend', 'products.archive', 'products.restore',
            'customers.view', 'customers.edit',
            'delivery-drivers.view', 'delivery-drivers.edit',
            'orders.view', 'orders.edit',
            'vendor-orders.view', 'vendor-orders.create', 'vendor-orders.assign-driver',
            'reviews.view', 'reviews.edit', 'reviews.delete',
            'disputes.view', 'disputes.edit',
            'return-requests.view', 'return-requests.edit',
            'contact-messages.view',
            'content-pages.view', 'content-pages.edit', 'content-pages.create', 'content-pages.delete', // PAGES-03A, PAGES-04B, PAGES-04C.
            'maintenance.bypass',
        ];

        $this->assertSame($before, array_values(array_diff($this->catalogue(), self::NEW_ABILITIES)));
        $this->assertCount(count($before) + 2, $this->catalogue());
    }

    public function test_nobody_receives_them_automatically(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $ordinary = $this->adminWithAbilities(['vendor-orders.view', 'vendor-orders.assign-driver']);
        $everythingElse = $this->adminWithAbilities(array_values(array_diff($this->catalogue(), self::NEW_ABILITIES)));

        // Saving an admin with its current abilities, as the form does, keeps exactly those abilities.
        $this->actingAs($super, 'admin')
            ->put(route('dashboard.admins.update', $ordinary), $this->payload($ordinary, ['name' => 'Renamed', 'abilities' => ['vendor-orders.view', 'vendor-orders.assign-driver']]))
            ->assertRedirect(route('dashboard.admins.edit', $ordinary));

        $this->assertSame(['vendor-orders.assign-driver', 'vendor-orders.view'], $this->abilitiesOf($ordinary));
        $this->assertSame(0, RoleUser::whereIn('role_name', self::NEW_ABILITIES)->count());
        foreach ([$super, $ordinary, $everythingElse] as $admin) {
            foreach (self::NEW_ABILITIES as $ability) {
                $this->assertFalse($admin->fresh()->hasAbility($ability), "{$admin->name} does not hold {$ability}.");
            }
        }
    }

    public function test_a_super_admin_is_offered_both_and_can_grant_and_revoke_them(): void
    {
        $super = $this->createAdmin(superAdmin: true);
        $target = $this->adminWithAbilities(['vendor-orders.view']);

        $form = $this->actingAs($super, 'admin')->get(route('dashboard.admins.edit', $target))->assertOk()->getContent();
        foreach (self::NEW_ABILITIES as $ability) {
            $this->assertStringContainsString('value="'.$ability.'"', $form);
        }

        $this->actingAs($super, 'admin')
            ->put(route('dashboard.admins.update', $target), $this->payload($target, ['abilities' => ['vendor-orders.view', ...self::NEW_ABILITIES]]))
            ->assertRedirect(route('dashboard.admins.edit', $target));
        $this->assertSame(['vendor-orders.confirm-return', 'vendor-orders.record-delivery-failure', 'vendor-orders.view'], $this->abilitiesOf($target));

        $this->actingAs($super, 'admin')
            ->put(route('dashboard.admins.update', $target), $this->payload($target, ['abilities' => ['vendor-orders.view']]))
            ->assertRedirect(route('dashboard.admins.edit', $target));
        $this->assertSame(['vendor-orders.view'], $this->abilitiesOf($target));
    }

    public function test_an_admin_without_them_cannot_grant_them(): void
    {
        $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.edit', 'vendor-orders.view', 'vendor-orders.assign-driver']);
        $target = $this->adminWithAbilities(['vendor-orders.view']);

        foreach (self::NEW_ABILITIES as $ability) {
            $this->actingAs($delegated, 'admin')
                ->put(route('dashboard.admins.update', $target), $this->payload($target, ['abilities' => ['vendor-orders.view', $ability]]))
                ->assertForbidden();
        }

        $this->assertSame(['vendor-orders.view'], $this->abilitiesOf($target));
    }

    public function test_an_admin_holding_them_can_grant_them_like_any_other_ability(): void
    {
        $this->createAdmin(superAdmin: true);
        $delegated = $this->adminWithAbilities(['admins.edit', 'vendor-orders.view', ...self::NEW_ABILITIES]);
        $target = $this->adminWithAbilities(['vendor-orders.view']);

        $this->actingAs($delegated, 'admin')
            ->put(route('dashboard.admins.update', $target), $this->payload($target, ['abilities' => ['vendor-orders.view', 'vendor-orders.confirm-return']]))
            ->assertRedirect(route('dashboard.admins.edit', $target));

        $this->assertSame(['vendor-orders.confirm-return', 'vendor-orders.view'], $this->abilitiesOf($target));
    }

    public function test_nothing_in_the_application_checks_them_yet(): void
    {
        $files = array_merge(File::allFiles(app_path()), File::allFiles(base_path('routes')), File::allFiles(resource_path()));

        foreach ($files as $file) {
            $content = $file->getContents();
            foreach (['record-delivery-failure', 'confirm-return'] as $name) {
                $this->assertStringNotContainsString($name, $content, "{$file->getRelativePathname()} mentions {$name}.");
            }
        }
    }

    /** @return list<string> group.ability, in catalogue order */
    private function catalogue(): array
    {
        $names = [];
        foreach (app('abilities') as $group => $abilities) {
            foreach (array_keys($abilities) as $ability) {
                if ($ability !== 'name') {
                    $names[] = $group.'.'.$ability;
                }
            }
        }

        return $names;
    }

    private function createAdmin(bool $superAdmin = false): Admin
    {
        $number = Admin::query()->count() + 1;

        return Admin::create([
            'name' => 'Admin '.$number,
            'email' => 'admin'.$number.'@example.com',
            'phone' => '05990000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'password' => 'password',
            'status' => 'active',
            'super_admin' => $superAdmin,
        ]);
    }

    private function adminWithAbilities(array $abilities): Admin
    {
        $admin = $this->createAdmin();
        foreach ($abilities as $ability) {
            RoleUser::create(['user_id' => $admin->id, 'role_name' => $ability, 'ability' => 'allow']);
        }

        return $admin;
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

    /** @return list<string> */
    private function abilitiesOf(Admin $admin): array
    {
        return RoleUser::where('user_id', $admin->id)->orderBy('role_name')->pluck('role_name')->all();
    }
}
