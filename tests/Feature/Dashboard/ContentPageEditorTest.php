<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\StorefrontContentPage;
use App\Support\Content\PageContentSanitizer;
use Database\Seeders\AdminContentPageTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PAGES-03B: the page bodies from the visual editor: saved only when sent, always cleaned, the Arabic one kept as
 * plain text on the page; the editor's places in the form (disabled field until changed, the picker in callback
 * mode, the language tabs); permissions and limits.
 */
class ContentPageEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://hirfah.test']);
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->withoutVite();
    }

    // ---------------------------------------------------------------- saving

    public function test_a_sent_body_is_cleaned_saved_and_the_arabic_one_kept_as_plain_text(): void
    {
        $page = $this->page('returns');

        $this->save($page, ['translations' => [
            'ar' => ['title' => 'الاستبدال', 'body_html' => '<h2 onclick="x()">العنوان</h2><p>نص <strong>غامق</strong><script>alert(1)</script></p>'
                .'<p><a href="javascript:alert(1)">رابط</a> <a href="https://example.org">موقع</a></p>'
                .'<img src="https://hirfah.test/storage/media/2026/10/a.jpg" alt="صورة"><img src="https://evil.example/x.jpg" alt="x">'
                .'<table><tr><th>أ</th><td>ب</td></tr></table><iframe src="https://evil.example"></iframe>'],
            'en' => ['title' => 'Returns', 'body_html' => '<p>Return within <em>14 days</em>.</p>'],
        ]])->assertSessionHasNoErrors();

        $arabic = $page->translations()->where('locale', 'ar')->value('body_html');
        $this->assertSame('<h2>العنوان</h2><p>نص <strong>غامق</strong></p><p><a rel="noopener noreferrer">رابط</a> <a href="https://example.org" rel="noopener noreferrer">موقع</a></p>'
            .'<img src="/storage/media/2026/10/a.jpg" alt="صورة" /><table><tr><th>أ</th><td>ب</td></tr></table>', $arabic);
        $this->assertSame('<p>Return within <em>14 days</em>.</p>', $page->translations()->where('locale', 'en')->value('body_html'));
        $this->assertSame("العنوان\n\nنص غامق\n\nرابط موقع\n\nأ ب", $page->fresh()->body, 'The rollback copy: plain text.');

        // The storefront shows the cleaned body.
        $html = $this->withSession(['locale' => 'en'])->get(route('content.show', ['locale' => 'en', 'slug' => 'returns']))->assertOk()->getContent();
        $this->assertStringContainsString('lang="en" dir="ltr" data-content-body><p>Return within <em>14 days</em>.</p></article>', $html);
    }

    public function test_saving_without_a_body_keeps_the_stored_body_byte_for_byte(): void
    {
        $page = $this->page('faq');
        $before = [$page->translations()->where('locale', 'ar')->value('body_html'), $page->body];

        $this->save($page, ['translations' => ['ar' => ['title' => 'الأسئلة', 'excerpt' => 'ملخص']]])->assertSessionHasNoErrors();

        $this->assertSame($before, [$page->translations()->where('locale', 'ar')->value('body_html'), $page->fresh()->body]);
        $this->assertSame('الأسئلة', $page->translations()->where('locale', 'ar')->value('title'));
    }

    public function test_the_legacy_plain_text_survives_a_round_trip_through_the_editor_html(): void
    {
        foreach (StorefrontContentPage::query()->get() as $page) {
            $html = app(PageContentSanitizer::class)->sanitize(PageContentSanitizer::fromPlainText($page->body));
            $this->assertSame(trim(str_replace("\r\n", "\n", $page->body)), PageContentSanitizer::toPlainText($html), $page->slug);
        }
    }

    public function test_an_english_body_alone_creates_the_english_page_only_with_a_title(): void
    {
        $page = $this->page('about');

        $this->save($page, ['translations' => ['ar' => ['title' => 'عن حرفة'], 'en' => ['title' => '', 'body_html' => '<p>Our story.</p>']]])
            ->assertSessionHasErrors('translations.en.title');
        $this->assertSame(0, $page->translations()->where('locale', 'en')->count());

        // An empty English editor (Tiptap's empty document) is no content: nothing is created.
        $this->save($page, ['translations' => ['ar' => ['title' => 'عن حرفة'], 'en' => ['title' => '', 'body_html' => '<p></p>']]])->assertSessionHasNoErrors();
        $this->assertSame(0, $page->translations()->where('locale', 'en')->count());

        $this->save($page, ['translations' => ['ar' => ['title' => 'عن حرفة'], 'en' => ['title' => 'About', 'body_html' => '<p>Our story.</p>']]])->assertSessionHasNoErrors();
        $this->assertSame('<p>Our story.</p>', $page->translations()->where('locale', 'en')->value('body_html'));

        // English emptied (title and content): its row goes, the page shows Arabic again.
        $this->save($page, ['translations' => ['ar' => ['title' => 'عن حرفة'], 'en' => ['title' => '', 'body_html' => '<p></p>']]])->assertSessionHasNoErrors();
        $this->assertSame(0, $page->translations()->where('locale', 'en')->count());
    }

    // ---------------------------------------------------------------- refusals

    public function test_oversized_empty_and_malformed_bodies_are_refused_and_nothing_is_saved(): void
    {
        $page = $this->page('privacy');
        $before = $this->snapshot($page);

        $cases = [
            'translations.ar.body_html' => [str_repeat('a', PageContentSanitizer::MAX_LENGTH + 1)],
            'translations.ar.body_html ' => ['<p></p>'],
            'translations.ar.body_html  ' => ['<script>alert(1)</script><style>p{}</style>'],
            'translations.ar.body_html   ' => [['<p>x</p>']],
        ];

        foreach ($cases as $field => [$body]) {
            $this->save($page, ['translations' => ['ar' => ['title' => 'الخصوصية', 'body_html' => $body]]])->assertSessionHasErrors(trim($field));
            $this->assertEquals($before, $this->snapshot($page), $field);
        }

        // Exactly at the limit: accepted, never cut.
        $body = '<p>'.str_repeat('ب', PageContentSanitizer::MAX_LENGTH - 7).'</p>';
        $this->assertSame(PageContentSanitizer::MAX_LENGTH, mb_strlen($body));
        $this->save($page, ['translations' => ['ar' => ['title' => 'الخصوصية', 'body_html' => $body]]])->assertSessionHasNoErrors();
        $this->assertSame($body, $page->translations()->where('locale', 'ar')->value('body_html'));
    }

    public function test_only_editors_can_send_a_body(): void
    {
        $page = $this->page('terms');
        $before = $this->snapshot($page);
        $payload = $this->payload(['translations' => ['ar' => ['title' => 'شروط', 'body_html' => '<p>جديد</p>']]]);

        $this->put(route('dashboard.content-pages.update', $page), $payload)->assertRedirect();
        $this->actingAs($this->admin(['content-pages.view']), 'admin')->put(route('dashboard.content-pages.update', $page), $payload)->assertForbidden();
        $this->actingAs($this->admin([]), 'admin')->put(route('dashboard.content-pages.update', $page), $payload)->assertForbidden();

        $this->assertEquals($before, $this->snapshot($page));
    }

    public function test_slugs_and_the_published_state_of_system_pages_stay(): void
    {
        $page = $this->page('shipping');

        $this->save($page, ['slug' => 'delivery', 'translations' => ['ar' => ['title' => 'الشحن', 'body_html' => '<p>نص</p>']]])->assertSessionHasErrors('slug');
        $this->save($page, ['status' => 'draft', 'translations' => ['ar' => ['title' => 'الشحن', 'body_html' => '<p>نص</p>']]])->assertSessionHasErrors('status');
        $this->assertSame(['shipping', 'published', true], [$page->fresh()->slug, $page->fresh()->status, $page->fresh()->is_published]);
    }

    // ---------------------------------------------------------------- the form

    public function test_the_form_holds_the_editor_places_for_an_editor_and_none_for_a_viewer(): void
    {
        $this->seed(AdminContentPageTranslationSeeder::class);
        $page = $this->page('faq');

        $editor = $this->form($this->admin(['content-pages.view', 'content-pages.edit', 'media.view']), $page, 'en');
        $this->assertSame(2, substr_count($editor, 'data-content-editor data-locale='));
        $this->assertStringContainsString('data-content-editor data-locale="ar" data-editable="true"', $editor);
        $this->assertStringContainsString('data-body-sent="false" lang="ar" dir="rtl"', $editor);
        $this->assertStringContainsString('data-body-sent="false" lang="en" dir="ltr"', $editor);
        $this->assertSame(2, substr_count($editor, 'hidden disabled data-content-body-field'));
        $this->assertStringContainsString('<p class="admin-media-hint mb-0" role="status" dir="auto" data-content-editor-status>Loading the editor…</p>', $editor);
        $this->assertStringContainsString('<script type="application/json" data-content-editor-config>', $editor);
        $this->assertStringContainsString('"canBrowseMedia":true', $editor);
        $this->assertStringContainsString('data-link-dialog', $editor);
        $this->assertStringContainsString('data-image-dialog', $editor);
        $this->assertStringContainsString(':not([data-editor-state]) [data-content-editor-status]', $editor, 'A script that never runs does not leave "Loading".');
        $this->assertStringContainsString('data-admin-media-picker  data-picker-mode="callback"', $editor);
        $this->assertStringContainsString('role="tablist" aria-label="Languages" data-language-tablist data-initial-language="ar" hidden', $editor);
        $this->assertStringNotContainsString('name="content_page_image"', $editor, 'The picker in callback mode posts nothing.');

        // Without the media library, no picker (and no image tool, see the config).
        $noMedia = $this->form($this->admin(['content-pages.view', 'content-pages.edit']), $page, 'en');
        $this->assertStringContainsString('"canBrowseMedia":false', $noMedia);
        $this->assertStringNotContainsString('data-picker-mode="callback"', $noMedia);

        // A viewer: the content read-only, no editor, no dialogs.
        $viewer = $this->form($this->admin(['content-pages.view']), $page, 'en');
        $this->assertStringContainsString('data-content-editor data-locale="ar" data-editable="false"', $viewer);
        $this->assertStringNotContainsString('data-content-editor-config', $viewer);
        $this->assertStringNotContainsString('data-link-dialog', $viewer);
        $this->assertStringContainsString('You can view this content but not change it.', $viewer);
    }

    public function test_after_a_failed_save_the_sent_body_comes_back_cleaned_and_its_tab_opens(): void
    {
        $this->seed(AdminContentPageTranslationSeeder::class);
        $page = $this->page('about');

        $html = $this->actingAs($this->admin(['content-pages.view', 'content-pages.edit']), 'admin')->withSession(['locale' => 'en'])
            ->from(route('dashboard.content-pages.edit', $page))->followingRedirects()
            ->put(route('dashboard.content-pages.update', $page), $this->payload(['translations' => [
                'ar' => ['title' => 'عن حرفة'],
                'en' => ['title' => '', 'body_html' => '<p>Unsaved <strong>story</strong><script>x</script></p>'],
            ]]))->assertOk()->getContent();

        $this->assertStringContainsString('data-initial-language="en"', $html);
        $this->assertStringContainsString('data-body-sent="true" lang="en" dir="ltr"', $html);
        $this->assertStringContainsString('data-body-sent="false" lang="ar" dir="rtl"', $html);
        $this->assertStringContainsString('hidden disabled data-content-body-field>&lt;p&gt;Unsaved &lt;strong&gt;story&lt;/strong&gt;&lt;/p&gt;</textarea>', $html);
        $this->assertMatchesRegularExpression('#id="language-tab-en"[^>]*>\s*<span lang="en" dir="ltr">English</span>\s*<span class="admin-content-page-tab-alert">#', $html);
    }

    public function test_the_category_form_keeps_its_media_picker_field(): void
    {
        $html = $this->actingAs($this->admin(['categories.view', 'categories.create', 'media.view']), 'admin')
            ->get(route('dashboard.categories.create'))->assertOk()->getContent();

        $this->assertStringContainsString('name="image_media_id"', $html);
        $this->assertStringContainsString('name="remove_image"', $html);
        $this->assertStringNotContainsString('data-picker-mode="callback"', $html);
        $this->assertStringContainsString('data-picker-open', $html);
    }

    public function test_pages_03c_an_english_only_save_leaves_every_arabic_byte_as_it_was(): void
    {
        $page = $this->page('about');
        $arabic = fn () => [
            DB::table('storefront_content_page_translations')->where('page_id', $page->id)->where('locale', 'ar')->first(),
            DB::table('storefront_content_pages')->where('id', $page->id)->first(['title', 'excerpt', 'body']),
        ];
        $before = $arabic();
        $this->travel(5)->minutes();

        // The form posts the Arabic title and summary as shown, and no Arabic body (the Arabic editor was not touched).
        $this->save($page, ['translations' => [
            'ar' => ['title' => $before[0]->title, 'excerpt' => $before[0]->excerpt, 'meta_title' => '', 'meta_description' => ''],
            'en' => ['title' => 'About Hirfah', 'excerpt' => 'Our story', 'body_html' => '<h2>Our story</h2><p>Handmade.</p>'],
        ]])->assertSessionHasNoErrors();

        $this->assertEquals($before, $arabic(), 'Arabic row (updated_at included) and the rollback copy unchanged.');
        $this->assertSame('<h2>Our story</h2><p>Handmade.</p>', $page->translations()->where('locale', 'en')->value('body_html'));

        // And again with the English page changed a second time.
        $this->save($page, ['translations' => [
            'ar' => ['title' => $before[0]->title, 'excerpt' => $before[0]->excerpt],
            'en' => ['title' => 'About', 'body_html' => '<p>Changed.</p>'],
        ]])->assertSessionHasNoErrors();
        $this->assertEquals($before, $arabic());
    }

    public function test_pages_03c_the_keyboard_hint_waits_for_the_editor_and_images_can_be_described_again(): void
    {
        $this->seed(AdminContentPageTranslationSeeder::class);
        $page = $this->page('faq');

        $editor = $this->form($this->admin(['content-pages.view', 'content-pages.edit', 'media.view']), $page, 'en');
        $this->assertMatchesRegularExpression('#<p class="admin-media-hint mb-0" id="content-body-ar-hint"\s+hidden\s*>Alt\+F10 moves#', $editor);
        $this->assertStringContainsString('data-image-insert data-update-label="Save description">Insert image</button>', $editor);

        $viewer = $this->form($this->admin(['content-pages.view']), $page, 'en');
        $this->assertMatchesRegularExpression('#<p class="admin-media-hint mb-0" id="content-body-ar-hint"\s*>You can view this content but not change it\.</p>#', $viewer);

        // A saved image keeps its description, so the editor opens it with it.
        $this->save($page, ['translations' => ['ar' => ['title' => 'الأسئلة', 'body_html' => '<p>نص</p><img src="/storage/media/2026/10/a.jpg" alt="تطريز يدوي">']]])->assertSessionHasNoErrors();
        $this->assertStringContainsString('<img src="/storage/media/2026/10/a.jpg" alt="تطريز يدوي" />', $this->form($this->admin(['content-pages.view', 'content-pages.edit']), $page, 'ar'));
    }

    private function snapshot(StorefrontContentPage $page): array
    {
        return [DB::table('storefront_content_pages')->where('id', $page->id)->first(), DB::table('storefront_content_page_translations')->where('page_id', $page->id)->orderBy('id')->get()->toArray()];
    }

    private function form(Admin $admin, StorefrontContentPage $page, string $locale): string
    {
        return $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get(route('dashboard.content-pages.edit', $page))->assertOk()->getContent();
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
