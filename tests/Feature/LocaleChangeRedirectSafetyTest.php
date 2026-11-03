<?php

namespace Tests\Feature;

use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-01 (PRODUCT-AUDIT-01 H1): GET /change-locale/{locale}?redirect=… follows only same-site destinations: a path
 * with one leading slash, or an absolute http(s) URL on the site's host. Protocol-relative, backslash, control
 * character, other-host, other-scheme and credential-bearing values fall back to the previous same-site page or the
 * home page; a Referer from another site is not followed either. The locale is still stored.
 */
class LocaleChangeRedirectSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::query()->create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::query()->create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    public function test_external_protocol_relative_and_malformed_destinations_are_refused(): void
    {
        $refused = [
            '//evil.example/x',
            '///evil.example/x',
            '/\\evil.example/x',
            "/\t/evil.example/x",
            "/\n/evil.example",
            'https://evil.example/x',
            'http://evil.example',
            'javascript://localhost/%0Aalert(1)',
            'ftp://localhost/x',
            'http://user@evil.example/',
            'http://localhost@evil.example/',
            'https://user:pass@localhost/x',
            'http://localhost\\@evil.example/',
            'evil.example',
            'not a url',
        ];

        foreach ($refused as $redirect) {
            // Refused: back to the (same-site) page the visitor came from instead.
            $this->get('/change-locale/en?'.http_build_query(['redirect' => $redirect]), ['Referer' => url('/categories')])
                ->assertRedirect(url('/categories'));

            // Without a Referer: never off the site and never back to the switch itself.
            $location = $this->get('/change-locale/en?'.http_build_query(['redirect' => $redirect]))->headers->get('Location');
            $this->assertSame(parse_url(url('/'), PHP_URL_HOST), parse_url($location, PHP_URL_HOST), 'Refused: '.json_encode($redirect));
            $this->assertStringNotContainsString('change-locale', $location, 'Refused: '.json_encode($redirect));
        }
        $this->assertSame('en', session('locale'), 'The locale is still stored.');
    }

    public function test_same_site_destinations_are_kept(): void
    {
        $kept = [
            '/browse' => url('/browse'),
            '/product?slug=clay-jar' => url('/product?slug=clay-jar'),
            '/browse?q=%D8%AC%D8%B1%D8%A9&sort=price-asc' => url('/browse?q=%D8%AC%D8%B1%D8%A9&sort=price-asc'),
            url('/vendors/vendor') => url('/vendors/vendor'),
        ];

        foreach ($kept as $redirect => $expected) {
            $this->get('/change-locale/ar?'.http_build_query(['redirect' => $redirect]))->assertRedirect($expected);
        }
        $this->assertSame('ar', session('locale'));
    }

    public function test_the_previous_page_is_followed_only_when_it_is_on_this_site(): void
    {
        $this->get('/change-locale/en', ['Referer' => url('/browse?q=jar')])->assertRedirect(url('/browse?q=jar'));
        $this->get('/change-locale/en', ['Referer' => 'https://evil.example/page'])->assertRedirect(url('/'));
        $this->get('/change-locale/en', ['Referer' => '//evil.example/page'])->assertRedirect(url('/'));
    }

    public function test_an_unknown_locale_changes_nothing_and_still_redirects_safely(): void
    {
        $this->get('/change-locale/ar');
        $this->get('/change-locale/xx?'.http_build_query(['redirect' => '//evil.example']))->assertRedirect(url('/'));
        $this->assertSame('ar', session('locale'));
    }

    public function test_repeating_a_switch_without_a_referer_does_not_loop(): void
    {
        // The session's previous URL is the switch itself after the first request; it is never redirected back to.
        $this->get('/change-locale/en')->assertRedirect();
        $this->get('/change-locale/en')->assertRedirect(url('/'));
        $this->get('/change-locale/ar?redirect=%2F%2Fevil.example')->assertRedirect(url('/'));
    }
}
