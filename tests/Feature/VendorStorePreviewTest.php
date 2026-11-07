<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
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
 * PREVIEW-03: the vendor's private preview of their own store page (vendor.dashboard.preview.store). The storefront's
 * store page, always the signed-in vendor's own store, with only the products a customer may see and a count of the
 * others; its products open their own previews; no buying, cart, favourites or public links; in `open` and
 * `coming_soon` only; never indexed or cached; the public store page is unchanged.
 */
class VendorStorePreviewTest extends TestCase
{
    use RefreshDatabase;

    private string $hotFile = '';

    private Vendor $vendor;

    private Category $category;

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

        $this->category = Category::query()->create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $this->vendor = $this->vendor('dar-one', 'Dar One');
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    // ---------------------------------------------------------------- whose store and which products

    public function test_the_preview_is_the_signed_in_vendors_own_store_with_its_sellable_products_and_the_hidden_count(): void
    {
        $visible = [$this->product($this->vendor, 'jar'), $this->product($this->vendor, 'bowl', ['stock_quantity' => 0])];
        foreach (['draft' => ['status' => 'draft'], 'inactive' => ['status' => 'inactive'], 'out' => ['status' => 'out_of_stock']] as $slug => $attributes) {
            $this->product($this->vendor, $slug, $attributes);
        }
        $this->product($this->vendor, 'suspended')->forceFill(['suspended_at' => '2026-03-15 09:30:00'])->save();
        $this->product($this->vendor, 'archived')->forceFill(['archived_at' => '2026-04-20 11:00:00'])->save();
        // Another store, with products of its own (sellable or not): never shown or counted here.
        $other = $this->vendor('dar-two', 'Dar Two');
        $this->product($other, 'theirs');
        $this->product($other, 'their-draft', ['status' => 'draft']);

        $response = $this->preview()->assertOk()->assertViewIs('pages.vendor')
            ->assertViewHas('preview', true)->assertViewHas('previewHiddenCount', 5)
            ->assertViewHas('vendor', fn ($vendor) => $vendor->is($this->vendor))
            ->assertViewHas('products', fn ($products) => $products->pluck('slug')->sort()->values()->all() === ['bowl', 'jar']);
        $html = $response->getContent();
        $xpath = $this->xpath($html);

        $this->assertSame('store', $xpath->query('//*[@data-vendor-preview]')->item(0)->getAttribute('data-vendor-preview'));
        $this->assertSame('5', $xpath->query('//*[@data-preview-hidden]')->item(0)->getAttribute('data-preview-hidden'));
        $this->assertSame('منتجات لا تظهر للزوار: 5', trim($xpath->query('//*[@data-preview-hidden]/span[1]')->item(0)->textContent));
        $this->assertSame(['Product bowl', 'Product jar'], $this->cardNames($xpath));
        $this->assertStringContainsString('Dar One', $html);
        foreach (['Dar Two', 'Product theirs', 'Product draft', 'Product inactive', 'Product out', 'Product suspended', 'Product archived', '2026-03-15', '2026-04-20'] as $absent) {
            $this->assertStringNotContainsString($absent, $html, $absent);
        }

        // Nothing in the address chooses the store: another store's slug or id is ignored.
        $this->actingAs($this->vendor, 'vendor')->get(route('vendor.dashboard.preview.store', ['storeSlug' => 'dar-two', 'vendor' => $other->id]))
            ->assertOk()->assertViewHas('vendor', fn ($vendor) => $vendor->is($this->vendor))->assertDontSee('Dar Two');
        // The other vendor sees their own store, and their own count.
        $this->actingAs($other, 'vendor')->get(route('vendor.dashboard.preview.store'))->assertOk()
            ->assertViewHas('vendor', fn ($vendor) => $vendor->is($other))->assertViewHas('previewHiddenCount', 1)->assertDontSee('Dar One');
    }

    public function test_without_hidden_products_no_count_is_shown(): void
    {
        $this->product($this->vendor, 'jar');

        $html = $this->preview()->assertOk()->assertViewHas('previewHiddenCount', 0)->getContent();

        $this->assertStringNotContainsString('data-preview-hidden', $html);
        $this->assertStringNotContainsString('منتجات لا تظهر للزوار', $html);
    }

    public function test_only_a_signed_in_approved_vendor_may_preview(): void
    {
        $this->get(route('vendor.dashboard.preview.store'))->assertRedirect();
        $this->assertGuest('vendor');

        $pending = $this->vendor('dar-pending', 'Dar Pending');
        $pending->profile->forceFill(['approval_status' => 'pending'])->save();
        $this->actingAs($pending, 'vendor')->get(route('vendor.dashboard.preview.store'))->assertRedirect(route('vendor.approval-status'));
    }

    // ---------------------------------------------------------------- links, buying and availability

    public function test_products_open_their_previews_and_nothing_leads_to_a_public_page_or_buys(): void
    {
        $jar = $this->product($this->vendor, 'jar');
        $bowl = $this->product($this->vendor, 'bowl');

        $html = $this->preview()->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $links = array_map(fn ($node) => $node->getAttribute('href'), iterator_to_array($xpath->query('//a[@href]')));
        sort($links);
        $expected = [route('vendor.dashboard.my-store'), route('vendor.dashboard.preview.product', $bowl), route('vendor.dashboard.preview.product', $jar)];
        sort($expected);
        $this->assertSame($expected, $links, 'Only the products\' previews and the way back to My Store.');
        $this->assertSame(0, $xpath->query('//form')->length, 'No cart, favourite or search form.');
        foreach ([route('customer.cart.items.store'), route('customer.favorites.store'), 'add-cart', 'class="favorite', 'cartButton', 'searchPanel'] as $absent) {
            $this->assertStringNotContainsString($absent, $html, $absent);
        }
        // The product preview opened from the store preview is the owner's (PREVIEW-02).
        $this->actingAs($this->vendor, 'vendor')->get(route('vendor.dashboard.preview.product', $jar))->assertOk()->assertSee('data-vendor-preview="product"', false);
    }

