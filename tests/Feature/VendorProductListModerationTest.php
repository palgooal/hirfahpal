<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
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
 * VENDOR-PRODUCT-PUBLISH-UI-01E (DETAIL-02, DETAIL-03): each row of the vendor's products list carries the same
 * read-only moderation object as the product details (UI-01C), {archived: bool, suspended: bool}, added after the
 * product's own serialization; the list shows a translated badge per true flag next to the publication status. The
 * rest of the list (fields, filters, paging, order, ownership, actions, texts) is unchanged, and no date, reason or
 * admin is exposed. The badge logic is unit tested in tests/js/vendor-resource-list.test.mjs and checked in a browser.
 */
class VendorProductListModerationTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = 'resources/js/vendor-resource-list.js';

    private Vendor $vendor;

    private Admin $admin;

    private string $hotFile = '';

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
        $this->admin = Admin::create(['name' => 'Moderator', 'email' => 'moderator@example.com', 'phone' => '0599000556', 'password' => 'password', 'status' => 'active', 'super_admin' => true]);
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    // ---------------------------------------------------------------- the list contract

    public function test_each_row_carries_its_two_flags_as_booleans(): void
    {
        $ids = $this->fourStates();

        $response = $this->list()->assertOk();
        $rows = collect($response->json('products.data'))->keyBy('id');

        $this->assertSame(['archived' => false, 'suspended' => false], $rows[$ids['neither']]['moderation']);
        $this->assertSame(['archived' => false, 'suspended' => true], $rows[$ids['suspended']]['moderation']);
        $this->assertSame(['archived' => true, 'suspended' => false], $rows[$ids['archived']]['moderation']);
        $this->assertSame(['archived' => true, 'suspended' => true], $rows[$ids['both']]['moderation']);
        $this->assertStringContainsString('"moderation":{"archived":true,"suspended":true}', $response->getContent(), 'Real JSON booleans.');
        $this->assertStringNotContainsString('"archived":"', $response->getContent());
    }

    public function test_every_other_field_of_a_row_is_the_products_own_serialization_as_before(): void
    {
        $this->fourStates();

        $rows = $this->list()->json('products.data');
        $expected = Product::query()->with(['category', 'primaryImage'])->where('vendor_id', $this->vendor->id)->latest()->get()
            ->map(fn (Product $product) => json_decode(json_encode($product), true))->all();

        $this->assertSame($expected, array_map(fn (array $row) => array_diff_key($row, ['moderation' => true]), $rows));
        foreach ($rows as $row) {
            $this->assertSame(array_merge(array_keys(json_decode(json_encode(Product::find($row['id'])->load(['category', 'primaryImage'])), true)), ['moderation']), array_keys($row), 'moderation is added last; nothing else changes.');
            $this->assertSame($row['status'], Product::find($row['id'])->status, 'The publication status is unchanged.');
        }
    }

    public function test_no_date_reason_or_admin_is_in_the_list(): void
    {
        $this->fourStates();
        $content = $this->list()->getContent();

        foreach (['suspended_at', 'archived_at', 'Counterfeit listing', 'Duplicate listing', 'Moderator', 'moderator@example.com', 'reason', 'admin'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $content, $hidden);
        }
    }

    public function test_the_top_level_keys_paging_filters_and_order_are_unchanged(): void
    {
        $ids = $this->fourStates();
        $category = Category::create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        Product::query()->whereKey($ids['suspended'])->update(['category_id' => $category->id]);

        $page = $this->list(['per_page' => 2])->assertOk();
        $this->assertSame(['products', 'filters'], array_keys($page->json()));
        $this->assertSame(['current_page', 'data', 'first_page_url', 'from', 'last_page', 'last_page_url', 'links', 'next_page_url', 'path', 'per_page', 'prev_page_url', 'to', 'total'], array_keys($page->json('products')));
        $this->assertSame([2, 4, 2, 2], [count($page->json('products.data')), $page->json('products.total'), $page->json('products.per_page'), $page->json('products.last_page')]);
        $this->assertStringContainsString('per_page=2', $page->json('products.next_page_url'), 'The query string is kept.');
        $this->assertSame([['archived' => true, 'suspended' => true], ['archived' => true, 'suspended' => false]], array_column($page->json('products.data'), 'moderation'), 'Newest first, as before.');
        $this->assertSame(['categories', 'statuses'], array_keys($page->json('filters')));

        $this->assertSame([$ids['suspended']], array_column($this->list(['category_id' => $category->id])->json('products.data'), 'id'));
        $this->assertSame([$ids['archived']], array_column($this->list(['search' => 'Archived jug'])->json('products.data'), 'id'));
        $this->assertSame([$ids['neither']], array_column($this->list(['status' => 'draft'])->json('products.data'), 'id'));
    }

    public function test_only_the_vendors_own_products_are_listed(): void
    {
        $this->fourStates();
        $other = Vendor::factory()->approved()->create();
        $theirs = Product::create(['vendor_id' => $other->id, 'name' => 'Their jug', 'slug' => 'their-jug', 'price' => 10, 'stock_quantity' => 1, 'status' => 'active']);
        $theirs->forceFill(['archived_at' => now()])->save();

        $this->assertNotContains($theirs->id, array_column($this->list()->json('products.data'), 'id'));
        $this->assertSame([$theirs->id], array_column($this->actingAs($other, 'vendor')->getJson(route('vendor.dashboard.products.index'))->json('products.data'), 'id'));
    }

    public function test_the_details_answer_and_the_storefront_are_unchanged(): void
    {
        $ids = $this->fourStates();

        $show = $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.products.show', $ids['suspended']))->assertOk();
        $this->assertSame(['product', 'available_stock', 'reserved_stock', 'moderation'], array_keys($show->json()));
        $this->assertArrayNotHasKey('moderation', $show->json('product'));

        Product::create(['vendor_id' => $this->vendor->id, 'name' => 'Visible jug', 'slug' => 'visible-jug', 'price' => 10, 'stock_quantity' => 2, 'status' => 'active', 'published_at' => now()]);
        $store = $this->getJson(route('shop.products.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Visible jug', $store);
        $this->assertStringNotContainsString('moderation', $store);
        $this->assertStringNotContainsString('Suspended jug', $store, 'A suspended active product is still not on sale.');
        $this->assertStringNotContainsString('Both jug', $store);
    }

    // ---------------------------------------------------------------- the page and the script

    public function test_the_page_gives_the_badge_texts_in_both_languages_and_creates_no_rows(): void
    {
        $before = TranslationValue::count();

        foreach (['ar' => ['rtl', 'مؤرشف', 'معلّق إداريًا'], 'en' => ['ltr', 'Archived', 'Suspended by administration']] as $locale => [$dir, $archived, $suspended]) {
            $html = $this->actingAs($this->vendor, 'vendor')->withSession(['locale' => $locale])->get(route('vendor.dashboard.products.page'))->assertOk()->getContent();
            $this->assertMatchesRegularExpression("/<html lang=\"{$locale}\" dir=\"{$dir}\"/", $html);
            $this->assertSame(1, preg_match('/data-moderation-texts="([^"]*)"/', $html, $match));
            $this->assertSame(['archived' => $archived, 'suspended' => $suspended], json_decode(html_entity_decode($match[1]), true, flags: JSON_THROW_ON_ERROR));
        }

        $this->assertSame($before, TranslationValue::count());
        foreach (['vendor.Product_Badge_Archived', 'vendor.Product_Badge_Suspended'] as $key) {
            $this->assertSame(['ar', 'en'], TranslationValue::query()->where('key', $key)->orderBy('locale')->pluck('locale')->all());
        }

        // Only the products list gets them.
        $orders = $this->actingAs($this->vendor, 'vendor')->get(route('vendor.dashboard.orders.page'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-moderation-texts', $orders);
    }

    public function test_the_script_adds_text_badges_after_the_status_and_keeps_the_actions(): void
    {
        $script = file_get_contents(base_path(self::SCRIPT));

        $this->assertStringContainsString('cell.textContent = text(statusLabel(value, texts));', $script, 'The publication status is shown as before.');
        $this->assertMatchesRegularExpression('/moderationBadges\(item\.moderation, badges\)\.forEach\(\(\[kind, label\]\) => \{.*?badge\.textContent = label;\s*cell\.append\(\' \', badge\);/s', $script);
        $this->assertStringNotContainsString('innerHTML', $script);
        $this->assertStringNotContainsString('insertAdjacentHTML', $script);
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $script);
        foreach (["'Archived'", "'Suspended by administration'"] as $english) {
            $this->assertStringNotContainsString($english, $script);
        }
        // Edit and Delete: the same buttons, hooks and order (OPEN-PROD-08: deletion unchanged).
        $this->assertStringContainsString("[[texts.edit, 'edit', texts.editNamed], [texts.delete, 'delete', texts.deleteNamed]].forEach(", $script);
        $this->assertStringContainsString('button.dataset.productAction = action; button.dataset.productId = item.id;', $script);
    }

    // ---------------------------------------------------------------- helpers

    /** @return array<string, int> one product per state, the last created (newest) is "both" */
    private function fourStates(): array
    {
        $ids = [];
        foreach (['neither' => [[], 'draft', 'Plain jug'], 'suspended' => [['suspend'], 'active', 'Suspended jug'], 'archived' => [['archive'], 'inactive', 'Archived jug'], 'both' => [['suspend', 'archive'], 'active', 'Both jug']] as $key => [$actions, $status, $name]) {
            $product = Product::create(['vendor_id' => $this->vendor->id, 'name' => $name, 'slug' => str_replace(' ', '-', strtolower($name)), 'price' => 10, 'stock_quantity' => 2, 'status' => $status, 'published_at' => $status === 'active' ? now() : null]);
            $product->forceFill(['created_at' => now()->addMinutes(count($ids))])->save();
            foreach ($actions as $action) {
                [$route, $field, $reason] = $action === 'suspend'
                    ? ['dashboard.products.suspend', 'reason', 'Counterfeit listing']
                    : ['dashboard.products.archive', 'archive_reason', 'Duplicate listing'];
                $this->actingAs($this->admin, 'admin')->post(route($route, $product->id), [$field => $reason])->assertRedirect();
            }
            $ids[$key] = $product->id;
        }

        return $ids;
    }

    private function list(array $query = [])
    {
        return $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.products.index', $query));
    }
}
