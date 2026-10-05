<?php

namespace Tests\Feature;

use App\Http\Controllers\Store\CartController;
use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The storefront cart is a static migration of the approved reference: demo content,
 * inert controls, and no use of the real customer.cart.* JSON API.
 */
class StorefrontCartPageTest extends TestCase
{
    use RefreshDatabase;

    private const ITEMS = [
        ['إبريق فخار مقدسي مزخرف', '145', '3', '1', '₪145'],
        ['طبق تقديم خزفي أزرق', '135', '5', '2', '₪270'],
        ['وسادة تطريز فلاحي كنعاني', '220', '4', '1', '₪220'],
        ['شال مطرز بخيوط قطنية', '180', '3', '1', '₪180'],
        ['شمعة صويا وزيت زيتون', '68', '5', '2', '₪136'],
        ['مجموعة شموع زيت زيتون صغيرة', '60', '5', '1', '₪60'],
    ];

    public function test_guest_and_customer_can_open_the_public_cart_page(): void
    {
        $this->get(route('cart'))->assertOk()->assertViewIs('pages.cart');
        $this->assertGuest('customer');

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(route('cart'))->assertOk()->assertViewIs('pages.cart');
    }

    public function test_title_meta_hook_and_public_breadcrumb(): void
    {
        $response = $this->get(route('cart'))
            ->assertSee('<title>سلة المشتريات | حرفة</title>', false)
            ->assertSee('<meta name="description" content="سلة المشتريات في حرفة - مراجعة المنتجات مجمعة حسب التاجر قبل متابعة الدفع">', false);

        $main = $this->mainContent($response->getContent());
        $this->assertStringStartsWith('<main data-cart-page>', $main);

        preg_match('/<nav[^>]*aria-label="مسار الصفحة">(.*?)<\/nav>/s', $main, $breadcrumb);
        $this->assertSame('الرئيسية / سلة المشتريات', trim(preg_replace('/\s+/', ' ', strip_tags($breadcrumb[1]))));
        $this->assertStringContainsString('href="'.route('home').'"', $breadcrumb[1]);
        $this->assertStringNotContainsString('حسابي', $breadcrumb[1]);
    }

    public function test_static_vendor_groups_line_items_and_totals(): void
    {
        $main = $this->mainContent($this->get(route('cart'))->getContent());

        preg_match_all('/data-vendor-slug="([^"]+)"/', $main, $slugs);
        $this->assertSame(['dar-al-karma', 'bethlehem-women', 'noor-alzaytouna'], $slugs[1]);

        preg_match_all('/<article class="cart-line-item[^"]*"\s+data-unit-price="(\d+)" data-max-quantity="(\d+)">(.*?)<\/article>/s', $main, $items, PREG_SET_ORDER);
        $this->assertCount(6, $items);

        foreach (self::ITEMS as $index => [$name, $unitPrice, $maxQuantity, $quantity, $lineTotal]) {
            [, $itemUnitPrice, $itemMax, $markup] = $items[$index];
            $this->assertSame($unitPrice, $itemUnitPrice, $name);
            $this->assertSame($maxQuantity, $itemMax, $name);
            $this->assertStringContainsString('>'.$name.'</h2>', $markup, $name);
            $this->assertMatchesRegularExpression('/<bdi class="cart-qty[^"]*">'.$quantity.'<\/bdi>/', $markup, $name);
            $this->assertMatchesRegularExpression('/data-line-total><bdi>'.preg_quote($lineTotal, '/').'<\/bdi>/', $markup, $name);
        }

        preg_match_all('/data-vendor-subtotal><bdi>([^<]+)<\/bdi>/', $main, $subtotals);
        $this->assertSame(['₪415', '₪400', '₪196'], $subtotals[1]);
        $this->assertStringContainsString('data-grand-total><bdi>₪1,011</bdi>', $main);
        $this->assertStringContainsString('data-grand-total-display><bdi>₪1,011</bdi>', $main);
        $this->assertStringContainsString('<span data-cart-line-count><bdi>6</bdi></span>', $main);
        $this->assertStringContainsString('<span data-cart-vendor-count><bdi>3</bdi></span>', $main);
        $this->assertStringContainsString('التوصيل يُحسب لاحقاً لكل تاجر عند إتمام الطلب.', $main);
    }

