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
 * PREVIEW-02: the vendor's private preview of their own product page (vendor.dashboard.preview.product). The
 * storefront's product page, for the owner only, in any status, in `open` and `coming_soon` only; no buying, cart,
 * favourites, related products or links to public pages; never indexed or cached; the public page is unchanged.
 */
class VendorProductPreviewTest extends TestCase
{
    use RefreshDatabase;

    private const STATUS_TEXTS = [
        'visible' => ['منشور، ويظهر للعملاء عندما يكون المتجر مفتوحًا.', 'Published, and shown to customers while the store is open.'],
        'draft' => ['مسودة، ولا يظهر للعملاء.', 'Draft, not shown to customers.'],
        'inactive' => ['غير نشط، ولا يظهر للعملاء.', 'Inactive, not shown to customers.'],
        'out_of_stock' => ['نفد من المخزون، ولا يظهر للعملاء.', 'Out of stock, not shown to customers.'],
        'suspended' => ['معلّق من الإدارة، ولا يظهر للعملاء حتى يُرفع التعليق.', 'Suspended by the administration, not shown to customers until the suspension is lifted.'],
        'archived' => ['مؤرشف من الإدارة، ولا يظهر للعملاء.', 'Archived by the administration, not shown to customers.'],
    ];

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
        $this->vendor = Vendor::factory()->approved()->create(['status' => 'active']);
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    // ---------------------------------------------------------------- who and what

    public function test_the_vendor_previews_their_own_product_in_every_status(): void
    {
        $products = [
            'visible' => $this->product('visible'),
            'draft' => $this->product('draft', ['status' => 'draft', 'published_at' => null]),
            'inactive' => $this->product('inactive', ['status' => 'inactive']),
            'out_of_stock' => $this->product('out', ['status' => 'out_of_stock']),
            'suspended' => tap($this->product('suspended'), fn ($product) => $product->forceFill(['suspended_at' => '2026-03-15 09:30:00'])->save()),
            'archived' => tap($this->product('archived', ['status' => 'draft']), fn ($product) => $product->forceFill(['archived_at' => '2026-04-20 11:00:00', 'suspended_at' => '2026-03-15 09:30:00'])->save()),
        ];

        foreach ($products as $status => $product) {
            $response = $this->preview($product)->assertOk()->assertViewIs('pages.product')
                ->assertViewHas('preview', true)->assertViewHas('previewStatus', $status);
            $html = $response->getContent();
            $xpath = $this->xpath($html);

            $this->assertSame(1, $xpath->query('//*[@data-vendor-preview]')->length, $status);
            $this->assertSame(self::STATUS_TEXTS[$status][0], trim($xpath->query('//*[@data-preview-status="'.$status.'"]/span[2]')->item(0)->textContent), $status);
            $this->assertStringContainsString($product->name, $html);
            // No administration detail: no dates of the suspension or the archiving.
            foreach (['2026-03-15', '2026-04-20', '09:30', '11:00'] as $detail) {
                $this->assertStringNotContainsString($detail, $html, "{$status}: {$detail}");
            }
        }

        // The public page still hides every one of them but the published one.
        $this->get(route('product', ['slug' => 'visible']))->assertOk();
        foreach (['draft', 'inactive', 'out', 'suspended', 'archived'] as $slug) {
            $this->get(route('product', ['slug' => $slug]))->assertNotFound();
        }
    }

    public function test_another_vendors_product_or_an_unknown_one_is_not_found(): void
    {
        $other = Vendor::factory()->approved()->create(['status' => 'active']);
        $theirs = $this->product('theirs', ['vendor_id' => $other->id]);
        $theirDraft = $this->product('their-draft', ['vendor_id' => $other->id, 'status' => 'draft']);

        $this->preview($theirs)->assertNotFound();
        $this->preview($theirDraft)->assertNotFound();
        $this->actingAs($this->vendor, 'vendor')->get('/vendor/dashboard/preview/products/999999')->assertNotFound();
        $this->actingAs($this->vendor, 'vendor')->get('/vendor/dashboard/preview/products/abc')->assertNotFound();
    }

    public function test_only_a_signed_in_approved_vendor_may_preview(): void
    {
        $product = $this->product('jar');

        $this->get(route('vendor.dashboard.preview.product', $product))->assertRedirect();
        $this->assertGuest('vendor');

        $pending = Vendor::factory()->approved()->create(['status' => 'active']);
        $pending->profile->forceFill(['approval_status' => 'pending'])->save();
        $pendingProduct = $this->product('pending-jar', ['vendor_id' => $pending->id]);
        $this->actingAs($pending, 'vendor')->get(route('vendor.dashboard.preview.product', $pendingProduct))->assertRedirect(route('vendor.approval-status'));
    }

    // ---------------------------------------------------------------- site availability (DEC-SA-01)

