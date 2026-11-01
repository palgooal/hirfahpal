<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Product;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * VENDOR-PRODUCT-LIST-PAGINATION-01B: the vendor's products list pages through all of the vendor's products. The API
 * (unchanged in shape) orders newest first with the id breaking ties, so equal timestamps still give one order and
 * every product is on exactly one page; per_page is 1 to 50 (default 15, see ProductController::perPage()). The page
 * has a pager (the media library's controls and texts, carets chosen by direction), shared since ORD-01A with the
 * orders list only (VendorOrderListPaginationTest); the other four lists are unchanged. The pager's logic is unit tested in tests/js/vendor-resource-list.test.mjs and checked in a browser.
 */
class VendorProductListPaginationTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = 'resources/js/vendor-resource-list.js';

    private const EDITOR = 'resources/js/vendor-product-editor.js';

    private Vendor $vendor;

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
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    // ---------------------------------------------------------------- pages, order, ownership

    public function test_forty_one_products_with_the_same_timestamp_are_on_three_pages_once_each_in_one_order(): void
    {
        $ids = $this->products(41, sameSecond: true);
        $other = Vendor::factory()->approved()->create();
        $theirs = $this->productsFor($other, 5, 'Their jug');

        $seen = [];
        foreach ([[1, 15, 1, 15], [2, 15, 16, 30], [3, 11, 31, 41]] as [$page, $count, $from, $to]) {
            $response = $this->list(['page' => $page])->assertOk();
            $this->assertSame([$page, 3, 41, 15, $from, $to], [$response->json('products.current_page'), $response->json('products.last_page'), $response->json('products.total'), $response->json('products.per_page'), $response->json('products.from'), $response->json('products.to')]);
            $this->assertCount($count, $response->json('products.data'));
            $seen = array_merge($seen, array_column($response->json('products.data'), 'id'));
        }

        $this->assertSame(array_reverse($ids), $seen, 'Equal timestamps: id descending, every product exactly once.');
        $this->assertSame([], array_intersect($theirs, $seen), "Never another vendor's product.");
        foreach (range(1, 3) as $page) {
            $this->assertSame(array_slice(array_reverse($ids), ($page - 1) * 15, 15), array_column($this->list(['page' => $page])->json('products.data'), 'id'), "Page {$page} is stable.");
        }
    }

    public function test_newest_first_then_id_with_mixed_timestamps(): void
    {
        $older = $this->products(3, sameSecond: true, at: '2026-10-01 10:00:00');
        $newer = $this->products(3, sameSecond: true, at: '2026-10-02 10:00:00');

        $this->assertSame([...array_reverse($newer), ...array_reverse($older)], array_column($this->list()->json('products.data'), 'id'));
    }

    public function test_another_vendor_sees_only_their_own_pages(): void
    {
        $this->products(20);
        $other = Vendor::factory()->approved()->create();
        $theirs = $this->productsFor($other, 3, 'Their jug');

        $response = $this->actingAs($other, 'vendor')->getJson(route('vendor.dashboard.products.index', ['page' => 1, 'per_page' => 50]))->assertOk();
        $this->assertSame([3, 1], [$response->json('products.total'), $response->json('products.last_page')]);
        $this->assertEqualsCanonicalizing($theirs, array_column($response->json('products.data'), 'id'));
        $this->assertSame([], $this->actingAs($other, 'vendor')->getJson(route('vendor.dashboard.products.index', ['page' => 2]))->json('products.data'));
    }

    // ---------------------------------------------------------------- per_page and page

    public static function perPageValues(): array
    {
        return [
            'missing' => [null, 15], 'one' => ['1', 1], 'twenty' => ['20', 20], 'fifty' => ['50', 50],
            'fifty-one' => ['51', 50], 'huge' => ['100000', 50], 'zero' => ['0', 15], 'negative' => ['-3', 15],
            'decimal' => ['2.5', 15], 'text' => ['abc', 15], 'empty' => ['', 15], 'array' => [['5'], 15],
        ];
    }

    #[DataProvider('perPageValues')]
    public function test_per_page_is_bounded_with_a_safe_default(mixed $given, int $expected): void
    {
        $this->products(60, sameSecond: true);

        $response = $this->list($given === null ? [] : ['per_page' => $given])->assertOk();

        $this->assertSame($expected, $response->json('products.per_page'));
        $this->assertCount($expected, $response->json('products.data'));
        $this->assertSame((int) ceil(60 / $expected), $response->json('products.last_page'));
    }

    public function test_a_page_past_the_end_is_empty_with_the_real_last_page_and_a_bad_page_is_page_one(): void
    {
        $this->products(20);

        $past = $this->list(['page' => 9])->assertOk();
        $this->assertSame([[], 9, 2, 20], [$past->json('products.data'), $past->json('products.current_page'), $past->json('products.last_page'), $past->json('products.total')], 'The page recovers to last_page.');

        foreach (['abc', '0', '-1', ''] as $bad) {
            $this->assertSame(1, $this->list(['page' => $bad])->json('products.current_page'), $bad);
        }
    }

    public function test_filters_and_the_query_string_are_kept_across_pages(): void
    {
        $this->products(12, status: 'draft');
        $this->products(4, status: 'active');

        $response = $this->list(['status' => 'draft', 'per_page' => 5, 'page' => 2])->assertOk();
        $this->assertSame([12, 3, 2], [$response->json('products.total'), $response->json('products.last_page'), $response->json('products.current_page')]);
        $this->assertSame(['draft'], array_values(array_unique(array_column($response->json('products.data'), 'status'))));
        foreach (['next_page_url', 'prev_page_url'] as $link) {
            $this->assertStringContainsString('status=draft', $response->json("products.{$link}"));
            $this->assertStringContainsString('per_page=5', $response->json("products.{$link}"));
        }
        $this->assertSame(['products', 'filters'], array_keys($response->json()));
    }

    public function test_statuses_and_moderation_flags_are_right_on_later_pages(): void
    {
        $ids = $this->products(41, sameSecond: true);
        // The three oldest (on page 3): archived, suspended, both, each with its own status.
        Product::query()->whereKey($ids[0])->update(['status' => 'inactive']);
        DB::table('products')->where('id', $ids[0])->update(['archived_at' => now()]);
        DB::table('products')->where('id', $ids[1])->update(['suspended_at' => now(), 'status' => 'active']);
        DB::table('products')->where('id', $ids[2])->update(['archived_at' => now(), 'suspended_at' => now(), 'status' => 'out_of_stock']);

        $rows = collect($this->list(['page' => 3])->json('products.data'))->keyBy('id');

        $this->assertSame(['inactive', ['archived' => true, 'suspended' => false]], [$rows[$ids[0]]['status'], $rows[$ids[0]]['moderation']]);
        $this->assertSame(['active', ['archived' => false, 'suspended' => true]], [$rows[$ids[1]]['status'], $rows[$ids[1]]['moderation']]);
        $this->assertSame(['out_of_stock', ['archived' => true, 'suspended' => true]], [$rows[$ids[2]]['status'], $rows[$ids[2]]['moderation']]);
        $this->assertArrayNotHasKey('archived_at', $rows[$ids[0]]);
    }

    // ---------------------------------------------------------------- the page

    public function test_the_products_page_has_a_labelled_pager_with_direction_correct_carets(): void
    {
        $before = TranslationValue::count();
        $texts = [
            'ar' => ['rtl', 'صفحات المنتجات', 'السابق', 'التالي', 'الصفحة :page من :last', 'ph-caret-right', 'ph-caret-left'],
            'en' => ['ltr', 'Product pages', 'Previous', 'Next', 'Page :page of :last', 'ph-caret-left', 'ph-caret-right'],
        ];

        foreach ($texts as $locale => [$dir, $label, $previous, $next, $status, $back, $forward]) {
            $html = $this->page($locale, 'products.page');
            $xpath = $this->xpath($html);
            $this->assertMatchesRegularExpression("/<html lang=\"{$locale}\" dir=\"{$dir}\"/", $html);

            $navs = $xpath->query('//*[@data-vendor-resource-list]//nav[@data-resource-pager]');
            $this->assertSame(1, $navs->length);
            /** @var DOMElement $nav */
            $nav = $navs->item(0);
            $this->assertSame('vendor-media-more', $nav->getAttribute('class'), 'The media library pager.');
            $this->assertSame($label, $nav->getAttribute('aria-label'));
            $this->assertSame($status, $nav->getAttribute('data-page-status'));
            $this->assertTrue($nav->hasAttribute('hidden'), 'Shown by the script when there is a second page.');

            [$prev, $nextButton] = [$xpath->query('.//button[@data-resource-previous]', $nav)->item(0), $xpath->query('.//button[@data-resource-next]', $nav)->item(0)];
            foreach ([$prev, $nextButton] as $button) {
                $this->assertSame('button', $button->getAttribute('type'));
                $this->assertTrue($button->hasAttribute('disabled'));
                $this->assertSame('true', $xpath->query('.//i', $button)->item(0)->getAttribute('aria-hidden'));
            }
            $this->assertSame($previous, trim($prev->textContent));
            $this->assertSame($next, trim($nextButton->textContent));
            $this->assertSame("ph-duotone {$back}", $xpath->query('.//i', $prev)->item(0)->getAttribute('class'), 'Previous points back along the reading direction.');
            $this->assertSame("ph-duotone {$forward}", $xpath->query('.//i', $nextButton)->item(0)->getAttribute('class'));
            $this->assertSame('polite', $xpath->query('.//*[@data-resource-page-status]', $nav)->item(0)->getAttribute('aria-live'));
        }

        $this->assertSame($before, TranslationValue::count(), 'Rendering creates no translation rows.');
        $this->assertSame(['ar', 'en'], TranslationValue::query()->where('key', 'vendor.Product_List_Pagination')->orderBy('locale')->pluck('locale')->all());
    }

    public function test_the_other_four_lists_get_no_pager(): void
    {
        // ORD-01A: the orders list pages too (VendorOrderListPaginationTest).
        foreach (['reviews.page', 'returns.page', 'disputes.page', 'commissions.page'] as $page) {
            $html = $this->page('en', $page);
            $this->assertStringNotContainsString('data-resource-pager', $html, $page);
            $this->assertStringContainsString('data-vendor-resource-list', $html, $page);
        }
    }

    public function test_the_script_pages_only_the_products_and_orders_lists_and_the_editor_says_what_changed(): void
    {
        $script = file_get_contents(base_path(self::SCRIPT));

        // ORD-01A: products and orders page (isPaged); the products path itself is unchanged.
        $this->assertStringContainsString("export const isPaged = (resource) => ['products', 'orders'].includes(resource);", $script);
        $this->assertStringContainsString("const pager = isPaged(root.dataset.resource) ? root.querySelector('[data-resource-pager]') : null;", $script);
        // ORD-01B: filters is null for every list but orders, so pageUrl sets only page for products.
        $this->assertStringContainsString('fetch(pager ? pageUrl(root.dataset.endpoint, page, window.location.href, filters) : root.dataset.endpoint,', $script, 'The other lists fetch their endpoint as before.');
        $this->assertStringContainsString('let filters = filterForm ? filtersFromQuery(window.location.search, statuses) : null;', $script);
        $this->assertStringContainsString("const filterForm = root.dataset.resource === 'orders' ? root.querySelector('[data-order-filters]') : null;", $script);
        $this->assertStringContainsString('const stale = () => pager !== null && mine !== latest;', $script, 'Stale answers are dropped for the paged lists only.');
        $this->assertStringContainsString('if (back !== null && back < page) return load(back, { focus });', $script);
        $this->assertStringContainsString('load(pager ? refreshPage(event.detail?.reason, page) : undefined);', $script);
        $this->assertStringNotContainsString('innerHTML', $script);
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $script);
        foreach (["'Previous'", "'Next'", "'Page "] as $english) {
            $this->assertStringNotContainsString($english, $script);
        }

        $editor = file_get_contents(base_path(self::EDITOR));
        $this->assertStringContainsString("const reason = editingId ? 'saved' : 'created';", $editor);
        $this->assertStringContainsString("detail: { resource: 'products', reason } }", $editor);
        $this->assertStringContainsString("detail: { resource: 'products', reason: 'deleted' } }", $editor);
    }

    // ---------------------------------------------------------------- helpers

    /** @return list<int> ids in creation order (oldest first) */
    private function products(int $count, bool $sameSecond = false, string $at = '2026-10-15 12:00:00', string $status = 'draft'): array
    {
        return $this->productsFor($this->vendor, $count, 'Jug', $sameSecond, $at, $status);
    }

    /** @return list<int> */
    private function productsFor(Vendor $vendor, int $count, string $name, bool $sameSecond = false, string $at = '2026-10-15 12:00:00', string $status = 'draft'): array
    {
        $ids = [];
        foreach (range(1, $count) as $i) {
            $time = $sameSecond ? Carbon::parse($at) : Carbon::parse($at)->addSeconds($i);
            $product = Product::create(['vendor_id' => $vendor->id, 'name' => "{$name} {$i}", 'slug' => str()->slug("{$name} {$i} {$vendor->id} {$status} {$at}"), 'price' => 10, 'stock_quantity' => 2, 'status' => $status]);
            DB::table('products')->where('id', $product->id)->update(['created_at' => $time, 'updated_at' => $time]);
            $ids[] = $product->id;
        }

        return $ids;
    }

    private function list(array $query = [])
    {
        return $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.products.index', $query));
    }

    private function page(string $locale, string $route): string
    {
        return $this->actingAs($this->vendor, 'vendor')->withSession(['locale' => $locale])->get(route("vendor.dashboard.{$route}"))->assertOk()->getContent();
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }
}