    public function test_empty_state_is_present_hidden_and_links_to_categories(): void
    {
        $main = $this->mainContent($this->get(route('cart'))->getContent());

        $this->assertMatchesRegularExpression('/<div id="cartEmptyState"\s+class="hidden /', $main);
        $this->assertStringContainsString('سلتك فارغة حالياً', $main);
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('categories'), '/').'"\s+class="[^"]*">\s*<i data-lucide="grid-3x3"/', $main);
    }

    public function test_quantity_and_remove_controls_are_inert_buttons(): void
    {
        $main = $this->mainContent($this->get(route('cart'))->getContent());

        // Initial stepper states match the reference after its script ran: decrease disabled at quantity 1, never at max.
        preg_match_all('/<button type="button"( disabled)?\s+class="cart-qty-(decrease|increase) /', $main, $steppers, PREG_SET_ORDER);
        $states = array_map(fn ($match) => $match[2].($match[1] ? ':disabled' : ''), $steppers);
        $this->assertSame([
            'decrease:disabled', 'increase', 'decrease', 'increase', 'decrease:disabled', 'increase',
            'decrease:disabled', 'increase', 'decrease', 'increase', 'decrease:disabled', 'increase',
        ], $states);

        $this->assertSame(6, preg_match_all('/<button type="button"\s+class="cart-remove-item /', $main));
        $this->assertSame(6, substr_count($main, 'aria-label="إنقاص الكمية"'));
        $this->assertSame(6, substr_count($main, 'aria-label="زيادة الكمية"'));
        // No cart page script: the steppers and remove buttons have no behaviour.
        $this->assertStringNotContainsString('storefront-cart', $this->get(route('cart'))->getContent());
    }

    public function test_checkout_cta_links_to_the_checkout_display_and_other_navigation_is_deferred(): void
    {
        $main = $this->mainContent($this->get(route('cart'))->getContent());

        // GET customer.checkout.show and POST customer.checkout.store share one URI, so the CTA is checked as a plain GET link.
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('customer.checkout.show'), '/').'"\s+class="[^"]*">\s*<span>متابعة إلى الدفع<\/span>/', $main);
        $this->assertStringNotContainsString('data-deferred-navigation="checkout.html"', $main);
        $this->assertStringNotContainsString('<form', $main);
        $this->assertStringNotContainsString('formaction', $main);
        $this->assertStringNotContainsString('data-method', $main);

        $this->assertSame(6, substr_count($main, '<a role="link" aria-disabled="true" data-deferred-navigation="product.html" class='));
        foreach (['dar-al-karma', 'bethlehem-women', 'noor-alzaytouna'] as $slug) {
            $this->assertStringContainsString('<a role="link" aria-disabled="true" data-deferred-navigation="vendor.html?id='.$slug.'" class=', $main, $slug);
        }

        $this->assertStringNotContainsString('href="'.route('vendors.show').'"', $main);
        $this->assertStringNotContainsString('href="'.route('product').'"', $main);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\.html/', $main);
    }

    public function test_footer_links_to_cart_and_header_cart_stays_static(): void
    {
        $html = $this->get(route('cart'))->getContent();

        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('cart'), '/').'" class="[^"]*"><i\s+class="[^"]*"><\/i><span>سلة المشتريات<\/span><\/a>/', $html);
        $this->assertStringNotContainsString('data-deferred-navigation="cart.html"', $html);

        $this->assertMatchesRegularExpression('/<button type="button" disabled aria-disabled="true" id="cartButton"/', $html);
        $this->assertMatchesRegularExpression('/id="cartCount"\s+class="[^"]*">2<\/span>/', $html);
        $this->assertStringContainsString('<bdi id="cartTotal" class="w-10 text-right text-xs font-bold leading-3">₪365</bdi>', $html);
    }

    public function test_cart_api_routes_are_unchanged_and_display_route_is_read_only(): void
    {
        $show = Route::getRoutes()->getByName('customer.cart.show');
        $this->assertSame('customer/cart', $show->uri());
        $this->assertSame(CartController::class.'@show', $show->getActionName());
        $this->assertNotContains('auth:customer', $show->gatherMiddleware());

        foreach (['customer.cart.items.store' => ['POST', 'customer/cart/items', 'store'], 'customer.cart.items.update' => ['PATCH', 'customer/cart/items/{cartItem}', 'update'], 'customer.cart.items.destroy' => ['DELETE', 'customer/cart/items/{cartItem}', 'destroy']] as $name => [$method, $uri, $action]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertSame($uri, $route->uri(), $name);
            $this->assertContains($method, $route->methods(), $name);
            $this->assertSame(CartController::class.'@'.$action, $route->getActionName(), $name);
        }

        $display = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => $route->uri() === 'cart' || str_starts_with($route->uri(), 'cart/'));
        $this->assertSame(['cart'], $display->map->uri()->unique()->values()->all());
        $this->assertEqualsCanonicalizing(['GET', 'HEAD'], $display->flatMap->methods()->unique()->values()->all());

        $this->get(route('customer.cart.show'))->assertOk()->assertHeader('Content-Type', 'application/json');
        $this->assertGuest('customer');
    }

    public function test_rendering_the_cart_page_queries_no_cart_or_product_tables(): void
    {
        $customer = Customer::factory()->create();

        DB::enableQueryLog();
        $this->get(route('cart'))->assertOk();
        $this->actingAs($customer, 'customer')->get(route('cart'))->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();

        $this->assertDoesNotMatchRegularExpression('/\b(carts|cart_items|products)\b/', $queries);
        $this->assertSame(0, DB::table('carts')->count());
    }

    public function test_blocked_customer_and_other_account_areas_are_unaffected(): void
    {
        $blocked = Customer::factory()->create(['status' => 'blocked']);
        $this->actingAs($blocked, 'customer')->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));
        $this->assertGuest('customer');

        $this->actingAs(Vendor::factory()->create(), 'vendor')
            ->get(route('vendor.dashboard'))->assertOk()->assertViewIs('accounts.dashboard');
        $this->actingAs(DeliveryDriver::factory()->create(), 'delivery_driver')
            ->get(route('delivery-driver.dashboard'))->assertOk()->assertViewIs('accounts.dashboard');
    }

    private function mainContent(string $html): string
    {
        $start = strpos($html, '<main data-cart-page>');
        $this->assertNotFalse($start);

        return substr($html, $start, strpos($html, '</main>', $start) + 7 - $start);
    }
}
