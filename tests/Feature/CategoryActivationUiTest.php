<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ConfirmsCategoryDeletion;
use Tests\TestCase;

/**
 * DEC-CAT-Q16 (AD-UI-CAT-01D-3): activation is not managed from the admin categories UI. New categories are
 * always active; updates keep the stored state; is_active sent in any request is ignored.
 */
class CategoryActivationUiTest extends TestCase
{
    use ConfirmsCategoryDeletion;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    public function test_a_new_category_is_active(): void
    {
        $this->post(...$this->store(['name' => 'Pottery']))->assertRedirect(route('dashboard.categories.index'))->assertSessionHasNoErrors();

        $this->assertTrue(Category::where('name', 'Pottery')->firstOrFail()->is_active);
    }

    public function test_a_switched_off_value_sent_on_create_is_ignored(): void
    {
        foreach ([0, '0', false, 'false', 'off', ''] as $index => $off) {
            $name = 'Created '.$index;

            $this->post(...$this->store(['name' => $name, 'is_active' => $off]))
                ->assertRedirect(route('dashboard.categories.index'))
                ->assertSessionHasNoErrors();

            $this->assertTrue(Category::where('name', $name)->firstOrFail()->is_active, var_export($off, true));
        }
    }

    public function test_an_update_keeps_an_active_category_active_whatever_is_sent(): void
    {
        $category = $this->category('Pottery', active: true);
        $this->product($category);

        foreach ([0, '0', false, 'false', null] as $index => $off) {
            $this->put(...$this->update($category, ['name' => 'Renamed '.$index, 'is_active' => $off]))->assertSessionHasNoErrors();

            $fresh = $category->fresh();
            $this->assertSame('Renamed '.$index, $fresh->name, 'The other fields are saved.');
            $this->assertTrue($fresh->is_active, var_export($off, true));
        }
    }

    public function test_an_update_keeps_a_previously_inactive_category_inactive(): void
    {
        // Created inactive before this decision; the decision does not change existing states.
        $category = $this->category('Dormant', active: false);

        $this->put(...$this->update($category, ['name' => 'Still dormant', 'is_active' => 1]))->assertSessionHasNoErrors();
        $this->put(...$this->update($category->fresh(), ['description' => 'Edited', 'is_active' => true]))->assertSessionHasNoErrors();

        $fresh = $category->fresh();
        $this->assertSame('Still dormant', $fresh->name);
        $this->assertSame('Edited', $fresh->description);
        $this->assertFalse($fresh->is_active);
    }

