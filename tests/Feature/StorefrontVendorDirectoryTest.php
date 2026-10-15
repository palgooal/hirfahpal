<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\Customer;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontVendorDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_only_displays_approved_active_stores(): void
    {
        $visible = $this->vendor('Visible store', 'visible-store');
        $pending = $this->vendor('Pending store', 'pending-store', 'pending');
        $rejected = $this->vendor('Rejected store', 'rejected-store', 'rejected');
        $blocked = $this->vendor('Blocked store', 'blocked-store', accountStatus: 'blocked');
        Vendor::factory()->create(['name' => 'No profile']);

        $response = $this->get(route('vendors'))->assertOk()->assertViewIs('pages.vendors');
        $response->assertSee('Visible store')->assertDontSee('Pending store')->assertDontSee('Rejected store')
            ->assertDontSee('Blocked store')->assertDontSee('No profile')
            ->assertSee(route('vendors.profile', ['storeSlug' => 'visible-store']), false)
            ->assertDontSee($visible->email)->assertDontSee($visible->phone);

        foreach ([$pending, $rejected, $blocked] as $vendor) {
            $this->get(route('vendors.profile', ['storeSlug' => $vendor->profile->slug]))->assertNotFound();
        }
        $this->get(route('vendors.profile', ['storeSlug' => 'missing-store']))->assertNotFound();
    }

    public function test_directory_renders_real_location_copy_and_search_attributes(): void
    {
        $governorate = Governorate::query()->create(['name' => 'الخليل', 'slug' => 'hebron', 'status' => 'active']);
        $city = City::query()->create(['governorate_id' => $governorate->id, 'name' => 'دورا', 'slug' => 'dura', 'status' => 'active']);
        $vendor = $this->vendor('مشغل أصالة', 'asala');
        $vendor->profile()->update(['city_id' => $city->id, 'governorate_id' => $governorate->id, 'short_description' => 'وصف المشغل من قاعدة البيانات']);

        $this->get(route('vendors'))->assertOk()->assertSee('data-name="مشغل أصالة"', false)
            ->assertSee('data-city="دورا"', false)->assertSee('وصف المشغل من قاعدة البيانات')
            ->assertSee('م.أ')->assertSee('id="vendorSearch"', false)
            ->assertSee('storefront-vendors', false);

        $vendor->profile()->update(['city_id' => null]);
        $this->get(route('vendors'))->assertOk()->assertSee('data-city="الخليل"', false);
    }

    public function test_directory_counts_only_sellable_products_with_available_stock(): void
    {
        $vendor = $this->vendor('Inventory store', 'inventory');
        $this->product($vendor, 'available-product');
        $this->product($vendor, 'draft-product', ['status' => 'draft']);
        $this->product($vendor, 'empty-product', ['stock_quantity' => 0]);
        $suspended = $this->product($vendor, 'suspended-product');
        $suspended->forceFill(['suspended_at' => now()])->save();
        $archived = $this->product($vendor, 'archived-product');
        $archived->forceFill(['archived_at' => now()])->save();
        $reserved = $this->product($vendor, 'reserved-product', ['stock_quantity' => 2]);
        $order = $this->order();
        $reserved->stockReservations()->create(['order_id' => $order->id, 'quantity' => 2, 'status' => 'reserved']);

        $this->get(route('vendors'))->assertOk()
            ->assertViewHas('vendors', fn ($vendors) => $vendors->first()->available_products_count === 1)
            ->assertSee('<bdi>1</bdi> منتج متاح', false);
    }

    public function test_directory_ratings_only_include_published_vendor_reviews(): void
    {
        $vendor = $this->vendor('Reviewed store', 'reviewed');
        $order = $this->order();
        foreach ([['vendor', 'published', 4], ['vendor', 'published', 5], ['vendor', 'hidden', 1], ['vendor', 'pending', 1], ['product', 'published', 1]] as [$type, $status, $rating]) {
            Review::query()->create(['customer_id' => $order->customer_id, 'order_id' => $order->id,
                'vendor_id' => $vendor->id, 'reviewable_type' => $type, 'status' => $status, 'rating' => $rating]);
        }

        $this->get(route('vendors'))->assertOk()->assertViewHas('vendors', fn ($vendors) => $vendors->first()->published_reviews_count === 2 && (float) $vendors->first()->published_rating === 4.5)
            ->assertSee('تقييم 4.5 من 5');
    }

    public function test_empty_directory_uses_the_existing_visible_empty_state_without_creating_records(): void
    {
        $response = $this->get(route('vendors'))->assertOk()->assertViewHas('vendors', fn ($vendors) => $vendors->isEmpty());
        $this->assertMatchesRegularExpression('/id="vendorEmptyState"\s+class="rounded-/', $response->getContent());
        $this->assertDatabaseCount('vendors', 0);
        $this->assertDatabaseCount('vendor_profiles', 0);
    }

    public function test_directory_exposes_all_real_cards_to_existing_search_and_escapes_store_copy(): void
    {
        for ($index = 0; $index < 10; $index++) {
            $this->vendor('Store '.$index, 'store-'.$index);
        }
        $vendor = $this->vendor('<script>alert(1)</script>', 'escaped-store');
        $vendor->profile()->update(['short_description' => '<img src=x onerror=alert(2)>']);

        $this->get(route('vendors'))->assertOk()->assertViewHas('vendors', fn ($vendors) => $vendors->count() === 11)
            ->assertSee('data-name="Store 9"', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x onerror=alert(2)>', false);
    }

    public function test_visit_store_resolves_its_slug_and_displays_only_its_own_sellable_products(): void
    {
        $vendor = $this->vendor('My store', 'my-store');
        $other = $this->vendor('Other store', 'other-store');
        $category = Category::query()->create(['name' => 'Ceramics', 'slug' => 'ceramics', 'is_active' => true]);
        $product = $this->product($vendor, 'my-product', ['category_id' => $category->id]);
        $this->product($vendor, 'draft-product', ['status' => 'draft']);
        $this->product($other, 'other-product');

        $this->get(route('vendors.profile', ['storeSlug' => 'my-store']))->assertOk()
            ->assertViewHas('vendor', fn ($store) => $store->id === $vendor->id)
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$product->id])
            ->assertSee('My store')->assertSee('my-product')->assertSee('25.00 ILS')
            ->assertSee('data-filter="ceramics"', false)->assertDontSee('other-product')->assertDontSee('draft-product');
    }

    public function test_legacy_store_url_uses_database_data_and_empty_stores_have_no_fake_products(): void
    {
        $vendor = $this->vendor('Actual legacy store', 'dar-al-karma');
        $response = $this->get(route('vendors.show'))->assertOk()->assertSee('Actual legacy store')
            ->assertViewHas('vendor', fn ($store) => $store->id === $vendor->id)
            ->assertViewHas('products', fn ($products) => $products->isEmpty());
        $this->assertMatchesRegularExpression('/data-vendor-empty-state\s+class="rounded-/', $response->getContent());
    }

    private function vendor(string $storeName, string $slug, string $approval = 'approved', string $accountStatus = 'active'): Vendor
    {
        $vendor = Vendor::factory()->withProfile($approval)->create(['status' => $accountStatus]);
        $vendor->profile()->update(['store_name' => $storeName, 'slug' => $slug]);

        return $vendor;
    }

    private function product(Vendor $vendor, string $slug, array $attributes = []): Product
    {
        return Product::query()->create(array_merge(['vendor_id' => $vendor->id, 'name' => $slug, 'slug' => $slug,
            'price' => 25, 'stock_quantity' => 10, 'status' => 'active'], $attributes));
    }

    private function order(): Order
    {
        return Order::query()->create(['number' => 'VENDOR-DIRECTORY-1', 'customer_id' => Customer::factory()->create()->id, 'payment_method' => 'cod']);
    }
}
