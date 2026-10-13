<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerFavorite;
use App\Models\Governorate;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_update_account_details(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'phone' => '0591111111',
        ]);

        $this->actingAs($customer, 'customer')
            ->patchJson(route('customer.account-details.update'), [
                'name' => 'New Name',
                'email' => 'new@example.com',
                'phone' => '0592222222',
            ])
            ->assertOk()
            ->assertJsonPath('customer.name', 'New Name');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'email' => 'new@example.com',
            'phone' => '0592222222',
        ]);
    }

    public function test_customer_can_update_password_with_current_password(): void
    {
        $customer = Customer::factory()->create([
            'password' => Hash::make('Old-password-1'),
        ]);

        $this->actingAs($customer, 'customer')
            ->patchJson(route('customer.account-details.password.update'), [
                'current_password' => 'Old-password-1',
                'password' => 'New-password-1',
                'password_confirmation' => 'New-password-1',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('New-password-1', $customer->fresh()->password));
    }

    public function test_customer_can_create_update_and_delete_own_address(): void
    {
        $customer = Customer::factory()->create();
        [$governorate, $city] = $this->location();

        $create = $this->actingAs($customer, 'customer')
            ->postJson(route('customer.addresses.store'), [
                'governorate_id' => $governorate->id,
                'city_id' => $city->id,
                'label' => 'home',
                'recipient_name' => 'Salma',
                'phone' => '0593333333',
                'address_line' => 'Main street',
                'is_default' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('label', 'home');

        $addressId = $create->json('id');

        $this->actingAs($customer, 'customer')
            ->patchJson(route('customer.addresses.update', $addressId), [
                'governorate_id' => $governorate->id,
                'city_id' => $city->id,
                'label' => 'work',
                'recipient_name' => 'Salma',
                'phone' => '0594444444',
                'address_line' => 'Second street',
            ])
            ->assertOk()
            ->assertJsonPath('label', 'work');

        $this->actingAs($customer, 'customer')
            ->deleteJson(route('customer.addresses.destroy', $addressId))
            ->assertOk();

        $this->assertDatabaseMissing('customer_addresses', ['id' => $addressId]);
    }

    public function test_customer_cannot_mutate_another_customers_address(): void
    {
        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        [$governorate, $city] = $this->location();

        $address = CustomerAddress::query()->create([
            'customer_id' => $owner->id,
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'label' => 'home',
            'recipient_name' => 'Owner',
            'phone' => '0595555555',
            'address_line' => 'Owner street',
        ]);

        $this->actingAs($other, 'customer')
            ->deleteJson(route('customer.addresses.destroy', $address))
            ->assertForbidden();
    }

    public function test_address_city_must_belong_to_selected_governorate(): void
    {
        $customer = Customer::factory()->create();
        [$governorate] = $this->location('ramallah');
        [, $foreignCity] = $this->location('nablus');

        $this->actingAs($customer, 'customer')
            ->postJson(route('customer.addresses.store'), [
                'governorate_id' => $governorate->id,
                'city_id' => $foreignCity->id,
                'label' => 'home',
                'recipient_name' => 'Salma',
                'phone' => '0596666666',
                'address_line' => 'Main street',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('city_id');
    }

    public function test_checkout_rejects_address_that_belongs_to_another_customer(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        $address = CustomerAddress::query()->create([
            'customer_id' => $other->id,
            'label' => 'home',
            'recipient_name' => 'Other',
            'phone' => '0597777777',
            'address_line' => 'Other street',
        ]);

        $product = $this->sellableProduct();
        $cart = Cart::query()->create(['customer_id' => $customer->id, 'status' => 'active']);
        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $product->price,
        ]);

        $this->actingAs($customer, 'customer')
            ->postJson(route('customer.checkout.store'), [
                'customer_address_id' => $address->id,
                'payment_method' => 'cod',
            ])
            ->assertForbidden();
    }

    public function test_customer_can_add_and_remove_sellable_product_favorite(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->sellableProduct();

        $create = $this->actingAs($customer, 'customer')
            ->postJson(route('customer.favorites.store'), ['product_id' => $product->id])
            ->assertCreated()
            ->assertJsonPath('product_id', $product->id);

        $favoriteId = $create->json('id');

        $this->actingAs($customer, 'customer')
            ->deleteJson(route('customer.favorites.destroy', $favoriteId))
            ->assertOk();

        $this->assertDatabaseMissing('customer_favorites', ['id' => $favoriteId]);
    }

    public function test_customer_cannot_remove_another_customers_favorite(): void
    {
        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        $favorite = CustomerFavorite::query()->create([
            'customer_id' => $owner->id,
            'product_id' => $this->sellableProduct()->id,
        ]);

        $this->actingAs($other, 'customer')
            ->deleteJson(route('customer.favorites.destroy', $favorite))
            ->assertForbidden();
    }

    private function location(string $slug = 'ramallah'): array
    {
        $governorate = Governorate::query()->create([
            'name' => Str::headline($slug),
            'slug' => $slug.'-'.Str::random(6),
            'status' => 'active',
        ]);

        $city = City::query()->create([
            'governorate_id' => $governorate->id,
            'name' => Str::headline($slug).' City',
            'slug' => $slug.'-city-'.Str::random(6),
            'status' => 'active',
        ]);

        return [$governorate, $city];
    }

    private function sellableProduct(): Product
    {
        $vendor = Vendor::factory()->approved()->create(['status' => 'active']);

        return Product::query()->create([
            'vendor_id' => $vendor->id,
            'name' => 'Blue Pitcher',
            'slug' => 'blue-pitcher-'.Str::random(6),
            'price' => 145,
            'stock_quantity' => 5,
            'status' => 'active',
            'published_at' => now(),
        ]);
    }
}
