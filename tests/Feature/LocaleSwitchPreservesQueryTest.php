<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * VQA-FIX-02: switching language (?change-locale=xx, SetLocale) keeps the current page: the same path and every other
 * query parameter exactly as sent (a product's slug, browse category / q / sale / sort / min / max, repeated keys and
 * their encoding); only change-locale is removed, the locale is still stored in the session, and the redirect stays
 * on the site and cannot loop. The storefront switcher's GET forms carry the current parameters to it.
 */
class LocaleSwitchPreservesQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::query()->create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::query()->create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    public function test_a_product_keeps_its_identity_in_both_directions_and_after_repeated_switches(): void
    {
        $category = Category::query()->create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $vendor = Vendor::factory()->approved()->create(['status' => 'active']);
        $jar = $this->product($vendor, $category, 'clay-jar', 'Clay jar');
        // The newest sellable product: what /product shows when the slug is lost.
        $this->product($vendor, $category, 'newer-bowl', 'Newer bowl');

        foreach (['en', 'ar', 'en', 'ar'] as $locale) {
            $this->get('/product?slug=clay-jar&change-locale='.$locale)
                ->assertRedirect(url('/product').'?slug=clay-jar');
            $this->assertSame($locale, session('locale'));

            $this->get('/product?slug=clay-jar')->assertOk()
                ->assertViewHas('product', fn (Product $product) => $product->is($jar))
                ->assertSee('<html lang="'.$locale.'" dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"', false);
        }

        // A different product keeps its own identity too.
        $this->get('/product?slug=newer-bowl&change-locale=en')->assertRedirect(url('/product').'?slug=newer-bowl');
    }

    public function test_browse_filters_search_offers_sorting_and_prices_are_kept(): void
    {
        $cases = [
            'category=pottery',
            'q=Pottery',
            'sale=1',
            'sort=price-asc',
            'min=10&max=40',
            'category=pottery&q=jar&sale=1&sort=price-desc&min=5&max=50',
        ];

        foreach ($cases as $query) {
            $this->get('/browse?'.$query.'&change-locale=en')->assertRedirect(url('/browse').'?'.$query);
            $this->get('/browse?change-locale=ar&'.$query)->assertRedirect(url('/browse').'?'.$query);
        }
    }

    public function test_an_arabic_search_term_is_kept_with_its_encoding(): void
    {
        $location = $this->get('/browse?'.http_build_query(['q' => 'جرّة فخار', 'change-locale' => 'en']))
            ->assertRedirect()->headers->get('Location');

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame(['q' => 'جرّة فخار'], $query);
        $this->assertStringStartsWith(url('/browse').'?q=%D8%AC', $location);
    }

    public function test_a_page_without_other_parameters_redirects_to_its_clean_url(): void
    {
        $this->get('/?change-locale=en')->assertRedirect(url('/'));
        $this->get('/browse?change-locale=ar')->assertRedirect(url('/browse'));
        $this->assertSame('ar', session('locale'));
    }

    public function test_repeated_keys_order_and_raw_encoding_are_kept(): void
    {
        // The test client rebuilds QUERY_STRING from parsed input (one value per key), so the raw string a web server
        // passes is set directly: two categories, as storefront-browse.js writes them, around the switch.
        $raw = 'category=%D8%A7%D9%84%D9%81%D8%AE%D8%A7%D8%B1&change-locale=en&category=textiles&q=a+b';
        $request = Request::create('http://localhost/browse?'.$raw);
        $request->server->set('QUERY_STRING', $raw);
        $request->setLaravelSession($this->app['session.store']);

        $response = (new SetLocale)->handle($request, fn () => response('next'));

        $this->assertSame('http://localhost/browse?category=%D8%A7%D9%84%D9%81%D8%AE%D8%A7%D8%B1&category=textiles&q=a+b', $response->headers->get('Location'));
    }

    public function test_every_change_locale_pair_is_removed_and_the_redirect_stays_on_the_site(): void
    {
        $location = $this->get('/browse?change-locale=en&q=jar&change-locale=ar')->headers->get('Location');
        $this->assertSame(url('/browse').'?q=jar', $location, 'No switch is left in the destination: no loop.');

        // An array value is ignored (no locale change, no error) and removed like any other switch.
        $this->get('/browse?q=jar')->assertOk();
        $before = session('locale');
        $this->get('/browse?change-locale[]=en&q=jar')->assertRedirect(url('/browse').'?q=jar');
        $this->assertSame($before, session('locale'));

        // A parameter that looks like a destination is only a query parameter of the same page.
        $location = $this->get('/browse?redirect=https%3A%2F%2Fevil.example&change-locale=en')->headers->get('Location');
        $this->assertSame(url('/browse').'?redirect=https%3A%2F%2Fevil.example', $location);
        $this->assertSame(parse_url(url('/'), PHP_URL_HOST), parse_url($location, PHP_URL_HOST));

        // An unsupported locale changes nothing but still returns to the same page.
        $current = session('locale');
        $this->get('/browse?change-locale=fr&q=jar')->assertRedirect(url('/browse').'?q=jar');
        $this->assertSame($current, session('locale'));
    }

    public function test_the_storefront_switcher_forms_carry_the_current_parameters(): void
    {
        $category = Category::query()->create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $this->product(Vendor::factory()->approved()->create(['status' => 'active']), $category, 'clay-jar', 'Clay jar');
        $this->get('/?change-locale=ar');

        $html = $this->get('/product?slug=clay-jar')->assertOk()->getContent();
        foreach (['locale-header-0', 'locale-top-bar-0'] as $form) {
            $this->assertSame(['slug=clay-jar', 'change-locale=en'], $this->formFields($html, $form), $form);
        }

        $html = $this->get('/browse?'.http_build_query(['category' => 'pottery', 'q' => 'جرّة', 'sale' => 1, 'sort' => 'price-asc']))->assertOk()->getContent();
        $this->assertSame(['category=pottery', 'q=جرّة', 'sale=1', 'sort=price-asc', 'change-locale=en'], $this->formFields($html, 'locale-top-bar-0'));

        // Without parameters the form sends the switch only, as before.
        $this->assertSame(['change-locale=en'], $this->formFields($this->get('/browse')->getContent(), 'locale-header-0'));
    }

    private function product(Vendor $vendor, Category $category, string $slug, string $name): Product
    {
        return Product::query()->create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => $name, 'slug' => $slug,
            'price' => 25, 'stock_quantity' => 5, 'status' => 'active', 'published_at' => now(),
        ]);
    }

    /** @return list<string> the "name=value" fields of the form with this id, in order */
    private function formFields(string $html, string $id): array
    {
        $this->assertSame(1, preg_match('/<form hidden id="'.$id.'"[^>]*>(.*?)<\/form>/s', $html, $form), $id);
        preg_match_all('/<input type="hidden" name="([^"]*)" value="([^"]*)">/', $form[1], $inputs, PREG_SET_ORDER);

        return array_map(fn (array $input) => html_entity_decode($input[1]).'='.html_entity_decode($input[2]), $inputs);
    }
}
