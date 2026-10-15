<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStorefront;
use Tests\TestCase;

class CustomerFavoritesPageTest extends TestCase
{
    use InteractsWithStorefront, RefreshDatabase;

    public function test_favorites_require_customer_authentication(): void
    {
        $this->get(route('customer.favorites'))->assertRedirect(route('customer.login'));
    }

    public function test_favorites_render_owned_products_counts_and_native_remove_forms(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->storefrontProduct('saved-product');
        $favorite = $customer->favorites()->create(['product_id' => $product->id]);
        $other = $this->storefrontProduct('foreign-product');
        Customer::factory()->create()->favorites()->create(['product_id' => $other->id]);
        $this->actingAs($customer, 'customer')->get(route('customer.favorites'))->assertOk()->assertSee('saved-product')
            ->assertViewHas('favorites', fn ($favorites) => $favorites->count() === 1 && $favorites->first()->id === $favorite->id)
            ->assertSee('id="favoritesCount">1', false)->assertSee('favorite-remove-'.$favorite->id);
        $this->from(route('customer.favorites'))->delete(route('customer.favorites.destroy', $favorite))->assertRedirect(route('customer.favorites'));
        $this->assertDatabaseMissing('customer_favorites', ['id' => $favorite->id]);
    }

    public function test_unavailable_moderated_products_are_not_shown_as_favorites(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->storefrontProduct('suspended-favorite');
        $customer->favorites()->create(['product_id' => $product->id]);
        $product->forceFill(['suspended_at' => now()])->save();
        $this->actingAs($customer, 'customer')->get(route('customer.favorites'))->assertOk()->assertDontSee('suspended-favorite')
            ->assertViewHas('favorites', fn ($favorites) => $favorites->isEmpty());
    }
}
