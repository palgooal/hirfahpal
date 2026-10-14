<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Language;
use App\Models\Product;
use App\Models\RoleUser;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminCategoryTranslationSeeder;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Database\Seeders\AdminProductTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AD-UI-PROD-M3: the admin products list. Search (grouped, literal), the six filters alone and combined, the default
 * archive view (OPEN-PROD-04D), low stock as available stock at or below the threshold (OPEN-PROD-16), stock figures,
 * badges, permissions, pagination, a constant number of queries, language and direction, and no translation rows.
 */
class AdminProductsIndexTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->vendor = Vendor::factory()->approved()->create(['name' => 'Dar Karma']);
        $this->category = Category::create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
    }

    // ---------------------------------------------------------------- shell

    public function test_the_list_renders_in_the_admin_shell_with_one_title_and_linked_filter_labels(): void
    {
        $this->seedTranslations();
        $this->product('Clay jug');
        $html = $this->page($this->admin(super: true));

        $this->assertStringContainsString('id="admin-products"', $html);
        $this->assertSame(1, substr_count($html, '<h1'), 'One page title, in the header.');
        $this->assertStringNotContainsString('class="breadcrumb-item"', $html);
        $this->assertStringContainsString('أشرف على المنتجات التي يعرضها البائعون', $html);

        foreach (['products-search', 'products-vendor', 'products-category', 'products-status', 'products-suspension', 'products-archive', 'products-stock'] as $id) {
            $this->assertStringContainsString('for="'.$id.'"', $html, $id);
            $this->assertStringContainsString('id="'.$id.'"', $html, $id);
        }
        $this->assertStringContainsString('role="search"', $html);
    }

    public function test_the_table_has_column_headers_and_card_labels(): void
    {
        $this->product('Clay jug');
        $list = $this->list($this->page($this->admin(super: true), 'en'));

        $this->assertSame(1, substr_count($list, '<table class="admin-products-table">'));
        $this->assertSame(7, substr_count($list, '<th scope="col"'), 'Product, vendor, category, price, stock, status, actions.');
        $this->assertStringContainsString('<caption class="visually-hidden">Products list</caption>', $list);
        $this->assertSame(5, substr_count($list, 'class="admin-products-label"'), 'Each data cell but the product names itself in the card layout.');

        $css = file_get_contents(resource_path('css/admin-dashboard.css'));
        $this->assertMatchesRegularExpression('/@media \(max-width: 1023\.98px\) \{\s*html\.admin-shell \.admin-products \.admin-products-table thead/', $css, 'Cards below 1024px (5.13).');
        $this->assertStringContainsString("html.admin-shell .admin-products .admin-products-label {\n  display: none;", $css, 'Labels only in the card layout.');
    }

    public function test_no_delete_suspend_archive_or_review_control_is_offered(): void
    {
        $this->product('Clay jug');
        $list = $this->list($this->page($this->admin(super: true), 'en'));

        $this->assertStringNotContainsString('name="_method"', $list);
        $this->assertDoesNotMatchRegularExpression('/method="POST"/i', $list);
        $this->assertStringNotContainsString('<img', $list, 'No thumbnails in M3.');
        $this->assertStringNotContainsStringIgnoringCase('review', $list);
    }

    // ---------------------------------------------------------------- search

    public function test_search_with_a_status_never_bypasses_the_status_filter(): void
    {
        $draft = $this->product('Alpha jug', status: 'draft');
        $active = $this->product('Beta bowl', sku: 'ALPHA-2', status: 'active');

        // PRD-04 / S1: "Alpha" matches the draft by name and the active one by SKU; only the active one stays.
        $this->assertSame([$active->id], $this->ids(['search' => 'Alpha', 'status' => 'active']));
        $this->assertEqualsCanonicalizing([$draft->id, $active->id], $this->ids(['search' => 'alpha']));
        $this->assertSame([$active->id], $this->ids(['search' => 'ALPHA-2']));
    }

    public function test_search_is_literal(): void
    {
        $percent = $this->product('100% clay');
        $underscore = $this->product('Jug_large');
        $this->product('Plain jug');

        $this->assertSame([$percent->id], $this->ids(['search' => '%']));
        $this->assertSame([$underscore->id], $this->ids(['search' => '_']));
        $this->assertSame([], $this->ids(['search' => '!']));
    }

    // ---------------------------------------------------------------- filters

    public function test_vendor_category_and_status_filters(): void
    {
        $otherVendor = Vendor::factory()->approved()->create(['name' => 'Bait Nour']);
        $otherCategory = Category::create(['name' => 'Baskets', 'slug' => 'baskets', 'is_active' => true]);
        $mine = $this->product('Mine');
        $theirs = $this->product('Theirs', vendor: $otherVendor);
        $basket = $this->product('Basket', category: $otherCategory, status: 'inactive');

        $this->assertEqualsCanonicalizing([$mine->id, $basket->id], $this->ids(['vendor_id' => $this->vendor->id]));
        $this->assertSame([$theirs->id], $this->ids(['vendor_id' => $otherVendor->id]));
        $this->assertSame([$basket->id], $this->ids(['category_id' => $otherCategory->id]));
        $this->assertSame([$basket->id], $this->ids(['status' => 'inactive']));
    }

    public function test_suspension_filter(): void
    {
        $normal = $this->product('Normal');
        $suspended = $this->state($this->product('Suspended'), ['suspended_at']);

        $this->assertSame([$suspended->id], $this->ids(['suspension' => 'suspended']));
        $this->assertSame([$normal->id], $this->ids(['suspension' => 'not_suspended']));
        $this->assertEqualsCanonicalizing([$normal->id, $suspended->id], $this->ids());
    }

    public function test_archived_products_are_hidden_by_default_and_shown_on_request(): void
    {
        $normal = $this->product('Normal');
        $archived = $this->state($this->product('Archived'), ['archived_at']);

        $this->assertSame([$normal->id], $this->ids(), 'Not archived by default (OPEN-PROD-04D).');
        $this->assertSame([$archived->id], $this->ids(['archive' => 'archived']));
        $this->assertEqualsCanonicalizing([$normal->id, $archived->id], $this->ids(['archive' => 'all']));
    }

    public function test_low_stock_is_available_stock_at_or_below_the_threshold(): void
    {
        // OPEN-PROD-16: stock 10, threshold 2.
        $reservedDown = $this->product('Reserved down', stock: 10, threshold: 2);
        $this->reserve($reservedDown, 8); // available 2 = threshold: low
        $released = $this->product('Released only', stock: 10, threshold: 2);
        $this->reserve($released, 9, 'released'); // released reservations hold nothing: available 10
        $plenty = $this->product('Plenty', stock: 10, threshold: 2); // available 10
        $atThreshold = $this->product('At threshold', stock: 2, threshold: 2); // available 2: low
        $empty = $this->product('Empty', stock: 0, threshold: 0); // available 0 = threshold 0: low

        $this->assertEqualsCanonicalizing([$reservedDown->id, $atThreshold->id, $empty->id], $this->ids(['stock' => 'low']));
        $this->assertNotContains($released->id, $this->ids(['stock' => 'low']));
        $this->assertNotContains($plenty->id, $this->ids(['stock' => 'low']));

        // The same rule as the vendor's scope.
        $this->assertEqualsCanonicalizing($this->ids(['stock' => 'low']), Product::query()->lowAvailableStock()->pluck('id')->all());
    }

    public function test_filters_combine(): void
    {
        $otherVendor = Vendor::factory()->approved()->create(['name' => 'Bait Nour']);
        $match = $this->state($this->product('Clay jug', stock: 1, threshold: 2), ['suspended_at']);
        $this->state($this->product('Clay jug archived', stock: 1, threshold: 2), ['suspended_at', 'archived_at']);
        $this->product('Clay jug not suspended', stock: 1, threshold: 2);
        $this->state($this->product('Clay jug other vendor', stock: 1, threshold: 2, vendor: $otherVendor), ['suspended_at']);
        $this->state($this->product('Clay jug plenty', stock: 50, threshold: 2), ['suspended_at']);
        $this->state($this->product('Bowl', stock: 1, threshold: 2), ['suspended_at']);

        $this->assertSame([$match->id], $this->ids([
            'search' => 'clay jug', 'vendor_id' => $this->vendor->id, 'category_id' => $this->category->id,
            'status' => 'active', 'suspension' => 'suspended', 'stock' => 'low',
        ]));
    }

    public function test_invalid_filter_values_are_ignored_without_errors(): void
    {
        $normal = $this->product('Normal');
        $this->state($this->product('Archived'), ['archived_at']);
        $admin = $this->admin(super: true);

        foreach ([
            ['status' => 'deleted'], ['vendor_id' => '999999'], ['vendor_id' => 'abc'], ['category_id' => '1 OR 1=1'],
            ['suspension' => 'maybe'], ['archive' => 'bogus'], ['stock' => 'high'], ['search' => ['x']],
            ['status' => ['active']], ['vendor_id' => ['1']], ['archive' => ['all']], ['stock' => ['low']],
        ] as $query) {
            $html = $this->page($admin, 'en', $query);
            $this->assertSame([$normal->id], $this->rowIds($html), json_encode($query));
            $this->assertStringNotContainsString('>Reset<', $html, 'An ignored value does not count as a filter: '.json_encode($query));
        }

        $this->assertStringContainsString('>Reset<', $this->page($admin, 'en', ['status' => 'active']));
    }

    // ---------------------------------------------------------------- figures, badges, empty states

    public function test_stock_figures_and_badges(): void
    {
        $this->seedTranslations();
        $product = $this->product('Clay jug', stock: 10, threshold: 4, sku: 'JUG-1');
        $this->reserve($product, 7);
        $this->state($product, ['suspended_at', 'archived_at']);
        $list = $this->list($this->page($this->admin(super: true), 'ar', ['archive' => 'all']));

        $this->assertMatchesRegularExpression('#<dt>الفعلي</dt>\s*<dd>10</dd>#u', $list);
        $this->assertMatchesRegularExpression('#<dt>المحجوز</dt>\s*<dd>7</dd>#u', $list);
        $this->assertMatchesRegularExpression('#<dt>المتاح</dt>\s*<dd>3</dd>#u', $list);
        $this->assertStringContainsString('<span class="admin-products-badge is-low">مخزون منخفض</span>', $list);
        $this->assertStringContainsString('<span class="admin-products-badge is-active">نشط</span>', $list);
        $this->assertStringContainsString('<span class="admin-products-badge is-suspended">معلّق</span>', $list);
        $this->assertStringContainsString('<span class="admin-products-badge is-archived">مؤرشف</span>', $list);
        $this->assertStringContainsString('<p class="admin-products-code" dir="ltr">JUG-1</p>', $list);
        $this->assertStringContainsString('<bdi>Dar Karma Store</bdi>', $list);
    }

    public function test_empty_states(): void
    {
        $admin = $this->admin(super: true);

        $this->assertStringContainsString('No products yet.', $this->page($admin, 'en'));

        $this->product('Clay jug');
        $this->assertStringContainsString('No products match these filters.', $this->page($admin, 'en', ['search' => 'nothing']));

        $past = $this->page($admin, 'en', ['page' => 9, 'status' => 'active']);
        $this->assertStringContainsString('There are no products on this page.', $past);
        $this->assertStringContainsString('status=active', $past, 'The last-page link keeps the filters.');
    }

    // ---------------------------------------------------------------- pagination

    public function test_pagination_is_translated_and_keeps_the_filters(): void
    {
        $this->seedTranslations();
        for ($i = 1; $i <= 17; $i++) {
            $this->product("Jug {$i}");
        }

        $html = $this->page($this->admin(super: true), 'ar', ['status' => 'active', 'stock' => '']);

        $this->assertStringContainsString('class="admin-pagination"', $html);
        $this->assertMatchesRegularExpression('#عرض\s+1\s+إلى\s+15\s+من\s+17#u', $html);
        $this->assertMatchesRegularExpression('#href="[^"]*page=2[^"]*"#', $html);
        $this->assertMatchesRegularExpression('#href="[^"]*status=active[^"]*page=2#', $html);
        $this->assertCount(15, $this->rowIds($html));
    }

    // ---------------------------------------------------------------- performance

    public function test_the_number_of_queries_does_not_grow_with_the_rows(): void
    {
        // Seeded, as on a deployed server: t() caches the values it finds (an absent key, whose value is null, is
        // looked up again on every call, so an unseeded page repeats its label lookups per row).
        $this->seedTranslations();
        $admin = $this->admin(super: true);
        $subcategory = Category::create(['name' => 'Jugs', 'slug' => 'jugs', 'parent_id' => $this->category->id, 'is_active' => true]);
        $vendors = [$this->vendor, Vendor::factory()->approved()->create(), Vendor::factory()->approved()->create()];

        $make = function (int $count) use ($vendors, $subcategory) {
            for ($i = 0; $i < $count; $i++) {
                $product = $this->product('Item '.uniqid(), vendor: $vendors[$i % 3], category: $i % 2 ? $subcategory : null);
                $this->reserve($product, 1);
            }
        };

        $make(3);
        // Warm the translation cache. Twice: the shared header creates three of its own keys on the first request
        // (its t() auto-creates them) and they are cached from the next one on.
        $this->page($admin, 'en');
        $this->page($admin, 'en');
        $few = $this->countQueries(fn () => $this->page($admin, 'en'));

        $make(12); // 15 rows on the page.
        $this->page($admin, 'en'); // Same warm-up for the new rows' labels.
        $many = $this->countQueries(fn () => $this->page($admin, 'en'));

        $this->assertSame($few, $many, "Queries: {$few} with 3 rows, {$many} with 15 rows.");
    }

    // ---------------------------------------------------------------- permissions

    public function test_actions_follow_the_product_abilities(): void
    {
        $product = $this->product('Clay jug');

        $viewOnly = $this->list($this->page($this->admin(['products.view']), 'en'));
        $this->assertStringNotContainsString(route('dashboard.products.create'), $viewOnly);
        $this->assertStringNotContainsString(route('dashboard.products.edit', $product), $viewOnly);
        $this->assertStringNotContainsString('class="is-actions"', $viewOnly, 'No actions column.');

        $creator = $this->page($this->admin(['products.view', 'products.create']), 'en');
        $this->assertStringContainsString(route('dashboard.products.create'), $creator);
        $this->assertStringNotContainsString(route('dashboard.products.edit', $product), $creator);

        $editor = $this->page($this->admin(['products.view', 'products.edit']), 'en');
        $this->assertStringNotContainsString(route('dashboard.products.create'), $editor);
        $this->assertStringContainsString('href="'.route('dashboard.products.edit', $product).'"', $editor);
        $this->assertStringContainsString('aria-label="Edit Clay jug"', $editor);
    }

    public function test_the_list_requires_products_view(): void
    {
        $this->get(route('dashboard.products.index'))->assertRedirect();
        $this->actingAs($this->admin(['products.edit']), 'admin')->get(route('dashboard.products.index'))->assertForbidden();
    }

    // ---------------------------------------------------------------- language

    public function test_direction_and_language(): void
    {
        $this->seedTranslations();
        $this->product('جرة فخار');
        $admin = $this->admin(super: true);

        $ar = $this->page($admin, 'ar');
        $this->assertMatchesRegularExpression('#<html lang="ar" [^>]*dir="rtl"#', $ar);
        foreach (['إضافة منتج', 'البحث في المنتجات', 'كل البائعين', 'غير المؤرشفة', 'مخزون منخفض', 'المنتج', 'السعر', 'تعديل'] as $text) {
            $this->assertStringContainsString($text, $ar, $text);
        }

        $en = $this->page($admin, 'en');
        $this->assertMatchesRegularExpression('#<html lang="en" [^>]*dir="ltr"#', $en);
        $this->assertDoesNotMatchRegularExpression(
            '/\p{Arabic}/u',
            preg_replace('#<bdi>[^<]*</bdi>|aria-label="Edit [^"]*"#u', '', $this->list($en)),
            'Only user data is Arabic in the English list.'
        );
    }

    public function test_the_page_creates_no_translation_rows(): void
    {
        $this->product('Clay jug');
        $this->page($this->admin(super: true), 'ar', ['stock' => 'low']);
        $this->page($this->admin(super: false, abilities: ['products.view']), 'en');

        $this->assertSame(0, TranslationValue::query()->where('key', 'like', 'dashboard.Product%')->count());
    }

    // ---------------------------------------------------------------- helpers

    private function seedTranslations(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->seed(AdminCategoryTranslationSeeder::class);
        $this->seed(AdminProductTranslationSeeder::class);
    }

    private function product(string $name, int $stock = 10, int $threshold = 1, string $status = 'active', ?Vendor $vendor = null, ?Category $category = null, ?string $sku = null): Product
    {
        return Product::create([
            'vendor_id' => ($vendor ?? $this->vendor)->id,
            'category_id' => ($category ?? $this->category)->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.uniqid(),
            'sku' => $sku,
            'price' => 12.5,
            'stock_quantity' => $stock,
            'low_stock_threshold' => $threshold,
            'status' => $status,
        ]);
    }

    private function state(Product $product, array $columns): Product
    {
        $product->forceFill(array_fill_keys($columns, now()))->save();

        return $product;
    }

    private function reserve(Product $product, int $quantity, string $status = 'reserved'): void
    {
        $customer = Customer::factory()->create();
        $orderId = DB::table('orders')->insertGetId(['number' => 'O-'.uniqid(), 'customer_id' => $customer->id, 'payment_method' => 'cod', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('stock_reservations')->insert(['product_id' => $product->id, 'order_id' => $orderId, 'quantity' => $quantity, 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function admin(array $abilities = [], bool $super = false): Admin
    {
        static $sequence = 0;
        $sequence++;
        $admin = Admin::create(['name' => "Admin {$sequence}", 'email' => "admin{$sequence}@example.com", 'phone' => '05990002'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT), 'password' => 'password', 'status' => 'active', 'super_admin' => $super]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function page(Admin $admin, string $locale = 'ar', array $query = []): string
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get(route('dashboard.products.index', $query))->assertOk()->getContent();
    }

    /** @return list<int> */
    private function ids(array $query = []): array
    {
        static $admin = null;
        $admin = $admin?->exists && Admin::whereKey($admin->id)->exists() ? $admin : $this->admin(super: true);

        return $this->rowIds($this->page($admin, 'en', $query));
    }

    /** @return list<int> */
    private function rowIds(string $html): array
    {
        preg_match_all('/data-product-id="(\d+)"/', $html, $matches);

        return array_map('intval', $matches[1]);
    }

    private function list(string $html): string
    {
        $this->assertSame(1, preg_match('#id="admin-products".*?</section>#s', $html, $match));

        return $match[0];
    }

    private function countQueries(callable $run): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $run();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
