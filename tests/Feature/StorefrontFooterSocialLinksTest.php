<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Setting;
use App\Models\TranslationValue;
use Database\Seeders\AdminSettingsTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FOOTER-SOCIAL-02A: the storefront footer renders only enabled, valid social links, in their saved order, as
 * one-colour icons in a new tab with translated names; no row without one; reading never writes.
 */
class StorefrontFooterSocialLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->withoutVite();
    }

    public function test_enabled_valid_links_render_in_their_saved_order_with_icons_and_names(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);
        $this->store(['social_links' => [
            ['platform' => 'youtube', 'url' => 'https://www.youtube.com/@hirfah', 'enabled' => true],
            ['platform' => 'whatsapp', 'url' => 'https://wa.me/970599000000', 'enabled' => true],
            ['platform' => 'facebook', 'url' => 'https://facebook.com/hirfah', 'enabled' => false],
            ['platform' => 'instagram', 'url' => 'https://instagram.com.example.org/hirfah', 'enabled' => true],
            ['platform' => 'tiktok', 'url' => 'https://www.tiktok.com/@hirfah', 'enabled' => true],
        ]]);

        $ar = $this->footer('ar');
        $this->assertSame(['youtube', 'whatsapp', 'tiktok'], $this->platforms($ar));
        $this->assertStringContainsString('<a href="https://wa.me/970599000000" target="_blank" rel="noopener noreferrer" class="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/10 text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sage" aria-label="واتساب (يُفتح في نافذة جديدة)" data-footer-social="whatsapp">', $ar);
        $this->assertStringNotContainsString('facebook.com/hirfah', $ar, 'Disabled: not rendered.');
        $this->assertStringNotContainsString('example.org', $ar, 'Lookalike host: not rendered.');

        // Each link holds one decorative, one-colour icon.
        preg_match_all('#data-footer-social="[a-z]+">\s*<svg xmlns="http://www.w3.org/2000/svg" viewBox="[^"]+" fill="currentColor" aria-hidden="true" focusable="false" class="h-4 w-4"><path[^>]* d="[^"]{40,}"/></svg>\s*</a>#', $ar, $icons);
        $this->assertCount(3, $icons[0]);

        $en = $this->footer('en');
        $this->assertStringContainsString('aria-label="YouTube (opens in a new tab)"', $en);
        $this->assertStringContainsString('aria-label="TikTok (opens in a new tab)"', $en);
    }

    public function test_every_platform_has_an_icon_and_a_name_in_both_languages(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);
        $urls = ['facebook' => 'https://facebook.com/h', 'instagram' => 'https://instagram.com/h', 'whatsapp' => 'https://wa.me/1', 'tiktok' => 'https://tiktok.com/@h',
            'youtube' => 'https://youtu.be/h', 'x' => 'https://x.com/h', 'linkedin' => 'https://linkedin.com/in/h', 'telegram' => 'https://t.me/h'];
        $this->store(['social_links' => array_map(fn ($platform) => ['platform' => $platform, 'url' => $urls[$platform], 'enabled' => true], array_keys($urls))]);

        foreach (['ar' => '/\p{Arabic}/u', 'en' => '/^[A-Za-z]+$/'] as $locale => $script) {
            $footer = $this->footer($locale);
            $this->assertSame(array_keys($urls), $this->platforms($footer));
            preg_match_all('#aria-label="([^"(]+) \(#u', $footer, $names);
            $this->assertCount(8, array_unique($names[1]), $locale);
            if ($locale === 'en') {
                foreach ($names[1] as $name) {
                    $this->assertMatchesRegularExpression($script, $name);
                }
            }
            $this->assertSame(8, substr_count($footer, 'fill="currentColor"'));
        }
    }

    public function test_legacy_links_still_render_and_the_row_is_hidden_without_any(): void
    {
        $this->store(['instagram_url' => 'https://instagram.com/hirfah', 'facebook_url' => '', 'x_url' => 'https://x.com/hirfah']);
        $footer = $this->footer('ar');
        $this->assertSame(['instagram', 'x'], $this->platforms($footer));
        $this->assertStringNotContainsString('<img src="'.asset('assets/storefront/imgs/mcp/footer-x.svg'), $footer, 'The old image icons are no longer drawn.');

        $this->store(['instagram_url' => '', 'facebook_url' => 'http://127.0.0.1:8002/dashboard/settings', 'x_url' => null]);
        $footer = $this->footer('ar');
        $this->assertSame([], $this->platforms($footer));
        $this->assertStringNotContainsString('h-8 w-8', $footer, 'No social row at all.');
        $this->assertStringContainsString(Setting::footerDefaults()['copyright'], $footer, 'The rest of the footer is unchanged.');

        $this->store(['instagram_url' => 'https://instagram.com/hirfah', 'social_links' => []]);
        $this->assertSame([], $this->platforms($this->footer('ar')), 'A saved empty list shows nothing.');
    }

    public function test_rendering_writes_nothing(): void
    {
        $this->store(['social_links' => [['platform' => 'telegram', 'url' => 'https://t.me/hirfah', 'enabled' => true]]]);
        $settings = DB::table('settings')->get()->toArray();
        $translations = TranslationValue::count();

        foreach (['ar', 'en'] as $locale) {
            $this->assertSame(['telegram'], $this->platforms($this->footer($locale)));
        }

        $this->assertEquals($settings, DB::table('settings')->get()->toArray());
        $this->assertSame($translations, TranslationValue::count(), 'Names are read without creating translation rows.');
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

    /** @return list<string> */
    private function platforms(string $footer): array
    {
        preg_match_all('#data-footer-social="([a-z]+)"#', $footer, $matches);

        return $matches[1];
    }
}
