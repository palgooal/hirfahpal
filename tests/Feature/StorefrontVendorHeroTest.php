<?php

namespace Tests\Feature;

use App\Models\Vendor;
use App\Models\VendorProfile;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * VP-FIDELITY-01: the public vendor hero. The identity column is limited to the approved width so the description
 * keeps its room, and the eyebrow shows the short description only when it does not repeat the main description.
 */
class StorefrontVendorHeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_distinct_short_description_is_the_eyebrow_beside_the_full_description(): void
    {
        $this->vendor('karma', 'دار الكرمة للخزف', 'متجر خزف وفخار من الخليل', 'مشغل عائلي يحفظ حكاية الخزف الأزرق.');

        $hero = $this->hero('karma');
        $this->assertSame('متجر خزف وفخار من الخليل', $hero['tagline']);
        $this->assertSame('دار الكرمة للخزف', $hero['name']);
        $this->assertSame('مشغل عائلي يحفظ حكاية الخزف الأزرق.', $hero['description']);
    }

    public function test_the_eyebrow_is_left_out_when_it_would_be_empty_or_repeat_the_description(): void
    {
        $description = 'مشغل عائلي يحفظ حكاية الخزف الأزرق. تبدأ كل قطعة من طين مشغول على الدولاب اليدوي.';
        $cases = [
            'empty' => ['', $description],
            'null' => [null, $description],
            'blank' => ['   ', $description],
            'identical' => [$description, $description],
            // The description opens with it (the current data's pattern), with other spacing and letter case.
            'contained' => ["  مشغل عائلي   يحفظ حكاية\nالخزف الأزرق.  ", $description],
            'contained english' => ['Family Workshop', 'A family workshop in Hebron.'],
            // Without a description the short description is the main text, so it is not repeated above it.
            'no description' => ['متجر خزف وفخار من الخليل', null],
        ];

        foreach ($cases as $case => [$short, $long]) {
            $this->vendor($slug = 'store-'.str_replace(' ', '-', $case), 'متجر '.$case, $short, $long);

            $hero = $this->hero($slug);
            $this->assertNull($hero['tagline'], $case);
            $this->assertSame('متجر '.$case, $hero['name'], $case);
            // The store name is never used in its place.
            $this->assertSame(1, substr_count($hero['identity'], 'متجر '.$case), $case);
        }

        $this->assertSame('متجر خزف وفخار من الخليل', $this->hero('store-no-description')['description']);
    }

    public function test_the_store_name_and_a_long_description_are_rendered_in_full(): void
    {
        $name = 'TheVeryLongPalestinianHandmadeCeramicsAndEmbroideryCooperativeWorkshop of Hebron';
        $description = trim(str_repeat('مشغل عائلي يحفظ حكاية الخزف الأزرق والمزجج بلمسة ورثة الحرفة في البلدة القديمة. ', 25));
        $this->vendor('long', $name, str_repeat('ت', 255), $description);

        $hero = $this->hero('long');
        $this->assertSame($name, $hero['name']);
        $this->assertSame($description, $hero['description']);
        $this->assertSame(str_repeat('ت', 255), $hero['tagline']); // whole in the markup; only its display is clamped
    }

    public function test_the_hero_layout_contract(): void
    {
        $this->vendor('contract', 'Contract store', 'Short line', 'Main description.');
        $html = $this->get(route('vendors.profile', ['storeSlug' => 'contract']))->assertOk()->getContent();
        $xpath = $this->xpath($html);

        // The identity column fits its content up to the approved width; the description column can shrink.
        $this->assertSame(1, $xpath->query('//main//div[contains(concat(" ", normalize-space(@class), " "), " lg:grid-cols-[fit-content(380px)_minmax(0,1fr)_auto] ")]')->length);
        $this->assertStringNotContainsString('lg:grid-cols-[auto_1fr_auto]', $html);
        // Long words in the store name wrap instead of running out of the card; only the eyebrow is clamped.
        $this->assertStringContainsString(' wrap-anywhere ', ' '.$xpath->query('//main//h1')->item(0)->getAttribute('class').' ');
        $this->assertStringContainsString('line-clamp-2', $xpath->query('//*[@data-store-tagline]')->item(0)->getAttribute('class'));
        $this->assertSame(1, substr_count($html, 'line-clamp'));
        // The statistics are unchanged (VP-DATA-02B).
        $this->assertSame(1, $xpath->query('//main//div[span[normalize-space()="مدة التجهيز"]]/bdi')->length);
    }

    public function test_public_tagline_rules(): void
    {
        $tagline = fn (?string $short, ?string $description) => (new VendorProfile(['short_description' => $short, 'description' => $description]))->publicTagline();

        $this->assertSame('Short line', $tagline('  Short line ', 'Main description.'));
        $this->assertNull($tagline('', 'Main description.'));
        $this->assertNull($tagline('MAIN description', 'Main description.'));
        $this->assertNull($tagline('Short line', '   '));
        $this->assertNull($tagline(null, null));
    }

    /** @return array{identity: string, tagline: ?string, name: string, description: string} */
    private function hero(string $slug): array
    {
        $xpath = $this->xpath($this->get(route('vendors.profile', ['storeSlug' => $slug]))->assertOk()->getContent());
        $identity = $xpath->query('//main//h1/parent::div')->item(0);
        $tagline = $xpath->query('//main//*[@data-store-tagline]');
        $description = $xpath->query('//main//h1/ancestor::div[contains(@class, "grid")][1]/p');
        $this->assertSame(1, $description->length, $slug);

        return [
            'identity' => $identity->textContent,
            'tagline' => $tagline->length ? trim($tagline->item(0)->textContent) : null,
            'name' => trim($xpath->query('//main//h1')->item(0)->textContent),
            'description' => trim($description->item(0)->textContent),
        ];
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private function vendor(string $slug, string $name, ?string $short, ?string $description): Vendor
    {
        $vendor = Vendor::factory()->withProfile('approved')->create(['status' => 'active']);
        $vendor->profile()->update(['slug' => $slug, 'store_name' => $name, 'short_description' => $short, 'description' => $description]);

        return $vendor;
    }
}
