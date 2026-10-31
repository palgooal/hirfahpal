<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * VENDOR-PRODUCT-PUBLISH-BE-01A (VENDOR-PRODUCT-PUBLISH-DECISION-01, OPEN-PROD-18 to 22, DETAIL-01 to 04): the vendor
 * chooses draft, active or inactive (draft when omitted on create, the stored status when omitted on update, never
 * out_of_stock). published_at follows the transition from the locked row: publishing sets a fresh date (a restored
 * draft's kept date is not reused), staying active keeps it, leaving active clears it. An archived product is not
 * editable by its vendor; a suspended one is, and stays suspended and off sale.
 */
class VendorProductPublicationTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->approved()->create();
        $this->travelTo(Carbon::parse('2026-10-31 12:00:00'));
    }

    // ---------------------------------------------------------------- create

    public function test_a_new_product_is_a_draft_when_no_status_is_sent(): void
    {
        $product = $this->create([]);

        $this->assertSame('draft', $product->status);
        $this->assertNull($product->published_at);
    }

    public function test_a_new_product_can_be_published_and_gets_the_current_date(): void
    {
        // A published_at in the body is not the vendor's to set.
        $product = $this->create(['status' => 'active', 'published_at' => '2020-01-01 00:00:00']);

        $this->assertSame('active', $product->status);
        $this->assertEquals(now(), $product->published_at);
        $this->assertTrue($product->isSellable());
        $this->getJson(route('shop.products.show', $product))->assertOk();
    }

    public function test_a_new_product_can_be_created_inactive(): void
    {
        $product = $this->create(['status' => 'inactive']);

        $this->assertSame('inactive', $product->status);
        $this->assertNull($product->published_at);
        $this->assertFalse($product->isSellable());
    }

    #[DataProvider('refusedStatuses')]
    public function test_out_of_stock_and_unknown_statuses_are_refused_on_create(mixed $status): void
    {
        $this->actingAs($this->vendor, 'vendor')
            ->postJson(route('vendor.dashboard.products.store'), $this->input(['status' => $status]))
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertSame(0, Product::count());
    }

    public static function refusedStatuses(): array
    {
        return ['out of stock' => ['out_of_stock'], 'unknown' => ['published'], 'array' => [['active']]];
    }

    // ---------------------------------------------------------------- update: transitions

    public function test_publishing_a_draft_sets_the_current_date(): void
    {
        $product = $this->product(['status' => 'draft']);

        $this->update($product, ['status' => 'active'])->assertOk()->assertJsonPath('product.status', 'active');

        $this->assertEquals(now(), $product->fresh()->published_at);
        $this->assertTrue($product->fresh()->isSellable());
    }

    #[DataProvider('unpublished')]
    public function test_unpublishing_clears_the_date(string $status): void
    {
        $product = $this->product(['status' => 'active', 'published_at' => now()->subDays(3)]);

        $this->update($product, ['status' => $status])->assertOk();

        $this->assertSame($status, $product->fresh()->status);
        $this->assertNull($product->fresh()->published_at);
        $this->assertFalse($product->fresh()->isSellable());
    }

    public static function unpublished(): array
    {
        return ['draft' => ['draft'], 'inactive' => ['inactive']];
    }

    public function test_saving_an_active_product_keeps_its_date_with_or_without_the_status(): void
    {
        $published = now()->subDays(3)->startOfSecond();
        $product = $this->product(['status' => 'active', 'published_at' => $published]);

        $this->update($product, ['status' => 'active', 'name' => 'Clay jug, glazed'])->assertOk();
        $this->assertEquals($published, $product->fresh()->published_at);

        $this->update($product, ['name' => 'Clay jug, blue'])->assertOk();
        $this->assertSame('active', $product->fresh()->status);
        $this->assertEquals($published, $product->fresh()->published_at);
    }

    public function test_moving_between_unpublished_statuses_keeps_the_date_empty(): void
    {
        $product = $this->product(['status' => 'draft']);

        $this->update($product, ['status' => 'inactive'])->assertOk();
        $this->assertSame(['inactive', null], [$product->fresh()->status, $product->fresh()->published_at]);

        $this->update($product, [])->assertOk();
        $this->assertSame(['inactive', null], [$product->fresh()->status, $product->fresh()->published_at]);
    }

    // ---------------------------------------------------------------- restored drafts (DETAIL-04)

    public function test_a_restored_draft_keeps_its_old_date_until_the_vendor_republishes_it_with_a_fresh_one(): void
    {
        $published = now()->subDays(10)->startOfSecond();
        $product = $this->restored(['status' => 'active', 'published_at' => $published]);

        $this->assertSame('draft', $product->status);
        $this->assertEquals($published, $product->published_at, 'Kept at restore (OPEN-PROD-04B).');

        // Edited while still unpublished: the kept date stays as it is.
        $this->update($product, ['name' => 'Clay jug, restored'])->assertOk();
        $this->assertSame('draft', $product->fresh()->status);
        $this->assertEquals($published, $product->fresh()->published_at);

        $this->travel(5)->minutes();
        $this->update($product, ['status' => 'active'])->assertOk();

        $this->assertEquals(now(), $product->fresh()->published_at, 'A fresh date, never the restored one.');
        $this->assertTrue($product->fresh()->isSellable());
        $this->getJson(route('shop.products.show', $product))->assertOk();
    }

    public function test_a_restored_draft_moved_to_inactive_is_still_unpublished(): void
    {
        $published = now()->subDays(10)->startOfSecond();
        $product = $this->restored(['status' => 'active', 'published_at' => $published]);

        $this->update($product, ['status' => 'inactive'])->assertOk();

        $this->assertSame('inactive', $product->fresh()->status);
        $this->assertEquals($published, $product->fresh()->published_at);
        $this->assertFalse($product->fresh()->isSellable());
    }

    // ---------------------------------------------------------------- out_of_stock (DETAIL-01)

    public function test_a_historical_out_of_stock_product_keeps_its_status_when_none_is_sent(): void
    {
        $published = now()->subDays(2)->startOfSecond();
        $product = $this->product(['status' => 'out_of_stock', 'published_at' => $published]);

        $this->update($product, ['name' => 'Clay jug, again', 'price' => 12])->assertOk();

        $fresh = $product->fresh();
        $this->assertSame(['out_of_stock', 'Clay jug, again'], [$fresh->status, $fresh->name]);
        $this->assertEquals($published, $fresh->published_at);
    }

    public function test_out_of_stock_cannot_be_sent_on_update_but_another_status_can(): void
    {
        $product = $this->product(['status' => 'draft']);

        $this->update($product, ['status' => 'out_of_stock', 'name' => 'Changed'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame(['draft', 'Clay jug'], [$product->fresh()->status, $product->fresh()->name]);

        $historical = $this->product(['status' => 'out_of_stock', 'slug' => 'clay-jug-2', 'sku' => 'JUG-2']);
        $this->update($historical, ['status' => 'active'])->assertOk();
        $this->assertSame('active', $historical->fresh()->status);
        $this->assertEquals(now(), $historical->fresh()->published_at);
    }

    // ---------------------------------------------------------------- archived (DETAIL-02) and suspended (DETAIL-03)

    #[DataProvider('archivedStates')]
    public function test_an_archived_product_cannot_be_updated_by_its_vendor(array $columns): void
    {
        $product = $this->moderated(['status' => 'draft'], $columns);
        $before = $product->fresh()->getAttributes();

        foreach ([['status' => 'active'], ['status' => 'draft', 'name' => 'Changed'], []] as $changes) {
            $this->update($product, $changes)->assertUnprocessable()->assertJsonPath('message', 'This product is archived and cannot be edited.');
        }

        $this->assertSame($before, $product->fresh()->getAttributes(), 'Nothing written.');
        $this->assertFalse($product->fresh()->isSellable());
    }

    public static function archivedStates(): array
    {
        return ['archived' => [['archived_at']], 'archived and suspended' => [['archived_at', 'suspended_at']]];
    }

    public function test_a_restored_product_is_editable_again(): void
    {
        $product = $this->restored(['status' => 'active']);

        $this->update($product, ['name' => 'Back again'])->assertOk();

        $this->assertSame('Back again', $product->fresh()->name);
    }

    public function test_a_suspended_product_can_be_edited_and_published_but_stays_suspended_and_off_sale(): void
    {
        $product = $this->moderated(['status' => 'draft'], ['suspended_at']);
        $suspendedAt = $product->suspended_at;

        $this->update($product, ['status' => 'active', 'name' => 'Clay jug, fixed', 'suspended_at' => null])->assertOk();

        $fresh = $product->fresh();
        $this->assertSame(['active', 'Clay jug, fixed'], [$fresh->status, $fresh->name]);
        $this->assertEquals(now(), $fresh->published_at);
        $this->assertEquals($suspendedAt, $fresh->suspended_at, 'The suspension is the admin\'s alone.');
        $this->assertFalse($fresh->isSellable());
        // The renamed product has a new slug, so its storefront URL is read from the fresh row.
        $this->getJson(route('shop.products.show', $fresh))->assertNotFound();

        // Lifting it shows the active product straight away.
        $this->actingAs($this->admin(), 'admin')->post(route('dashboard.products.unsuspend', $product->id), ['reason' => 'Fixed']);
        $this->assertTrue($product->fresh()->isSellable());
        $this->getJson(route('shop.products.show', $fresh))->assertOk();
    }

    // ---------------------------------------------------------------- ownership and approval

    public function test_another_vendors_product_answers_not_found_whatever_its_state(): void
    {
        $other = Vendor::factory()->approved()->create();
        $plain = $this->product(['vendor_id' => $other->id, 'status' => 'draft']);
        $archived = $this->moderated(['vendor_id' => $other->id, 'status' => 'draft', 'slug' => 'clay-jug-2', 'sku' => 'JUG-2'], ['archived_at']);

        foreach ([$plain, $archived] as $product) {
            $this->update($product, ['status' => 'active', 'name' => 'Taken'])->assertNotFound();
            $this->assertSame(['draft', 'Clay jug', null], [$product->fresh()->status, $product->fresh()->name, $product->fresh()->published_at]);
        }
    }

    public function test_a_vendor_whose_store_is_not_approved_cannot_create_or_publish(): void
    {
        $pending = Vendor::factory()->withProfile('pending')->create();
        $product = $this->product(['vendor_id' => $pending->id, 'status' => 'draft']);

        $this->actingAs($pending, 'vendor')->postJson(route('vendor.dashboard.products.store'), $this->input(['status' => 'active']))->assertForbidden();
        $this->actingAs($pending, 'vendor')->putJson(route('vendor.dashboard.products.update', $product), $this->input(['status' => 'active']))->assertForbidden();

        $this->assertSame(1, Product::count());
        $this->assertSame('draft', $product->fresh()->status);
        $this->assertNull($product->fresh()->published_at);
    }

    // ---------------------------------------------------------------- helpers

    private function input(array $changes): array
    {
        return $changes + ['name' => 'Clay jug', 'price' => 10, 'stock_quantity' => 10];
    }

    private function create(array $changes): Product
    {
        $id = $this->actingAs($this->vendor, 'vendor')
            ->postJson(route('vendor.dashboard.products.store'), $this->input($changes))
            ->assertCreated()->json('product.id');

        return Product::findOrFail($id);
    }

    private function update(Product $product, array $changes): TestResponse
    {
        return $this->actingAs($this->vendor, 'vendor')->putJson(route('vendor.dashboard.products.update', $product), $this->input($changes));
    }

    private function product(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'vendor_id' => $this->vendor->id,
            'name' => 'Clay jug',
            'slug' => 'clay-jug',
            'sku' => 'JUG-1',
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

    /** Archived, then restored through the admin's own route (OPEN-PROD-04B). */
    private function restored(array $attributes): Product
    {
        $product = $this->moderated($attributes, ['archived_at']);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('dashboard.products.restore', $product->id), ['archive_reason' => 'Listed again'])
            ->assertRedirect();

        return $product->fresh();
    }

    private function admin(): Admin
    {
        return Admin::firstOrCreate(['email' => 'publisher@example.com'], ['name' => 'Publisher', 'phone' => '0599000777', 'password' => 'password', 'status' => 'active', 'super_admin' => true]);
    }
}