    public function test_the_preview_works_in_open_and_coming_soon_and_is_blocked_in_maintenance(): void
    {
        $product = $this->product('jar', ['status' => 'draft']);

        config(['site-availability.override' => 'open']);
        $this->preview($product)->assertOk();

        config(['site-availability.override' => 'coming_soon']);
        $this->preview($product)->assertOk()->assertSee('data-vendor-preview', false);
        // The public pages stay closed (the coming-soon page, 200 for a GET), the vendor signed in or not.
        $public = $this->get(route('product', ['slug' => 'jar']))->assertOk();
        $this->assertStringContainsString('data-site-availability="coming_soon"', $public->getContent());
        $this->assertStringNotContainsString('data-vendor-preview', $public->getContent());

        config(['site-availability.override' => 'maintenance']);
        $response = $this->preview($product)->assertStatus(503);
        $this->assertStringNotContainsString('data-vendor-preview', $response->getContent());
        $this->assertStringNotContainsString($product->name, $response->getContent());
    }

    // ---------------------------------------------------------------- what the page leaves out

    public function test_no_buying_cart_favourites_related_products_or_public_links(): void
    {
        $product = $this->product('jar');
        $this->product('related-1');
        $this->product('related-2');

        $html = $this->preview($product)->assertOk()->assertViewHas('relatedProducts', fn ($related) => $related->isEmpty())->getContent();
        $xpath = $this->xpath($html);

        $this->assertSame(0, $xpath->query('//form')->length, 'No form at all: no cart, buy-now, favourite or search form.');
        foreach ([route('customer.cart.items.store'), route('customer.favorites.store'), 'add-cart', 'class="favorite', 'قد يعجبك أيضاً', 'Product related-1', 'cartButton', 'searchPanel'] as $absent) {
            $this->assertStringNotContainsString($absent, $html, $absent);
        }
        $purchase = $xpath->query('//button[@data-preview-purchase]')->item(0);
        $this->assertNotNull($purchase);
        $this->assertTrue($purchase->hasAttribute('disabled'));
        $this->assertSame('button', $purchase->getAttribute('type'));

        // Every link stays in the vendor dashboard (no header, footer, breadcrumb or store link to public pages);
        // PREVIEW-04: the store's preview, then back to the products list.
        $links = array_map(fn ($node) => $node->getAttribute('href'), iterator_to_array($xpath->query('//a[@href]')));
        $this->assertSame([route('vendor.dashboard.preview.store'), route('vendor.dashboard.products.page')], $links);
    }

    public function test_the_preview_is_never_indexed_or_cached(): void
    {
        $response = $this->preview($this->product('jar'))->assertOk();

        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertHeader('Referrer-Policy', 'same-origin');
        $cache = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cache);
        $this->assertStringContainsString('private', $cache);
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $response->getContent());
    }

    // ---------------------------------------------------------------- the public page and the languages

    public function test_the_public_product_page_is_unchanged(): void
    {
        $this->product('jar');
        $this->product('related-1');

        $response = $this->get(route('product', ['slug' => 'jar']))->assertOk();
        $html = $response->getContent();

        $this->assertFalse($response->headers->has('X-Robots-Tag'));
        $this->assertStringNotContainsString('name="robots"', $html);
        $this->assertStringNotContainsString('data-vendor-preview', $html);
        $this->assertStringNotContainsString('data-preview-purchase', $html);
        foreach ([route('customer.cart.items.store'), route('customer.favorites.store'), 'قد يعجبك أيضاً', 'Product related-1', 'id="cartButton"'] as $present) {
            $this->assertStringContainsString($present, $html, $present);
        }
        $response->assertViewMissing('preview');
    }

    public function test_the_preview_reads_in_arabic_and_english(): void
    {
        $product = $this->product('jar', ['status' => 'inactive']);

        foreach (['ar' => ['rtl', 'معاينة خاصة', 'حالة المنتج:', 'الشراء غير متاح في المعاينة', 'العودة إلى المنتجات', 0],
            'en' => ['ltr', 'Private preview', 'Product status:', 'Purchasing is not available in the preview', 'Back to products', 1]] as $locale => [$dir, $title, $label, $purchase, $back, $index]) {
            $html = $this->actingAs($this->vendor, 'vendor')->withSession(['locale' => $locale])
                ->get(route('vendor.dashboard.preview.product', $product))->assertOk()->getContent();
            $xpath = $this->xpath($html);

            $this->assertMatchesRegularExpression("/<html lang=\"{$locale}\" dir=\"{$dir}\"/", $html);
            $this->assertSame($title, trim($xpath->query('//*[@id="vendor-preview-title"]')->item(0)->textContent));
            $this->assertSame($label, trim($xpath->query('//*[@data-preview-status]/span[1]')->item(0)->textContent));
            $this->assertSame(self::STATUS_TEXTS['inactive'][$index], trim($xpath->query('//*[@data-preview-status]/span[2]')->item(0)->textContent));
            $this->assertSame($purchase, trim($xpath->query('//button[@data-preview-purchase]')->item(0)->textContent));
            $this->assertSame($back, trim($xpath->query('//*[@data-vendor-preview]//a[not(@data-preview-store-link)]')->item(0)->textContent));
        }
    }

    // ---------------------------------------------------------------- helpers

    private function preview(Product $product)
    {
        return $this->actingAs($this->vendor, 'vendor')->withSession(['locale' => 'ar'])->get(route('vendor.dashboard.preview.product', $product));
    }

    private function product(string $slug, array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'vendor_id' => $this->vendor->id, 'category_id' => $this->category->id, 'name' => 'Product '.$slug, 'slug' => $slug,
            'price' => 25, 'stock_quantity' => 5, 'status' => 'active', 'published_at' => now(),
        ], $attributes));
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
