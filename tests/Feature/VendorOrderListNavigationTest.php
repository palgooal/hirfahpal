<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Language;
use App\Models\Order;
use App\Models\TranslationValue;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Support\VendorDashboard\VendorOrderListContext;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ORD-02A (step 4): from the orders list to an order's details page and back. Each list row links to
 * vendor.dashboard.orders.details with the list's context (search, status, needs_response, page); the list keeps its page
 * in its address; the details page leads back to the orders list with that context only, cleaned by
 * VendorOrderListContext (never an address from the request). The list script's URL handling is unit tested in
 * tests/js/vendor-resource-list.test.mjs and the navigation in a real browser.
 */
class VendorOrderListNavigationTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = 'resources/js/vendor-resource-list.js';

    private Vendor $vendor;

    private string $hotFile = '';

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
        $this->vendor = Vendor::factory()->approved()->create();
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    // ---------------------------------------------------------------- the context

    public static function contexts(): array
    {
        return [
            'nothing' => [[], []],
            'all four' => [['search' => 'HF-1', 'status' => 'pending', 'needs_response' => '1', 'page' => '3'], ['search' => 'HF-1', 'status' => 'pending', 'needs_response' => '1', 'page' => '3']],
            'search trimmed, Arabic kept' => [['search' => '  سارة أحمد  '], ['search' => 'سارة أحمد']],
            'blank search' => [['search' => '   '], []],
            'needs_response as the list reads it' => [['needs_response' => 'yes'], ['needs_response' => '1']],
            'needs_response off' => [['needs_response' => '0'], []],
            'needs_response other' => [['needs_response' => 'maybe'], []],
            'unknown status' => [['status' => 'archived'], []],
            'script in status' => [['status' => '<script>alert(1)</script>'], []],
            'page 1 is the default' => [['page' => '1'], []],
            'page bounds' => [['page' => '999999'], ['page' => '999999']],
            'page too large' => [['page' => '1000000'], []],
            'page not a whole number' => [['page' => '2.5'], []],
            'page negative' => [['page' => '-2'], []],
            'page with text' => [['page' => '2abc'], []],
            'page with leading zero' => [['page' => '02'], []],
            'page with space' => [['page' => ' 3'], []],
            'arrays are dropped' => [['search' => ['x'], 'status' => ['pending'], 'needs_response' => ['1'], 'page' => ['2']], []],
            'unknown keys are dropped' => [['redirect' => 'https://evil.test', 'return' => '//evil.test', 'url' => 'javascript:alert(1)', 'next' => '/admin', 'vendor_id' => '2'], []],
        ];
    }

    #[DataProvider('contexts')]
    public function test_only_the_four_list_parameters_survive_and_only_when_valid(array $query, array $expected): void
    {
        $this->assertSame($expected, VendorOrderListContext::fromQuery($query));
        $this->assertSame(route('vendor.dashboard.orders.page', $expected), VendorOrderListContext::listUrl($query));
    }

    // ---------------------------------------------------------------- the details page leads back

    public function test_the_details_page_leads_back_to_the_list_it_came_from(): void
    {
        $order = $this->order();
        $query = ['search' => 'HF 1&2', 'status' => 'pending', 'needs_response' => '1', 'page' => '2'];
        $expected = route('vendor.dashboard.orders.page', $query);

        $xpath = $this->xpath($this->page(route('vendor.dashboard.orders.details', [$order, ...$query])));

        $this->assertSame($expected, $xpath->query('//a[@data-details-back]')->item(0)->getAttribute('href'));
        $this->assertSame($expected, $xpath->query('//ul[contains(@class, "breadcrumb")]/li[2]/a')->item(0)->getAttribute('href'), 'The Orders breadcrumb too.');
        $this->assertSame($expected, $xpath->query('//*[@data-details-gone]//a')->item(0)->getAttribute('href'), 'And the "no longer available" link.');
        $this->assertSame(route('vendor.dashboard.orders.details', $order), $xpath->query('//*[@data-vendor-order-details]')->item(0)->getAttribute('data-endpoint'), 'The refresh URL carries no context.');
    }

    public function test_hostile_return_parameters_never_leave_the_orders_list(): void
    {
        $order = $this->order();
        $hostile = [
            'redirect' => 'https://evil.test/phish', 'return' => '//evil.test', 'url' => 'javascript:alert(1)', 'next' => '/admin',
            'status' => '"><script>alert(1)</script>', 'page' => '-5', 'needs_response' => 'evil', 'search' => ['https://evil.test'],
        ];

        $html = $this->page(route('vendor.dashboard.orders.details', [$order, ...$hostile]));
        $xpath = $this->xpath($html);

        $this->assertSame(route('vendor.dashboard.orders.page'), $xpath->query('//a[@data-details-back]')->item(0)->getAttribute('href'), 'Only the plain orders list remains.');
        $this->assertSame(route('vendor.dashboard.orders.page'), $xpath->query('//ul[contains(@class, "breadcrumb")]/li[2]/a')->item(0)->getAttribute('href'));
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html, 'Markup never appears as markup.');

        // Every link stays on this site. (The shell's language switcher links to this same page with its query, encoded;
        // the locale middleware then redirects to the path alone. That is the existing shell, not this page's way back.)
        $host = parse_url(config('app.url'), PHP_URL_HOST);
        foreach ($xpath->query('//a[@href]') as $link) {
            $href = $link->getAttribute('href');
            $this->assertDoesNotMatchRegularExpression('#^\s*(javascript:|//)#i', $href, $href);
            if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $href) === 1) {
                $this->assertSame($host, parse_url($href, PHP_URL_HOST), $href);
            }
        }
        foreach ($xpath->query('//a[contains(@class, "vendor-lang-item")]') as $switch) {
            $this->assertStringStartsWith(route('vendor.dashboard.orders.details', $order).'?', $switch->getAttribute('href'), 'The language switcher only links to this same page.');
        }
    }

    public function test_a_search_with_markup_stays_text(): void
    {
        $html = $this->page(route('vendor.dashboard.orders.details', [$this->order(), 'search' => '"><img src=x onerror=alert(1)>']));

        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertSame(route('vendor.dashboard.orders.page', ['search' => '"><img src=x onerror=alert(1)>']), $this->xpath($html)->query('//a[@data-details-back]')->item(0)->getAttribute('href'));
    }

    public function test_the_context_never_opens_another_vendors_order(): void
    {
        $theirs = $this->order(Vendor::factory()->approved()->create(), 'HF-NAV-THEIRS');

        $this->actingAs($this->vendor, 'vendor')->get(route('vendor.dashboard.orders.details', [$theirs, 'search' => 'x', 'page' => '2']))->assertNotFound();
        $this->actingAs($this->vendor, 'vendor')->get(route('vendor.dashboard.orders.page', ['page' => '2', 'status' => 'pending']))->assertOk();
    }

    // ---------------------------------------------------------------- the list's links

    public function test_the_list_gives_its_rows_a_translated_details_link_to_the_named_route(): void
    {
        $before = TranslationValue::count();

        foreach (['ar' => ['عرض التفاصيل', 'عرض التفاصيل للطلب :number'], 'en' => ['View details', 'View details of order :number']] as $locale => [$label, $named]) {
            $html = $this->actingAs($this->vendor, 'vendor')->withSession(['locale' => $locale])->get(route('vendor.dashboard.orders.page'))->assertOk()->getContent();
            /** @var DOMElement $root */
            $root = $this->xpath($html)->query('//*[@data-vendor-resource-list]')->item(0);
            $base = $root->getAttribute('data-details-base');
            $texts = json_decode($root->getAttribute('data-list-texts'), true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame(route('vendor.dashboard.orders.page'), $base);
            $this->assertSame(route('vendor.dashboard.orders.details', 42), $base.'/42', 'The script\'s {base}/{id} is the named route.');
            $this->assertSame(['label' => $label, 'named' => $named], $texts['detailsLink']);
            $this->assertStringContainsString($label, $named, "{$locale}: the visible label is in the accessible name.");
        }

        $this->assertSame($before, TranslationValue::count());
        foreach (['vendor.Order_List_View_Details', 'vendor.Order_List_View_Details_Named'] as $key) {
            $this->assertSame(['ar', 'en'], TranslationValue::query()->where('key', $key)->orderBy('locale')->pluck('locale')->all(), $key);
        }
        foreach (['products.page', 'reviews.page'] as $route) {
            $this->assertStringNotContainsString('data-details-base', $this->actingAs($this->vendor, 'vendor')->get(route("vendor.dashboard.{$route}"))->getContent(), $route);
        }
    }

    public function test_the_script_uses_a_plain_link_and_keeps_the_page_in_the_address_for_orders_only(): void
    {
        $script = file_get_contents(base_path(self::SCRIPT));

        $this->assertStringContainsString('const href = detailsUrl(root.dataset.detailsBase, item.id, filters, page, window.location.href);', $script);
        $this->assertStringContainsString("const link = document.createElement('a');", $script);
        $this->assertStringContainsString('link.href = href;', $script);
        $this->assertStringNotContainsString('link.addEventListener', $script, 'Native link behavior: new tab, middle click, copy address.');
        $this->assertStringContainsString("if (filterForm) page = pageNumber(new URLSearchParams(window.location.search).get('page'));", $script);
        $this->assertStringContainsString('render(items);'."\n".'            syncAddress();', $script);
        $this->assertStringContainsString('if (!filterForm) return;', $script, 'The products list keeps its page in memory.');
        $this->assertStringContainsString('listQuery(window.location.search, filters, 1)', $script, 'A filter change goes back to page 1.');
        $this->assertStringNotContainsString('innerHTML', $script);
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $script);
    }

    // ---------------------------------------------------------------- helpers

    private function order(?Vendor $vendor = null, string $number = 'HF-NAV-1'): VendorOrder
    {
        $order = Order::query()->create(['number' => 'P-'.$number, 'customer_id' => Customer::factory()->create()->id, 'status' => 'pending', 'payment_method' => 'cod', 'payment_status' => 'pending', 'subtotal' => 10, 'delivery_total' => 0, 'grand_total' => 10]);

        return VendorOrder::query()->create(['number' => $number, 'order_id' => $order->id, 'vendor_id' => ($vendor ?? $this->vendor)->id, 'status' => 'pending', 'subtotal' => 10, 'delivery_fee' => 0, 'commission_amount' => 0, 'total' => 10]);
    }

    private function page(string $url): string
    {
        return $this->actingAs($this->vendor, 'vendor')->get($url)->assertOk()->getContent();
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }
}
