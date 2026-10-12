<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\ConfirmsCategoryDeletion;
use Tests\TestCase;

/**
 * DEC-CAT-Q3 (AD-UI-CAT-01C-1E): category deletion is refused with a clear reason when the category
 * has products (any status) or children; only an empty category is deleted; database foreign key
 * refusals become messages, never a 500; unrelated database errors are not disguised.
 */
class CategoryDeletionProtectionTest extends TestCase
{
    use ConfirmsCategoryDeletion;
    use RefreshDatabase;

    private const HAS_PRODUCTS = 'This category cannot be deleted because it has associated products.';

    private const HAS_CHILDREN = 'This category has subcategories and cannot be deleted. Move or delete them first.';

    private const IN_USE = 'This category is in use and cannot be deleted.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
    }

    public function test_a_category_with_an_active_product_is_not_deleted(): void
    {
        [$category, $product] = $this->categoryWithProduct('active');

        $this->deleteCategory($category)
            ->assertRedirect(route('dashboard.categories.index'))
            ->assertSessionHas('danger', self::HAS_PRODUCTS)
            ->assertSessionMissing('success');

        $this->assertRelationsKept($category, $product);
    }

    public function test_a_category_with_an_inactive_product_is_not_deleted(): void
    {
        foreach (['inactive', 'draft', 'out_of_stock'] as $status) {
            [$category, $product] = $this->categoryWithProduct($status);

            $this->deleteCategory($category)->assertSessionHas('danger', self::HAS_PRODUCTS);
            $this->assertRelationsKept($category, $product);
        }
    }

    public function test_a_parent_with_a_child_is_not_deleted(): void
    {
        $parent = Category::create(['name' => 'Parent', 'slug' => 'parent']);
        $child = Category::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id]);

        $this->deleteCategory($parent)->assertSessionHas('danger', self::HAS_CHILDREN);

        $this->assertDatabaseHas('categories', ['id' => $parent->id]);
        $this->assertSame($parent->id, $child->fresh()->parent_id);
    }

    public function test_an_empty_category_is_deleted(): void
    {
        $category = Category::create(['name' => 'Empty', 'slug' => 'empty']);

        $this->deleteCategory($category)
            ->assertRedirect(route('dashboard.categories.index'))
            ->assertSessionHas('success')
            ->assertSessionMissing('danger');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_an_admin_without_the_delete_ability_cannot_delete(): void
    {
        $category = Category::create(['name' => 'Empty', 'slug' => 'empty']);
        $viewer = $this->admin(abilities: ['categories.view', 'categories.edit']);

        $this->actingAs($viewer, 'admin')->delete(route('dashboard.categories.destroy', $category))->assertForbidden();
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_delete_route_keeps_web_csrf_admin_guard_and_ability(): void
    {
        $middleware = Route::getRoutes()->getByName('dashboard.categories.destroy')->gatherMiddleware();

        $this->assertContains('web', $middleware, 'The web group carries CSRF verification.');
        $this->assertContains('auth:admin', $middleware);
        $this->post(route('dashboard.categories.destroy', Category::create(['name' => 'X', 'slug' => 'x'])), ['_method' => 'DELETE'])
            ->assertRedirect(route('admin.login'));
    }

    public function test_a_foreign_key_refusal_after_the_check_becomes_a_message_not_a_500(): void
    {
        $category = Category::create(['name' => 'Raced', 'slug' => 'raced']);
        $vendor = Vendor::factory()->create();

        // A product that appears after the relation check (what a concurrent insert looks like on SQLite,
        // which has no row locks): the database refuses the delete with ON DELETE RESTRICT.
        Category::deleting(function (Category $deleting) use ($vendor) {
            DB::table('products')->insert(['vendor_id' => $vendor->id, 'category_id' => $deleting->id, 'name' => 'Late', 'slug' => 'late', 'price' => 1]);
        });

        $this->deleteCategory($category)
            ->assertRedirect(route('dashboard.categories.index'))
            ->assertSessionHas('danger', self::IN_USE);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_an_unrelated_database_error_is_not_reported_as_a_category_in_use(): void
    {
        $category = Category::create(['name' => 'Broken', 'slug' => 'broken']);
        Category::deleting(fn () => DB::select('select * from table_that_does_not_exist'));

        $this->withoutExceptionHandling();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/table_that_does_not_exist/');

        try {
            $this->deleteCategory($category);
        } finally {
            $this->assertDatabaseHas('categories', ['id' => $category->id]);
        }
    }

    public function test_a_parent_deleted_after_validation_is_reported_on_the_field(): void
    {
        $parent = Category::create(['name' => 'Parent', 'slug' => 'parent']);

        // The parent disappears between validation and save: another request deletes it right after the
        // exists check. (Deleting it inside this request's own transaction, after the parent is locked, cannot
        // happen for real; that deletion would also be rolled back with the refused save.)
        $deleted = false;
        DB::listen(function ($query) use ($parent, &$deleted) {
            if (! $deleted && str_contains($query->sql, 'select count(*) as aggregate from "categories"')) {
                $deleted = true;
                DB::table('categories')->where('id', $parent->id)->delete();
            }
        });

        $this->actingAs($this->admin(superAdmin: true), 'admin')
            ->from(route('dashboard.categories.create'))
            ->post(route('dashboard.categories.store'), ['name' => 'Child', 'parent_id' => $parent->id])
            ->assertRedirect(route('dashboard.categories.create'))
            ->assertSessionHasErrors(['parent_id' => 'The selected parent category no longer exists.']);

        $this->assertDatabaseMissing('categories', ['name' => 'Child']);
    }

    public function test_refusal_messages_do_not_auto_create_translation_rows(): void
    {
        [$category] = $this->categoryWithProduct('active');

        $this->deleteCategory($category)->assertSessionHas('danger');

        $this->assertSame(0, TranslationValue::query()->where('key', 'like', 'dashboard.Category_delete_%')->count());
    }

    /** @return array{0: Category, 1: int} */
    private function categoryWithProduct(string $status): array
    {
        $vendor = Vendor::factory()->create();
        $category = Category::create(['name' => 'Cat '.$status, 'slug' => 'cat-'.$status]);
        $product = DB::table('products')->insertGetId(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => $status, 'slug' => 'p-'.$status, 'price' => 10, 'status' => $status]);

        return [$category, $product];
    }

    private function assertRelationsKept(Category $category, int $productId): void
    {
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertSame($category->id, (int) DB::table('products')->where('id', $productId)->value('category_id'));
    }

    private function deleteCategory(Category $category)
    {
        // N-CAT-1: with a server-issued confirmation (CategoryDeleteConfirmationTest covers the confirmation).
        return $this->confirmedDelete($this->admin(superAdmin: true), $category);
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
