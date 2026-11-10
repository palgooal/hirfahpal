<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\StorefrontContentPage;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PAGES-05E: content page SEO for every enabled language of the registry: a self-referencing canonical, reciprocal
 * hreflang and x-default, and Open Graph tags on public translations only; "noindex, follow" and no canonical,
 * hreflang or Open Graph on a fallback; /sitemap-pages.xml with the public addresses and their alternates.
 */
class ContentPageSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->language('ar', 'العربية', true);
        $this->language('en', 'English');
        $this->withoutVite();
    }

    // ---------------------------------------------------------------- canonical and hreflang

    public function test_a_complete_translation_has_a_self_canonical_and_reciprocal_hreflang(): void
    {
        $this->language('fr', 'Français');
        $this->language('fa', 'فارسی', true);
        $this->page([
            'en' => ['title' => 'Our story', 'body_html' => '<p>English</p>'],
            'fr' => ['title' => 'Notre histoire', 'body_html' => '<p>Français</p>'],
            'fa' => ['title' => 'داستان ما', 'body_html' => '<p>فارسی</p>'],
        ]);
        $expected = ['ar' => url('/ar/pages/our-story'), 'en' => url('/en/pages/our-story'), 'fr' => url('/fr/pages/our-story'),
            'fa' => url('/fa/pages/our-story'), 'x-default' => url('/ar/pages/our-story')];

        foreach (['ar' => 'rtl', 'en' => 'ltr', 'fr' => 'ltr', 'fa' => 'rtl'] as $locale => $dir) {
            $head = $this->seoTags('/'.$locale.'/pages/our-story');
            $this->assertSame([url('/'.$locale.'/pages/our-story')], $head['canonical'], $locale);
            $this->assertSame($expected, $head['hreflang'], $locale.': the same set on every version.');
            $this->assertNull($head['robots'], $locale);
            $this->assertSame([$locale, $dir], [$head['lang'], $head['dir']], $locale);
        }
    }

    public function test_the_canonical_never_follows_the_session(): void
    {
        $this->page(['en' => ['title' => 'Our story', 'body_html' => '<p>English</p>']]);

        foreach (['ar', 'en'] as $session) {
            $this->assertSame([url('/en/pages/our-story')], $this->seoTags('/en/pages/our-story', $session)['canonical'], $session);
            $this->assertSame([url('/ar/pages/our-story')], $this->seoTags('/ar/pages/our-story', $session)['canonical'], $session);
        }
    }

    public function test_a_missing_or_incomplete_translation_is_noindex_without_canonical_or_hreflang(): void
    {
        $this->language('fr', 'Français');
        $this->page(['en' => ['title' => 'Title only', 'body_html' => '<p><img src="/storage/media/a.jpg" alt=""></p>']]);

        foreach (['en' => 'incomplete', 'fr' => 'missing'] as $locale => $case) {
            $head = $this->seoTags('/'.$locale.'/pages/our-story');
            $this->assertSame('noindex, follow', $head['robots'], $case);
            $this->assertSame([], $head['canonical'], $case);
            $this->assertSame([], $head['hreflang'], $case);
            $this->assertSame([], $head['og'], $case);
            $this->assertSame([$locale, 'ar', 'rtl'], [$head['lang'], $head['bodyLang'], $head['bodyDir']], $case.': the Arabic text keeps its language.');
            $this->assertStringStartsWith('قصتنا | ', $head['title'], $case.': the source metadata, not invented.');
        }

        // The public versions do not point to them.
        $head = $this->seoTags('/ar/pages/our-story');
        $this->assertSame(['ar' => url('/ar/pages/our-story'), 'x-default' => url('/ar/pages/our-story')], $head['hreflang']);
    }

    public function test_x_default_only_when_the_source_language_is_public(): void
    {
        $page = $this->page(['en' => ['title' => 'Our story', 'body_html' => '<p>English</p>']]);
        $page->translations()->where('locale', 'ar')->update(['body_html' => '<p> </p>']);

        $head = $this->seoTags('/en/pages/our-story');
        $this->assertSame(['en' => url('/en/pages/our-story')], $head['hreflang'], 'No x-default, no Arabic.');
        $this->assertSame('noindex, follow', $this->seoTags('/ar/pages/our-story')['robots'], 'An incomplete source is not indexed either.');
    }

    public function test_disabled_languages_and_drafts_never_appear(): void
    {
        $german = $this->language('de', 'Deutsch');
        $page = $this->page(['de' => ['title' => 'Unsere Geschichte', 'body_html' => '<p>Text</p>']]);
        $this->assertArrayHasKey('de', $this->seoTags('/ar/pages/our-story')['hreflang']);

        $german->update(['is_active' => false]);
        $this->assertArrayNotHasKey('de', $this->seoTags('/ar/pages/our-story')['hreflang']);
        $this->get('/de/pages/our-story')->assertNotFound();
        $this->assertStringNotContainsString('/de/pages/', $this->get('/sitemap-pages.xml')->getContent());

        $page->update(['status' => 'draft']);
        $this->get('/ar/pages/our-story')->assertNotFound();
        $this->assertStringNotContainsString('our-story', $this->get('/sitemap-pages.xml')->getContent());
        $this->assertSame([], $page->fresh()->publicAlternates());
    }

    // ---------------------------------------------------------------- metadata

    public function test_open_graph_and_metadata_come_from_the_translation_and_are_escaped(): void
    {
        $this->language('pt-br', 'Português (Brasil)');
        $this->page([
            'en' => ['title' => 'Our "story" <b>', 'excerpt' => 'Summary', 'meta_title' => 'SEO "title" & <script>', 'meta_description' => 'Description "quoted" <i>', 'body_html' => '<p>English</p>'],
            'pt-br' => ['title' => 'Nossa história', 'body_html' => '<p>Texto</p>'],
        ]);

        $html = $this->get('/en/pages/our-story')->assertOk()->getContent();
        $head = $this->seoTags('/en/pages/our-story');
        $this->assertSame('SEO "title" & <script> | Hirfah', $head['title']);
        $this->assertSame('Description "quoted" <i>', $head['description']);
        $this->assertSame(['og:type' => 'website', 'og:site_name' => 'Hirfah', 'og:title' => 'SEO "title" & <script>',
            'og:description' => 'Description "quoted" <i>', 'og:url' => url('/en/pages/our-story')], $head['og'], 'No og:locale for a bare language code.');
        $this->assertStringNotContainsString('<script> | ', $html, 'Escaped.');
        $this->assertSame(1, substr_count($html, '<title>'));
        $this->assertSame(1, substr_count($html, 'name="description"'));

        $this->assertSame('pt_BR', $this->seoTags('/pt-br/pages/our-story')['og']['og:locale']);
        $this->assertSame('Nossa história', $this->seoTags('/pt-br/pages/our-story')['og']['og:title'], 'Falls back to the title when no SEO title.');
    }

    // ---------------------------------------------------------------- sitemap

    public function test_the_sitemap_lists_public_addresses_with_their_alternates(): void
    {
        $this->language('fr', 'Français');
        $this->language('de', 'Deutsch', false, false);
        $this->page([
            'en' => ['title' => 'Our story', 'body_html' => '<p>English</p>'],
            'fr' => ['title' => 'Brouillon', 'body_html' => ''],
            'de' => ['title' => 'Unsere Geschichte', 'body_html' => '<p>Text</p>'],
        ]);
        StorefrontContentPage::create(['slug' => 'draft-page', 'title' => 'مسودة', 'body' => 'نص', 'status' => 'draft'])
            ->translations()->create(['locale' => 'ar', 'title' => 'مسودة', 'body_html' => '<p>نص</p>']);

        DB::enableQueryLog();
        $response = $this->withSession(['locale' => 'en'])->get('/sitemap-pages.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $queries = count(array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'storefront_content_page') || str_contains($q['query'], 'languages')));
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(3, $queries, 'Pages, translations and languages, once each.');

        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($response->getContent()), 'Well-formed XML.');
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xpath->registerNamespace('x', 'http://www.w3.org/1999/xhtml');
        $locs = array_map(fn ($node) => $node->textContent, iterator_to_array($xpath->query('//s:url/s:loc')));

        // The seven system pages in Arabic (no English), and the custom page in Arabic and English only.
        $this->assertCount(9, $locs);
        $this->assertContains(url('/ar/pages/our-story'), $locs);
        $this->assertContains(url('/en/pages/our-story'), $locs);
        $this->assertContains(url('/ar/pages/terms'), $locs);
        foreach (['/fr/', '/de/', 'draft-page', '/en/pages/terms', '/preview/', '/dashboard'] as $absent) {
            $this->assertEmpty(array_filter($locs, fn ($loc) => str_contains($loc, $absent)), $absent);
        }
        foreach ($locs as $loc) {
            $this->assertStringStartsWith(url('/'), $loc, 'Absolute.');
        }

        $alternates = fn (string $loc) => array_column(array_map(fn ($node) => [$node->getAttribute('hreflang'), $node->getAttribute('href')],
            iterator_to_array($xpath->query('//s:url[s:loc="'.$loc.'"]/x:link[@rel="alternate"]'))), 1, 0);
        $expected = ['ar' => url('/ar/pages/our-story'), 'en' => url('/en/pages/our-story'), 'x-default' => url('/ar/pages/our-story')];
        $this->assertSame($expected, $alternates(url('/ar/pages/our-story')));
        $this->assertSame($expected, $alternates(url('/en/pages/our-story')), 'Reciprocal.');
        $this->assertSame(1, $xpath->query('//s:url[s:loc="'.url('/ar/pages/our-story').'"]/s:lastmod')->length);

        // Not dependent on the visitor.
        $this->assertSame($response->getContent(), $this->withSession(['locale' => 'ar'])->get('/sitemap-pages.xml')->getContent());
    }

    // ---------------------------------------------------------------- unchanged

    public function test_previews_and_other_storefront_pages_are_unchanged(): void
    {
        $page = $this->page(['en' => ['title' => 'Our story', 'body_html' => '<p>English</p>']]);
        $preview = $this->actingAs($this->admin(), 'admin')->get(route('dashboard.content-pages.preview', [$page, 'en']))->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Cache-Control', 'no-store, private')->getContent();
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $preview);
        $this->assertStringNotContainsString('rel="canonical"', $preview);
        $this->assertStringNotContainsString('hreflang=', $preview);
        $this->assertStringNotContainsString('og:', $preview);

        foreach ([route('home'), route('categories'), route('contact.create')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('rel="canonical"', $html, $url);
            $this->assertStringNotContainsString('rel="alternate" hreflang', $html, $url);
            $this->assertStringNotContainsString('property="og:', $html, $url);
            $this->assertStringNotContainsString('name="robots"', $html, $url);
        }
    }

    /** @return array{title: string, description: ?string, robots: ?string, canonical: list<string>, hreflang: array<string, string>, og: array<string, string>, lang: string, dir: string, bodyLang: ?string, bodyDir: ?string} */
    private function seoTags(string $url, ?string $session = null): array
    {
        $request = $session ? $this->withSession(['locale' => $session]) : $this;
        $html = $request->get($url)->assertOk()->getContent();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($dom);
        $attr = fn (string $query, string $name) => array_map(fn ($node) => $node->getAttribute($name), iterator_to_array($xpath->query($query)));
        $article = $xpath->query('//article[@data-content-body]')->item(0);

        return [
            'title' => trim($xpath->query('//title')->item(0)->textContent),
            'description' => $attr('//meta[@name="description"]', 'content')[0] ?? null,
            'robots' => $attr('//meta[@name="robots"]', 'content')[0] ?? null,
            'canonical' => $attr('//link[@rel="canonical"]', 'href'),
            'hreflang' => array_combine($attr('//link[@rel="alternate"][@hreflang]', 'hreflang'), $attr('//link[@rel="alternate"][@hreflang]', 'href')),
            'og' => array_combine($attr('//meta[starts-with(@property, "og:")]', 'property'), $attr('//meta[starts-with(@property, "og:")]', 'content')),
            'lang' => $xpath->query('//html')->item(0)->getAttribute('lang'),
            'dir' => $xpath->query('//html')->item(0)->getAttribute('dir'),
            'bodyLang' => $article?->getAttribute('lang'),
            'bodyDir' => $article?->getAttribute('dir'),
        ];
    }

    private function language(string $code, string $native, bool $rtl = false, bool $active = true): Language
    {
        return Language::create(['name' => $native, 'native' => $native, 'code' => $code, 'is_rtl' => $rtl, 'is_active' => $active]);
    }

    private function page(array $translations = []): StorefrontContentPage
    {
        $page = StorefrontContentPage::create(['slug' => 'our-story', 'title' => 'قصتنا', 'body' => 'نص', 'status' => 'published']);
        $page->translations()->create(['locale' => 'ar', 'title' => 'قصتنا', 'body_html' => '<p>نص عربي</p>']);
        foreach ($translations as $locale => $values) {
            $page->translations()->create(['locale' => $locale, ...$values]);
        }

        return $page;
    }

    private function admin(): Admin
    {
        $admin = Admin::create(['name' => 'Viewer', 'email' => 'viewer@example.com', 'phone' => '0599000001', 'password' => 'password', 'status' => 'active', 'super_admin' => false]);
        RoleUser::query()->create(['role_name' => 'content-pages.view', 'user_id' => $admin->id, 'ability' => 'allow']);

        return $admin;
    }
}
