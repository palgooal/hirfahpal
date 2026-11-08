<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\RoleUser;
use App\Models\Setting;
use App\Models\Vendor;
use App\Support\SiteAvailability\SiteAvailability;
use App\Support\SiteAvailability\SiteAvailabilityState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SETTINGS-FIX-01: the settings page's data integrity. SET-01: the footer's social links are absolute http(s)
 * addresses only. SET-02: an empty footer text field goes back to its default (no empty heading or link label), an
 * empty social link is no link. SET-03: ILS is the default for a new settings row, a saved currency is never replaced,
 * and viewing the page never writes. R-02: footer content of an unexpected shape never breaks a page and is never
 * rewritten by reading it. Site availability and product prices are not affected.
 */
class SettingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const REJECTED_URLS = [
        'javascript:alert(1)', 'javascript://%0aalert(1)', 'data:text/html;base64,PHNjcmlwdD4=', 'data://text/html,x',
        'file:///etc/passwd', 'file://host/etc', 'ftp://example.com/a', 'chrome://settings', 'ssh://host', 'sms://x',
        '//example.com', 'example.com',
    ];

    // ---------------------------------------------------------------- SET-01: social links

    public function test_social_links_accept_http_and_https_and_may_be_left_empty(): void
    {
        $this->save(['footer' => ['instagram_url' => 'https://instagram.com/hirfah', 'facebook_url' => 'http://facebook.com/hirfah', 'x_url' => '']])
            ->assertSessionHasNoErrors()->assertRedirect(route('dashboard.setting.index'));

        $footer = Setting::singleton()->footer_content;
        $this->assertSame(['https://instagram.com/hirfah', 'http://facebook.com/hirfah', ''], [$footer['instagram_url'], $footer['facebook_url'], $footer['x_url']]);
    }

    public function test_social_links_reject_every_other_scheme_and_save_nothing(): void
    {
        foreach (['instagram_url', 'facebook_url', 'x_url'] as $field) {
            foreach (self::REJECTED_URLS as $url) {
                $this->save(['site_name' => 'Changed', 'footer' => [$field => $url]])->assertSessionHasErrors("footer.{$field}");
            }
        }

        $this->assertDatabaseCount('settings', 0);
    }

    // ---------------------------------------------------------------- SET-02: empty footer fields

    public function test_an_empty_or_blank_text_field_goes_back_to_its_default_and_an_omitted_one_keeps_its_value(): void
    {
        $defaults = Setting::footerDefaults();
        Setting::singleton()->update(['footer_content' => array_replace($defaults, [
            'about_title' => 'عن المنصة', 'cart_label' => 'السلة', 'copyright' => 'حقوق مخصصة', 'description' => 'وصف مخصص',
            'instagram_url' => 'https://instagram.com/old',
        ])]);

        $this->save(['footer' => ['about_title' => '', 'cart_label' => '   ', 'description' => '', 'tagline' => 'شعار جديد', 'instagram_url' => '']])
            ->assertSessionHasNoErrors();

        $footer = Setting::singleton()->footer_content;
        $this->assertSame($defaults['about_title'], $footer['about_title'], 'Empty: the default.');
        $this->assertSame($defaults['cart_label'], $footer['cart_label'], 'Whitespace only: the default.');
        $this->assertSame($defaults['description'], $footer['description']);
        $this->assertSame('شعار جديد', $footer['tagline'], 'A value is saved.');
        $this->assertSame('حقوق مخصصة', $footer['copyright'], 'Left out: kept.');
        $this->assertSame('', $footer['instagram_url'], 'An empty link is no link, never a placeholder.');
        $this->assertSame(array_keys($defaults), array_keys($footer), 'Every field, nothing else.');
    }

    public function test_the_storefront_footer_never_shows_an_empty_heading_label_or_placeholder_link(): void
    {
        $defaults = Setting::footerDefaults();
        // Stored before this fix: empty and null values, as an empty form field used to save them.
        Setting::singleton()->update(['footer_content' => array_replace($defaults, [
            'links_title' => null, 'orders_label' => '', 'faq_label' => '  ', 'copyright' => null,
            'instagram_url' => null, 'facebook_url' => '', 'x_url' => 'https://x.com/hirfah',
        ])]);
        $stored = DB::table('settings')->value('footer_content');

        $html = $this->get(route('home'))->assertOk()->getContent();
        $footer = $this->footerHtml($html);

        foreach (['links_title', 'orders_label', 'faq_label', 'copyright'] as $key) {
            $this->assertStringContainsString($defaults[$key], $footer, $key);
        }
        $this->assertDoesNotMatchRegularExpression('#<h3[^>]*>\s*</h3>#', $footer, 'No empty heading.');
        $this->assertDoesNotMatchRegularExpression('#<span>\s*</span></a>#', $footer, 'No link without a label.');
        $this->assertStringNotContainsString('href="#"', $footer, 'No placeholder link.');
        $this->assertStringNotContainsString('aria-disabled', $footer);
        $this->assertSame(1, substr_count($footer, 'href="https://x.com/hirfah"'), 'A saved link is a link.');
        $this->assertSame($stored, DB::table('settings')->value('footer_content'), 'Rendering never rewrites what is stored.');

        // The settings form shows the same values.
        $form = $this->actingAs($this->editor(), 'admin')->get(route('dashboard.setting.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#name="footer\[links_title\]"\s+value="'.preg_quote(e($defaults['links_title']), '#').'"#u', $form);
    }

    public function test_saved_custom_content_and_escaping_are_kept(): void
    {
        $this->save(['footer' => ['tagline' => '<script>alert(1)</script>', 'description' => 'نص مخصص']])->assertSessionHasNoErrors();

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('نص مخصص', $html);
    }

    // ---------------------------------------------------------------- SET-03: the default currency

    public function test_without_a_settings_row_the_currency_is_ils_and_viewing_the_page_writes_nothing(): void
    {
        $this->assertSame('ILS', Setting::forDisplay()->default_currency);
        $this->assertFalse(Setting::forDisplay()->exists);

        $html = $this->actingAs($this->editor(), 'admin')->get(route('dashboard.setting.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#name="default_currency"\s+value="ILS"#', $html);
        $this->assertDatabaseCount('settings', 0);

        $this->get(route('home'))->assertOk()->assertViewHas('currency', 'ILS');
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_a_saved_currency_is_never_replaced_by_viewing(): void
    {
        foreach (['USD', 'ILS', 'JOD'] as $currency) {
            Setting::singleton()->forceFill(['default_currency' => $currency])->save();
            $before = (array) DB::table('settings')->first();

            $form = $this->actingAs($this->editor(), 'admin')->get(route('dashboard.setting.index'))->assertOk()->getContent();
            $this->assertMatchesRegularExpression('#name="default_currency"\s+value="'.$currency.'"#', $form);
            $this->get(route('home'))->assertOk()->assertViewHas('currency', $currency);

            $this->assertSame($before, (array) DB::table('settings')->first(), "{$currency}: the row is untouched.");
        }
    }

    public function test_saving_other_settings_keeps_the_saved_currency(): void
    {
        Setting::singleton()->forceFill(['default_currency' => 'USD'])->save();

        // The form sends the currency it shows (the saved one) with the other changes.
        $this->save(['site_name' => 'Renamed', 'default_currency' => 'USD', 'footer' => ['tagline' => 'جديد']])->assertSessionHasNoErrors();
        $this->assertSame(['Renamed', 'USD'], [Setting::singleton()->site_name, Setting::singleton()->default_currency]);

        // The home content and the site availability have their own saves and never touch it.
        $super = $this->createAdmin(superAdmin: true);
        $this->actingAs($super, 'admin')->patch(route('dashboard.setting.site-availability.update'), ['state' => 'coming_soon', 'confirm' => '1']);
        $this->assertSame('USD', Setting::singleton()->default_currency);
    }

    public function test_the_first_save_creates_the_row_with_the_currency_the_form_shows(): void
    {
        $html = $this->actingAs($this->editor(), 'admin')->get(route('dashboard.setting.index'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('/name="default_currency"\s+value="([A-Z]{3})"/', $html, $shown));

        $this->save(['default_currency' => $shown[1]])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('settings', 1);
        $this->assertSame('ILS', Setting::singleton()->default_currency);
        // A row created by any authorized write starts with ILS too.
        DB::table('settings')->delete();
        $this->assertSame('ILS', Setting::singleton()->default_currency);
    }

    // ---------------------------------------------------------------- R-02: unexpected footer content

    public function test_footer_content_of_an_unexpected_shape_falls_back_to_the_defaults_and_is_not_rewritten(): void
    {
        $defaults = Setting::footerDefaults();
        Setting::singleton();

        foreach (['"a string"', '42', 'null', 'true', '[1, 2, 3]', '{"tagline": ["nested"], "description": 7, "about_title": "عنوان محفوظ"}', 'not json at all'] as $raw) {
            DB::table('settings')->update(['footer_content' => $raw]);

            $home = $this->get(route('home'))->assertOk()->getContent();
            $this->assertStringContainsString($defaults['copyright'], $home, $raw);
            $this->actingAs($this->editor(), 'admin')->get(route('dashboard.setting.index'))->assertOk();
            $this->assertSame($raw, DB::table('settings')->value('footer_content'), "{$raw}: reading never rewrites it.");
        }

        // Valid parts of an otherwise odd object are kept; the rest falls back.
        DB::table('settings')->update(['footer_content' => '{"tagline": ["nested"], "description": 7, "about_title": "عنوان محفوظ"}']);
        $footer = Setting::footerFor(Setting::singleton());
        $this->assertSame('عنوان محفوظ', $footer['about_title']);
        $this->assertSame($defaults['tagline'], $footer['tagline']);
        $this->assertSame($defaults['description'], $footer['description']);

        // A save over unexpected content works and stores a proper object.
        DB::table('settings')->update(['footer_content' => '"a string"']);
        $this->save(['footer' => ['tagline' => 'محفوظ']])->assertSessionHasNoErrors();
        $this->assertSame('محفوظ', Setting::singleton()->footer_content['tagline']);
        $this->assertSame($defaults['about_title'], Setting::singleton()->footer_content['about_title']);
    }

    public function test_a_stored_link_that_is_not_http_is_never_rendered_as_a_link(): void
    {
        Setting::singleton()->update(['footer_content' => array_replace(Setting::footerDefaults(), ['instagram_url' => 'data://text/html,x', 'facebook_url' => 'javascript:alert(1)'])]);

        $footer = $this->footerHtml($this->get(route('home'))->assertOk()->getContent());

        $this->assertStringNotContainsString('data://', $footer);
        $this->assertStringNotContainsString('javascript:', $footer);
    }

    // ---------------------------------------------------------------- what this phase never touches

    public function test_site_availability_and_product_prices_are_unchanged(): void
    {
        $vendor = Vendor::factory()->approved()->create(['status' => 'active']);
        $category = Category::query()->create(['name' => 'Pottery', 'slug' => 'pottery', 'is_active' => true]);
        $product = Product::query()->create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Jar', 'slug' => 'jar', 'price' => 45.5, 'compare_at_price' => 39.9, 'stock_quantity' => 3, 'status' => 'active', 'published_at' => now()]);
        Setting::singleton()->forceFill(['default_currency' => 'USD'])->save();
        DB::table('settings')->update(['site_availability_mode' => 'coming_soon']);
        $productsBefore = (array) DB::table('products')->where('id', $product->id)->first();

        $this->actingAs($this->editor(), 'admin')->get(route('dashboard.setting.index'))->assertOk();
        $this->save(['default_currency' => 'USD', 'footer' => ['tagline' => '']])->assertSessionHasNoErrors();

        $this->assertSame('coming_soon', DB::table('settings')->value('site_availability_mode'));
        app(SiteAvailability::class)->forget();
        $this->assertSame(SiteAvailabilityState::ComingSoon, app(SiteAvailability::class)->current());
        $this->assertSame($productsBefore, (array) DB::table('products')->where('id', $product->id)->first());
        $this->assertSame('USD', Setting::singleton()->default_currency);
    }

    // ---------------------------------------------------------------- helpers

    private function save(array $overrides = [])
    {
        return $this->actingAs($this->editor(), 'admin')->put(route('dashboard.setting.update'), array_merge([
            'site_name' => 'Site', 'timezone' => 'UTC', 'default_locale' => 'ar', 'default_currency' => 'ILS',
        ], $overrides));
    }

    private function footerHtml(string $html): string
    {
        $this->assertSame(1, preg_match('#<footer\b.*?</footer>#s', $html, $match));

        return $match[0];
    }

    private function editor(): Admin
    {
        $admin = Admin::query()->where('email', 'settings-editor@example.com')->first() ?? $this->createAdmin(email: 'settings-editor@example.com');
        foreach (['settings.view', 'settings.edit'] as $ability) {
            RoleUser::query()->firstOrCreate(['role_name' => $ability, 'user_id' => $admin->id], ['ability' => 'allow']);
        }

        return $admin;
    }

    private function createAdmin(bool $superAdmin = false, ?string $email = null): Admin
    {
        $number = Admin::query()->count() + 1;

        return Admin::create([
            'name' => 'Admin '.$number, 'email' => $email ?? 'admin'.$number.'@example.com',
            'phone' => '05990000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'password' => 'password',
            'status' => 'active', 'super_admin' => $superAdmin,
        ]);
    }
}
