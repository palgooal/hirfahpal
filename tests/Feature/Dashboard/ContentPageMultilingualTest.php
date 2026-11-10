<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\StorefrontContentPage;
use App\Models\StorefrontContentPageTranslation;
use App\Support\Content\PageContentSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * PAGES-05B: content pages in every enabled language of the registry (languages.is_active), Arabic as the source
 * language: dynamic validation and saving, a disabled language's translation kept, one completeness rule, and the
 * direction from Language.is_rtl.
 */
class ContentPageMultilingualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->language('ar', 'Arabic', true);
        $this->language('en', 'English');
        $this->withoutVite();
    }

    // ---------------------------------------------------------------- registry and direction

    public function test_the_editable_languages_follow_the_registry_with_the_source_first(): void
    {
        $this->assertSame(['ar', 'en'], StorefrontContentPage::editableLocales());

        $french = $this->language('fr', 'French');
        $this->language('fa', 'Persian', true);
        $this->language('de', 'German', false, false);
        $this->assertSame(['ar', 'en', 'fr', 'fa'], StorefrontContentPage::editableLocales(), 'Enabled ones only, read again after a change.');

        $french->update(['is_active' => false]);
        $this->assertSame(['ar', 'en', 'fa'], StorefrontContentPage::editableLocales());

        // The source language stays editable even if it were disabled.
        Language::query()->where('code', 'ar')->first()->update(['is_active' => false]);
        $this->assertSame(['ar', 'en', 'fa'], StorefrontContentPage::editableLocales());
    }

    public function test_direction_comes_from_is_rtl(): void
    {
        $this->language('fa', 'Persian', true);
        $this->language('fr', 'French');
        $this->language('he', 'Hebrew', true, false);

        $this->assertSame(['rtl', 'ltr', 'rtl', 'ltr', 'rtl'], array_map(StorefrontContentPage::directionOf(...), ['ar', 'en', 'fa', 'fr', 'he']), 'A disabled language keeps its direction.');
        $this->assertSame('ltr', StorefrontContentPage::directionOf('xx'), 'Unknown: left to right.');

        Language::query()->delete();
        $this->assertSame('rtl', StorefrontContentPage::directionOf('ar'), 'The source language without a registry row.');

        $this->language('fa', 'Persian', true);
        $page = $this->pageWith(['fa' => ['title' => 'درباره', 'body_html' => '<p>متن</p>']]);
        $this->assertSame(['fa', 'rtl', false], [$page->contentFor('fa')['locale'], $page->contentFor('fa')['dir'], $page->contentFor('fa')['fallback']]);
    }

    // ---------------------------------------------------------------- validation

    public function test_a_third_language_and_an_rtl_one_are_validated_and_saved(): void
    {
        $this->language('fr', 'French');
        $this->language('fa', 'Persian', true);
        $page = $this->custom();

        $this->save($page, ['translations' => [
            'ar' => ['title' => 'قصتنا'],
            'fr' => ['title' => 'Notre histoire', 'excerpt' => 'Résumé', 'meta_title' => 'SEO', 'body_html' => '<p>Le <script>x()</script>texte</p>'],
            'fa' => ['title' => 'داستان ما', 'body_html' => '<p>متن</p>'],
        ]])->assertSessionHasNoErrors();

        $rows = $page->translations()->get()->keyBy('locale');
        $this->assertSame(['ar', 'fa', 'fr'], $rows->keys()->sort()->values()->all());
        $this->assertSame(['Notre histoire', 'Résumé', 'SEO', '<p>Le texte</p>'], [$rows['fr']->title, $rows['fr']->excerpt, $rows['fr']->meta_title, $rows['fr']->body_html]);
        $this->assertSame('<p>متن</p>', $rows['fa']->body_html);

        // Limits and the "needs a title" rule apply to every language, named from the registry.
        $this->save($page, ['translations' => ['ar' => ['title' => 'قصتنا'], 'fr' => ['title' => str_repeat('a', 256)]]])->assertSessionHasErrors('translations.fr.title');
        $this->save($page, ['translations' => ['ar' => ['title' => 'قصتنا'], 'fa' => ['excerpt' => 'خلاصه']]])->assertSessionHasErrors('translations.fa.title');
        $this->save($page, ['translations' => ['ar' => ['title' => 'قصتنا'], 'fr' => ['title' => '', 'body_html' => '<p>Texte</p>']]])
            ->assertSessionHasErrors(['translations.fr.title' => 'The French page has content, so it needs a title.']);
        $this->assertSame('Notre histoire', $page->translations()->where('locale', 'fr')->value('title'), 'A refused save changes nothing.');

        $form = $this->actingAs($this->admin(), 'admin')->get(route('dashboard.content-pages.edit', $page))->getContent();
        $this->assertStringContainsString('Page title (English)', $form, 'Existing labels unchanged.');
    }

    public function test_disabled_unknown_and_malformed_languages_are_refused_not_ignored(): void
    {
        $this->language('de', 'German', false, false);
        $page = $this->custom();

        foreach ([['de' => ['title' => 'Titel']], ['xx' => ['title' => 'x']], ['AR' => ['title' => 'x']]] as $extra) {
            $this->save($page, ['translations' => ['ar' => ['title' => 'جديد'], ...$extra]])
                ->assertSessionHasErrors(['translations' => 'Content was sent for a language that is not enabled.']);
        }
        $this->save($page, ['translations' => 'ar'])->assertSessionHasErrors('translations');
        $this->save($page, ['translations' => ['ar' => 'title']])->assertSessionHasErrors('translations.ar');
        $this->save($page, ['translations' => ['ar' => ['title' => 'x', 'slug' => 'y']]])->assertSessionHasErrors('translations.ar');

        $this->assertSame(['قصة', 0], [$page->fresh()->title, $page->translations()->where('locale', 'de')->count()]);
    }

    public function test_the_source_language_title_and_body_stay_required(): void
    {
        $creator = $this->admin(['content-pages.view', 'content-pages.create']);
        $post = fn (array $translations) => $this->actingAs($creator, 'admin')->post(route('dashboard.content-pages.store'), ['slug' => 'new-page', 'status' => 'draft', 'translations' => $translations]);

        $post(['en' => ['title' => 'Only English', 'body_html' => '<p>Text</p>']])->assertSessionHasErrors(['translations.ar', 'translations.ar.title']);
        $post(['ar' => ['title' => 'عنوان']])->assertSessionHasErrors(['translations.ar.body_html' => 'The Arabic page needs some content.']);
        $post(['ar' => ['title' => 'عنوان', 'body_html' => '<p> </p>']])->assertSessionHasErrors('translations.ar.body_html');
        $post(['ar' => ['title' => '', 'body_html' => '<p>نص</p>']])->assertSessionHasErrors('translations.ar.title');
        $this->assertNull(StorefrontContentPage::query()->where('slug', 'new-page')->first());

        $post(['ar' => ['title' => 'عنوان', 'body_html' => '<p>نص</p>']])->assertSessionHasNoErrors();
    }

    // ---------------------------------------------------------------- completeness

    public function test_incomplete_translations_are_saved_but_never_shown(): void
    {
        $this->language('fr', 'French');
        $page = $this->custom('published');

        // A title only: a draft of that language.
        $this->save($page, ['translations' => ['ar' => ['title' => 'قصة'], 'fr' => ['title' => 'Brouillon', 'excerpt' => 'À finir']]])->assertSessionHasNoErrors();
        $page->refresh();
        $this->assertSame(['Brouillon', 'À finir', ''], [$page->translations->firstWhere('locale', 'fr')->title, $page->translations->firstWhere('locale', 'fr')->excerpt, $page->translations->firstWhere('locale', 'fr')->body_html]);
        $this->assertFalse(StorefrontContentPage::isTranslationComplete($page->translations->firstWhere('locale', 'fr')));
        $this->assertFalse($page->hasPublicTranslation('fr'));
        $this->assertSame(['ar', true], [$page->contentFor('fr')['locale'], $page->contentFor('fr')['fallback']], 'The public page falls back to Arabic, as before.');

        // Completed later.
        $this->save($page, ['translations' => ['ar' => ['title' => 'قصة'], 'fr' => ['title' => 'Notre histoire', 'body_html' => '<p>Texte</p>']]])->assertSessionHasNoErrors();
        $page->refresh();
        $this->assertSame(['ar', 'fr'], $page->completeLocales());
        $this->assertTrue($page->hasPublicTranslation('fr'));
        $this->assertSame(['fr', 'ltr', 'Notre histoire'], [$page->contentFor('fr')['locale'], $page->contentFor('fr')['dir'], $page->contentFor('fr')['title']]);

        // Completeness is not the page's state: a draft page has no public translation.
        $page->update(['status' => 'draft']);
        $this->assertSame(['ar', 'fr'], $page->fresh()->completeLocales());
        $this->assertFalse($page->fresh()->hasPublicTranslation('fr'));
    }

    public function test_empty_markup_whitespace_and_an_image_alone_are_not_content(): void
    {
        foreach (['', '<p></p>', '<p> </p>', '<p><br></p>', '<p>&nbsp;</p>', "<p>\u{200B}</p>", '<h2> </h2><hr>', '<p><img src="/storage/media/a.jpg" alt=""></p>'] as $html) {
            $this->assertFalse(PageContentSanitizer::hasText($html), $html);
        }
        $this->assertTrue(PageContentSanitizer::hasText('<p><img src="/storage/media/a.jpg" alt="">نص</p>'));
        $this->assertTrue(PageContentSanitizer::hasContent('<p><img src="/storage/media/a.jpg" alt=""></p>'), 'The existing body rule (PAGES-03B) is unchanged.');

        $page = $this->pageWith(['en' => ['title' => 'Image only', 'body_html' => '<p><img src="/storage/media/a.jpg" alt=""></p>']]);
        $this->assertSame([], array_values(array_diff($page->completeLocales(), ['ar'])));
        $page = $this->pageWith(['en' => ['title' => 'Unsafe only', 'body_html' => '<script>alert(1)</script><p> </p>']]);
        $this->assertFalse($page->hasPublicTranslation('en'), 'Judged after cleaning.');
        $page = $this->pageWith(['en' => ['title' => '  ', 'body_html' => '<p>Text</p>']]);
        $this->assertFalse($page->hasPublicTranslation('en'), 'A blank title.');
    }

    // ---------------------------------------------------------------- persistence

    public function test_a_disabled_language_keeps_its_translation_through_saves(): void
    {
        $french = $this->language('fr', 'French');
        $page = $this->pageWith(['fr' => ['title' => 'Notre histoire', 'excerpt' => 'Résumé', 'body_html' => '<p>Texte</p>']]);
        $before = $page->translations()->where('locale', 'fr')->first()->only(['title', 'excerpt', 'body_html', 'updated_at']);
        $this->assertTrue($page->hasPublicTranslation('fr'));

        $french->update(['is_active' => false]);
        $this->save($page, ['translations' => ['ar' => ['title' => 'جديد'], 'en' => ['title' => '', 'body_html' => '<p></p>']]])->assertSessionHasNoErrors();
        $page->refresh();
        $this->assertSame('جديد', $page->title);
        $this->assertEquals($before, $page->translations->firstWhere('locale', 'fr')->only(['title', 'excerpt', 'body_html', 'updated_at']), 'Kept as it was.');
        $this->assertFalse($page->hasPublicTranslation('fr'), 'Not shown while disabled.');
        $this->assertSame('ar', $page->contentFor('fr')['locale']);

        $french->update(['is_active' => true]);
        $this->assertTrue($page->fresh()->hasPublicTranslation('fr'), 'Back when enabled again.');
    }

    public function test_an_enabled_language_left_out_of_the_form_is_not_changed(): void
    {
        $this->language('fr', 'French');
        $page = $this->pageWith(['fr' => ['title' => 'Notre histoire', 'body_html' => '<p>Texte</p>'], 'en' => ['title' => 'Our story', 'body_html' => '<p>Text</p>']]);

        // Today's form sends Arabic and English only.
        $this->save($page, ['translations' => ['ar' => ['title' => 'قصة'], 'en' => ['title' => 'Our story']]])->assertSessionHasNoErrors();
        $this->assertSame(['Notre histoire', '<p>Texte</p>'], array_values($page->translations()->where('locale', 'fr')->first()->only(['title', 'body_html'])));

        // An emptied language that was sent goes, as English did before.
        $this->save($page, ['translations' => ['ar' => ['title' => 'قصة'], 'fr' => ['title' => '', 'body_html' => '<p></p>']]])->assertSessionHasNoErrors();
        $this->assertSame(['ar', 'en'], $page->translations()->orderBy('locale')->pluck('locale')->all());
    }

    public function test_the_legacy_arabic_columns_follow_the_source_language(): void
    {
        $page = $this->custom();
        $this->save($page, ['translations' => ['ar' => ['title' => 'العنوان', 'excerpt' => 'الملخص', 'body_html' => '<h2>قسم</h2><p>سطر<br>ثانٍ</p>'], 'en' => ['title' => 'Title', 'body_html' => '<p>English</p>']]])
            ->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertSame(['العنوان', 'الملخص', "قسم\n\nسطر\nثانٍ"], [$page->title, $page->excerpt, $page->body]);
    }

    public function test_a_failed_save_rolls_back_every_language(): void
    {
        $this->language('fr', 'French');
        $page = $this->pageWith(['fr' => ['title' => 'Avant', 'body_html' => '<p>Avant</p>']]);
        StorefrontContentPageTranslation::saving(function (StorefrontContentPageTranslation $row) {
            if ($row->locale === 'fr') {
                throw new RuntimeException('Disk full');
            }
        });

        $this->save($page, ['translations' => ['ar' => ['title' => 'بعد', 'body_html' => '<p>بعد</p>'], 'fr' => ['title' => 'Après', 'body_html' => '<p>Après</p>']]])->assertServerError();

        $page->refresh();
        $this->assertSame(['قصة', 'قصة', 'Avant'], [$page->title, $page->translations->firstWhere('locale', 'ar')->title, $page->translations->firstWhere('locale', 'fr')->title]);
    }

    // ---------------------------------------------------------------- unchanged access

    public function test_authorization_and_previews_are_unchanged(): void
    {
        $this->language('fr', 'French');
        $page = $this->custom();
        $payload = ['status' => 'draft', 'translations' => ['ar' => ['title' => 'x'], 'fr' => ['title' => 'x']]];

        $this->put(route('dashboard.content-pages.update', $page), $payload)->assertRedirect(route('admin.login'));
        $this->actingAs($this->admin(['content-pages.view']), 'admin')->put(route('dashboard.content-pages.update', $page), $payload)->assertForbidden();

        $viewer = $this->admin(['content-pages.view']);
        $this->actingAs($viewer, 'admin')->get(route('dashboard.content-pages.preview', [$page, 'en']))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        // PAGES-05C: an enabled third language can be previewed (the route no longer lists Arabic and English).
        $this->actingAs($viewer, 'admin')->get(route('dashboard.content-pages.preview', [$page, 'fr']))->assertOk();
        $this->get('/pages/custom-page')->assertNotFound();
    }

    private function save(StorefrontContentPage $page, array $payload)
    {
        return $this->actingAs($this->admin(), 'admin')->from(route('dashboard.content-pages.edit', $page))
            ->put(route('dashboard.content-pages.update', $page), array_replace(['status' => $page->fresh()->status], $payload));
    }

    private function language(string $code, string $name, bool $rtl = false, bool $active = true): Language
    {
        return Language::create(['name' => $name, 'native' => $name, 'code' => $code, 'is_rtl' => $rtl, 'is_active' => $active]);
    }

    private function custom(string $status = 'draft'): StorefrontContentPage
    {
        $page = StorefrontContentPage::create(['slug' => 'custom-page', 'title' => 'قصة', 'body' => 'نص', 'status' => $status]);
        $page->translations()->create(['locale' => 'ar', 'title' => 'قصة', 'body_html' => '<p>نص</p>']);

        return $page;
    }

    private function pageWith(array $translations): StorefrontContentPage
    {
        $page = StorefrontContentPage::query()->where('slug', 'custom-page')->first() ?? $this->custom('published');
        $page->update(['status' => 'published']);
        $page->translations()->where('locale', '!=', 'ar')->delete();
        foreach ($translations as $locale => $values) {
            $page->translations()->create(['locale' => $locale, ...$values]);
        }

        return $page->fresh();
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
