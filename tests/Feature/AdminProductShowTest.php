<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Language;
use App\Models\Product;
use App\Models\ProductAdminAction;
use App\Models\ProductImage;
use App\Models\RoleUser;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminCategoryTranslationSeeder;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Database\Seeders\AdminProductTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * AD-UI-PROD-M4 (DEC-PROD-12): the read-only product details page. Permissions, the five groups (all present without
 * JavaScript), the current suspension reason with products.view, the action log only with products.audit (and not
 * even queried without it), its order and pagination, safe images, escaped vendor text, a constant number of
 * queries, both languages, and no moderation action.
 */
class AdminProductShowTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Category $category;

    private string $hotFile;

    protected function setUp(): void
    {
        parent::setUp();

        // A Vite hot file, so the page's script tag is rendered and can be asserted.
        $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot');
        file_put_contents($this->hotFile, 'http://vite.test');
        Vite::useHotFile($this->hotFile);

        Storage::fake('public');
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->vendor = Vendor::factory()->approved()->create(['name' => 'Dar Karma']);
        $parent = Category::create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $this->category = Category::create(['name' => 'Jugs', 'slug' => 'jugs', 'parent_id' => $parent->id, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    // ---------------------------------------------------------------- access

    public function test_the_page_needs_products_view(): void
    {
        $product = $this->product();

        $this->get(route('dashboard.products.show', $product))->assertRedirect();
        $this->actingAs($this->admin(['products.edit', 'products.audit']), 'admin')->get(route('dashboard.products.show', $product))->assertForbidden();
        $this->actingAs($this->admin(['products.view']), 'admin')->get(route('dashboard.products.show', $product))->assertOk();
    }

    public function test_an_unknown_product_is_not_found(): void
    {
        $this->actingAs($this->admin(super: true), 'admin')->get('/dashboard/products/999999')->assertNotFound();
    }

    public function test_the_list_links_to_the_details(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin(['products.view']), 'admin')->withSession(['locale' => 'en'])
            ->get(route('dashboard.products.index'))
            ->assertSee('href="'.route('dashboard.products.show', $product).'"', false);
    }

    public function test_the_edit_button_follows_products_edit_and_no_moderation_action_is_offered(): void
    {
        $product = $this->product();

        $viewer = $this->page($this->admin(['products.view']), $product, 'en');
        $this->assertStringNotContainsString(route('dashboard.products.edit', $product), $viewer);

        $editor = $this->page($this->admin(['products.view', 'products.edit']), $product, 'en');
        $this->assertStringContainsString('href="'.route('dashboard.products.edit', $product).'"', $editor);

        $content = $this->content($editor);
        $this->assertDoesNotMatchRegularExpression('/<form/i', $content, 'No action form (M5, M6).');
        $this->assertStringNotContainsString('name="_method"', $content);
    }

    public function test_products_audit_is_a_grantable_ability(): void
    {
        $this->assertArrayHasKey('audit', app('abilities')['products']);

        $html = $this->actingAs($this->admin(super: true), 'admin')->get(route('dashboard.admins.create'))->getContent();
        $this->assertStringContainsString('value="products.audit"', $html);
    }

    // ---------------------------------------------------------------- the five groups

    public function test_the_five_groups_are_all_in_the_page_without_javascript(): void
    {
        $this->seedTranslations();
        $html = $this->page($this->admin(super: true), $this->product(), 'ar');
        $content = $this->content($html);

        foreach (['info', 'images', 'stock', 'moderation', 'log'] as $key) {
            $this->assertMatchesRegularExpression('#<section class="card admin-media-panel admin-product-panel" id="product-panel-'.$key.'" aria-labelledby="product-heading-'.$key.'" data-product-panel="'.$key.'">#', $content, $key);
            $this->assertMatchesRegularExpression('#<h2 class="admin-product-panel-title" id="product-heading-'.$key.'">#', $content, $key);
            $this->assertStringContainsString('role="tab" id="product-tab-'.$key.'" aria-controls="product-panel-'.$key.'"', $content);
        }

        $this->assertDoesNotMatchRegularExpression('#<section[^>]*admin-product-panel[^>]*hidden#', $content, 'No group is hidden by the server.');
        $this->assertMatchesRegularExpression('#<div class="admin-product-tablist" role="tablist"[^>]*data-product-tablist hidden>#', $content, 'The tab list waits for the script.');
        $this->assertSame(1, substr_count($content, 'aria-selected="true"'));
        $this->assertStringContainsString('http://vite.test/resources/js/admin-product-show.js', $html);
        $this->assertSame(1, substr_count($html, '<h1'), 'One page title, in the header.');
        $this->assertStringNotContainsString('class="breadcrumb-item"', $html);
    }

    public function test_information_price_and_stock(): void
    {
        $product = $this->product([
            'sku' => 'JUG-1', 'price' => 145, 'compare_at_price' => 180, 'stock_quantity' => 10, 'low_stock_threshold' => 4,
            'weight' => 1.5, 'dimensions' => ['length' => 20, 'width' => 15, 'height' => 30],
            'short_description' => "Hand made.\nGlazed.", 'description' => 'A large jug.',
        ]);
        $this->reserve($product, 7);
        $content = $this->content($this->page($this->admin(super: true), $product, 'en'));

        foreach (['JUG-1', 'Dar Karma Store', 'Pottery › Jugs', '145.00', '180.00', '1.50', '20 × 15 × 30', "Hand made.\nGlazed.", 'A large jug.'] as $text) {
            $this->assertStringContainsString(e($text), $content, $text);
        }
        $this->assertMatchesRegularExpression('#<dt>On hand</dt><dd>10</dd>#', $content);
        $this->assertMatchesRegularExpression('#<dt>Reserved</dt><dd>7</dd>#', $content);
        $this->assertMatchesRegularExpression('#<dt>Available</dt><dd>3</dd>#', $content);
        $this->assertStringContainsString('<span class="admin-products-badge is-low">Low stock</span>', $content);
    }

    // ---------------------------------------------------------------- moderation and log

    public function test_suspended_and_archived_products_show_their_state_and_the_current_reason_with_view_only(): void
    {
        $product = $this->moderate($this->product(), ['suspended_at', 'archived_at']);
        $admin = $this->admin(super: true);
        $this->log($product, $admin, 'suspended', 'Old reason', minutesAgo: 30);
        $this->log($product, $admin, 'unsuspended', 'Lifted', minutesAgo: 20);
        $this->log($product, $admin, 'suspended', 'Misleading photos', minutesAgo: 10);
        $this->log($product, $admin, 'archived', 'Duplicate listing', minutesAgo: 5);

        $content = $this->content($this->page($this->admin(['products.view']), $product, 'en'));

        $this->assertStringContainsString('<span class="admin-products-badge is-suspended">Suspended</span>', $content);
        $this->assertStringContainsString('<span class="admin-products-badge is-archived">Archived</span>', $content);
        $this->assertStringContainsString('Reason for the suspension', $content);
        $this->assertStringContainsString('Misleading photos', $content, 'The latest suspension reason.');
        foreach (['Old reason', 'Lifted', 'Duplicate listing', 'Moderator'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $content, "The log is not shown without products.audit: {$hidden}");
        }
        $this->assertStringContainsString('Viewing the action log needs the &quot;View product action log&quot; permission.', $content);
    }

    public function test_without_products_audit_the_log_is_never_queried(): void
    {
        $product = $this->moderate($this->product(), ['suspended_at']);
        $this->log($product, $this->admin(super: true), 'suspended', 'Reason');
        $viewer = $this->admin(['products.view']);

        $queries = $this->queriesOn('product_admin_actions', fn () => $this->page($viewer, $product, 'en'));

        $this->assertCount(1, $queries, 'Only the current suspension reason.');
        $this->assertStringContainsString('"action" = ?', $queries[0]['sql']);
        $this->assertContains('suspended', $queries[0]['bindings']);
        $this->assertStringContainsString('limit 1', $queries[0]['sql']);

        // Not suspended: not even that.
        $this->assertSame([], $this->queriesOn('product_admin_actions', fn () => $this->page($viewer, $this->product(['name' => 'Other']), 'en')));
    }

    public function test_the_log_is_newest_first_and_paginated_by_twenty(): void
    {
        $product = $this->product();
        $admin = $this->admin(super: true);
        for ($i = 1; $i <= 25; $i++) {
            $this->log($product, $admin, $i % 2 ? 'suspended' : 'unsuspended', "Reason {$i}", minutesAgo: 100 - $i);
        }

        $first = $this->content($this->page($admin, $product, 'en'));
        $ids = $this->entryIds($first);
        $this->assertCount(20, $ids);
        $this->assertSame(ProductAdminAction::query()->orderByDesc('created_at')->orderByDesc('id')->limit(20)->pluck('id')->all(), $ids);
        $this->assertStringContainsString('Reason 25', $first);
        $this->assertMatchesRegularExpression('#href="[^"]*actions_page=2\#product-panel-log"#', $first, 'The next page opens the log tab.');

        $second = $this->content($this->page($admin, $product, 'en', ['actions_page' => 2]));
        $this->assertCount(5, $this->entryIds($second));
        $this->assertStringContainsString('Reason 1<', $second);
    }

    public function test_the_log_shows_action_admin_time_reason_and_changes(): void
    {
        $this->seedTranslations();
        $product = $this->product();
        $admin = $this->admin(super: true);
        $this->log($product, $admin, 'restored', 'Vendor fixed the photos', changes: ['status' => ['active', 'draft']]);

        $content = $this->content($this->page($admin, $product, 'ar'));

        $this->assertStringContainsString('<span class="admin-product-log-action is-restored">استعادة</span>', $content);
        $this->assertStringContainsString('<bdi>Moderator</bdi>', $content);
        $this->assertStringContainsString('Vendor fixed the photos', $content);
        $this->assertMatchesRegularExpression('#الحالة:\s*نشط\s*→\s*مسودة#u', $content);
        $this->assertMatchesRegularExpression('#<time class="admin-product-log-when" dir="ltr" datetime="[^"]+">\d{4}-\d{2}-\d{2} \d{2}:\d{2}</time>#', $content);
    }

    public function test_an_empty_log(): void
    {
        $content = $this->content($this->page($this->admin(super: true), $this->product(), 'en'));

        $this->assertStringContainsString('No administrative action on this product yet.', $content);
    }

    // ---------------------------------------------------------------- images

    public function test_only_stored_local_images_are_shown(): void
    {
        Storage::disk('public')->put('products/jug.jpg', 'jpeg');
        Storage::disk('public')->put('products/جرة.webp', 'webp');
        Storage::disk('public')->put('products/vector.svg', '<svg/>');
        Storage::disk('public')->put('products/page.php', '<?php');
        $product = $this->product();

        $paths = [
            'products/jug.jpg' => true,
            'products/جرة.webp' => true,
            'products/missing.png' => false,
            'https://evil.example/x.png' => false,
            '//evil.example/x.png' => false,
            '/etc/passwd.png' => false,
            '../outside.png' => false,
            'products/../products/jug.jpg' => false,
            'products/%2e%2e/jug.jpg' => false,
            'products\\jug.jpg' => false,
            'javascript:alert(1).png' => false,
            'data:image/png;base64,AAAA' => false,
            'products/vector.svg' => false,
            'products/page.php' => false,
            "products/jug.jpg\n" => false,
            'products/jug .jpg' => false,
        ];
        foreach (array_keys($paths) as $i => $path) {
            ProductImage::create(['product_id' => $product->id, 'path' => $path, 'sort_order' => $i, 'is_primary' => $i === 0]);
        }

        $content = $this->content($this->page($this->admin(super: true), $product, 'en'));
        preg_match_all('#<img src="([^"]*)"#', $content, $sources);

        $this->assertSame([Storage::disk('public')->url('products/jug.jpg'), Storage::disk('public')->url('products/جرة.webp')], array_map('html_entity_decode', $sources[1]));
        $this->assertSame(count($paths) - 2, substr_count($content, 'Not shown: not a stored image file'));
        $this->assertStringContainsString('<code class="admin-product-image-path" dir="ltr">https://evil.example/x.png</code>', $content, 'A refused path is shown as text.');
        $this->assertStringNotContainsString('src="https://evil', $content);
        $this->assertStringNotContainsString('src="//', $content);
        $this->assertStringNotContainsString('src="javascript', $content);
        $this->assertStringNotContainsString('src="data:', $content);
        $this->assertStringContainsString('<span class="admin-products-badge is-active">Main image</span>', $content);
        $this->assertStringContainsString('alt="Product image 1"', $content, 'A default alternative text.');
    }

    public function test_no_images(): void
    {
        $content = $this->content($this->page($this->admin(super: true), $this->product(), 'en'));

        $this->assertStringContainsString('This product has no images.', $content);
        $this->assertStringNotContainsString('<img', $content);
    }

    // ---------------------------------------------------------------- escaping

    public function test_vendor_and_admin_text_is_escaped(): void
    {
        $payload = '<script>alert(1)</script><img src=x onerror=alert(2)>';
        $product = $this->product(['name' => "Jug {$payload}", 'sku' => $payload, 'short_description' => $payload, 'description' => $payload]);
        $product = $this->moderate($product, ['suspended_at']);
        ProductImage::create(['product_id' => $product->id, 'path' => $payload, 'alt_text' => $payload, 'sort_order' => 0]);
        Storage::disk('public')->put('products/ok.png', 'png');
        ProductImage::create(['product_id' => $product->id, 'path' => 'products/ok.png', 'alt_text' => '" onload="alert(3)', 'sort_order' => 1]);
        $admin = $this->admin(super: true);
        $admin->forceFill(['name' => $payload])->save();
        $this->log($product, $admin, 'suspended', $payload, changes: [$payload => [$payload, $payload]]);

        $html = $this->page($admin, $product, 'en');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertStringNotContainsString('onload="alert(3)', $html);
        $this->assertStringContainsString(e($payload), $html);
        $this->assertStringContainsString('alt="&quot; onload=&quot;alert(3)"', $html);
    }

    // ---------------------------------------------------------------- performance

    public function test_the_number_of_queries_does_not_grow_with_images_or_log_entries(): void
    {
        $this->seedTranslations();
        $admin = $this->admin(super: true);
        $product = $this->moderate($this->product(), ['suspended_at']);
        $this->reserve($product, 1);

        $add = function (int $images, int $entries) use ($product, $admin) {
            for ($i = 0; $i < $images; $i++) {
                ProductImage::create(['product_id' => $product->id, 'path' => 'products/'.uniqid().'.png', 'sort_order' => $i]);
            }
            for ($i = 0; $i < $entries; $i++) {
                $this->log($product, $admin, 'suspended', 'Reason '.uniqid());
            }
        };

        $add(1, 1);
        $this->page($admin, $product, 'en');
        $this->page($admin, $product, 'en');
        $few = $this->countQueries(fn () => $this->page($admin, $product, 'en'));

        $add(9, 29);
        $this->page($admin, $product, 'en');
        $many = $this->countQueries(fn () => $this->page($admin, $product, 'en'));

        $this->assertSame($few, $many, "Queries: {$few} with 1 image and 1 entry, {$many} with 10 images and 30 entries.");
    }

    // ---------------------------------------------------------------- language

    public function test_direction_and_language(): void
    {
        $this->seedTranslations();
        $product = $this->product(['name' => 'جرة فخار']);
        $admin = $this->admin(super: true);

        $ar = $this->page($admin, $product, 'ar');
        $this->assertMatchesRegularExpression('#<html lang="ar" [^>]*dir="rtl"#', $ar);
        foreach (['العودة إلى المنتجات', 'معلومات المنتج', 'الصور', 'السعر والمخزون', 'الإشراف', 'سجل الإجراءات', 'المتاح', 'غير معلّق', 'غير مؤرشف'] as $text) {
            $this->assertStringContainsString($text, $ar, $text);
        }

        $en = $this->page($admin, $product, 'en');
        $this->assertMatchesRegularExpression('#<html lang="en" [^>]*dir="ltr"#', $en);
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', preg_replace('#<bdi>[^<]*</bdi>#u', '', $this->content($en)), 'Only user data is Arabic in English.');
    }

    public function test_the_page_creates_no_translation_rows(): void
    {
        $product = $this->moderate($this->product(), ['suspended_at']);
        $this->page($this->admin(super: true), $product, 'ar');
        $this->page($this->admin(['products.view']), $product, 'en');

        $this->assertSame(0, TranslationValue::query()->where('key', 'like', 'dashboard.Product%')->count());
    }

    // ---------------------------------------------------------------- helpers

    private function seedTranslations(): void
    {
        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->seed(AdminCategoryTranslationSeeder::class);
        $this->seed(AdminProductTranslationSeeder::class);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Clay jug',
            'slug' => 'clay-jug-'.uniqid(),
            'price' => 12.5,
            'stock_quantity' => 10,
            'low_stock_threshold' => 1,
            'status' => 'active',
        ]);
    }

    private function moderate(Product $product, array $columns): Product
    {
        $product->forceFill(array_fill_keys($columns, now()))->save();

        return $product->fresh();
    }

    private function log(Product $product, Admin $admin, string $action, string $reason, int $minutesAgo = 0, ?array $changes = null): ProductAdminAction
    {
        $entry = ProductAdminAction::create([
            'product_id' => $product->id, 'product_name' => $product->name, 'product_sku' => $product->sku,
            'admin_id' => $admin->id, 'admin_name' => $admin->name, 'action' => $action, 'reason' => $reason, 'changes' => $changes,
        ]);
        // The model is append-only; the test sets the time through the table.
        DB::table('product_admin_actions')->where('id', $entry->id)->update(['created_at' => now()->subMinutes($minutesAgo)]);

        return $entry;
    }

    private function reserve(Product $product, int $quantity): void
    {
        $orderId = DB::table('orders')->insertGetId(['number' => 'O-'.uniqid(), 'customer_id' => Customer::factory()->create()->id, 'payment_method' => 'cod', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('stock_reservations')->insert(['product_id' => $product->id, 'order_id' => $orderId, 'quantity' => $quantity, 'status' => 'reserved', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function admin(array $abilities = [], bool $super = false): Admin
    {
        static $sequence = 0;
        $sequence++;
        $admin = Admin::create(['name' => 'Moderator', 'email' => "moderator{$sequence}@example.com", 'phone' => '05990003'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT), 'password' => 'password', 'status' => 'active', 'super_admin' => $super]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function page(Admin $admin, Product $product, string $locale = 'ar', array $query = []): string
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])
            ->get(route('dashboard.products.show', ['product' => $product] + $query))->assertOk()->getContent();
    }

    private function content(string $html): string
    {
        $this->assertSame(1, preg_match('#id="admin-product-show".*</x-dashboard-layout>|id="admin-product-show".*?<footer#s', $html, $match) ?: preg_match('#id="admin-product-show".*#s', $html, $match));

        return $match[0];
    }

    /** @return list<int> */
    private function entryIds(string $html): array
    {
        preg_match_all('/data-action-id="(\d+)"/', $html, $matches);

        return array_map('intval', $matches[1]);
    }

    /** @return list<array{sql: string, bindings: array}> */
    private function queriesOn(string $table, callable $run): array
    {
        $queries = [];
        DB::listen(function ($query) use ($table, &$queries) {
            if (str_contains($query->sql, '"'.$table.'"')) {
                $queries[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
            }
        });
        $run();

        return $queries;
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
