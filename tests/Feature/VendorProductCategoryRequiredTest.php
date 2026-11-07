<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * VPM-CATEGORY-REQUIRED-01: a vendor product needs a category on create and on update: an active one, or (update) the
 * one the product already has. Missing, null, unknown and inactive categories are refused with the usual 422 and a
 * message in the page language; nothing is assigned by default and products stored without one are left as they are.
 */
class VendorProductCategoryRequiredTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = 'resources/js/vendor-product-editor.js';

    private string $hotFile = '';

    private Vendor $vendor;

    private Category $active;

    private Category $inactive;

    protected function setUp(): void
    {
        parent::setUp();

        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->seed(AdminAuthTranslationSeeder::class);
        $this->seed(VendorAuthTranslationSeeder::class);
        $this->seed(VendorDashboardTranslationSeeder::class);
        $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot');
        file_put_contents($this->hotFile, 'http://localhost:5173');
        Vite::useHotFile($this->hotFile);

        $this->vendor = Vendor::factory()->approved()->create();
        $this->active = Category::create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $this->inactive = Category::create(['name' => 'Old', 'slug' => 'old', 'is_active' => false]);
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    // ---------------------------------------------------------------- create

    public function test_create_refuses_a_missing_null_unknown_or_inactive_category_and_creates_nothing(): void
    {
        foreach ([[], ['category_id' => null], ['category_id' => ''], ['category_id' => 999999], ['category_id' => 'abc'], ['category_id' => $this->inactive->id]] as $category) {
            $this->as('en')->postJson(route('vendor.dashboard.products.store'), $this->body() + $category)
                ->assertUnprocessable()->assertJsonValidationErrors('category_id');
        }

        $this->assertSame(0, Product::count(), 'No default category, no product.');
    }

    public function test_create_accepts_an_active_category(): void
    {
        $this->as('en')->postJson(route('vendor.dashboard.products.store'), $this->body() + ['category_id' => $this->active->id])
            ->assertCreated()->assertJsonPath('product.category_id', $this->active->id);
    }

    // ---------------------------------------------------------------- update

    public function test_update_refuses_a_missing_null_or_unknown_category_and_leaves_the_product_as_it_was(): void
    {
        $product = $this->product($this->active->id);

        foreach ([[], ['category_id' => null], ['category_id' => 999999]] as $category) {
            $this->as('en')->putJson(route('vendor.dashboard.products.update', $product), $this->body('Renamed') + $category)
                ->assertUnprocessable()->assertJsonValidationErrors('category_id');
        }

        $this->assertSame(['Jug', $this->active->id], [$product->fresh()->name, $product->fresh()->category_id]);
    }

    public function test_an_uncategorized_product_must_be_given_a_category_and_is_otherwise_left_unchanged(): void
    {
        $legacy = $this->product(null);

        $this->as('en')->putJson(route('vendor.dashboard.products.update', $legacy), $this->body('Renamed') + ['category_id' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->assertSame(['Jug', null], [$legacy->fresh()->name, $legacy->fresh()->category_id], 'Not reclassified, not changed.');

        $this->as('en')->putJson(route('vendor.dashboard.products.update', $legacy), $this->body('Renamed') + ['category_id' => $this->active->id])->assertOk();
        $this->assertSame(['Renamed', $this->active->id], [$legacy->fresh()->name, $legacy->fresh()->category_id]);
    }

    public function test_a_product_keeps_its_own_inactive_category_but_cannot_move_to_another_inactive_one(): void
    {
        $product = $this->product($this->inactive->id);
        $otherInactive = Category::create(['name' => 'Older', 'slug' => 'older', 'is_active' => false]);

        $this->as('en')->putJson(route('vendor.dashboard.products.update', $product), $this->body('Kept') + ['category_id' => $this->inactive->id])->assertOk();
        $this->as('en')->putJson(route('vendor.dashboard.products.update', $product), $this->body('Moved') + ['category_id' => $otherInactive->id])
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->assertSame(['Kept', $this->inactive->id], [$product->fresh()->name, $product->fresh()->category_id]);
    }

    public function test_ownership_and_moderation_are_unchanged(): void
    {
        // Another vendor's product: still 404, and its inactive category is no exception for this vendor.
        $other = Vendor::factory()->approved()->create();
        $foreign = Product::create(['vendor_id' => $other->id, 'category_id' => $this->inactive->id, 'name' => 'Theirs', 'slug' => 'theirs', 'price' => 10, 'stock_quantity' => 5, 'status' => 'draft']);
        $this->as('en')->putJson(route('vendor.dashboard.products.update', $foreign), $this->body() + ['category_id' => $this->active->id])->assertNotFound();
        $this->as('en')->putJson(route('vendor.dashboard.products.update', $foreign), $this->body() + ['category_id' => $this->inactive->id])
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->assertSame('Theirs', $foreign->fresh()->name);

        // Archived: still refused with a 422 that names no field (the editor's archived check), with a valid category.
        $archived = $this->product($this->active->id);
        $archived->forceFill(['archived_at' => now()])->save();
        $this->as('en')->putJson(route('vendor.dashboard.products.update', $archived), $this->body('Renamed') + ['category_id' => $this->active->id])
            ->assertUnprocessable()->assertJsonMissingPath('errors');
        $this->assertSame('Jug', $archived->fresh()->name);
    }

    // ---------------------------------------------------------------- messages and the editor

    public function test_the_messages_are_in_the_page_language(): void
    {
        foreach (['ar' => ['يرجى اختيار تصنيف للمنتج.', 'اختر تصنيفًا متاحًا.'], 'en' => ['Please choose a category for the product.', 'Choose an available category.']] as $locale => [$required, $invalid]) {
            $this->as($locale)->postJson(route('vendor.dashboard.products.store'), $this->body())->assertUnprocessable()->assertJsonPath('errors.category_id', [$required]);
            $this->as($locale)->postJson(route('vendor.dashboard.products.store'), $this->body() + ['category_id' => $this->inactive->id])->assertUnprocessable()->assertJsonPath('errors.category_id', [$invalid]);
        }

        foreach (['vendor.Product_Category_Choose', 'vendor.Product_Category_Required', 'vendor.Product_Category_Invalid'] as $key) {
            $this->assertSame(['ar', 'en'], TranslationValue::query()->where('key', $key)->orderBy('locale')->pluck('locale')->all(), $key);
        }
    }

    public function test_the_editor_field_is_required_with_a_prompt_that_is_never_a_choice(): void
    {
        foreach (['ar' => 'جارٍ تحميل التصنيفات…', 'en' => 'Loading categories…'] as $locale => $prompt) {
            $html = $this->as($locale)->get(route('vendor.dashboard.products.page'))->assertOk()->getContent();
            $document = new DOMDocument;
            libxml_use_internal_errors(true);
            $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
            libxml_clear_errors();
            $xpath = new DOMXPath($document);

            $select = $xpath->query('//select[@id="vendor-product-category"]')->item(0);
            $this->assertTrue($select->hasAttribute('required'), $locale);
            // VPM-CATEGORY-RELIABILITY-01: disabled (and busy) until the script has loaded the categories.
            $this->assertTrue($select->hasAttribute('disabled'), $locale);
            $this->assertSame('true', $select->getAttribute('aria-busy'));
            $this->assertContains('vendor-product-category-error', explode(' ', $select->getAttribute('aria-describedby')));
            $options = $xpath->query('./option', $select);
            $this->assertSame(1, $options->length, 'The categories are added by the script.');
            $this->assertSame(['', $prompt, true, true], [$options->item(0)->getAttribute('value'), trim($options->item(0)->textContent), $options->item(0)->hasAttribute('disabled'), $options->item(0)->hasAttribute('selected')]);
            $this->assertSame(1, $xpath->query('//label[@for="vendor-product-category"]/span[@class="vendor-required"][@aria-hidden="true"]')->length, 'The asterisk.');
            $this->assertSame(1, $xpath->query('//p[@id="vendor-product-category-error"][@data-error-for="category_id"][@hidden]')->length);
            $this->assertStringNotContainsString($locale === 'ar' ? 'بدون تصنيف' : 'No category', $html);
            // The loading failure: a message (announced when shown) and Retry, hidden at first.
            $failed = $xpath->query('//div[@data-product-category-failed][@hidden]')->item(0);
            $this->assertNotNull($failed, $locale);
            $this->assertSame('alert', $xpath->query('./p', $failed)->item(0)->getAttribute('role'));
            $this->assertSame('button', $xpath->query('./button[@data-product-category-retry]', $failed)->item(0)->getAttribute('type'));
        }
    }

    // ---------------------------------------------------------------- VPM-CATEGORY-RELIABILITY-01

    public function test_a_product_cannot_take_the_inactive_category_of_another_product_of_the_same_vendor(): void
    {
        $this->product($this->inactive->id);
        $mine = $this->product($this->active->id);

        $this->as('en')->putJson(route('vendor.dashboard.products.update', $mine), $this->body('Moved') + ['category_id' => $this->inactive->id])
            ->assertUnprocessable()->assertJsonPath('errors.category_id', ['Choose an available category.']);
        $this->assertSame(['Jug', $this->active->id], [$mine->fresh()->name, $mine->fresh()->category_id]);
        $this->assertFalse($this->inactive->fresh()->is_active, 'The category itself is not changed.');
    }

    public function test_the_editor_reads_the_active_categories_and_the_products_own_category_from_existing_answers(): void
    {
        $product = $this->product($this->inactive->id);

        // The products endpoint (one product per page) gives the active categories only.
        $this->as('en')->getJson(route('vendor.dashboard.products.index', ['page' => 1, 'per_page' => 1]))->assertOk()
            ->assertJsonPath('filters.categories.0.id', $this->active->id)->assertJsonCount(1, 'filters.categories');
        // A product's read gives its own category, inactive or not (its name for the field).
        $this->as('en')->getJson(route('vendor.dashboard.products.show', $product))->assertOk()
            ->assertJsonPath('product.category_id', $this->inactive->id)->assertJsonPath('product.category.name', 'Old')->assertJsonPath('product.category.is_active', false);
    }

    public function test_the_script_owns_the_category_state_and_ignores_stale_answers(): void
    {
        $script = file_get_contents(base_path(self::SCRIPT));

        // No dependency on when the list loads: the editor reads the categories itself, the latest request only.
        $this->assertStringNotContainsString("'vendor-resource:loaded'", $script);
        $this->assertStringContainsString('const list = categoriesFrom(await fetchJson(`${endpoint}?page=1&per_page=1`, { credentials: \'same-origin\', headers: headers(false) }));', $script);
        $this->assertStringContainsString('if (!categoryTickets.isCurrent(ticket)) return;', $script);
        // The saved value is the editor's state, never what the field happens to show.
        $this->assertStringContainsString("category_id: categoryValue === '' ? null : Number(categoryValue), price:", $script);
        $this->assertStringContainsString("categoryValue = product.category_id === null || product.category_id === undefined ? '' : String(product.category_id);", $script);
        $this->assertStringContainsString("title.textContent = texts.add; categoryValue = ''; ownCategory = null; renderCategories();", $script, 'Add (and closing) starts from no category.');
        $this->assertStringContainsString('categoryField.disabled = activeCategories === null;', $script);
        $this->assertStringContainsString("categoryRetry.addEventListener('click', loadCategories);", $script);
    }

    // ---------------------------------------------------------------- helpers

    private function as(string $locale): static
    {
        return $this->actingAs($this->vendor, 'vendor')->withSession(['locale' => $locale]);
    }

    /** The editor's payload without its category (each test adds the one it sends). */
    private function body(string $name = 'Jug'): array
    {
        return ['name' => $name, 'price' => 10, 'stock_quantity' => 5, 'sku' => null, 'compare_at_price' => null, 'low_stock_threshold' => 1, 'weight' => null, 'status' => 'draft'];
    }

    private function product(?int $categoryId): Product
    {
        return Product::create(['vendor_id' => $this->vendor->id, 'category_id' => $categoryId, 'name' => 'Jug', 'slug' => 'jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 5, 'status' => 'draft']);
    }
}
