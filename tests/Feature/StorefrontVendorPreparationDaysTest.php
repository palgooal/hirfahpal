<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Vendor;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * VP-DATA-02B: the public vendor page shows the store's own preparation time (vendor_profiles.preparation_days) in
 * Arabic, «غير محددة» when none is saved, and never a product's preparation time.
 */
class StorefrontVendorPreparationDaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_vendor_page_shows_the_stores_saved_preparation_time(): void
    {
        $cases = [
            'null' => [null, 'غير محددة'],
            'zero' => [0, 'في اليوم نفسه'],
            'one' => [1, 'يوم واحد'],
            'two' => [2, 'يومان'],
            'seven' => [7, '7 أيام'],
        ];

        foreach ($cases as $slug => [$days, $label]) {
            $this->vendor($slug, $days);

            $this->assertSame($label, $this->preparationStat($slug), $slug);
        }
    }

    public function test_the_value_comes_from_the_store_not_from_its_products(): void
    {
        $vendor = $this->vendor('store-with-product-times', null);
        $this->product($vendor, 'own-time-product', 9);

        $this->assertSame('غير محددة', $this->preparationStat('store-with-product-times'));

        $vendor->profile()->update(['preparation_days' => 2]);
        $this->assertSame('يومان', $this->preparationStat('store-with-product-times'));
    }

    public function test_the_old_placeholder_label_is_gone(): void
    {
        $this->vendor('placeholder-check', 3);

        $this->get(route('vendors.profile', ['storeSlug' => 'placeholder-check']))->assertOk()
            ->assertDontSee('أيام تجهيز')->assertSee('مدة التجهيز');
    }

    /** The value shown above «مدة التجهيز» in the store's statistics. */
    private function preparationStat(string $slug): string
    {
        $html = $this->get(route('vendors.profile', ['storeSlug' => $slug]))->assertOk()->getContent();
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $values = (new DOMXPath($document))->query('//main//div[span[normalize-space()="مدة التجهيز"]]/bdi');
        $this->assertSame(1, $values->length, $slug);

        return trim($values->item(0)->textContent);
    }

    private function vendor(string $slug, ?int $days): Vendor
    {
        $vendor = Vendor::factory()->withProfile('approved')->create(['status' => 'active']);
        $vendor->profile()->update(['store_name' => 'Store '.$slug, 'slug' => $slug, 'preparation_days' => $days]);

        return $vendor;
    }

    private function product(Vendor $vendor, string $slug, int $days): Product
    {
        return Product::query()->create(['vendor_id' => $vendor->id, 'name' => $slug, 'slug' => $slug, 'price' => 25,
            'stock_quantity' => 10, 'status' => 'active', 'preparation_days' => $days]);
    }
}
