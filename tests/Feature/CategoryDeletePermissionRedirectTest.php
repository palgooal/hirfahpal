<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\Vendor;
use App\Support\Categories\CategoryDeleteConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\ConfirmsCategoryDeletion;
use Tests\TestCase;

/**
 * AD-UI-CAT-01F-FIX-03 (AUD-04b): an admin with categories.delete but not categories.view never ends on a 403
 * because of where the delete flow sends them (after a delete, a refusal, a stale confirmation, or Cancel).
 * The abilities, the single-use confirmation, CSRF and DEC-CAT-Q3 are unchanged.
 */
class CategoryDeletePermissionRedirectTest extends TestCase
{
    use ConfirmsCategoryDeletion;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    public function test_delete_only_ends_on_the_dashboard_home_and_never_on_a_403(): void
    {
        $deleter = $this->admin(['categories.delete']);
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery']);

        $page = $this->actingAs($deleter, 'admin')->get(route('dashboard.categories.delete', $category))->assertOk()->getContent();
        $this->assertStringContainsString('<a href="'.route('dashboard.home').'" class="btn btn-light-secondary">Cancel</a>', $page);
        $this->actingAs($deleter, 'admin')->get(route('dashboard.home'))->assertOk();

        $token = $this->issueDeleteConfirmation($deleter, $category);
        $response = $this->actingAs($deleter, 'admin')->from(route('dashboard.categories.delete', $category))
            ->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $token])
            ->assertRedirect(route('dashboard.home'))
            ->assertSessionHas('success', 'Category deleted successfully.');
        $this->assertModelMissing($category);
        $this->assertOpens($deleter, $response);

        // Sending the same delete again deletes nothing more and still ends on an allowed page.
        $again = $this->actingAs($deleter, 'admin')->from(route('dashboard.categories.delete', $category->id))
            ->delete(route('dashboard.categories.destroy', $category->id), ['confirmation' => $token])
            ->assertRedirect(route('dashboard.home'))
            ->assertSessionHas('danger', 'This category no longer exists. It may have been deleted already.');
        $this->assertOpens($deleter, $again);
        $this->assertSame(0, Category::count());

        // The list stays closed to them.
        $this->actingAs($deleter, 'admin')->get(route('dashboard.categories.index'))->assertForbidden();
    }

    public function test_with_view_and_for_a_super_admin_the_list_stays_the_destination(): void
    {
        foreach ([
            'view + delete' => $this->admin(['categories.view', 'categories.delete']),
            'super admin' => Admin::factory()->create(['super_admin' => true]),
        ] as $label => $admin) {
            $category = Category::create(['name' => "Pottery {$label}", 'slug' => 'c-'.md5($label)]);
            $list = route('dashboard.categories.index', ['search' => 'Pottery', 'page' => 1]);

            $page = $this->actingAs($admin, 'admin')->get(route('dashboard.categories.delete', $category))->getContent();
            $this->assertStringContainsString('<a href="'.route('dashboard.categories.index').'" class="btn btn-light-secondary">Cancel</a>', $page, $label);

            $this->confirmedDelete($admin, $category, $list)->assertRedirect($list)->assertSessionHas('success');
            $this->confirmedDelete($admin, Category::create(['name' => 'Next', 'slug' => 'n-'.md5($label)]), route('dashboard.categories.delete', 1))
                ->assertRedirect(route('dashboard.categories.index'));
        }
    }

    public function test_without_the_delete_ability_nothing_is_deleted(): void
    {
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery']);

        foreach ([['categories.view'], ['categories.edit'], ['categories.create', 'categories.edit']] as $abilities) {
            $admin = $this->admin($abilities);
            $this->actingAs($admin, 'admin')->get(route('dashboard.categories.delete', $category))->assertForbidden();
            $token = $this->issueDeleteConfirmation($admin, $category);
            $this->actingAs($admin, 'admin')->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $token])->assertForbidden();
        }

        $this->assertModelExists($category);
    }

    public function test_a_refused_delete_returns_delete_only_admins_to_the_confirmation_page(): void
    {
        $deleter = $this->admin(['categories.delete']);
        $withProduct = Category::create(['name' => 'With product', 'slug' => 'with-product']);
        DB::table('products')->insert(['vendor_id' => Vendor::factory()->create()->id, 'category_id' => $withProduct->id, 'name' => 'Jug', 'slug' => 'jug', 'price' => 10]);
        $parent = Category::create(['name' => 'Parent', 'slug' => 'parent']);
        Category::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id]);

        foreach ([[$withProduct, 'This category cannot be deleted because it has associated products.'], [$parent, 'This category has subcategories and cannot be deleted. Move or delete them first.']] as [$category, $message]) {
            // Even with a Referer pointing at the list (which they may not open) or outside the app.
            foreach ([route('dashboard.categories.index'), 'https://evil.example/dashboard/categories', null] as $referer) {
                $token = $this->issueDeleteConfirmation($deleter, $category);
                $request = $this->actingAs($deleter, 'admin');
                if ($referer !== null) {
                    $request = $request->withHeader('referer', $referer);
                }
                $response = $request->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $token])
                    ->assertRedirect(route('dashboard.categories.delete', $category))
                    ->assertSessionHas('danger', $message);
                $page = $this->assertOpens($deleter, $response);
                $this->assertStringContainsString($message, $page);
            }
            $this->assertModelExists($category);
        }

        // With categories.view, the list it came from stays the destination (AUD-05 unchanged).
        $viewer = $this->admin(['categories.view', 'categories.delete']);
        $this->confirmedDelete($viewer, $parent, route('dashboard.categories.index', ['level' => 'root']))
            ->assertRedirect(route('dashboard.categories.index', ['level' => 'root']));
    }

    public function test_a_stale_or_reused_confirmation_leads_to_the_confirmation_page(): void
    {
        $deleter = $this->admin(['categories.delete']);
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery']);

        // No confirmation, a forged one, an expired one: the confirmation page (allowed), nothing deleted.
        foreach ([null, 'forged', 'expired'] as $case) {
            $token = $case === 'expired' ? $this->issueDeleteConfirmation($deleter, $category) : $case;
            if ($case === 'expired') {
                $this->travel(CategoryDeleteConfirmation::TTL + 1)->seconds();
            }
            $response = $this->actingAs($deleter, 'admin')->delete(route('dashboard.categories.destroy', $category), $token === null ? [] : ['confirmation' => $token])
                ->assertRedirect(route('dashboard.categories.delete', $category));
            $this->assertOpens($deleter, $response);
            $this->travelBack();
        }

        $this->assertModelExists($category);
    }

    public function test_an_external_referer_never_decides_the_destination(): void
    {
        $deleter = $this->admin(['categories.delete']);

        foreach (['https://evil.example/', '//evil.example/dashboard/categories', route('dashboard.categories.index').'?x=1', 'javascript:alert(1)'] as $i => $referer) {
            $category = Category::create(['name' => "C{$i}", 'slug' => "c-{$i}"]);
            $token = $this->issueDeleteConfirmation($deleter, $category);
            $this->actingAs($deleter, 'admin')->withHeader('referer', $referer)
                ->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $token])
                ->assertRedirect(route('dashboard.home'));
        }
    }

    public function test_a_category_deleted_meanwhile_leads_delete_only_admins_home(): void
    {
        $deleter = $this->admin(['categories.delete']);
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery']);
        $token = $this->issueDeleteConfirmation($deleter, $category);
        DB::table('categories')->where('id', $category->id)->delete();

        $this->assertOpens($deleter, $this->actingAs($deleter, 'admin')->get(route('dashboard.categories.delete', $category->id))->assertRedirect(route('dashboard.home')));
        $this->assertOpens($deleter, $this->actingAs($deleter, 'admin')->delete(route('dashboard.categories.destroy', $category->id), ['confirmation' => $token])->assertRedirect(route('dashboard.home')));

        $viewer = $this->admin(['categories.view', 'categories.delete']);
        $this->actingAs($viewer, 'admin')->get(route('dashboard.categories.delete', $category->id))->assertRedirect(route('dashboard.categories.index'));
    }

    private function assertOpens(Admin $admin, TestResponse $redirect): string
    {
        return $this->actingAs($admin, 'admin')->get($redirect->headers->get('Location'))->assertOk()->getContent();
    }

    private function admin(array $abilities): Admin
    {
        $admin = Admin::factory()->create(['super_admin' => false]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }
}
