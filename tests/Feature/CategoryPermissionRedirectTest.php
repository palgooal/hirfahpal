<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\RoleUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AD-UI-CAT-01F-FIX-02 (AUD-04, option A): categories.view, .create, .edit and .delete are independent. After a
 * successful save, and through Cancel, the admin lands on a page they may open; no ability is implied or granted.
 */
class CategoryPermissionRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    public function test_create_without_view_lands_on_a_page_the_admin_may_open(): void
    {
        // create only: a new create form, with the result shown there.
        $creator = $this->admin(['categories.create']);
        $this->actingAs($creator, 'admin')->post(route('dashboard.categories.store'), ['name' => 'Pottery'])
            ->assertRedirect(route('dashboard.categories.create'))
            ->assertSessionHas('success', 'Category saved successfully.');
        $this->assertSame(1, Category::count());
        $html = $this->actingAs($creator, 'admin')->get(route('dashboard.categories.create'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<div class="admin-media-alert is-success" role="status" data-cat-alert>.*?Category saved successfully\.#s', $html);
        $this->assertSame(1, Category::count(), 'Following the redirect saves nothing again.');

        // create + edit: the new category's edit page.
        $creatorEditor = $this->admin(['categories.create', 'categories.edit']);
        $this->actingAs($creatorEditor, 'admin')->post(route('dashboard.categories.store'), ['name' => 'Glass'])
            ->assertRedirect(route('dashboard.categories.edit', Category::query()->where('name', 'Glass')->firstOrFail()));
        $this->actingAs($creatorEditor, 'admin')->get(route('dashboard.categories.edit', Category::query()->where('name', 'Glass')->firstOrFail()))
            ->assertOk()->assertSee('Category saved successfully.');

        // Neither gains the list.
        $this->actingAs($creator, 'admin')->get(route('dashboard.categories.index'))->assertForbidden();
        $this->actingAs($creatorEditor, 'admin')->get(route('dashboard.categories.index'))->assertForbidden();
    }

    public function test_edit_without_view_lands_on_the_same_edit_page(): void
    {
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery']);
        $editor = $this->admin(['categories.edit']);

        $this->actingAs($editor, 'admin')->put(route('dashboard.categories.update', $category), ['name' => 'Pottery 2', 'slug' => 'pottery'])
            ->assertRedirect(route('dashboard.categories.edit', $category))
            ->assertSessionHas('success');
        $this->assertSame('Pottery 2', $category->fresh()->name);
        $this->actingAs($editor, 'admin')->get(route('dashboard.categories.edit', $category))->assertOk()->assertSee('Category saved successfully.');

        $this->actingAs($editor, 'admin')->get(route('dashboard.categories.index'))->assertForbidden();
        $this->actingAs($editor, 'admin')->get(route('dashboard.categories.create'))->assertForbidden();
    }

    public function test_with_view_and_for_a_super_admin_the_list_stays_the_destination(): void
    {
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery']);

        foreach ([
            'view + create + edit' => $this->admin(['categories.view', 'categories.create', 'categories.edit']),
            'super admin' => Admin::factory()->create(['super_admin' => true]),
        ] as $label => $admin) {
            $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => "New {$label}"])
                ->assertRedirect(route('dashboard.categories.index'));
            $this->actingAs($admin, 'admin')->put(route('dashboard.categories.update', $category), ['name' => "Pottery {$label}", 'slug' => 'pottery'])
                ->assertRedirect(route('dashboard.categories.index'));
            $this->actingAs($admin, 'admin')->get(route('dashboard.categories.index'))->assertOk()->assertSee('Category saved successfully.');
        }
    }

    public function test_view_alone_and_missing_abilities_are_refused_and_change_nothing(): void
    {
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery', 'sort_order' => 3]);
        $before = $category->fresh()->toArray();

        $viewer = $this->admin(['categories.view']);
        $this->actingAs($viewer, 'admin')->get(route('dashboard.categories.index'))->assertOk();
        $this->actingAs($viewer, 'admin')->get(route('dashboard.categories.create'))->assertForbidden();
        $this->actingAs($viewer, 'admin')->get(route('dashboard.categories.edit', $category))->assertForbidden();
        $this->actingAs($viewer, 'admin')->post(route('dashboard.categories.store'), ['name' => 'Sneaky'])->assertForbidden();
        $this->actingAs($viewer, 'admin')->put(route('dashboard.categories.update', $category), ['name' => 'Changed', 'slug' => 'changed', 'sort_order' => 9])->assertForbidden();
        $this->actingAs($viewer, 'admin')->delete(route('dashboard.categories.destroy', $category))->assertForbidden();

        $creator = $this->admin(['categories.create']);
        $this->actingAs($creator, 'admin')->put(route('dashboard.categories.update', $category), ['name' => 'Changed', 'slug' => 'changed'])->assertForbidden();
        $this->actingAs($creator, 'admin')->delete(route('dashboard.categories.destroy', $category))->assertForbidden();
        $editor = $this->admin(['categories.edit']);
        $this->actingAs($editor, 'admin')->post(route('dashboard.categories.store'), ['name' => 'Sneaky'])->assertForbidden();
        $this->actingAs($editor, 'admin')->get(route('dashboard.categories.delete', $category))->assertForbidden();

        $this->assertSame($before, $category->fresh()->toArray(), 'A refused request changes nothing.');
        $this->assertSame(1, Category::count());

        auth('admin')->logout();
        $this->post(route('dashboard.categories.store'), ['name' => 'Guest'])->assertRedirect(route('admin.login'));
        $this->assertSame(1, Category::count());
    }

    public function test_cancel_leads_to_a_page_the_admin_may_open(): void
    {
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery']);
        $cases = [
            'create only' => [['categories.create'], 'create', route('dashboard.home')],
            'edit only' => [['categories.edit'], 'edit', route('dashboard.home')],
            'view + create' => [['categories.view', 'categories.create'], 'create', route('dashboard.categories.index')],
            'view + edit' => [['categories.view', 'categories.edit'], 'edit', route('dashboard.categories.index')],
        ];

        foreach ($cases as $label => [$abilities, $page, $cancel]) {
            $admin = $this->admin($abilities);
            $url = $page === 'create' ? route('dashboard.categories.create') : route('dashboard.categories.edit', $category);
            $html = $this->actingAs($admin, 'admin')->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('<a href="'.$cancel.'" class="btn btn-light-secondary">Cancel</a>', $html, $label);
            // The Cancel target opens for this admin (no 403).
            $this->actingAs($admin, 'admin')->get($cancel)->assertOk();
        }

        $super = Admin::factory()->create(['super_admin' => true]);
        $this->assertStringContainsString('<a href="'.route('dashboard.categories.index').'" class="btn btn-light-secondary">Cancel</a>', $this->actingAs($super, 'admin')->get(route('dashboard.categories.create'))->getContent());
    }

    public function test_the_destination_ignores_the_referer(): void
    {
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery']);
        $editor = $this->admin(['categories.edit']);
        $creator = $this->admin(['categories.create']);
        $viewerEditor = $this->admin(['categories.view', 'categories.edit']);

        foreach (['https://evil.example/dashboard/categories', route('dashboard.categories.index').'?search=x', '//evil.example/x'] as $referer) {
            $this->actingAs($editor, 'admin')->withHeader('referer', $referer)
                ->put(route('dashboard.categories.update', $category), ['name' => 'Pottery', 'slug' => 'pottery'])
                ->assertRedirect(route('dashboard.categories.edit', $category));
            $this->actingAs($creator, 'admin')->withHeader('referer', $referer)
                ->post(route('dashboard.categories.store'), ['name' => 'C '.md5($referer)])
                ->assertRedirect(route('dashboard.categories.create'));
            $this->actingAs($viewerEditor, 'admin')->withHeader('referer', $referer)
                ->put(route('dashboard.categories.update', $category), ['name' => 'Pottery', 'slug' => 'pottery'])
                ->assertRedirect(route('dashboard.categories.index'));
        }

        // A refused save without view still returns to the form only (AUD-05), not to a foreign Referer.
        $this->actingAs($editor, 'admin')->withHeader('referer', 'https://evil.example/')
            ->put(route('dashboard.categories.update', $category), ['name' => ''])
            ->assertRedirect(route('dashboard.categories.edit', $category))
            ->assertSessionHasErrors('name');
    }

    public function test_no_save_ends_on_a_forbidden_page(): void
    {
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery']);
        $combinations = [
            ['categories.create'], ['categories.edit'], ['categories.create', 'categories.edit'],
            ['categories.view', 'categories.create'], ['categories.view', 'categories.edit'], ['categories.create', 'categories.delete'],
        ];

        foreach ($combinations as $i => $abilities) {
            $admin = $this->admin($abilities);
            $requests = [];
            if (in_array('categories.create', $abilities, true)) {
                $requests[] = $this->actingAs($admin, 'admin')->post(route('dashboard.categories.store'), ['name' => "Made {$i}"]);
            }
            if (in_array('categories.edit', $abilities, true)) {
                $requests[] = $this->actingAs($admin, 'admin')->put(route('dashboard.categories.update', $category), ['name' => "Edited {$i}", 'slug' => 'pottery']);
            }

            foreach ($requests as $response) {
                $response->assertRedirect();
                $this->actingAs($admin, 'admin')->get($response->headers->get('Location'))->assertOk();
            }
        }
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
