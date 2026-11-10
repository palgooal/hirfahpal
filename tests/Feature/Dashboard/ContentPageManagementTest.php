<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\StorefrontContentPage;
use App\Models\TranslationValue;
use Database\Seeders\AdminContentPageTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PAGES-03A: the content pages in the dashboard: content-pages.view / .edit (ContentPagePolicy), the list, the
 * bilingual form (titles, summaries, SEO texts; bodies read-only), saving through Eloquent, system pages locked and
 * published, and the private saved-content preview.
 */
class ContentPageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->withoutVite();
    }

    // ---------------------------------------------------------------- permissions

    public function test_only_admins_with_the_abilities_reach_the_pages(): void
    {
        $page = $this->page('terms');
        $routes = [
            ['get', route('dashboard.content-pages.index')],
            ['get', route('dashboard.content-pages.edit', $page)],
            ['get', route('dashboard.content-pages.preview', [$page, 'ar'])],
            ['put', route('dashboard.content-pages.update', $page)],
        ];

        foreach ($routes as [$method, $url]) {
            $this->{$method}($url, $this->payload())->assertRedirect();
        }

        $nobody = $this->admin([]);
        foreach ($routes as [$method, $url]) {
            $this->actingAs($nobody, 'admin')->{$method}($url, $this->payload())->assertForbidden();
        }

        $viewer = $this->admin(['content-pages.view']);
        foreach (array_slice($routes, 0, 3) as [$method, $url]) {
            $this->actingAs($viewer, 'admin')->get($url)->assertOk();
        }
        $this->actingAs($viewer, 'admin')->put(route('dashboard.content-pages.update', $page), $this->payload(['translations' => ['ar' => ['title' => 'X']]]))->assertForbidden();
        $form = $this->actingAs($viewer, 'admin')->get(route('dashboard.content-pages.edit', $page))->getContent();
        $this->assertStringContainsString('<fieldset class="admin-content-page-fields" disabled>', $form);
        $this->assertStringNotContainsString('type="submit"', $this->between($form, 'data-content-page-form', '</form>'));

        $editor = $this->admin(['content-pages.view', 'content-pages.edit']);
        $this->actingAs($editor, 'admin')->put(route('dashboard.content-pages.update', $page), $this->payload())->assertRedirect(route('dashboard.content-pages.edit', $page));

        $super = Admin::create(['name' => 'Super', 'email' => 'super@example.com', 'phone' => '0599000099', 'password' => 'password', 'status' => 'active', 'super_admin' => true]);
        $this->actingAs($super, 'admin')->get(route('dashboard.content-pages.index'))->assertOk();
    }

    public function test_the_sidebar_shows_the_entry_only_with_the_view_ability(): void
    {
        $this->seed(AdminContentPageTranslationSeeder::class);
        $home = route('dashboard.home');

        $with = $this->actingAs($this->admin(['content-pages.view']), 'admin')->withSession(['locale' => 'ar'])->get($home)->getContent();
        $this->assertStringContainsString('href="'.route('dashboard.content-pages.index').'"', $with);
        $this->assertStringContainsString('صفحات المحتوى', $with);

        $without = $this->actingAs($this->admin([]), 'admin')->get($home)->getContent();
        $this->assertStringNotContainsString(route('dashboard.content-pages.index'), $without);
    }

    // ---------------------------------------------------------------- list and form

    public function test_the_list_shows_every_page_with_its_state_language_and_actions(): void
    {
        $this->seed(AdminContentPageTranslationSeeder::class);
        $this->page('about')->translations()->create(['locale' => 'en', 'title' => 'Our story', 'body_html' => '']);

        $html = $this->actingAs($this->admin(['content-pages.view', 'content-pages.edit']), 'admin')->withSession(['locale' => 'en'])
            ->get(route('dashboard.content-pages.index'))->assertOk()->getContent();

        preg_match_all('#data-content-page="([a-z]+)"#', $html, $slugs);
        $this->assertSame(['about', 'authenticity', 'shipping', 'returns', 'faq', 'terms', 'privacy'], $slugs[1]);
        $this->assertSame(7, substr_count($html, 'data-content-status="published"'));
        // PAGES-05C: per language; English with a title but no content is incomplete, not present.
        $this->assertSame(7, substr_count($html, 'data-translation-status="ar:complete"'));
        $this->assertSame(6, substr_count($html, 'data-translation-status="en:missing"'));
        $this->assertSame(1, substr_count($html, 'data-translation-status="en:incomplete"'));
        $this->assertSame(7, substr_count($html, '<i class="ph-duotone ph-lock-simple" aria-hidden="true"></i>System page'));
        $this->assertStringContainsString('<span class="admin-content-pages-slug" dir="ltr">/pages/faq</span>', $html);
        $this->assertStringContainsString('href="'.route('dashboard.content-pages.preview', [$this->page('faq'), 'en']).'"', $html);
        $this->assertStringContainsString('<title>Content pages | ', $html);
    }

    public function test_the_form_shows_both_languages_and_the_editor_places(): void
    {
        $this->seed(AdminContentPageTranslationSeeder::class);
        $html = $this->actingAs($this->admin(['content-pages.view', 'content-pages.edit']), 'admin')->withSession(['locale' => 'ar'])
            ->get(route('dashboard.content-pages.edit', $this->page('faq')))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#id="translations-ar-title" name="translations\[ar\]\[title\]" value="الأسئلة الأكثر شيوعاً" maxlength="255" lang="ar" dir="rtl"#u', $html);
        $this->assertMatchesRegularExpression('#id="translations-en-title" name="translations\[en\]\[title\]" value="" maxlength="255" lang="en" dir="ltr"#', $html);
        $this->assertStringContainsString('<input type="hidden" name="status" value="published">', $html, 'A system page stays published.');
        $this->assertStringNotContainsString('<select id="status"', $html);
        $this->assertStringContainsString('<bdi dir="ltr">/pages/faq</bdi>', $html);
        $this->assertStringNotContainsString('name="slug"', $html);

        // PAGES-03B: each body: the saved content read-only until the editor starts, and a hidden, disabled field
        // (posted only once the content is changed).
        $this->assertMatchesRegularExpression('#<div class="admin-content-page-body hirfah-admin-prose" id="content-body-ar" role="region" tabindex="0" lang="ar" dir="rtl"\s+aria-labelledby="content-body-ar-label" aria-describedby="content-body-ar-hint" data-content-preview>\s*<p>كيف أتابع طلبي؟<br />#u', $html);
        $this->assertMatchesRegularExpression('#<textarea id="translations-ar-body_html" name="translations\[ar\]\[body_html\]" hidden disabled data-content-body-field>&lt;p&gt;كيف أتابع طلبي؟#u', $html);
        $this->assertStringContainsString('لا يوجد محتوى بهذه اللغة بعد.', $this->between($html, 'id="content-body-en"', '</div>'));
        $this->assertStringContainsString('<textarea id="translations-en-body_html" name="translations[en][body_html]" hidden disabled data-content-body-field></textarea>', $html);
    }

    // ---------------------------------------------------------------- saving

    public function test_saving_stores_both_languages_keeps_the_bodies_and_the_rollback_copy(): void
    {
        $page = $this->page('shipping');
        $arabicBody = $page->translations()->where('locale', 'ar')->value('body_html');
        $legacyBody = $page->body;
        $editor = $this->admin(['content-pages.view', 'content-pages.edit']);

        $this->actingAs($editor, 'admin')->put(route('dashboard.content-pages.update', $page), $this->payload([
            'translations' => [
                'ar' => ['title' => '  الشحن والتوصيل  ', 'excerpt' => 'ملخص جديد', 'meta_title' => 'الشحن | حرفة', 'meta_description' => 'وصف للبحث'],
                'en' => ['title' => 'Shipping', 'excerpt' => 'How delivery works.', 'meta_title' => '', 'meta_description' => ''],
            ],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('dashboard.content-pages.edit', $page));

        $page->refresh();
        $arabic = $page->translations()->where('locale', 'ar')->first();
        $english = $page->translations()->where('locale', 'en')->first();
        $this->assertSame(['الشحن والتوصيل', 'ملخص جديد', 'الشحن | حرفة', 'وصف للبحث'], [$arabic->title, $arabic->excerpt, $arabic->meta_title, $arabic->meta_description]);
        $this->assertSame($arabicBody, $arabic->body_html, 'The body is never part of this save.');
        $this->assertSame(['Shipping', 'How delivery works.', null, null, ''], [$english->title, $english->excerpt, $english->meta_title, $english->meta_description, $english->body_html]);
        $this->assertSame(['الشحن والتوصيل', 'ملخص جديد', $legacyBody], [$page->title, $page->excerpt, $page->body], 'The rollback copy: Arabic title and summary, body untouched.');
        $this->assertSame([$editor->id, 'published', true], [$page->updated_by, $page->status, $page->is_published]);

        // English emptied again: its row (no body) goes; the page shows Arabic.
        $this->actingAs($editor, 'admin')->put(route('dashboard.content-pages.update', $page), $this->payload([
            'translations' => ['ar' => ['title' => 'الشحن والتوصيل'], 'en' => ['title' => '', 'excerpt' => '']],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(['ar'], $page->translations()->pluck('locale')->all());
    }

    public function test_an_english_row_with_content_keeps_needing_a_title(): void
    {
        $page = $this->page('about');
        $page->translations()->create(['locale' => 'en', 'title' => 'About', 'body_html' => '<p>English body</p>']);

        $this->save($page, ['translations' => ['ar' => ['title' => 'عن حرفة'], 'en' => ['title' => '']]])->assertSessionHasErrors('translations.en.title');
        $this->assertSame('About', $page->translations()->where('locale', 'en')->value('title'));
        $this->assertSame('<p>English body</p>', $page->translations()->where('locale', 'en')->value('body_html'));
    }

    public function test_invalid_oversized_and_forbidden_input_is_refused_and_nothing_is_saved(): void
    {
        $page = $this->page('privacy');
        $before = [DB::table('storefront_content_pages')->where('id', $page->id)->first(), DB::table('storefront_content_page_translations')->where('page_id', $page->id)->get()->toArray()];

        $cases = [
            'translations.ar.title' => ['translations' => ['ar' => ['title' => '']]],
            'translations.ar.title ' => ['translations' => ['ar' => ['title' => str_repeat('ع', 256)]]],
            'translations.ar.excerpt' => ['translations' => ['ar' => ['title' => 'ت', 'excerpt' => str_repeat('ع', 501)]]],
            'translations.en.meta_title' => ['translations' => ['ar' => ['title' => 'ت'], 'en' => ['title' => 'T', 'meta_title' => str_repeat('a', 256)]]],
            'translations.en.meta_description' => ['translations' => ['ar' => ['title' => 'ت'], 'en' => ['title' => 'T', 'meta_description' => str_repeat('a', 501)]]],
            'translations.en.title' => ['translations' => ['ar' => ['title' => 'ت'], 'en' => ['excerpt' => 'Only a summary']]],
            'translations.ar.body_html' => ['translations' => ['ar' => ['title' => 'ت', 'body_html' => '<script>alert(1)</script>']]],
            'translations' => ['translations' => ['ar' => ['title' => 'ت'], 'fr' => ['title' => 'Conditions']]],
            'translations.ar.title  ' => ['translations' => ['ar' => ['title' => ['nested']]]],
            'slug' => ['slug' => 'other', 'translations' => ['ar' => ['title' => 'ت']]],
            'status' => ['status' => 'archived', 'translations' => ['ar' => ['title' => 'ت']]],
            'status ' => ['status' => 'draft', 'translations' => ['ar' => ['title' => 'ت']]],
        ];

        foreach ($cases as $field => $payload) {
            $this->save($page, $payload)->assertSessionHasErrors(trim($field));
            $this->assertEquals($before, [DB::table('storefront_content_pages')->where('id', $page->id)->first(), DB::table('storefront_content_page_translations')->where('page_id', $page->id)->get()->toArray()], $field);
        }
    }

    public function test_a_non_system_page_can_be_a_draft_and_status_follows_is_published(): void
    {
        $page = StorefrontContentPage::create(['slug' => 'campaign', 'title' => 'حملة', 'body' => 'نص', 'status' => 'published']);
        $page->translations()->create(['locale' => 'ar', 'title' => 'حملة', 'body_html' => '<p>نص</p>']);

        $this->save($page, ['status' => 'draft', 'translations' => ['ar' => ['title' => 'حملة']]])->assertSessionHasNoErrors();
        $this->assertSame(['draft', false], [$page->fresh()->status, $page->fresh()->is_published]);
        $this->get(route('content.show', ['locale' => 'ar', 'slug' => 'campaign']))->assertNotFound();

        $this->save($page, ['status' => 'published', 'translations' => ['ar' => ['title' => 'حملة']]])->assertSessionHasNoErrors();
        $this->assertSame(['published', true], [$page->fresh()->status, $page->fresh()->is_published]);
        $this->get(route('content.show', ['locale' => 'ar', 'slug' => 'campaign']))->assertOk();
    }

    public function test_after_a_failed_save_the_form_keeps_what_was_sent_and_links_the_error(): void
    {
        $this->seed(AdminContentPageTranslationSeeder::class);
        $html = $this->actingAs($this->admin(['content-pages.view', 'content-pages.edit']), 'admin')->withSession(['locale' => 'en'])
            ->from(route('dashboard.content-pages.edit', $this->page('faq')))->followingRedirects()
            ->put(route('dashboard.content-pages.update', $this->page('faq')), $this->payload(['translations' => ['ar' => ['title' => 'عنوان مرسل'], 'en' => ['excerpt' => 'Sent summary']]]))
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#name="translations\[ar\]\[title\]" value="عنوان مرسل"#u', $html);
        $this->assertStringContainsString('>Sent summary</textarea>', $html);
        $this->assertStringContainsString('<li><a href="#translations-en-title">Page title (English)</a>: ', $html);
        $this->assertMatchesRegularExpression('#id="translations-en-title"[^>]*class="form-control is-invalid"\s+aria-invalid="true" aria-describedby="translations-en-title-error"#', $html);
    }

    // ---------------------------------------------------------------- preview

    public function test_the_preview_shows_the_saved_page_privately_drafts_included(): void
    {
        $this->seed(AdminContentPageTranslationSeeder::class);
        $page = StorefrontContentPage::create(['slug' => 'draft-page', 'title' => 'مسودة', 'body' => 'نص المسودة']);
        $page->translations()->create(['locale' => 'ar', 'title' => 'مسودة', 'body_html' => '<p>نص المسودة</p>']);
        $viewer = $this->admin(['content-pages.view']);
        $rows = TranslationValue::count();

        $this->get(route('content.show', ['locale' => 'ar', 'slug' => 'draft-page']))->assertNotFound();

        $response = $this->actingAs($viewer, 'admin')->get(route('dashboard.content-pages.preview', [$page, 'en']))->assertOk();
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $html = $response->getContent();
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
        $this->assertStringContainsString('data-content-preview="draft"', $html);
        $this->assertStringContainsString('It is a draft: visitors cannot see it.', $html);
        $this->assertStringContainsString('data-content-preview-fallback', $html, 'English falls back to Arabic, and says so.');
        $this->assertMatchesRegularExpression('#<html lang="en" dir="ltr"#', $html);
        $this->assertStringContainsString('lang="ar" dir="rtl" data-content-body><p>نص المسودة</p></article>', $html);
        $this->assertStringContainsString('href="'.route('dashboard.content-pages.edit', $page).'"', $html);
        $this->assertStringNotContainsString('<footer', $html, 'No storefront footer in the preview.');

        $arabic = $this->actingAs($viewer, 'admin')->get(route('dashboard.content-pages.preview', [$page, 'ar']))->assertOk()->getContent();
        $this->assertStringContainsString('<html lang="ar" dir="rtl"', $arabic);
        $this->assertStringNotContainsString('data-content-preview-fallback', $arabic);

        $this->actingAs($viewer, 'admin')->get('/dashboard/content-pages/'.$page->id.'/preview/fr')->assertNotFound();
        $this->assertSame($rows, TranslationValue::count(), 'Previews create no translation rows.');
    }

    // ---------------------------------------------------------------- storefront heading (PAGES-03A)

    public function test_the_storefront_heading_follows_the_content_language(): void
    {
        $en = $this->withSession(['locale' => 'en'])->get(route('content.show', ['locale' => 'en', 'slug' => 'about']))->assertOk()->getContent();
        $this->assertStringNotContainsString('Hirfah information', $en);
        $this->assertStringNotContainsString('معلومات حِرفة', $en);
        $this->assertMatchesRegularExpression('#<div class="mx-auto max-w-\[1216px\] text-start" lang="ar" dir="rtl" data-content-header>\s*<h1 #', $en, 'The Arabic fallback reads as one right-to-left block.');

        $ar = $this->withSession(['locale' => 'ar'])->get(route('content.show', ['locale' => 'ar', 'slug' => 'about']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#lang="ar" dir="rtl" data-content-header>\s*<p class="mb-2 text-xs font-bold text-copper">معلومات حِرفة</p>\s*<h1 #u', $ar);
    }

    // ---------------------------------------------------------------- translations

    public function test_the_seeder_covers_exactly_the_texts_the_dashboard_reads(): void
    {
        $sources = ['resources/views/dashboard/content-pages/index.blade.php', 'resources/views/dashboard/content-pages/edit.blade.php',
            'resources/views/storefront/partials/content-preview-banner.blade.php', 'app/Http/Requests/Dashboard/UpdateContentPageRequest.php', 'app/Http/Requests/Dashboard/StoreContentPageRequest.php',
            'app/Http/Controllers/Dashboard/ContentPageManagementController.php'];
        $read = [];
        foreach ($sources as $source) {
            preg_match_all("/(?:\\\$cpText|\\\$this->text)\('([A-Za-z0-9_]+)', '([^']+)'\)/", file_get_contents(base_path($source)), $matches, PREG_SET_ORDER);
            foreach ($matches as [, $key, $default]) {
                $this->assertSame($read['dashboard.'.$key] ?? $default, $default, $key.' has one default everywhere.');
                $read['dashboard.'.$key] = $default;
            }
        }
        $nav = file_get_contents(resource_path('views/layouts/partials/dashboard/nav.blade.php'));
        $this->assertStringContainsString("'key' => 'dashboard.Nav_Content_Pages', 'default' => 'Content pages'", $nav);
        $read['dashboard.Nav_Content_Pages'] = 'Content pages';

        ksort($read);
        $seeded = AdminContentPageTranslationSeeder::TRANSLATIONS;
        ksort($seeded);
        $this->assertSame(array_keys($read), array_keys($seeded));
        foreach ($seeded as $key => $values) {
            $this->assertSame(['ar', 'en'], array_keys($values), $key);
            $this->assertSame($read[$key], $values['en'], $key);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], $key);
        }
        $this->assertStringNotContainsString('AdminContentPageTranslationSeeder', file_get_contents(database_path('seeders/DatabaseSeeder.php')));
    }

    private function save(StorefrontContentPage $page, array $payload)
    {
        return $this->actingAs($this->admin(['content-pages.view', 'content-pages.edit']), 'admin')
            ->from(route('dashboard.content-pages.edit', $page))->put(route('dashboard.content-pages.update', $page), $this->payload($payload));
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['status' => 'published', 'translations' => ['ar' => ['title' => 'عنوان']]], $overrides);
    }

    private function page(string $slug): StorefrontContentPage
    {
        return StorefrontContentPage::query()->where('slug', $slug)->firstOrFail();
    }

    private function between(string $html, string $start, string $end): string
    {
        $from = strpos($html, $start);
        $this->assertNotFalse($from, $start);

        return substr($html, $from, strpos($html, $end, $from) - $from);
    }

    private function admin(array $abilities): Admin
    {
        $number = Admin::query()->count() + 1;
        $admin = Admin::create(['name' => 'Admin '.$number, 'email' => 'admin'.$number.'@example.com',
            'phone' => '05990000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'password' => 'password', 'status' => 'active', 'super_admin' => false]);
        foreach ($abilities as $ability) {
            RoleUser::query()->create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }
}
