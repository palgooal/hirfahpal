<?php

namespace Tests\Feature;

use App\Http\Controllers\VendorDashboard\ProductImageController;
use App\Models\Admin;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Models\Media;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * VENDOR-PRODUCT-PUBLISH-UI-01C (DETAIL-02, DETAIL-03): the vendor's product details answer adds a top-level, read-only
 * moderation object, {archived: bool, suspended: bool}, for the editor. Only the owner gets it; the product object and
 * its serialization are unchanged (suspended_at and archived_at stay hidden), no date, reason or admin is exposed, the
 * list is unchanged, and the flags change nothing about what the vendor may write.
 */
class VendorProductModerationFlagsTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->approved()->create();
        $this->admin = Admin::create(['name' => 'Moderator', 'email' => 'moderator@example.com', 'phone' => '0599000555', 'password' => 'password', 'status' => 'active', 'super_admin' => true]);
    }

    public static function states(): array
    {
        return [
            'neither' => [[], false, false],
            'suspended' => [['suspend'], false, true],
            'archived' => [['archive'], true, false],
            'both' => [['suspend', 'archive'], true, true],
        ];
    }

    // ---------------------------------------------------------------- the flags

    #[DataProvider('states')]
    public function test_the_owner_gets_the_two_flags_as_booleans(array $actions, bool $archived, bool $suspended): void
    {
        $product = $this->moderated($actions);

        $response = $this->show($product)->assertOk();

        $this->assertSame(['archived' => $archived, 'suspended' => $suspended], $response->json('moderation'), 'Exactly two keys, real booleans.');
        $this->assertSame(['product', 'available_stock', 'reserved_stock', 'moderation'], array_keys($response->json()));
        $this->assertStringContainsString('"moderation":{"archived":'.json_encode($archived).',"suspended":'.json_encode($suspended).'}', $response->getContent());
    }

    public function test_no_date_reason_or_admin_is_exposed(): void
    {
        $product = $this->moderated(['suspend', 'archive']);
        $stored = $product->fresh();

        $response = $this->show($product)->assertOk();
        $content = $response->getContent();

        foreach (['Counterfeit listing', 'Duplicate of another product', 'Moderator', 'moderator@example.com', 'suspended_at', 'archived_at', 'reason', 'admin'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $content, $hidden);
        }

        // The moderation save also refreshes the product's own updated_at (an existing field, as before), so the last
        // moderation moment legitimately appears there; everywhere else neither date appears.
        $this->assertEquals($stored->archived_at, $stored->updated_at);
        $rest = $response->json();
        unset($rest['product']['updated_at']);
        $rest = json_encode($rest);
        foreach ([$stored->suspended_at, $stored->archived_at] as $date) {
            $this->assertStringNotContainsString($date->toJSON(), $rest);
            $this->assertStringNotContainsString($date->format('Y-m-d H:i:s'), $rest);
        }
        $this->assertStringNotContainsString($stored->suspended_at->toJSON(), $content, 'The suspension date is nowhere.');
    }

    // ---------------------------------------------------------------- the product object is unchanged

    #[DataProvider('states')]
    public function test_the_product_object_is_serialized_exactly_as_before(array $actions): void
    {
        $product = $this->moderated($actions);

        $response = $this->show($product)->assertOk();

        $expected = json_decode(json_encode(Product::findOrFail($product->id)->load(['category', 'images', 'stockReservations'])), true);
        $this->assertSame($expected, $response->json('product'));
        $this->assertArrayNotHasKey('archived_at', $response->json('product'));
        $this->assertArrayNotHasKey('suspended_at', $response->json('product'));
        $this->assertArrayNotHasKey('moderation', $response->json('product'));
        $this->assertSame([$product->fresh()->availableStockQuantity(), $product->fresh()->reservedStockQuantity()], [$response->json('available_stock'), $response->json('reserved_stock')]);
    }

    public function test_the_list_is_unchanged(): void
    {
        $this->moderated(['suspend', 'archive']);

        $response = $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.products.index'))->assertOk();

        $this->assertSame(['products', 'filters'], array_keys($response->json()));
        foreach ($response->json('products.data') as $row) {
            foreach (['moderation', 'archived_at', 'suspended_at'] as $key) {
                $this->assertArrayNotHasKey($key, $row, $key);
            }
        }
    }

    // ---------------------------------------------------------------- who gets it

    public function test_another_vendor_gets_a_404_without_the_flags(): void
    {
        $product = $this->moderated(['suspend', 'archive']);
        $intruder = Vendor::factory()->approved()->create();

        $response = $this->actingAs($intruder, 'vendor')->getJson(route('vendor.dashboard.products.show', $product))->assertNotFound();

        $this->assertNull($response->json('moderation'));
        $this->assertStringNotContainsString('moderation', $response->getContent());
        $this->assertStringNotContainsString('"archived"', $response->getContent());
    }

    public function test_a_guest_and_a_vendor_not_yet_approved_are_still_blocked(): void
    {
        $product = $this->moderated(['archive']);

        $this->getJson(route('vendor.dashboard.products.show', $product))->assertUnauthorized();
        $this->get(route('vendor.dashboard.products.show', $product))->assertRedirect(route('vendor.login'));

        $pending = Vendor::factory()->withProfile('pending')->create();
        $own = Product::create(['vendor_id' => $pending->id, 'name' => 'Pending jug', 'slug' => 'pending-jug', 'price' => 10, 'stock_quantity' => 2, 'status' => 'draft']);
        $own->forceFill(['archived_at' => now()])->save();

        $refused = $this->actingAs($pending, 'vendor')->getJson(route('vendor.dashboard.products.show', $own))->assertForbidden();
        $this->assertStringNotContainsString('moderation', $refused->getContent());
        $this->actingAs($pending, 'vendor')->get(route('vendor.dashboard.products.show', $own))->assertRedirect(route('vendor.approval-status'));
    }

    // ---------------------------------------------------------------- read-only, and nothing else changes

    public function test_the_flags_cannot_be_written_and_the_existing_protections_still_apply(): void
    {
        Storage::fake('public');
        $suspended = $this->moderated(['suspend']);
        $archived = $this->moderated(['archive'], 'archived-jug');
        $flagsOff = ['moderation' => ['archived' => false, 'suspended' => false], 'suspended_at' => null, 'archived_at' => null];

        // Suspended: editable (DETAIL-03), and stays suspended whatever is sent.
        $this->update($suspended, $flagsOff + ['status' => 'active'])->assertOk();
        $this->assertTrue($suspended->fresh()->isSuspended());
        $this->assertSame(['archived' => false, 'suspended' => true], $this->show($suspended)->json('moderation'));

        // Archived: the update and an image change are refused (DETAIL-02), and it stays archived.
        $this->update($archived, $flagsOff)->assertUnprocessable()->assertJsonPath('message', 'This product is archived and cannot be edited.');
        $path = "vendors/media/{$this->vendor->id}/jug.png";
        Storage::disk('public')->put($path, 'image');
        Media::create(['file_name' => 'jug.png', 'file_original_name' => 'jug.png', 'file_path' => $path, 'file_extension' => 'png', 'mime_type' => 'image/png', 'size' => 5, 'file_type' => 'image', 'disk' => 'public', 'uploader_id' => $this->vendor->id]);
        $this->actingAs($this->vendor, 'vendor')->postJson(route('vendor.dashboard.products.images.store', $archived), ['path' => $path])
            ->assertUnprocessable()->assertJsonPath('message', ProductImageController::ARCHIVED_MESSAGE);
        $this->assertTrue($archived->fresh()->isArchived());
        $this->assertSame(0, $archived->images()->count());
        $this->assertSame(['archived' => true, 'suspended' => false], $this->show($archived)->json('moderation'));
    }

    // ---------------------------------------------------------------- helpers

    private function show(Product $product)
    {
        return $this->actingAs($this->vendor, 'vendor')->getJson(route('vendor.dashboard.products.show', $product));
    }

    private function update(Product $product, array $changes)
    {
        return $this->actingAs($this->vendor, 'vendor')->putJson(route('vendor.dashboard.products.update', $product), $changes + ['name' => 'Clay jug', 'price' => 10, 'stock_quantity' => 2]);
    }

    /** The vendor's product, suspended and / or archived through the admin's own moderation routes (with reasons). */
    private function moderated(array $actions, string $slug = 'clay-jug'): Product
    {
        $product = Product::create(['vendor_id' => $this->vendor->id, 'name' => 'Clay jug', 'slug' => $slug, 'price' => 10, 'stock_quantity' => 2, 'status' => 'active', 'published_at' => now()]);

        foreach ($actions as $index => $action) {
            // Each moderation at its own time, so its date can be told apart from the product's own dates.
            $this->travel(37 + $index)->minutes();
            [$route, $field, $reason] = $action === 'suspend'
                ? ['dashboard.products.suspend', 'reason', 'Counterfeit listing']
                : ['dashboard.products.archive', 'archive_reason', 'Duplicate of another product'];
            $this->actingAs($this->admin, 'admin')->post(route($route, $product->id), [$field => $reason])->assertRedirect();
        }

        return $product->fresh();
    }
}
