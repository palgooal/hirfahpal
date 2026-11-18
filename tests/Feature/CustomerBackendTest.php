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

    // ---------------------------------------------------------------- the address location (H1-F, Governorate → City)

    public function test_a_new_address_location_follows_the_hierarchy(): void
    {
        $customer = Customer::factory()->create();
        [$ramallah, $ramallahCity] = $this->location('ramallah');
        [, $nablusCity] = $this->location('nablus');

        $this->storeAddress($customer, ['governorate_id' => $ramallah->id, 'city_id' => $ramallahCity->id])->assertCreated()
            ->assertJsonPath('governorate_id', $ramallah->id)->assertJsonPath('city_id', $ramallahCity->id);
        $this->storeAddress($customer, ['governorate_id' => $ramallah->id])->assertCreated()->assertJsonPath('city_id', null);
        $this->storeAddress($customer, [])->assertCreated()->assertJsonPath('governorate_id', null);

        $this->storeAddress($customer, ['city_id' => $ramallahCity->id])->assertUnprocessable()
            ->assertJsonValidationErrors('governorate_id')->assertJsonMissingValidationErrors('city_id');
        $this->storeAddress($customer, ['governorate_id' => $ramallah->id, 'city_id' => $nablusCity->id])->assertUnprocessable()
            ->assertJsonValidationErrors(['city_id' => 'The selected city does not belong to the selected governorate.'])
            ->assertJsonMissingValidationErrors('governorate_id');

        $this->assertSame(3, $customer->addresses()->count(), 'Nothing refused is saved.');
    }

    public function test_an_address_update_keeps_omitted_and_clears_null_location_fields(): void
    {
        $customer = Customer::factory()->create();
        [$ramallah, $ramallahCity] = $this->location('ramallah');
        $address = $this->addressFor($customer, $ramallah->id, $ramallahCity->id);

        $this->patchAddress($customer, $address, ['label' => 'work'])->assertOk()
            ->assertJsonPath('governorate_id', $ramallah->id)->assertJsonPath('city_id', $ramallahCity->id);
        $this->assertAddressLocation($address, $ramallah->id, $ramallahCity->id);

        $this->patchAddress($customer, $address, ['governorate_id' => null, 'city_id' => null])->assertOk()
            ->assertJsonPath('governorate_id', null)->assertJsonPath('city_id', null);
        $this->assertAddressLocation($address, null, null);
        $this->assertSame(['work', 'Salma', '0591111111', 'Main street'], [$address->fresh()->label, $address->fresh()->recipient_name, $address->fresh()->phone, $address->fresh()->address_line]);
    }

    public function test_an_address_update_changing_one_field_checks_the_stored_other(): void
    {
        $customer = Customer::factory()->create();
        [$ramallah, $ramallahCity] = $this->location('ramallah');
        [$nablus, $nablusCity] = $this->location('nablus');
        $bireh = City::query()->create(['governorate_id' => $ramallah->id, 'name' => 'Al-Bireh', 'slug' => 'al-bireh']);
        $address = $this->addressFor($customer, $ramallah->id, $ramallahCity->id);

        // The governorate alone against the stored city, the city alone against the stored governorate: refused.
        $this->patchAddress($customer, $address, ['governorate_id' => $nablus->id])->assertUnprocessable()
            ->assertJsonValidationErrors('city_id')->assertJsonMissingValidationErrors('governorate_id');
        $this->patchAddress($customer, $address, ['city_id' => $nablusCity->id])->assertUnprocessable()->assertJsonValidationErrors('city_id');
        $this->assertAddressLocation($address, $ramallah->id, $ramallahCity->id);

        $this->patchAddress($customer, $address, ['city_id' => $bireh->id])->assertOk();
        $this->assertAddressLocation($address, $ramallah->id, $bireh->id);

        // The city cleared, the governorate kept; then the governorate can change alone.
        $this->patchAddress($customer, $address, ['city_id' => null])->assertOk();
        $this->assertAddressLocation($address, $ramallah->id, null);
        $this->patchAddress($customer, $address, ['governorate_id' => $nablus->id])->assertOk();
        $this->assertAddressLocation($address, $nablus->id, null);

        // The governorate cannot be cleared under a city.
        $this->patchAddress($customer, $address, ['city_id' => $nablusCity->id])->assertOk();
        $this->patchAddress($customer, $address, ['governorate_id' => null])->assertUnprocessable()
            ->assertJsonValidationErrors('governorate_id')->assertJsonMissingValidationErrors('city_id');
        $this->assertAddressLocation($address, $nablus->id, $nablusCity->id);
    }

    public function test_an_unchanged_legacy_or_inactive_address_location_survives_unrelated_updates(): void
    {
        $customer = Customer::factory()->create();
        [, $ramallahCity] = $this->location('ramallah');
        [$nablus] = $this->location('nablus');
        $closedGovernorate = Governorate::query()->create(['name' => 'Closed', 'slug' => 'closed', 'status' => 'inactive']);
        $closedCity = City::query()->create(['governorate_id' => $closedGovernorate->id, 'name' => 'Closed City', 'slug' => 'closed-city', 'status' => 'inactive']);
        $orphan = City::query()->create(['governorate_id' => null, 'name' => 'Orphan', 'slug' => 'orphan']);

        foreach ([
            'mismatched' => [$nablus->id, $ramallahCity->id],
            'inactive' => [$closedGovernorate->id, $closedCity->id],
            'city without governorate' => [null, $orphan->id],
        ] as $label => [$governorateId, $cityId]) {
            $address = $this->addressFor($customer, $governorateId, $cityId);

            $this->patchAddress($customer, $address, ['label' => 'work'])->assertOk();
            $this->patchAddress($customer, $address, ['label' => 'office', 'governorate_id' => $governorateId, 'city_id' => $cityId])->assertOk();

            $this->assertAddressLocation($address, $governorateId, $cityId, $label);
            $this->assertSame('office', $address->fresh()->label, $label);
        }
    }

    public function test_newly_selected_inactive_unknown_or_malformed_address_locations_are_refused(): void
    {
        $customer = Customer::factory()->create();
        [$ramallah, $ramallahCity] = $this->location('ramallah');
        $closedGovernorate = Governorate::query()->create(['name' => 'Closed', 'slug' => 'closed', 'status' => 'inactive']);
        $closedCity = City::query()->create(['governorate_id' => $ramallah->id, 'name' => 'Closed City', 'slug' => 'closed-city', 'status' => 'inactive']);
        $address = $this->addressFor($customer, $ramallah->id, $ramallahCity->id);

        $this->storeAddress($customer, ['governorate_id' => $closedGovernorate->id])->assertUnprocessable()->assertJsonValidationErrors('governorate_id');
        $this->storeAddress($customer, ['governorate_id' => $ramallah->id, 'city_id' => $closedCity->id])->assertUnprocessable()
            ->assertJsonValidationErrors('city_id')->assertJsonMissingValidationErrors('governorate_id');
        $this->patchAddress($customer, $address, ['governorate_id' => $closedGovernorate->id, 'city_id' => null])->assertUnprocessable()->assertJsonValidationErrors('governorate_id');
        $this->patchAddress($customer, $address, ['city_id' => $closedCity->id])->assertUnprocessable()->assertJsonValidationErrors('city_id');

        foreach ([['governorate_id' => 999], ['governorate_id' => 'abc'], ['city_id' => 999], ['city_id' => '1.5']] as $location) {
            $field = array_key_first($location);
            $store = $this->storeAddress($customer, $location + ['governorate_id' => $ramallah->id])->assertUnprocessable()->assertJsonValidationErrors($field);
            $patch = $this->patchAddress($customer, $address, $location)->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->assertCount(1, $store->json("errors.{$field}"), 'One message, from the basic rules.');
            $this->assertCount(1, $patch->json("errors.{$field}"));
        }

        $this->assertSame(1, $customer->addresses()->count());
        $this->assertAddressLocation($address, $ramallah->id, $ramallahCity->id);
    }

    public function test_another_customers_address_location_is_never_read_or_changed(): void
    {
        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        [$ramallah, $ramallahCity] = $this->location('ramallah');
        [$nablus, $nablusCity] = $this->location('nablus');
        $address = $this->addressFor($owner, $ramallah->id, $ramallahCity->id);

        // A governorate alone would mismatch the owner's stored city: still a plain 403, never a 422 about their data.
        foreach ([['governorate_id' => $nablus->id], ['city_id' => $nablusCity->id], ['label' => 'mine'], ['governorate_id' => null, 'city_id' => null]] as $payload) {
            $this->patchAddress($other, $address, $payload)->assertForbidden()->assertJsonMissingPath('errors');
        }

        $this->assertAddressLocation($address, $ramallah->id, $ramallahCity->id);
        $this->assertSame('home', $address->fresh()->label);
        $this->assertSame(0, $other->addresses()->count());
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

    private function storeAddress(Customer $customer, array $location)
    {
        return $this->actingAs($customer, 'customer')->postJson(route('customer.addresses.store'), $location + [
            'label' => 'home',
            'recipient_name' => 'Salma',
            'phone' => '0591111111',
            'address_line' => 'Main street',
        ]);
    }

    /** A PATCH with the address's other required fields as stored, plus $payload. */
    private function patchAddress(Customer $customer, CustomerAddress $address, array $payload)
    {
        $stored = $address->fresh();

        return $this->actingAs($customer, 'customer')->patchJson(route('customer.addresses.update', $address), $payload + [
            'label' => $stored->label,
            'recipient_name' => $stored->recipient_name,
            'phone' => $stored->phone,
            'address_line' => $stored->address_line,
        ]);
    }

    private function addressFor(Customer $customer, ?int $governorateId, ?int $cityId): CustomerAddress
    {
        return CustomerAddress::query()->create([
            'customer_id' => $customer->id,
            'governorate_id' => $governorateId,
            'city_id' => $cityId,
            'label' => 'home',
            'recipient_name' => 'Salma',
            'phone' => '0591111111',
            'address_line' => 'Main street',
        ]);
    }

    private function assertAddressLocation(CustomerAddress $address, ?int $governorateId, ?int $cityId, string $message = ''): void
    {
        $fresh = $address->fresh();

        $this->assertSame([$governorateId, $cityId], [$fresh->governorate_id, $fresh->city_id], $message);
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
