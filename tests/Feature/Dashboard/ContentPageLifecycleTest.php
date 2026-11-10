<?php

namespace Tests\Feature\Dashboard;

use App\Http\Requests\Dashboard\UpdateContentPageRequest;
use App\Models\Admin;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\StorefrontContentPage;
use Database\Seeders\AdminContentPageTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PAGES-04C: a custom page's lifecycle: its slug editable until the first publication and locked from then on (also
 * back in draft), publishing and unpublishing, deleting a custom draft with its languages (content-pages.delete,
 * never a system or published page), the list's page kinds, copy-link and delete actions.
 */
class ContentPageLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const EDITOR = ['content-pages.view', 'content-pages.edit'];

    private const DELETER = ['content-pages.view', 'content-pages.delete'];

    protected function setUp(): void
    {
        parent::setUp();

        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->withoutVite();
    }

    // ---------------------------------------------------------------- slug

    public function test_a_never_published_page_can_change_its_slug_with_the_creation_rules(): void
    {
        $page = $this->custom('draft-one');
        $html = $this->actingAs($this->admin(self::EDITOR), 'admin')->get(route('dashboard.content-pages.edit', $page))->getContent();
        $this->assertStringContainsString('name="slug" value="draft-one"', $html);

        foreach (['Bad Slug', 'a--b', 'terms', 'create', str_repeat('a', 101)] as $slug) {
            $this->save($page, ['slug' => $slug])->assertSessionHasErrors('slug');
        }
        $this->custom('taken');
        $this->save($page, ['slug' => 'taken'])->assertSessionHasErrors(['slug' => 'Another page already uses this address.']);
        $this->save($page, ['slug' => 'draft-one'])->assertSessionHasNoErrors();
        $this->assertSame('draft-one', $page->fresh()->slug);

        $this->save($page, ['slug' => 'renamed'])->assertSessionHasNoErrors()->assertRedirect(route('dashboard.content-pages.edit', $page));
        $this->assertSame('renamed', $page->fresh()->slug);
        $this->assertSame(['ar'], $page->translations()->pluck('locale')->all(), 'The languages stay with the page.');
    }

    public function test_the_slug_locks_on_first_publication_and_stays_locked_in_draft(): void
    {
        $page = $this->custom('our-story');
        $this->save($page, ['slug' => 'our-story-2', 'status' => 'published'])->assertSessionHasNoErrors();
        $page->refresh();
        $this->assertSame(['our-story-2', 'published'], [$page->slug, $page->status]);
        $this->assertTrue($page->isSlugLocked());
        $this->get('/ar/pages/our-story-2')->assertOk();

        $publishedAt = $page->published_at->toDateTimeString();
        $this->save($page, ['slug' => 'again'])->assertSessionHasErrors(['slug' => 'This address can no longer be changed: the page is a system page or has been published.']);

        $this->save($page, ['status' => 'draft'])->assertSessionHasNoErrors();
        $this->get('/pages/our-story-2')->assertNotFound();
        $this->save($page, ['status' => 'draft', 'slug' => 'again'])->assertSessionHasErrors('slug');
        $page->refresh();
        $this->assertSame(['our-story-2', 'draft', $publishedAt], [$page->slug, $page->status, $page->published_at->toDateTimeString()]);

        $html = $this->actingAs($this->admin(self::EDITOR), 'admin')->get(route('dashboard.content-pages.edit', $page))->getContent();
        $this->assertStringNotContainsString('name="slug"', $html);
        $this->assertStringContainsString('data-slug-locked', $html);

        $this->save($page, ['status' => 'published'])->assertSessionHasNoErrors();
        $this->assertSame($publishedAt, $page->fresh()->published_at->toDateTimeString(), 'The first publication is kept.');
        $this->get('/ar/pages/our-story-2')->assertOk();
    }

    public function test_system_slugs_never_change(): void
    {
        $terms = StorefrontContentPage::query()->where('slug', 'terms')->firstOrFail();
        $this->save($terms, ['slug' => 'terms-new'])->assertSessionHasErrors('slug');
        $this->assertSame('terms', $terms->fresh()->slug);
    }

    public function test_a_duplicate_slug_saved_meanwhile_is_reported_and_nothing_changes(): void
    {
        $page = $this->custom('draft-one');
        // Another admin takes the slug after validation passed: the database's unique index refuses the save.
        StorefrontContentPage::saving(function (StorefrontContentPage $saving) {
            if ($saving->slug === 'wanted' && ! DB::table('storefront_content_pages')->where('slug', 'wanted')->exists()) {
                DB::table('storefront_content_pages')->insert(['slug' => 'wanted', 'title' => 'x', 'body' => 'x', 'is_published' => false, 'status' => 'draft']);
            }
        });

        $this->save($page, ['slug' => 'wanted', 'translations' => ['ar' => ['title' => 'عنوان جديد']]])
            ->assertSessionHasErrors(['slug' => 'Another page already uses this address.']);
        $page->refresh();
        $this->assertSame(['draft-one', 'قصة'], [$page->slug, $page->translations()->where('locale', 'ar')->value('title')], 'The whole save was rolled back.');

        // The same on creation.
        $this->actingAs($this->admin(['content-pages.view', 'content-pages.create']), 'admin')->post(route('dashboard.content-pages.store'),
            ['slug' => 'wanted', 'status' => 'draft', 'translations' => ['ar' => ['title' => 'ت', 'body_html' => '<p>ن</p>']]])
            ->assertSessionHasErrors(['slug' => 'Another page already uses this address.']);
    }

    public function test_a_publication_after_validation_still_locks_the_slug_inside_the_transaction(): void
    {
        $page = $this->custom('draft-one');
        // Another admin publishes the page after this request was validated (slug still unlocked) but before it saves.
        $this->app->afterResolving(UpdateContentPageRequest::class, function () use ($page): void {
            DB::table('storefront_content_pages')->where('id', $page->id)->update(['status' => 'published', 'is_published' => true, 'published_at' => now()]);
        });

        $this->save($page, ['slug' => 'renamed', 'status' => 'draft', 'translations' => ['ar' => ['title' => 'عنوان جديد']]])
            ->assertSessionHasErrors(['slug' => 'This address can no longer be changed: the page is a system page or has been published.']);

        $page->refresh();
        $this->assertSame(['draft-one', 'published', 'قصة'], [$page->slug, $page->status, $page->translations()->where('locale', 'ar')->value('title')], 'Nothing from the request was saved.');
        $this->get('/ar/pages/draft-one')->assertOk();
    }

    // ---------------------------------------------------------------- the whole lifecycle

    public function test_the_whole_lifecycle_of_a_custom_page(): void
    {
        $admin = $this->admin(['content-pages.view', 'content-pages.create', 'content-pages.edit', 'content-pages.delete']);
        $this->actingAs($admin, 'admin');
        $put = fn (StorefrontContentPage $page, array $data) => $this->put(route('dashboard.content-pages.update', $page), $data)->assertSessionHasNoErrors();

        // 1. An Arabic draft.
        $this->post(route('dashboard.content-pages.store'), ['slug' => 'spring-sale', 'status' => 'draft',
            'translations' => ['ar' => ['title' => 'تخفيضات الربيع', 'body_html' => '<p>عروض <script>x()</script>الموسم</p>']]])->assertSessionHasNoErrors();
        $page = StorefrontContentPage::query()->where('slug', 'spring-sale')->firstOrFail();
        $this->assertSame('<p>عروض الموسم</p>', $page->translations()->where('locale', 'ar')->value('body_html'));

        // 2. English content (the Arabic body is left out, so it is kept).
        $put($page, ['status' => 'draft', 'translations' => ['ar' => ['title' => 'تخفيضات الربيع'], 'en' => ['title' => 'Spring sale', 'body_html' => '<p>Seasonal offers</p>']]]);
        $this->assertSame(['ar', 'en'], $page->translations()->orderBy('locale')->pluck('locale')->all());
        $this->assertSame('<p>عروض الموسم</p>', $page->translations()->where('locale', 'ar')->value('body_html'));

        // 3. A new slug while never published.
        $put($page, ['slug' => 'spring-offers', 'status' => 'draft', 'translations' => ['ar' => ['title' => 'تخفيضات الربيع'], 'en' => ['title' => 'Spring sale']]]);
        $page->refresh();
        $this->assertSame('spring-offers', $page->slug);

        // 4. The private preview of the draft, in both languages; not public.
        $this->get(route('dashboard.content-pages.preview', [$page, 'ar']))->assertOk()->assertSee('عروض الموسم');
        $this->get(route('dashboard.content-pages.preview', [$page, 'en']))->assertOk()->assertSee('Seasonal offers');
        $this->get('/pages/spring-offers')->assertNotFound();

        // 5–6. Published, at its public address.
        $put($page, ['status' => 'published', 'translations' => ['ar' => ['title' => 'تخفيضات الربيع'], 'en' => ['title' => 'Spring sale']]]);
        $this->withSession(['locale' => 'ar'])->get('/ar/pages/spring-offers')->assertOk()->assertSee('عروض الموسم');
        $this->withSession(['locale' => 'en'])->get('/en/pages/spring-offers')->assertOk()->assertSee('Seasonal offers');
        $this->get('/pages/spring-sale')->assertNotFound();

        // 7. The slug is locked.
        $this->put(route('dashboard.content-pages.update', $page), ['slug' => 'other', 'status' => 'published', 'translations' => ['ar' => ['title' => 'ت']]])->assertSessionHasErrors('slug');

        // 8–9. Back to draft: not public, slug still locked, and a published page cannot be deleted before that.
        $this->delete(route('dashboard.content-pages.destroy', $page))->assertSessionHas('error');
        $put($page, ['status' => 'draft', 'translations' => ['ar' => ['title' => 'تخفيضات الربيع'], 'en' => ['title' => 'Spring sale']]]);
        $this->get('/pages/spring-offers')->assertNotFound();
        $this->assertTrue($page->fresh()->isSlugLocked());

        // 10. Deleted with its languages.
        $this->delete(route('dashboard.content-pages.destroy', $page))->assertSessionHas('success');
        $this->assertNull(StorefrontContentPage::find($page->id));
        $this->assertSame(0, DB::table('storefront_content_page_translations')->where('page_id', $page->id)->count());
        $this->assertSame(7, StorefrontContentPage::count());
    }

    // ---------------------------------------------------------------- deletion

    public function test_a_custom_draft_is_deleted_with_its_languages(): void
    {
        $page = $this->custom('old-campaign');
        $page->translations()->create(['locale' => 'en', 'title' => 'Old', 'body_html' => '<p>Old</p>']);
        $this->assertSame(2, $page->translations()->count());

        $this->actingAs($this->admin(self::DELETER), 'admin')->delete(route('dashboard.content-pages.destroy', $page))
            ->assertRedirect(route('dashboard.content-pages.index'))
            ->assertSessionHas('success', 'The page was deleted. Links to its address no longer work.');

        $this->assertNull(StorefrontContentPage::find($page->id));
        $this->assertSame(0, DB::table('storefront_content_page_translations')->where('page_id', $page->id)->count());
        $this->assertSame(7, StorefrontContentPage::count());
    }

    public function test_a_published_page_is_not_deleted_even_if_it_was_a_draft_when_confirmed(): void
    {
        $page = $this->custom('live');
        $html = $this->actingAs($this->admin(self::DELETER), 'admin')->get(route('dashboard.content-pages.index'))->getContent();
        $this->assertStringContainsString('data-delete-url="'.route('dashboard.content-pages.destroy', $page).'"', $html);

        // Published by someone else before the confirmation is submitted.
        $page->update(['status' => 'published']);

        $this->actingAs($this->admin(self::DELETER), 'admin')->delete(route('dashboard.content-pages.destroy', $page))
            ->assertRedirect(route('dashboard.content-pages.index'))
            ->assertSessionHas('error', 'Only a draft page can be deleted. Return the page to draft first.');
        $this->assertNotNull($page->fresh());
        $this->assertSame(1, $page->translations()->count());
    }

    public function test_deletion_needs_its_own_ability_and_never_touches_system_pages(): void
    {
        $page = $this->custom('draft-one');
        $url = route('dashboard.content-pages.destroy', $page);

        $this->delete($url)->assertRedirect();
        foreach ([[], ['content-pages.view'], [...self::EDITOR, 'content-pages.create'], ['content-pages.delete']] as $abilities) {
            $this->actingAs($this->admin($abilities), 'admin')->delete($url)->assertForbidden();
        }
        $this->assertNotNull($page->fresh());

        $deleter = $this->admin(self::DELETER);
        foreach (StorefrontContentPage::SYSTEM_SLUGS as $slug) {
            $system = StorefrontContentPage::query()->where('slug', $slug)->firstOrFail();
            $this->actingAs($deleter, 'admin')->delete(route('dashboard.content-pages.destroy', $system))->assertForbidden();
        }
        $super = Admin::create(['name' => 'Super', 'email' => 'super@example.com', 'phone' => '0599000099', 'password' => 'password', 'status' => 'active', 'super_admin' => true]);
        $this->actingAs($super, 'admin')->delete(route('dashboard.content-pages.destroy', StorefrontContentPage::query()->where('slug', 'privacy')->firstOrFail()))->assertForbidden();
        $this->assertSame(8, StorefrontContentPage::count());

        // A deleter who cannot edit still cannot change a page.
        $this->actingAs($deleter, 'admin')->put(route('dashboard.content-pages.update', $page), ['status' => 'draft', 'translations' => ['ar' => ['title' => 'x']]])->assertForbidden();
    }

    // ---------------------------------------------------------------- the list

    public function test_the_list_marks_page_kinds_and_shows_copy_and_delete_only_where_they_apply(): void
    {
        $draft = $this->custom('draft-one');
        $this->custom('live-one', 'published');

        $html = $this->actingAs($this->admin(self::DELETER), 'admin')->get(route('dashboard.content-pages.index'))->assertOk()->getContent();
        $this->assertSame(7, substr_count($html, 'data-content-kind="system"'));
        $this->assertSame(2, substr_count($html, 'data-content-kind="custom"'));

        // Copy link: published pages only, the absolute public address.
        $this->assertStringContainsString('data-copy-url="'.url('/ar/pages/live-one').'"', $html);
        $this->assertStringContainsString('data-copy-url="'.url('/ar/pages/terms').'"', $html);
        $this->assertStringNotContainsString('data-copy-url="'.url('/ar/pages/draft-one').'"', $html);
        $this->assertSame(8, substr_count($html, 'data-copy-url='));

        // Delete: the custom draft only, hidden until the script and confirmed in the dialog.
        $this->assertSame(1, substr_count($html, 'data-delete-url='));
        $this->assertStringContainsString('data-delete-url="'.route('dashboard.content-pages.destroy', $draft).'"', $html);
        $this->assertStringContainsString('data-delete-address="/pages/draft-one"', $html);
        $this->assertMatchesRegularExpression('/data-delete-url="[^"]+"[^>]*hidden>/', $html);
        $this->assertStringContainsString('data-delete-dialog', $html);
        $this->assertStringContainsString('Links to :address will stop working.', $html);

        $viewer = $this->actingAs($this->admin(self::EDITOR), 'admin')->get(route('dashboard.content-pages.index'))->getContent();
        $this->assertStringNotContainsString('data-delete-url=', $viewer);
        $this->assertStringNotContainsString('data-delete-dialog', $viewer);
        $this->assertSame(8, substr_count($viewer, 'data-copy-url='));
    }

    public function test_each_page_has_one_preview_in_the_dashboard_language(): void
    {
        $this->seed(AdminContentPageTranslationSeeder::class);
        $draft = $this->custom('draft-one');
        $admin = $this->admin([...self::EDITOR, 'content-pages.delete']);

        foreach (['ar' => 'معاينة', 'en' => 'Preview'] as $locale => $label) {
            $html = $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get(route('dashboard.content-pages.index'))->assertOk()->getContent();

            $this->assertSame(8, substr_count($html, 'data-content-preview-link='), 'One preview per page.');
            $this->assertSame(8, substr_count($html, 'data-content-preview-link="'.$locale.'"'));
            $this->assertSame(8, substr_count($html, '<span>'.$label.'</span>'));
            $this->assertStringContainsString('href="'.route('dashboard.content-pages.preview', [$draft, $locale]).'"', $html);
            $this->assertStringNotContainsString('Preview in Arabic', $html);
            $this->assertStringNotContainsString('Preview in English', $html);
            $this->assertStringNotContainsString('معاينة بالعربية', $html);

            // The other actions are unchanged.
            $this->assertSame(16, preg_match_all('#href="[^"]+/dashboard/content-pages/\d+/edit"#', $html), 'Title and Edit per page.');
            $this->assertSame(7, substr_count($html, 'data-copy-url='));
            $this->assertSame(1, substr_count($html, 'data-delete-url='));
        }

        // PAGES-05C: any enabled language is a content language, so a French dashboard previews in French (a page
        // without French shows Arabic in the preview, which says so).
        Language::create(['name' => 'French', 'native' => 'Français', 'code' => 'fr', 'is_rtl' => false, 'is_active' => true]);
        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'fr'])->get(route('dashboard.content-pages.index'))->assertOk()->getContent();
        $this->assertSame('fr', app()->getLocale(), 'The dashboard was really in French.');
        $this->assertSame(8, substr_count($html, 'data-content-preview-link="fr"'));
    }

    public function test_the_single_preview_falls_back_to_arabic_and_stays_private(): void
    {
        $draft = $this->custom('draft-one');
        $url = route('dashboard.content-pages.preview', [$draft, 'en']);

        $html = $this->actingAs($this->admin(['content-pages.view']), 'admin')->get($url)->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Cache-Control', 'no-store, private')->getContent();
        $this->assertStringContainsString('data-content-preview="draft"', $html);
        $this->assertStringContainsString('data-content-preview-fallback', $html, 'No English yet: the Arabic page, and the banner says so.');
        $this->assertStringContainsString('<p>نص</p>', $html);

        $this->app['auth']->guard('admin')->logout();
        $this->get($url)->assertRedirect();
        $this->actingAs($this->admin([]), 'admin')->get($url)->assertForbidden();
        $this->get('/pages/draft-one')->assertNotFound();
    }

    public function test_the_edit_form_keeps_the_editor_and_media_picker(): void
    {
        $page = $this->custom('draft-one');
        $html = $this->actingAs($this->admin([...self::EDITOR, 'media.view']), 'admin')->get(route('dashboard.content-pages.edit', $page))->getContent();

        $this->assertStringContainsString('data-content-editor data-locale="ar" data-editable="true"', $html);
        $this->assertStringContainsString('data-content-editor data-locale="en" data-editable="true"', $html);
        $this->assertStringContainsString('data-picker-mode="callback"', $html);
        $this->assertStringContainsString(route('dashboard.content-pages.preview', [$page, 'en']), $html);
    }

    private function save(StorefrontContentPage $page, array $payload)
    {
        return $this->actingAs($this->admin(self::EDITOR), 'admin')->from(route('dashboard.content-pages.edit', $page))
            ->put(route('dashboard.content-pages.update', $page), array_replace(['status' => $page->fresh()->status, 'translations' => ['ar' => ['title' => 'قصة']]], $payload));
    }

    private function custom(string $slug, string $status = 'draft'): StorefrontContentPage
    {
        $page = StorefrontContentPage::create(['slug' => $slug, 'title' => 'قصة', 'body' => 'نص', 'status' => $status]);
        $page->translations()->create(['locale' => 'ar', 'title' => 'قصة', 'body_html' => '<p>نص</p>']);

        return $page;
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
