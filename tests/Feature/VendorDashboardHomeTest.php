<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Language;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Vendor dashboard home (VUI-02B). The page is server-rendered with labels and
 * loading placeholders; resources/js/vendor-dashboard-home.js fills in values from
 * the existing Palgoals overview endpoint (vendor.dashboard.overview, unchanged).
 * These tests cover the page markup, the approved fields of the consumed contract,
 * and that the frontend consumes nothing beyond them.
 */
class VendorDashboardHomeTest extends TestCase
{
    use RefreshDatabase;

    private const STATUSES = [
        'pending', 'accepted', 'preparing', 'ready_for_delivery', 'assigned',
        'out_for_delivery', 'delivered', 'completed', 'rejected', 'cancelled',
    ];

    private string $hotFile;

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
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    // ---- Page markup ----

    public function test_home_renders_the_overview_shell_in_its_loading_state(): void
    {
        $html = $this->actingAs($this->vendor(), 'vendor')
            ->get(route('vendor.dashboard'))
            ->assertOk()
            ->assertViewIs('vendor-dashboard.home')
            ->assertSee('data-overview-url="'.route('vendor.dashboard.overview').'"', false)
            ->assertSee('data-state="loading"', false)
            ->assertSee('resources/js/vendor-dashboard-home.js', false)
            ->getContent();

        foreach (['orders_pending', 'orders_in_progress', 'products_active', 'products_low_stock'] as $kpi) {
            $this->assertStringContainsString('data-kpi="'.$kpi.'"', $html);
        }
        $this->assertStringContainsString('data-kpi-meta="products_total"', $html);
        $this->assertSame(4, substr_count($html, 'data-kpi-card='));

        // Placeholders only: no value is rendered before the data arrives.
        $this->assertSame(4, preg_match_all('/<span class="vendor-skeleton vendor-skeleton-value" data-kpi="[a-z_]+"><\/span>/', $html));
        $this->assertMatchesRegularExpression('/<div class="card vendor-home-error" data-home-error hidden>/', $html);
        $this->assertMatchesRegularExpression('/<table class="vendor-recent-table" data-recent-table hidden>/', $html);
        $this->assertMatchesRegularExpression('/<p class="vendor-empty" data-recent-empty hidden>/', $html);
    }

    public function test_arabic_labels(): void
    {
        $this->actingAs($this->vendor(), 'vendor')
            ->get(route('vendor.dashboard'))
            ->assertSee('<html lang="ar" dir="rtl"', false)
            ->assertSee('ملخص المتجر')
            ->assertSee('بانتظار ردّك')
            ->assertSee('قيد التنفيذ')
            ->assertSee('المنتجات المفعّلة')
            ->assertSee('مخزون متاح منخفض')
            ->assertSee('أحدث الطلبات')
            ->assertSeeInOrder(['رقم الطلب', 'التاريخ', 'الحالة', 'العناصر'])
            ->assertSee('لا توجد طلبات بعد')
            ->assertSee('تعذّر تحميل بيانات لوحة التحكم.')
            ->assertSee('إعادة المحاولة');
    }

    public function test_english_labels(): void
    {
        $this->actingAs($this->vendor(), 'vendor');
        $this->get(route('vendor.dashboard').'?change-locale=en');

        $this->get(route('vendor.dashboard'))
            ->assertSee('<html lang="en" dir="ltr"', false)
            ->assertSee('Store overview')
            ->assertSee('Awaiting your response')
            ->assertSee('In progress')
            ->assertSee('Active products')
            ->assertSee('Low available stock')
            ->assertSee('Recent orders')
            ->assertSeeInOrder(['Order number', 'Date', 'Status', 'Items'])
            ->assertSee('No orders yet')
            ->assertSee('Dashboard data could not be loaded.')
            ->assertSee('Retry');
    }

