<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Language;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRODUCT-FIX-02 (PRODUCT-AUDIT-01 M1, M2, L6): /product needs a valid slug (missing, empty, array, oversized or
 * unknown: 404, never another product); a browser form whose add-to-cart is refused returns to the product page with
 * an Arabic message announced as an alert and the quantity it sent, writing nothing; JSON requests keep their status
 * codes and messages; success and checkout pricing are unchanged.
 */
class ProductPageRequestHandlingTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::query()->create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
    }

    public function test_the_product_page_needs_a_valid_slug(): void
    {
        $this->product('clay-jar');
        $this->product('newer-bowl');

        foreach (['/product', '/product?slug=', '/product?slug=%20%20', '/product?slug[]=clay-jar', '/product?slug[a]=clay-jar',
            '/product?slug='.str_repeat('a', 256), '/product?slug='.str_repeat('a', 255), '/product?slug=unknown',
            '/product?slug='.rawurlencode("' OR '1'='1")] as $url) {
            $this->get($url)->assertNotFound();
        }

        $this->get('/product?slug=clay-jar')->assertOk()->assertViewHas('product', fn (Product $product) => $product->slug === 'clay-jar');
    }

    public function test_hidden_products_stay_not_found(): void
    {
        $draft = $this->product('draft-jar', ['status' => 'draft']);
        $suspended = $this->product('suspended-jar');
        Product::query()->whereKey($suspended->id)->update(['suspended_at' => now()]);

        $this->get('/product?slug='.$draft->slug)->assertNotFound();
        $this->get('/product?slug='.$suspended->slug)->assertNotFound();
    }

    public function test_an_invalid_quantity_shows_one_without_a_redirect_and_a_large_one_is_capped(): void
    {
        $this->product('clay-jar', ['stock_quantity' => 4]);

        foreach (['abc', '0', '-2', '1.5'] as $quantity) {
            $this->get('/product?slug=clay-jar&quantity='.$quantity)->assertOk()->assertViewHas('selectedQuantity', 1);
        }
        $this->get('/product?slug=clay-jar&quantity=3')->assertOk()->assertViewHas('selectedQuantity', 3);
        $this->get('/product?slug=clay-jar&quantity=999')->assertOk()->assertViewHas('selectedQuantity', 4);
    }

    public function test_switching_language_on_a_product_page_keeps_it(): void
    {
        Language::query()->create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::query()->create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->product('clay-jar');

        $this->get('/product?slug=clay-jar&change-locale=en')->assertRedirect(url('/product').'?slug=clay-jar');
        $this->get('/product?slug=clay-jar')->assertOk();
    }

    public function test_a_form_with_more_than_the_stock_returns_to_the_page_with_an_alert_and_writes_nothing(): void
    {
        $product = $this->product('clay-jar', ['stock_quantity' => 3]);

        $this->from($this->url($product))->post(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 5])
            ->assertRedirect($this->url($product))->assertSessionHasErrors(['cart' => 'الكمية المطلوبة غير متوفرة. المتوفر حالياً: 3.']);
        $this->assertSame(0, CartItem::query()->count());
        $this->assertSame(0, Cart::query()->count(), 'Refused before any cart was created.');

        // The page shows the message as an alert, with the quantity sent (capped at the stock).
        $this->get($this->url($product))->assertOk()
            ->assertSee('role="alert"', false)->assertSee('الكمية المطلوبة غير متوفرة. المتوفر حالياً: 3.')
            ->assertViewHas('selectedQuantity', 3);
    }

    public function test_a_form_for_an_out_of_stock_or_unavailable_product_returns_with_its_message(): void
    {
        $empty = $this->product('empty-jar', ['stock_quantity' => 0]);
        $draft = $this->product('draft-jar', ['status' => 'draft']);

        $this->from($this->url($empty))->post(route('customer.cart.items.store'), ['product_id' => $empty->id, 'quantity' => 1])
            ->assertRedirect($this->url($empty))->assertSessionHasErrors(['cart' => 'هذا المنتج غير متوفر حالياً.']);
        $this->from('/browse')->post(route('customer.cart.items.store'), ['product_id' => $draft->id, 'quantity' => 1])
            ->assertRedirect('/browse')->assertSessionHasErrors(['cart' => 'هذا المنتج غير متاح للشراء حالياً.']);
        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_a_form_that_would_exceed_the_stock_with_the_cart_keeps_the_cart_unchanged(): void
    {
        $product = $this->product('clay-jar', ['stock_quantity' => 3]);
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');
        $this->post(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();

        $this->from($this->url($product))->post(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2, 'checkout' => 1])
            ->assertRedirect($this->url($product))
            ->assertSessionHasErrors(['cart' => 'لا يمكن إضافة هذه الكمية: في سلتك 2 من هذا المنتج، والمتوفر حالياً: 3.']);

        $this->assertSame([2], CartItem::query()->pluck('quantity')->all(), 'One line, still 2: no duplicate, no change.');
        $this->get($this->url($product))->assertViewHas('selectedQuantity', 2);
    }

    public function test_form_validation_errors_are_in_arabic_and_keep_the_quantity(): void
    {
        $product = $this->product('clay-jar');

        foreach (['0', 'abc', ''] as $quantity) {
            $this->from($this->url($product))->post(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => $quantity])
                ->assertRedirect($this->url($product))
                ->assertSessionHasErrors(['quantity' => 'يجب أن تكون الكمية رقماً صحيحاً لا يقل عن 1.']);
        }
        $this->from($this->url($product))->post(route('customer.cart.items.store'), ['product_id' => 999999, 'quantity' => 1])
            ->assertSessionHasErrors(['product_id' => 'هذا المنتج غير متاح للشراء حالياً.']);
        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_json_requests_keep_their_status_codes_and_messages(): void
    {
        $product = $this->product('clay-jar', ['stock_quantity' => 3]);
        $draft = $this->product('draft-jar', ['status' => 'draft']);

        $this->postJson(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 5])
            ->assertStatus(422)->assertJsonPath('message', 'Requested quantity is not available.');
        $this->postJson(route('customer.cart.items.store'), ['product_id' => $draft->id, 'quantity' => 1])->assertNotFound();
        $invalid = $this->postJson(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('quantity');
        $this->assertNotSame('يجب أن تكون الكمية رقماً صحيحاً لا يقل عن 1.', $invalid->json('errors.quantity.0'), 'JSON keeps the default message.');
        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_a_successful_add_and_buy_now_are_unchanged(): void
    {
        $product = $this->product('clay-jar', ['price' => 45, 'compare_at_price' => 39.90, 'stock_quantity' => 5]);
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->from($this->url($product))->post(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2])
            ->assertRedirect($this->url($product))->assertSessionHas('success')->assertSessionHasNoErrors();
        $this->get($this->url($product))->assertSee('role="status"', false)->assertDontSee('role="alert"', false);
        $this->post(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1, 'checkout' => 1])
            ->assertRedirect(route('customer.checkout.show'));
        $this->postJson(route('customer.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertCreated()->assertJsonPath('items.0.quantity', 4)->assertJsonPath('items.0.unit_price', '39.90');
    }

    private function url(Product $product): string
    {
        return route('product', ['slug' => $product->slug]);
    }

    private function product(string $slug, array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'vendor_id' => Vendor::factory()->approved()->create(['status' => 'active'])->id, 'category_id' => $this->category->id,
            'name' => 'Product '.$slug, 'slug' => $slug, 'price' => 25, 'stock_quantity' => 5, 'status' => 'active', 'published_at' => now(),
        ], $attributes));
    }
}
