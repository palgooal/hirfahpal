<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Product;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * VENDOR-UI-CLEANUP-01A (F-3): the vendor products list's "Actions" column and its Edit / Delete buttons were English
 * in Arabic, hard-coded in resources/js/vendor-resource-list.js. The products page now gives the script its translated
 * texts (data-list-texts); the other lists are unchanged. Editing and deleting keep their hooks and endpoints.
 */
class VendorProductListTextsTest extends TestCase
{
    use RefreshDatabase;

    private const KEYS = ['vendor.Product_List_Actions' => ['الإجراءات', 'Actions'], 'vendor.Product_List_Edit' => ['تعديل', 'Edit'], 'vendor.Product_List_Delete' => ['حذف', 'Delete']];

    private string $hotFile = '';

    private Vendor $vendor;

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
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    public function test_the_products_list_texts_in_arabic_rtl_and_english_ltr(): void
    {
        $ar = $this->page('ar', 'products.page');
        $this->assertMatchesRegularExpression('/<html lang="ar" dir="rtl"/', $ar);
        $this->assertSame(['actions' => 'الإجراءات', 'edit' => 'تعديل', 'delete' => 'حذف'], $this->texts($ar));

        $en = $this->page('en', 'products.page');
        $this->assertMatchesRegularExpression('/<html lang="en" dir="ltr"/', $en);
        $this->assertSame(['actions' => 'Actions', 'edit' => 'Edit', 'delete' => 'Delete'], $this->texts($en));
    }

    public function test_the_keys_are_seeded_once_in_both_languages_and_rendering_creates_no_rows(): void
    {
        $source = file_get_contents(database_path('seeders/VendorDashboardTranslationSeeder.php'));

        foreach (self::KEYS as $key => [$arabic, $english]) {
            $this->assertSame(['ar' => $arabic, 'en' => $english], VendorDashboardTranslationSeeder::TRANSLATIONS[$key]);
            $this->assertSame(1, substr_count($source, "'{$key}'"), "{$key} is declared once (a repeated array key would silently replace the first).");
            $this->assertSame(2, TranslationValue::where('key', $key)->count(), $key);
        }

        $before = TranslationValue::count();
        $this->page('ar', 'products.page');
        $this->page('en', 'products.page');
        $this->assertSame($before, TranslationValue::count());
    }

    public function test_only_the_products_list_gets_the_texts(): void
    {
        foreach (['orders.page', 'reviews.page', 'returns.page', 'disputes.page', 'commissions.page'] as $page) {
            $this->assertStringNotContainsString('data-list-texts', $this->page('ar', $page), $page);
        }
    }

    public function test_the_script_uses_the_page_texts_and_keeps_the_edit_and_delete_hooks(): void
    {
        $source = file_get_contents(resource_path('js/vendor-resource-list.js'));

        $this->assertStringContainsString('const texts = listTexts(root.dataset.listTexts);', $source);
        $this->assertStringContainsString('th.textContent = texts.actions;', $source);
        $this->assertStringContainsString("[[texts.edit, 'edit'], [texts.delete, 'delete']]", $source);
        $this->assertStringNotContainsString("th.textContent = 'Actions'", $source);
        $this->assertStringNotContainsString("[['Edit', 'edit'], ['Delete', 'delete']]", $source);
        // The buttons the editor listens for are unchanged (vendor-product-editor.js: [data-product-action]).
        $this->assertStringContainsString('button.dataset.productAction = action; button.dataset.productId = item.id;', $source);
        $this->assertStringContainsString("closest('[data-product-action]')", file_get_contents(resource_path('js/vendor-product-editor.js')));
    }

    public function test_editing_and_deleting_a_product_still_work_through_the_same_endpoints(): void
    {
        $product = Product::create(['vendor_id' => $this->vendor->id, 'name' => 'Jug', 'slug' => 'jug-'.uniqid(), 'price' => 10, 'stock_quantity' => 2, 'status' => 'draft']);
        $this->actingAs($this->vendor, 'vendor');

        $this->getJson(route('vendor.dashboard.products.show', $product))->assertOk()->assertJsonPath('product.id', $product->id);
        $this->deleteJson(route('vendor.dashboard.products.destroy', $product))->assertOk();
        $this->assertModelMissing($product);
    }

    public function test_the_script_passes_its_node_tests(): void
    {
        $node = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node' : 'command -v node'));
        $node = strtok($node, "\r\n") ?: 'node';

        $process = proc_open([$node, '--test', 'tests/js/vendor-resource-list.test.mjs'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), $output);
        $this->assertMatchesRegularExpression('/^(?:ℹ|#) fail 0$/m', $output);
        $this->assertDoesNotMatchRegularExpression('/^(?:ℹ|#) pass 0$/m', $output);
    }

    private function page(string $locale, string $route): string
    {
        return $this->actingAs($this->vendor, 'vendor')->withSession(['locale' => $locale])->get(route('vendor.dashboard.'.$route))->assertOk()->getContent();
    }

    /** @return array<string, string> */
    private function texts(string $html): array
    {
        $this->assertSame(1, preg_match('#data-list-texts="([^"]*)"#', $html, $match));

        return json_decode(html_entity_decode($match[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
    }
}
