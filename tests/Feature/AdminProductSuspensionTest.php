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
 * AD-UI-PROD-M5: suspending a product and lifting the suspension from the admin. Separate abilities, a reason of 3 to
 * 1000 characters, no repeated transition, the state and its log entry written together under the product row lock,
 * products.status and archived_at untouched, sale stopped (checkout included) and restored, the moderation tab forms
 * by ability, CSRF, escaping, both languages and no needless queries or writes.
 */
class AdminProductSuspensionTest extends TestCase
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

    public function test_each_action_needs_its_own_ability(): void
    {
        $product = $this->product();

        $this->post(route('dashboard.products.suspend', $product->id), ['reason' => 'Fake photos'])->assertRedirect();
        $this->assertNull($product->fresh()->suspended_at, 'A guest changes nothing.');

        foreach ([['products.view'], ['products.view', 'products.unsuspend'], ['products.view', 'products.edit', 'products.audit']] as $abilities) {
            $this->actingAs($this->admin($abilities), 'admin')
                ->post(route('dashboard.products.suspend', $product->id), ['reason' => 'Fake photos'])->assertForbidden();
        }
        $this->assertNull($product->fresh()->suspended_at);

        $this->actingAs($this->admin(['products.suspend']), 'admin')
            ->post(route('dashboard.products.suspend', $product->id), ['reason' => 'Fake photos'])->assertRedirect();
        $this->assertNotNull($product->fresh()->suspended_at, 'products.suspend alone is enough to suspend.');

        $this->actingAs($this->admin(['products.view', 'products.suspend']), 'admin')
            ->post(route('dashboard.products.unsuspend', $product->id), ['reason' => 'Fixed'])->assertForbidden();
        $this->assertNotNull($product->fresh()->suspended_at);

        $this->actingAs($this->admin(['products.unsuspend']), 'admin')
            ->post(route('dashboard.products.unsuspend', $product->id), ['reason' => 'Fixed'])->assertRedirect();
        $this->assertNull($product->fresh()->suspended_at);
        $this->assertSame(2, ProductAdminAction::count(), 'Only the two allowed actions were logged.');
    }

    public function test_the_ability_is_checked_before_the_product_is_read(): void
    {
        $product = $this->product();
        $queries = $this->queriesOn('products', fn () => $this->actingAs($this->admin(['products.view']), 'admin')
            ->post(route('dashboard.products.suspend', $product->id), ['reason' => 'Fake photos'])->assertForbidden());

        $this->assertSame([], $queries);
    }

    public function test_the_two_abilities_are_grantable(): void
    {
        $this->assertArrayHasKey('suspend', app('abilities')['products']);
        $this->assertArrayHasKey('unsuspend', app('abilities')['products']);

        $html = $this->actingAs($this->admin(super: true), 'admin')->get(route('dashboard.admins.create'))->getContent();
        $this->assertStringContainsString('value="products.suspend"', $html);
        $this->assertStringContainsString('value="products.unsuspend"', $html);
    }

    // ---------------------------------------------------------------- suspend and lift

    public function test_suspending_sets_the_state_logs_it_and_changes_nothing_else(): void
    {
        $product = $this->product(['status' => 'active', 'stock_quantity' => 7]);
        $before = $product->fresh();
        $admin = $this->admin(super: true);
        $this->travel(5)->seconds();

        $this->actingAs($admin, 'admin')
            ->post(route('dashboard.products.suspend', $product->id), ['reason' => "  Misleading photos.\nPlease review.  "])
            ->assertRedirect(route('dashboard.products.show', $product->id).'#product-panel-moderation')
            ->assertSessionHas('success', 'The product was suspended. It can no longer be sold.');

        $after = $product->fresh();
        $this->assertNotNull($after->suspended_at);
        $this->assertTrue($after->updated_at->greaterThan($before->updated_at), 'updated_at follows.');
        foreach (['status', 'archived_at', 'stock_quantity', 'price', 'name', 'published_at'] as $field) {
            $this->assertEquals($before->{$field}, $after->{$field}, $field);
        }

        $entry = ProductAdminAction::sole();
        $this->assertSame('suspended', $entry->action);
        $this->assertSame("Misleading photos.\nPlease review.", $entry->reason, 'Trimmed at both ends only.');
        $this->assertSame([$product->id, 'Clay jug', 'JUG-1', $admin->id, $admin->name], [$entry->product_id, $entry->product_name, $entry->product_sku, $entry->admin_id, $entry->admin_name]);
        $this->assertSame(['suspended_at' => [null, $after->suspended_at->toIso8601String()]], $entry->changes);
    }

    public function test_lifting_clears_the_state_logs_it_and_keeps_status_and_archive(): void
    {
        $product = $this->moderated(['status' => 'inactive'], ['suspended_at', 'archived_at']);
        $suspendedAt = $product->suspended_at->toIso8601String();
        $archivedAt = $product->archived_at;

        $this->actingAs($this->admin(super: true), 'admin')
            ->post(route('dashboard.products.unsuspend', $product->id), ['reason' => 'Photos fixed'])
            ->assertRedirect(route('dashboard.products.show', $product->id).'#product-panel-moderation')
            ->assertSessionHas('success', 'The suspension was lifted.');

        $after = $product->fresh();
        $this->assertNull($after->suspended_at);
        $this->assertSame('inactive', $after->status, 'Lifting does not publish.');
        $this->assertEquals($archivedAt, $after->archived_at, 'Lifting does not restore an archived product.');

        $entry = ProductAdminAction::sole();
        $this->assertSame(['unsuspended', 'Photos fixed', ['suspended_at' => [$suspendedAt, null]]], [$entry->action, $entry->reason, $entry->changes]);
    }

    public function test_an_archived_product_can_be_suspended_and_lifted_and_stays_archived(): void
    {
        $product = $this->moderated([], ['archived_at']);
        $admin = $this->admin(super: true);

        $this->actingAs($admin, 'admin')->post(route('dashboard.products.suspend', $product->id), ['reason' => 'Fake photos'])->assertSessionHas('success');
        $this->assertTrue($product->fresh()->isSuspended());
        $this->assertTrue($product->fresh()->isArchived());

        $this->actingAs($admin, 'admin')->post(route('dashboard.products.unsuspend', $product->id), ['reason' => 'Fixed'])->assertSessionHas('success');
        $this->assertFalse($product->fresh()->isSuspended());
        $this->assertTrue($product->fresh()->isArchived());
        $this->assertFalse($product->fresh()->isSellable(), 'Still archived: still off sale.');
    }

    public function test_a_repeated_transition_is_refused_and_writes_nothing(): void
    {
        $admin = $this->admin(super: true);
        $suspended = $this->moderated([], ['suspended_at']);
        $suspendedAt = $suspended->suspended_at;
        $plain = $this->product(['name' => 'Plain', 'slug' => 'plain']);

        $this->actingAs($admin, 'admin')->post(route('dashboard.products.suspend', $suspended->id), ['reason' => 'Again'])
            ->assertRedirect(route('dashboard.products.show', $suspended->id).'#product-panel-moderation')
            ->assertSessionHas('danger', 'This product is already suspended. Nothing was changed.');
        $this->actingAs($admin, 'admin')->post(route('dashboard.products.unsuspend', $plain->id), ['reason' => 'Nothing to lift'])
            ->assertSessionHas('danger', 'This product is not suspended. Nothing was changed.');

        $this->assertEquals($suspendedAt, $suspended->fresh()->suspended_at);
        $this->assertNull($plain->fresh()->suspended_at);
        $this->assertSame(0, ProductAdminAction::count());
    }

    // ---------------------------------------------------------------- reason

    /** @return array<string, array{mixed}> */
    public static function invalidReasons(): array
    {
        return [
            'missing' => [null],
            'empty' => [''],
            'spaces only' => ['     '],
            'two characters' => ['ab'],
            'two characters after trimming' => ['  ab  '],
            '1001 characters' => [str_repeat('a', 1001)],
            'an array' => [['Fake photos']],
        ];
    }

    #[DataProvider('invalidReasons')]
    public function test_an_invalid_reason_is_a_field_error_and_writes_nothing(mixed $reason): void
    {
        $product = $this->product();
        $payload = $reason === null ? [] : ['reason' => $reason];

        $this->actingAs($this->admin(super: true), 'admin')->withHeader('referer', 'https://evil.example/')
            ->post(route('dashboard.products.suspend', $product->id), $payload)
            ->assertRedirect(route('dashboard.products.show', $product->id).'#product-panel-moderation')
            ->assertSessionHasErrors('reason');

        $this->assertNull($product->fresh()->suspended_at);
        $this->assertSame(0, ProductAdminAction::count());
    }

    public function test_reasons_at_the_limits_are_accepted(): void
    {
        $admin = $this->admin(super: true);
        $product = $this->product();

        $this->actingAs($admin, 'admin')->post(route('dashboard.products.suspend', $product->id), ['reason' => 'abc'])->assertSessionHasNoErrors();
        $this->actingAs($admin, 'admin')->post(route('dashboard.products.unsuspend', $product->id), ['reason' => str_repeat('ب', 1000)])->assertSessionHasNoErrors();

        $this->assertSame(['abc', str_repeat('ب', 1000)], ProductAdminAction::orderBy('id')->pluck('reason')->all(), 'Counted in characters, not bytes.');
    }

    // ---------------------------------------------------------------- atomicity and locking

    public function test_the_state_is_rolled_back_when_the_log_entry_cannot_be_written(): void
    {
        $product = $this->product();
        ProductAdminAction::creating(fn () => throw new RuntimeException('Simulated log failure.'));

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->admin(super: true), 'admin')->post(route('dashboard.products.suspend', $product->id), ['reason' => 'Fake photos']);
            $this->fail('The failure must surface, never as a success.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated log failure.', $e->getMessage());
        }

        $this->assertNull($product->fresh()->suspended_at, 'No suspension without its log entry.');
        $this->assertSame(0, ProductAdminAction::count());
    }

    public function test_a_database_failure_is_reported_as_nothing_changed(): void
    {
        $product = $this->product();
        ProductAdminAction::creating(fn () => DB::select('select * from table_that_does_not_exist'));

        $this->actingAs($this->admin(super: true), 'admin')
            ->post(route('dashboard.products.suspend', $product->id), ['reason' => 'Fake photos'])
            ->assertRedirect(route('dashboard.products.show', $product->id).'#product-panel-moderation')
            ->assertSessionHas('danger', 'The change could not be saved, so nothing was changed. Please try again.')
            ->assertSessionMissing('success')
            ->assertSessionHasInput('reason', 'Fake photos');

        $this->assertNull($product->fresh()->suspended_at);
        $this->assertSame(0, ProductAdminAction::count());
    }

    public function test_the_row_is_locked_then_checked_then_written_then_logged_inside_one_transaction(): void
    {
        $product = $this->product();
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = [DB::transactionLevel(), $query->sql];
        });

        $this->actingAs($this->admin(super: true), 'admin')->post(route('dashboard.products.suspend', $product->id), ['reason' => 'Fake photos']);

        // RefreshDatabase holds level 1; the action's transaction is level 2.
        $inside = array_column(array_values(array_filter($queries, fn ($q) => $q[0] >= 2)), 1);
        $read = $this->firstIndex($inside, fn ($s) => str_starts_with($s, 'select') && str_contains($s, 'from "products"'));
        $write = $this->firstIndex($inside, fn ($s) => str_starts_with($s, 'update "products"'));
        $log = $this->firstIndex($inside, fn ($s) => str_starts_with($s, 'insert into "product_admin_actions"'));
        $this->assertNotNull($read);
        $this->assertNotNull($write);
        $this->assertNotNull($log);
        $this->assertTrue($read < $write && $write < $log, 'read (locked), write, log');
        $this->assertStringContainsString('"suspended_at" = ?', $inside[$write]);
        $this->assertStringNotContainsString('"status"', $inside[$write]);
        $this->assertStringNotContainsString('"archived_at"', $inside[$write]);
    }

    public function test_an_action_runs_a_small_fixed_number_of_queries(): void
    {
        $product = $this->product();
        $admin = $this->admin(super: true);

        $count = $this->countQueries(fn () => $this->actingAs($admin, 'admin')->post(route('dashboard.products.suspend', $product->id), ['reason' => 'Fake photos']));

        // Ability, product read, product write, log insert (and the auth / session lookups of the request).
        $this->assertLessThanOrEqual(8, $count, "Queries: {$count}");
    }

    // ---------------------------------------------------------------- sale

    public function test_a_suspended_product_is_refused_by_checkout_and_sells_again_once_lifted(): void
    {
        $admin = $this->admin(super: true);
        $product = $this->product(['stock_quantity' => 5]);
        $customer = $this->customerWithCart($product, 2);

        $this->actingAs($admin, 'admin')->post(route('dashboard.products.suspend', $product->id), ['reason' => 'Fake photos']);

        $this->actingAs($customer, 'customer')->postJson(route('customer.checkout.store'), ['payment_method' => 'cod'])
            ->assertStatus(422)->assertJsonPath('message', 'Product [Clay jug] is no longer available for sale.');
        $this->getJson(route('shop.products.show', $product))->assertNotFound();
        $this->assertSame([0, 0, 5], [Order::count(), StockReservation::count(), $product->fresh()->stock_quantity]);

        $this->actingAs($admin, 'admin')->post(route('dashboard.products.unsuspend', $product->id), ['reason' => 'Fixed']);

        $this->getJson(route('shop.products.show', $product))->assertOk();
        $this->actingAs($customer, 'customer')->postJson(route('customer.checkout.store'), ['payment_method' => 'cod'])->assertCreated();
        $this->assertSame(1, StockReservation::where('product_id', $product->id)->where('status', 'reserved')->count());
    }

    public function test_a_suspension_between_the_first_check_and_the_lock_is_refused_by_checkout(): void
    {
        $admin = $this->admin(super: true);
        $product = $this->product(['stock_quantity' => 5]);
        $customer = $this->customerWithCart($product, 2);

        // The product passes checkout's first check; the suspension is committed through the real admin action just
        // before checkout's own transaction starts, so only the re-check on the locked row can refuse it.
        $suspended = false;
        DB::connection()->beforeStartingTransaction(function () use (&$suspended, $admin, $product) {
            if (! $suspended) {
                $suspended = true;
                $this->actingAs($admin, 'admin')->post(route('dashboard.products.suspend', $product->id), ['reason' => 'Fake photos']);
            }
        });

        $this->actingAs($customer, 'customer')->postJson(route('customer.checkout.store'), ['payment_method' => 'cod'])
            ->assertStatus(422)->assertJsonPath('message', 'Product [Clay jug] is no longer available for sale.');

        $this->assertNotNull($product->fresh()->suspended_at);
        $this->assertSame([0, 0, 5], [Order::count(), StockReservation::count(), $product->fresh()->stock_quantity]);
    }

    public function test_lifting_does_not_bypass_the_other_sale_conditions(): void
    {
        $product = $this->moderated(['status' => 'draft'], ['suspended_at']);

        $this->actingAs($this->admin(super: true), 'admin')->post(route('dashboard.products.unsuspend', $product->id), ['reason' => 'Fixed']);

        $this->assertFalse($product->fresh()->isSellable(), 'A draft stays off sale.');
    }

    public function test_the_vendor_still_cannot_lift_a_suspension(): void
    {
        $product = $this->moderated([], ['suspended_at']);

        $this->actingAs($this->vendor, 'vendor')->putJson(route('vendor.dashboard.products.update', $product), [
            'name' => 'Clay jug', 'price' => 10, 'stock_quantity' => 10, 'status' => 'active', 'suspended_at' => null,
        ])->assertOk();

        $this->assertTrue($product->fresh()->isSuspended());
    }

    // ---------------------------------------------------------------- routes and requests

    public function test_unknown_products_methods_and_ids_from_the_body(): void
    {
        $admin = $this->admin(super: true);
        $product = $this->product();
        $other = $this->product(['name' => 'Other', 'slug' => 'other']);

        $this->actingAs($admin, 'admin')->post('/dashboard/products/999999/suspend', ['reason' => 'Fake photos'])->assertNotFound();
        $this->actingAs($admin, 'admin')->post('/dashboard/products/abc/suspend', ['reason' => 'Fake photos'])->assertNotFound();
        $this->actingAs($admin, 'admin')->get(route('dashboard.products.suspend', $product->id))->assertMethodNotAllowed();
        $this->actingAs($admin, 'admin')->put(route('dashboard.products.unsuspend', $product->id), ['reason' => 'x'])->assertMethodNotAllowed();

        // The product is the one in the URL, whatever the body says.
        $this->actingAs($admin, 'admin')->post(route('dashboard.products.suspend', $product->id), ['reason' => 'Fake photos', 'product' => $other->id, 'product_id' => $other->id]);
        $this->assertTrue($product->fresh()->isSuspended());
        $this->assertFalse($other->fresh()->isSuspended());
    }

    public function test_csrf_is_required(): void
    {
        $product = $this->product();
        // The test runner skips CSRF checks; run the real middleware for this request.
        $this->app->instance(ValidateCsrfToken::class, new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->actingAs($this->admin(super: true), 'admin')
            ->post(route('dashboard.products.suspend', $product->id), ['reason' => 'Fake photos'])
            ->assertStatus(419);

        $this->assertNull($product->fresh()->suspended_at);
    }

    // ---------------------------------------------------------------- moderation tab

    public function test_the_tab_offers_the_one_action_the_state_and_the_ability_allow(): void
    {
        $plain = $this->product();
        $suspended = $this->moderated(['name' => 'Suspended', 'slug' => 'suspended'], ['suspended_at']);
        $suspendForm = fn (Product $p) => 'action="'.route('dashboard.products.suspend', $p->id).'"';
        $liftForm = fn (Product $p) => 'action="'.route('dashboard.products.unsuspend', $p->id).'"';

        $super = $this->admin(super: true);
        $this->assertStringContainsString($suspendForm($plain), $this->page($super, $plain));
        $this->assertStringNotContainsString($liftForm($plain), $this->page($super, $plain));
        $this->assertStringContainsString($liftForm($suspended), $this->page($super, $suspended));
        $this->assertStringNotContainsString($suspendForm($suspended), $this->page($super, $suspended));

        $suspender = $this->admin(['products.view', 'products.suspend']);
        $this->assertStringContainsString($suspendForm($plain), $this->page($suspender, $plain));
        $this->assertStringNotContainsString('<form method="POST"', $this->page($suspender, $suspended), 'Cannot lift: no form.');

        $lifter = $this->admin(['products.view', 'products.unsuspend']);
        $this->assertStringContainsString($liftForm($suspended), $this->page($lifter, $suspended));
        $this->assertStringNotContainsString('<form method="POST"', $this->page($lifter, $plain), 'Cannot suspend: no form.');

        $viewer = $this->admin(['products.view']);
        $this->assertStringNotContainsString('<form method="POST"', $this->page($viewer, $plain));
        $this->assertStringNotContainsString('<form method="POST"', $this->page($viewer, $suspended));
    }

    public function test_the_form_has_a_labelled_bounded_reason_and_a_csrf_token(): void
    {
        $html = $this->page($this->admin(super: true), $this->product());

        $this->assertStringContainsString('<label class="form-label" for="product-moderation-reason">Why is the product suspended?</label>', $html);
        $this->assertMatchesRegularExpression('#<textarea class="form-control " id="product-moderation-reason" name="reason" rows="3" required minlength="3" maxlength="1000"#', $html);
        $this->assertMatchesRegularExpression('#action="[^"]+/suspend"[^>]*>\s*<input type="hidden" name="_token"#', $html);
        $this->assertSame(1, substr_count($html, 'id="product-panel-moderation"'));
    }

    public function test_a_refused_reason_is_shown_by_the_field_with_the_text_kept(): void
    {
        $product = $this->product();
        $admin = $this->admin(super: true);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])
            ->post(route('dashboard.products.suspend', $product->id), ['reason' => 'ab']);
        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.products.show', $product->id))->getContent();

        $this->assertMatchesRegularExpression('#<p class="admin-product-moderation-error" id="product-moderation-error" role="alert">The reason field must be at least 3 characters\.</p>#', $html);
        $this->assertMatchesRegularExpression('#aria-invalid="true"\s*>ab</textarea>#', $html);
        $this->assertStringContainsString('aria-describedby="product-moderation-hint product-moderation-limits product-moderation-error"', $html);
    }

    public function test_messages_and_reasons_are_escaped(): void
    {
        $product = $this->product();
        $admin = $this->admin(super: true);
        $payload = '<script>alert(1)</script><img src=x onerror=alert(2)>';

        $this->actingAs($admin, 'admin')->post(route('dashboard.products.suspend', $product->id), ['reason' => $payload]);
        $html = $this->page($admin, $product);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, e($payload)), 'In the current reason and in the log.');
    }

    public function test_both_languages_and_no_translation_rows(): void
    {
        $product = $this->product();
        $admin = $this->admin(super: true);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->post(route('dashboard.products.suspend', $product->id), ['reason' => 'صور مضللة']);
        $this->assertSame(0, TranslationValue::query()->where('key', 'like', 'dashboard.Product%')->count(), 'Unseeded: English defaults, no rows written.');

        $this->seed(AdminDashboardTranslationSeeder::class);
        $this->seed(AdminCategoryTranslationSeeder::class);
        $this->seed(AdminProductTranslationSeeder::class);

        $ar = $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar', 'success' => null])->get(route('dashboard.products.show', $product->id))->getContent();
        $this->assertMatchesRegularExpression('#<html lang="ar" [^>]*dir="rtl"#', $ar);
        foreach (['رفع التعليق', 'لماذا يُرفع التعليق؟', 'بين 3 و1000 حرف', 'صور مضللة'] as $text) {
            $this->assertStringContainsString($text, $ar, $text);
        }

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->post(route('dashboard.products.unsuspend', $product->id), ['reason' => 'أُصلحت الصور']);
        $back = $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.products.show', $product->id))->getContent();
        $this->assertStringContainsString('رُفع التعليق عن المنتج.', $back);
        $this->assertStringContainsString('تعليق هذا المنتج', $back);

        $en = $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.products.show', $product->id))->getContent();
        $this->assertMatchesRegularExpression('#<html lang="en" [^>]*dir="ltr"#', $en);
        $this->assertStringContainsString('Why is the product suspended?', $en);
    }

    // ---------------------------------------------------------------- helpers

    private function product(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'vendor_id' => $this->vendor->id,
            'name' => 'Clay jug',
            'slug' => 'clay-jug',
            // SKUs are unique: only the default product carries JUG-1.
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
        $admin = Admin::create(['name' => "Moderator {$sequence}", 'email' => "mod{$sequence}@example.com", 'phone' => '05990004'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT), 'password' => 'password', 'status' => 'active', 'super_admin' => $super]);

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

    private function countQueries(callable $run): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $run();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
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
