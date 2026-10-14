<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Language;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAdminAction;
use App\Models\RoleUser;
use App\Models\StockReservation;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminCategoryTranslationSeeder;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Database\Seeders\AdminProductTranslationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * AD-UI-PROD-M6: archiving a product (instead of deleting it) and restoring it from the admin. Separate abilities,
 * a reason of 3 to 1000 characters in its own field (archive_reason), no repeated transition, the state and its log
 * entry written together under the product row lock. Archiving touches archived_at only; restoring clears it and
 * always sets status to draft (B1: published_at kept), never touching suspended_at and never making the product
 * sellable on its own. Checkout, the forms of M5 and M6 side by side, CSRF, escaping and both languages.
 */
class AdminProductArchiveTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->vendor = Vendor::factory()->approved()->create();
    }

    // ---------------------------------------------------------------- abilities

    public function test_each_action_needs_its_own_ability_checked_before_the_product_is_read(): void
    {
        $product = $this->product();

        $this->post(route('dashboard.products.archive', $product->id), ['archive_reason' => 'Duplicate'])->assertRedirect();
        foreach ([['products.view'], ['products.view', 'products.restore'], ['products.suspend', 'products.unsuspend', 'products.delete']] as $abilities) {
            $queries = $this->queriesOn('products', fn () => $this->actingAs($this->admin($abilities), 'admin')
                ->post(route('dashboard.products.archive', $product->id), ['archive_reason' => 'Duplicate'])->assertForbidden());
            $this->assertSame([], $queries, 'No product read before the ability.');
        }
        $this->assertNull($product->fresh()->archived_at);

        $this->actingAs($this->admin(['products.archive']), 'admin')->post(route('dashboard.products.archive', $product->id), ['archive_reason' => 'Duplicate'])->assertRedirect();
        $this->assertNotNull($product->fresh()->archived_at);

        $this->actingAs($this->admin(['products.view', 'products.archive']), 'admin')
            ->post(route('dashboard.products.restore', $product->id), ['archive_reason' => 'Back'])->assertForbidden();
        $this->actingAs($this->admin(['products.restore']), 'admin')->post(route('dashboard.products.restore', $product->id), ['archive_reason' => 'Back'])->assertRedirect();
        $this->assertNull($product->fresh()->archived_at);
        $this->assertSame(2, ProductAdminAction::count());
    }

    public function test_the_two_abilities_are_grantable(): void
    {
        $this->assertArrayHasKey('archive', app('abilities')['products']);
        $this->assertArrayHasKey('restore', app('abilities')['products']);

        $html = $this->actingAs($this->admin(super: true), 'admin')->get(route('dashboard.admins.create'))->getContent();
        $this->assertStringContainsString('value="products.archive"', $html);
        $this->assertStringContainsString('value="products.restore"', $html);
    }

    // ---------------------------------------------------------------- archive

    public function test_archiving_sets_archived_at_only_and_logs_it(): void
    {
        $product = $this->product(['status' => 'active', 'published_at' => now()->subDays(3)]);
        $before = $product->fresh();
        $admin = $this->admin(super: true);
        $this->travel(5)->seconds();

        $this->actingAs($admin, 'admin')
            ->post(route('dashboard.products.archive', $product->id), ['archive_reason' => '  Duplicate listing.  '])
            ->assertRedirect(route('dashboard.products.show', $product->id).'#product-panel-moderation')
            ->assertSessionHas('success', 'The product was archived. It can no longer be sold, and all its data is kept.');

        $after = $product->fresh();
        $this->assertNotNull($after->archived_at);
        foreach (['status', 'suspended_at', 'published_at', 'stock_quantity', 'price', 'name', 'category_id', 'vendor_id'] as $field) {
            $this->assertEquals($before->{$field}, $after->{$field}, $field);
        }
        $this->assertTrue($after->updated_at->greaterThan($before->updated_at));

        $entry = ProductAdminAction::sole();
        $this->assertSame(['archived', 'Duplicate listing.', $admin->id, $admin->name, 'Clay jug', 'JUG-1'], [$entry->action, $entry->reason, $entry->admin_id, $entry->admin_name, $entry->product_name, $entry->product_sku]);
        $this->assertSame(['archived_at' => [null, $after->archived_at->toIso8601String()]], $entry->changes);
    }

    public function test_archiving_keeps_every_related_row(): void
    {
        $product = $this->product(['stock_quantity' => 5]);
        $customer = $this->customerWithCart($product, 1);
        $this->actingAs($customer, 'customer')->postJson(route('customer.checkout.store'), ['payment_method' => 'cod'])->assertCreated();

        $this->actingAs($this->admin(super: true), 'admin')->post(route('dashboard.products.archive', $product->id), ['archive_reason' => 'Duplicate']);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertSame(1, StockReservation::where('product_id', $product->id)->count());
        $this->assertSame(1, DB::table('order_items')->where('product_id', $product->id)->count());
    }

    // ---------------------------------------------------------------- restore

    /** @return array<string, array{string}> */
    public static function statuses(): array
    {
        return ['active' => ['active'], 'inactive' => ['inactive'], 'out of stock' => ['out_of_stock'], 'draft' => ['draft']];
    }

    #[DataProvider('statuses')]
    public function test_restoring_always_makes_a_draft_and_logs_the_status_even_when_unchanged(string $status): void
    {
        $published = now()->subDays(10)->startOfSecond();
        $product = $this->moderated(['status' => $status, 'published_at' => $published], ['archived_at']);
        $archivedAt = $product->archived_at->toIso8601String();

        $this->actingAs($this->admin(super: true), 'admin')
            ->post(route('dashboard.products.restore', $product->id), ['archive_reason' => 'Listed again'])
            ->assertSessionHas('success', 'The product was restored as a draft. It is not on sale until it is published again.');

        $after = $product->fresh();
        $this->assertNull($after->archived_at);
        $this->assertSame('draft', $after->status);
        $this->assertEquals($published, $after->published_at, 'published_at is kept (B1).');
        $this->assertSame(['archived_at' => [$archivedAt, null], 'status' => [$status, 'draft']], ProductAdminAction::sole()->changes);
    }

    public function test_restoring_keeps_a_suspension_and_does_not_sell(): void
    {
        $product = $this->moderated(['status' => 'active'], ['archived_at', 'suspended_at']);
        $suspendedAt = $product->suspended_at;

        $this->actingAs($this->admin(super: true), 'admin')->post(route('dashboard.products.restore', $product->id), ['archive_reason' => 'Listed again']);

        $after = $product->fresh();
        $this->assertEquals($suspendedAt, $after->suspended_at, 'The suspension stays.');
        $this->assertFalse($after->isSellable());
    }

    public function test_a_suspended_product_can_be_archived_and_restored_without_lifting_the_suspension(): void
    {
        $product = $this->moderated([], ['suspended_at']);
        $admin = $this->admin(super: true);

        $this->actingAs($admin, 'admin')->post(route('dashboard.products.archive', $product->id), ['archive_reason' => 'Duplicate'])->assertSessionHas('success');
        $this->assertTrue($product->fresh()->isSuspended());
        $this->actingAs($admin, 'admin')->post(route('dashboard.products.restore', $product->id), ['archive_reason' => 'Back'])->assertSessionHas('success');
        $this->assertTrue($product->fresh()->isSuspended());
        $this->assertFalse($product->fresh()->isArchived());
    }

    public function test_a_repeated_transition_is_refused_and_writes_nothing(): void
    {
        $admin = $this->admin(super: true);
        $archived = $this->moderated([], ['archived_at']);
        $archivedAt = $archived->archived_at;
        $plain = $this->product(['name' => 'Plain', 'slug' => 'plain', 'status' => 'active']);

        $this->actingAs($admin, 'admin')->post(route('dashboard.products.archive', $archived->id), ['archive_reason' => 'Again'])
            ->assertRedirect(route('dashboard.products.show', $archived->id).'#product-panel-moderation')
            ->assertSessionHas('danger', 'This product is already archived. Nothing was changed.');
        $this->actingAs($admin, 'admin')->post(route('dashboard.products.restore', $plain->id), ['archive_reason' => 'Nothing to restore'])
            ->assertSessionHas('danger', 'This product is not archived. Nothing was changed.');

        $this->assertEquals($archivedAt, $archived->fresh()->archived_at);
        $this->assertSame('active', $plain->fresh()->status, 'A refused restore does not set draft.');
        $this->assertSame(0, ProductAdminAction::count());
    }

    // ---------------------------------------------------------------- reason

    /** @return array<string, array{mixed}> */
    public static function invalidReasons(): array
    {
        return [
            'missing' => [null], 'empty' => [''], 'spaces only' => ['    '], 'two characters' => ['ab'],
            'two after trimming' => ['  ab '], '1001 characters' => [str_repeat('a', 1001)], 'an array' => [['Duplicate']],
        ];
    }

    #[DataProvider('invalidReasons')]
    public function test_an_invalid_reason_is_a_field_error_and_writes_nothing(mixed $reason): void
    {
        $product = $this->product();

        $this->actingAs($this->admin(super: true), 'admin')->withHeader('referer', 'https://evil.example/')
            ->post(route('dashboard.products.archive', $product->id), $reason === null ? [] : ['archive_reason' => $reason])
            ->assertRedirect(route('dashboard.products.show', $product->id).'#product-panel-moderation')
            ->assertSessionHasErrors('archive_reason')
            ->assertSessionDoesntHaveErrors('reason');

        $this->assertNull($product->fresh()->archived_at);
        $this->assertSame(0, ProductAdminAction::count());
    }

    public function test_the_suspension_field_is_not_the_archive_reason(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin(super: true), 'admin')
            ->post(route('dashboard.products.archive', $product->id), ['reason' => 'Sent in the suspension field'])
            ->assertSessionHasErrors('archive_reason');

        $this->assertNull($product->fresh()->archived_at);
    }

    public function test_reasons_at_the_limits_are_accepted(): void
    {
        $admin = $this->admin(super: true);
        $product = $this->product();

        $this->actingAs($admin, 'admin')->post(route('dashboard.products.archive', $product->id), ['archive_reason' => 'abc'])->assertSessionHasNoErrors();
        $this->actingAs($admin, 'admin')->post(route('dashboard.products.restore', $product->id), ['archive_reason' => str_repeat('ب', 1000)])->assertSessionHasNoErrors();

        $this->assertSame(['abc', str_repeat('ب', 1000)], ProductAdminAction::orderBy('id')->pluck('reason')->all());
    }

    // ---------------------------------------------------------------- atomicity and locking

    public function test_the_state_is_rolled_back_when_the_log_entry_cannot_be_written(): void
    {
        $product = $this->moderated(['status' => 'active'], ['archived_at']);
        ProductAdminAction::creating(fn () => throw new RuntimeException('Simulated log failure.'));

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->admin(super: true), 'admin')->post(route('dashboard.products.restore', $product->id), ['archive_reason' => 'Back']);
            $this->fail('The failure must surface.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated log failure.', $e->getMessage());
        }

        $after = $product->fresh();
        $this->assertNotNull($after->archived_at, 'Still archived.');
        $this->assertSame('active', $after->status, 'No draft without its log entry.');
    }

    public function test_a_database_failure_is_reported_as_nothing_changed(): void
    {
        $product = $this->product();
        ProductAdminAction::creating(fn () => DB::select('select * from table_that_does_not_exist'));

        $this->actingAs($this->admin(super: true), 'admin')
            ->post(route('dashboard.products.archive', $product->id), ['archive_reason' => 'Duplicate'])
            ->assertSessionHas('danger', 'The change could not be saved, so nothing was changed. Please try again.')
            ->assertSessionHasInput('archive_reason', 'Duplicate');

        $this->assertNull($product->fresh()->archived_at);
    }

    public function test_the_row_is_locked_then_checked_then_written_then_logged(): void
    {
        $product = $this->moderated([], ['archived_at']);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = [DB::transactionLevel(), $query->sql];
        });

        $this->actingAs($this->admin(super: true), 'admin')->post(route('dashboard.products.restore', $product->id), ['archive_reason' => 'Back']);

        $inside = array_column(array_values(array_filter($queries, fn ($q) => $q[0] >= 2)), 1);
        $read = $this->firstIndex($inside, fn ($s) => str_starts_with($s, 'select') && str_contains($s, 'from "products"'));
        $write = $this->firstIndex($inside, fn ($s) => str_starts_with($s, 'update "products"'));
        $log = $this->firstIndex($inside, fn ($s) => str_starts_with($s, 'insert into "product_admin_actions"'));
        $this->assertTrue($read !== null && $write !== null && $log !== null && $read < $write && $write < $log);
        $this->assertStringContainsString('"archived_at" = ?', $inside[$write]);
        $this->assertStringContainsString('"status" = ?', $inside[$write]);
        $this->assertStringNotContainsString('"suspended_at"', $inside[$write]);
        $this->assertStringNotContainsString('"published_at"', $inside[$write]);
    }

    // ---------------------------------------------------------------- sale

    public function test_an_archived_product_is_refused_by_checkout_including_between_the_first_check_and_the_lock(): void
    {
        $admin = $this->admin(super: true);
        $product = $this->product(['stock_quantity' => 5]);
        $customer = $this->customerWithCart($product, 2);

        $archived = false;
        DB::connection()->beforeStartingTransaction(function () use (&$archived, $admin, $product) {
            if (! $archived) {
                $archived = true;
                $this->actingAs($admin, 'admin')->post(route('dashboard.products.archive', $product->id), ['archive_reason' => 'Duplicate']);
            }
        });

        $this->actingAs($customer, 'customer')->postJson(route('customer.checkout.store'), ['payment_method' => 'cod'])
            ->assertStatus(422)->assertJsonPath('message', 'Product [Clay jug] is no longer available for sale.');

        $this->assertNotNull($product->fresh()->archived_at);
        $this->assertSame([0, 0, 5], [Order::count(), StockReservation::count(), $product->fresh()->stock_quantity]);
        $this->getJson(route('shop.products.show', $product))->assertNotFound();
    }

    public function test_a_restored_product_stays_off_sale_until_the_vendor_publishes_it(): void
    {
        $product = $this->moderated(['status' => 'active'], ['archived_at']);
        $this->actingAs($this->admin(super: true), 'admin')->post(route('dashboard.products.restore', $product->id), ['archive_reason' => 'Listed again']);

        $this->assertFalse($product->fresh()->isSellable(), 'A draft: not on sale.');
        $this->getJson(route('shop.products.show', $product))->assertNotFound();

        // The vendor publishes it again (its own contract, unchanged).
        $this->actingAs($this->vendor, 'vendor')->putJson(route('vendor.dashboard.products.update', $product), [
            'name' => 'Clay jug', 'price' => 10, 'stock_quantity' => 10, 'status' => 'active',
        ])->assertOk();

        $this->assertTrue($product->fresh()->isSellable());
        $this->getJson(route('shop.products.show', $product))->assertOk();
    }

    // ---------------------------------------------------------------- routes and requests

    public function test_unknown_products_methods_and_ids_from_the_body(): void
    {
        $admin = $this->admin(super: true);
        $product = $this->product();
        $other = $this->product(['name' => 'Other', 'slug' => 'other']);

        $this->actingAs($admin, 'admin')->post('/dashboard/products/999999/archive', ['archive_reason' => 'Duplicate'])->assertNotFound();
        $this->actingAs($admin, 'admin')->post('/dashboard/products/abc/restore', ['archive_reason' => 'Duplicate'])->assertNotFound();
        $this->actingAs($admin, 'admin')->get(route('dashboard.products.archive', $product->id))->assertMethodNotAllowed();
        $this->actingAs($admin, 'admin')->delete(route('dashboard.products.restore', $product->id))->assertMethodNotAllowed();

        $this->actingAs($admin, 'admin')->post(route('dashboard.products.archive', $product->id), ['archive_reason' => 'Duplicate', 'product_id' => $other->id]);
        $this->assertTrue($product->fresh()->isArchived());
        $this->assertFalse($other->fresh()->isArchived());
    }

    public function test_csrf_is_required(): void
    {
        $product = $this->product();
        $this->app->instance(ValidateCsrfToken::class, new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->actingAs($this->admin(super: true), 'admin')
            ->post(route('dashboard.products.archive', $product->id), ['archive_reason' => 'Duplicate'])->assertStatus(419);

        $this->assertNull($product->fresh()->archived_at);
    }

    // ---------------------------------------------------------------- moderation tab

    public function test_the_tab_offers_the_archive_action_the_state_and_the_ability_allow(): void
    {
        $plain = $this->product();
        $archived = $this->moderated(['name' => 'Archived', 'slug' => 'archived'], ['archived_at']);
        $archiveForm = fn (Product $p) => 'action="'.route('dashboard.products.archive', $p->id).'"';
        $restoreForm = fn (Product $p) => 'action="'.route('dashboard.products.restore', $p->id).'"';

        $super = $this->admin(super: true);
        $this->assertStringContainsString($archiveForm($plain), $this->page($super, $plain));
        $this->assertStringNotContainsString($restoreForm($plain), $this->page($super, $plain));
        $this->assertStringContainsString($restoreForm($archived), $this->page($super, $archived));
        $this->assertStringNotContainsString($archiveForm($archived), $this->page($super, $archived));

        $archiver = $this->admin(['products.view', 'products.archive']);
        $this->assertStringContainsString($archiveForm($plain), $this->page($archiver, $plain));
        $this->assertStringNotContainsString('<form method="POST"', $this->page($archiver, $archived), 'Cannot restore: no form.');

        $restorer = $this->admin(['products.view', 'products.restore']);
        $this->assertStringContainsString($restoreForm($archived), $this->page($restorer, $archived));
        $this->assertStringNotContainsString('<form method="POST"', $this->page($restorer, $plain));

        $this->assertStringNotContainsString('<form method="POST"', $this->page($this->admin(['products.view']), $archived));
    }

    public function test_the_restore_form_warns_about_draft_and_suspension(): void
    {
        $product = $this->moderated([], ['archived_at']);
        $html = $this->page($this->admin(super: true), $product);

        $this->assertStringContainsString('The product comes back as a draft, whatever its status was, and is not on sale until it is published again. An administrative suspension, if any, stays.', $html);
        $this->assertStringContainsString('<label class="form-label" for="product-archive-reason">Why is the product restored?</label>', $html);
        $this->assertMatchesRegularExpression('#id="product-archive-reason" name="archive_reason" rows="3" required minlength="3" maxlength="1000"#', $html);
    }

    public function test_the_suspension_and_archive_forms_work_side_by_side_without_mixing(): void
    {
        $product = $this->product();
        $admin = $this->admin(super: true);

        $both = $this->page($admin, $product);
        $this->assertStringContainsString('action="'.route('dashboard.products.suspend', $product->id).'"', $both);
        $this->assertStringContainsString('action="'.route('dashboard.products.archive', $product->id).'"', $both);
        $this->assertSame(1, substr_count($both, 'id="product-moderation-reason"'));
        $this->assertSame(1, substr_count($both, 'id="product-archive-reason"'));

        // An archive error stays on the archive form; the suspension form keeps its own empty field.
        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->post(route('dashboard.products.archive', $product->id), ['archive_reason' => 'ab']);
        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.products.show', $product->id))->getContent();
        $this->assertStringContainsString('id="product-archive-error" role="alert">The reason field must be at least 3 characters.</p>', $html);
        $this->assertStringNotContainsString('id="product-moderation-error"', $html);
        $this->assertMatchesRegularExpression('#name="archive_reason"[^>]*aria-invalid="true"\s*>ab</textarea>#', $html);
        $this->assertMatchesRegularExpression('#name="reason"[^>]*>\s*</textarea>#', $html, 'The suspension field stays empty.');

        // And the other way round.
        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->post(route('dashboard.products.suspend', $product->id), ['reason' => 'x']);
        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.products.show', $product->id))->getContent();
        $this->assertStringContainsString('id="product-moderation-error"', $html);
        $this->assertStringNotContainsString('id="product-archive-error"', $html);
        $this->assertMatchesRegularExpression('#name="archive_reason"[^>]*>\s*</textarea>#', $html);

        // Both actions then succeed, each on its own column.
        $this->actingAs($admin, 'admin')->post(route('dashboard.products.suspend', $product->id), ['reason' => 'Fake photos']);
        $this->actingAs($admin, 'admin')->post(route('dashboard.products.archive', $product->id), ['archive_reason' => 'Duplicate']);
        $after = $product->fresh();
        $this->assertTrue($after->isSuspended() && $after->isArchived());
        $this->assertSame(['suspended', 'archived'], ProductAdminAction::orderBy('id')->pluck('action')->all());
    }

    public function test_reasons_and_messages_are_escaped(): void
    {
        $product = $this->product();
        $admin = $this->admin(super: true);
        $payload = '<script>alert(1)</script><img src=x onerror=alert(2)>';

        $this->actingAs($admin, 'admin')->post(route('dashboard.products.archive', $product->id), ['archive_reason' => $payload]);
        $html = $this->page($admin, $product);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString(e($payload), $html, 'In the action log.');
    }

    public function test_both_languages_and_no_translation_rows(): void
    {
        $product = $this->product();
        $admin = $this->admin(super: true);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->post(route('dashboard.products.archive', $product->id), ['archive_reason' => 'منتج مكرر']);
        $this->page($admin, $product, 'ar');
        $this->assertSame(0, TranslationValue::query()->where('key', 'like', 'dashboard.Product%')->count());

        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->seed(AdminCategoryTranslationSeeder::class);
        $this->seed(AdminProductTranslationSeeder::class);

        $ar = $this->page($admin, $product, 'ar');
        $this->assertMatchesRegularExpression('#<html lang="ar" [^>]*dir="rtl"#', $ar);
        foreach (['استعادة هذا المنتج', 'لماذا يُستعاد المنتج؟', 'يعود المنتج «مسودة»', 'استعادة كمسودة', 'منتج مكرر'] as $text) {
            $this->assertStringContainsString($text, $ar, $text);
        }

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->post(route('dashboard.products.restore', $product->id), ['archive_reason' => 'أُعيد عرضه']);
        $back = $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.products.show', $product->id))->getContent();
        $this->assertStringContainsString('استُعيد المنتج كمسودة', $back);
        $this->assertStringContainsString('أرشفة هذا المنتج', $back);

        $en = $this->page($admin, $product, 'en');
        $this->assertMatchesRegularExpression('#<html lang="en" [^>]*dir="ltr"#', $en);
        $this->assertStringContainsString('Why is the product archived?', $en);
    }

    // ---------------------------------------------------------------- helpers

    private function product(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'vendor_id' => $this->vendor->id,
            'name' => 'Clay jug',
            'slug' => 'clay-jug',
            'sku' => isset($attributes['name']) ? null : 'JUG-1',
            'price' => 10,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);
    }

    private function moderated(array $attributes, array $columns): Product
    {
        $product = $this->product($attributes);
        $product->forceFill(array_fill_keys($columns, now()->subHour()))->save();

        return $product->fresh();
    }

    private function customerWithCart(Product $product, int $quantity): Customer
    {
        $customer = Customer::factory()->create();
        $cart = Cart::create(['customer_id' => $customer->id, 'status' => 'active']);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => 10]);

        return $customer;
    }

    private function admin(array $abilities = [], bool $super = false): Admin
    {
        static $sequence = 0;
        $sequence++;
        $admin = Admin::create(['name' => "Archivist {$sequence}", 'email' => "arch{$sequence}@example.com", 'phone' => '05990005'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT), 'password' => 'password', 'status' => 'active', 'super_admin' => $super]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function page(Admin $admin, Product $product, string $locale = 'en'): string
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get(route('dashboard.products.show', $product->id))->assertOk()->getContent();
    }

    /** @return list<string> */
    private function queriesOn(string $table, callable $run): array
    {
        $queries = [];
        DB::listen(function ($query) use ($table, &$queries) {
            if (str_contains($query->sql, '"'.$table.'"')) {
                $queries[] = $query->sql;
            }
        });
        $run();

        return $queries;
    }

    private function firstIndex(array $items, callable $match): ?int
    {
        foreach ($items as $index => $item) {
            if ($match($item)) {
                return $index;
            }
        }

        return null;
    }
}
