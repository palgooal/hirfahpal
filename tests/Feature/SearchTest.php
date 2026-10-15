<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStorefront;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use InteractsWithStorefront, RefreshDatabase;

    public function test_search_matches_actual_product_category_and_store_data(): void
    {
        $product = $this->storefrontProduct('handmade-vase', attributes: ['short_description' => 'Decorative ceramic']);
        $product->vendor->profile()->update(['store_name' => 'Olive Workshop']);
        foreach (['handmade-vase', 'Decorative', 'Test category', 'Olive Workshop'] as $query) {
            $this->get(route('browse', ['q' => $query]))->assertOk()
                ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$product->id]);
        }
    }

    public function test_search_hides_unpublished_products_and_handles_no_matches(): void
    {
        $this->storefrontProduct('hidden-vase', attributes: ['status' => 'draft']);
        $this->get(route('browse', ['q' => 'vase']))->assertOk()->assertViewHas('products', fn ($products) => $products->isEmpty());
        $this->get(route('browse', ['q' => 'not found']))->assertOk()->assertViewHas('products', fn ($products) => $products->isEmpty());
    }

    public function test_invalid_search_input_is_rejected(): void
    {
        $this->getJson(route('browse', ['q' => ['invalid']]))->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson(route('browse', ['q' => str_repeat('a', 201)]))->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    public function test_offers_only_include_products_with_a_real_price_reduction(): void
    {
        $offer = $this->storefrontProduct('real-offer', attributes: ['price' => 20, 'compare_at_price' => 30]);
        $this->storefrontProduct('regular-product');
        $this->get(route('browse', ['sale' => 1]))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$offer->id]);
    }
}
