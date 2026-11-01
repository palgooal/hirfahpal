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
 * ORD-01A: the vendor's orders list pages through all of the vendor's orders with the products list's pager. The API is
 * unchanged (12 per page, payload keys orders and statuses); the page only adds the pager. VEN-BE-032: the API orders
 * newest first with the id breaking ties, so equal timestamps still give one order and every order is on exactly one
 * page. The pager's logic is unit tested in tests/js/vendor-resource-list.test.mjs.
 */
class VendorOrderListPaginationTest extends TestCase
{
    use RefreshDatabase;

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

    // ---------------------------------------------------------------- the API pages (unchanged contract)

    public function test_twenty_five_orders_are_on_three_pages_once_each_newest_first(): void
    {
        $ids = $this->orders($this->vendor, 25);
        $theirs = $this->orders(Vendor::factory()->approved()->create(), 4, at: '2026-10-20 12:00:00');

        $seen = [];
        foreach ([[1, 12, 1, 12], [2, 12, 13, 24], [3, 1, 25, 25]] as [$page, $count, $from, $to]) {
            $response = $this->list(['page' => $page])->assertOk();
            $this->assertSame([$page, 3, 25, 12, $from, $to], [$response->json('orders.current_page'), $response->json('orders.last_page'), $response->json('orders.total'), $response->json('orders.per_page'), $response->json('orders.from'), $response->json('orders.to')]);
            $this->assertCount($count, $response->json('orders.data'));
            $seen = array_merge($seen, array_column($response->json('orders.data'), 'id'));
        }

        $this->assertSame(array_reverse($ids), $seen, 'Newest first, every order exactly once.');
        $this->assertSame(array_slice(array_reverse($ids), 12, 12), array_column($this->list(['page' => 2])->json('orders.data'), 'id'), 'Page 2 is the next twelve.');
        $this->assertSame([], array_intersect($theirs, $seen), "Never another vendor's order.");
    }

    public function test_twenty_six_orders_with_the_same_timestamp_are_on_three_pages_once_each_in_one_order(): void
    {
        $ids = $this->orders($this->vendor, 26, sameSecond: true);
        $theirs = $this->orders(Vendor::factory()->approved()->create(), 4, sameSecond: true);
        $expected = array_reverse($ids);

        $seen = [];
        foreach ([[1, 12], [2, 12], [3, 2]] as [$page, $count]) {
            $response = $this->list(['page' => $page])->assertOk();
            $this->assertSame([$page, 3, 26], [$response->json('orders.current_page'), $response->json('orders.last_page'), $response->json('orders.total')]);
            $pageIds = array_column($response->json('orders.data'), 'id');
            $this->assertCount($count, $pageIds);
            $this->assertSame(array_slice($expected, ($page - 1) * 12, 12), $pageIds, "Page {$page}: id descending within the tie.");
            $this->assertSame($pageIds, array_column($this->list(['page' => $page])->json('orders.data'), 'id'), "Page {$page} is the same when asked again.");
            $seen = array_merge($seen, $pageIds);
        }

        $this->assertSame($expected, $seen, 'Every order exactly once: none twice, none missing.');
        $this->assertSame($seen, array_values(array_unique($seen)));
        $this->assertSame([], array_intersect($theirs, $seen), "Never another vendor's order.");
    }

    public function test_newest_first_then_id_with_mixed_timestamps(): void
    {
        $older = $this->orders($this->vendor, 3, sameSecond: true, at: '2026-10-01 10:00:00');
        $newer = $this->orders($this->vendor, 3, sameSecond: true, at: '2026-10-02 10:00:00');

        $this->assertSame([...array_reverse($newer), ...array_reverse($older)], array_column($this->list()->json('orders.data'), 'id'));
    }

    public function test_another_vendor_pages_only_through_their_own_orders(): void
    {
        $this->orders($this->vendor, 20);
        $other = Vendor::factory()->approved()->create();
        $theirs = $this->orders($other, 3);

        $response = $this->actingAs($other, 'vendor')->getJson(route('vendor.dashboard.orders.index', ['page' => 1]))->assertOk();
        $this->assertSame([3, 1], [$response->json('orders.total'), $response->json('orders.last_page')]);
        $this->assertEqualsCanonicalizing($theirs, array_column($response->json('orders.data'), 'id'));
        $this->assertSame([], $this->actingAs($other, 'vendor')->getJson(route('vendor.dashboard.orders.index', ['page' => 2]))->json('orders.data'));
    }

    public function test_no_orders_one_page_and_a_page_past_the_end(): void
    {
        $empty = $this->list()->assertOk();
        $this->assertSame([[], 1, 1, 0], [$empty->json('orders.data'), $empty->json('orders.current_page'), $empty->json('orders.last_page'), $empty->json('orders.total')]);
        $this->assertSame(['orders', 'statuses'], array_keys($empty->json()), 'The payload keys are unchanged.');

        $this->orders($this->vendor, 12);
        $this->assertSame([1, 12], [$this->list()->json('orders.last_page'), count($this->list()->json('orders.data'))], 'Exactly twelve: one page.');

        $past = $this->list(['page' => 5])->assertOk();
        $this->assertSame([[], 5, 1], [$past->json('orders.data'), $past->json('orders.current_page'), $past->json('orders.last_page')], 'The page recovers to last_page.');
    }

