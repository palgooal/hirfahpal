<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * VQA-FIX-01: every storefront product card shows Product::sellingPrice() (the price the cart and checkout charge),
 * with the regular price struck through and a rounded-down discount only when Product::hasDiscount(); the cards' and
 * Add to cart buttons' data-price (browse filtering and sorting) carry the selling price; the header cart total
 * follows the cart's pricing contract on the server and in the homepage script. Checkout pricing is unchanged.
 */
class StorefrontSellingPriceTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::query()->create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $this->vendor = Vendor::factory()->approved()->create(['status' => 'active']);
    }

    public function test_the_card_price_shows_the_selling_price_and_strikes_the_regular_price_only_for_a_genuine_discount(): void
    {
        $cases = [
            // price, compare_at_price => selling price shown, struck regular price (null: none)
            [45, 39.90, '39.90 ILS', '45.00 ILS'],
            [25, null, '25.00 ILS', null],
            [30, 30, '30.00 ILS', null],
            [0, null, '0.00 ILS', null],
        ];

        // The storefront view composer reads the session's cart, as in a real request.
        $this->app['request']->setLaravelSession($this->app['session.store']);

        foreach ($cases as $index => [$price, $compareAt, $selling, $struck]) {
            $product = $this->product("case-{$index}", ['price' => $price, 'compare_at_price' => $compareAt]);
            $label = "price {$price}, compare_at_price {$compareAt}";

            foreach (['after', 'before', 'none'] as $placement) {
                $html = view('storefront.partials.product-price', ['product' => $product, 'currency' => 'ILS', 'priceClass' => 'x', 'regular' => $placement])->render();

                $this->assertSame([$selling], $this->texts($html, '//*[@data-selling-price]'), $label);
                $this->assertSame($struck === null || $placement === 'none' ? [] : [$struck], $this->texts($html, '//*[@data-regular-price]'), "{$label}, {$placement}");
            }
        }

        // 'before' and 'after' put the struck price on that side of the selling price, on the same line.
        $discounted = Product::query()->where('slug', 'case-0')->sole();
        $before = view('storefront.partials.product-price', ['product' => $discounted, 'currency' => 'ILS', 'priceClass' => 'x', 'regular' => 'before'])->render();
        $this->assertSame(['data-regular-price', 'data-selling-price'], $this->childOrder($before));
        $after = view('storefront.partials.product-price', ['product' => $discounted, 'currency' => 'ILS', 'priceClass' => 'x'])->render();
        $this->assertSame(['data-selling-price', 'data-regular-price'], $this->childOrder($after));
        $this->assertStringContainsString('whitespace-nowrap', $after, 'One line: the fixed-height cards never grow.');
    }

    public function test_every_storefront_card_and_its_data_price_use_the_selling_price(): void
    {
        $discounted = $this->product('discounted-jar', ['price' => 45, 'compare_at_price' => 39.90, 'is_featured' => true]);
        $regular = $this->product('regular-bowl', ['price' => 25]);
        $customer = Customer::factory()->create();
        $customer->favorites()->create(['product_id' => $discounted->id]);

        $pages = [
            'home' => $this->get(route('home')),
            'browse' => $this->get(route('browse')),
            'offers' => $this->get(route('browse', ['sale' => 1])),
            'vendor' => $this->get(route('vendors.profile', ['storeSlug' => $this->vendor->profile->slug])),
            'related' => $this->get(route('product', ['slug' => $regular->slug])),
            'favorites' => $this->actingAs($customer, 'customer')->get(route('customer.favorites')),
        ];

        foreach ($pages as $page => $response) {
            $html = $response->assertOk()->getContent();
            $selling = $this->texts($html, '//*[@data-selling-price]');

            $this->assertContains('39.90 ILS', $selling, "{$page}: the discounted card shows its selling price.");
            $this->assertNotContains('45.00 ILS', $selling, "{$page}: the regular price is never shown as the card price.");
            $this->assertNotEmpty($this->texts($html, '//*[@data-regular-price]'), "{$page}: the regular price is shown struck through.");
            $this->assertSame(['45.00 ILS'], array_values(array_unique($this->texts($html, '//*[@data-regular-price]'))), $page);
            $this->assertContains('39.90', $this->texts($html, '//*[@data-price]/@data-price'), "{$page}: data-price is the selling price.");
            $this->assertNotContains('45.00', $this->texts($html, '//*[@data-price]/@data-price'), "{$page}: no data-price carries the regular price.");
        }

        // The undiscounted product: its price, no strike-through (offers, related and favorites list only the discount).
        foreach (['home', 'browse', 'vendor'] as $page) {
            $html = $pages[$page]->getContent();
            $this->assertContains('25.00 ILS', $this->texts($html, '//*[@data-selling-price]'), $page);
            $this->assertContains('25.00', $this->texts($html, '//*[@data-price]/@data-price'), $page);
            $this->assertNotContains('25.00 ILS', $this->texts($html, '//*[@data-regular-price]'), $page);
        }
        $this->assertSame(['39.90'], $this->texts($pages['offers']->getContent(), '//article[@data-price]/@data-price'));

        // The struck regular price takes the place of the popular card's "fair price" label and of the season card's
        // "price:" label; the undiscounted popular card keeps its label. Season lists only the featured (discounted) jar.
        $home = $pages['home']->getContent();
        $this->assertStringContainsString('السعر العادل', $home);
        $this->assertSame(['45.00 ILS'], $this->texts($home, '//section[@id="season"]//*[@data-regular-price]'));
        $this->assertSame(['39.90 ILS'], $this->texts($home, '//section[@id="season"]//*[@data-selling-price]'));
        $this->assertStringNotContainsString('السعر:', $home);
    }

    public function test_browse_filter_and_sort_metadata_order_products_by_selling_price(): void
    {
        $this->product('discounted-jar', ['price' => 45, 'compare_at_price' => 39.90]);
        $this->product('plain-vase', ['price' => 42]);
        $this->product('cheap-cup', ['price' => 10]);

        // storefront-browse.js filters (min / max) and sorts (price-asc / price-desc) by each article's data-price.
        $prices = array_map('floatval', $this->texts($this->get(route('browse'))->getContent(), '//article[@data-price]/@data-price'));
        sort($prices);

        $this->assertSame([10.0, 39.9, 42.0], $prices, 'The discounted jar sorts below the 42.00 vase and is inside a 0-40 filter.');
        $this->assertSame([10.0, 39.9], array_values(array_filter($prices, fn (float $price) => $price <= 40)));
    }

    public function test_the_product_page_add_to_cart_metadata_uses_the_selling_price(): void
    {
        $product = $this->product('discounted-jar', ['price' => 45, 'compare_at_price' => 39.90]);
        $html = $this->get(route('product', ['slug' => $product->slug]))->assertOk()->getContent();

        $this->assertSame(['39.90'], $this->texts($html, '//button[@form="main-cart-'.$product->id.'"]/@data-price'));
        $this->assertStringContainsString('39.90 ILS', $html);
        $this->assertStringContainsString('45.00 ILS', $html, 'The product page keeps its struck-through regular price.');
    }

    public function test_the_header_total_matches_the_cart_subtotal_with_mixed_products_and_quantities(): void
    {
        $discounted = $this->product('discounted-jar', ['price' => 45, 'compare_at_price' => 39.90]);
        $regular = $this->product('regular-bowl', ['price' => 25]);
        $customer = Customer::factory()->create();
        $cart = $customer->carts()->create(['status' => 'active']);
        $cart->items()->create(['product_id' => $discounted->id, 'quantity' => 3, 'unit_price' => 39.90]);
        $cart->items()->create(['product_id' => $regular->id, 'quantity' => 2, 'unit_price' => 25]);
        $expected = 3 * 39.90 + 2 * 25; // 169.70; at regular prices it would be 185.00

        $this->actingAs($customer, 'customer');
        foreach (['home', 'browse', 'cart'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertViewHas('cartTotal', fn ($total) => abs($total - $expected) < 0.001)
                ->assertSee('169.70 ILS');
        }
        $this->get(route('cart'))->assertViewHas('subtotal', fn ($subtotal) => abs($subtotal - $expected) < 0.001);
    }

    public function test_the_home_add_to_cart_script_sums_the_lines_unit_prices(): void
    {
        $discounted = $this->product('discounted-jar', ['price' => 45, 'compare_at_price' => 39.90]);
        $regular = $this->product('regular-bowl', ['price' => 25]);
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('sum + item.quantity * Number(item.unit_price)', $html);
        $this->assertStringNotContainsString('item.product?.price', $html);

        // What the script receives after Add to cart, summed as it sums it, equals the server's header total.
        $this->postJson(route('customer.cart.items.store'), ['product_id' => $discounted->id, 'quantity' => 2])->assertCreated();
        $items = $this->postJson(route('customer.cart.items.store'), ['product_id' => $regular->id, 'quantity' => 1])
            ->assertCreated()->json('items');
        $scriptTotal = array_sum(array_map(fn (array $item) => $item['quantity'] * (float) $item['unit_price'], $items));

        $this->assertEqualsWithDelta(2 * 39.90 + 25, $scriptTotal, 0.001);
        $this->get(route('home'))->assertViewHas('cartTotal', fn ($total) => abs($total - $scriptTotal) < 0.001);
    }

    public function test_checkout_still_charges_the_selling_price(): void
    {
        $discounted = $this->product('discounted-jar', ['price' => 45, 'compare_at_price' => 39.90]);
        $customer = Customer::factory()->create();
        $cart = $customer->carts()->create(['status' => 'active']);
        $cart->items()->create(['product_id' => $discounted->id, 'quantity' => 2, 'unit_price' => 1]);
        $address = $customer->addresses()->create(['label' => 'Home', 'recipient_name' => 'Recipient', 'phone' => '0500000000', 'address_line' => 'Street']);

        $this->actingAs($customer, 'customer')->post(route('customer.checkout.store'), ['paymentMethod' => 'cod',
            'addressChoice' => (string) $address->id])->assertRedirect(route('customer.dashboard'));

        $order = Order::query()->with('items')->firstOrFail();
        $this->assertSame('79.80', $order->grand_total);
        $this->assertSame('39.90', $order->items->sole()->unit_price);
    }

    private function product(string $slug, array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'vendor_id' => $this->vendor->id, 'category_id' => $this->category->id, 'name' => $slug, 'slug' => $slug,
            'price' => 25, 'stock_quantity' => 10, 'status' => 'active', 'published_at' => now(),
        ], $attributes));
    }

    /** @return list<string> the price data attribute of each child of the card price, in document order */
    private function childOrder(string $html): array
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        $order = [];
        foreach ((new DOMXPath($document))->query('//*[@data-product-price]/*') as $child) {
            $order[] = $child->hasAttribute('data-selling-price') ? 'data-selling-price' : ($child->hasAttribute('data-regular-price') ? 'data-regular-price' : $child->nodeName);
        }

        return $order;
    }

    /** @return list<string> the whitespace-normalised text of each node the XPath expression selects */
    private function texts(string $html, string $expression): array
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        $texts = [];
        foreach ((new DOMXPath($document))->query($expression) as $node) {
            $texts[] = trim(preg_replace('/\s+/u', ' ', $node->textContent));
        }

        return $texts;
    }
}
