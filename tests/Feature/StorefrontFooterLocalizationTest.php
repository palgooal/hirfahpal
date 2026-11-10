<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Setting;
use App\Models\TranslationValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FOOTER-I18N-01B: the storefront footer in the page language. Flat fields are the Arabic texts (all data saved so
 * far); texts.<locale> holds a language's own texts; English shows its approved defaults where it has none, never the
 * Arabic texts; the social links stay language-neutral; reading writes nothing.
 */
class StorefrontFooterLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private const ENGLISH = [
        'tagline' => 'Authentic Palestinian crafts',
        'description' => "A digital marketplace that celebrates Palestinian heritage and connects independent artisans and women's cooperatives with customers around the world through authentic, quality craftsmanship.",
        'about_title' => 'About Hirfah',
        'about_story_label' => 'Our story and vision',
        'about_authenticity_label' => 'Authenticity and quality charter',
        'about_contact_label' => 'Contact us',
        'links_title' => 'Useful links',
        'orders_label' => 'My orders',
        'cart_label' => 'Shopping cart',
        'login_label' => 'Sign in',
        'account_label' => 'My account',
        'customer_service_title' => 'Customer service',
        'shipping_label' => 'Shipping and delivery',
        'returns_label' => 'Returns and exchanges',
        'faq_label' => 'Frequently asked questions',
        'copyright' => 'All rights reserved.',
        'payment_methods_title' => 'Secure payment methods:',
        'card_payment_label' => 'Credit card',
        'apple_pay_label' => 'Apple Pay',
        'cash_on_delivery_label' => 'Cash on delivery',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->withoutVite();
    }

    public function test_the_defaults_are_the_approved_copy_and_arabic_is_unchanged(): void
    {
        $arabic = Setting::footerDefaults('ar');
        $english = Setting::footerDefaults('en');

        $this->assertSame(Setting::footerDefaults(), $arabic, 'Arabic stays the default.');
        $this->assertSame('سوق الصنعة الفلسطينية الأصيلة', $arabic['tagline']);
        $this->assertSame('جميع الحقوق محفوظة لرواد الحِرف الأصيلة في فلسطين.', $arabic['copyright']);
        $this->assertSame(array_keys($arabic), array_keys($english), 'Same keys, same order.');
        $this->assertSame(self::ENGLISH, array_diff_key($english, array_flip(Setting::FOOTER_URL_FIELDS)));
        $this->assertSame(['', '', ''], array_values(array_intersect_key($english, array_flip(Setting::FOOTER_URL_FIELDS))));
        $this->assertSame($english, Setting::footerDefaults('fr'), 'Another language reads English.');
    }

    public function test_saved_arabic_content_shows_in_arabic_and_english_shows_its_defaults_not_the_arabic(): void
    {
        $this->store(['tagline' => 'شعار مخصص للمتجر', 'copyright' => 'حقوق مخصصة.', 'faq_label' => 'أسئلة']);

        $ar = $this->footer('ar');
        $this->assertStringContainsString('<span dir="auto">شعار مخصص للمتجر</span>', $ar);
        $this->assertStringContainsString('<span dir="auto">حقوق مخصصة.</span>', $ar);
        $this->assertStringContainsString('<span dir="auto">'.Setting::footerDefaults()['about_title'].'</span>', $ar, 'An Arabic default where nothing is saved.');

        $en = $this->footer('en');
        foreach (self::ENGLISH as $key => $text) {
            $this->assertStringContainsString(e($text), $en, $key);
        }
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $en, 'No Arabic text on an English page.');
    }

    public function test_saved_localized_texts_override_the_defaults_in_their_language_only(): void
    {
        $this->store([
            'tagline' => 'شعار مسطح قديم',
            'texts' => [
                'en' => ['tagline' => '  Handmade in Palestine  ', 'copyright' => 'Copyright Hirfah.', 'faq_label' => ''],
                'ar' => ['tagline' => 'شعار باللغة العربية'],
            ],
        ]);

        $en = Setting::footerFor(Setting::query()->first(), 'en');
        $this->assertSame('Handmade in Palestine', $en['tagline'], 'Trimmed.');
        $this->assertSame('Copyright Hirfah.', $en['copyright']);
        $this->assertSame('Frequently asked questions', $en['faq_label'], 'Empty: the default.');
        $this->assertSame('About Hirfah', $en['about_title']);

        // texts.ar, once saved, is the Arabic source; the flat fields are no longer read for Arabic.
        $ar = Setting::footerFor(Setting::query()->first(), 'ar');
        $this->assertSame('شعار باللغة العربية', $ar['tagline']);
        $this->assertSame(Setting::footerDefaults()['copyright'], $ar['copyright']);

        $this->assertStringContainsString('<span dir="auto">Handmade in Palestine</span>', $this->footer('en'));
        $this->assertStringContainsString('<span dir="auto">شعار باللغة العربية</span>', $this->footer('ar'));
    }

    public function test_missing_malformed_and_unsupported_cases_fall_back_safely(): void
    {
        foreach ([null, 'text', ['texts' => 'en'], ['texts' => ['en' => 'x']], ['texts' => ['en' => ['tagline' => ['nested'], 'copyright' => 42, 'links_title' => '   ']]]] as $content) {
            $setting = new Setting(['footer_content' => $content]);
            $this->assertSame(Setting::footerDefaults('en'), Setting::footerFor($setting, 'en'), json_encode($content));
        }

        $this->assertSame(Setting::footerDefaults('en'), Setting::footerFor(null, 'en'));
        $this->assertSame(Setting::footerDefaults('ar'), Setting::footerFor(null));
        $this->assertSame(Setting::footerDefaults('en'), Setting::footerFor(new Setting(['footer_content' => ['texts' => ['fr' => ['tagline' => 'Artisanat']]]]), 'fr'), 'Unsupported: English, never another stored language.');

        // A malformed texts.ar falls back to the flat Arabic fields.
        $this->assertSame('شعار', Setting::footerFor(new Setting(['footer_content' => ['tagline' => 'شعار', 'texts' => ['ar' => 'x']]]), 'ar')['tagline']);
    }

    public function test_links_stay_language_neutral_and_routes_are_unchanged(): void
    {
        $this->store([
            'instagram_url' => 'https://instagram.com/hirfah', 'x_url' => 'javascript:alert(1)',
            'texts' => ['en' => ['instagram_url' => 'https://instagram.com/other']],
        ]);
        $setting = Setting::query()->first();

        foreach (['ar', 'en'] as $locale) {
            $footer = Setting::footerFor($setting, $locale);
            $this->assertSame('https://instagram.com/hirfah', $footer['instagram_url'], $locale);
            $this->assertSame('', $footer['x_url'], $locale);
        }

        $hrefs = fn (string $html) => preg_match_all('#href="([^"]+)"#', $html, $m) ? $m[1] : [];
        // PAGES-05D: the same links in both languages, except that a content page link carries the footer's language.
        $neutral = fn (array $links) => array_map(fn (string $href) => preg_replace('#/(ar|en)/pages/#', '/pages/', $href), $links);
        $this->assertSame($neutral($hrefs($this->footer('ar'))), $neutral($hrefs($this->footer('en'))));
        $this->assertContains('https://instagram.com/hirfah', $hrefs($this->footer('en')));
        $this->assertContains(route('content.show', ['locale' => 'en', 'slug' => 'faq']), $hrefs($this->footer('en')));
        $this->assertContains(route('content.show', ['locale' => 'ar', 'slug' => 'faq']), $hrefs($this->footer('ar')));
    }

    public function test_the_site_name_falls_back_per_language_and_a_saved_one_shows_in_both(): void
    {
        foreach ([null, '', '   '] as $name) {
            Setting::query()->delete();
            Setting::singleton()->update(['site_name' => $name]);
            $this->assertStringContainsString('dir="auto">حِرفة</strong>', $this->footer('ar'));
            $this->assertStringContainsString('dir="auto">Hirfah</strong>', $this->footer('en'));
            $this->assertStringContainsString('<span dir="auto">Hirfah.</span>', $this->footer('en'));
        }

        Setting::query()->update(['site_name' => 'Hirfah Market']);
        foreach (['ar', 'en'] as $locale) {
            $this->assertStringContainsString('dir="auto">Hirfah Market</strong>', $this->footer($locale));
        }
        $this->assertSame('Hirfah', Setting::siteNameFor(null, 'fr'));
    }

    public function test_reading_writes_nothing(): void
    {
        $this->store(['tagline' => 'شعار', 'texts' => ['en' => ['tagline' => 'Tagline']]]);
        $before = DB::table('settings')->get()->toArray();

        foreach (['ar', 'en'] as $locale) {
            $this->footer($locale);
        }

        $this->assertEquals($before, DB::table('settings')->get()->toArray());
        $this->assertSame(0, TranslationValue::where('key', 'like', 'storefront.Footer_%')->count());
    }

    private function store(array $content): void
    {
        Setting::singleton()->update(['footer_content' => array_replace(Setting::footerDefaults(), $content)]);
    }

    private function footer(string $locale): string
    {
        $html = $this->withSession(['locale' => $locale])->get(route('home'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<footer\b.*?</footer>#s', $html, $match));

        return $match[0];
    }
}
