<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Language;
use App\Models\Order;
use App\Models\TranslationValue;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Support\VendorDashboard\VendorOrderTexts;
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
 * ORD-02A (step 3): the vendor order details page (FR-VEN-011–015, FR-VEN-020; UC-VEN-005/006): summary (status, date,
 * rejection reason), the server's actions with the shared dialogs and feedback, items, the stored amounts and the
 * timeline, with refreshing, error/Retry and "no longer available" states, in Arabic and English. The page renders the
 * structure and labels; resources/js/vendor-order-details.js fills in the Step 1 contract (its data handling is tested in
 * tests/js/vendor-order-details.test.mjs, the interaction in a real browser). The data contract is VendorOrderDetailsDataContractTest.
 */
class VendorOrderDetailsPageTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = 'resources/js/vendor-order-details.js';

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

    public function test_the_page_sections_in_arabic_rtl_and_english_ltr(): void
    {
        $order = $this->order();
        $before = TranslationValue::count();
        $expected = [
            'ar' => ['rtl', ['ملخص الطلب', 'المنتجات', 'المبالغ', 'مراحل الطلب'], ['الحالة', 'تم إنشاء الطلب', 'سبب الرفض', 'المجموع الفرعي', 'رسوم التوصيل', 'الإجمالي'], ['المنتج', 'رمز المنتج (SKU)', 'الكمية', 'سعر الوحدة', 'المجموع'], 'الإجراءات', 'لا يحتوي هذا الطلب على منتجات.', 'هذا الطلب لم يعد متاحًا.', 'العودة إلى الطلبات', 'إعادة المحاولة'],
            'en' => ['ltr', ['Order summary', 'Items', 'Amounts', 'Order timeline'], ['Status', 'Order placed', 'Reason for rejection', 'Subtotal', 'Delivery fee', 'Total'], ['Product', 'SKU', 'Quantity', 'Unit price', 'Line total'], 'Actions', 'This order has no items.', 'This order is no longer available.', 'Back to orders', 'Retry'],
        ];

        foreach ($expected as $locale => [$dir, $headings, $labels, $columns, $actions, $noItems, $gone, $back, $retry]) {
            $html = $this->page($order, $locale);
            $xpath = $this->xpath($html);
            $this->assertMatchesRegularExpression("/<html lang=\"{$locale}\" dir=\"{$dir}\"/", $html);
            /** @var DOMElement $root */
            $root = $xpath->query('//*[@data-vendor-order-details]')->item(0);

            $sections = $xpath->query('.//section[@aria-labelledby]', $root);
            $this->assertSame(4, $sections->length);
            $titles = [];
            foreach ($sections as $section) {
                $title = $xpath->query('.//*[@id="'.$section->getAttribute('aria-labelledby').'"]', $section)->item(0);
                $this->assertNotNull($title, 'Each section is named by its heading.');
                $titles[] = trim($title->textContent);
            }
            $this->assertSame($headings, $titles);
            $this->assertSame(1, $xpath->query('//h1')->length);
            $this->assertSame(4, $xpath->query('.//h2[contains(@class, "vendor-section-title")][@id]', $root)->length);

            $this->assertSame($labels, array_map(fn ($node) => trim($node->textContent), iterator_to_array($xpath->query('.//dl//dt', $root))));
            $this->assertSame($columns, array_map(fn ($node) => trim($node->textContent), iterator_to_array($xpath->query('.//table//th[@scope="col"]', $root))));
            $this->assertSame($actions, trim($xpath->query('.//*[@id="vendor-order-actions-title"]', $root)->item(0)->textContent));
            $this->assertSame('vendor-order-actions-title', $xpath->query('.//*[@data-details-actions][@role="group"]', $root)->item(0)->getAttribute('aria-labelledby'));
            $this->assertSame($noItems, trim($xpath->query('.//*[@data-details-no-items][@hidden]', $root)->item(0)->textContent));

            // States: refreshing (status), failed load (alert + Retry), no longer available (heading + back to the list).
            $this->assertSame('status', $xpath->query('.//*[@data-details-refreshing][@hidden]', $root)->item(0)->getAttribute('role'));
            $this->assertSame('alert', $xpath->query('.//*[@data-details-error][@hidden]', $root)->item(0)->getAttribute('role'));
            $this->assertSame($retry, trim($xpath->query('.//button[@type="button"][@data-details-retry]', $root)->item(0)->textContent));
            $this->assertSame($gone, trim($xpath->query('.//*[@data-details-gone][@hidden]//h2[@tabindex="-1"]', $root)->item(0)->textContent));
            $link = $xpath->query('.//*[@data-details-gone]//a', $root)->item(0);
            $this->assertSame([route('vendor.dashboard.orders.page'), $back], [$link->getAttribute('href'), trim($link->textContent)]);

            // The orders list's dialogs and feedback, once, inside the page root the action script is given.
            $this->assertSame(1, $xpath->query('./dialog[@data-order-dialog="accept"]', $root)->length);
            $this->assertSame(1, $xpath->query('./dialog[@data-order-dialog="reject"]', $root)->length);
            $this->assertSame(1, $xpath->query('.//*[@data-order-success][@role="status"]', $root)->length);
            $this->assertSame(1, $xpath->query('.//*[@data-order-failure][@role="alert"]', $root)->length);
        }

        $this->assertSame($before, TranslationValue::count(), 'Rendering creates no translation rows.');
        foreach (array_filter(array_keys(VendorDashboardTranslationSeeder::TRANSLATIONS), fn ($key) => str_starts_with($key, 'vendor.Order_Details_')) as $key) {
            $this->assertSame(['ar', 'en'], TranslationValue::query()->where('key', $key)->orderBy('locale')->pluck('locale')->all(), $key);
        }
    }

    public function test_the_page_gives_the_script_its_endpoints_and_texts(): void
    {
        $order = $this->order();

        foreach (['ar' => ['تم تحديث بيانات الطلب', 'قيد الانتظار'], 'en' => ['The order has been refreshed.', 'Pending']] as $locale => [$refreshed, $pending]) {
            /** @var DOMElement $root */
            $root = $this->xpath($this->page($order, $locale))->query('//*[@data-vendor-order-details]')->item(0);
            $this->assertSame(route('vendor.dashboard.orders.details', $order), $root->getAttribute('data-endpoint'));
            $this->assertSame(route('vendor.dashboard.orders.index'), $root->getAttribute('data-action-endpoint'), 'The existing action endpoints: orders/{id}/{action}.');
            $this->assertSame(config('app.timezone'), $root->getAttribute('data-timezone'));

            $texts = json_decode($root->getAttribute('data-details-texts'), true, flags: JSON_THROW_ON_ERROR);
            $outcomes = json_decode($root->getAttribute('data-order-action-texts'), true, flags: JSON_THROW_ON_ERROR);
            app()->setLocale($locale);
            $this->assertSame(['statuses', 'actions', 'columns', 'timeline'], array_keys($texts));
            $this->assertSame(VendorOrderTexts::statuses(), $texts['statuses']);
            $this->assertSame(VendorOrderTexts::actions(), $texts['actions']);
            $this->assertSame($pending, $texts['statuses']['pending']);
            $this->assertSame(['created_at', 'accepted_at', 'rejected_at', 'ready_at', 'delivered_at', 'customer_receipt_confirmed_at', 'completed_at'], array_keys($texts['timeline']));
            $this->assertSame(['product_name', 'product_sku', 'quantity', 'unit_price', 'line_total'], array_keys($texts['columns']));

            $shared = VendorOrderTexts::outcomes();
            $this->assertSame($shared['success'], $outcomes['success']);
            $this->assertSame($shared['reason'], $outcomes['reason']);
            foreach (['session', 'forbidden', 'network', 'failed'] as $key) {
                $this->assertSame($shared['errors'][$key], $outcomes['errors'][$key], $key);
            }
            foreach (['conflict', 'conflictAccept'] as $key) {
                $this->assertStringContainsString($refreshed, $outcomes['errors'][$key], "{$key}: this page refreshes the order, not the list.");
            }
        }
    }

    public function test_no_customer_address_driver_parent_commission_or_deadline_value_is_anywhere_in_the_page(): void
    {
        $html = $this->page($this->order(), 'en');

        foreach (['customer.page@example.test', '0599777888', 'Page Customer', 'Page Recipient', '0598666555', 'Page Street 4', 'Ring twice', '31.5000', '35.1000',
            'PARENT-PAGE-1', '888.88', 'page parent note', '23.45', 'commission', 'vendor_response_due_at', 'grand_total', 'payment_status', 'stock_reservations', 'delivery_driver'] as $value) {
            $this->assertStringNotContainsString($value, $html, $value);
        }
    }

    public function test_the_page_loads_its_script_and_the_list_is_unchanged(): void
    {
        $html = $this->page($this->order(), 'en');
        $this->assertStringContainsString('resources/js/vendor-order-details.js', $html);
        $this->assertStringContainsString("'resources/js/vendor-order-details.js',", file_get_contents(base_path('vite.config.js')));

        $list = $this->actingAs($this->vendor, 'vendor')->get(route('vendor.dashboard.orders.page'))->assertOk()->getContent();
        $this->assertStringNotContainsString('vendor-order-details.js', $list);
        $this->assertStringNotContainsString('/vendor/dashboard/orders/manage/', $list, 'No list-to-details navigation in this step.');
    }

    public function test_the_script_reuses_the_shared_pieces_and_sends_no_action_itself(): void
    {
        $script = file_get_contents(base_path(self::SCRIPT));

        $this->assertStringContainsString("import { actionTexts, createOrderActions } from './vendor-order-actions.js';", $script);
        $this->assertStringContainsString("import { formatAmount, formatOrderDate, numberedLabel, ORDER_ACTION_STYLES } from './vendor-order-shared.js';", $script);
        $this->assertStringContainsString('orderActions.request(action, { id: view.id, number: view.number }, button)', $script);
        $this->assertStringContainsString("headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }", $script, 'The reload reads the page URL as JSON.');
        foreach (['PATCH', 'method:', 'X-CSRF-TOKEN', 'innerHTML', 'insertAdjacentHTML', 'outerHTML'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $script, $forbidden);
        }
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $script);
    }

    public function test_the_script_passes_its_node_tests(): void
    {
        $node = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node' : 'command -v node'));
        $node = strtok($node, "\r\n") ?: 'node';

        $process = proc_open([$node, '--test', 'tests/js/vendor-order-details.test.mjs'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), $output);
        $this->assertMatchesRegularExpression('/^(?:ℹ|#) fail 0$/m', $output);
        $this->assertDoesNotMatchRegularExpression('/^(?:ℹ|#) pass 0$/m', $output);
    }

    // ---------------------------------------------------------------- helpers

    /** A vendor order whose parent order carries every excluded kind of value. */
    private function order(): VendorOrder
    {
        $customer = Customer::factory()->create(['name' => 'Page Customer', 'email' => 'customer.page@example.test', 'phone' => '0599777888']);
        $order = Order::query()->create([
            'number' => 'PARENT-PAGE-1', 'customer_id' => $customer->id, 'status' => 'pending', 'payment_method' => 'cod', 'payment_status' => 'paid',
            'subtotal' => 870, 'delivery_total' => 18.88, 'grand_total' => 888.88, 'notes' => 'page parent note',
            'delivery_address_source' => 'checkout', 'delivery_recipient_name' => 'Page Recipient', 'delivery_phone' => '0598666555',
            'delivery_address_line' => 'Page Street 4', 'delivery_notes' => 'Ring twice', 'delivery_latitude' => '31.5000000', 'delivery_longitude' => '35.1000000',
        ]);

        return VendorOrder::query()->create([
            'number' => 'HF-PAGE-1-V1', 'order_id' => $order->id, 'vendor_id' => $this->vendor->id, 'status' => 'pending',
            'subtotal' => 25, 'delivery_fee' => 5, 'commission_amount' => 23.45, 'total' => 30, 'vendor_response_due_at' => now()->addDay(),
        ]);
    }

    private function page(VendorOrder $order, string $locale): string
    {
        return $this->actingAs($this->vendor, 'vendor')->withSession(['locale' => $locale])->get(route('vendor.dashboard.orders.details', $order))->assertOk()->getContent();
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
