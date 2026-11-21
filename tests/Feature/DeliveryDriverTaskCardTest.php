<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Vendor;
use App\Models\VendorOrder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E2-MVP batch 02 (FR-DEL-006): the task card on the driver's browser dashboard. It shows the recipient, their phone and
 * the checkout delivery address, the pickup store with its address and phone, the products and quantities and the payment
 * method, with call and Google Maps directions links; never an email, a price, a total, a commission or payment
 * internals; and the contact details only while the task needs them.
 */
class DeliveryDriverTaskCardTest extends TestCase
{
    use RefreshDatabase;

    /** Values that must never reach the driver's page (E2-MVP batch 05: the vendor order total 81.75 is the cash to
     * collect on an active cash-on-delivery task, and is checked separately). */
    private const SECRETS = [
        'customer-secret-', 'vendor-secret-', '@example.com', 'SKU-HIDDEN-77', '37.25', '74.50', '7.25', '11.11',
        'GATEWAY-REF-555', 'gateway-payload-secret', 'Vendor rejection secret',
    ];

    private Governorate $governorate;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->governorate = Governorate::create(['name' => 'Gaza Governorate', 'slug' => 'gaza-governorate', 'status' => 'active']);
        $this->city = City::create(['governorate_id' => $this->governorate->id, 'name' => 'Gaza', 'slug' => 'gaza', 'status' => 'active']);
    }

    public function test_an_active_task_shows_the_recipient_the_store_the_products_and_the_payment_method(): void
    {
        $driver = $this->approvedDriver();
        $assignment = $this->task($driver);

        $html = $this->page($driver);
        $card = $this->card($html, $assignment);

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('width=device-width', $html);

        // The recipient and the delivery address from the checkout snapshot.
        $customer = $this->section($card, 'customer');
        $this->assertStringContainsString('Recipient Snapshot', $customer->textContent);
        $this->assertStringContainsString('Main Street 4، Gaza، Gaza Governorate', $customer->textContent);
        $this->assertStringContainsString('Ring the bell', $customer->textContent);
        $this->assertStringContainsString('+970 599-111-222', $customer->textContent);
        $this->assertSame('tel:+970599111222', $this->link($card, 'data-call', 'customer'));
        $this->assertSame('https://www.google.com/maps/dir/?api=1&destination=31.5017%2C34.4668', $this->link($card, 'data-directions', 'customer'));

        // The pickup store, its address and its phone.
        $vendor = $this->section($card, 'vendor');
        $this->assertStringContainsString('Olive Workshop', $vendor->textContent);
        $this->assertStringContainsString('Market Road 9، Gaza، Gaza Governorate', $vendor->textContent);
        $vendorPhone = $assignment->vendorOrder->vendor->phone;
        $this->assertStringContainsString($vendorPhone, $vendor->textContent);
        $this->assertSame('tel:'.$vendorPhone, $this->link($card, 'data-call', 'vendor'));
        $this->assertSame('https://www.google.com/maps/dir/?api=1&destination='.rawurlencode('Olive Workshop، Market Road 9، Gaza، Gaza Governorate'), $this->link($card, 'data-directions', 'vendor'));

        // The products and quantities, and the payment method without any amount.
        $items = trim(preg_replace('/\s+/u', ' ', $this->xpath($html)->query('.//ul[@data-card-items]', $card)->item(0)->textContent));
        $this->assertSame('Olive Bowl × 2 Clay Cup × 1', $items);
        $this->assertStringContainsString('الدفع عند الاستلام', $this->xpath($html)->query('.//*[@data-payment-method]', $card)->item(0)->textContent);
    }

    public function test_the_page_never_shows_emails_prices_totals_commissions_or_payment_internals(): void
    {
        $driver = $this->approvedDriver();
        $this->task($driver);
        $this->task($driver, 'delivered', 'delivered');

        $html = $this->page($driver);

        foreach (self::SECRETS as $secret) {
            $this->assertStringNotContainsString($secret, $html, $secret);
        }
        // E2-MVP batch 05: the vendor order total appears once, as the cash to collect on the active task only.
        $this->assertSame(1, substr_count($html, '81.75'));
        $this->assertMatchesRegularExpression('/data-cod-amount>[^<]*<span[^>]*>81\.75</', $html);
    }

    public function test_directions_fall_back_to_the_written_address_and_are_left_out_without_one(): void
    {
        $driver = $this->approvedDriver();
        // Coordinates out of range: the written address is used.
        $byText = $this->task($driver, order: ['delivery_latitude' => '123.0', 'delivery_longitude' => '34.4668']);
        // No coordinates and no address line; the store has no address line and a number too short to dial.
        $none = $this->task($driver, order: ['delivery_latitude' => null, 'delivery_longitude' => null, 'delivery_address_line' => null, 'delivery_phone' => null],
            vendor: ['address_line' => null], vendorPhone: '12');

        $html = $this->page($driver);

        $this->assertSame('https://www.google.com/maps/dir/?api=1&destination='.rawurlencode('Main Street 4، Gaza، Gaza Governorate'), $this->link($this->card($html, $byText), 'data-directions', 'customer'));

        $card = $this->card($html, $none);
        $xpath = $this->xpath($html);
        $this->assertSame(0, $xpath->query('.//a[@data-directions]', $card)->length);
        $this->assertSame(0, $xpath->query('.//a[@data-call]', $card)->length);
        // Without an address line the city and governorate are still shown, but too vague for directions.
        $this->assertStringContainsString('Gaza، Gaza Governorate', $this->section($card, 'vendor')->textContent);
        $this->assertStringNotContainsString('Market Road 9', $card->textContent);
    }

    public function test_contact_details_appear_only_while_the_task_needs_them(): void
    {
        $driver = $this->approvedDriver();
        $statuses = [
            // assignment status, vendor order status, customer shown, store shown
            ['assigned', 'assigned', true, true],
            ['accepted', 'assigned', true, true],
            ['picked_up', 'out_for_delivery', true, true],
            ['delivered', 'delivered', false, false],
            ['cancelled', 'ready_for_delivery', false, false],
            // A failure before pickup: nothing is in the driver's hands.
            ['failed', 'ready_for_delivery', false, false],
            // A failure after pickup: the package is returned to the store, so the store stays reachable.
            ['failed', 'out_for_delivery', false, true],
        ];
        $tasks = array_map(fn ($status) => [$this->task($driver, $status[0], $status[1]), ...$status], $statuses);

        $html = $this->page($driver);
        $xpath = $this->xpath($html);

        foreach ($tasks as [$assignment, $status, $vendorOrderStatus, $customerShown, $vendorShown]) {
            $card = $this->card($html, $assignment);
            $case = "{$status}/{$vendorOrderStatus}";
            $this->assertSame($customerShown ? 1 : 0, $xpath->query('.//*[@data-card-customer]', $card)->length, $case);
            $this->assertSame($vendorShown ? 1 : 0, $xpath->query('.//*[@data-card-vendor]', $card)->length, $case);
            if (! $customerShown) {
                $this->assertStringNotContainsString('Main Street 4', $card->textContent, $case);
                $this->assertStringNotContainsString('599-111-222', $card->textContent, $case);
            }
            if (! $vendorShown) {
                $this->assertStringNotContainsString($assignment->vendorOrder->vendor->phone, $card->textContent, $case);
                $this->assertStringNotContainsString('Market Road 9', $card->textContent, $case);
            }
            // The number, the store name and the products stay.
            $this->assertStringContainsString('Olive Bowl', $card->textContent, $case);
        }
    }

    public function test_the_snapshot_is_shown_even_after_the_saved_address_changes_and_legacy_orders_use_the_saved_one(): void
    {
        $driver = $this->approvedDriver();
        $snapshot = $this->task($driver);
        $snapshot->vendorOrder->order->address->update(['address_line' => 'Changed Later 1', 'phone' => '0590000000']);

        $legacy = $this->task($driver, order: ['delivery_address_source' => null]);
        $legacy->vendorOrder->order->address->update(['recipient_name' => 'Saved Address Name', 'address_line' => 'Saved Street 2']);

        $html = $this->page($driver);

        $this->assertStringContainsString('Main Street 4', $this->card($html, $snapshot)->textContent);
        $this->assertStringNotContainsString('Changed Later 1', $this->card($html, $snapshot)->textContent);
        $this->assertStringContainsString('Saved Address Name', $this->card($html, $legacy)->textContent);
        $this->assertStringContainsString('Saved Street 2', $this->card($html, $legacy)->textContent);
    }

    public function test_a_driver_sees_only_their_own_tasks_and_an_unapproved_driver_none(): void
    {
        $driver = $this->approvedDriver();
        $other = $this->approvedDriver();
        $own = $this->task($driver);
        $theirs = $this->task($other, order: ['delivery_recipient_name' => 'Other Recipient']);

        $html = $this->page($driver);
        $this->assertSame(1, $this->xpath($html)->query('//*[@data-assignment-card="'.$own->id.'"]')->length);
        $this->assertSame(0, $this->xpath($html)->query('//*[@data-assignment-card="'.$theirs->id.'"]')->length);
        $this->assertStringNotContainsString('Other Recipient', $html);

        $pending = DeliveryDriver::factory()->create();
        $pending->profile()->create(['approval_status' => 'pending']);
        $this->task($pending);
        $this->actingAs($pending, 'delivery_driver')->get(route('delivery-driver.dashboard'))->assertRedirect(route('delivery-driver.approval-status'));
    }

    public function test_the_json_api_gives_the_delivery_address_only_for_an_active_task(): void
    {
        $driver = $this->approvedDriver();
        $active = $this->task($driver);
        $delivered = $this->task($driver, 'delivered', 'delivered');

        $show = fn (DeliveryAssignment $assignment) => $this->actingAs($driver, 'delivery_driver')
            ->getJson(route('delivery-driver.dashboard.assignments.show', $assignment))->assertOk()->json('vendor_order.order');

        $this->assertSame('Recipient Snapshot', $show($active)['delivery_address']['recipient_name']);
        $finished = $show($delivered);
        $this->assertArrayNotHasKey('delivery_address', $finished);
        $this->assertArrayNotHasKey('address', $finished);
        $this->assertSame('cod', $finished['payment_method']);

        $index = collect($this->actingAs($driver, 'delivery_driver')->getJson(route('delivery-driver.dashboard.assignments.index'))->assertOk()->json('data'))->keyBy('id');
        $this->assertArrayHasKey('delivery_address', $index[$active->id]['vendor_order']['order']);
        $this->assertArrayNotHasKey('delivery_address', $index[$delivered->id]['vendor_order']['order']);
        $this->assertStringNotContainsString('Main Street 4', json_encode($index[$delivered->id]));
    }

    // ---------------------------------------------------------------- helpers

    private function page(DeliveryDriver $driver): string
    {
        return $this->actingAs($driver, 'delivery_driver')->get(route('delivery-driver.dashboard'))->assertOk()->getContent();
    }

    private function card(string $html, DeliveryAssignment $assignment): \DOMElement
    {
        $card = $this->xpath($html)->query('//*[@data-assignment-card="'.$assignment->id.'"]')->item(0);
        $this->assertNotNull($card, "Card {$assignment->id}");

        return $card;
    }

    private function section(\DOMElement $card, string $who): \DOMElement
    {
        $section = (new DOMXPath($card->ownerDocument))->query('.//*[@data-card-'.$who.']', $card)->item(0);
        $this->assertNotNull($section, $who);

        return $section;
    }

    private function link(\DOMElement $card, string $attribute, string $who): ?string
    {
        return (new DOMXPath($card->ownerDocument))->query('.//a[@'.$attribute.'="'.$who.'"]', $card)->item(0)?->getAttribute('href');
    }

    private function approvedDriver(): DeliveryDriver
    {
        $driver = DeliveryDriver::factory()->create(['status' => 'active']);
        $driver->profile()->create(['approval_status' => 'approved', 'approved_at' => now(), 'is_available' => true]);

        return $driver;
    }

    private function task(DeliveryDriver $driver, string $status = 'assigned', string $vendorOrderStatus = 'assigned', array $order = [], array $vendor = [], ?string $vendorPhone = null): DeliveryAssignment
    {
        $customer = Customer::factory()->create(['email' => uniqid('customer-secret-').'@example.com']);
        $address = CustomerAddress::create([
            'customer_id' => $customer->id, 'governorate_id' => $this->governorate->id, 'city_id' => $this->city->id,
            'recipient_name' => 'Recipient Snapshot', 'phone' => '+970 599-111-222', 'address_line' => 'Main Street 4', 'notes' => 'Ring the bell',
            'latitude' => '31.5017', 'longitude' => '34.4668',
        ]);
        $orderRow = Order::query()->create(array_merge([
            'number' => 'HF-'.uniqid(), 'customer_id' => $customer->id, 'customer_address_id' => $address->id, 'status' => 'processing',
            'payment_method' => 'cod', 'payment_status' => 'pending', 'subtotal' => 74.50, 'delivery_total' => 7.25, 'grand_total' => 81.75,
            'delivery_address_source' => 'checkout', 'delivery_recipient_name' => 'Recipient Snapshot', 'delivery_phone' => '+970 599-111-222',
            'delivery_governorate_id' => $this->governorate->id, 'delivery_governorate_name' => 'Gaza Governorate',
            'delivery_city_id' => $this->city->id, 'delivery_city_name' => 'Gaza', 'delivery_address_line' => 'Main Street 4',
            'delivery_notes' => 'Ring the bell', 'delivery_latitude' => '31.5017', 'delivery_longitude' => '34.4668',
        ], $order));
        Payment::query()->create(['order_id' => $orderRow->id, 'method' => 'cod', 'status' => 'pending', 'amount' => 81.75,
            'provider' => 'gateway', 'provider_reference' => 'GATEWAY-REF-555', 'payload' => ['secret' => 'gateway-payload-secret']]);

        // vendors.phone and the emails are unique: one value per task.
        $vendorRow = Vendor::factory()->create(['name' => 'Olive', 'phone' => $vendorPhone ?? '059'.random_int(1000000, 9999999), 'email' => uniqid('vendor-secret-').'@example.com']);
        $vendorRow->profile()->create(array_merge([
            'store_name' => 'Olive Workshop', 'slug' => 'olive-workshop-'.uniqid(), 'approval_status' => 'approved', 'approved_at' => now(),
            'address_line' => 'Market Road 9', 'governorate_id' => $this->governorate->id, 'city_id' => $this->city->id,
            'commission_rate' => 11.11, 'rejection_reason' => 'Vendor rejection secret',
        ], $vendor));

        $vendorOrder = VendorOrder::query()->create([
            'number' => 'VO-'.uniqid(), 'order_id' => $orderRow->id, 'vendor_id' => $vendorRow->id, 'delivery_driver_id' => $driver->id,
            'status' => $vendorOrderStatus, 'subtotal' => 74.50, 'delivery_fee' => 7.25, 'commission_amount' => 11.11, 'total' => 81.75,
        ]);
        foreach ([['Olive Bowl', 2], ['Clay Cup', 1]] as [$name, $quantity]) {
            $orderRow->items()->create(['vendor_order_id' => $vendorOrder->id, 'product_name' => $name, 'product_sku' => 'SKU-HIDDEN-77',
                'quantity' => $quantity, 'unit_price' => 37.25, 'line_total' => 37.25 * $quantity]);
        }

        return DeliveryAssignment::query()->create([
            'vendor_order_id' => $vendorOrder->id, 'delivery_driver_id' => $driver->id, 'status' => $status, 'assigned_at' => now(),
            'failed_at' => $status === 'failed' ? now() : null, 'failure_reason' => $status === 'failed' ? 'Customer unavailable.' : null,
        ]);
    }

    /** One parsed document per page, so the nodes of a page can be queried together. */
    private function xpath(string $html): DOMXPath
    {
        $key = md5($html);

        if (! isset($this->parsed[$key])) {
            $document = new DOMDocument;
            libxml_use_internal_errors(true);
            $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
            libxml_clear_errors();
            $this->parsed[$key] = new DOMXPath($document);
        }

        return $this->parsed[$key];
    }

    /** @var array<string, DOMXPath> */
    private array $parsed = [];
}
