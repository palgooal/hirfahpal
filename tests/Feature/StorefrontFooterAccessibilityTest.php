<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Setting;
use App\Models\TranslationValue;
use Database\Seeders\AdminSettingsTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FOOTER-FIX-01: the storefront footer's direction, contrast, hit areas, landmarks, focus and headings.
 */
class StorefrontFooterAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->withoutVite();
    }

    public function test_alignment_follows_the_page_and_every_merchant_text_keeps_its_own_direction(): void
    {
        // Mixed directions in both languages: the flat fields are the Arabic texts, texts.en the English ones
        // (FOOTER-I18N-01B).
        $mixed = ['description' => 'سوق رقمي، يوثق التراث.', 'copyright' => 'All rights reserved.'];
        Setting::singleton()->update(['site_name' => 'Hirfah Store', 'footer_content' => array_replace(Setting::footerDefaults(), $mixed, [
            'texts' => ['en' => $mixed],
        ])]);

        foreach (['ar', 'en'] as $locale) {
            $footer = $this->footer($locale);
            $this->assertStringNotContainsString('text-right', $footer);
            $this->assertStringNotContainsString('text-left', $footer);

            // Tagline, description, the 3 headings, the 10 link labels, the site name and copyright of the bottom line and
            // the 3 payment labels; the site name and payment heading carry dir="auto" themselves.
            $this->assertSame(20, substr_count($footer, '<span dir="auto">'), $locale);
            $this->assertStringContainsString('<span class="text-white/70" dir="auto">', $footer);
            $this->assertStringContainsString('<span dir="auto">سوق رقمي، يوثق التراث.</span>', $footer);
            $this->assertStringContainsString('<span dir="auto">All rights reserved.</span>', $footer);
            $this->assertStringContainsString('<strong class="text-2xl font-bold leading-8 text-[#fef3c7]" dir="auto">Hirfah Store</strong>', $footer);
            $this->assertStringContainsString('<span dir="auto">Hirfah Store.</span>', $footer);
        }
    }

    public function test_the_link_columns_are_named_landmarks_with_h2_headings_and_24px_links(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);

        foreach (['ar' => ['عن الموقع', 'روابط مفيدة', 'خدمة العملاء'], 'en' => ['About', 'Useful links', 'Customer service']] as $locale => $names) {
            $footer = $this->footer($locale);
            preg_match_all('#<nav class="[^"]*" aria-label="([^"]+)" data-footer-nav="[a-z_]+">\s*(?:\{\{--.*?--\}\}\s*)?<h2 #su', $footer, $navs);
            $this->assertSame($names, $navs[1], $locale);
            $this->assertSame(3, substr_count($footer, '<h2 '));
            $this->assertStringNotContainsString('<h3', $footer);
        }

        $footer = $this->footer('ar');
        // Ten column links, each at least 24px high (min-h-6) with the sage focus ring; the rhythm is unchanged
        // (24px + 1.5px gap = the former 16px line + 9.5px gap).
        $this->assertSame(10, substr_count($footer, 'class="flex min-h-6 items-center justify-start gap-1.5 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sage"'));
        $this->assertSame(3, substr_count($footer, '<ul class="flex w-full flex-col gap-[1.5px] text-xs leading-4 text-white/75">'));
    }

    public function test_contrast_focus_logo_and_social_links(): void
    {
        Setting::singleton()->update(['footer_content' => array_replace(Setting::footerDefaults(), [
            'social_links' => [['platform' => 'telegram', 'url' => 'https://t.me/hirfah', 'enabled' => true]],
        ])]);
        $footer = $this->footer('en');

        $this->assertStringContainsString('<span class="text-white/70" dir="auto">', $footer, 'Payment methods heading: about 5.7:1 on olive.');
        $this->assertStringNotContainsString('text-white/50', $footer);
        $this->assertMatchesRegularExpression('#<img src="[^"]+" alt="" class="h-\[50px\] w-\[50px\] object-cover">#', $footer, 'The site name beside it names the logo.');
        $this->assertStringNotContainsString('شعار الموقع', $footer);
        $this->assertMatchesRegularExpression('#<a href="https://t.me/hirfah" target="_blank" rel="noopener noreferrer" class="[^"]*h-8 w-8[^"]*focus-visible:outline-sage" aria-label="Telegram \(opens in a new tab\)" data-footer-social="telegram">#', $footer);
    }

    public function test_the_footer_writes_nothing(): void
    {
        $this->footer('ar');
        $this->footer('en');

        $this->assertSame(0, TranslationValue::where('key', 'like', 'storefront.Footer_%')->count(), 'Footer texts are read without creating rows.');
    }

    private function footer(string $locale): string
    {
        $html = $this->withSession(['locale' => $locale])->get(route('home'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<footer\b.*?</footer>#s', $html, $match));

        return $match[0];
    }
}
