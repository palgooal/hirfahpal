<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\StorefrontContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PAGES-05C: the content pages dashboard in every enabled language of the registry: one tab and panel per language
 * (name and direction from the registry), translation states in the list, and the preview for any enabled language
 * with a fallback notice that says whether the translation is missing or incomplete.
 */
class ContentPageDashboardLanguagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->language('ar', 'Arabic', 'العربية', true);
        $this->language('en', 'English', 'English');
        $this->withoutVite();
    }

    // ---------------------------------------------------------------- tabs

    public function test_arabic_and_english_tabs_come_from_the_registry(): void
    {
        $html = $this->edit($this->custom());

        $this->assertSame(['ar', 'en'], $this->tabs($html));
        $this->assertStringContainsString('<span lang="ar" dir="rtl">العربية</span>', $html);
        $this->assertStringContainsString('<span lang="en" dir="ltr">English</span>', $html);
        $this->assertStringContainsString('data-content-language="ar" data-content-direction="rtl"', $html);
        $this->assertStringContainsString('data-content-language="en" data-content-direction="ltr"', $html);
        $this->assertStringContainsString('data-language-requirement="required">Required: a title and content.', $html);
        $this->assertStringContainsString('data-language-requirement="optional">Optional. Until its title and content are both filled in, visitors see the العربية page.', $html);
        $this->assertMatchesRegularExpression('#name="translations\[ar\]\[title\]"[^>]*required aria-required="true"#', $html);
    }

    public function test_a_third_language_and_an_rtl_one_get_their_own_tabs_fields_and_direction(): void
    {
        $this->language('fr', 'French', 'Français');
        $this->language('fa', 'Persian', 'فارسی', true);
        $page = $this->custom();
        $page->translations()->create(['locale' => 'fr', 'title' => 'Notre histoire', 'excerpt' => 'Résumé', 'meta_description' => 'Description', 'body_html' => '<p>Texte</p>']);

        $html = $this->edit($page);

        $this->assertSame(['ar', 'en', 'fr', 'fa'], $this->tabs($html), 'Source first, then the registry order.');
        foreach (['fr' => 'ltr', 'fa' => 'rtl'] as $locale => $dir) {
            foreach (['title', 'excerpt', 'meta_title', 'meta_description'] as $field) {
                $this->assertMatchesRegularExpression('#name="translations\['.$locale.'\]\['.$field.'\]"[^>]*lang="'.$locale.'" dir="'.$dir.'"#', $html, $locale.' '.$field);
            }
            $this->assertStringContainsString('data-content-editor data-locale="'.$locale.'" data-editable="true"', $html);
            $this->assertMatchesRegularExpression('#data-content-editor data-locale="'.$locale.'"[^>]*\s+data-body-sent="false" lang="'.$locale.'" dir="'.$dir.'"#', $html);
            $this->assertStringContainsString('name="translations['.$locale.'][body_html]" hidden disabled', $html);
            $this->assertStringContainsString(route('dashboard.content-pages.preview', [$page, $locale]), $html);
        }
        // Saved values, labels from the registry.
        $this->assertStringContainsString('value="Notre histoire"', $html);
        $this->assertStringContainsString('>Résumé</textarea>', $html);
        $this->assertStringContainsString('<p>Texte</p>', $html);
        $this->assertStringContainsString('Page title (Français)', $html);
        $this->assertStringContainsString('Preview in فارسی', $html);
        $this->assertSame(4, substr_count($html, 'data-content-editor data-locale='), 'One editor per language, from one template.');

        // The whole form saves every language, each in its own row.
        $this->save($page, ['translations' => ['ar' => ['title' => 'قصة'], 'en' => ['title' => ''], 'fr' => ['title' => 'Notre histoire'], 'fa' => ['title' => 'داستان', 'body_html' => '<p>متن</p>']]])->assertSessionHasNoErrors();
        $rows = $page->translations()->get()->keyBy('locale');
        $this->assertSame(['Notre histoire', '<p>Texte</p>', 'داستان', '<p>متن</p>'], [$rows['fr']->title, $rows['fr']->body_html, $rows['fa']->title, $rows['fa']->body_html]);
    }

    public function test_a_disabled_language_is_not_shown_and_its_translation_is_kept(): void
    {
        $this->language('de', 'German', 'Deutsch', false, false);
        $page = $this->custom();
        $page->translations()->create(['locale' => 'de', 'title' => 'Unsere Geschichte', 'body_html' => '<p>Text</p>']);

        $html = $this->edit($page);
        $this->assertSame(['ar', 'en'], $this->tabs($html));
        $this->assertStringNotContainsString('translations[de]', $html);
        $this->assertStringNotContainsString('Unsere Geschichte', $html);

        $this->save($page, ['translations' => ['ar' => ['title' => 'قصة'], 'en' => ['title' => '']]])->assertSessionHasNoErrors();
        $this->assertSame('Unsere Geschichte', $page->translations()->where('locale', 'de')->value('title'));
        $this->assertStringNotContainsString('data-translation-status="de:', $this->index());
    }

    public function test_old_input_of_any_language_comes_back_and_its_tab_opens(): void
    {
        $this->language('fr', 'French', 'Français');
        $page = $this->custom();

        $html = $this->actingAs($this->admin(), 'admin')->from(route('dashboard.content-pages.edit', $page))->followingRedirects()
            ->put(route('dashboard.content-pages.update', $page), ['status' => 'draft', 'translations' => [
                'ar' => ['title' => 'قصة'], 'en' => ['title' => 'Kept'], 'fr' => ['title' => '', 'excerpt' => 'Résumé non enregistré', 'body_html' => '<p>Texte <script>x</script></p>'],
            ]])->assertOk()->getContent();

        $this->assertStringContainsString('data-initial-language="fr"', $html);
        $this->assertStringContainsString('>Résumé non enregistré</textarea>', $html);
        $this->assertStringContainsString('value="Kept"', $html);
        $this->assertStringContainsString('data-body-sent="true" lang="fr" dir="ltr"', $html);
        $this->assertStringContainsString('hidden disabled data-content-body-field>&lt;p&gt;Texte &lt;/p&gt;</textarea>', $html, 'Sent body, cleaned.');
        $this->assertStringContainsString('<a href="#translations-fr-title">Page title (Français)</a>: ', $html);
        $this->assertSame(0, $page->translations()->where('locale', 'fr')->count());
    }

    // ---------------------------------------------------------------- list

    public function test_the_list_shows_missing_incomplete_and_complete_per_language(): void
    {
        $this->language('fr', 'French', 'Français');
        $this->language('fa', 'Persian', 'فارسی', true);
        $page = $this->custom();
        $page->translations()->create(['locale' => 'en', 'title' => 'Title only', 'body_html' => '']);
        $page->translations()->create(['locale' => 'fr', 'title' => 'Notre histoire', 'body_html' => '<p>Texte</p>']);

        $html = $this->index();
        $row = substr($html, strpos($html, 'data-content-page="custom-page"'));
        $row = substr($row, 0, strpos($row, '</li>'));

        $this->assertStringContainsString('data-translation-status="ar:complete"', $row);
        $this->assertStringContainsString('data-translation-status="en:incomplete"', $row, 'A title alone is not complete.');
        $this->assertStringContainsString('data-translation-status="fr:complete"', $row);
        $this->assertStringContainsString('data-translation-status="fa:missing"', $row);
        $this->assertStringContainsString('<span lang="fa" dir="rtl">فارسی</span><span class="visually-hidden">: Missing</span>', $row);
        $this->assertStringContainsString('title="English: Incomplete"', $row);
        $this->assertSame(4, substr_count($row, 'role="listitem"'));
        $this->assertStringContainsString('lang="ar" dir="rtl"', $row, 'The title in the source language.');
        $this->assertStringNotContainsString('No English version', $html);
    }

    // ---------------------------------------------------------------- preview

    public function test_the_preview_accepts_registry_languages_only_and_stays_private(): void
    {
        $this->language('fr', 'French', 'Français');
        $this->language('de', 'German', 'Deutsch', false, false);
        $page = $this->custom();
        $viewer = $this->admin(['content-pages.view']);
        $url = fn (string $locale) => '/dashboard/content-pages/'.$page->id.'/preview/'.$locale;

        $this->get($url('fr'))->assertRedirect();
        $this->actingAs($this->admin([]), 'admin')->get($url('fr'))->assertForbidden();
        $this->actingAs($viewer, 'admin')->get($url('fr'))->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('data-content-preview="draft"', false);

        foreach (['de', 'xx', 'FR', 'french', 'f'] as $locale) {
            $this->actingAs($viewer, 'admin')->get($url($locale))->assertNotFound();
        }
        $this->get('/pages/custom-page')->assertNotFound();
    }

    public function test_the_preview_says_whether_a_translation_is_missing_or_incomplete(): void
    {
        $this->language('fr', 'French', 'Français');
        $this->language('fa', 'Persian', 'فارسی', true);
        $page = $this->custom();
        $page->translations()->create(['locale' => 'fr', 'title' => 'Brouillon', 'body_html' => '<p> </p>']);
        $viewer = $this->admin(['content-pages.view']);
        $preview = fn (string $locale) => $this->actingAs($viewer, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.content-pages.preview', [$page, $locale]))->assertOk()->getContent();

        $html = $preview('fr');
        $this->assertStringContainsString('data-content-preview-fallback="incomplete">The Français version is not complete yet (it needs a title and content), so the العربية one is shown.', $html);
        $this->assertStringContainsString('lang="ar" dir="rtl" data-content-body><p>نص</p>', $html);

        $html = $preview('fa');
        $this->assertStringContainsString('data-content-preview-fallback="missing">There is no فارسی version yet, so the العربية one is shown.', $html);

        $page->translations()->where('locale', 'fr')->update(['body_html' => '<p>Texte</p>']);
        $html = $preview('fr');
        $this->assertStringNotContainsString('data-content-preview-fallback', $html);
        $this->assertStringContainsString('lang="fr" dir="ltr" data-content-body><p>Texte</p>', $html);
        $this->assertStringNotContainsString('data-content-preview-fallback', $preview('ar'));
    }

    // ---------------------------------------------------------------- workflows

    public function test_create_shows_every_language_and_publishing_still_works(): void
    {
        $this->language('fr', 'French', 'Français');
        $creator = $this->admin(['content-pages.view', 'content-pages.create', 'content-pages.edit']);

        $form = $this->actingAs($creator, 'admin')->get(route('dashboard.content-pages.create'))->assertOk()->getContent();
        $this->assertSame(['ar', 'en', 'fr'], $this->tabs($form));
        $this->assertStringNotContainsString('/preview/', $form);

        $this->actingAs($creator, 'admin')->post(route('dashboard.content-pages.store'), ['slug' => 'trois-langues', 'status' => 'draft', 'translations' => [
            'ar' => ['title' => 'ثلاث لغات', 'body_html' => '<p>نص</p>'], 'en' => ['title' => ''], 'fr' => ['title' => 'Trois langues', 'body_html' => '<p>Texte</p>'],
        ]])->assertSessionHasNoErrors();
        $page = StorefrontContentPage::query()->where('slug', 'trois-langues')->firstOrFail();
        $this->assertSame(['ar', 'fr'], $page->completeLocales());
        $this->get('/pages/trois-langues')->assertNotFound();

        $this->actingAs($creator, 'admin')->put(route('dashboard.content-pages.update', $page), ['status' => 'published', 'translations' => ['ar' => ['title' => 'ثلاث لغات'], 'fr' => ['title' => 'Trois langues']]])
            ->assertSessionHasNoErrors();
        $this->assertTrue($page->fresh()->hasPublicTranslation('fr'));
        $this->get('/ar/pages/trois-langues')->assertOk();
    }

    public function test_the_tabs_scroll_on_small_screens_instead_of_overflowing(): void
    {
        $css = file_get_contents(resource_path('css/admin-dashboard.css'));

        $this->assertMatchesRegularExpression('/\.admin-content-page-tabs \{[^}]*overflow-x: auto;/', $css);
        $this->assertMatchesRegularExpression('/\.admin-content-page-tab \{[^}]*flex: none;[^}]*min-height: 2\.75rem;/', $css, 'Tabs keep their size and stay 44px tall.');
        $this->assertMatchesRegularExpression('/\.admin-content-pages-languages \{[^}]*flex-wrap: wrap;/', $css);
    }

    private function tabs(string $html): array
    {
        preg_match_all('#data-language-tab="([a-z-]+)"#', $html, $matches);

        return $matches[1];
    }

    private function edit(StorefrontContentPage $page): string
    {
        return $this->actingAs($this->admin(), 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.content-pages.edit', $page))->assertOk()->getContent();
    }

    private function index(): string
    {
        return $this->actingAs($this->admin(), 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.content-pages.index'))->assertOk()->getContent();
    }

    private function save(StorefrontContentPage $page, array $payload)
    {
        return $this->actingAs($this->admin(), 'admin')->put(route('dashboard.content-pages.update', $page), ['status' => 'draft', ...$payload]);
    }

    private function language(string $code, string $name, string $native, bool $rtl = false, bool $active = true): Language
    {
        return Language::create(['name' => $name, 'native' => $native, 'code' => $code, 'is_rtl' => $rtl, 'is_active' => $active]);
    }

    private function custom(): StorefrontContentPage
    {
        $page = StorefrontContentPage::create(['slug' => 'custom-page', 'title' => 'قصة', 'body' => 'نص', 'status' => 'draft']);
        $page->translations()->create(['locale' => 'ar', 'title' => 'قصة', 'body_html' => '<p>نص</p>']);

        return $page;
    }

    private function admin(array $abilities = ['content-pages.view', 'content-pages.edit']): Admin
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
