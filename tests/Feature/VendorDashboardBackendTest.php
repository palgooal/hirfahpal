<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Models\Media;
use Tests\TestCase;

class VendorDashboardBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_create_product_for_their_store(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::query()->create([
            'name' => 'Ceramics',
            'slug' => 'ceramics',
            'is_active' => true,
        ]);
        // G7-C2-1: product images come from the vendor's own media library.
        $path = "vendors/media/{$vendor->id}/handmade-bowl.jpg";
        Media::create([
            'file_name' => 'handmade-bowl.jpg', 'file_original_name' => 'handmade-bowl.jpg', 'file_path' => $path, 'file_extension' => 'jpg',
            'mime_type' => 'image/jpeg', 'size' => 5, 'file_type' => 'image', 'disk' => 'public', 'uploader_id' => $vendor->id,
        ]);

        $response = $this->actingAs($vendor, 'vendor')->postJson(route('vendor.dashboard.products.store'), [
            'category_id' => $category->id,
            'name' => 'Handmade Bowl',
            'price' => 25,
            'sku' => 'HB-001',
            'stock_quantity' => 8,
            'status' => 'active',
            'images' => [
                [
                    'path' => $path,
                    'is_primary' => true,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('product.vendor_id', $vendor->id)
            ->assertJsonPath('product.slug', 'handmade-bowl')
            ->assertJsonPath('product.images.0.path', $path);

        $this->assertDatabaseHas('products', [
            'vendor_id' => $vendor->id,
            'name' => 'Handmade Bowl',
            'status' => 'active',
        ]);
    }

    public function test_vendor_cannot_update_another_vendor_product(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $otherVendor = Vendor::factory()->approved()->create();
        $product = Product::query()->create([
            'vendor_id' => $otherVendor->id,
            'name' => 'Private Product',
            'slug' => 'private-product',
            'price' => 10,
            'stock_quantity' => 5,
            'status' => 'draft',
        ]);

        $this->actingAs($vendor, 'vendor')->putJson(route('vendor.dashboard.products.update', $product), [
            'name' => 'Changed Product',
            'price' => 15,
            'stock_quantity' => 9,
            'status' => 'active',
        ])->assertNotFound();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Private Product',
            'status' => 'draft',
        ]);
    }

    public function test_vendor_can_upload_a_safe_image_to_the_library_and_attach_it_to_a_product(): void
    {
        // G7-C2-4: images reach a product through the vendor's media library only (no direct product upload).
        Storage::fake('public');
        $vendor = Vendor::factory()->approved()->create();
        $product = Product::query()->create([
            'vendor_id' => $vendor->id,
            'name' => 'Image Product',
            'slug' => 'image-product',
            'price' => 10,
            'stock_quantity' => 5,
            'status' => 'draft',
        ]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL0RAAAAABJRU5ErkJggg==');

        $path = $this->actingAs($vendor, 'vendor')->post(route('vendor.dashboard.products.media.store'), [
            'files' => [UploadedFile::fake()->createWithContent('photo.png', $png)],
        ], ['Accept' => 'application/json'])->assertCreated()->json('images.0.path');

        $this->assertMatchesRegularExpression('#^vendors/media/'.$vendor->id.'/[0-9a-f-]{36}\.png$#', $path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($vendor, 'vendor')->postJson(route('vendor.dashboard.products.images.store', $product), ['path' => $path])
            ->assertCreated()
            ->assertJsonPath('image.path', $path)
            ->assertJsonPath('image.is_primary', true);
    }

    public function test_vendor_library_upload_rejects_a_non_image_file(): void
    {
        Storage::fake('public');
        $vendor = Vendor::factory()->approved()->create();

        // A real file (not UploadedFile::fake(), whose MIME type follows the name), so the content is what is checked.
        $tmp = tempnam(sys_get_temp_dir(), 'g7c24');
        file_put_contents($tmp, '<script>alert(1)</script>');

        $this->actingAs($vendor, 'vendor')->post(route('vendor.dashboard.products.media.store'), [
            'files' => [new UploadedFile($tmp, 'image.png', 'image/png', null, true)],
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('files.0');
        @unlink($tmp);

        $this->assertDatabaseCount('media', 0);
        $this->assertDatabaseCount('product_images', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_vendor_can_accept_their_pending_order(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $vendorOrder = $this->vendorOrderFor($vendor, 'pending');

        $this->actingAs($vendor, 'vendor')
            ->patchJson(route('vendor.dashboard.orders.accept', $vendorOrder))
            ->assertOk()
            ->assertJsonPath('order.status', 'accepted');

        $this->assertDatabaseHas('vendor_orders', [
            'id' => $vendorOrder->id,
            'status' => 'accepted',
        ]);
    }

    public function test_vendor_cannot_access_another_vendor_order(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $otherVendor = Vendor::factory()->approved()->create();
        $vendorOrder = $this->vendorOrderFor($otherVendor, 'pending');

        $this->actingAs($vendor, 'vendor')
            ->getJson(route('vendor.dashboard.orders.show', $vendorOrder))
            ->assertNotFound();
    }

    private function vendorOrderFor(Vendor $vendor, string $status): VendorOrder
    {
        $customer = Customer::factory()->create();
        $order = Order::query()->create([
            'number' => 'HF-'.fake()->unique()->numerify('######'),
            'customer_id' => $customer->id,
            'status' => 'pending',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'subtotal' => 20,
            'delivery_total' => 0,
            'grand_total' => 20,
        ]);

        return VendorOrder::query()->create([
            'number' => 'VO-'.fake()->unique()->numerify('######'),
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => $status,
            'subtotal' => 20,
            'delivery_fee' => 0,
            'commission_amount' => 0,
            'total' => 20,
        ]);
    }
}
