<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Language;
use App\Models\Order;
use App\Models\TranslationValue;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * ORD-01B: the vendor's orders list has a search field, a status filter and a needs-response filter, sent as the orders
 * endpoint's existing search, status and needs_response parameters (the endpoint is unchanged). The page starts from
 * page 1 on every change and keeps the filters across pages; the script's filter logic is unit tested in
 * tests/js/vendor-resource-list.test.mjs. These tests pin the API behavior the page relies on and the page markup.
 */
class VendorOrderListFiltersTest extends TestCase
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

    // ---------------------------------------------------------------- the parameters the page sends

    public function test_search_finds_the_vendor_order_number_the_parent_order_number_and_the_customer_name(): void
    {
        $sara = $this->order(vendorOrderNumber: 'VO-ALPHA-1', orderNumber: 'HF-500100', customer: 'سارة أحمد');
        $omar = $this->order(vendorOrderNumber: 'VO-BETA-2', orderNumber: 'HF-500200', customer: 'Omar Khalil');

        $this->assertSame([$sara], $this->ids(['search' => 'ALPHA']), 'Vendor order number.');
        $this->assertSame([$omar], $this->ids(['search' => '500200']), 'Parent order number.');
        $this->assertSame([$sara], $this->ids(['search' => 'سارة']), 'Customer name, in Arabic.');
        $this->assertSame([$omar], $this->ids(['search' => 'Khalil']), 'Customer name, in English.');
        $this->assertSame([], $this->ids(['search' => 'no-such-order']));
        $this->assertSame([$omar, $sara], $this->ids(['search' => '   ']), 'A blank search is no search.');
    }

    public function test_search_never_finds_another_vendors_order(): void
    {
        $mine = $this->order(vendorOrderNumber: 'VO-SHARED-1', customer: 'Lina');
        $this->order(vendorOrderNumber: 'VO-SHARED-2', customer: 'Lina', vendor: Vendor::factory()->approved()->create());

        $this->assertSame([$mine], $this->ids(['search' => 'SHARED']));
        $this->assertSame([$mine], $this->ids(['search' => 'Lina']));
    }

    public function test_status_and_needs_response_filters(): void
    {
        $pending = $this->order(status: 'pending');
        $accepted = $this->order(status: 'accepted');
        $rejected = $this->order(status: 'rejected');

        $this->assertSame([$accepted], $this->ids(['status' => 'accepted']));
        $this->assertSame([$rejected], $this->ids(['status' => 'rejected']));
        $this->assertSame([], $this->ids(['status' => 'delivered']));
        $this->assertSame([$pending], $this->ids(['needs_response' => '1']), 'Needs response: pending orders.');
        $this->assertSame([$rejected, $accepted, $pending], $this->ids(['needs_response' => '0']), 'Off: every order.');
    }

    public function test_combined_filters_narrow_each_other(): void
    {
        $match = $this->order(status: 'pending', customer: 'Huda Saleh');
        $this->order(status: 'accepted', customer: 'Huda Saleh');
        $this->order(status: 'pending', customer: 'Karim');

        $this->assertSame([$match], $this->ids(['search' => 'Huda', 'status' => 'pending']));
        $this->assertSame([$match], $this->ids(['search' => 'Huda', 'needs_response' => '1']));
        $this->assertSame([$match], $this->ids(['search' => 'Huda', 'status' => 'pending', 'needs_response' => '1']));
        $this->assertSame([], $this->ids(['search' => 'Huda', 'status' => 'accepted', 'needs_response' => '1']), 'Needs response with another status: nothing (the API applies both).');
    }

    public function test_filters_are_kept_across_pages_in_one_order(): void
    {
        $pending = [];
        foreach (range(1, 30) as $i) {
            $id = $this->order(status: $i % 2 ? 'pending' : 'accepted', customer: 'Nour', at: Carbon::parse('2026-10-15 12:00:00')->addSeconds($i));
            if ($i % 2) {
                $pending[] = $id;
            }
        }

        $query = ['search' => 'Nour', 'status' => 'pending'];
        $first = $this->list([...$query, 'page' => 1])->assertOk();
        $second = $this->list([...$query, 'page' => 2])->assertOk();

        $this->assertSame([15, 2, 12, 3], [$first->json('orders.total'), $first->json('orders.last_page'), count($first->json('orders.data')), count($second->json('orders.data'))]);
        $this->assertSame(array_reverse($pending), [...array_column($first->json('orders.data'), 'id'), ...array_column($second->json('orders.data'), 'id')], 'Only pending orders, newest first, each once.');
        $this->assertStringContainsString('search=Nour', $first->json('orders.next_page_url'));
        $this->assertStringContainsString('status=pending', $first->json('orders.next_page_url'));
        $this->assertSame(['orders', 'statuses'], array_keys($first->json()), 'The payload keys are unchanged.');
    }

    // ---------------------------------------------------------------- the page

    public function test_the_orders_page_has_translated_search_and_filters_in_arabic_rtl_and_english_ltr(): void
    {
        $before = TranslationValue::count();
        $texts = [
            'ar' => ['rtl', 'البحث في الطلبات وتصفيتها', 'بحث', 'رقم الطلب أو اسم العميل', 'الحالة', 'جميع الحالات', 'بانتظار ردّك', 'تطبيق', 'مسح عوامل التصفية', 'لا توجد طلبات تطابق البحث أو التصفية.', 'قيد الانتظار'],
            'en' => ['ltr', 'Search and filter orders', 'Search', 'Order number or customer name', 'Status', 'All statuses', 'Needs your response', 'Apply', 'Clear filters', 'No orders match your search or filters.', 'Pending'],
        ];

        foreach ($texts as $locale => [$dir, $formLabel, $searchLabel, $placeholder, $statusLabel, $all, $needs, $apply, $reset, $noResults, $pendingLabel]) {
            $html = $this->page($locale);
            $xpath = $this->xpath($html);
            $this->assertMatchesRegularExpression("/<html lang=\"{$locale}\" dir=\"{$dir}\"/", $html);

            $forms = $xpath->query('//*[@data-vendor-resource-list]//form[@data-order-filters]');
            $this->assertSame(1, $forms->length);
            /** @var DOMElement $form */
            $form = $forms->item(0);
            $this->assertSame(['search', $formLabel, 'vendor-order-filters'], [$form->getAttribute('role'), $form->getAttribute('aria-label'), $form->getAttribute('class')]);

            /** @var DOMElement $search */
            $search = $xpath->query('.//input[@data-order-search]', $form)->item(0);
            $this->assertSame(['search', 'search', 'vendor-order-search', $placeholder], [$search->getAttribute('type'), $search->getAttribute('name'), $search->getAttribute('id'), $search->getAttribute('placeholder')]);
            $this->assertFalse($search->hasAttribute('dir'), 'The search field takes the page direction.');
            $this->assertSame($searchLabel, trim($xpath->query('.//label[@for="vendor-order-search"]', $form)->item(0)->textContent));

            $this->assertSame($statusLabel, trim($xpath->query('.//label[@for="vendor-order-status"]', $form)->item(0)->textContent));
            $options = $xpath->query('.//select[@id="vendor-order-status"][@name="status"][@data-order-status]/option', $form);
            $this->assertSame(['', $all], [$options->item(0)->getAttribute('value'), trim($options->item(0)->textContent)]);
            $this->assertSame($pendingLabel, trim($options->item(1)->textContent));

            /** @var DOMElement $checkbox */
            $checkbox = $xpath->query('.//input[@data-order-needs-response]', $form)->item(0);
            $this->assertSame(['checkbox', 'needs_response', '1', 'vendor-order-needs-response'], [$checkbox->getAttribute('type'), $checkbox->getAttribute('name'), $checkbox->getAttribute('value'), $checkbox->getAttribute('id')]);
            $this->assertSame($needs, trim($xpath->query('.//label[@for="vendor-order-needs-response"]', $form)->item(0)->textContent));

            $this->assertSame($apply, trim($xpath->query('.//button[@type="submit"]', $form)->item(0)->textContent));
            $this->assertSame($reset, trim($xpath->query('.//button[@type="button"][@data-order-filters-reset]', $form)->item(0)->textContent));

            /** @var DOMElement $empty */
            $empty = $xpath->query('//*[@data-resource-empty-filtered]')->item(0);
            $this->assertSame([$noResults, 'status'], [trim($empty->textContent), $empty->getAttribute('role')]);
            $this->assertTrue($empty->hasAttribute('hidden'));
            foreach (['data-resource-loading', 'data-resource-error', 'data-resource-empty', 'data-resource-pager'] as $state) {
                $this->assertSame(1, $xpath->query("//*[@{$state}]")->length, "The {$state} element is kept.");
            }
        }

        $this->assertSame($before, TranslationValue::count(), 'Rendering creates no translation rows.');
        foreach (array_keys(array_filter(VendorDashboardTranslationSeeder::TRANSLATIONS, fn ($key) => str_starts_with($key, 'vendor.Order_Filter'), ARRAY_FILTER_USE_KEY)) as $key) {
            $this->assertSame(['ar', 'en'], TranslationValue::query()->where('key', $key)->orderBy('locale')->pluck('locale')->all(), $key);
        }
    }

    public function test_the_status_options_are_the_statuses_of_the_orders_endpoint_in_its_order(): void
    {
        $api = array_keys($this->list()->assertOk()->json('statuses'));
        $options = [];
        foreach ($this->xpath($this->page('en'))->query('//select[@data-order-status]/option') as $option) {
            $options[] = $option->getAttribute('value');
        }

        $this->assertSame(['', ...$api], $options);
    }

    public function test_only_the_orders_list_has_search_and_filters(): void
    {
        foreach (['products.page', 'reviews.page', 'returns.page', 'disputes.page', 'commissions.page'] as $route) {
            $html = $this->actingAs($this->vendor, 'vendor')->get(route("vendor.dashboard.{$route}"))->assertOk()->getContent();
            $this->assertStringNotContainsString('data-order-filters', $html, $route);
            $this->assertStringNotContainsString('data-resource-empty-filtered', $html, $route);
        }
    }

    public function test_the_script_sends_the_filters_with_every_page_and_starts_again_from_page_one(): void
    {
        $script = file_get_contents(base_path(self::SCRIPT));

        $this->assertStringContainsString('pageUrl(root.dataset.endpoint, page, window.location.href, filters)', $script, 'The pager, Refresh and order actions keep the filters.');
        $this->assertStringContainsString("const filterForm = root.dataset.resource === 'orders' ? root.querySelector('[data-order-filters]') : null;", $script);
        $this->assertSame(1, substr_count($script, 'load(1);'), 'Apply and Clear filters both go through applyFilters, which loads page 1.');
        $this->assertStringContainsString('applyFilters(orderFilters({ search: searchInput.value, status: statusSelect.value, needsResponse: needsResponse.checked }, statuses));', $script);
        $this->assertStringContainsString('applyFilters(orderFilters({}, statuses));', $script);
        $this->assertStringContainsString('window.history.replaceState(', $script);
        $this->assertStringNotContainsString('innerHTML', $script);
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $script);
    }

    // ---------------------------------------------------------------- helpers

    private function order(string $status = 'pending', ?string $vendorOrderNumber = null, ?string $orderNumber = null, string $customer = 'Customer', ?Vendor $vendor = null, ?Carbon $at = null): int
    {
        static $sequence = 0;
        $sequence++;
        $order = Order::query()->create(['number' => $orderNumber ?? 'HF-'.(900000 + $sequence), 'customer_id' => Customer::factory()->create(['name' => $customer])->id, 'status' => 'pending', 'payment_method' => 'cod', 'payment_status' => 'pending', 'subtotal' => 10, 'delivery_total' => 0, 'grand_total' => 10]);
        $vendorOrder = VendorOrder::query()->create(['number' => $vendorOrderNumber ?? 'VO-'.(900000 + $sequence), 'order_id' => $order->id, 'vendor_id' => ($vendor ?? $this->vendor)->id, 'status' => $status, 'subtotal' => 10, 'delivery_fee' => 0, 'commission_amount' => 0, 'total' => 10]);
        $time = $at ?? Carbon::parse('2026-10-15 12:00:00')->addSeconds($sequence);
        DB::table('vendor_orders')->where('id', $vendorOrder->id)->update(['created_at' => $time, 'updated_at' => $time]);

        return $vendorOrder->id;
    }

    /** @return list<int> the ids on page 1 for the query, in the API's order */
    private function ids(array $query): array
    {
        return array_column($this->list($query)->assertOk()->json('orders.data'), 'id');
    }

    private function list(array $query = [])
    {
        return $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.orders.index', $query));
    }

    private function page(string $locale): string
    {
        return $this->actingAs($this->vendor, 'vendor')->withSession(['locale' => $locale])->get(route('vendor.dashboard.orders.page'))->assertOk()->getContent();
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
