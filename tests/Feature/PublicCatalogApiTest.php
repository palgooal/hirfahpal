<?php

namespace Tests\Feature;

use App\Http\Controllers\Store\ProductCatalogController;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-01 (PRODUCT-AUDIT-01 C1): the public catalog JSON (shop.products.index, shop.products.show, shop.vendors.index)
 * publishes explicit allow-lists only. No vendor account data (email, phone, login), no store administration or
 * commercial data (commission rate, approval, rejection reason, street address), no internal product data (SKU, raw
 * stock, threshold, status, moderation columns, timestamps); visibility is unchanged; the page size is bounded.
 */
class PublicCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private const PRODUCT_KEYS = ['id', 'name', 'slug', 'short_description', 'price', 'selling_price', 'has_discount', 'available_stock', 'image_url', 'category', 'store'];

    private const DETAIL_KEYS = ['description', 'material', 'place_of_manufacture', 'preparation_days', 'care_instructions', 'shipping_details', 'weight', 'weight_unit', 'dimensions', 'is_limited_edition', 'highlight_tags', 'craft_tags', 'images'];

    private const STORE_KEYS = ['slug', 'name', 'short_description', 'city', 'governorate', 'preparation_days'];

    /** Distinctive private values planted on the fixtures; none may appear in any public response. */
    private const SECRETS = ['secret-vendor-', '0599777', 'SECRET STREET 12', '7.77', 'SECRET REJECTION', 'SECRET-SKU-1'];

    /** Private attribute names that must not appear as keys at any depth. */
    private const FORBIDDEN_KEYS = ['email', 'phone', 'password', 'remember_token', 'last_login_at', 'email_verified_at', 'phone_verified_at', 'status',
        'commission_rate', 'approval_status', 'approved_at', 'approved_by', 'rejection_reason', 'address_line', 'vendor_id', 'city_id', 'governorate_id',
        'sku', 'stock_quantity', 'low_stock_threshold', 'is_featured', 'published_at', 'suspended_at', 'archived_at', 'created_at', 'updated_at', 'vendor', 'profile'];

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::query()->create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
    }

    public function test_the_product_list_exposes_only_the_approved_fields(): void
    {
        $this->product($this->vendor(), 'jar', ['price' => 45, 'compare_at_price' => 39.90]);

        $response = $this->getJson(route('shop.products.index'))->assertOk();
        $item = $response->json('data.0');

        $this->assertSame(self::PRODUCT_KEYS, array_keys($item));
        $this->assertSame(self::STORE_KEYS, array_keys($item['store']));
        $this->assertSame(['name', 'slug'], array_keys($item['category']));
        $this->assertSame(['45.00', '39.90', true], [$item['price'], $item['selling_price'], $item['has_discount']]);
        $this->assertNoPrivateData($response->getContent());
    }

    public function test_the_product_details_expose_only_the_approved_fields(): void
    {
        $product = $this->product($this->vendor(), 'jar', ['description' => 'Long text', 'material' => 'Clay', 'dimensions' => ['length' => 20, 'secret' => 1]]);
        ProductImage::query()->create(['product_id' => $product->id, 'path' => 'products/jar.jpg', 'alt_text' => 'Jar', 'sort_order' => 0, 'is_primary' => true]);

        $response = $this->getJson(route('shop.products.show', $product))->assertOk();

        $this->assertSame([...self::PRODUCT_KEYS, ...self::DETAIL_KEYS], array_keys($response->json()));
        $this->assertSame(['url', 'alt_text', 'is_primary'], array_keys($response->json('images.0')));
        $this->assertSame(['length' => 20], $response->json('dimensions'), 'Only the three public dimension keys.');
        $this->assertStringEndsWith('products/jar.jpg', $response->json('images.0.url'));
        $this->assertSame($product->id, $response->json('id'));
        $this->assertNoPrivateData($response->getContent());
    }

    public function test_the_vendor_list_exposes_only_public_store_identity_and_only_visible_stores(): void
    {
        $this->vendor();
        $this->vendor('pending');
        $this->vendor('approved', 'blocked');

        $response = $this->getJson(route('shop.vendors.index'))->assertOk();

        $this->assertCount(1, $response->json());
        $this->assertSame(self::STORE_KEYS, array_keys($response->json('0')));
        $this->assertNoPrivateData($response->getContent());
    }

    public function test_hidden_products_stay_hidden_from_the_list_and_the_details(): void
    {
        $visible = $this->product($this->vendor(), 'visible');
        $hidden = [
            $this->product($this->vendor(), 'draft', ['status' => 'draft']),
            $this->product($this->vendor(), 'inactive', ['status' => 'inactive']),
            $this->product($this->vendor(), 'suspended', [], ['suspended_at' => now()]),
            $this->product($this->vendor(), 'archived', [], ['archived_at' => now()]),
            $this->product($this->vendor('pending'), 'store-pending'),
            $this->product($this->vendor('approved', 'blocked'), 'vendor-blocked'),
        ];

        $this->assertSame([$visible->id], collect($this->getJson(route('shop.products.index'))->json('data'))->pluck('id')->all());
        foreach ($hidden as $product) {
            $this->getJson(route('shop.products.show', $product))->assertNotFound();
        }
    }

    public function test_available_stock_deducts_outstanding_reservations(): void
    {
        $product = $this->product($this->vendor(), 'jar', ['stock_quantity' => 5]);
        $order = Order::query()->create(['number' => 'SEC-1', 'customer_id' => Customer::factory()->create()->id, 'payment_method' => 'cod']);
        $product->stockReservations()->create(['order_id' => $order->id, 'quantity' => 2, 'status' => 'reserved']);

        $this->assertSame(3, $this->getJson(route('shop.products.index'))->json('data.0.available_stock'));
        $this->assertSame(3, $this->getJson(route('shop.products.show', $product))->json('available_stock'));
    }

    public function test_the_page_size_is_bounded_and_pagination_stays_correct(): void
    {
        $vendor = $this->vendor();
        foreach (range(1, 50) as $number) {
            $this->product($vendor, 'jar-'.$number);
        }

        $default = $this->getJson(route('shop.products.index'))->assertOk();
        $this->assertSame([12, 50, 5, 12], [$default->json('per_page'), $default->json('total'), $default->json('last_page'), count($default->json('data'))]);

        $max = $this->getJson(route('shop.products.index', ['per_page' => ProductCatalogController::MAX_PER_PAGE, 'page' => 2]))->assertOk();
        $this->assertSame([48, 2, 2], [$max->json('per_page'), $max->json('current_page'), count($max->json('data'))]);

        $filtered = $this->getJson(route('shop.products.index', ['category' => 'pottery', 'per_page' => 10]))->assertOk();
        $this->assertStringContainsString('category=pottery', $filtered->json('next_page_url'), 'The links keep the filters.');
        $this->assertStringContainsString('per_page=10', $filtered->json('next_page_url'));
    }

    public function test_malformed_or_excessive_pagination_input_is_refused(): void
    {
        $this->product($this->vendor(), 'jar');

        foreach ([['per_page' => 49], ['per_page' => 100000], ['per_page' => 0], ['per_page' => -1], ['per_page' => 'abc'], ['per_page' => '1.5'],
            ['per_page' => [12]], ['page' => 0], ['page' => 'abc'], ['page' => [2]]] as $query) {
            $this->getJson(route('shop.products.index', $query))->assertUnprocessable();
        }
        $this->getJson(route('shop.products.index', ['page' => 99]))->assertOk()->assertJsonPath('data', []);
    }

    private function assertNoPrivateData(string $json): void
    {
        foreach (self::SECRETS as $secret) {
            $this->assertStringNotContainsString($secret, $json, "Private value {$secret} is published.");
        }

        $keys = $this->keysAtEveryDepth(json_decode($json, true));
        foreach (self::FORBIDDEN_KEYS as $key) {
            $this->assertNotContains($key, $keys, "Private key {$key} is published.");
        }
    }

    /** @return list<string> every string key at every depth of a decoded JSON value */
    private function keysAtEveryDepth(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $keys = [];
        foreach ($value as $key => $child) {
            if (is_string($key)) {
                $keys[] = $key;
            }
            array_push($keys, ...$this->keysAtEveryDepth($child));
        }

        return $keys;
    }

    private function vendor(string $approval = 'approved', string $status = 'active'): Vendor
    {
        static $number = 0;
        $number++;
        $vendor = Vendor::factory()->withProfile($approval)->create([
            'status' => $status, 'email' => "secret-vendor-{$number}@example.com", 'phone' => '0599777'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
        ]);
        $vendor->profile()->update(['address_line' => 'SECRET STREET 12', 'commission_rate' => 7.77, 'rejection_reason' => 'SECRET REJECTION']);

        return $vendor->fresh();
    }

    private function product(Vendor $vendor, string $slug, array $attributes = [], array $forced = []): Product
    {
        $product = Product::query()->create(array_merge([
            'vendor_id' => $vendor->id, 'category_id' => $this->category->id, 'name' => 'Product '.$slug, 'slug' => $slug,
            'sku' => $slug === 'jar' ? 'SECRET-SKU-1' : null, 'price' => 25, 'stock_quantity' => 5, 'status' => 'active', 'published_at' => now(),
        ], $attributes));
        if ($forced !== []) {
            Product::query()->whereKey($product->id)->update($forced);
        }

        return $product->fresh();
    }
}
