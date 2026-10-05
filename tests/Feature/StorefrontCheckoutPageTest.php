<?php

namespace Tests\Feature;

use App\Http\Controllers\Store\CheckoutController;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The storefront checkout is a static migration of the approved reference: demo content,
 * local selection UI, and no use of the real customer.checkout.store endpoint.
 */
class StorefrontCheckoutPageTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Checkout-page-1';

    private const VENDORS = [
        'dar-al-karma' => ['دار الكرمة للخزف', '₪415', '₪18', '₪433'],
        'bethlehem-women' => ['جمعية نساء بيت لحم', '₪400', '₪22', '₪422'],
        'noor-alzaytouna' => ['مشغل نور الزيتونة', '₪196', '₪15', '₪211'],
    ];

    private const ITEMS = [
        ['إبريق فخار مقدسي مزخرف', '₪145', '1', '₪145'],
        ['طبق تقديم خزفي أزرق', '₪135', '2', '₪270'],
        ['وسادة تطريز فلاحي كنعاني', '₪220', '1', '₪220'],
        ['شال مطرز بخيوط قطنية', '₪180', '1', '₪180'],
        ['شمعة صويا وزيت زيتون', '₪68', '2', '₪136'],
        ['مجموعة شموع زيت زيتون صغيرة', '₪60', '1', '₪60'],
    ];

    public function test_guest_is_sent_to_customer_login_and_returns_after_login(): void
    {
        $customer = Customer::factory()->create(['email' => 'checkout@example.com', 'password' => self::PASSWORD]);

        $this->get(route('customer.checkout.show'))->assertRedirect(route('customer.login'));
        $this->assertSame(route('customer.checkout.show'), session('url.intended'));

        $this->post(route('customer.login.store'), ['login' => $customer->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('customer.checkout.show'));
        $this->get(route('customer.checkout.show'))->assertOk()->assertViewIs('pages.checkout');
    }

    public function test_active_customer_gets_the_storefront_checkout_page(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(route('customer.checkout.show'))
            ->assertOk()
            ->assertViewIs('pages.checkout')
            ->assertSee('<title>إتمام الطلب | حرفة</title>', false)
            ->assertSee('<meta name="description" content="إتمام الطلب في حرفة - اختيار عنوان التوصيل وطريقة الدفع ومراجعة الطلبات الفرعية حسب كل تاجر قبل التأكيد النهائي">', false)
            ->assertSee('<main data-checkout-page>', false)
            ->assertSee('storefront-checkout', false);
    }

    public function test_blocked_customer_is_forced_out(): void
    {
        $blocked = Customer::factory()->create(['status' => 'blocked']);

        $this->actingAs($blocked, 'customer')->get(route('customer.checkout.show'))->assertRedirect(route('customer.login'));
        $this->assertGuest('customer');
    }

    public function test_breadcrumb_hero_and_address_section(): void
    {
        $main = $this->mainContent();

        preg_match('/<nav[^>]*aria-label="مسار الصفحة">(.*?)<\/nav>/s', $main, $breadcrumb);
        $this->assertSame('الرئيسية / سلة المشتريات / إتمام الطلب', trim(preg_replace('/\s+/', ' ', strip_tags($breadcrumb[1]))));
        $this->assertStringContainsString('<a href="'.route('home').'" class="hover:text-olive">الرئيسية</a>', $breadcrumb[1]);
        $this->assertStringContainsString('<a href="'.route('cart').'" class="hover:text-olive">سلة المشتريات</a>', $breadcrumb[1]);
        $this->assertStringContainsString('<h1 class="text-[30px] font-bold leading-9 text-ink sm:text-4xl sm:leading-[48px]">إتمام الطلب</h1>', $main);

        $this->assertStringContainsString('aria-labelledby="addressTitle"', $main);
        preg_match_all('/<input type="radio" name="addressChoice" value="(\w+)" class="sr-only"( checked)?>/', $main, $choices, PREG_SET_ORDER);
        $this->assertSame(['home: checked', 'work:', 'new:'], array_map(fn ($match) => $match[1].':'.($match[2] ?? ''), $choices));
        $this->assertSame(3, substr_count($main, 'class="address-card '));
        $this->assertSame(2, substr_count($main, '<span class="mt-3 block text-sm leading-6 text-muted">ليان خليل</span>'));
        $this->assertStringContainsString('رام الله، حي الطيرة، قرب دوار الساعة', $main);
        $this->assertStringContainsString('القدس، شارع صلاح الدين، الطابق <bdi>2</bdi>', $main);
        $this->assertSame(2, substr_count($main, '<bdi>0599123456</bdi>'));
    }

    public function test_new_address_fields_are_hidden_inert_and_have_no_city(): void
    {
        $main = $this->mainContent();

        preg_match('/<div id="newAddressForm" class="mt-5 hidden [^"]*">(.*?)<\/textarea>/s', $main, $form);
        $this->assertNotEmpty($form);
        preg_match_all('/<option>([^<]+)<\/option>/', $form[1], $options);
        $this->assertSame(['القدس', 'الخليل', 'بيت لحم', 'رام الله', 'نابلس', 'جنين', 'الجليل'], $options[1]);
        $this->assertDoesNotMatchRegularExpression('/\sname=/', $form[1]);
        $this->assertStringNotContainsString('المدينة', $main);
    }

    public function test_static_vendor_groups_line_items_and_totals(): void
    {
        $main = $this->mainContent();

        preg_match_all('/<article class="cart-vendor-group[^"]*"\s+data-vendor-slug="([^"]+)">(.*?)<\/footer>/s', $main, $groups, PREG_SET_ORDER);
        $this->assertSame(array_keys(self::VENDORS), array_column($groups, 1));

        foreach ($groups as [, $slug, $markup]) {
            [$name, $subtotal, $delivery, $total] = self::VENDORS[$slug];
            $this->assertStringContainsString('>'.$name.'</strong>', $markup, $slug);
            $this->assertStringContainsString('<strong class="text-xl font-bold leading-8 text-ink"><bdi>'.$subtotal.'</bdi></strong>', $markup, $slug);
            $this->assertMatchesRegularExpression('/توصيل هذا التاجر<\/span>\s*<strong class="[^"]*"><bdi>'.$delivery.'<\/bdi>/', $markup, $slug);
            $this->assertMatchesRegularExpression('/المجموع مع التوصيل<\/span>\s*<strong class="[^"]*text-olive"><bdi>'.$total.'<\/bdi>/', $markup, $slug);
        }

        preg_match_all('/<article class="cart-line-item[^"]*">(.*?)<\/article>/s', $main, $items);
        $this->assertCount(6, $items[1]);
        foreach (self::ITEMS as $index => [$name, $price, $quantity, $lineTotal]) {
            $markup = $items[1][$index];
            $this->assertStringContainsString('>'.$name.'</h3>', $markup, $name);
            $this->assertStringContainsString('<strong class="block text-sm font-bold leading-6 text-ink"><bdi>'.$price.'</bdi></strong>', $markup, $name);
            $this->assertStringContainsString('text-sm font-bold text-ink"><bdi>'.$quantity.'</bdi></strong>', $markup, $name);
            $this->assertStringContainsString('<strong class="block text-lg font-bold leading-7 text-olive"><bdi>'.$lineTotal.'</bdi></strong>', $markup, $name);
        }

        // No quantity steppers or remove buttons on the checkout review.
        $this->assertStringNotContainsString('cart-qty', $main);
        $this->assertStringNotContainsString('cart-remove-item', $main);

        preg_match('/<aside class="reveal [^"]*">(.*?)<\/aside>/s', $main, $aside);
        $this->assertStringContainsString('<bdi>6</bdi> منتجات من <bdi>3</bdi> متاجر', $aside[1]);
        $this->assertStringContainsString('<strong class="font-bold text-ink"><bdi>₪1,011</bdi></strong>', $aside[1]);
        // The approved design shows the delivery total twice: the highlighted box and the plain row.
        $this->assertSame(2, substr_count($aside[1], '<bdi>₪55</bdi>'));
        $this->assertStringContainsString('<strong class="text-3xl font-bold leading-10 text-olive"><bdi>₪1,066</bdi></strong>', $aside[1]);
    }

    public function test_payment_selector_starts_unselected_with_hidden_error(): void
    {
        $main = $this->mainContent();

        $this->assertStringContainsString('<section data-payment-selector class=', $main);
        $this->assertStringContainsString('role="radiogroup" aria-describedby="paymentError"', $main);
        foreach (['online' => 'paymentOnline', 'cod' => 'paymentCod'] as $value => $id) {
            $this->assertMatchesRegularExpression('/<label for="'.$id.'" tabindex="0" data-payment-value="'.$value.'"\s+class="payment-card [^"]*"\s+role="radio" aria-checked="false">/', $main);
            $this->assertStringContainsString('<input id="'.$id.'" type="radio" name="paymentMethod" value="'.$value.'" class="sr-only">', $main);
        }
        $this->assertStringNotContainsString('name="paymentMethod" value="online" class="sr-only" checked', $main);
        $this->assertStringNotContainsString('name="paymentMethod" value="cod" class="sr-only" checked', $main);

        $this->assertMatchesRegularExpression('/<p id="paymentError" class="mt-4 hidden [^"]*"\s+role="alert"><\/p>/', $main);
        $this->assertStringContainsString('<strong data-selected-payment class="mt-1 block text-sm font-bold leading-6 text-ink">لم يتم الاختيار بعد</strong>', $main);
        $this->assertStringContainsString('<button id="placeOrderButton" type="button" aria-disabled="true"', $main);
    }

    public function test_confirmation_modal_is_hidden_and_final_confirm_is_a_plain_button(): void
    {
        $html = $this->checkoutHtml();

        $this->assertMatchesRegularExpression('/<div id="confirmOrderModal" class="fixed [^"]* hidden [^"]*" role="dialog"\s+aria-modal="true" aria-labelledby="confirmOrderTitle">/', $html);
        $this->assertStringContainsString('تنويه: بعد تأكيد الطلب لا يمكن إلغاؤه', $html);
        $this->assertStringContainsString('<strong data-confirm-payment class="text-ink"></strong>', $html);
        $this->assertStringContainsString('<strong class="text-lg font-bold text-olive"><bdi>₪1,066</bdi></strong>', $html);
        $this->assertSame(2, substr_count($html, 'data-close-confirm-modal'));
        $this->assertStringContainsString('رجوع للمراجعة', $html);
        $this->assertMatchesRegularExpression('/<button id="finalConfirmButton" type="button"\s+class="[^"]*">\s*<span>تأكيد الطلب نهائياً<\/span>/', $html);
    }

    public function test_fake_success_and_toast_are_not_migrated(): void
    {
        $html = $this->checkoutHtml();

        $this->assertStringNotContainsString('checkoutSuccessTemplate', $html);
        $this->assertStringNotContainsString('HF-2026-0814', $html);
        $this->assertStringNotContainsString('order-detail', $html);
        $this->assertStringNotContainsString('تم استلام طلبك بنجاح', $html);
        $this->assertStringNotContainsString('<template', $html);
        $this->assertStringNotContainsString('id="toast"', $html);
    }

    public function test_page_has_no_backend_submission(): void
    {
        $html = $this->checkoutHtml();

        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('formaction', $html);
        $this->assertStringNotContainsString('name="_token"', $html);
        $this->assertStringNotContainsString('type="submit"', $this->mainAndModal($html));
        $this->assertStringNotContainsString('type="hidden"', $html);
        $this->assertStringNotContainsString('fetch(', $html);
        $this->assertStringNotContainsString('XMLHttpRequest', $html);
        // Only the address and payment radios carry names; none is a backend field such as payment_method.
        preg_match_all('/\sname="([^"]+)"/', $this->mainAndModal($html), $names);
        $this->assertSame(['addressChoice', 'paymentMethod'], array_values(array_unique($names[1])));
        $this->assertStringNotContainsString('customer_address_id', $html);
        $this->assertStringNotContainsString('delivery_fees', $html);
    }

    public function test_page_script_is_local_ui_only(): void
    {
        $script = file_get_contents(resource_path('js/storefront-checkout.js'));

        $this->assertStringContainsString("$('[data-checkout-page]')", $script);
        $this->assertStringContainsString('storefrontCheckoutInitialized', $script);
        foreach (['fetch(', 'XMLHttpRequest', '$.ajax', '$.post', '$.get', 'axios', 'localStorage', 'sessionStorage', 'location', 'customer/checkout', 'checkoutSuccessTemplate', '#toast', '.submit('] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $script, $forbidden);
        }
        $this->assertStringNotContainsString("$('#finalConfirmButton').on", $script);

        $this->assertStringContainsString("'resources/js/storefront-checkout.js'", file_get_contents(base_path('vite.config.js')));
        $this->assertStringContainsString("@vite('resources/js/storefront-checkout.js')", file_get_contents(resource_path('views/pages/checkout.blade.php')));
    }

    public function test_product_and_vendor_navigation_stays_deferred(): void
    {
        $main = $this->mainContent();

        $this->assertSame(6, substr_count($main, '<a role="link" aria-disabled="true" data-deferred-navigation="product.html" class='));
        foreach (array_keys(self::VENDORS) as $slug) {
            $this->assertStringContainsString('<a role="link" aria-disabled="true" data-deferred-navigation="vendor.html?id='.$slug.'" class=', $main, $slug);
        }
        $this->assertStringNotContainsString('href="'.route('vendors.show').'"', $main);
        $this->assertStringNotContainsString('href="'.route('product').'"', $main);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\.html/', $this->mainAndModal($this->checkoutHtml()));
    }

    public function test_get_display_and_post_store_routes_coexist(): void
    {
        $show = Route::getRoutes()->getByName('customer.checkout.show');
        $store = Route::getRoutes()->getByName('customer.checkout.store');

        $this->assertSame('customer/checkout', $show->uri());
        $this->assertSame('customer/checkout', $store->uri());
        $this->assertEqualsCanonicalizing(['GET', 'HEAD'], $show->methods());
        $this->assertSame(['POST'], $store->methods());
        $this->assertSame('pages.checkout', $show->defaults['view'] ?? null);
        $this->assertSame(CheckoutController::class.'@store', $store->getActionName());
        $this->assertContains('auth:customer', $show->gatherMiddleware());
        $this->assertContains('auth:customer', $store->gatherMiddleware());

        // The POST endpoint still answers as before: guests go to the customer login.
        $this->post(route('customer.checkout.store'))->assertRedirect(route('customer.login'));
    }

    public function test_rendering_the_checkout_page_queries_no_commerce_tables_and_writes_nothing(): void
    {
        $customer = Customer::factory()->create();

        DB::enableQueryLog();
        $this->actingAs($customer, 'customer')->get(route('customer.checkout.show'))->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();

        $this->assertDoesNotMatchRegularExpression('/\b(carts|cart_items|customer_addresses|products|vendors|orders|vendor_orders|order_items|payments|stock_reservations|governorates|cities)\b/', $queries);
        $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete)\b/im', $queries);
        foreach (['carts', 'orders', 'payments', 'stock_reservations'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
    }

    private function checkoutHtml(): string
    {
        return $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(route('customer.checkout.show'))->assertOk()->getContent();
    }

    private function mainContent(): string
    {
        $html = $this->checkoutHtml();
        $start = strpos($html, '<main data-checkout-page>');
        $this->assertNotFalse($start);

        return substr($html, $start, strpos($html, '</main>', $start) + 7 - $start);
    }

    private function mainAndModal(string $html): string
    {
        $start = strpos($html, '<main data-checkout-page>');
        // Vendor groups contain their own <footer>; the storefront footer is the last one.
        $end = strrpos($html, '<footer');

        return substr($html, $start, $end - $start);
    }
}