    public function test_interface_strings_for_the_script_cover_every_status(): void
    {
        $i18n = $this->i18n($this->actingAs($this->vendor(), 'vendor')->get(route('vendor.dashboard')));

        $this->assertSame(self::STATUSES, array_keys($i18n['statuses']));
        $this->assertSame('قيد الانتظار', $i18n['statuses']['pending']);
        $this->assertSame('مُسند لمندوب', $i18n['statuses']['assigned']);
        $this->assertSame('من أصل :total', $i18n['ofTotal']);
        $this->assertSame('لم تُضف منتجات بعد', $i18n['noProducts']);
        $this->assertSame(['number', 'date', 'status', 'items'], array_keys($i18n['columns']));

        $this->get(route('vendor.dashboard').'?change-locale=en');
        $english = $this->i18n($this->get(route('vendor.dashboard')));
        $this->assertSame('Assigned to a driver', $english['statuses']['assigned']);
        $this->assertSame('of :total', $english['ofTotal']);
        $this->assertSame('No products added yet', $english['noProducts']);
    }

    public function test_no_deferred_kpis_or_lists(): void
    {
        $this->actingAs($this->vendor(), 'vendor');

        foreach (['ar', 'en'] as $locale) {
            $this->get(route('vendor.dashboard').'?change-locale='.$locale);
            $this->get(route('vendor.dashboard'))
                ->assertDontSee('sales_total')
                ->assertDontSee('commission_pending')
                ->assertDontSee('orders_completed')
                ->assertDontSee('average_rating')
                ->assertDontSee('low_stock_products')
                ->assertDontSee('تقييم المتجر')
                ->assertDontSee('Store rating')
                ->assertDontSee('المبيعات')
                ->assertDontSee('Sales')
                ->assertDontSee('العمولة')
                ->assertDontSee('Commission')
                ->assertDontSee('Completed orders')
                ->assertDontSee('chart');
        }
    }

    // ---- Consumed contract (real endpoint, approved fields only) ----

    public function test_overview_endpoint_provides_the_approved_kpis(): void
    {
        $vendor = $this->vendor();
        $this->product($vendor, 'active', stock: 20);
        $this->product($vendor, 'active', stock: 1, threshold: 2);  // low
        $this->product($vendor, 'draft', stock: 0);                 // low, counted across statuses (backend definition)
        $this->vendorOrder($vendor, 'pending');
        $this->vendorOrder($vendor, 'pending');
        $this->vendorOrder($vendor, 'preparing');
        $this->vendorOrder($vendor, 'rejected');

        $stats = $this->actingAs($vendor, 'vendor')
            ->getJson(route('vendor.dashboard.overview'))
            ->assertOk()
            ->json('stats');

        $this->assertSame(2, $stats['orders_pending']);
        $this->assertSame(1, $stats['orders_in_progress']);
        $this->assertSame(2, $stats['products_active']);
        $this->assertSame(3, $stats['products_total']);
        $this->assertSame(2, $stats['products_low_stock']);
    }

    public function test_overview_endpoint_provides_the_recent_order_fields_the_ui_uses(): void
    {
        $vendor = $this->vendor();
        $vendorOrder = $this->vendorOrder($vendor, 'accepted', items: 3);

        $order = $this->actingAs($vendor, 'vendor')
            ->getJson(route('vendor.dashboard.overview'))
            ->json('recent_orders.0');

        $this->assertSame($vendorOrder->number, $order['number']);
        $this->assertSame('accepted', $order['status']);
        $this->assertIsString($order['created_at']);
        $this->assertCount(3, $order['items']);
    }

    public function test_zero_states_come_back_as_numbers_and_an_empty_list(): void
    {
        $response = $this->actingAs($this->vendor(), 'vendor')
            ->getJson(route('vendor.dashboard.overview'))
            ->assertOk();

        foreach (['orders_pending', 'orders_in_progress', 'products_active', 'products_total', 'products_low_stock'] as $kpi) {
            $this->assertSame(0, $response->json('stats.'.$kpi), $kpi);
        }
        $this->assertSame([], $response->json('recent_orders'));
    }

    // ---- Data minimization (frontend consumption only; VEN-BE-023 stays a backend finding) ----

