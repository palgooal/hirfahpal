<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\TranslationValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DEC-CAT-Q13 (AD-UI-CAT-01C-2A): two category levels, enforced on the server for every create and
 * update request, whatever the form offered.
 */
class CategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private const NOT_ROOT = 'Choose a main category as the parent. Subcategories cannot have subcategories of their own.';

    private const SELF = 'A category cannot be its own parent.';

    private const HAS_CHILDREN = 'This category has subcategories, so it cannot be moved under another category.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    public function test_a_root_category_is_created(): void
    {
        $this->store(['name' => 'Pottery'])->assertRedirect(route('dashboard.categories.index'));

        $this->assertNull(Category::where('name', 'Pottery')->value('parent_id'));
    }

    public function test_a_child_of_a_root_is_created(): void
    {
        $root = $this->category('Pottery');

        $this->store(['name' => 'Jugs', 'parent_id' => $root->id])->assertRedirect(route('dashboard.categories.index'));

        $this->assertSame($root->id, Category::where('name', 'Jugs')->value('parent_id'));
    }

    public function test_a_third_level_is_refused_on_create(): void
    {
        $child = $this->category('Jugs', $this->category('Pottery'));

        $this->store(['name' => 'Small jugs', 'parent_id' => $child->id])->assertSessionHasErrors(['parent_id' => self::NOT_ROOT]);

        $this->assertDatabaseMissing('categories', ['name' => 'Small jugs']);
    }

    public function test_a_third_level_is_refused_when_moving_a_root_without_children_under_a_child(): void
    {
        $child = $this->category('Jugs', $this->category('Pottery'));
        $other = $this->category('Embroidery');

        $this->update($other, ['parent_id' => $child->id])->assertSessionHasErrors(['parent_id' => self::NOT_ROOT]);

        $this->assertNull($other->fresh()->parent_id);
    }

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        $root = $this->category('Pottery');

        $this->update($root, ['parent_id' => $root->id])->assertSessionHasErrors(['parent_id' => self::SELF]);

        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_a_cycle_is_refused(): void
    {
        // A > B; trying B as the parent of A would close the cycle A > B > A.
        $a = $this->category('A');
        $b = $this->category('B', $a);

        $this->update($a, ['parent_id' => $b->id])->assertSessionHasErrors('parent_id');

        $this->assertNull($a->fresh()->parent_id);
        $this->assertSame($a->id, $b->fresh()->parent_id);
    }

    public function test_a_root_with_children_cannot_be_moved_under_another_root(): void
    {
        $pottery = $this->category('Pottery');
        $this->category('Jugs', $pottery);
        $embroidery = $this->category('Embroidery');

        $this->update($pottery, ['parent_id' => $embroidery->id])->assertSessionHasErrors(['parent_id' => self::HAS_CHILDREN]);

        $this->assertNull($pottery->fresh()->parent_id);
    }

    public function test_a_child_can_become_a_root(): void
    {
        $child = $this->category('Jugs', $this->category('Pottery'));

        $this->update($child, ['parent_id' => ''])->assertRedirect(route('dashboard.categories.index'));

        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_a_child_can_move_to_another_root(): void
    {
        $child = $this->category('Jugs', $this->category('Pottery'));
        $glass = $this->category('Glass');

        $this->update($child, ['parent_id' => $glass->id])->assertRedirect(route('dashboard.categories.index'));

        $this->assertSame($glass->id, $child->fresh()->parent_id);
    }

    public function test_moves_never_change_any_active_state(): void
    {
        $pottery = $this->category('Pottery', active: false);
        $glass = $this->category('Glass', active: true);
        $child = $this->category('Jugs', $pottery, active: false);
        $sibling = $this->category('Plates', $pottery, active: true);

        // A request without is_active (e.g. sent directly) keeps the stored state.
        $this->update($child, ['parent_id' => $glass->id], withActive: false)->assertRedirect();
        $this->assertFalse($child->fresh()->is_active);
        $this->assertSame($glass->id, $child->fresh()->parent_id);

        $this->update($child, ['parent_id' => ''], withActive: false)->assertRedirect();
        $this->assertFalse($child->fresh()->is_active);

        $this->assertFalse($pottery->fresh()->is_active);
        $this->assertTrue($glass->fresh()->is_active);
        $this->assertTrue($sibling->fresh()->is_active);
    }

    public function test_a_missing_parent_is_refused(): void
    {
        $this->store(['name' => 'Orphan', 'parent_id' => 999999])->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('categories', ['name' => 'Orphan']);
    }

    public function test_permissions_are_enforced_and_direct_requests_are_validated(): void
    {
        $root = $this->category('Pottery');
        $child = $this->category('Jugs', $root);
        $viewer = $this->admin(abilities: ['categories.view']);

        $this->actingAs($viewer, 'admin')->post(route('dashboard.categories.store'), ['name' => 'X', 'parent_id' => $root->id])->assertForbidden();
        $this->actingAs($viewer, 'admin')->put(route('dashboard.categories.update', $root), ['name' => 'Pottery', 'parent_id' => $child->id])->assertForbidden();
        $this->assertDatabaseMissing('categories', ['name' => 'X']);

        // An editor sending a crafted parent the form would never offer is still refused.
        $editor = $this->admin(abilities: ['categories.view', 'categories.create', 'categories.edit']);
        $this->actingAs($editor, 'admin')->from(route('dashboard.categories.index'))
            ->post(route('dashboard.categories.store'), ['name' => 'Deep', 'parent_id' => $child->id, 'is_active' => 1])
            ->assertSessionHasErrors(['parent_id' => self::NOT_ROOT]);
        $this->assertDatabaseMissing('categories', ['name' => 'Deep']);
    }

    public function test_the_form_offers_only_main_categories_and_none_for_a_parent(): void
    {
        $pottery = $this->category('Pottery');
        $jugs = $this->category('Jugs', $pottery);
        $glass = $this->category('Glass');

        $create = $this->actingAs($this->admin(superAdmin: true), 'admin')->get(route('dashboard.categories.create'))->assertOk()->getContent();
        $this->assertStringContainsString('<option value="'.$pottery->id.'"', $create);
        $this->assertStringContainsString('<option value="'.$glass->id.'"', $create);
        $this->assertStringNotContainsString('<option value="'.$jugs->id.'"', $create);

        $editParent = $this->actingAs($this->admin(superAdmin: true), 'admin')->get(route('dashboard.categories.edit', $pottery))->assertOk()->getContent();
        $this->assertStringNotContainsString('<option value="'.$glass->id.'"', $editParent, 'A category with children is offered no parent.');

        $editChild = $this->actingAs($this->admin(superAdmin: true), 'admin')->get(route('dashboard.categories.edit', $jugs))->assertOk()->getContent();
        $this->assertStringContainsString('<option value="'.$pottery->id.'" selected', $editChild);
        $this->assertStringNotContainsString('<option value="'.$jugs->id.'"', $editChild);
    }

    public function test_hierarchy_messages_do_not_auto_create_translation_rows(): void
    {
        $child = $this->category('Jugs', $this->category('Pottery'));
        $before = TranslationValue::count();

        $this->store(['name' => 'Deep', 'parent_id' => $child->id])->assertSessionHasErrors('parent_id');

        $this->assertSame(0, TranslationValue::where('key', 'like', 'dashboard.Category_parent_%')->count());
        $this->assertSame($before, TranslationValue::count());
    }

    private function category(string $name, ?Category $parent = null, bool $active = true): Category
    {
        return Category::create(['name' => $name, 'slug' => (string) str($name)->slug(), 'parent_id' => $parent?->id, 'is_active' => $active]);
    }

    private function store(array $data)
    {
        return $this->actingAs($this->admin(superAdmin: true), 'admin')
            ->from(route('dashboard.categories.create'))
            ->post(route('dashboard.categories.store'), $data + ['is_active' => 1]);
    }

    private function update(Category $category, array $data, bool $withActive = true)
    {
        $payload = $data + ['name' => $category->name, 'slug' => $category->slug];

        if ($withActive) {
            $payload['is_active'] = $category->is_active ? 1 : 0;
        }

        return $this->actingAs($this->admin(superAdmin: true), 'admin')
            ->from(route('dashboard.categories.edit', $category))
            ->put(route('dashboard.categories.update', $category), $payload);
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
