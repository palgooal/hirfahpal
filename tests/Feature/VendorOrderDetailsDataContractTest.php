<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Language;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\TranslationValue;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * ORD-02A (step 1): GET vendor/dashboard/orders/manage/{vendorOrder}, the vendor order details page, under the vendor
 * guard, the approval gate and the account checks. Only the owner gets it (another vendor's or an unknown order: 404).
 * The page embeds, and a JSON request to the same URL returns, one minimal whitelist (OrderController::details()):
 * no customer, address, driver, parent-order, commission, response-deadline or stock-reservation data. The existing
 * order endpoints are unchanged (VEN-BE-023 stays open for them).
 */
class VendorOrderDetailsDataContractTest extends TestCase
{
    use RefreshDatabase;

    private const ORDER_KEYS = ['id', 'number', 'status', 'created_at', 'timeline', 'rejection_reason', 'subtotal', 'delivery_fee', 'total', 'items', 'actions'];

    private const ITEM_KEYS = ['product_name', 'product_sku', 'quantity', 'unit_price', 'line_total'];

    private const TIMELINE_KEYS = ['accepted_at', 'rejected_at', 'ready_at', 'delivered_at', 'customer_receipt_confirmed_at', 'completed_at'];

    private Vendor $vendor;

    private string $hotFile = '';

    private int $sequence = 0;

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

    // ---------------------------------------------------------------- route and access

    public function test_the_route_is_the_approved_url_under_the_vendor_dashboard(): void
    {
        $order = $this->fullOrder();

        $this->assertSame(url("/vendor/dashboard/orders/manage/{$order->id}"), route('vendor.dashboard.orders.details', $order));
        $route = app('router')->getRoutes()->getByName('vendor.dashboard.orders.details');
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        foreach (['auth:vendor', 'vendor.approved', 'setLocale', 'disableTranslationAutoCreate'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware(), $middleware);
        }
    }

    public function test_the_owner_gets_the_page_and_the_json(): void
    {
        $order = $this->fullOrder();

        $this->actingAs($this->vendor, 'vendor')->get($this->url($order))->assertOk()->assertViewIs('vendor-dashboard.order-details');
        $this->actingAs($this->vendor, 'vendor')->getJson($this->url($order))->assertOk()->assertJsonPath('order.id', $order->id);
    }

    public function test_another_vendors_order_and_unknown_orders_are_not_found(): void
    {
        $theirs = $this->fullOrder(Vendor::factory()->approved()->create(), number: 'HF-THEIRS-1');

        $html = $this->actingAs($this->vendor, 'vendor')->get($this->url($theirs))->assertNotFound()->getContent();
        $this->assertStringNotContainsString('HF-THEIRS-1', $html, 'Nothing of the order is shown.');
        $json = $this->actingAs($this->vendor, 'vendor')->getJson($this->url($theirs))->assertNotFound()->getContent();
        $this->assertStringNotContainsString('HF-THEIRS-1', $json);

        $this->actingAs($this->vendor, 'vendor')->get('/vendor/dashboard/orders/manage/999999')->assertNotFound();
        $this->actingAs($this->vendor, 'vendor')->getJson('/vendor/dashboard/orders/manage/999999')->assertNotFound();
        $this->actingAs($this->vendor, 'vendor')->get('/vendor/dashboard/orders/manage/abc')->assertNotFound();
    }

    public function test_guests_unapproved_and_inactive_vendors_never_get_the_order(): void
    {
        $order = $this->fullOrder();
        $url = $this->url($order);

        $this->get($url)->assertRedirect(route('vendor.login'));
        $this->getJson($url)->assertUnauthorized();

        foreach (['pending', 'rejected'] as $state) {
            $vendor = Vendor::factory()->withProfile($state)->create();
            $mine = $this->fullOrder($vendor, number: "HF-{$state}-1");
            $this->actingAs($vendor, 'vendor')->get($this->url($mine))->assertRedirect(route('vendor.approval-status'));
            $this->actingAs($vendor, 'vendor')->getJson($this->url($mine))->assertForbidden()->assertJsonMissingPath('order');
        }

        $this->vendor->forceFill(['status' => 'blocked'])->save();
        $this->actingAs($this->vendor, 'vendor')->get($url)->assertRedirect(route('vendor.login'));
        $this->actingAs($this->vendor, 'vendor')->getJson($url)->assertUnauthorized()->assertJsonMissingPath('order');
    }

    // ---------------------------------------------------------------- the whitelist

    public function test_the_json_is_exactly_the_whitelist(): void
    {
        $order = $this->fullOrder(status: 'accepted');

        $json = $this->actingAs($this->vendor, 'vendor')->getJson($this->url($order))->assertOk()->json();

        $this->assertSame(['order'], array_keys($json));
        $this->assertSame(self::ORDER_KEYS, array_keys($json['order']));
        $this->assertSame(self::TIMELINE_KEYS, array_keys($json['order']['timeline']));
        $this->assertCount(2, $json['order']['items']);
        foreach ($json['order']['items'] as $item) {
            $this->assertSame(self::ITEM_KEYS, array_keys($item));
        }

        $this->assertSame([$order->id, 'HF-DETAILS-1', 'accepted'], [$json['order']['id'], $json['order']['number'], $json['order']['status']]);
        $this->assertSame(['25.00', '5.00', '30.00'], [$json['order']['subtotal'], $json['order']['delivery_fee'], $json['order']['total']], 'Amounts as stored.');
        $this->assertSame([
            ['product_name' => 'Clay jug', 'product_sku' => 'JUG-1', 'quantity' => 2, 'unit_price' => '7.50', 'line_total' => '15.00'],
            ['product_name' => 'Woven basket', 'product_sku' => null, 'quantity' => 1, 'unit_price' => '10.00', 'line_total' => '10.00'],
        ], $json['order']['items'], 'Item snapshot fields, in order, line totals from the server.');
        $this->assertSame(['preparing', 'ready'], $json['order']['actions']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', $json['order']['created_at']);
        $this->assertNotNull($json['order']['timeline']['accepted_at']);
        $this->assertNull($json['order']['timeline']['completed_at'], 'A timestamp not reached is null.');
    }

    public function test_no_customer_address_driver_parent_commission_deadline_or_reservation_data_is_in_the_json_or_the_page(): void
    {
        $order = $this->fullOrder(status: 'assigned');
        $json = $this->actingAs($this->vendor, 'vendor')->getJson($this->url($order))->assertOk()->getContent();
        $html = $this->actingAs($this->vendor, 'vendor')->get($this->url($order))->assertOk()->getContent();
        $embedded = $this->embedded($html);

        // Values planted in the excluded data: none may appear.
        $values = ['customer.secret@example.test', '0599111222', 'Secret Customer', 'Recipient Person', '0598333444', 'Hidden Street 9', 'Gate code 1234', '31.9038', '35.2034',
            'PARENT-ORDER-777', '999.99', 'paid', 'Leave at the door, secret note', 'Driver Person', 'driver.secret@example.test', 'PLATE-7781', '12.34', 'reserved'];
        // Keys of excluded data: none may appear in the contract.
        $keys = ['customer', 'email', 'phone', 'address', 'delivery_address', 'delivery_phone', 'latitude', 'longitude', 'order_id', 'grand_total', 'payment_status', 'payment_method', 'notes',
            'delivery_driver', 'delivery_driver_id', 'delivery_assignment', 'profile', 'vehicle_plate', 'commission_amount', 'vendor_response_due_at', 'stock_reservations', 'product', 'product_id', 'vendor_id'];

        foreach (['json' => $json, 'embedded page data' => json_encode($embedded, JSON_UNESCAPED_UNICODE)] as $where => $content) {
            foreach ($values as $value) {
                $this->assertStringNotContainsString($value, $content, "{$where}: {$value}");
            }
            foreach ($keys as $key) {
                $this->assertStringNotContainsString("\"{$key}\"", $content, "{$where}: key {$key}");
            }
        }
        foreach (['customer.secret@example.test', '0599111222', 'Secret Customer', 'Hidden Street 9', 'PLATE-7781', 'Driver Person', 'PARENT-ORDER-777', '999.99', 'secret note'] as $value) {
            $this->assertStringNotContainsString($value, $html, "page HTML: {$value}");
        }
    }

    public function test_the_page_embeds_exactly_the_json_contract(): void
    {
        $order = $this->fullOrder(status: 'rejected', rejectionReason: 'Out of clay this week');

        $json = $this->actingAs($this->vendor, 'vendor')->getJson($this->url($order))->json('order');
        $html = $this->actingAs($this->vendor, 'vendor')->get($this->url($order))->getContent();

        $this->assertSame($json, $this->embedded($html));
        $this->assertSame('Out of clay this week', $json['rejection_reason']);
        $this->assertSame([], $json['actions']);
        $this->assertStringContainsString('data-endpoint="'.route('vendor.dashboard.orders.details', $order).'"', $html);
    }

    public function test_an_order_without_items_and_with_no_lifecycle_dates(): void
    {
        $order = $this->vendorOrder($this->parentOrder(), 'pending', number: 'HF-EMPTY-1');

        $json = $this->actingAs($this->vendor, 'vendor')->getJson($this->url($order))->assertOk()->json('order');

        $this->assertSame([], $json['items']);
        $this->assertSame(array_fill_keys(self::TIMELINE_KEYS, null), $json['timeline']);
        $this->assertNull($json['rejection_reason']);
        $this->assertSame(['accept', 'reject'], $json['actions']);
    }

    public function test_a_lifecycle_column_missing_from_the_schema_is_left_out(): void
    {
        $order = $this->fullOrder(status: 'completed');
        Schema::table('vendor_orders', fn (Blueprint $table) => $table->dropColumn('customer_receipt_confirmed_at'));

        $json = $this->actingAs($this->vendor, 'vendor')->getJson($this->url($order->fresh()))->assertOk()->json('order');

        $this->assertSame(['accepted_at', 'rejected_at', 'ready_at', 'delivered_at', 'completed_at'], array_keys($json['timeline']));
        $this->assertNotNull($json['timeline']['completed_at']);
    }

    public function test_the_actions_are_the_servers_allowed_actions_for_every_status(): void
    {
        $expected = [
            'pending' => ['accept', 'reject'], 'accepted' => ['preparing', 'ready'], 'preparing' => ['preparing', 'ready'], 'ready_for_delivery' => ['ready'],
            'assigned' => [], 'out_for_delivery' => [], 'delivered' => [], 'completed' => [], 'rejected' => [], 'cancelled' => [],
        ];

        foreach ($expected as $status => $actions) {
            $order = $this->vendorOrder($this->parentOrder(), $status, number: "HF-{$status}");
            $this->assertSame($actions, $this->actingAs($this->vendor, 'vendor')->getJson($this->url($order))->json('order.actions'), $status);
            $allowed = $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.orders.show', $order))->json('allowed_actions');
            $this->assertSame(count($actions), count(array_filter($allowed)), "{$status}: the same count as the show endpoint's allowed_actions.");
        }
    }

    // ---------------------------------------------------------------- the page shell

    public function test_the_page_shell_in_arabic_and_english_creates_no_translation_rows(): void
    {
        $order = $this->fullOrder();
        $before = TranslationValue::count();

        foreach (['ar' => ['rtl', 'الطلب HF-DETAILS-1', 'الطلبات'], 'en' => ['ltr', 'Order HF-DETAILS-1', 'Orders']] as $locale => [$dir, $title, $orders]) {
            $html = $this->actingAs($this->vendor, 'vendor')->withSession(['locale' => $locale])->get($this->url($order))->assertOk()->getContent();
            $this->assertMatchesRegularExpression("/<html lang=\"{$locale}\" dir=\"{$dir}\"/", $html);
            $this->assertMatchesRegularExpression('#<h1[^>]*>\s*'.preg_quote($title, '#').'\s*</h1>#u', $html);
            $this->assertStringContainsString('href="'.route('vendor.dashboard.orders.page').'">'.$orders.'</a>', $html);
            $this->assertStringContainsString('<bdi dir="ltr">HF-DETAILS-1</bdi>', $html);
        }

        $this->assertSame($before, TranslationValue::count());
        $this->assertSame(['ar', 'en'], TranslationValue::query()->where('key', 'vendor.Order_Details_Title')->orderBy('locale')->pluck('locale')->all());
    }

    // ---------------------------------------------------------------- the existing endpoints are unchanged

    public function test_the_existing_order_endpoints_are_unchanged(): void
    {
        $order = $this->fullOrder(status: 'pending');

        $show = $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.orders.show', $order))->assertOk()->json();
        $this->assertSame(['order', 'allowed_actions'], array_keys($show));
        $this->assertSame('customer.secret@example.test', $show['order']['order']['customer']['email'], 'VEN-BE-023 stays open for the existing endpoint.');
        $this->assertSame(['orders', 'statuses'], array_keys($this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.orders.index'))->assertOk()->json()));
        $this->actingAs($this->vendor, 'vendor')->get(route('vendor.dashboard.orders.page'))->assertOk()->assertViewIs('vendor-dashboard.resources');
    }

    // ---------------------------------------------------------------- helpers

    private function url(VendorOrder $order): string
    {
        return "/vendor/dashboard/orders/manage/{$order->id}";
    }

    /** @return array<string, mixed> the data embedded in the page */
    private function embedded(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" data-order-details>(.*?)</script>#s', $html, $match));

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }

    /** The first parent order of a test carries the planted customer values; later ones get unique ones. */
    private function parentOrder(string $number = 'PARENT-ORDER-777'): Order
    {
        $sequence = ++$this->sequence;
        $customer = Customer::factory()->create($sequence === 1
            ? ['name' => 'Secret Customer', 'email' => 'customer.secret@example.test', 'phone' => '0599111222']
            : ['name' => "Customer {$sequence}"]);

        return Order::query()->create([
            'number' => $sequence === 1 ? $number : "{$number}-{$sequence}", 'customer_id' => $customer->id, 'status' => 'processing',
            'payment_method' => 'cod', 'payment_status' => 'paid', 'subtotal' => 980, 'delivery_total' => 19.99, 'grand_total' => 999.99,
            'notes' => 'Leave at the door, secret note',
            'delivery_address_source' => 'checkout', 'delivery_recipient_name' => 'Recipient Person', 'delivery_phone' => '0598333444',
            'delivery_governorate_name' => 'Hebron', 'delivery_city_name' => 'Hebron', 'delivery_address_line' => 'Hidden Street 9',
            'delivery_notes' => 'Gate code 1234', 'delivery_latitude' => '31.9038000', 'delivery_longitude' => '35.2034000',
        ]);
    }

    private function vendorOrder(Order $order, string $status, ?Vendor $vendor = null, string $number = 'HF-DETAILS-1', ?string $rejectionReason = null): VendorOrder
    {
        $reached = fn (array $from) => in_array($status, $from, true) ? now() : null;

        return VendorOrder::query()->create([
            'number' => $number, 'order_id' => $order->id, 'vendor_id' => ($vendor ?? $this->vendor)->id, 'status' => $status,
            'subtotal' => 25, 'delivery_fee' => 5, 'commission_amount' => 12.34, 'total' => 30, 'vendor_response_due_at' => now()->addDay(),
            'accepted_at' => $reached(['accepted', 'preparing', 'ready_for_delivery', 'assigned', 'out_for_delivery', 'delivered', 'completed']),
            'ready_at' => $reached(['ready_for_delivery', 'assigned', 'out_for_delivery', 'delivered', 'completed']),
            'delivered_at' => $reached(['delivered', 'completed']),
            'customer_receipt_confirmed_at' => $reached(['completed']),
            'completed_at' => $reached(['completed']),
            'rejected_at' => $status === 'rejected' ? now() : null,
            'rejection_reason' => $rejectionReason,
        ]);
    }

    /** A vendor order with everything the contract must leave out: customer, address, driver, parent order, commission, reservations. */
    private function fullOrder(?Vendor $vendor = null, string $status = 'pending', string $number = 'HF-DETAILS-1', ?string $rejectionReason = null): VendorOrder
    {
        $vendor ??= $this->vendor;
        $vendorOrder = $this->vendorOrder($this->parentOrder(), $status, $vendor, $number, $rejectionReason);
        $jug = Product::create(['vendor_id' => $vendor->id, 'name' => 'Clay jug', 'slug' => 'clay-jug-'.$vendorOrder->id, 'sku' => 'JUG-P'.$vendorOrder->id, 'price' => 7.5, 'stock_quantity' => 9, 'status' => 'active']);
        $first = $vendorOrder->order->items()->create(['vendor_order_id' => $vendorOrder->id, 'product_id' => $jug->id, 'product_name' => 'Clay jug', 'product_sku' => 'JUG-1', 'quantity' => 2, 'unit_price' => 7.5, 'line_total' => 15]);
        $vendorOrder->order->items()->create(['vendor_order_id' => $vendorOrder->id, 'product_id' => null, 'product_name' => 'Woven basket', 'product_sku' => null, 'quantity' => 1, 'unit_price' => 10, 'line_total' => 10]);
        StockReservation::query()->create(['product_id' => $jug->id, 'order_id' => $vendorOrder->order_id, 'vendor_order_id' => $vendorOrder->id, 'order_item_id' => $first->id, 'quantity' => 2, 'status' => 'reserved', 'reserved_at' => now()]);

        $driver = DeliveryDriver::factory()->create(['name' => 'Driver Person', 'email' => 'driver.secret'.$vendorOrder->id.'@example.test']);
        $driver->profile()->create(['vehicle_type' => 'van', 'vehicle_plate' => 'PLATE-7781', 'approval_status' => 'approved', 'is_available' => true]);
        DB::table('vendor_orders')->where('id', $vendorOrder->id)->update(['delivery_driver_id' => $driver->id]);
        DeliveryAssignment::query()->create(['vendor_order_id' => $vendorOrder->id, 'delivery_driver_id' => $driver->id, 'status' => 'assigned', 'assigned_at' => now()]);

        return $vendorOrder->fresh();
    }
}
