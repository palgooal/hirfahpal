<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\StorefrontContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * PAGES-05D: public content pages at /{locale}/pages/{slug} for every enabled language of the registry: the URL decides
 * the language (never the session, and the session is not changed), the source-language fallback for a missing or
 * incomplete translation, the former /pages/{slug} sending a published page on (302), and the footer and dashboard
 * links in the new form.
 */
class ContentPageLocalizedRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->language('ar', 'العربية', true);
        $this->language('en', 'English');
        $this->withoutVite();
    }

    // ---------------------------------------------------------------- localized pages

    public function test_arabic_english_a_third_and_another_rtl_language_each_render_their_translation(): void
    {
        $this->language('fr', 'Français');
        $this->language('fa', 'فارسی', true);
        $page = $this->custom([
            'en' => ['title' => 'Our story', 'body_html' => '<p>English text</p>'],
            'fr' => ['title' => 'Notre histoire', 'body_html' => '<p>Texte français</p>'],
            'fa' => ['title' => 'داستان ما', 'body_html' => '<p>متن فارسی</p>'],
        ]);

        foreach (['ar' => ['rtl', 'قصتنا', '<p>نص عربي</p>'], 'en' => ['ltr', 'Our story', '<p>English text</p>'],
            'fr' => ['ltr', 'Notre histoire', '<p>Texte français</p>'], 'fa' => ['rtl', 'داستان ما', '<p>متن فارسی</p>']] as $locale => [$dir, $title, $body]) {
            $html = $this->get('/'.$locale.'/pages/our-story')->assertOk()->getContent();
            $this->assertStringContainsString('<html lang="'.$locale.'" dir="'.$dir.'"', $html, $locale);
            $this->assertStringContainsString('lang="'.$locale.'" dir="'.$dir.'" data-content-body>'.$body.'</article>', $html, $locale);
            $this->assertStringContainsString('<h1 class="text-[30px] font-bold leading-9 text-ink sm:text-4xl">'.$title.'</h1>', $html, $locale);
        }
        $this->assertSame(url('/fa/pages/our-story'), route('content.show', ['locale' => 'fa', 'slug' => $page->slug]));
    }

    public function test_disabled_unknown_and_malformed_locales_are_not_found(): void
    {
        $this->language('de', 'Deutsch', false, false);
        $this->custom(['de' => ['title' => 'Unsere Geschichte', 'body_html' => '<p>Text</p>']]);

        foreach (['de', 'xx', 'fr', 'EN', 'En', 'e', 'english', 'en_us', 'en-', '12', 'ar-'] as $locale) {
            $this->get('/'.$locale.'/pages/our-story')->assertNotFound();
        }
        $this->assertStringNotContainsString('Unsere Geschichte', $this->get('/en/pages/our-story')->getContent(), 'A disabled language is never shown.');
    }

    public function test_unknown_and_draft_pages_are_not_found_in_every_language(): void
    {
        $this->custom([], 'draft');

        foreach (['ar', 'en'] as $locale) {
            $this->get('/'.$locale.'/pages/our-story')->assertNotFound();
            $this->get('/'.$locale.'/pages/no-such-page')->assertNotFound();
            $this->get('/'.$locale.'/pages/Bad_Slug')->assertNotFound();
        }
    }

    public function test_the_url_language_wins_over_the_session_and_leaves_it_unchanged(): void
    {
        $this->custom(['en' => ['title' => 'Our story', 'body_html' => '<p>English text</p>']]);

        $this->withSession(['locale' => 'ar'])->get('/en/pages/our-story')->assertOk()
            ->assertSee('<html lang="en" dir="ltr"', false)->assertSee('English text')->assertSessionHas('locale', 'ar');
        $this->withSession(['locale' => 'en'])->get('/ar/pages/our-story')->assertOk()
            ->assertSee('<html lang="ar" dir="rtl"', false)->assertSee('نص عربي')->assertSessionHas('locale', 'en');

        $this->flushSession();
        $this->get('/en/pages/our-story')->assertOk()->assertSessionMissing('locale');
    }

    public function test_missing_and_incomplete_translations_fall_back_to_the_source_language(): void
    {
        $this->language('fr', 'Français');
        $this->custom(['en' => ['title' => 'Title only', 'body_html' => '<p> </p>']]);

        foreach (['en' => 'incomplete', 'fr' => 'missing'] as $locale => $case) {
            $html = $this->get('/'.$locale.'/pages/our-story')->assertOk()->getContent();
            $this->assertStringContainsString('<html lang="'.$locale.'"', $html, $case.': the page keeps the requested language.');
            $this->assertStringContainsString('lang="ar" dir="rtl" data-content-body><p>نص عربي</p></article>', $html, $case.': the source content, in its own direction.');
            $this->assertStringNotContainsString('Title only', $html, $case);
        }
    }

    public function test_the_storefront_switcher_on_a_page_goes_to_the_same_page_in_the_chosen_language(): void
    {
        $this->custom(['en' => ['title' => 'Our story', 'body_html' => '<p>English text</p>']]);

        $this->withSession(['locale' => 'ar'])->get('/ar/pages/our-story?change-locale=en')
            ->assertRedirect(url('/en/pages/our-story'))->assertSessionHas('locale', 'en');
        $this->withSession(['locale' => 'ar'])->get('/ar/pages/our-story?change-locale=de')
            ->assertRedirect(url('/ar/pages/our-story'))->assertSessionHas('locale', 'ar');
        $this->get('/en/pages/our-story?change-locale[]=ar')->assertRedirect(url('/en/pages/our-story'));
    }

    // ---------------------------------------------------------------- the former address

    public function test_the_former_address_sends_a_published_page_to_the_visitors_language(): void
    {
        $this->custom();

        $this->withSession(['locale' => 'en'])->get('/pages/our-story')->assertStatus(302)->assertRedirect(url('/en/pages/our-story'));
        $this->withSession(['locale' => 'ar'])->get('/pages/our-story')->assertStatus(302)->assertRedirect(url('/ar/pages/our-story'));
        $this->get('/pages/our-story?change-locale=en')->assertRedirect(url('/pages/our-story'));

        // Never to a disabled language: the default, else the source language.
        Language::query()->where('code', 'en')->first()->update(['is_active' => false]);
        $this->withSession(['locale' => 'en'])->get('/pages/our-story')->assertRedirect(url('/ar/pages/'.'our-story'));
        $this->get('/ar/pages/our-story')->assertOk();
    }

    public function test_the_former_address_reveals_no_draft_and_knows_no_unknown_page(): void
    {
        $this->custom([], 'draft');

        $this->get('/pages/our-story')->assertNotFound();
        $this->get('/pages/no-such-page')->assertNotFound();
        $this->get('/pages/Our-Story')->assertNotFound();
    }

    // ---------------------------------------------------------------- links

    public function test_footer_links_point_to_the_page_in_the_footer_language(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $html = $this->withSession(['locale' => $locale])->get(route('home'))->assertOk()->getContent();
            $footer = substr($html, strpos($html, '<footer'));
            foreach (['about', 'authenticity', 'shipping', 'returns', 'faq'] as $slug) {
                $this->assertStringContainsString('href="'.url('/'.$locale.'/pages/'.$slug).'"', $footer, $locale.' '.$slug);
            }
            $this->assertStringNotContainsString('href="'.url('/pages/'), $footer);
        }
    }

    public function test_the_dashboard_copies_the_localized_public_address_of_published_pages_only(): void
    {
        $this->custom(['en' => ['title' => 'Our story', 'body_html' => '<p>English text</p>']]);
        StorefrontContentPage::create(['slug' => 'draft-one', 'title' => 'مسودة', 'body' => 'نص', 'status' => 'draft']);
        $admin = $this->admin(['content-pages.view', 'content-pages.edit']);

        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.content-pages.index'))->getContent();
        $this->assertStringContainsString('data-copy-url="'.url('/en/pages/our-story').'"', $html, 'Complete in the dashboard language.');
        $this->assertStringContainsString('data-copy-url="'.url('/ar/pages/terms').'"', $html, 'Not in English: the source language.');
        $this->assertStringNotContainsString('pages/draft-one"', $html);
        $this->assertStringNotContainsString('/preview/', substr($html, strpos($html, 'data-copy-url'), 200));

        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.content-pages.index'))->getContent();
        $this->assertStringContainsString('data-copy-url="'.url('/ar/pages/our-story').'"', $html);
    }

    public function test_previews_keep_their_protection(): void
    {
        $page = $this->custom([], 'draft');
        $url = route('dashboard.content-pages.preview', [$page, 'en']);

        $this->get($url)->assertRedirect();
        $this->actingAs($this->admin([]), 'admin')->get($url)->assertForbidden();
        $this->actingAs($this->admin(['content-pages.view']), 'admin')->get($url)->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Cache-Control', 'no-store, private');
    }

    // ---------------------------------------------------------------- routing

    public function test_other_routes_are_unchanged_and_the_routes_compile_for_the_route_cache(): void
    {
        $this->custom();

        // Every other storefront address resolves as before.
        foreach (['/' => 'home', '/categories' => 'categories', '/contact' => 'contact.create', '/up' => null] as $path => $name) {
            $route = Route::getRoutes()->match(Request::create($path));
            $this->assertNotSame('content.show', $route->getName(), $path);
            if ($name !== null) {
                $this->assertSame($name, $route->getName(), $path);
            }
        }
        $this->get('/categories')->assertOk();

        // The route cache's compiled collection matches the same way (route:cache).
        $compiled = Route::getRoutes()->toCompiledRouteCollection(app('router'), app());
        $this->assertSame('content.show', $compiled->match(Request::create('/en/pages/our-story'))->getName());
        $this->assertSame('content.show', $compiled->match(Request::create('/pt-br/pages/our-story'))->getName(), 'Longer codes have their route; the registry decides.');
        $this->assertSame('content.legacy', $compiled->match(Request::create('/pages/our-story'))->getName());
        $this->assertSame('home', $compiled->match(Request::create('/'))->getName());
    }

    private function language(string $code, string $native, bool $rtl = false, bool $active = true): Language
    {
        return Language::create(['name' => $native, 'native' => $native, 'code' => $code, 'is_rtl' => $rtl, 'is_active' => $active]);
    }

    private function custom(array $translations = [], string $status = 'published'): StorefrontContentPage
    {
        $page = StorefrontContentPage::create(['slug' => 'our-story', 'title' => 'قصتنا', 'body' => 'نص عربي', 'status' => $status]);
        $page->translations()->create(['locale' => 'ar', 'title' => 'قصتنا', 'body_html' => '<p>نص عربي</p>']);
        foreach ($translations as $locale => $values) {
            $page->translations()->create(['locale' => $locale, ...$values]);
        }

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
