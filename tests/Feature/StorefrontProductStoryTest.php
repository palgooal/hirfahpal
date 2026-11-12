<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontProductStoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_story_is_hidden_when_no_story_data_was_added(): void
    {
        $product = $this->product('without-story');

        $this->get(route('product', ['slug' => $product->slug]))
            ->assertOk()
            ->assertDontSee('data-node-id="223:2894"', false);
    }

    public function test_product_story_shows_only_when_story_data_was_added(): void
    {
        $product = $this->product('with-story', [
            'material' => 'Clay',
            'place_of_manufacture' => 'Nablus',
            'preparation_days' => 20,
            'care_instructions' => 'Keep in a cool place.',
            'story_description' => 'Formed by hand in a small workshop.',
            'story_image_path' => 'product-stories/story.webp',
        ]);

        $this->get(route('product', ['slug' => $product->slug]))
            ->assertOk()
            ->assertSee('data-node-id="223:2894"', false)
            ->assertSee('Formed by hand in a small workshop.')
            ->assertSee('Clay')
            ->assertSee('Nablus')
            ->assertSee('product-stories/story.webp');
    }

    public function test_description_and_uses_reads_its_own_field_not_the_long_description(): void
    {
        $product = $this->product('separate-usage-description', [
            'description' => 'This is the long product description.',
            'usage_description' => 'This is the description and uses text.',
        ]);

        $html = $this->get(route('product', ['slug' => $product->slug]))->assertOk()->getContent();
        $accordion = str($html)->between('id="spec-description"', 'id="spec-measurements"')->toString();

        $this->assertStringContainsString('This is the description and uses text.', $accordion);
        $this->assertStringNotContainsString('This is the long product description.', $accordion);
    }

    private function product(string $slug, array $attributes = []): Product
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'story-category'],
            ['name' => 'Story category', 'is_active' => true]
        );
        $vendor = Vendor::factory()->approved()->create(['status' => 'active']);

        return Product::query()->create(array_merge([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => $slug,
            'slug' => $slug,
            'price' => 25,
            'stock_quantity' => 10,
            'status' => 'active',
            'published_at' => now(),
        ], $attributes));
    }
}
