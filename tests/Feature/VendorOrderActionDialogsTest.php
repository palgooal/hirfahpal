<?php

namespace Tests\Feature;

use App\Http\Requests\VendorDashboard\RejectVendorOrderRequest;
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
 * ORD-01D: Accept and Reject ask first, in native modal dialogs (Accept: the order number, what accepting does,
 * Confirm / Cancel; Reject: a required reason of at most 1000 characters, Reject order / Cancel); Preparing and Ready
 * are sent at once as before. Every outcome is announced with the page's translated text, never the server's message.
 * The endpoints are unchanged: these tests pin the markup, the texts, and the server behavior the script relies on. The
 * dialog and feedback logic is tested in tests/js/vendor-order-actions.test.mjs against a fake document; the browser's
 * own dialog behavior (focus trap, inert page, Escape, screen reader output) is not tested here.
 */
class VendorOrderActionDialogsTest extends TestCase
{
    use RefreshDatabase;

    private const MODULE = 'resources/js/vendor-order-actions.js';

    private const LIST = 'resources/js/vendor-resource-list.js';

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

    // ---------------------------------------------------------------- the page

    public function test_the_dialogs_in_arabic_rtl_and_english_ltr(): void
    {
        $before = TranslationValue::count();
        $expected = [
            'ar' => ['rtl', 'قبول الطلب :number؟', 'سيتم قبول الطلب وخصم الكمية المحجوزة له من مخزونك. لا يمكنك التراجع عن ذلك من لوحة التحكم.', 'تأكيد القبول', 'إلغاء', 'رفض الطلب :number؟', 'سيتم رفض الطلب وتحرير الكمية المحجوزة له. يُحفظ السبب مع الطلب.', 'سبب الرفض', 'مطلوب. حتى 1000 حرف.', 'رفض الطلب'],
            'en' => ['ltr', 'Accept order :number?', 'The order will be accepted and the stock reserved for it will be deducted from your stock. You cannot undo this from your dashboard.', 'Confirm acceptance', 'Cancel', 'Reject order :number?', 'The order will be rejected and the stock reserved for it released. The reason is saved with the order.', 'Reason for rejection', 'Required. Up to 1000 characters.', 'Reject order'],
        ];

        foreach ($expected as $locale => [$dir, $acceptTitle, $acceptText, $confirm, $cancel, $rejectTitle, $rejectText, $reasonLabel, $hint, $rejectButton]) {
            $html = $this->page($locale);
            $xpath = $this->xpath($html);
            $this->assertMatchesRegularExpression("/<html lang=\"{$locale}\" dir=\"{$dir}\"/", $html);

            $accept = $this->dialog($xpath, 'accept');
            $this->assertSame($acceptTitle, $this->labelledBy($xpath, $accept)->getAttribute('data-template'));
            $this->assertSame($acceptText, trim($this->describedBy($xpath, $accept)->textContent));
            $this->assertButtons($xpath, $accept, $cancel, $confirm, 'btn-primary');

            $reject = $this->dialog($xpath, 'reject');
            $this->assertSame($rejectTitle, $this->labelledBy($xpath, $reject)->getAttribute('data-template'));
            $this->assertSame($rejectText, trim($this->describedBy($xpath, $reject)->textContent));
            $this->assertButtons($xpath, $reject, $cancel, $rejectButton, 'btn-danger');

            /** @var DOMElement $reason */
            $reason = $xpath->query('.//textarea[@data-order-reject-reason]', $reject)->item(0);
            $this->assertSame(['rejection_reason', '1000', 'true', 'false'], [$reason->getAttribute('name'), $reason->getAttribute('maxlength'), $reason->getAttribute('aria-required'), $reason->getAttribute('aria-invalid')]);
            $this->assertTrue($reason->hasAttribute('required'));
            $this->assertSame($reasonLabel, trim($xpath->query('.//label[@for="'.$reason->getAttribute('id').'"]', $reject)->item(0)->textContent));
            $described = explode(' ', $reason->getAttribute('aria-describedby'));
            $this->assertSame($hint, trim($xpath->query('//*[@id="'.$described[0].'"]')->item(0)->textContent));
            /** @var DOMElement $fieldError */
            $fieldError = $xpath->query('//*[@id="'.$described[1].'"]')->item(0);
            $this->assertTrue($fieldError->hasAttribute('data-order-reject-error') && $fieldError->hasAttribute('hidden'), 'The field error is the reason\'s description.');
            $this->assertSame('true', $xpath->query('.//*[@data-order-reject-count]', $reject)->item(0)->getAttribute('aria-hidden'), 'The counter is visual; the hint gives the limit.');

            foreach ([$accept, $reject] as $dialog) {
                $error = $xpath->query('.//*[@data-order-dialog-error]', $dialog)->item(0);
                $this->assertSame(['alert', true], [$error->getAttribute('role'), $error->hasAttribute('hidden')]);
            }

            $root = $xpath->query('//*[@data-vendor-resource-list]')->item(0);
            $this->assertSame('status', $xpath->query('.//*[@data-order-success]', $root)->item(0)->getAttribute('role'));
            $this->assertSame('alert', $xpath->query('.//*[@data-order-failure]', $root)->item(0)->getAttribute('role'));
            $this->assertSame(1, $xpath->query('.//dialog[@data-order-dialog="accept"]', $root)->length, 'Inside the list, which the script controls.');
        }

        $this->assertSame($before, TranslationValue::count(), 'Rendering creates no translation rows.');
    }

