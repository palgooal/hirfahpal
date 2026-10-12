<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Vendor;
use Database\Seeders\AdminCategoryTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DEC-CAT-Q15 (AD-UI-CAT-01D-1, 01D-2): the internal deactivation rule. Since DEC-CAT-Q16 (AD-UI-CAT-01D-3)
 * activation is not managed from the admin UI, so the rule is no longer reachable through the category
 * requests (see CategoryActivationUiTest); it is kept for a future dedicated action and checked here directly.
 */
class CategoryDeactivationProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_products_of_any_status_block_deactivation(): void
    {
        foreach (['active', 'inactive', 'draft', 'out_of_stock'] as $status) {
            $category = $this->category('Direct '.$status);
            $this->product($category, $status);

            $this->assertTrue($category->hasAssociatedProducts(), $status);
        }
    }

    public function test_products_of_any_subcategory_block_deactivating_the_main_category(): void
    {
        foreach (['active', 'inactive', 'draft', 'out_of_stock'] as $status) {
            $root = $this->category('Root '.$status);
            $this->category('Empty child '.$status, $root);
            $child = $this->category('Child '.$status, $root);
            $this->product($child, $status);

            $this->assertTrue($root->hasAssociatedProducts(), $status);
            $this->assertTrue($child->hasAssociatedProducts(), $status);
        }
    }

    public function test_empty_categories_and_main_categories_with_empty_subcategories_can_be_deactivated(): void
    {
        $root = $this->category('Root');
        $this->category('First', $root);
        $this->category('Second', $root);
        $empty = $this->category('Empty');

        // Products elsewhere do not count.
        $this->product($this->category('Other'), 'active');

        $this->assertFalse($root->hasAssociatedProducts());
        $this->assertFalse($empty->hasAssociatedProducts());
    }

    public function test_a_subcategory_only_counts_its_own_products(): void
    {
        $root = $this->category('Root');
        $this->product($root, 'active');
        $empty = $this->category('Empty child', $root);

        $this->assertTrue($root->hasAssociatedProducts());
        $this->assertFalse($empty->hasAssociatedProducts(), 'The parent\'s own products do not block a subcategory.');
    }

    public function test_the_deactivation_message_is_kept_for_the_future_action(): void
    {
        $this->assertSame(
            ['ar' => 'لا يمكن تعطيل هذا التصنيف لأنه مرتبط بمنتجات.', 'en' => 'This category has products and cannot be deactivated.'],
            AdminCategoryTranslationSeeder::TRANSLATIONS['dashboard.Category_deactivate_has_products']
        );
        $this->assertStringContainsString(
            "'dashboard.Category_deactivate_has_products', 'This category has products and cannot be deactivated.'",
            file_get_contents(app_path('Http/Controllers/Dashboard/CategoryManagementController.php'))
        );
    }

    private function category(string $name, ?Category $parent = null): Category
    {
        return Category::create(['name' => $name, 'slug' => (string) str($name)->slug(), 'parent_id' => $parent?->id, 'is_active' => true]);
    }

    private function product(Category $category, string $status): void
    {
        DB::table('products')->insert([
            'vendor_id' => Vendor::factory()->create()->id,
            'category_id' => $category->id,
            'name' => 'Product '.$category->id.' '.$status,
            'slug' => 'product-'.$category->id.'-'.$status,
            'price' => 10,
            'status' => $status,
        ]);
    }
}