    public function test_the_preview_works_in_open_and_coming_soon_and_is_blocked_in_maintenance(): void
    {
        $this->product($this->vendor, 'jar');

        config(['site-availability.override' => 'open']);
        $this->assertSame('open', $this->xpath($this->preview()->assertOk()->getContent())->query('//*[@data-preview-site]')->item(0)->getAttribute('data-preview-site'));

        config(['site-availability.override' => 'coming_soon']);
        $xpath = $this->xpath($this->preview()->assertOk()->getContent());
        $this->assertSame('closed', $xpath->query('//*[@data-preview-site]')->item(0)->getAttribute('data-preview-site'));
        $this->assertSame('هكذا ستظهر صفحة متجرك للعملاء عند افتتاح متجر حرفة. لا يراها الزوار حاليًا، والشراء والإضافة إلى السلة والمفضلة معطّلة في المعاينة.', trim($xpath->query('//*[@data-preview-site]')->item(0)->textContent));
        // The public store page stays closed (the coming-soon page), the vendor signed in or not.
        $this->assertStringContainsString('data-site-availability="coming_soon"', $this->get(route('vendors.profile', ['storeSlug' => 'dar-one']))->assertOk()->getContent());

        config(['site-availability.override' => 'maintenance']);
        $response = $this->preview()->assertStatus(503);
        $this->assertStringNotContainsString('data-vendor-preview', $response->getContent());
        $this->assertStringNotContainsString('Dar One', $response->getContent());
    }

    public function test_the_preview_is_never_indexed_or_cached(): void
    {
        $response = $this->preview()->assertOk();

        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertHeader('Referrer-Policy', 'same-origin');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $response->getContent());
    }

    // ---------------------------------------------------------------- the public page and the languages

    public function test_the_public_store_page_is_unchanged(): void
    {
        $jar = $this->product($this->vendor, 'jar');

        $response = $this->get(route('vendors.profile', ['storeSlug' => 'dar-one']))->assertOk();
        $html = $response->getContent();

        $response->assertViewMissing('preview');
        $this->assertFalse($response->headers->has('X-Robots-Tag'));
        foreach (['name="robots"', 'data-vendor-preview', '/vendor/dashboard/preview'] as $absent) {
            $this->assertStringNotContainsString($absent, $html, $absent);
        }
        foreach ([route('product', ['slug' => $jar->slug]), route('customer.cart.items.store'), route('customer.favorites.store'), route('home'), route('vendors'), 'id="cartButton"'] as $present) {
            $this->assertStringContainsString($present, $html, $present);
        }
    }

    public function test_the_preview_reads_in_arabic_and_english(): void
    {
        $this->product($this->vendor, 'jar');
        $this->product($this->vendor, 'draft', ['status' => 'draft']);

        foreach (['ar' => ['rtl', 'معاينة متجري', 'منتجات لا تظهر للزوار: 1', 'العودة إلى متجري'],
            'en' => ['ltr', 'My store preview', 'Products not shown to visitors: 1', 'Back to My Store']] as $locale => [$dir, $title, $hidden, $back]) {
            $html = $this->actingAs($this->vendor, 'vendor')->withSession(['locale' => $locale])
                ->get(route('vendor.dashboard.preview.store'))->assertOk()->getContent();
            $xpath = $this->xpath($html);

            $this->assertMatchesRegularExpression("/<html lang=\"{$locale}\" dir=\"{$dir}\"/", $html);
            $this->assertSame($title, trim($xpath->query('//*[@id="vendor-preview-title"]')->item(0)->textContent));
            $this->assertSame($hidden, trim($xpath->query('//*[@data-preview-hidden]/span[1]')->item(0)->textContent));
            $this->assertSame($back, trim($xpath->query('//*[@data-vendor-preview]//a')->item(0)->textContent));
        }
    }

    // ---------------------------------------------------------------- helpers

    private function preview()
    {
        return $this->actingAs($this->vendor, 'vendor')->withSession(['locale' => 'ar'])->get(route('vendor.dashboard.preview.store'));
    }

    private function vendor(string $slug, string $name): Vendor
    {
        $vendor = Vendor::factory()->approved()->create(['status' => 'active']);
        $vendor->profile->forceFill(['slug' => $slug, 'store_name' => $name])->save();

        return $vendor;
    }

    private function product(Vendor $vendor, string $slug, array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'vendor_id' => $vendor->id, 'category_id' => $this->category->id, 'name' => 'Product '.$slug, 'slug' => $slug,
            'price' => 25, 'stock_quantity' => 5, 'status' => 'active', 'published_at' => now(),
        ], $attributes));
    }

    /** @return list<string> */
    private function cardNames(DOMXPath $xpath): array
    {
        $names = array_map(fn ($node) => trim($node->textContent), iterator_to_array($xpath->query('//*[@data-vendor-product-grid]//h3')));
        sort($names);

        return $names;
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }
}
