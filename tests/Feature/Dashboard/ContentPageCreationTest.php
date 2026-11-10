<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\StorefrontContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * PAGES-04B: creating custom content pages: content-pages.create (never implied by edit), the slug checked as sent
 * (format, reserved words, unique), draft or published, both languages through the same form and editor, the body
 * cleaned, the legacy Arabic columns filled, published_at set once and kept, drafts hidden from the storefront.
 */
class ContentPageCreationTest extends TestCase
{
    use RefreshDatabase;

    private const CREATOR = ['content-pages.view', 'content-pages.create'];

    protected function setUp(): void
    {
        parent::setUp();

        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->withoutVite();
    }

    public function test_only_admins_with_the_create_ability_can_create(): void
    {
        $this->get(route('dashboard.content-pages.create'))->assertRedirect();
        $this->post(route('dashboard.content-pages.store'), $this->payload())->assertRedirect(route('admin.login'));

        foreach ([[], ['content-pages.view'], ['content-pages.view', 'content-pages.edit'], ['content-pages.create']] as $abilities) {
            $admin = $this->admin($abilities);
            $this->actingAs($admin, 'admin')->get(route('dashboard.content-pages.create'))->assertForbidden();
            $this->actingAs($admin, 'admin')->post(route('dashboard.content-pages.store'), $this->payload())->assertForbidden();
        }
        $this->assertSame(7, StorefrontContentPage::count());

        $editor = $this->admin(['content-pages.view', 'content-pages.edit']);
        $this->assertStringNotContainsString('data-content-page-create', $this->actingAs($editor, 'admin')->get(route('dashboard.content-pages.index'))->getContent());

        $creator = $this->admin(self::CREATOR);
        $index = $this->actingAs($creator, 'admin')->get(route('dashboard.content-pages.index'))->getContent();
        $this->assertStringContainsString('href="'.route('dashboard.content-pages.create').'"', $index);
        $this->actingAs($creator, 'admin')->post(route('dashboard.content-pages.store'), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame(8, StorefrontContentPage::count());
    }

    public function test_the_form_reuses_the_editor_with_an_address_field_and_no_preview(): void
    {
        $html = $this->actingAs($this->admin(self::CREATOR), 'admin')->get(route('dashboard.content-pages.create'))->assertOk()->getContent();

        $this->assertStringContainsString('action="'.route('dashboard.content-pages.store').'"', $html);
        $this->assertStringNotContainsString('name="_method"', $html);
        $this->assertStringContainsString('name="slug"', $html);
        $this->assertStringContainsString('<option value="draft" selected>', $html);
        foreach (['ar', 'en'] as $locale) {
            $this->assertStringContainsString('data-content-editor data-locale="'.$locale.'" data-editable="true"', $html);
            // The body field starts disabled: only an edit in a working editor posts a body.
            $this->assertMatchesRegularExpression('/<textarea id="translations-'.$locale.'-body_html" name="translations\['.$locale.'\]\[body_html\]" hidden disabled data-content-body-field><\/textarea>/', $html);
        }
        $this->assertStringContainsString('data-content-editor-config', $html);
        $this->assertStringContainsString('data-language-tablist', $html);
        $this->assertStringNotContainsString('/preview/', $html);
    }

    public function test_a_draft_is_created_hidden_from_the_storefront_and_previewable(): void
    {
        $admin = $this->admin(self::CREATOR);
        $this->actingAs($admin, 'admin')->post(route('dashboard.content-pages.store'), $this->payload(['status' => 'draft']))->assertSessionHasNoErrors();

        $page = StorefrontContentPage::query()->where('slug', 'our-story')->firstOrFail();
        $this->assertSame(['draft', false, false, null], [$page->status, $page->is_published, $page->is_system, $page->published_at]);
        $this->assertSame($admin->id, $page->updated_by);

        $this->get('/pages/our-story')->assertNotFound();
        $this->actingAs($admin, 'admin')->get(route('dashboard.content-pages.preview', [$page, 'ar']))->assertOk()->assertSee('قصتنا')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_a_published_page_stores_both_languages_and_is_shown(): void
    {
        $response = $this->actingAs($this->admin(self::CREATOR), 'admin')->post(route('dashboard.content-pages.store'), $this->payload([
            'translations' => [
                'ar' => ['title' => 'قصتنا', 'excerpt' => 'ملخص', 'meta_title' => 'عنوان البحث', 'body_html' => '<h2>البداية</h2><p>نص <script>alert(1)</script><a href="javascript:alert(1)">رابط</a></p>'],
                'en' => ['title' => 'Our story', 'meta_description' => 'Search text', 'body_html' => '<p onclick="x()">How we <strong>began</strong>.</p>'],
            ],
        ]));

        $page = StorefrontContentPage::query()->where('slug', 'our-story')->firstOrFail();
        $response->assertRedirect(route('dashboard.content-pages.edit', $page));
        $this->assertSame(['published', true], [$page->status, $page->is_published]);
        $this->assertNotNull($page->published_at);

        $ar = $page->translations()->where('locale', 'ar')->firstOrFail();
        $en = $page->translations()->where('locale', 'en')->firstOrFail();
        $this->assertSame(['قصتنا', 'ملخص', 'عنوان البحث'], [$ar->title, $ar->excerpt, $ar->meta_title]);
        $this->assertSame('<h2>البداية</h2><p>نص <a rel="noopener noreferrer">رابط</a></p>', $ar->body_html, 'Cleaned on the server.');
        $this->assertSame(['Our story', 'Search text', '<p>How we <strong>began</strong>.</p>'], [$en->title, $en->meta_description, $en->body_html]);
        // The legacy Arabic columns (the rollback copy).
        $this->assertSame(['قصتنا', 'ملخص', "البداية\n\nنص رابط"], [$page->title, $page->excerpt, $page->body]);

        $this->get('/ar/pages/our-story')->assertOk()->assertSee('<h2>البداية</h2>', false)->assertDontSee('<script>', false);
        $this->withSession(['locale' => 'en'])->get('/en/pages/our-story')->assertOk()->assertSee('<strong>began</strong>', false);
    }

    public function test_english_is_optional_and_the_arabic_title_and_body_are_required(): void
    {
        $creator = $this->admin(self::CREATOR);
        $this->actingAs($creator, 'admin')->post(route('dashboard.content-pages.store'), $this->payload(['translations' => ['ar' => ['title' => 'قصتنا', 'body_html' => '<p>نص</p>']]]))
            ->assertSessionHasNoErrors();
        $this->assertSame(['ar'], StorefrontContentPage::query()->where('slug', 'our-story')->firstOrFail()->translations()->pluck('locale')->all());

        // No body (an editor that never started posts none), an empty one, and no title.
        foreach ([['title' => 'عنوان'], ['title' => 'عنوان', 'body_html' => '<p> </p>'], ['title' => '', 'body_html' => '<p>نص</p>']] as $i => $arabic) {
            $this->actingAs($creator, 'admin')->from(route('dashboard.content-pages.create'))
                ->post(route('dashboard.content-pages.store'), $this->payload(['slug' => 'page-'.$i, 'translations' => ['ar' => $arabic]]))
                ->assertRedirect(route('dashboard.content-pages.create'))
                ->assertSessionHasErrors($i === 2 ? 'translations.ar.title' : 'translations.ar.body_html');
        }
        $this->assertSame(8, StorefrontContentPage::count());
    }

    public function test_invalid_reserved_and_duplicate_slugs_are_refused_as_sent(): void
    {
        $creator = $this->admin(self::CREATOR);
        $invalid = ['', 'Our-Story', 'our_story', 'our story', '-story', 'story-', 'our--story', 'قصتنا', 'our/story', 'café', str_repeat('a', 101)];
        $taken = [...StorefrontContentPage::RESERVED_SLUGS];

        foreach ([...$invalid, ...$taken] as $slug) {
            $this->actingAs($creator, 'admin')->post(route('dashboard.content-pages.store'), $this->payload(['slug' => $slug]))->assertSessionHasErrors('slug');
        }
        $this->actingAs($creator, 'admin')->post(route('dashboard.content-pages.store'), $this->payload(['slug' => ['our-story']]))->assertSessionHasErrors('slug');
        $this->assertSame(7, StorefrontContentPage::count(), 'Nothing saved, nothing silently corrected.');

        $this->actingAs($creator, 'admin')->post(route('dashboard.content-pages.store'), $this->payload(['slug' => str_repeat('a', 100)]))->assertSessionHasNoErrors();
        $this->actingAs($creator, 'admin')->post(route('dashboard.content-pages.store'), $this->payload())->assertSessionHasNoErrors();
        $this->actingAs($creator, 'admin')->post(route('dashboard.content-pages.store'), $this->payload())
            ->assertSessionHasErrors(['slug' => 'Another page already uses this address.']);
        $this->assertSame(9, StorefrontContentPage::count());
    }

    public function test_publication_time_is_set_once_kept_on_draft_and_never_taken_from_input(): void
    {
        Carbon::setTestNow('2026-11-09 10:00:00');
        $this->actingAs($this->admin(self::CREATOR), 'admin')->post(route('dashboard.content-pages.store'), $this->payload([
            'status' => 'draft', 'published_at' => '2020-01-01 00:00:00', 'is_system' => '1', 'is_published' => '1',
        ]))->assertSessionHasNoErrors();
        $page = StorefrontContentPage::query()->where('slug', 'our-story')->firstOrFail();
        $this->assertSame(['draft', false, false, null], [$page->status, $page->is_published, $page->is_system, $page->published_at]);

        $editor = $this->admin(['content-pages.view', 'content-pages.edit']);
        $save = fn (string $status) => $this->actingAs($editor, 'admin')->put(route('dashboard.content-pages.update', $page), array_diff_key($this->payload(['status' => $status, 'published_at' => null]), ['slug' => true]))
            ->assertSessionHasNoErrors();

        $save('published');
        $this->assertSame('2026-11-09 10:00:00', $page->fresh()->published_at->toDateTimeString());

        Carbon::setTestNow('2026-11-10 10:00:00');
        $save('draft');
        $this->assertSame(['draft', '2026-11-09 10:00:00'], [$page->fresh()->status, $page->fresh()->published_at->toDateTimeString()]);
        $this->get('/pages/our-story')->assertNotFound();
        $save('published');
        $this->assertSame('2026-11-09 10:00:00', $page->fresh()->published_at->toDateTimeString(), 'The first publication is kept.');
        $this->assertSame('our-story', $page->fresh()->slug);
        Carbon::setTestNow();
    }

    public function test_the_public_route_only_matches_well_formed_slugs(): void
    {
        foreach (['Terms', 'terms_x', 'a--b', '-a', 'قصتنا'] as $slug) {
            $this->get('/pages/'.rawurlencode($slug))->assertNotFound();
        }
        $this->get('/ar/pages/terms')->assertOk();
        $this->assertSame(url('/ar/pages/our-story'), route('content.show', ['locale' => 'ar', 'slug' => 'our-story']));
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['slug' => 'our-story', 'status' => 'published', 'translations' => ['ar' => ['title' => 'قصتنا', 'body_html' => '<p>نص الصفحة</p>']]], $overrides);
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
