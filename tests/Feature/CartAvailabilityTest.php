<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use App\Support\Storefront\CartAvailability;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CART-FIX-01 (CART-AUDIT-01): a cart line whose product is no longer for sale, is sold out or is above the stock
 * available now is marked with an Arabic explanation, can be fixed (set to the available quantity, or removed) and
 * is never removed for the customer; while such a line remains there is no payable total and no checkout (page,
 * HTML form), and a stock change after the cart was shown sends the HTML checkout back to the cart. JSON responses,
 * prices, stock and reservation rules, ownership and CSRF are unchanged.
 */
class CartAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        $this->category = Category::query()->create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $this->customer = Customer::factory()->create();
    }

    public function test_a_valid_cart_has_no_notices_a_total_and_checkout(): void
    {
        $this->cartWith([[$this->product('jar', ['price' => 45, 'compare_at_price' => 39.90]), 2]]);

        $xpath = $this->cartPage();

        $this->assertSame(0, $xpath->query('//*[@data-cart-issue-message]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-cart-checkout-blocked]')->length);
        $this->assertSame('79.80 ILS', trim($xpath->query('//*[@data-grand-total-display]')->item(0)->textContent));
        $this->assertSame(1, $xpath->query('//a[@href="'.route('customer.checkout.show').'"]')->length);
        $this->actingAs($this->customer, 'customer')->get(route('customer.checkout.show'))->assertOk();
    }

    public function test_an_unsellable_line_is_marked_kept_and_blocks_checkout(): void
    {
        foreach (['draft' => ['status' => 'draft'], 'inactive' => ['status' => 'inactive'], 'suspended' => ['suspended_at' => now()],
            'archived' => ['archived_at' => now()]] as $label => $state) {
            Cart::query()->delete();
            $hidden = $this->product('hidden-'.$label);
            $this->cartWith([[$this->product('ok-'.$label), 1], [$hidden, 2]]);
            Product::query()->whereKey($hidden->id)->update($state);

            $xpath = $this->cartPage();
            $line = $xpath->query('//*[@data-cart-issue="'.CartAvailability::UNAVAILABLE.'"]')->item(0);
            $this->assertNotNull($line, $label);
            $this->assertStringContainsString('هذا المنتج لم يعد متاحاً للشراء. أزله من السلة لإتمام الطلب.', $line->textContent, $label);
            $this->assertSame('disabled', $xpath->query('.//button[contains(@class, "cart-qty-decrease")]', $line)->item(0)->getAttribute('disabled'), $label);
            $this->assertCheckoutBlocked($xpath, $label);
            $this->assertSame(2, CartItem::query()->count(), "{$label}: no line is removed for the customer.");
        }
    }

    public function test_a_sold_out_line_is_marked_and_can_be_removed(): void
    {
        $empty = $this->product('empty-jar', ['stock_quantity' => 0]);
        $this->cartWith([[$this->product('ok-jar'), 1], [$empty, 1]]);

        $xpath = $this->cartPage();
        $this->assertStringContainsString('نفد مخزون هذا المنتج حالياً.', $xpath->query('//*[@data-cart-issue="'.CartAvailability::OUT_OF_STOCK.'"]')->item(0)->textContent);
        $this->assertCheckoutBlocked($xpath, 'sold out');

        $line = CartItem::query()->where('product_id', $empty->id)->sole();
        $this->actingAs($this->customer, 'customer')->from(route('cart'))->delete(route('customer.cart.items.destroy', $line))->assertRedirect(route('cart'));
        $this->assertSame(0, $this->cartPage()->query('//*[@data-cart-checkout-blocked]')->length, 'Ready once the line is removed.');
    }

    public function test_an_over_stock_line_can_be_set_to_the_available_quantity(): void
    {
        $jar = $this->product('jar', ['stock_quantity' => 5]);
        $this->cartWith([[$jar, 3]]);
        Product::query()->whereKey($jar->id)->update(['stock_quantity' => 1]);

        $xpath = $this->cartPage();
        $line = $xpath->query('//*[@data-cart-issue="'.CartAvailability::EXCEEDS_STOCK.'"]')->item(0);
        $this->assertStringContainsString('الكمية في سلتك (3) أكبر من المتوفر حالياً (1). خفّض الكمية أو أزل المنتج.', $line->textContent);
        $this->assertStringContainsString('اعتماد الكمية المتوفرة (1)', $line->textContent);
        $item = CartItem::query()->sole();
        $this->assertSame(['1', '1'], [
            $xpath->query('//form[@id="cart-available-'.$item->id.'"]/input[@name="quantity"]')->item(0)->getAttribute('value'),
            $xpath->query('//form[@id="cart-minus-'.$item->id.'"]/input[@name="quantity"]')->item(0)->getAttribute('value'),
        ], 'Both "set to available" and "−" send the available quantity.');
        $this->assertSame('إنقاص الكمية إلى 1', $xpath->query('.//button[contains(@class, "cart-qty-decrease")]', $line)->item(0)->getAttribute('aria-label'));
        $this->assertCheckoutBlocked($xpath, 'over stock');

        $this->actingAs($this->customer, 'customer')->from(route('cart'))->patch(route('customer.cart.items.update', $item), ['quantity' => 1])
            ->assertRedirect(route('cart'))->assertSessionHasNoErrors();
        $this->assertSame(1, $item->fresh()->quantity);
        $this->assertSame(0, $this->cartPage()->query('//*[@data-cart-issue-message]')->length);
    }

    public function test_the_checkout_page_and_html_checkout_return_to_the_cart_while_a_line_is_blocking(): void
    {
        $jar = $this->product('jar', ['stock_quantity' => 5]);
        $this->cartWith([[$jar, 3]]);
        Product::query()->whereKey($jar->id)->update(['stock_quantity' => 2]);
        $address = $this->customer->addresses()->create(['label' => 'Home', 'recipient_name' => 'R', 'phone' => '0500000000', 'address_line' => 'Street']);
        $this->actingAs($this->customer, 'customer');

        $this->get(route('customer.checkout.show'))->assertRedirect(route('cart'))->assertSessionHasErrors(['cart' => CartAvailability::CHECKOUT_BLOCKED]);
        $this->post(route('customer.checkout.store'), ['paymentMethod' => 'cod', 'addressChoice' => (string) $address->id])
            ->assertRedirect(route('cart'))->assertSessionHasErrors(['cart' => CartAvailability::CHECKOUT_BLOCKED]);
        $this->get(route('cart'))->assertSee('role="alert"', false)->assertSee(CartAvailability::CHECKOUT_BLOCKED);
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(3, CartItem::query()->value('quantity'), 'The line is kept as it was.');
    }

    public function test_a_stock_change_after_the_cart_was_shown_sends_the_html_checkout_back_to_the_cart(): void
    {
        $jar = $this->product('jar', ['stock_quantity' => 5]);
        $this->cartWith([[$jar, 3]]);
        $address = $this->customer->addresses()->create(['label' => 'Home', 'recipient_name' => 'R', 'phone' => '0500000000', 'address_line' => 'Street']);

        // Valid at the first check; the stock drops just before the checkout's transaction, as a concurrent order would.
        $changed = false;
        DB::connection()->beforeStartingTransaction(function () use ($jar, &$changed) {
            if (! $changed) {
                $changed = true;
                DB::table('products')->where('id', $jar->id)->update(['stock_quantity' => 1]);
            }
        });

        $this->actingAs($this->customer, 'customer')
            ->post(route('customer.checkout.store'), ['paymentMethod' => 'cod', 'addressChoice' => (string) $address->id])
            ->assertRedirect(route('cart'))->assertSessionHasErrors(['cart' => CartAvailability::CHANGED_DURING_CHECKOUT]);

        $this->assertTrue($changed);
        $this->assertSame(0, Order::query()->count(), 'Nothing ordered: the locked re-check refused it.');
        $this->assertSame('active', Cart::query()->value('status'));
        $this->assertSame(0, DB::table('stock_reservations')->count());
    }

    public function test_html_quantity_updates_return_to_the_cart_with_arabic_messages(): void
    {
        $jar = $this->product('jar', ['stock_quantity' => 3]);
        $this->cartWith([[$jar, 1]]);
        $item = CartItem::query()->sole();
        $this->actingAs($this->customer, 'customer');

        $this->from(route('cart'))->patch(route('customer.cart.items.update', $item), ['quantity' => 9])
            ->assertRedirect(route('cart'))->assertSessionHasErrors(['cart' => 'الكمية المطلوبة غير متوفرة. المتوفر حالياً: 3.']);
        $this->from(route('cart'))->patch(route('customer.cart.items.update', $item), ['quantity' => 0])
            ->assertRedirect(route('cart'))->assertSessionHasErrors(['quantity' => 'يجب أن تكون الكمية رقماً صحيحاً لا يقل عن 1.']);
        Product::query()->whereKey($jar->id)->update(['status' => 'draft']);
        $this->from(route('cart'))->patch(route('customer.cart.items.update', $item), ['quantity' => 1])
            ->assertRedirect(route('cart'))->assertSessionHasErrors(['cart' => 'هذا المنتج غير متاح للشراء حالياً.']);
        $this->assertSame(1, $item->fresh()->quantity);
    }

    public function test_json_responses_are_unchanged(): void
    {
        $jar = $this->product('jar', ['stock_quantity' => 3]);
        $this->cartWith([[$jar, 1]]);
        $item = CartItem::query()->sole();
        $address = $this->customer->addresses()->create(['label' => 'Home', 'recipient_name' => 'R', 'phone' => '0500000000', 'address_line' => 'Street']);
        $this->actingAs($this->customer, 'customer');

        $this->patchJson(route('customer.cart.items.update', $item), ['quantity' => 9])->assertStatus(422)->assertJsonPath('message', 'Requested quantity is not available.');
        Product::query()->whereKey($jar->id)->update(['status' => 'draft']);
        $this->patchJson(route('customer.cart.items.update', $item), ['quantity' => 1])->assertStatus(422)->assertJsonPath('message', 'This product is no longer available for sale.');
        $this->postJson(route('customer.checkout.store'), ['payment_method' => 'cod', 'customer_address_id' => $address->id])
            ->assertStatus(422)->assertJsonPath('message', 'Product [Product jar] is no longer available for sale.');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_guest_cart_is_marked_too_and_ownership_still_holds(): void
    {
        $jar = $this->product('jar', ['stock_quantity' => 5]);
        $session = Str::random(40);
        $this->withCookie(config('session.cookie'), $session)
            ->postJson(route('customer.cart.items.store'), ['product_id' => $jar->id, 'quantity' => 4])->assertCreated();
        Product::query()->whereKey($jar->id)->update(['stock_quantity' => 2]);

        $html = $this->withCookie(config('session.cookie'), $session)->get(route('cart'))->assertOk()->getContent();
        $this->assertStringContainsString('الكمية في سلتك (4) أكبر من المتوفر حالياً (2).', $html);
        $this->assertStringContainsString('data-cart-checkout-blocked', $html);

        $item = CartItem::query()->sole();
        $this->actingAs(Customer::factory()->create(), 'customer')->patchJson(route('customer.cart.items.update', $item), ['quantity' => 1])->assertForbidden();
        $this->assertSame(4, $item->fresh()->quantity, 'Another customer cannot change the guest line.');
    }

    public function test_the_cart_and_checkout_charge_the_selling_price_unchanged(): void
    {
        $jar = $this->product('jar', ['price' => 45, 'compare_at_price' => 39.90, 'stock_quantity' => 5]);
        $this->cartWith([[$jar, 2]]);
        $address = $this->customer->addresses()->create(['label' => 'Home', 'recipient_name' => 'R', 'phone' => '0500000000', 'address_line' => 'Street']);

        $this->actingAs($this->customer, 'customer')->post(route('customer.checkout.store'), ['paymentMethod' => 'cod', 'addressChoice' => (string) $address->id])
            ->assertRedirect(route('customer.dashboard'));
        $order = Order::query()->with('items')->sole();
        $this->assertSame(['79.80', '39.90'], [$order->grand_total, $order->items->sole()->unit_price]);
        $this->assertSame(2, (int) DB::table('stock_reservations')->sum('quantity'));
    }

    private function assertCheckoutBlocked(DOMXPath $xpath, string $label): void
    {
        $this->assertSame(1, $xpath->query('//*[@data-cart-checkout-blocked]')->length, "{$label}: the reason is shown.");
        $this->assertSame(0, $xpath->query('//*[@data-grand-total-display]')->length, "{$label}: no payable total.");
        $this->assertSame(0, $xpath->query('//a[@href="'.route('customer.checkout.show').'"]')->length, "{$label}: no checkout link.");
        $button = $xpath->query('//button[@aria-describedby="cart-checkout-blocked"]')->item(0);
        $this->assertSame('disabled', $button?->getAttribute('disabled'), "{$label}: the checkout button is disabled and explained.");
        $this->assertSame(1, $xpath->query('//span[normalize-space()="مجموع السلة الحالي (يشمل منتجات تحتاج إلى معالجة)"]')->length, "{$label}: the sum is labelled as the cart's.");
    }

    private function cartPage(): DOMXPath
    {
        $html = $this->actingAs($this->customer, 'customer')->get(route('cart'))->assertOk()->getContent();
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    /** @param list<array{0: Product, 1: int}> $lines */
    private function cartWith(array $lines): void
    {
        $cart = $this->customer->carts()->firstOrCreate(['status' => 'active']);
        foreach ($lines as [$product, $quantity]) {
            $cart->items()->create(['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $product->sellingPrice()]);
        }
    }

    private function product(string $slug, array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'vendor_id' => Vendor::factory()->approved()->create(['status' => 'active'])->id, 'category_id' => $this->category->id,
            'name' => 'Product '.$slug, 'slug' => $slug, 'price' => 25, 'stock_quantity' => 5, 'status' => 'active', 'published_at' => now(),
        ], $attributes));
    }
}
