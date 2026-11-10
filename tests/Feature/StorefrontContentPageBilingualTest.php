<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\StorefrontContentPage;
use App\Models\TranslationValue;
use App\Support\Content\PageContentSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PAGES-02: the seven pages carried into the bilingual tables without loss, status kept in step with is_published,
 * and /pages/{slug} shown in Arabic or English (Arabic when English is missing), its body cleaned on render.
 */
class StorefrontContentPageBilingualTest extends TestCase
{
    use RefreshDatabase;

    private const SYSTEM = ['about', 'authenticity', 'shipping', 'returns', 'faq', 'terms', 'privacy'];

    protected function setUp(): void
    {
        parent::setUp();

        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->withoutVite();
    }

    // ---------------------------------------------------------------- migration

    public function test_the_seven_pages_are_carried_over_losslessly_with_their_legacy_columns_untouched(): void
    {
        $pages = DB::table('storefront_content_pages')->orderBy('id')->get();
        $this->assertSame(self::SYSTEM, $pages->pluck('slug')->all());

        foreach ($pages as $page) {
            $this->assertSame('published', $page->status, $page->slug);
            $this->assertSame(1, (int) $page->is_published);
            $this->assertSame(1, (int) $page->is_system);
            $this->assertNotNull($page->published_at);
            $this->assertNull($page->updated_by);

            $rows = DB::table('storefront_content_page_translations')->where('page_id', $page->id)->get();
            $this->assertSame(['ar'], $rows->pluck('locale')->all(), $page->slug.': Arabic only.');
            $row = $rows->first();
            $this->assertSame($page->title, $row->title);
            $this->assertSame($page->excerpt, $row->excerpt);
            $this->assertSame(PageContentSanitizer::fromPlainText($page->body), $row->body_html, $page->slug.': the migration converts as the class does.');
            // Already clean: the sanitizer only writes <br> as <br />.
            $this->assertSame($row->body_html, str_replace('<br />', '<br>', app(PageContentSanitizer::class)->sanitize($row->body_html)), $page->slug);

            // The text is the same, block by block.
            $text = html_entity_decode(strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $row->body_html)), ENT_QUOTES | ENT_HTML5);
            $this->assertSame($this->normalize($page->body), $this->normalize($text), $page->slug);
            $this->assertSame(count(preg_split('/\n\s*\n/', trim($page->body))), substr_count($row->body_html, '<p>'), $page->slug.': one paragraph per block.');
        }

        $faq = DB::table('storefront_content_page_translations')->where('page_id', DB::table('storefront_content_pages')->where('slug', 'faq')->value('id'))->value('body_html');
        $this->assertSame(3, substr_count($faq, '<br>'), 'The three question / answer line breaks of the FAQ.');
    }

    public function test_running_the_migration_again_changes_nothing_and_it_rolls_back_cleanly(): void
    {
        $migration = require database_path('migrations/2026_11_09_000000_add_bilingual_content_to_storefront_content_pages.php');
        $before = DB::table('storefront_content_page_translations')->orderBy('id')->get()->toArray();
        $pages = DB::table('storefront_content_pages')->orderBy('id')->get(['id', 'slug', 'title', 'excerpt', 'body', 'is_published'])->toArray();

        $migration->up();
        $this->assertEquals($before, DB::table('storefront_content_page_translations')->orderBy('id')->get()->toArray());

        $migration->down();
        StorefrontContentPage::forgetBilingualReady();
        $this->assertFalse(Schema::hasTable('storefront_content_page_translations'));
        $this->assertFalse(Schema::hasColumn('storefront_content_pages', 'status'));
        $this->assertEquals($pages, DB::table('storefront_content_pages')->orderBy('id')->get(['id', 'slug', 'title', 'excerpt', 'body', 'is_published'])->toArray(), 'The legacy columns survive the rollback.');

        // Before the migration (or after a rollback) the page still renders from its legacy columns.
        $this->get(route('content.show', ['locale' => 'ar', 'slug' => 'faq']))->assertOk()->assertSee('<br />', false);

        $migration->up();
        StorefrontContentPage::forgetBilingualReady();
        $this->assertSame(7, DB::table('storefront_content_page_translations')->count());
    }

    public function test_status_and_is_published_always_agree(): void
    {
        $page = StorefrontContentPage::query()->where('slug', 'terms')->first();

        $page->update(['status' => 'draft']);
        $this->assertFalse($page->fresh()->is_published);

        $page->update(['is_published' => true]);
        $this->assertSame('published', $page->fresh()->status);

        $new = StorefrontContentPage::create(['slug' => 'new-page', 'title' => 'T', 'body' => 'B']);
        $this->assertSame(['draft', false, null], [$new->fresh()->status, $new->fresh()->is_published, $new->fresh()->published_at]);

        $new->update(['status' => 'published']);
        $this->assertNotNull($new->fresh()->published_at);
        $this->assertTrue($new->fresh()->is_published);

        $new->update(['status' => 'archived']);
        $this->assertSame(['draft', false], [$new->fresh()->status, $new->fresh()->is_published], 'An unknown status is a draft.');
    }

    // ---------------------------------------------------------------- rendering

    public function test_arabic_pages_render_as_before_with_their_paragraphs_and_line_breaks(): void
    {
        $html = $this->page('faq', 'ar');

        $this->assertMatchesRegularExpression('#<article class="hirfah-prose [^"]*" lang="ar" dir="rtl" data-content-body><p>[^<]+<br />[^<]+</p>#u', $html);
        $this->assertStringNotContainsString('whitespace-pre-line', $html);
        // PAGES-03A: the whole header carries the content's lang/dir; the small heading on Arabic pages only.
        $this->assertMatchesRegularExpression('#text-start" lang="ar" dir="rtl" data-content-header>\s*<p class="mb-2 text-xs font-bold text-copper">معلومات حِرفة</p>\s*<h1 [^>]*>الأسئلة الأكثر شيوعاً</h1>#u', $html);
        $this->assertStringContainsString('<title>الأسئلة الأكثر شيوعاً | حِرفة</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="إجابات مختصرة حول الطلبات والحساب.">', $html);
    }

    public function test_english_shows_its_own_translation_and_falls_back_to_arabic_with_its_language(): void
    {
        // Without English: the Arabic content, marked Arabic, inside an English page.
        $html = $this->page('about', 'en');
        $this->assertStringContainsString('<html lang="en" dir="ltr"', $html);
        // PAGES-03A: no generic English heading; the Arabic header reads as one right-to-left block.
        $this->assertStringNotContainsString('Hirfah information', $html);
        $this->assertMatchesRegularExpression('#text-start" lang="ar" dir="rtl" data-content-header>\s*<h1 [^>]*>قصة المنصة ورؤيتها</h1>#u', $html);
        $this->assertStringContainsString('lang="ar" dir="rtl" data-content-body', $html);
        $this->assertStringContainsString('<title>قصة المنصة ورؤيتها | Hirfah</title>', $html);

        // With English: its own text, SEO texts first.
        $page = StorefrontContentPage::query()->where('slug', 'about')->first();
        $page->translations()->create(['locale' => 'en', 'title' => 'Our story', 'excerpt' => 'Who we are.', 'body_html' => '<h2>Mission</h2><p>Hand<strong>made</strong>.</p>',
            'meta_title' => 'About Hirfah', 'meta_description' => 'The story of Hirfah.']);

        $en = $this->page('about', 'en');
        $this->assertMatchesRegularExpression('#text-start" lang="en" dir="ltr" data-content-header>\s*<h1 [^>]*>Our story</h1>\s*<p [^>]*>Who we are.</p>#', $en);
        $this->assertStringContainsString('lang="en" dir="ltr" data-content-body><h2>Mission</h2><p>Hand<strong>made</strong>.</p></article>', $en);
        $this->assertStringContainsString('<title>About Hirfah | Hirfah</title>', $en);
        $this->assertStringContainsString('<meta name="description" content="The story of Hirfah.">', $en);

        // Arabic is unchanged by the English row; an English row without a title is not used.
        $this->assertMatchesRegularExpression('#<h1 [^>]*>قصة المنصة ورؤيتها</h1>#u', $this->page('about', 'ar'));
        $page->translations()->where('locale', 'en')->update(['title' => '  ']);
        $this->assertMatchesRegularExpression('#text-start" lang="ar" dir="rtl" data-content-header>#', $this->page('about', 'en'));
    }

    public function test_stored_unsafe_html_is_cleaned_when_shown(): void
    {
        $page = StorefrontContentPage::query()->where('slug', 'privacy')->first();
        $page->translations()->where('locale', 'ar')->update(['body_html' => '<p onclick="alert(1)">نص</p><script>alert(1)</script><a href="javascript:alert(1)">رابط</a><img src="https://evil.example/x.jpg" onerror="alert(1)"><iframe src="https://evil.example"></iframe>']);

        $body = $this->between($this->page('privacy', 'ar'), 'data-content-body>', '</article>');

        $this->assertSame('<p>نص</p><a rel="noopener noreferrer">رابط</a>', $body);
    }

    public function test_drafts_unknown_slugs_and_an_empty_title_fall_back_safely(): void
    {
        StorefrontContentPage::query()->where('slug', 'returns')->first()->update(['status' => 'draft']);

        $this->get(route('content.show', ['locale' => 'ar', 'slug' => 'returns']))->assertNotFound();
        $this->get(route('content.show', ['locale' => 'ar', 'slug' => 'no-such-page']))->assertNotFound();
        $this->get('/pages/'.rawurlencode('../about'))->assertNotFound();

        // A page without any translation row shows its legacy Arabic columns.
        $page = StorefrontContentPage::create(['slug' => 'legacy-only', 'title' => 'صفحة', 'excerpt' => null, 'body' => "سطر\n\nآخر", 'status' => 'published']);
        $this->assertStringContainsString('lang="ar" dir="rtl" data-content-body><p>سطر</p>'."\n".'<p>آخر</p></article>', $this->page('legacy-only', 'en'));
        $this->assertStringContainsString('<meta name="description" content="صفحة">', $this->page('legacy-only', 'ar'), 'No excerpt: the title.');
        $this->assertSame(0, $page->translations()->count());
    }

    public function test_get_requests_create_no_translation_rows_and_never_write(): void
    {
        $translations = TranslationValue::count();
        $pages = DB::table('storefront_content_pages')->get()->toArray();
        $rows = DB::table('storefront_content_page_translations')->get()->toArray();

        foreach (self::SYSTEM as $slug) {
            foreach (['ar', 'en'] as $locale) {
                $this->page($slug, $locale);
            }
        }

        $this->assertSame($translations, TranslationValue::count());
        $this->assertEquals($pages, DB::table('storefront_content_pages')->get()->toArray());
        $this->assertEquals($rows, DB::table('storefront_content_page_translations')->get()->toArray());
    }

    private function page(string $slug, string $locale): string
    {
        return $this->withSession(['locale' => $locale])->get(route('content.show', ['locale' => $locale, 'slug' => $slug]))->assertOk()->getContent();
    }

    private function between(string $html, string $start, string $end): string
    {
        $from = strpos($html, $start);
        $this->assertNotFalse($from);
        $from += strlen($start);

        return substr($html, $from, strpos($html, $end, $from) - $from);
    }

    private function normalize(string $text): string
    {
        return preg_replace('/\s+/u', ' ', trim($text));
    }
}
