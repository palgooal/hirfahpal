<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $response = $this->actingAs($vendor, 'vendor')->postJson(route('vendor.dashboard.products.store'), [
            'category_id' => $category->id,
            'name' => 'Handmade Bowl',
            'price' => 25,
            'sku' => 'HB-001',
            'stock_quantity' => 8,
            'status' => 'active',
            'images' => [
                [
                    'path' => 'products/handmade-bowl.jpg',
                    'is_primary' => true,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('product.vendor_id', $vendor->id)
            ->assertJsonPath('product.slug', 'handmade-bowl')
            ->assertJsonPath('product.images.0.path', 'products/handmade-bowl.jpg');

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
