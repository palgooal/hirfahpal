<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Favorites is a static storefront migration: the five approved demo cards render
 * as-is, filtering is local UI, and every persistence action stays inert.
 */
class CustomerFavoritesPageTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Favorites-page-1';

    private const CARDS = [
        'pitcher-blue-hebron' => ['إبريق الخليل الأزرق', 'دار الكرمة للخزف', '₪145', 'جديد'],
        'silk-embroidered-cushion' => ['وسادة تطريز خيوط حرير طبيعي', 'تعاونية نساء نابلس', '₪220', 'تطريز يدوي <bdi>100%</bdi>'],
        'straw-basket-hebron' => ['سلة قش فلسطينية من الخليل', 'جمعية إحسان بيت لحم', '₪95', 'إصدار'],
        'serving-plate-ornate' => ['طبق تقديم مزخرف', 'دار الكرمة للخزف', '₪120', 'مميز'],
        'olive-oil-candle' => ['شمعة زيت زيتون في فخار ريفي', 'تعاونية نساء نابلس', '₪68', 'طبيعي <bdi>100%</bdi>'],
    ];

    public function test_guest_is_sent_to_customer_login_and_returns_after_login(): void
    {
        $customer = Customer::factory()->create(['email' => 'favorites@example.com', 'password' => self::PASSWORD]);

        $this->get(route('customer.favorites'))->assertRedirect(route('customer.login'));
        $this->assertSame(route('customer.favorites'), session('url.intended'));

        $this->post(route('customer.login.store'), ['login' => $customer->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('customer.favorites'));
    }

    public function test_active_customer_gets_the_storefront_favorites_page(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(route('customer.favorites'))
            ->assertOk()
            ->assertViewIs('pages.customer-favorites')
            ->assertSee('<title>المفضلة | حرفة</title>', false)
            ->assertSee('<meta name="description" content="مفضلة حسابي في حرفة - القطع المحفوظة من الحرفيين والمشاغل">', false)
            ->assertSee('<main data-favorites-page>', false)
            ->assertDontSee('data-profile-page', false)
            ->assertSee('storefront-favorites', false);
    }

    public function test_title_section_keeps_the_static_counts_markup(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('<h1 class="text-[30px] font-bold leading-9 text-ink sm:text-4xl sm:leading-[48px]">مفضلتي</h1>', $page);
        $this->assertStringContainsString('<bdi id="favoritesCount">5</bdi> قطع محفوظة من <bdi id="favoritesVendorCount">3</bdi> حرفيين ومشاغل — كل قطعة عليها اسم التاجر عشان تعرف مصدرها بسرعة.', $page);
    }

    public function test_sidebar_marks_favorites_active_and_links_are_real(): void
    {
        $page = $this->page();

        $this->assertMatchesRegularExpression('/<a\s+href="'.preg_quote(route('customer.favorites'), '/').'"\s+aria-current="page"/', $page);
        foreach (['customer.dashboard', 'customer.addresses', 'customer.account-details'] as $real) {
            $this->assertMatchesRegularExpression('/<a\s+href="'.preg_quote(route($real), '/').'"\s+class=/', $page, $real);
        }
        $this->assertStringContainsString('data-deferred-navigation="dashboard/orders.html"', $page);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\.html/', $page);
    }

    public function test_dashboard_favorites_card_links_to_the_favorites_page(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(route('customer.dashboard'))
            ->assertSee('<a href="'.route('customer.favorites').'" class="flex min-h-32', false);
    }

    public function test_the_five_approved_static_favorite_cards_are_rendered(): void
    {
        $page = $this->page();

        $this->assertSame(5, substr_count($page, 'class="favorite-card product-card'));
        preg_match_all('/data-product-id="([^"]+)"/', $page, $ids);
        $this->assertSame(array_keys(self::CARDS), $ids[1]);

        foreach (self::CARDS as $id => [$name, $vendor, $price, $badge]) {
            $start = strpos($page, 'data-product-id="'.$id.'"');
            $card = substr($page, $start, strpos($page, '</article>', $start) - $start);

            $this->assertStringContainsString('data-vendor="'.$vendor.'"', $card, $id);
            $this->assertStringContainsString('>'.$name.'</h3>', $card, $id);
            $this->assertStringContainsString('class="text-lg font-bold leading-7">'.$price.'</bdi>', $card, $id);
            $this->assertStringContainsString($badge, $card, $id);
        }
    }

    public function test_filter_bar_starts_with_all_cards_and_hidden_states(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('<input id="favoritesSearch" type="search" placeholder="ابحث باسم القطعة أو التاجر..."', $page);
        preg_match_all('/<option value="([^"]+)">([^<]+)<\/option>/', $page, $options);
        $this->assertSame(['all', 'دار الكرمة للخزف', 'تعاونية نساء نابلس', 'جمعية إحسان بيت لحم'], $options[1]);
        $this->assertSame('كل التجار', $options[2][0]);
        $this->assertStringContainsString('<p id="favoritesResultsSummary" class="text-start text-sm font-semibold text-muted">عرض <bdi>5</bdi> من <bdi>5</bdi> قطعة</p>', $page);

        $this->assertMatchesRegularExpression('/<div id="favoritesNoMatchState" class="hidden [^"]*">/', $page);
        $this->assertStringContainsString('<section id="favoritesEmptyState" class="hidden">', $page);
        $this->assertStringContainsString('لسا ما ضفت شي للمفضلة', $page);
    }

    public function test_empty_state_ctas_use_the_migrated_routes(): void
    {
        $page = $this->page();

        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('categories'), '/').'"\s+class="[^"]*">\s*<span>تصفح التصنيفات<\/span>/', $page);
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('vendors'), '/').'"\s+class="[^"]*">\s*<span>تصفح كل المتاجر<\/span>/', $page);
    }

    public function test_card_controls_are_inert_and_links_deferred(): void
    {
        $page = $this->page();

        $this->assertSame(5, preg_match_all('/<button\s+type="button"\s+class="remove-favorite /', $page));
        $this->assertSame(5, preg_match_all('/<button\s+type="button"\s+class="add-cart /', $page));
        $this->assertSame(5, substr_count($page, '<a role="link" aria-disabled="true" data-deferred-navigation="product.html">'));
        $this->assertSame(5, substr_count($page, '<a role="link" aria-disabled="true" data-deferred-navigation="vendor.html" class="flex justify-start pb-3">'));
        $this->assertStringNotContainsString('href="'.route('product').'"', $page);
        $this->assertStringNotContainsString('href="'.route('vendors.show').'"', $page);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\.html/', $page);
    }

    public function test_no_favorites_mutation_routes_are_registered(): void
    {
        $favoriteRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'favorite'));

        $this->assertSame(['customer/favorites'], $favoriteRoutes->map->uri()->unique()->values()->all());
        $this->assertEqualsCanonicalizing(['GET', 'HEAD'], $favoriteRoutes->flatMap->methods()->unique()->values()->all());
    }

    public function test_rendering_does_not_query_favorites_products_or_vendors(): void
    {
        $customer = Customer::factory()->create();

        DB::enableQueryLog();
        $this->actingAs($customer, 'customer')->get(route('customer.favorites'))->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();

        $this->assertDoesNotMatchRegularExpression('/\b(favorites|wishlists|products|vendors|vendor_profiles)\b/', $queries);
    }

    public function test_blocked_customer_is_forced_out(): void
    {
        $customer = Customer::factory()->create(['status' => 'blocked']);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.favorites'))
            ->assertRedirect(route('customer.login'));

        $this->assertGuest('customer');
    }

    public function test_vendor_and_driver_dashboards_are_unaffected(): void
    {
        $this->actingAs(Vendor::factory()->approved()->create(), 'vendor')
            ->get(route('vendor.dashboard'))->assertOk()->assertViewIs('vendor-dashboard.home');

        $this->actingAs(DeliveryDriver::factory()->create(), 'delivery_driver')
            ->get(route('delivery-driver.dashboard'))->assertOk()->assertViewIs('accounts.dashboard');
    }

    private function page(): string
    {
        return $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(route('customer.favorites'))
            ->assertOk()
            ->getContent();
    }
}
