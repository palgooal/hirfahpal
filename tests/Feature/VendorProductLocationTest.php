<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorProductLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_save_a_product_with_a_governorate_belonging_to_its_city(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::query()->create(['name' => 'Crafts', 'slug' => 'crafts', 'is_active' => true]);
        $city = City::query()->create(['name' => 'Gaza', 'slug' => 'gaza']);
        $governorate = Governorate::query()->create(['city_id' => $city->id, 'name' => 'Al Rimal', 'slug' => 'al-rimal']);

        $this->actingAs($vendor, 'vendor')
            ->postJson(route('vendor.dashboard.products.store'), $this->productData($category, $city, $governorate))
            ->assertCreated()
            ->assertJsonPath('product.city_id', $city->id)
            ->assertJsonPath('product.governorate_id', $governorate->id);

        $this->assertDatabaseHas('products', ['city_id' => $city->id, 'governorate_id' => $governorate->id]);
    }

    public function test_vendor_cannot_save_a_governorate_from_a_different_city(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::query()->create(['name' => 'Crafts', 'slug' => 'crafts', 'is_active' => true]);
        $city = City::query()->create(['name' => 'Gaza', 'slug' => 'gaza']);
        $otherCity = City::query()->create(['name' => 'Nablus', 'slug' => 'nablus']);
        $governorate = Governorate::query()->create(['city_id' => $otherCity->id, 'name' => 'Old City', 'slug' => 'old-city']);

        $this->actingAs($vendor, 'vendor')
            ->postJson(route('vendor.dashboard.products.store'), $this->productData($category, $city, $governorate))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('governorate_id');

        $this->assertDatabaseCount('products', 0);
    }

    /** @return array<string, int|string> */
    private function productData(Category $category, City $city, Governorate $governorate): array
    {
        return [
            'category_id' => $category->id,
            'city_id' => $city->id,
            'governorate_id' => $governorate->id,
            'name' => 'Clay bowl',
            'price' => 25,
            'stock_quantity' => 4,
        ];
    }
}
