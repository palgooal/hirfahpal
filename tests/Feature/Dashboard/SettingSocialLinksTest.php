<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\Setting;
use App\Models\TranslationValue;
use Database\Seeders\AdminSettingsTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FOOTER-SOCIAL-02B: the footer's social links managed from Settings → Footer → Social links. Saved as an ordered
 * footer_content.social_links (legacy fields kept in step), only when the form sends them; validated per platform;
 * legacy links shown without migration; text fields and other settings untouched.
 */
class SettingSocialLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->withoutVite();
    }

    public function test_legacy_links_appear_in_the_form_without_migration_and_viewing_writes_nothing(): void
    {
        $this->store(['instagram_url' => 'https://instagram.com/hirfah', 'facebook_url' => '', 'x_url' => 'https://x.com/hirfah']);
        $before = DB::table('settings')->get()->toArray();

        $html = $this->form();

        $this->assertSame(['instagram', 'x', 'facebook', 'whatsapp', 'tiktok', 'youtube', 'linkedin', 'telegram'], $this->rowOrder($html), 'Added first, in their order; then the others.');
        $this->assertSame(['instagram' => '1', 'x' => '1', 'facebook' => '0'], array_intersect_key($this->configured($html), array_flip(['instagram', 'x', 'facebook'])));
        $this->assertMatchesRegularExpression('#name="footer_social\[instagram\]\[url\]" value="https://instagram.com/hirfah"#', $html);
        $this->assertMatchesRegularExpression('#name="footer_social\[x\]\[position\]" value="2"#', $html);
        $this->assertEquals($before, DB::table('settings')->get()->toArray());
    }

    public function test_the_form_works_without_javascript_and_names_every_control(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);
        $html = $this->form('ar');
        $manager = $this->between($html, 'data-social-links', '</ol>');

        // Every row is in the form with its address, order and shown state; the script-only controls start hidden.
        $this->assertSame(8, substr_count($manager, 'data-social-order-field>'));
        $this->assertSame(8, substr_count($manager, '<span class="admin-settings-social-actions" data-social-actions hidden>'));
        $this->assertSame(24, substr_count($manager, '<button type="button" class="btn btn-light-secondary"'), 'Never a submit button.');
        $this->assertStringContainsString('<div class="admin-settings-social-add" data-social-add hidden>', $html);
        $this->assertStringContainsString('aria-label="نقل إنستغرام للأعلى"', $manager);
        $this->assertStringContainsString('aria-label="إزالة تيليجرام"', $manager);
        $this->assertStringContainsString('<label class="form-label" for="footer_social_whatsapp_url">رابط واتساب</label>', $manager);
        $this->assertStringContainsString('aria-live="polite" data-social-status', $html);

        $en = $this->between($this->form('en'), 'data-social-links', '</ol>');
        $this->assertStringContainsString('aria-label="Move YouTube down"', $en);
        $this->assertStringContainsString('<label class="form-check-label" for="footer_social_linkedin_enabled">Show in the footer</label>', $en);
    }

    public function test_an_ordered_list_is_saved_with_disabled_links_and_the_legacy_fields_follow_it(): void
    {
        $this->store(['tagline' => 'شعار محفوظ', 'instagram_url' => 'https://instagram.com/old']);

        $this->save(['footer_social' => $this->rows([
            'telegram' => ['https://t.me/hirfah', true, 1],
            'instagram' => ['https://instagram.com/hirfah', false, 2],
            'x' => [' https://x.com/hirfah ', true, 3],
            'facebook' => ['', true, 4],
        ])])->assertRedirect(route('dashboard.setting.index'))->assertSessionHasNoErrors();

        $content = Setting::query()->first()->footer_content;
        $this->assertSame([
            ['platform' => 'telegram', 'url' => 'https://t.me/hirfah', 'enabled' => true],
            ['platform' => 'instagram', 'url' => 'https://instagram.com/hirfah', 'enabled' => false],
            ['platform' => 'x', 'url' => 'https://x.com/hirfah', 'enabled' => true],
        ], $content['social_links']);
        $this->assertSame(['', '', 'https://x.com/hirfah'], [$content['instagram_url'], $content['facebook_url'], $content['x_url']], 'Disabled or removed: empty; enabled: its address.');
        $this->assertSame('شعار محفوظ', $content['tagline'], 'Text fields are untouched.');

        // The storefront: enabled ones only, in order; the disabled one stays saved but hidden.
        $footer = $this->storefrontFooter();
        preg_match_all('#data-footer-social="([a-z]+)"#', $footer, $shown);
        $this->assertSame(['telegram', 'x'], $shown[1]);

        // The form shows the saved list, disabled entry included, in order.
        $html = $this->form();
        $this->assertSame(['telegram', 'instagram', 'x'], array_slice($this->rowOrder($html), 0, 3));
        $this->assertDoesNotMatchRegularExpression('#id="footer_social_instagram_enabled"[^>]*checked#', $html);
    }

    public function test_an_omitted_list_keeps_the_saved_one_and_an_empty_one_removes_everything(): void
    {
        $this->store(['social_links' => [['platform' => 'youtube', 'url' => 'https://youtu.be/a', 'enabled' => true]], 'x_url' => 'https://x.com/legacy']);

        // A save without the social list (another client, or only the text fields): the list stays.
        $this->save(['footer' => ['ar' => ['tagline' => 'جديد']]])->assertSessionHasNoErrors();
        $this->assertSame([['platform' => 'youtube', 'url' => 'https://youtu.be/a', 'enabled' => true]], Setting::query()->first()->footer_content['social_links']);
        $this->save([])->assertSessionHasNoErrors();
        $this->assertCount(1, Setting::query()->first()->footer_content['social_links']);

        // The list sent with every address empty: saved as empty; nothing comes back through the legacy fields.
        $this->save(['footer_social' => $this->rows(['youtube' => ['', true, 1], 'x' => [null, true, 2]])])->assertSessionHasNoErrors();
        $content = Setting::query()->first()->footer_content;
        $this->assertSame([], $content['social_links']);
        $this->assertSame('', $content['x_url']);
        $this->assertStringNotContainsString('data-footer-social', $this->storefrontFooter());
    }

    public function test_invalid_lookalike_and_unknown_entries_are_rejected_and_nothing_is_saved(): void
    {
        $this->store(['social_links' => [['platform' => 'instagram', 'url' => 'https://instagram.com/keep', 'enabled' => true]]]);
        $before = DB::table('settings')->value('footer_content');

        $cases = [
            ['footer_social.instagram.url', $this->rows(['instagram' => ['https://instagram.com.example.org/a', true, 1]])],
            ['footer_social.instagram.url', $this->rows(['instagram' => ['javascript:alert(1)', true, 1]])],
            ['footer_social.facebook.url', $this->rows(['facebook' => ['https://instagram.com/a', true, 1]])],
            ['footer_social.x.url', $this->rows(['x' => [str_repeat('a', 256), true, 1]])],
            ['footer_social', ['myspace' => ['url' => 'https://myspace.com/a', 'enabled' => '1', 'position' => '1']]],
            ['footer_social', 'instagram'],
            ['footer_social.instagram', ['instagram' => ['url' => 'https://instagram.com/a', 'enabled' => '1', 'position' => '1', 'extra' => 'x']]],
            ['footer_social.instagram.enabled', ['instagram' => ['url' => 'https://instagram.com/a', 'enabled' => 'maybe', 'position' => '1']]],
            ['footer_social.instagram.position', $this->rows(['instagram' => ['https://instagram.com/a', true, 9]])],
        ];

        foreach ($cases as [$field, $rows]) {
            $this->save(['footer_social' => $rows])->assertSessionHasErrors($field);
            $this->assertSame($before, DB::table('settings')->value('footer_content'), $field);
        }

        // Two platforms in the same position: both are told, none is saved.
        $this->save(['footer_social' => $this->rows(['instagram' => ['https://instagram.com/a', true, 2], 'tiktok' => ['https://tiktok.com/@a', true, 2]])])
            ->assertSessionHasErrors(['footer_social.instagram.position', 'footer_social.tiktok.position']);
        $this->assertSame($before, DB::table('settings')->value('footer_content'));
    }

    public function test_after_a_failed_save_the_form_keeps_what_was_sent_with_the_error_under_its_field(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);
        $html = $this->actingAs($this->editor(), 'admin')->withSession(['locale' => 'en'])->from(route('dashboard.setting.index'))->followingRedirects()
            ->put(route('dashboard.setting.update'), $this->payload(['footer_social' => $this->rows([
                'linkedin' => ['https://linkedin.com/company/a', true, 1],
                'whatsapp' => ['https://wa.me.example.org/1', false, 2],
            ])]))->assertOk()->getContent();

        $this->assertSame(['linkedin', 'whatsapp'], array_slice($this->rowOrder($html), 0, 2), 'The sent order.');
        $this->assertSame('1', $this->configured($html)['whatsapp']);
        $this->assertMatchesRegularExpression('#name="footer_social\[whatsapp\]\[url\]" value="https://wa.me.example.org/1"[^>]*class="form-control is-invalid" aria-describedby="settings-hint-social footer_social_whatsapp_url_error"\s+aria-invalid="true"#', $html);
        $this->assertStringContainsString('<p class="admin-cat-form-error" id="footer_social_whatsapp_url_error">Enter a valid WhatsApp address starting with https://.</p>', $html);
        $this->assertStringContainsString('<li><a href="#footer_social_whatsapp_url">WhatsApp link</a>: Enter a valid WhatsApp address starting with https://.</li>', $html);
        $this->assertMatchesRegularExpression('#id="settings-group-social" open data-settings-group data-settings-group-open="false" data-settings-group-errors="1">#', $html);
        $this->assertStringContainsString('data-initial-tab="footer"', $html);
    }

    // ---------------------------------------------------------------- FOOTER-SOCIAL-02D: saved links needing attention

    public function test_a_saved_link_the_platform_check_rejects_is_flagged_on_its_row_and_kept(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);
        $this->store(['facebook_url' => 'http://127.0.0.1:8002/dashboard/settings', 'instagram_url' => 'https://linktr.ee/hirfah', 'x_url' => 'https://x.com/hirfah']);
        $before = DB::table('settings')->value('footer_content');

        $html = $this->form('ar');

        $this->assertSame(2, substr_count($html, 'data-social-warning'));
        $this->assertStringContainsString('<div class="admin-media-alert is-warning mt-2" id="footer_social_facebook_url_warning" data-social-warning>', $html);
        $this->assertStringContainsString('هذا الرابط المحفوظ ليس رابط فيسبوك صالحًا، لذلك لا يظهر في المتجر. صحّحه أو أزِل المنصة قبل الحفظ.', $html);
        $this->assertMatchesRegularExpression('#name="footer_social\[instagram\]\[url\]" value="https://linktr.ee/hirfah"[^>]*aria-describedby="settings-hint-social footer_social_instagram_url_warning"#', $html, 'Kept as saved, described by its warning.');
        $this->assertStringNotContainsString('footer_social_x_url_warning', $html, 'A valid link has no warning.');
        $this->assertDoesNotMatchRegularExpression('#name="footer_social\[instagram\]\[url\]"[^>]*is-invalid#', $html, 'A warning, not a validation error.');

        // Discoverable: the Footer tab is suggested and the Social links group starts open; nothing is forced.
        $this->assertStringContainsString('data-initial-tab="" data-suggested-tab="footer"', $html);
        $this->assertMatchesRegularExpression('#id="settings-group-social" open data-settings-group data-settings-group-open="true"#', $html);
        $this->assertEquals($before, DB::table('settings')->value('footer_content'), 'Showing the warning writes nothing.');
    }

    public function test_valid_disabled_and_empty_links_raise_no_warning(): void
    {
        $this->store(['social_links' => [
            ['platform' => 'instagram', 'url' => 'https://instagram.com/a', 'enabled' => true],
            ['platform' => 'telegram', 'url' => 'https://t.me/a', 'enabled' => false],
            ['platform' => 'youtube', 'url' => '', 'enabled' => true],
        ]]);
        $html = $this->form();
        $this->assertStringNotContainsString('data-social-warning', $html);
        $this->assertStringContainsString('data-suggested-tab=""', $html);
        $this->assertMatchesRegularExpression('#id="settings-group-social" open data-settings-group data-settings-group-open="false"#', $html);

        $this->store(['instagram_url' => '', 'facebook_url' => null, 'x_url' => '']);
        $this->assertStringNotContainsString('data-social-warning', $this->form());
    }

    public function test_after_a_failed_save_the_validation_error_replaces_the_warning_and_leads_navigation(): void
    {
        $this->store(['facebook_url' => 'https://linktr.ee/hirfah']);

        $html = $this->actingAs($this->editor(), 'admin')->from(route('dashboard.setting.index'))->followingRedirects()
            ->put(route('dashboard.setting.update'), $this->payload(['site_name' => str_repeat('a', 256),
                'footer_social' => $this->rows(['facebook' => ['https://linktr.ee/hirfah', true, 1]])]))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-social-warning', $html);
        $this->assertStringContainsString('id="footer_social_facebook_url_error"', $html);
        $this->assertStringContainsString('data-initial-tab="general" data-suggested-tab=""', $html, 'The server picks the tab with errors.');
        $this->assertSame('https://linktr.ee/hirfah', Setting::query()->first()->footer_content['facebook_url'], 'Still saved until corrected or removed.');
    }

    public function test_only_an_admin_who_may_edit_settings_can_save_social_links(): void
    {
        $viewer = $this->createAdmin();
        RoleUser::query()->create(['role_name' => 'settings.view', 'user_id' => $viewer->id, 'ability' => 'allow']);
        $rows = ['footer_social' => $this->rows(['telegram' => ['https://t.me/a', true, 1]])];

        $this->actingAs($viewer, 'admin')->put(route('dashboard.setting.update'), $this->payload($rows))->assertForbidden();
        $this->assertSame(0, Setting::query()->count());

        auth('admin')->logout();
        $this->put(route('dashboard.setting.update'), $this->payload($rows))->assertRedirect();
        $this->assertSame(0, Setting::query()->count());
    }

    public function test_a_social_save_changes_no_other_setting_and_creates_no_translation_row(): void
    {
        $this->store(['copyright' => 'حقوق محفوظة']);
        Setting::query()->first()->forceFill(['default_currency' => 'ILS', 'site_availability_mode' => 'open'])->save();
        // The success notice's own key is auto-created by the existing controller message (not a social text).
        $rows = fn () => TranslationValue::query()->where('key', '!=', 'dashboard.Settings_updated_successfully')->count();
        $before = $rows();

        $this->save(['footer_social' => $this->rows(['tiktok' => ['https://www.tiktok.com/@hirfah', true, 1]])])->assertSessionHasNoErrors();

        $setting = Setting::query()->first();
        $this->assertSame('حقوق محفوظة', $setting->footer_content['copyright']);
        $this->assertSame(['ILS', 'open'], [$setting->default_currency, $setting->site_availability_mode]);
        $this->assertSame($before, $rows());
    }

    /** @param array<string, array{0: ?string, 1: bool, 2: int}> $rows */
    private function rows(array $rows): array
    {
        return array_map(fn ($row) => ['url' => $row[0], 'enabled' => $row[1] ? '1' : '0', 'position' => (string) $row[2]], $rows);
    }

    private function store(array $content): void
    {
        Setting::singleton()->update(['footer_content' => array_replace(Setting::footerDefaults(), $content)]);
    }

    private function save(array $overrides)
    {
        return $this->actingAs($this->editor(), 'admin')->from(route('dashboard.setting.index'))
            ->put(route('dashboard.setting.update'), $this->payload($overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['site_name' => 'Site', 'timezone' => 'UTC', 'default_locale' => 'ar', 'default_currency' => 'ILS'], $overrides);
    }

    private function form(string $locale = 'ar'): string
    {
        return $this->actingAs($this->editor(), 'admin')->withSession(['locale' => $locale])->get(route('dashboard.setting.index'))->assertOk()->getContent();
    }

    private function storefrontFooter(): string
    {
        auth('admin')->logout();
        $this->assertSame(1, preg_match('#<footer\b.*?</footer>#s', $this->get(route('home'))->assertOk()->getContent(), $match));

        return $match[0];
    }

    /** @return list<string> */
    private function rowOrder(string $html): array
    {
        preg_match_all('#data-social-row="([a-z]+)"#', $html, $matches);

        return $matches[1];
    }

    /** @return array<string, string> */
    private function configured(string $html): array
    {
        preg_match_all('#data-social-row="([a-z]+)" data-social-configured="([01])"#', $html, $matches);

        return array_combine($matches[1], $matches[2]);
    }

    private function between(string $html, string $start, string $end): string
    {
        $from = strpos($html, $start);
        $this->assertNotFalse($from, $start);

        return substr($html, $from, strpos($html, $end, $from) - $from + strlen($end));
    }

    private function editor(): Admin
    {
        $admin = Admin::query()->where('email', 'social-editor@example.com')->first() ?? $this->createAdmin('social-editor@example.com');
        foreach (['settings.view', 'settings.edit'] as $ability) {
            RoleUser::query()->firstOrCreate(['role_name' => $ability, 'user_id' => $admin->id], ['ability' => 'allow']);
        }

        return $admin;
    }

    private function createAdmin(?string $email = null): Admin
    {
        $number = Admin::query()->count() + 1;

        return Admin::create([
            'name' => 'Admin '.$number, 'email' => $email ?? 'admin'.$number.'@example.com',
            'phone' => '05990000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'password' => 'password',
            'status' => 'active', 'super_admin' => false,
        ]);
    }
}