    public function test_direct_requests_cannot_change_the_state_and_permissions_still_apply(): void
    {
        $active = $this->category('Active', active: true);
        $inactive = $this->category('Inactive', active: false);
        $editor = $this->admin(abilities: ['categories.view', 'categories.create', 'categories.edit']);

        $this->actingAs($editor, 'admin')->put(route('dashboard.categories.update', $active), ['name' => 'Active', 'is_active' => 0])->assertSessionHasNoErrors();
        $this->actingAs($editor, 'admin')->patch(route('dashboard.categories.update', $inactive), ['name' => 'Inactive', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->actingAs($editor, 'admin')->post(route('dashboard.categories.store'), ['name' => 'Crafted', 'is_active' => 0])->assertSessionHasNoErrors();

        $this->assertTrue($active->fresh()->is_active);
        $this->assertFalse($inactive->fresh()->is_active);
        $this->assertTrue(Category::where('name', 'Crafted')->firstOrFail()->is_active);

        $viewer = $this->admin(abilities: ['categories.view']);
        $this->actingAs($viewer, 'admin')->put(route('dashboard.categories.update', $active), ['name' => 'Active', 'is_active' => 0])->assertForbidden();
        $this->actingAs($viewer, 'admin')->post(route('dashboard.categories.store'), ['name' => 'Forbidden'])->assertForbidden();
        $this->assertDatabaseMissing('categories', ['name' => 'Forbidden']);
    }

    public function test_the_forms_offer_no_activation_option(): void
    {
        $category = $this->category('Pottery', active: true);
        $admin = $this->admin(superAdmin: true);

        foreach ([route('dashboard.categories.create'), route('dashboard.categories.edit', $category)] as $url) {
            $form = $this->form($this->actingAs($admin, 'admin')->get($url)->assertOk()->getContent());

            $this->assertStringNotContainsString('name="is_active"', $form, $url);
            $this->assertStringNotContainsString('type="checkbox"', $form, $url);
        }
    }

    public function test_the_list_has_no_activation_action(): void
    {
        $this->category('Pottery', active: true);

        $html = $this->actingAs($this->admin(superAdmin: true), 'admin')->get(route('dashboard.categories.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('type="checkbox"', $html);
        $this->assertDoesNotMatchRegularExpression('#<form[^>]*method="POST"[^>]*>(?:(?!</form>).)*name="is_active"#is', $html, 'No form posts an activation change.');
    }

    public function test_existing_states_are_kept_by_unrelated_operations(): void
    {
        $root = $this->category('Root', active: false);
        $child = $this->category('Child', active: true, parent: $root);
        $other = $this->category('Other', active: true);

        // Move the child to another main category, then back to a root.
        $this->put(...$this->update($child, ['parent_id' => $other->id]))->assertSessionHasNoErrors();
        $this->put(...$this->update($child->fresh(), ['parent_id' => '']))->assertSessionHasNoErrors();

        $this->assertFalse($root->fresh()->is_active);
        $this->assertTrue($child->fresh()->is_active);
        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_deletion_and_hierarchy_rules_still_apply(): void
    {
        $root = $this->category('Root', active: true);
        $child = $this->category('Child', active: true, parent: $root);
        $withProduct = $this->category('With product', active: true);
        $this->product($withProduct);
        $admin = $this->admin(superAdmin: true);

        $this->confirmedDelete($admin, $root)
            ->assertSessionHas('danger', 'This category has subcategories and cannot be deleted. Move or delete them first.');
        $this->confirmedDelete($admin, $withProduct)
            ->assertSessionHas('danger', 'This category cannot be deleted because it has associated products.');
        $this->post(...$this->store(['name' => 'Third level', 'parent_id' => $child->id]))->assertSessionHasErrors('parent_id');

        $this->assertDatabaseHas('categories', ['id' => $root->id]);
        $this->assertDatabaseHas('categories', ['id' => $withProduct->id]);
        $this->assertDatabaseMissing('categories', ['name' => 'Third level']);
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function store(array $data): array
    {
        $this->actingAs($this->admin(superAdmin: true), 'admin')->from(route('dashboard.categories.create'));

        return [route('dashboard.categories.store'), $data];
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function update(Category $category, array $data): array
    {
        $this->actingAs($this->admin(superAdmin: true), 'admin')->from(route('dashboard.categories.edit', $category));

        return [route('dashboard.categories.update', $category), $data + ['name' => $category->name, 'slug' => $category->slug, 'parent_id' => $category->parent_id]];
    }

    private function form(string $html): string
    {
        $this->assertSame(1, preg_match('#<form method="POST"[^>]*>.*?</form>#s', $html, $match), 'Category form not rendered.');

        return $match[0];
    }

    private function category(string $name, bool $active, ?Category $parent = null): Category
    {
        return Category::create(['name' => $name, 'slug' => (string) str($name)->slug(), 'parent_id' => $parent?->id, 'is_active' => $active]);
    }

    private function product(Category $category): void
    {
        DB::table('products')->insert(['vendor_id' => Vendor::factory()->create()->id, 'category_id' => $category->id, 'name' => 'Product '.$category->id, 'slug' => 'product-'.$category->id, 'price' => 10]);
    }

    private function admin(bool $superAdmin = false, array $abilities = []): Admin
    {
        $number = Admin::query()->count() + 1;
        $admin = Admin::create([
            'name' => 'Admin '.$number,
            'email' => 'admin'.$number.'@example.com',
            'phone' => '05990000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'password' => 'password',
            'status' => 'active',
            'super_admin' => $superAdmin,
        ]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }
}
