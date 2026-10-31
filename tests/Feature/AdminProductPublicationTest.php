<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\Vendor;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * VENDOR-PRODUCT-PUBLISH-BE-01C (OPEN-PROD-22, OPEN-PROD-23, with OPEN-PROD-04B and DETAIL-04): the admin form sets
 * published_at with the vendor's rule, read from the locked row. Publishing (any other status to active) sets a fresh
 * date, staying active keeps it, leaving active clears it, and between unpublished statuses the stored value stays
 * (the date a restore kept included). A restored draft republished by the admin or the vendor gets a fresh date.
 * Suspension and archiving stay with the moderation actions, and the storefront rules are unchanged.
 */
class AdminProductPublicationTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Category $category;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->vendor = Vendor::factory()->approved()->create();
        $this->category = Category::create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $this->admin = Admin::create(['name' => 'Publisher', 'email' => 'publisher@example.com', 'phone' => '0599000999', 'password' => 'password', 'status' => 'active', 'super_admin' => true]);
        $this->travelTo(Carbon::parse('2026-10-31 12:00:00'));
    }

    // ---------------------------------------------------------------- create

    public function test_a_product_created_active_gets_the_current_date_and_one_created_as_a_draft_none(): void
    {
        foreach (['active' => now(), 'draft' => null, 'inactive' => null] as $status => $expected) {
            $values = $this->formValues($this->page(route('dashboard.products.create')));
            $this->actingAs($this->admin, 'admin')->post(route('dashboard.products.store'), [
                'vendor_id' => $this->vendor->id, 'category_id' => $this->category->id, 'name' => "Jug {$status}", 'price' => '10', 'stock_quantity' => '4', 'status' => $status,
            ] + $values)->assertRedirect(route('dashboard.products.index'));

            $product = Product::query()->where('name', "Jug {$status}")->sole();
            $this->assertSame($status, $product->status);
            $this->assertEquals($expected, $product->published_at, $status);
        }
    }

    // ---------------------------------------------------------------- update transitions

    #[DataProvider('transitions')]
    public function test_the_admin_form_follows_the_publication_rule(string $from, ?string $storedAgo, string $to, string $expected): void
    {
        $stored = $storedAgo === null ? null : now()->sub($storedAgo)->startOfSecond();
        $product = $this->product(['status' => $from, 'published_at' => $stored]);
        $this->travel(5)->minutes();

        $this->save($product, ['status' => $to])->assertRedirect(route('dashboard.products.index'));

        $fresh = $product->fresh();
        $this->assertSame($to, $fresh->status);
        $this->assertEquals(['now' => now(), 'kept' => $stored, 'none' => null][$expected], $fresh->published_at);
    }

    public static function transitions(): array
    {
        return [
            'draft to active: fresh' => ['draft', null, 'active', 'now'],
            'inactive to active: fresh' => ['inactive', null, 'active', 'now'],
            'out of stock to active: fresh' => ['out_of_stock', null, 'active', 'now'],
            'active to active: kept' => ['active', '3 days', 'active', 'kept'],
            'active to draft: cleared' => ['active', '3 days', 'draft', 'none'],
            'active to inactive: cleared' => ['active', '3 days', 'inactive', 'none'],
            'active to out of stock: cleared' => ['active', '3 days', 'out_of_stock', 'none'],
            'draft to inactive: still none' => ['draft', null, 'inactive', 'none'],
        ];
    }

    public function test_saving_an_active_product_untouched_or_with_another_field_changed_keeps_its_date(): void
    {
        $published = now()->subDays(3)->startOfSecond();
        $product = $this->product(['status' => 'active', 'published_at' => $published]);
        $this->travel(5)->minutes();

        $this->save($product)->assertRedirect(route('dashboard.products.index'));
        $this->assertEquals($published, $product->fresh()->published_at, 'The form submitted as rendered.');

        $this->save($product, ['name' => 'Clay jug, glazed', 'price' => '14'])->assertRedirect(route('dashboard.products.index'));
        $this->assertSame(['active', 'Clay jug, glazed'], [$product->fresh()->status, $product->fresh()->name]);
        $this->assertEquals($published, $product->fresh()->published_at);
    }

    // ---------------------------------------------------------------- restored drafts

    public function test_a_restored_draft_keeps_its_old_date_until_the_admin_republishes_it_with_a_fresh_one(): void
    {
        $published = now()->subDays(10)->startOfSecond();
        $product = $this->restored(['status' => 'active', 'published_at' => $published]);

        $this->assertSame('draft', $product->status);
        $this->assertEquals($published, $product->published_at, 'Kept at restore (OPEN-PROD-04B).');
        $this->assertFalse($product->isSellable());

        // Saved by the admin as a draft: still unpublished, the kept date stays.
        $this->save($product, ['name' => 'Clay jug, restored'])->assertRedirect(route('dashboard.products.index'));
        $this->assertSame('draft', $product->fresh()->status);
        $this->assertEquals($published, $product->fresh()->published_at);

        $this->travel(5)->minutes();
        $this->save($product, ['status' => 'active'])->assertRedirect(route('dashboard.products.index'));

        $fresh = $product->fresh();
        $this->assertEquals(now(), $fresh->published_at, 'A fresh date, never the restored one.');
        $this->assertTrue($fresh->isSellable());
        $this->getJson(route('shop.products.show', $fresh))->assertOk();
    }

    public function test_a_restored_draft_republished_by_the_vendor_gets_a_fresh_date_too(): void
    {
        $published = now()->subDays(10)->startOfSecond();
        $product = $this->restored(['status' => 'active', 'published_at' => $published]);
        $this->travel(5)->minutes();

        $this->actingAs($this->vendor, 'vendor')->putJson(route('vendor.dashboard.products.update', $product), [
            'name' => 'Clay jug', 'price' => 10, 'stock_quantity' => 10, 'status' => 'active',
        ])->assertOk();

        $this->assertEquals(now(), $product->fresh()->published_at);
    }

    // ---------------------------------------------------------------- the locked row, not the bound one

    public function test_the_transition_is_read_from_the_locked_row_not_the_product_bound_before_it(): void
    {
        // The form was opened while the product was active since three days ago. Between the route binding and the
        // lock, the vendor unpublishes it (simulated on the first read of the row: the binding). The admin then saves
        // it as active: that is a new publication, so the date is fresh, not the stale one the bound model still has.
        $stale = now()->subDays(3)->startOfSecond();
        $product = $this->product(['status' => 'active', 'published_at' => $stale]);
        $form = $this->formValues($this->page(route('dashboard.products.edit', $product)));
        $this->travel(5)->minutes();

        $this->onFirstRead($product, ['status' => 'draft', 'published_at' => null]);
        $this->actingAs($this->admin, 'admin')->put(route('dashboard.products.update', $product), $form)->assertRedirect(route('dashboard.products.index'));

        $this->assertSame('active', $product->fresh()->status);
        $this->assertEquals(now(), $product->fresh()->published_at, 'The stale date is not reused.');
    }

    public function test_a_concurrent_publication_is_not_overwritten_by_a_date_from_the_bound_row(): void
    {
        // The other way round: bound as a draft, published by the vendor before the lock, saved active by the admin.
        $product = $this->product(['status' => 'draft']);
        $form = $this->formValues($this->page(route('dashboard.products.edit', $product)));
        $vendorDate = now()->startOfSecond();
        $this->travel(5)->minutes();

        $this->onFirstRead($product, ['status' => 'active', 'published_at' => $vendorDate]);
        $this->actingAs($this->admin, 'admin')->put(route('dashboard.products.update', $product), ['status' => 'active'] + $form)->assertRedirect(route('dashboard.products.index'));

        $this->assertEquals($vendorDate, $product->fresh()->published_at, 'Active to active on the locked row: the date is kept.');
    }

    // ---------------------------------------------------------------- moderation and storefront unchanged

    public function test_publishing_from_the_form_does_not_lift_a_suspension_or_an_archive(): void
    {
        foreach (['suspended_at', 'archived_at'] as $index => $column) {
            $product = $this->product(['status' => 'draft', 'slug' => "jug-{$index}", 'sku' => "JUG-{$index}"]);
            $product->forceFill([$column => now()->subHour()])->save();
            $moderatedAt = $product->fresh()->{$column};

            $this->save($product, ['status' => 'active', $column => ''])->assertRedirect(route('dashboard.products.index'));

            $fresh = $product->fresh();
            $this->assertSame('active', $fresh->status);
            $this->assertEquals(now(), $fresh->published_at);
            $this->assertEquals($moderatedAt, $fresh->{$column}, "{$column} is the moderation actions' alone.");
            $this->assertFalse($fresh->isSellable());
            $this->getJson(route('shop.products.show', $fresh))->assertNotFound();
        }
    }

    public function test_only_an_active_product_is_on_the_storefront(): void
    {
        $product = $this->product(['status' => 'draft']);
        $this->getJson(route('shop.products.show', $product))->assertNotFound();

        $this->save($product, ['status' => 'active']);
        $this->getJson(route('shop.products.show', $product))->assertOk();

        $this->save($product, ['status' => 'inactive']);
        $this->getJson(route('shop.products.show', $product))->assertNotFound();
    }

    // ---------------------------------------------------------------- helpers

    private function product(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Clay jug',
            'slug' => 'clay-jug',
            'sku' => 'JUG-1',
            'price' => 10,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);
    }

    /** The edit form as rendered, with these fields changed, submitted by the admin. */
    private function save(Product $product, array $changes = []): TestResponse
    {
        $form = $this->formValues($this->page(route('dashboard.products.edit', $product)));

        return $this->actingAs($this->admin, 'admin')->put(route('dashboard.products.update', $product), $changes + $form);
    }

    /** Archived, then restored through the admin's own route (OPEN-PROD-04B). */
    private function restored(array $attributes): Product
    {
        $product = $this->product($attributes);
        $product->forceFill(['archived_at' => now()->subHour()])->save();

        $this->actingAs($this->admin, 'admin')
            ->post(route('dashboard.products.restore', $product->id), ['archive_reason' => 'Listed again'])
            ->assertRedirect();

        return $product->fresh();
    }

    /** Right after the next read of this product's row (the route binding), another writer changes it. */
    private function onFirstRead(Product $product, array $columns): void
    {
        $done = false;

        Product::retrieved(function (Product $read) use ($product, $columns, &$done): void {
            if ($done || $read->getKey() !== $product->getKey()) {
                return;
            }

            $done = true;
            DB::table('products')->where('id', $product->id)->update($columns);
        });
    }

    private function page(string $url): string
    {
        return $this->actingAs($this->admin, 'admin')->get($url)->assertOk()->getContent();
    }

    /**
     * What the product form would submit as rendered (as AdminProductFormTest: a browser's successful controls).
     *
     * @return array<string, string>
     */
    private function formValues(string $html): array
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        $xpath = new DOMXPath($document);
        $values = [];

        foreach ($xpath->query('//form[@novalidate]//input') as $input) {
            if ($input->getAttribute('type') === 'checkbox' && ! $input->hasAttribute('checked')) {
                continue;
            }
            $values[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        foreach ($xpath->query('//form[@novalidate]//select') as $select) {
            $selected = $xpath->query('.//option[@selected]', $select)->item(0) ?? $xpath->query('.//option', $select)->item(0);
            $values[$select->getAttribute('name')] = $selected?->getAttribute('value') ?? '';
        }
        foreach ($xpath->query('//form[@novalidate]//textarea') as $textarea) {
            $values[$textarea->getAttribute('name')] = $textarea->textContent;
        }

        return $values;
    }
}
