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
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * ORD-01C: the vendor's orders list in Arabic and English: translated column headers, status labels on the dashboard
 * home's badge, dates in the page language and the application timezone, order numbers and totals isolated left to
 * right, the vendor order's own total, and translated action buttons whose accessible names give the order number.
 * The page gives the texts (data-list-texts); the script renders them as plain text and its formatting is unit tested
 * in tests/js/vendor-resource-list.test.mjs. The actions offered and what they send are unchanged.
 */
class VendorOrderListPresentationTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = 'resources/js/vendor-resource-list.js';

    private const CSS = 'resources/css/vendor-dashboard.css';

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

    public function test_the_orders_list_texts_in_arabic_rtl_and_english_ltr(): void
    {
        $before = TranslationValue::count();
        $expected = [
            'ar' => [
                'dir' => 'rtl',
                'actions' => 'الإجراءات',
                'columns' => ['number' => 'رقم الطلب', 'status' => 'الحالة', 'order.customer.name' => 'العميل', 'created_at' => 'التاريخ', 'total' => 'الإجمالي'],
                'statuses' => ['pending' => 'قيد الانتظار', 'assigned' => 'مُسند لمندوب'],
                'orderActions' => [
                    'accept' => ['label' => 'قبول', 'named' => 'قبول الطلب :number'],
                    'reject' => ['label' => 'رفض', 'named' => 'رفض الطلب :number'],
                    'preparing' => ['label' => 'قيد التحضير', 'named' => 'نقل الطلب :number إلى قيد التحضير'],
                    'ready' => ['label' => 'جاهز', 'named' => 'تحديد الطلب :number جاهزًا للتسليم'],
                ],
                'actionError' => 'تعذّر تحديث الطلب. حاول مرة أخرى.',
            ],
            'en' => [
                'dir' => 'ltr',
                'actions' => 'Actions',
                'columns' => ['number' => 'Order number', 'status' => 'Status', 'order.customer.name' => 'Customer', 'created_at' => 'Date', 'total' => 'Total'],
                'statuses' => ['pending' => 'Pending', 'assigned' => 'Assigned to a driver'],
                'orderActions' => [
                    'accept' => ['label' => 'Accept', 'named' => 'Accept order :number'],
                    'reject' => ['label' => 'Reject', 'named' => 'Reject order :number'],
                    'preparing' => ['label' => 'Preparing', 'named' => 'Mark order :number as preparing'],
                    'ready' => ['label' => 'Ready', 'named' => 'Mark order :number as ready for delivery'],
                ],
                'actionError' => 'The order could not be updated. Please try again.',
            ],
        ];

        foreach ($expected as $locale => $want) {
            $html = $this->page($locale);
            $this->assertMatchesRegularExpression("/<html lang=\"{$locale}\" dir=\"{$want['dir']}\"/", $html);
            $texts = $this->texts($html);

            $this->assertSame($want['actions'], $texts['actions']);
            $this->assertSame($want['columns'], $texts['columns'], 'One header per column, by column path.');
            $this->assertSame(array_keys($this->list()->json('statuses')), array_keys($texts['statuses']), 'A label for every status the endpoint returns.');
            foreach ($want['statuses'] as $status => $label) {
                $this->assertSame($label, $texts['statuses'][$status]);
            }
            $this->assertSame($want['orderActions'], $texts['orderActions']);
            $this->assertSame($want['actionError'], $texts['actionError']);
            foreach ($texts['orderActions'] as $kind => $action) {
                $this->assertStringContainsString(':number', $action['named'], $kind);
                $this->assertStringContainsString(mb_strtolower($action['label']), mb_strtolower($action['named']), "{$kind}: the visible label is in the accessible name.");
            }
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $locale === 'en' ? json_encode($texts, JSON_UNESCAPED_UNICODE) : '', 'English has no Arabic text.');
        }

        $this->assertSame($before, TranslationValue::count(), 'Rendering creates no translation rows.');
        foreach (array_filter(array_keys(VendorDashboardTranslationSeeder::TRANSLATIONS), fn ($key) => str_starts_with($key, 'vendor.Order_List_') || str_starts_with($key, 'vendor.Order_Action_')) as $key) {
            $this->assertSame(['ar', 'en'], TranslationValue::query()->where('key', $key)->orderBy('locale')->pluck('locale')->all(), $key);
        }
    }

    public function test_the_table_is_the_dashboard_homes_orders_table_with_the_application_timezone(): void
    {
        $xpath = $this->xpath($this->page('ar'));
        /** @var DOMElement $root */
        $root = $xpath->query('//*[@data-vendor-resource-list]')->item(0);

        $this->assertSame(config('app.timezone'), $root->getAttribute('data-timezone'));
        $this->assertFalse($root->hasAttribute('data-moderation-texts'), 'Moderation badges are for products only.');
        $table = $xpath->query('.//*[@data-resource-table]//table', $root)->item(0);
        $this->assertSame('vendor-recent-table vendor-order-table', $table->getAttribute('class'), 'Cards with labelled rows on a phone.');
        $this->assertSame(1, $xpath->query('.//*[@data-resource-table][contains(@class, "table-responsive")]', $root)->length);
        foreach (['data-order-filters', 'data-resource-pager', 'data-resource-empty-filtered', 'data-resource-loading', 'data-resource-error', 'data-resource-empty'] as $kept) {
            $this->assertSame(1, $xpath->query(".//*[@{$kept}]", $root)->length, "ORD-01A / ORD-01B: {$kept} is kept.");
        }
    }

    public function test_the_other_lists_keep_their_table(): void
    {
        foreach (['products.page', 'reviews.page', 'returns.page', 'disputes.page', 'commissions.page'] as $route) {
            $html = $this->actingAs($this->vendor, 'vendor')->get(route("vendor.dashboard.{$route}"))->assertOk()->getContent();
            $this->assertStringContainsString('<table class="table table-hover align-middle mb-0">', $html, $route);
            $this->assertStringNotContainsString('data-timezone', $html, $route);
        }
    }

    public function test_the_total_is_the_vendor_orders_own_total_not_the_parent_grand_total(): void
    {
        $order = Order::query()->create(['number' => 'HF-700001', 'customer_id' => Customer::factory()->create(['name' => 'سارة'])->id, 'status' => 'pending', 'payment_method' => 'cod', 'payment_status' => 'pending', 'subtotal' => 95, 'delivery_total' => 10, 'grand_total' => 105]);
        VendorOrder::query()->create(['number' => 'HF-700001-V1', 'order_id' => $order->id, 'vendor_id' => $this->vendor->id, 'status' => 'pending', 'subtotal' => 25, 'delivery_fee' => 5, 'commission_amount' => 0, 'total' => 30]);
        VendorOrder::query()->create(['number' => 'HF-700001-V2', 'order_id' => $order->id, 'vendor_id' => Vendor::factory()->approved()->create()->id, 'status' => 'pending', 'subtotal' => 70, 'delivery_fee' => 5, 'commission_amount' => 0, 'total' => 75]);

        $row = $this->list()->assertOk()->json('orders.data.0');

        $this->assertSame(['HF-700001-V1', 'pending', 'سارة', '30.00'], [$row['number'], $row['status'], $row['order']['customer']['name'], $row['total']], 'The fields each column reads.');
        $this->assertNotSame($row['order']['grand_total'], $row['total']);
        $this->assertIsString($row['created_at']);
    }

    public function test_the_script_renders_plain_text_with_isolated_numbers_and_unchanged_actions(): void
    {
        $script = file_get_contents(base_path(self::SCRIPT));

        $this->assertStringContainsString("orders: { key: 'orders', columns: [['number', 'Order'], ['status', 'Status'], ['order.customer.name', 'Customer'], ['created_at', 'Date'], ['total', 'Total']] },", $script);
        $this->assertStringContainsString("if (path === 'number' || path === 'total') isolated.dir = 'ltr';", $script, 'Order numbers and totals read left to right in Arabic.');
        $this->assertStringContainsString("badge.className = 'vendor-status-badge';", $script);
        $this->assertStringContainsString('badge.dataset.status = String(value);', $script, 'The stored status is kept on the badge.');
        $this->assertStringContainsString("const time = document.createElement('time');", $script);
        $this->assertStringContainsString('cell.dataset.label = columnLabel(path, label, texts);', $script, 'Every cell is labelled for the card layout.');
        $this->assertStringContainsString('orderActionsFor(item.status).forEach(addAction);', $script);
        $this->assertStringContainsString('const name = numberedLabel(named, item.number);', $script);
        $this->assertStringContainsString("button.setAttribute('aria-label', name);", $script);
        // ORD-01D: the request moved, unchanged, to vendor-order-actions.js (VendorOrderActionDialogsTest).
        $this->assertStringContainsString("button.addEventListener('click', () => orderActions.request(action, item, button));", $script);
        $this->assertStringContainsString('fetchImpl(`${endpoint}/${id}/${action}`, {', file_get_contents(base_path('resources/js/vendor-order-actions.js')), 'Each action calls the same endpoint as before.');
        $this->assertStringNotContainsString('window.prompt', $script, 'ORD-01D: the reject dialog replaces the prompt.');
        $this->assertStringContainsString('error.textContent = loadErrorText;', $script, 'Each load shows the load error text again.');
        $this->assertStringNotContainsString('innerHTML', $script);
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $script);

        $css = file_get_contents(base_path(self::CSS));
        $this->assertStringContainsString('html.vendor-shell .vendor-order-actions {', $css);
        $this->assertMatchesRegularExpression('/@media \(max-width: 639\.98px\) \{\s*html\.vendor-shell \.vendor-order-actions \{\s*justify-content: flex-end;\s*\}\s*html\.vendor-shell \.vendor-order-actions \.btn \{\s*min-height: 44px;/', $css, 'Touch-sized actions on a phone.');
    }

    // ---------------------------------------------------------------- helpers

    /** @return array<string, mixed> the page's data-list-texts */
    private function texts(string $html): array
    {
        /** @var DOMElement $root */
        $root = $this->xpath($html)->query('//*[@data-vendor-resource-list]')->item(0);

        return json_decode($root->getAttribute('data-list-texts'), true, flags: JSON_THROW_ON_ERROR);
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