    public function test_the_page_never_embeds_overview_data(): void
    {
        $vendor = Vendor::factory()->approved()->create(['email' => 'vendor.private@example.com', 'phone' => '0599000111']);
        $vendor->profile->update(['commission_rate' => 12.5, 'address_line' => 'Private vendor address']);
        $vendorOrder = $this->vendorOrder($vendor, 'rejected', withSensitiveData: true);

        $this->actingAs($vendor, 'vendor')
            ->get(route('vendor.dashboard'))
            ->assertOk()
            ->assertDontSee($vendorOrder->number)
            ->assertDontSee('buyer.private@example.com')
            ->assertDontSee('0599888777')
            ->assertDontSee('Leave it with the neighbour')
            ->assertDontSee('Private rejection reason')
            ->assertDontSee('cod')
            ->assertDontSee('payment_status')
            ->assertDontSee('777.77')
            ->assertDontSee('123.45')
            ->assertDontSee('7.77')
            ->assertDontSee('vendor.private@example.com')
            ->assertDontSee('0599000111')
            ->assertDontSee('12.50')
            ->assertDontSee('Private vendor address')
            ->assertDontSee('commission_rate')
            ->assertDontSee('approved_by');
    }

    public function test_the_script_reads_only_the_approved_fields(): void
    {
        $script = file_get_contents(resource_path('js/vendor-dashboard-home.js'));

        foreach (['orders_pending', 'orders_in_progress', 'products_active', 'products_total', 'products_low_stock', 'recent_orders', 'number', 'created_at', 'status', 'items'] as $field) {
            $this->assertStringContainsString($field, $script, $field);
        }

        foreach ([
            'sales_total', 'commission_pending', 'orders_completed', 'average_rating', 'low_stock_products',
            'customer', '.vendor', 'profile', 'payment', 'notes', 'rejection_reason', 'commission_amount',
            'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'localStorage', 'sessionStorage', 'console.',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $script, $forbidden);
        }

        // No monetary total of an order: neither `.total` nor a "total"/'total' key.
        $this->assertDoesNotMatchRegularExpression('/\.total\b|[\'"]total[\'"]/', $script);
    }

    // ---- Gating ----

    public function test_pending_and_rejected_vendors_stay_gated(): void
    {
        foreach (['pending' => null, 'rejected' => 'Reason.'] as $state => $reason) {
            $vendor = Vendor::factory()->withProfile($state, $reason)->create();

            $this->actingAs($vendor, 'vendor')->get(route('vendor.dashboard'))->assertRedirect(route('vendor.approval-status'));
            $this->getJson(route('vendor.dashboard.overview'))->assertForbidden();
        }
    }

    private function vendor(): Vendor
    {
        return Vendor::factory()->approved()->create();
    }

    private function product(Vendor $vendor, string $status, int $stock, int $threshold = 1): Product
    {
        static $sequence = 0;
        $sequence++;

        return Product::query()->create([
            'vendor_id' => $vendor->id,
            'name' => "Product $sequence",
            'slug' => "product-$sequence-".uniqid(),
            'price' => 10,
            'stock_quantity' => $stock,
            'low_stock_threshold' => $threshold,
            'status' => $status,
        ]);
    }

    private function vendorOrder(Vendor $vendor, string $status, int $items = 1, bool $withSensitiveData = false): VendorOrder
    {
        static $sequence = 0;
        $sequence++;

        $customer = Customer::factory()->create($withSensitiveData
            ? ['email' => 'buyer.private@example.com', 'phone' => '0599888777']
            : []);

        $order = Order::query()->create([
            'number' => "HF-TEST-$sequence",
            'customer_id' => $customer->id,
            'status' => 'pending',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'subtotal' => 777.77,
            'delivery_total' => 0,
            'discount_total' => 0,
            'grand_total' => 777.77,
            'notes' => $withSensitiveData ? 'Leave it with the neighbour' : null,
        ]);

        $vendorOrder = VendorOrder::query()->create([
            'number' => "HF-TEST-$sequence-V{$vendor->id}",
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => $status,
            'subtotal' => 123.45,
            'delivery_fee' => 0,
            'commission_amount' => 7.77,
            'total' => 123.45,
            'rejection_reason' => $withSensitiveData ? 'Private rejection reason' : null,
        ]);

        for ($i = 1; $i <= $items; $i++) {
            OrderItem::query()->create([
                'order_id' => $order->id,
                'vendor_order_id' => $vendorOrder->id,
                'product_name' => "Item $i",
                'quantity' => 1,
                'unit_price' => 10,
                'line_total' => 10,
            ]);
        }

        return $vendorOrder;
    }

    /**
     * @return array<string, mixed>
     */
    private function i18n(TestResponse $response): array
    {
        preg_match('/<script type="application\/json" data-home-i18n>(.*?)<\/script>/s', $response->getContent(), $match);

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }
}
