<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;

trait InteractsWithStorefront
{
    protected function storefrontProduct(string $slug = 'test-product', ?Vendor $vendor = null, array $attributes = []): Product
    {
        $vendor ??= Vendor::factory()->approved()->create();
        $category = Category::query()->firstOrCreate(['slug' => 'test-category'], ['name' => 'Test category', 'is_active' => true]);

        return Product::query()->create(array_merge(['vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => $slug, 'slug' => $slug, 'price' => 25, 'stock_quantity' => 10, 'status' => 'active'], $attributes));
    }

    protected function storefrontAddress(Customer $customer, array $attributes = [])
    {
        return $customer->addresses()->create(array_merge(['label' => 'Home', 'recipient_name' => $customer->name,
            'phone' => $customer->phone, 'address_line' => 'Real street'], $attributes));
    }

    protected function storefrontOrder(Customer $customer, string $number = 'REAL-ORDER'): Order
    {
        return Order::query()->create(['number' => $number, 'customer_id' => $customer->id,
            'payment_method' => 'cod', 'grand_total' => 50]);
    }
}
