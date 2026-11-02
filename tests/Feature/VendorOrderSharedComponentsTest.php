<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\TranslationValue;
use App\Models\Vendor;
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
 * ORD-02A (step 2): the order UI pieces the orders list and the coming order details page share, extracted without
 * changing the list: the Accept/Reject dialogs and the action feedback (Blade partials), the status, action and
 * outcome texts (VendorOrderTexts, same keys and wording) and the presentation helpers (vendor-order-shared.js,
 * re-exported by vendor-resource-list.js). The list's behavior stays covered by the ORD-01 tests.
 */
class VendorOrderSharedComponentsTest extends TestCase
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

    public function test_the_shared_texts_are_exactly_what_the_orders_list_embeds_in_both_languages(): void
    {
        $before = TranslationValue::count();

        foreach (['ar' => 'قيد الانتظار', 'en' => 'Pending'] as $locale => $pending) {
            /** @var DOMElement $root */
            $root = $this->xpath($this->ordersPage($locale))->query('//*[@data-vendor-resource-list]')->item(0);
            $list = json_decode($root->getAttribute('data-list-texts'), true, flags: JSON_THROW_ON_ERROR);
            $outcomes = json_decode($root->getAttribute('data-order-action-texts'), true, flags: JSON_THROW_ON_ERROR);

            app()->setLocale($locale);
            $this->assertSame(VendorOrderTexts::statuses(), $list['statuses'], $locale);
            $this->assertSame(VendorOrderTexts::actions(), $list['orderActions'], $locale);
            $this->assertSame(VendorOrderTexts::actionError(), $list['actionError'], $locale);
            $this->assertSame(VendorOrderTexts::outcomes(), $outcomes, $locale);
            $this->assertSame($pending, VendorOrderTexts::statuses()['pending']);
            $this->assertSame(VendorOrderTexts::actionError(), VendorOrderTexts::outcomes()['errors']['failed']);
        }

        $this->assertSame($before, TranslationValue::count(), 'No translation rows are created.');
    }

    public function test_the_shared_texts_use_only_existing_seeded_keys(): void
    {
        $source = file_get_contents(app_path('Support/VendorDashboard/VendorOrderTexts.php'));
        preg_match_all("/t\\('([^']+)'/", $source, $matches);

        // 10 statuses, 4 action labels and 4 accessible names, the action error, 4 successes, 5 errors, 3 reason texts.
        $this->assertCount(31, array_unique($matches[1]));
        foreach (array_unique($matches[1]) as $key) {
            $this->assertArrayHasKey($key, VendorDashboardTranslationSeeder::TRANSLATIONS, $key);
        }
    }

    public function test_the_partials_render_on_their_own_in_both_languages(): void
    {
        foreach (['ar' => ['قبول الطلب :number؟', 'رفض الطلب', 'إلغاء'], 'en' => ['Accept order :number?', 'Reject order', 'Cancel']] as $locale => [$acceptTitle, $rejectButton, $cancel]) {
            app()->setLocale($locale);
            $xpath = $this->xpath('<html><body>'.view('vendor-dashboard.partials.order-action-dialogs')->render().view('vendor-dashboard.partials.order-action-feedback')->render().'</body></html>');

            $this->assertSame(1, $xpath->query('//dialog[@data-order-dialog="accept"][@aria-labelledby="vendor-order-accept-title"][@aria-describedby="vendor-order-accept-description"]')->length);
            $this->assertSame(1, $xpath->query('//dialog[@data-order-dialog="reject"][@aria-labelledby="vendor-order-reject-title"][@aria-describedby="vendor-order-reject-description"]')->length);
            $this->assertSame($acceptTitle, $xpath->query('//*[@id="vendor-order-accept-title"]')->item(0)->getAttribute('data-template'));
            $this->assertSame($rejectButton, trim($xpath->query('//dialog[@data-order-dialog="reject"]//button[@data-order-dialog-confirm]')->item(0)->textContent));
            $this->assertSame($cancel, trim($xpath->query('//dialog[@data-order-dialog="accept"]//button[@data-order-dialog-cancel]')->item(0)->textContent));
            $this->assertSame('1000', $xpath->query('//textarea[@data-order-reject-reason]')->item(0)->getAttribute('maxlength'));
            $this->assertSame('status', $xpath->query('//*[@data-order-success]')->item(0)->getAttribute('role'));
            $this->assertSame('alert', $xpath->query('//*[@data-order-failure]')->item(0)->getAttribute('role'));
        }
    }

    public function test_the_orders_list_uses_the_partials_and_keeps_no_inline_copy(): void
    {
        $view = file_get_contents(resource_path('views/vendor-dashboard/resources.blade.php'));

        $this->assertSame(1, substr_count($view, "@include('vendor-dashboard.partials.order-action-feedback')"));
        $this->assertSame(1, substr_count($view, "@include('vendor-dashboard.partials.order-action-dialogs')"));
        $this->assertStringNotContainsString('data-order-dialog=', $view);
        $this->assertStringNotContainsString('data-order-success', $view);
        $this->assertStringNotContainsString("t('vendor.Order_Action_Success_Accept'", $view, 'Texts come from VendorOrderTexts.');

        $html = $this->ordersPage('en');
        $this->assertSame(1, substr_count($html, 'data-order-dialog="accept"'));
        $this->assertSame(1, substr_count($html, 'data-order-dialog="reject"'));
        $this->assertSame(1, substr_count($html, 'data-order-success'));
        $xpath = $this->xpath($html);
        $this->assertSame(2, $xpath->query('//*[@data-vendor-resource-list]//section//*[@data-order-success or @data-order-failure]')->length, 'The feedback stays inside the list card.');
        $this->assertSame(2, $xpath->query('//*[@data-vendor-resource-list]/dialog[@data-order-dialog]')->length, 'The dialogs stay directly inside the list root.');
    }

    public function test_the_shared_module_is_pure_and_the_list_imports_it(): void
    {
        $shared = file_get_contents(resource_path('js/vendor-order-shared.js'));
        $list = file_get_contents(resource_path('js/vendor-resource-list.js'));

        $this->assertStringNotContainsString('document', preg_replace('#/\*.*?\*/#s', '', $shared), 'No DOM access.');
        $this->assertStringNotContainsString('fetch(', $shared, 'No requests.');
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $shared);
        $this->assertStringContainsString("import { formatAmount, formatOrderDate, numberedLabel, ORDER_ACTION_STYLES } from './vendor-order-shared.js';", $list);
        $this->assertStringContainsString('export { formatAmount, formatOrderDate, numberedLabel, ORDER_ACTION_STYLES };', $list);
        foreach (['export function formatOrderDate', 'export function formatAmount', 'export const numberedLabel', 'export const ORDER_ACTION_STYLES'] as $definition) {
            $this->assertStringNotContainsString($definition, $list, "{$definition} lives in vendor-order-shared.js only.");
        }
    }

    public function test_the_shared_module_passes_its_node_tests(): void
    {
        $node = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node' : 'command -v node'));
        $node = strtok($node, "\r\n") ?: 'node';

        $process = proc_open([$node, '--test', 'tests/js/vendor-order-shared.test.mjs'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), $output);
        $this->assertMatchesRegularExpression('/^(?:ℹ|#) fail 0$/m', $output);
        $this->assertDoesNotMatchRegularExpression('/^(?:ℹ|#) pass 0$/m', $output);
    }

    private function ordersPage(string $locale): string
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
