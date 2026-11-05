<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Vendor;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * VP-FIDELITY-02: the vendor page's product cards keep the approved 451px as a minimum height, so a long title or
 * a wrapped price row grows the card instead of being cut off; long words wrap and nothing is truncated.
 */
class StorefrontVendorProductCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_card_layout_contract(): void
    {
        $vendor = $this->vendor();
        $this->product($vendor, 'jug', 'جرّة فخارية');
        $card = $this->cards()[0];

        // A minimum height, not a fixed one; the image area keeps the approved fixed height.
        $this->assertClasses($card, ['product-card', 'flex', 'min-h-[451px]', 'flex-col', 'overflow-hidden', 'rounded-[24px]']);
        $this->assertNotContains('h-[451px]', $this->classes($card));
        $this->assertClasses($this->first($card, './div[1]'), ['relative', 'h-[284px]', 'shrink-0', 'overflow-hidden']);
        // The title wraps long words and is never clamped; the price row stays at the bottom and may wrap.
        $this->assertClasses($this->first($card, './/h3'), ['text-base', 'font-bold', 'leading-6', 'wrap-anywhere']);
        $this->assertClasses($this->first($card, './/div[contains(@class, "mt-auto")]'), ['mt-auto', 'flex', 'flex-wrap', 'items-center', 'justify-between', 'gap-x-2', 'gap-y-1']);
        $this->assertStringNotContainsString('line-clamp', $card->ownerDocument->saveHTML($card));
    }

    public function test_long_titles_are_rendered_in_full(): void
    {
        $vendor = $this->vendor();
        $titles = [
            'طقم أباريق خزفية مقدسية مرسومة يدوياً بزخارف نباتية زرقاء تقليدية مع ستة أكواب للضيافة',
            'Hand painted Hebron ceramic jug with traditional floral pattern',
            'HandPaintedHebronCeramicJugTraditional',
        ];
        foreach ($titles as $i => $title) {
            $this->product($vendor, 'long-'.$i, $title);
        }

        $rendered = array_map(fn (DOMElement $card) => trim($this->first($card, './/h3')->textContent), $this->cards());
        sort($rendered);
        sort($titles);
        $this->assertSame($titles, $rendered);
    }

    public function test_prices_badges_and_the_purchase_button_are_all_rendered(): void
    {
        $vendor = $this->vendor();
        $this->product($vendor, 'discounted', 'Discounted', ['price' => 45, 'compare_at_price' => 39.90]);
        $this->product($vendor, 'large', 'Large', ['price' => 1234567.89]);
        $this->product($vendor, 'sold-out', 'Sold out', ['stock_quantity' => 0]);

        $cards = [];
        foreach ($this->cards() as $card) {
            $cards[$card->getAttribute('data-name')] = $card;
        }

        $row = fn (string $name) => $this->first($cards[$name], './/div[contains(@class, "mt-auto")]');
        $this->assertSame('39.90 ILS', trim($this->first($row('Discounted'), './/*[@data-selling-price]')->textContent));
        $this->assertSame('45.00 ILS', trim($this->first($row('Discounted'), './/*[@data-regular-price]')->textContent));
        $this->assertSame('1,234,567.89 ILS', trim($this->first($row('Large'), './/*[@data-selling-price]')->textContent));

        foreach (['Discounted' => '10', 'Large' => '10', 'Sold out' => '0'] as $name => $stock) {
            // The stock badge (wording unchanged) is in the price row.
            $badge = $this->first($row($name), './span[last()]');
            $this->assertSame('متوفر '.$stock.' قطع', preg_replace('/\s+/u', ' ', trim($badge->textContent)), $name);
            // The purchase button is still rendered with its form; only a sold-out product's is disabled.
            $button = $this->first($cards[$name], './/button[contains(@class, "add-cart")]');
            $this->assertSame('submit', $button->getAttribute('type'), $name);
            $this->assertNotSame('', $button->getAttribute('form'), $name);
            $this->assertSame($stock === '0', $button->hasAttribute('disabled'), $name);
        }
    }

    /** @return list<DOMElement> */
    private function cards(): array
    {
        $html = $this->get(route('vendors.profile', ['storeSlug' => 'card-store']))->assertOk()->getContent();
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return iterator_to_array((new DOMXPath($document))->query('//*[@data-vendor-product-grid]/article'), false);
    }

    private function first(DOMElement $context, string $expression): DOMElement
    {
        $nodes = (new DOMXPath($context->ownerDocument))->query($expression, $context);
        $this->assertGreaterThan(0, $nodes->length, $expression);

        return $nodes->item(0);
    }

    /** @return list<string> */
    private function classes(DOMElement $element): array
    {
        return preg_split('/\s+/', trim($element->getAttribute('class')));
    }

    private function assertClasses(DOMElement $element, array $expected): void
    {
        foreach ($expected as $class) {
            $this->assertContains($class, $this->classes($element), $element->getNodePath());
        }
    }

    private function vendor(): Vendor
    {
        $vendor = Vendor::factory()->withProfile('approved')->create(['status' => 'active']);
        $vendor->profile()->update(['store_name' => 'Card store', 'slug' => 'card-store']);

        return $vendor;
    }

    private function product(Vendor $vendor, string $slug, string $name, array $attributes = []): Product
    {
        return Product::query()->create(array_merge(['vendor_id' => $vendor->id, 'name' => $name, 'slug' => $slug,
            'price' => 25, 'stock_quantity' => 10, 'status' => 'active'], $attributes));
    }
}