    public function test_the_outcome_texts_are_translated(): void
    {
        $ar = $this->actionTexts($this->page('ar'));
        $en = $this->actionTexts($this->page('en'));

        $this->assertSame(['accept', 'reject', 'preparing', 'ready'], array_keys($ar['success']));
        $this->assertSame(['conflict', 'conflictAccept', 'session', 'forbidden', 'network', 'failed'], array_keys($ar['errors']));
        $this->assertSame(['required', 'too_long', 'invalid'], array_keys($ar['reason']));
        $this->assertSame('تم قبول الطلب :number.', $ar['success']['accept']);
        $this->assertSame('Order :number was accepted.', $en['success']['accept']);
        $this->assertSame('تعذّر الاتصال. تحقّق من اتصالك وحاول مرة أخرى.', $ar['errors']['network']);
        $this->assertSame('يجب ألا يزيد السبب عن :max حرف.', $ar['reason']['too_long']);
        foreach (['success', 'errors', 'reason'] as $group) {
            foreach ($ar[$group] as $key => $text) {
                $this->assertMatchesRegularExpression('/\p{Arabic}/u', $text, "ar {$group}.{$key}");
                $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $en[$group][$key], "en {$group}.{$key}");
            }
        }
        foreach (array_filter(array_keys(VendorDashboardTranslationSeeder::TRANSLATIONS), fn ($key) => str_starts_with($key, 'vendor.Order_Accept_') || str_starts_with($key, 'vendor.Order_Reject_') || str_starts_with($key, 'vendor.Order_Action_')) as $key) {
            $this->assertSame(['ar', 'en'], TranslationValue::query()->where('key', $key)->orderBy('locale')->pluck('locale')->all(), $key);
        }
    }

    public function test_only_the_orders_list_has_the_dialogs(): void
    {
        foreach (['products.page', 'reviews.page', 'returns.page', 'disputes.page', 'commissions.page'] as $route) {
            $html = $this->actingAs($this->vendor, 'vendor')->get(route("vendor.dashboard.{$route}"))->assertOk()->getContent();
            foreach (['data-order-dialog', 'data-order-success', 'data-order-action-texts'] as $marker) {
                $this->assertStringNotContainsString($marker, $html, "{$route}: {$marker}");
            }
        }
    }

    // ---------------------------------------------------------------- the server behavior the dialogs rely on

    public function test_the_reason_limit_is_the_servers_and_the_server_trims_and_validates_as_the_dialog_does(): void
    {
        $this->assertSame(['required', 'string', 'max:1000'], (new RejectVendorOrderRequest)->rules()['rejection_reason']);

        foreach (['', '   ', str_repeat('a', 1001)] as $bad) {
            $response = $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.orders.reject', $this->vendorOrder()), ['rejection_reason' => $bad])->assertStatus(422);
            $this->assertArrayHasKey('rejection_reason', $response->json('errors'), 'A field error: the dialog shows its own translated text.');
        }

        $order = $this->vendorOrder();
        $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.orders.reject', $order), ['rejection_reason' => '  '.str_repeat('a', 1000).'  '])
            ->assertOk()->assertJsonPath('order.status', 'rejected');
        $this->assertSame(str_repeat('a', 1000), $order->fresh()->rejection_reason, 'Stored trimmed, exactly 1000 characters.');
    }

    public function test_success_answers_carry_the_new_status_and_refusals_have_no_field_errors(): void
    {
        $order = $this->vendorOrder();
        $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.orders.accept', $order))->assertOk()->assertJsonPath('order.status', 'accepted');

        $again = $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.orders.accept', $order))->assertStatus(422);
        $this->assertNull($again->json('errors'), 'A second Accept is refused without field errors: "the order changed".');
        $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.orders.preparing', $order))->assertOk()->assertJsonPath('order.status', 'preparing');
        $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.orders.ready', $order))->assertOk()->assertJsonPath('order.status', 'ready_for_delivery');

        $theirs = $this->vendorOrder(Vendor::factory()->approved()->create());
        $this->actingAs($this->vendor, 'vendor')->patchJson(route('vendor.dashboard.orders.accept', $theirs))->assertNotFound();
        $this->assertSame('pending', $theirs->fresh()->status);
    }

    public function test_the_actions_offered_per_status_match_the_endpoints_allowed_actions(): void
    {
        // The same table as orderActionsFor in tests/js/vendor-resource-list.test.mjs.
        $offered = [
            'pending' => ['accept', 'reject'], 'accepted' => ['mark_preparing', 'mark_ready'], 'preparing' => ['mark_preparing', 'mark_ready'],
            'ready_for_delivery' => ['mark_ready'], 'assigned' => [], 'out_for_delivery' => [], 'delivered' => [], 'completed' => [], 'rejected' => [], 'cancelled' => [],
        ];

        foreach ($offered as $status => $actions) {
            $allowed = $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.orders.show', $this->vendorOrder(status: $status)))->assertOk()->json('allowed_actions');
            $this->assertSame($actions, array_keys(array_filter($allowed)), $status);
        }
    }

    // ---------------------------------------------------------------- the scripts

    public function test_the_scripts_send_as_before_and_show_no_server_text(): void
    {
        $module = file_get_contents(base_path(self::MODULE));
        $list = file_get_contents(base_path(self::LIST));

        $this->assertStringContainsString("import { createOrderActions } from './vendor-order-actions.js';", $list);
        $this->assertStringNotContainsString('window.prompt', $list.$module);
        $this->assertStringContainsString("method: 'PATCH', credentials: 'same-origin', body,", $module);
        $this->assertStringContainsString("headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken, ...(body ? { 'Content-Type': 'application/json' } : {}) },", $module);
        $this->assertStringContainsString('body = JSON.stringify({ rejection_reason: reason });', $module);
        $this->assertStringContainsString('ui.dialog.showModal();', $module);
        $this->assertStringNotContainsString('payload.message', $module, 'The server message is never shown.');
        $this->assertStringNotContainsString('innerHTML', $module.$list);
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $module.$list);
    }

    public function test_the_scripts_pass_their_node_tests(): void
    {
        $node = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node' : 'command -v node'));
        $node = strtok($node, "\r\n") ?: 'node';

        $process = proc_open([$node, '--test', 'tests/js/vendor-order-actions.test.mjs'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), $output);
        $this->assertMatchesRegularExpression('/^(?:ℹ|#) fail 0$/m', $output);
        $this->assertDoesNotMatchRegularExpression('/^(?:ℹ|#) pass 0$/m', $output);
    }

    // ---------------------------------------------------------------- helpers

    private function dialog(DOMXPath $xpath, string $action): DOMElement
    {
        $dialogs = $xpath->query("//dialog[@data-order-dialog=\"{$action}\"]");
        $this->assertSame(1, $dialogs->length, $action);

        return $dialogs->item(0);
    }

    private function labelledBy(DOMXPath $xpath, DOMElement $dialog): DOMElement
    {
        $title = $xpath->query('.//*[@id="'.$dialog->getAttribute('aria-labelledby').'"]', $dialog)->item(0);
        $this->assertNotNull($title, 'aria-labelledby names the title.');
        $this->assertTrue($title->hasAttribute('data-order-dialog-title'));

        return $title;
    }

    private function describedBy(DOMXPath $xpath, DOMElement $dialog): DOMElement
    {
        $text = $xpath->query('.//*[@id="'.$dialog->getAttribute('aria-describedby').'"]', $dialog)->item(0);
        $this->assertNotNull($text, 'aria-describedby names the explanation.');

        return $text;
    }

    private function assertButtons(DOMXPath $xpath, DOMElement $dialog, string $cancel, string $confirm, string $style): void
    {
        /** @var DOMElement $cancelButton */
        $cancelButton = $xpath->query('.//button[@data-order-dialog-cancel]', $dialog)->item(0);
        /** @var DOMElement $confirmButton */
        $confirmButton = $xpath->query('.//button[@data-order-dialog-confirm]', $dialog)->item(0);
        $this->assertSame(['button', $cancel], [$cancelButton->getAttribute('type'), trim($cancelButton->textContent)]);
        $this->assertSame(['submit', $confirm], [$confirmButton->getAttribute('type'), trim($confirmButton->textContent)]);
        $this->assertStringContainsString($style, $confirmButton->getAttribute('class'));
        $this->assertSame(1, $xpath->query('.//form[@novalidate]', $dialog)->length, 'The script validates (translated messages).');
    }

    /** @return array<string, array<string, string>> */
    private function actionTexts(string $html): array
    {
        /** @var DOMElement $root */
        $root = $this->xpath($html)->query('//*[@data-vendor-resource-list]')->item(0);

        return json_decode($root->getAttribute('data-order-action-texts'), true, flags: JSON_THROW_ON_ERROR);
    }

    private function vendorOrder(?Vendor $vendor = null, string $status = 'pending'): VendorOrder
    {
        $order = Order::query()->create(['number' => 'HF-'.uniqid(), 'customer_id' => Customer::factory()->create()->id, 'status' => 'pending', 'payment_method' => 'cod', 'payment_status' => 'pending', 'subtotal' => 10, 'delivery_total' => 0, 'grand_total' => 10]);

        return VendorOrder::query()->create(['number' => 'VO-'.uniqid(), 'order_id' => $order->id, 'vendor_id' => ($vendor ?? $this->vendor)->id, 'status' => $status, 'subtotal' => 10, 'delivery_fee' => 0, 'commission_amount' => 0, 'total' => 10]);
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
