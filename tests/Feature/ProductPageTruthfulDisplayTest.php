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
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRODUCT-FIX-01 (PRODUCT-AUDIT-01 H2, H3, M3, M6): the product page shows the actual published rating (or an unrated
 * state) with an accessible name that states it, no unsupported claims (100% handmade, serial number, international
 * packaging, verified authentic piece, rare piece, single piece), the store's own preparation time in correct Arabic,
 * and each related product's own name on its favourite button.
 */
class ProductPageTruthfulDisplayTest extends TestCase
{
    use RefreshDatabase;

    private const UNSUPPORTED_CLAIMS = ['100% صناعة يدوية أصيلة', 'قطعة أصلية 100%', 'تسلسلي', 'للشحن الداخلي والخارجي', 'تغليف آمن ضد الكسر',
        'قطعة أصلية موثقة', 'قطعة نادرة', 'قطعة وحيدة', 'سلة القش', '5 من 5 نجوم'];

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::query()->create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
    }

    public function test_an_unrated_product_shows_an_unrated_state_and_no_fixed_stars(): void
    {
        $product = $this->product($this->vendor(), 'unrated-jar');

        $html = $this->page($product);
        $xpath = $this->xpath($html);

        $this->assertStringContainsString('لا توجد تقييمات بعد', $this->text($xpath, '//*[@data-product-rating]'));
        $this->assertSame(0, $xpath->query('//*[@data-product-rating]//img')->length, 'No stars next to an unrated product.');
        $this->assertSame('—', trim($this->text($xpath, '//*[@data-review-summary]/strong')));
        $this->assertSame(['0'], $this->attributes($xpath, '//*[@data-review-summary]//*[@data-filled-stars]/@data-filled-stars'));
        $this->assertSame(['لا توجد تقييمات بعد'], $this->attributes($xpath, '//*[@data-review-summary]//*[@role="img"]/@aria-label'));
        $this->assertStringContainsString('لم يُقيَّم هذا المنتج بعد', $html);
    }

    public function test_a_rated_product_shows_its_actual_published_rating(): void
    {
        $product = $this->product($this->vendor(), 'rated-jar');
        $this->review($product, 2, 'published');
        $this->review($product, 3, 'published');
        $this->review($product, 5, 'pending');
        $this->review($product, 5, 'hidden');

        $xpath = $this->xpath($this->page($product));

        // Published only: (2 + 3) / 2 = 2.5, shown as 2.5; rounded to the nearest whole star, 3 filled, 2 dimmed.
        $this->assertSame(['3', '3'], $this->attributes($xpath, '//*[@data-filled-stars]/@data-filled-stars'));
        $this->assertSame(['التقييم 2.5 من 5', 'التقييم 2.5 من 5'], $this->attributes($xpath, '//*[@data-filled-stars]/@aria-label'));
        $this->assertSame(2, $xpath->query('//*[@data-product-rating]//img[@style]')->length, 'Two dimmed stars.');
        $this->assertSame('2.5', trim($this->text($xpath, '//*[@data-review-summary]/strong')));
        $this->assertStringContainsString('(2 تقييماً موثقاً من مشترين فعليين)', $this->text($xpath, '//*[@data-product-rating]'));
    }

    public function test_unsupported_claims_are_gone_and_supported_ones_remain(): void
    {
        $html = $this->page($this->product($this->vendor(), 'jar'));

        foreach (self::UNSUPPORTED_CLAIMS as $claim) {
            $this->assertStringNotContainsString($claim, $html, $claim);
        }
        // Backed by the checkout's payment methods (online, cod) and the vendor-to-customer delivery.
        $this->assertStringContainsString('دفع مرن وآمن', $html);
        $this->assertStringContainsString('شحن مباشر', $html);
    }

    public function test_the_workshop_location_badge_appears_only_when_the_store_has_a_location(): void
    {
        // The badge's icon is used by the badge only ("من مشاغل" also appears in a fixed section subtitle).
        $html = $this->page($this->product($this->vendor(), 'no-location'));
        $this->assertStringNotContainsString('breadcrumb-verified.svg', $html);
        $this->assertStringNotContainsString('من مشاغل غير محدد', $html);

        $governorate = Governorate::query()->create(['name' => 'الخليل', 'slug' => 'hebron', 'status' => 'active']);
        $city = City::query()->create(['governorate_id' => $governorate->id, 'name' => 'دورا', 'slug' => 'dura', 'status' => 'active']);
        $vendor = $this->vendor();
        $vendor->profile()->update(['city_id' => $city->id, 'governorate_id' => $governorate->id]);

        $this->assertStringContainsString('من مشاغل دورا', $this->page($this->product($vendor->fresh(), 'located')));
    }

    public function test_the_store_and_product_preparation_times_use_their_own_values_in_correct_arabic(): void
    {
        $vendor = $this->vendor();
        $xpath = $this->xpath($this->page($this->product($vendor, 'no-times')));
        $this->assertSame('غير محدد', $this->storePreparation($xpath));

        $vendor->profile()->update(['preparation_days' => 2]);
        $html = $this->page($this->product($vendor->fresh(), 'store-two-product-three', ['preparation_days' => 3]));
        $this->assertSame('يومان', $this->storePreparation($this->xpath($html)), 'The store tile shows the store value, not the product one.');
        $this->assertStringContainsString('3 أيام', $html, 'The product keeps its own preparation time.');
        $this->assertStringNotContainsString('3 يوم', $html);

        $vendor->profile()->update(['preparation_days' => 14]);
        $this->assertSame('14 يومًا', $this->storePreparation($this->xpath($this->page($this->product($vendor->fresh(), 'store-fourteen')))));
    }

    public function test_related_products_use_their_own_names_badges_and_availability(): void
    {
        $vendor = $this->vendor();
        $main = $this->product($vendor, 'main-jar');
        $this->product($vendor, 'blue-bowl', ['name' => 'Blue bowl', 'is_limited_edition' => true]);
        $this->product($vendor, 'olive-cup', ['name' => 'Olive cup', 'stock_quantity' => 0]);

        $xpath = $this->xpath($this->page($main));

        $labels = $this->attributes($xpath, '//button[starts-with(@form, "related-favorite-")]/@aria-label');
        sort($labels);
        $this->assertSame(['أضف Blue bowl إلى المفضلة', 'أضف Olive cup إلى المفضلة'], $labels);
        $this->assertSame(1, substr_count($this->page($main), 'إصدار حرفي محدود'), 'Only the limited edition product has the badge.');
        $this->assertStringContainsString('غير متوفر حالياً', $this->page($main), 'The out-of-stock related product says so.');
    }

    public function test_prices_stock_and_cart_controls_are_unchanged(): void
    {
        $product = $this->product($this->vendor(), 'priced-jar', ['price' => 45, 'compare_at_price' => 39.90, 'stock_quantity' => 12]);
        $html = $this->page($product);

        $this->assertStringContainsString('39.90 ILS', $html);
        $this->assertStringContainsString('45.00 ILS', $html);
        $this->assertStringContainsString('12 قطعة متاحة', $html);
        $this->assertStringContainsString('data-price="39.90"', $html);
        $this->assertStringContainsString('action="'.route('customer.cart.items.store').'"', $html);
    }

    private function page(Product $product): string
    {
        return $this->get(route('product', ['slug' => $product->slug]))->assertOk()->getContent();
    }

    private function storePreparation(DOMXPath $xpath): string
    {
        return trim($this->text($xpath, '//span[normalize-space()="التجهيز"]/following-sibling::strong'));
    }

    private function vendor(): Vendor
    {
        return Vendor::factory()->approved()->create(['status' => 'active']);
    }

    private function product(Vendor $vendor, string $slug, array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'vendor_id' => $vendor->id, 'category_id' => $this->category->id, 'name' => 'Product '.$slug, 'slug' => $slug,
            'price' => 25, 'stock_quantity' => 5, 'status' => 'active', 'published_at' => now(),
        ], $attributes));
    }

    private function review(Product $product, int $rating, string $status): void
    {
        $customer = Customer::factory()->create();
        $order = Order::query()->create(['number' => 'R-'.uniqid(), 'customer_id' => $customer->id, 'payment_method' => 'cod']);
        Review::query()->create(['customer_id' => $customer->id, 'order_id' => $order->id, 'product_id' => $product->id,
            'reviewable_type' => 'product', 'rating' => $rating, 'comment' => 'Comment', 'status' => $status]);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private function text(DOMXPath $xpath, string $expression): string
    {
        return preg_replace('/\s+/u', ' ', $xpath->query($expression)->item(0)?->textContent ?? '');
    }

    /** @return list<string> */
    private function attributes(DOMXPath $xpath, string $expression): array
    {
        return array_map(fn ($node) => $node->nodeValue, iterator_to_array($xpath->query($expression)));
    }
}
