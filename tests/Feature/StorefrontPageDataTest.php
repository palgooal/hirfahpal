<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Vendor;
use App\Support\Storefront\ProductPageData;
use App\Support\Storefront\StorePageData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * PREVIEW-01: the public product and store pages build their data through ProductPageData and StorePageData (to be
 * shared with the vendor's private preview later). These pin the public behaviour: which products and stores are
 * shown (Product::scopeSellable(), Vendor::scopeStorefrontVisible()), and the exact view data of both pages.
 */
class StorefrontPageDataTest extends TestCase
{
    use RefreshDatabase;

    private Category $pottery;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pottery = Category::query()->create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true, 'sort_order' => 2]);
        $this->vendor = Vendor::factory()->approved()->create(['status' => 'active']);
        $this->vendor->profile->forceFill(['slug' => 'dar-one'])->save();
    }

    // ---------------------------------------------------------------- the product page

    public function test_the_product_page_shows_only_products_a_customer_may_see(): void
    {
        $this->product('visible');
        $this->product('draft', ['status' => 'draft']);
        $this->product('inactive', ['status' => 'inactive']);
        $this->product('out', ['status' => 'out_of_stock']);
        $this->product('suspended')->forceFill(['suspended_at' => now()])->save();
        $this->product('archived')->forceFill(['archived_at' => now()])->save();
        $pending = Vendor::factory()->approved()->create(['status' => 'active']);
        $pending->profile->forceFill(['approval_status' => 'pending'])->save();
        $this->product('pending-store', ['vendor_id' => $pending->id]);
        $blocked = Vendor::factory()->approved()->create(['status' => 'blocked']);
        $this->product('blocked-store', ['vendor_id' => $blocked->id]);

        $this->get(route('product', ['slug' => 'visible']))->assertOk()->assertViewIs('pages.product');
        foreach (['draft', 'inactive', 'out', 'suspended', 'archived', 'pending-store', 'blocked-store', 'missing'] as $slug) {
            $this->get(route('product', ['slug' => $slug]))->assertNotFound();
        }
        $this->get('/product')->assertNotFound();
        $this->get('/product?slug='.str_repeat('a', 256))->assertNotFound();

        // The shared query is the sellable scope with the storefront's data, nothing more and nothing less.
        $this->assertSame(Product::query()->sellable()->orderBy('id')->pluck('id')->all(), ProductPageData::sellable()->orderBy('id')->pluck('id')->all());
        $this->assertSame(Product::query()->count(), ProductPageData::withListingData(Product::query())->count(), 'withListingData() adds data, never a condition.');
    }

    public function test_the_product_page_view_data_is_unchanged(): void
    {
        $main = $this->product('main', ['price' => 40, 'compare_at_price' => 30, 'stock_quantity' => 3]);
        $second = ProductImage::query()->create(['product_id' => $main->id, 'path' => 'products/second.png', 'alt_text' => 'Second', 'sort_order' => 0, 'is_primary' => false]);
        $primary = ProductImage::query()->create(['product_id' => $main->id, 'path' => 'products/primary.png', 'alt_text' => 'Primary', 'sort_order' => 1, 'is_primary' => true]);
        $this->review($main, 5, 'published');
        $this->review($main, 5, 'published');
        $this->review($main, 3, 'published');
        $this->review($main, 1, 'pending');
        $this->review($main, 2, 'hidden');
        // Related: same category, a customer may see them, newest first, at most four, never the product itself.
        $related = collect(range(1, 5))->map(fn ($i) => $this->product("related-{$i}"))->all();
        $this->product('related-draft', ['status' => 'draft']);
        $other = Category::query()->create(['name' => 'Baskets', 'slug' => 'baskets', 'is_active' => true]);
        $this->product('other-category', ['category_id' => $other->id]);

        $response = $this->get(route('product', ['slug' => 'main', 'quantity' => 2]))->assertOk();

        $response->assertViewHas('product', fn ($product) => $product->is($main) && $product->relationLoaded('vendor') && $product->reviews_count === 3);
        $response->assertViewHas('selectedQuantity', 2);
        $response->assertViewHas('productImages', fn ($images) => $images->pluck('id')->all() === [$primary->id, $second->id]);
        $response->assertViewHas('productReviews', fn ($reviews) => $reviews->count() === 3 && $reviews->every(fn ($review) => $review->status === 'published'));
        $response->assertViewHas('reviewDistribution', [1 => 0, 2 => 0, 3 => 33, 4 => 0, 5 => 67]);
        $response->assertViewHas('relatedProducts', fn ($products) => $products->pluck('id')->all() === [$related[4]->id, $related[3]->id, $related[2]->id, $related[1]->id]);

        // The quantity: a whole number of at least 1, never above the available stock.
        $this->get(route('product', ['slug' => 'main', 'quantity' => 99]))->assertViewHas('selectedQuantity', 3);
        $this->get(route('product', ['slug' => 'main', 'quantity' => 'abc']))->assertViewHas('selectedQuantity', 1);
        $this->get(route('product', ['slug' => 'main', 'quantity' => 0]))->assertViewHas('selectedQuantity', 1);
        // A product without images shows one placeholder named after the product.
        $this->get(route('product', ['slug' => 'related-1']))->assertViewHas('productImages', fn ($images) => $images->count() === 1 && $images->first()->id === null && $images->first()->alt_text === 'Product related-1');
    }

    public function test_the_shared_view_data_matches_what_the_page_renders(): void
    {
        $main = $this->product('main');
        $this->product('related-1');
        $request = Request::create('/product', 'GET', ['slug' => 'main', 'quantity' => '2']);

        $data = ProductPageData::viewData(ProductPageData::sellable()->whereKey($main->id)->firstOrFail(), $request);

        $this->assertSame(['product', 'selectedQuantity', 'productImages', 'productReviews', 'reviewDistribution', 'relatedProducts'], array_keys($data));
        $this->assertSame(2, $data['selectedQuantity']);
        $this->assertSame(['related-1'], $data['relatedProducts']->pluck('slug')->all());
    }

    // ---------------------------------------------------------------- the store page

    public function test_the_store_page_shows_only_visible_stores_and_their_sellable_products(): void
    {
        $older = $this->product('older', ['published_at' => now()->subDay()]);
        $newer = $this->product('newer', ['published_at' => now()]);
        $baskets = Category::query()->create(['name' => 'Baskets', 'slug' => 'baskets', 'is_active' => true, 'sort_order' => 1]);
        $inactive = Category::query()->create(['name' => 'Old', 'slug' => 'old', 'is_active' => false]);
        $basket = $this->product('basket', ['category_id' => $baskets->id, 'published_at' => now()->subDays(2)]);
        $oldCategory = $this->product('old-category', ['category_id' => $inactive->id, 'published_at' => now()->subDays(3)]);
        $this->product('draft', ['status' => 'draft']);
        $this->product('suspended')->forceFill(['suspended_at' => now()])->save();
        $this->product('archived')->forceFill(['archived_at' => now()])->save();
        Setting::query()->create(['site_name' => 'Hirfah', 'default_currency' => 'USD']);

        $response = $this->get(route('vendors.profile', ['storeSlug' => 'dar-one']))->assertOk()->assertViewIs('pages.vendor');

        $response->assertViewHas('vendor', fn ($vendor) => $vendor->is($this->vendor));
        $response->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$newer->id, $older->id, $basket->id, $oldCategory->id]);
        $response->assertViewHas('vendorCategories', fn ($categories) => $categories->pluck('slug')->all() === ['baskets', 'pottery'], 'Active categories of the shown products only, by sort order.');
        $response->assertViewHas('currency', 'USD');
        $response->assertViewHas('siteSetting', fn ($setting) => $setting?->site_name === 'Hirfah');

        // Stores a customer may not see, and unknown stores.
        $pending = Vendor::factory()->approved()->create(['status' => 'active']);
        $pending->profile->forceFill(['slug' => 'dar-pending', 'approval_status' => 'pending'])->save();
        $this->get(route('vendors.profile', ['storeSlug' => 'dar-pending']))->assertNotFound();
        $this->get(route('vendors.profile', ['storeSlug' => 'nope']))->assertNotFound();
    }

    public function test_the_shared_store_data_has_the_same_keys_and_defaults(): void
    {
        $this->product('jar');

        $data = StorePageData::viewData(Vendor::query()->storefrontVisible()->withStorefrontSummary()->findOrFail($this->vendor->id));

        $this->assertSame(['vendor', 'products', 'vendorCategories', 'siteSetting', 'currency'], array_keys($data));
        $this->assertSame(['jar'], $data['products']->pluck('slug')->all());
        $this->assertSame('ILS', $data['currency'], 'No settings row: the default currency.');
        $this->assertNull($data['siteSetting']);
    }

    // ---------------------------------------------------------------- helpers

    private function product(string $slug, array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'vendor_id' => $this->vendor->id, 'category_id' => $this->pottery->id, 'name' => 'Product '.$slug, 'slug' => $slug,
            'price' => 25, 'stock_quantity' => 5, 'status' => 'active', 'published_at' => now(),
        ], $attributes));
    }

    private function review(Product $product, int $rating, string $status): void
    {
        $customer = Customer::factory()->create();
        $order = Order::query()->create(['number' => 'R-'.uniqid(), 'customer_id' => $customer->id, 'payment_method' => 'cod']);
        Review::query()->create(['customer_id' => $customer->id, 'order_id' => $order->id, 'product_id' => $product->id,
            'reviewable_type' => 'product', 'rating' => $rating, 'comment' => 'Comment', 'status' => $status]);
    }
}