    // ---------------------------------------------------------------- the page

    public function test_the_orders_page_has_a_labelled_pager_with_direction_correct_carets(): void
    {
        $before = TranslationValue::count();
        $texts = [
            'ar' => ['rtl', 'صفحات الطلبات', 'السابق', 'التالي', 'الصفحة :page من :last', 'ph-caret-right', 'ph-caret-left'],
            'en' => ['ltr', 'Order pages', 'Previous', 'Next', 'Page :page of :last', 'ph-caret-left', 'ph-caret-right'],
        ];

        foreach ($texts as $locale => [$dir, $label, $previous, $next, $status, $back, $forward]) {
            $html = $this->page($locale);
            $xpath = $this->xpath($html);
            $this->assertMatchesRegularExpression("/<html lang=\"{$locale}\" dir=\"{$dir}\"/", $html);

            $list = $xpath->query('//*[@data-vendor-resource-list]')->item(0);
            $this->assertSame('orders', $list->getAttribute('data-resource'));
            foreach (['data-resource-loading', 'data-resource-error', 'data-resource-table', 'data-resource-empty'] as $state) {
                $this->assertSame(1, $xpath->query(".//*[@{$state}]", $list)->length, "The {$state} state is kept.");
            }

            $navs = $xpath->query('.//nav[@data-resource-pager]', $list);
            $this->assertSame(1, $navs->length);
            /** @var DOMElement $nav */
            $nav = $navs->item(0);
            $this->assertSame('vendor-media-more', $nav->getAttribute('class'), 'The shared pager.');
            $this->assertSame($label, $nav->getAttribute('aria-label'));
            $this->assertSame($status, $nav->getAttribute('data-page-status'));
            $this->assertTrue($nav->hasAttribute('hidden'), 'Shown by the script when there is a second page.');

            [$prev, $nextButton] = [$xpath->query('.//button[@data-resource-previous]', $nav)->item(0), $xpath->query('.//button[@data-resource-next]', $nav)->item(0)];
            foreach ([$prev, $nextButton] as $button) {
                $this->assertSame('button', $button->getAttribute('type'));
                $this->assertTrue($button->hasAttribute('disabled'));
                $this->assertSame('true', $xpath->query('.//i', $button)->item(0)->getAttribute('aria-hidden'));
            }
            $this->assertSame($previous, trim($prev->textContent));
            $this->assertSame($next, trim($nextButton->textContent));
            $this->assertSame("ph-duotone {$back}", $xpath->query('.//i', $prev)->item(0)->getAttribute('class'));
            $this->assertSame("ph-duotone {$forward}", $xpath->query('.//i', $nextButton)->item(0)->getAttribute('class'));
            $this->assertSame('polite', $xpath->query('.//*[@data-resource-page-status]', $nav)->item(0)->getAttribute('aria-live'));

            $this->assertStringNotContainsString('data-product-editor', $html, 'No product editor on the orders page.');
            $this->assertStringContainsString('data-list-texts', $html, 'ORD-01C: the orders list has its own texts (VendorOrderListPresentationTest).');
        }

        $this->assertSame($before, TranslationValue::count(), 'Rendering creates no translation rows.');
        $this->assertSame(['ar', 'en'], TranslationValue::query()->where('key', 'vendor.Order_List_Pagination')->orderBy('locale')->pluck('locale')->all());
    }

    // ---------------------------------------------------------------- helpers

    /** @return list<int> vendor order ids in creation order (oldest first), one second apart or all in the same second */
    private function orders(Vendor $vendor, int $count, bool $sameSecond = false, string $at = '2026-10-15 12:00:00'): array
    {
        $customer = Customer::factory()->create();
        $ids = [];
        foreach (range(1, $count) as $i) {
            $order = Order::query()->create(['number' => 'HF-'.uniqid(), 'customer_id' => $customer->id, 'status' => 'pending', 'payment_method' => 'cod', 'payment_status' => 'pending', 'subtotal' => 10, 'delivery_total' => 0, 'grand_total' => 10]);
            $vendorOrder = VendorOrder::query()->create(['number' => 'VO-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => $vendor->id, 'status' => 'pending', 'subtotal' => 10, 'delivery_fee' => 0, 'commission_amount' => 0, 'total' => 10]);
            $time = $sameSecond ? Carbon::parse($at) : Carbon::parse($at)->addSeconds($i);
            DB::table('vendor_orders')->where('id', $vendorOrder->id)->update(['created_at' => $time, 'updated_at' => $time]);
            $ids[] = $vendorOrder->id;
        }

        return $ids;
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
