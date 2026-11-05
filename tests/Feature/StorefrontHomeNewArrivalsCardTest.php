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
 * VP-FIDELITY-02A: the Home New Arrivals cards (and their section) keep the approved heights as minimums, so a long
 * title or a wrapped price row grows the card instead of being cut off or running into the next section.
 */
class StorefrontHomeNewArrivalsCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_section_and_card_layout_contract(): void
    {
        $this->product($this->vendor(), 'jug', 'جرّة فخارية');
        [$xpath, $cards] = $this->page();
        $card = $cards[0];

        $section = $xpath->query('//section[@data-node-id="223:409"]')->item(0);
        $this->assertContains('lg:min-h-[689.5px]', $this->classes($section));
        $this->assertNotContains('lg:h-[689.5px]', $this->classes($section));

        $this->assertClasses($card, ['product-card', 'flex', 'min-h-[451px]', 'flex-col', 'overflow-hidden', 'rounded-[24px]']);
        $this->assertNotContains('h-[451px]', $this->classes($card));
        $this->assertClasses($this->first($card, './div[1]'), ['relative', 'h-[284px]', 'shrink-0', 'overflow-hidden']);
        $this->assertClasses($this->first($card, './/h3'), ['text-base', 'font-bold', 'leading-6', 'wrap-anywhere']);
        $this->assertClasses($this->first($card, './/div[contains(@class, "mt-auto")]'), ['mt-auto', 'flex', 'flex-wrap', 'items-center', 'justify-between', 'gap-x-2', 'gap-y-1']);
        $this->assertStringNotContainsString('line-clamp', $card->ownerDocument->saveHTML($card));
    }

    public function test_one_line_and_long_titles_are_rendered_in_full(): void
    {
        $vendor = $this->vendor();
        $titles = [
            'جرّة فخارية',
            'طقم أباريق خزفية مقدسية مرسومة يدوياً بزخارف نباتية زرقاء تقليدية مع ستة أكواب للضيافة',
            'Hand painted Hebron ceramic jug with traditional floral pattern',
            'HandPaintedHebronCeramicJugTraditional',
        ];
        foreach ($titles as $i => $title) {
            $this->product($vendor, 'title-'.$i, $title);
        }

        $rendered = array_map(fn (DOMElement $card) => trim($this->first($card, './/h3')->textContent), $this->page()[1]);
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
        $this->product($vendor, 'in-stock', 'In stock');

        $cards = [];
        foreach ($this->page()[1] as $card) {
            $cards[trim($this->first($card, './/h3')->textContent)] = $card;
        }
        $this->assertCount(4, $cards);

        $row = fn (string $name) => $this->first($cards[$name], './/div[contains(@class, "mt-auto")]');
        $this->assertSame('39.90 ILS', trim($this->first($row('Discounted'), './/*[@data-selling-price]')->textContent));
        $this->assertSame('45.00 ILS', trim($this->first($row('Discounted'), './/*[@data-regular-price]')->textContent));
        $this->assertSame('1,234,567.89 ILS', trim($this->first($row('Large'), './/*[@data-selling-price]')->textContent));

        foreach (['Discounted' => false, 'Large' => false, 'Sold out' => true, 'In stock' => false] as $name => $soldOut) {
            // The badge keeps its current (content-managed) text; the purchase button keeps its disabled state.
            $badge = $this->first($row($name), './span[last()]');
            $this->assertSame(config('homepage.new-arrivals_text_6'), trim($badge->textContent), $name);
            $button = $this->first($cards[$name], './/button[contains(@class, "add-cart")]');
            $this->assertSame($soldOut, $button->hasAttribute('disabled'), $name);
        }
    }

    /** @return array{0: DOMXPath, 1: list<DOMElement>} */
    private function page(): array
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $xpath = new DOMXPath($document);

        return [$xpath, iterator_to_array($xpath->query('//*[@data-node-id="223:422"]/article'), false)];
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
        $vendor->profile()->update(['store_name' => 'Home store', 'slug' => 'home-store']);

        return $vendor;
    }

    private function product(Vendor $vendor, string $slug, string $name, array $attributes = []): Product
    {
        return Product::query()->create(array_merge(['vendor_id' => $vendor->id, 'name' => $name, 'slug' => $slug,
            'price' => 25, 'stock_quantity' => 10, 'status' => 'active', 'published_at' => now()], $attributes));
    }
}
