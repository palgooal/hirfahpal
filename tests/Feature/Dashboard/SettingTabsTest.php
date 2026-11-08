<?php

namespace Tests\Feature\Dashboard;

use App\Http\Requests\Dashboard\UpdateSettingRequest;
use App\Models\Admin;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\Setting;
use Database\Seeders\AdminSettingsTranslationSeeder;
use Database\Seeders\AdminSiteAvailabilityTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SETTINGS-UX-01: the settings page as three tabs. Site availability (Super Admin only) keeps its own form; general and
 * footer settings stay one form and one save. Without JavaScript the tab list is hidden and every panel shown. After a
 * failed save the tab holding the errors opens first, with the number of errors on each tab.
 */
class SettingTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->withoutVite();
    }

    public function test_a_super_admin_sees_three_tabs_and_every_panel_without_javascript(): void
    {
        $html = $this->page($this->createAdmin(superAdmin: true));

        $this->assertSame(['availability', 'general', 'footer'], $this->tabKeys($html));
        $this->assertMatchesRegularExpression('#<div class="admin-settings-tablist" role="tablist" aria-label="[^"]+" data-settings-tablist hidden>#', $html, 'Hidden until the script runs.');
        $this->assertMatchesRegularExpression('#id="settings-tab-availability" aria-controls="settings-panel-availability" aria-selected="true" tabindex="0"#', $html);
        $this->assertMatchesRegularExpression('#id="settings-tab-general" aria-controls="settings-panel-general" aria-selected="false" tabindex="-1"#', $html);
        $this->assertStringContainsString('data-initial-tab=""', $html);

        foreach (['availability', 'general', 'footer'] as $key) {
            $this->assertMatchesRegularExpression('#<section [^>]*id="settings-panel-'.$key.'"#', $html);
            $this->assertDoesNotMatchRegularExpression('#<section [^>]*id="settings-panel-'.$key.'"[^>]*\bhidden\b#', $html, "{$key} is shown without JavaScript.");
        }
        $this->assertStringContainsString('id="site-availability"', $html, 'The #site-availability links still land on the card.');
    }

    public function test_the_two_forms_stay_separate_and_hold_their_own_fields(): void
    {
        $html = $this->page($this->createAdmin(superAdmin: true));

        $this->assertSame(2, substr_count($this->settingsArea($html), '<form '));
        $availability = $this->between($html, '<section class="admin-settings-panel" id="settings-panel-availability"', '</section>');
        $settings = $this->between($html, '<form method="POST" action="'.route('dashboard.setting.update').'"', '</form>');

        $this->assertStringContainsString('action="'.route('dashboard.setting.site-availability.update').'"', $availability);
        $this->assertStringContainsString('name="confirm"', $availability);
        $this->assertStringNotContainsString('name="footer[', $availability);
        $this->assertStringNotContainsString('<form', substr($settings, 5), 'No nested form.');
        $this->assertStringNotContainsString('name="state"', $settings);
        $this->assertStringNotContainsString('name="confirm"', $settings);

        // Every general and footer field is still in the one settings form, under its own panel.
        $general = $this->between($settings, 'id="settings-panel-general"', '</section>');
        $footer = $this->between($settings, 'id="settings-panel-footer"', '</section>');
        foreach (array_keys(UpdateSettingRequest::GENERAL_LABELS) as $field) {
            $this->assertSame(1, substr_count($general, 'name="'.$field.'"'), $field);
        }
        foreach (array_keys(Setting::footerDefaults()) as $field) {
            $this->assertSame(1, substr_count($footer, 'name="footer['.$field.']"'), $field);
        }
        $this->assertSame(6, substr_count($footer, '<legend class="admin-cat-form-legend visually-hidden">'));
        $this->assertSame(3, substr_count($general, '<legend class="admin-cat-form-legend">'));
        $this->assertSame(1, substr_count($settings, 'type="submit"'), 'One save for both panels.');
    }

    public function test_an_admin_who_is_not_super_admin_never_gets_the_site_availability_tab(): void
    {
        foreach ([['settings.view'], ['settings.view', 'settings.edit']] as $abilities) {
            $html = $this->page($this->adminWith($abilities));

            $this->assertSame(['general', 'footer'], $this->tabKeys($html));
            $this->assertMatchesRegularExpression('#id="settings-tab-general" aria-controls="settings-panel-general" aria-selected="true" tabindex="0"#', $html);
            $this->assertStringNotContainsString('settings-panel-availability', $html);
            $this->assertStringNotContainsString('id="site-availability"', $html);
            $this->assertStringNotContainsString(route('dashboard.setting.site-availability.update'), $html);
            $this->assertStringNotContainsString('name="state"', $html);
            $this->assertSame(1, substr_count($this->settingsArea($html), '<form '));
        }

        $viewer = $this->page($this->adminWith(['settings.view']));
        $this->assertStringContainsString('<fieldset class="admin-settings-fields" disabled>', $viewer);
        $this->assertStringNotContainsString('type="submit"', $this->settingsArea($viewer));

        $this->actingAs($this->adminWith(['settings.view', 'settings.edit']), 'admin')
            ->patch(route('dashboard.setting.site-availability.update'), ['state' => 'maintenance', 'confirm' => '1'])
            ->assertForbidden();
    }

    public function test_a_failed_save_opens_the_tab_holding_the_errors_and_keeps_the_entered_values(): void
    {
        $editor = $this->adminWith(['settings.view', 'settings.edit']);

        $footer = $this->failedSave($editor, ['footer' => ['about_title' => str_repeat('ع', 101), 'tagline' => 'شعار جديد']]);
        $this->assertStringContainsString('data-initial-tab="footer"', $footer);
        $this->assertMatchesRegularExpression('#id="settings-tab-footer" aria-controls="settings-panel-footer" aria-selected="true" tabindex="0"#', $footer);
        $this->assertMatchesRegularExpression('#id="settings-tab-general" aria-controls="settings-panel-general" aria-selected="false"#', $footer);
        $this->assertStringContainsString('data-settings-tab-errors="1"', $footer);
        $this->assertMatchesRegularExpression('#name="footer\[tagline\]"\s+value="شعار جديد"#u', $footer);

        $both = $this->failedSave($editor, ['site_name' => str_repeat('a', 256), 'footer' => ['x_url' => 'ftp://x', 'faq_label' => str_repeat('a', 256)]]);
        $this->assertStringContainsString('data-initial-tab="general"', $both, 'The first tab with errors opens.');
        $this->assertMatchesRegularExpression('#id="settings-tab-general".*?data-settings-tab-errors="1"#s', $both);
        $this->assertMatchesRegularExpression('#id="settings-tab-footer".*?data-settings-tab-errors="2"#s', $both);

        // The messages and the success notice belong to the settings form's area (the general and footer panels).
        $area = $this->between($both, 'data-settings-form-area', '</form>');
        $this->assertStringContainsString('<div class="admin-media-alert is-error admin-cat-form-summary" role="alert" tabindex="-1" id="settings-form-errors">', $area);

        $saved = $this->actingAs($editor, 'admin')->from(route('dashboard.setting.index'))->followingRedirects()
            ->put(route('dashboard.setting.update'), $this->payload())->assertOk()->getContent();
        $this->assertStringContainsString('<div class="admin-media-alert is-success" role="status">', $this->between($saved, 'data-settings-form-area', '</form>'));
        $this->assertStringContainsString('data-initial-tab=""', $saved);
    }

    public function test_a_site_availability_message_opens_its_tab(): void
    {
        $super = $this->createAdmin(superAdmin: true);

        $html = $this->actingAs($super, 'admin')->followingRedirects()
            ->patch(route('dashboard.setting.site-availability.update'), ['state' => 'maintenance'])
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-initial-tab="availability"', $html);
        $this->assertMatchesRegularExpression('#id="settings-tab-availability" aria-controls="settings-panel-availability" aria-selected="true"#', $html);
        $this->assertSame('open', Setting::query()->value('site_availability_mode') ?? 'open', 'Not confirmed: nothing saved.');
    }

    public function test_the_tab_labels_follow_the_dashboard_language(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);
        $this->seed(AdminSiteAvailabilityTranslationSeeder::class);
        $super = $this->createAdmin(superAdmin: true);

        $ar = $this->actingAs($super, 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.setting.index'))->assertOk()->getContent();
        $this->assertSame(['حالة الموقع', 'الإعدادات العامة', 'إعدادات التذييل'], $this->tabLabels($ar));
        $this->assertStringContainsString('aria-label="أقسام الإعدادات"', $ar);
        $this->assertStringContainsString('روابط التواصل الاجتماعي', $ar);

        $en = $this->actingAs($super, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.setting.index'))->assertOk()->getContent();
        $this->assertSame(['Site availability', 'General settings', 'Footer settings'], $this->tabLabels($en));
        $this->assertStringContainsString('aria-label="Settings sections"', $en);
        $this->assertStringContainsString('Regional settings', $en);
    }

    // ---------------------------------------------------------------- SETTINGS-UX-02B: approved admin pieces

    public function test_the_page_uses_the_approved_panel_sections_fields_and_actions(): void
    {
        $html = $this->settingsArea($this->page($this->adminWith(['settings.view', 'settings.edit'])));

        $this->assertStringContainsString('<div class="col-span-12 admin-media admin-cat-form admin-settings"', $html);
        $this->assertMatchesRegularExpression('#<div class="admin-media-head">\s*<p class="admin-media-intro">#', $html);
        $this->assertStringContainsString('class="card admin-media-panel admin-cat-form-card" data-settings-form>', $html);
        $this->assertSame(9, substr_count($html, '<fieldset class="admin-cat-form-section">'));
        $this->assertStringNotContainsString('card-header', $html);

        // One save, inside the card, with its icon and label.
        $this->assertMatchesRegularExpression('#<div class="admin-cat-form-actions">\s*<button type="submit" class="btn btn-primary">\s*<i class="ph-duotone ph-floppy-disk" aria-hidden="true"></i>\s*<span>Save settings</span>#', $html);
        $this->assertLessThan(strpos($html, '</form>'), strpos($html, 'admin-cat-form-actions'));

        // The three required fields carry the mark; links, email, phone and codes read left to right.
        foreach (['timezone', 'default_locale', 'default_currency'] as $field) {
            $this->assertMatchesRegularExpression('#<label class="form-label" for="'.$field.'">[^<]+<span class="admin-cat-form-mark">\(Required\)</span></label>#', $html, $field);
            $this->assertMatchesRegularExpression('#name="'.$field.'"[^>]*dir="ltr"[^>]*aria-describedby="settings-hint-#', $html, $field);
        }
        $this->assertSame(3, substr_count($html, '<span class="admin-cat-form-mark">'));
        foreach (['email', 'phone', 'footer[instagram_url]', 'footer[facebook_url]', 'footer[x_url]'] as $field) {
            $this->assertMatchesRegularExpression('#name="'.preg_quote($field, '#').'"[^>]*dir="ltr"#', $html, $field);
        }
        $this->assertSame(3, substr_count($html, 'aria-describedby="settings-hint-social"'));
        foreach (['site_name', 'footer[tagline]', 'footer[copyright]'] as $field) {
            $this->assertMatchesRegularExpression('#name="'.preg_quote($field, '#').'"[^>]*dir="auto"#', $html, $field);
        }
    }

    public function test_the_error_summary_links_each_field_by_its_translated_name(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);
        $html = $this->actingAs($this->adminWith(['settings.view', 'settings.edit']), 'admin')->withSession(['locale' => 'ar'])
            ->from(route('dashboard.setting.index'))->followingRedirects()
            ->put(route('dashboard.setting.update'), $this->payload(['site_name' => str_repeat('a', 256), 'footer' => ['x_url' => 'ftp://x']]))
            ->assertOk()->getContent();

        $summary = $this->between($html, 'id="settings-form-errors"', '</ul>');
        $this->assertStringContainsString('يرجى تصحيح ما يلي:', $summary);
        $this->assertMatchesRegularExpression('#<li><a href="\#site_name">[^<]+</a>: #u', $summary);
        $this->assertStringContainsString('<li><a href="#footer[x_url]">رابط إكس (X)</a>: ', $summary);
        $this->assertStringNotContainsString('footer.x_url', $summary);
        // Each link's target exists: the field's id.
        $this->assertStringContainsString('id="footer[x_url]"', $html);
    }

    public function test_the_site_availability_card_uses_the_approved_panel_and_keeps_its_form(): void
    {
        $html = $this->page($this->createAdmin(superAdmin: true));
        $card = $this->between($html, 'id="site-availability"', '</form>');

        $this->assertStringContainsString('<div class="card admin-media-panel">', $card);
        $this->assertStringNotContainsString('card-header', $card);
        $this->assertStringContainsString('<form method="POST" action="'.route('dashboard.setting.site-availability.update').'">', $card);
        $this->assertStringContainsString('name="confirm"', $card);
        $this->assertMatchesRegularExpression('#data-site-availability-save><i class="ph-duotone ph-floppy-disk" aria-hidden="true"></i><span>#', $card);
    }

    // ---------------------------------------------------------------- SETTINGS-UX-03: footer groups

    public function test_the_footer_groups_are_disclosures_all_open_without_javascript(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);
        $html = $this->actingAs($this->adminWith(['settings.view', 'settings.edit']), 'admin')->withSession(['locale' => 'en'])
            ->get(route('dashboard.setting.index'))->assertOk()->getContent();
        $footer = $this->between($html, 'id="settings-panel-footer"', '</section>');

        preg_match_all('#<details class="card admin-media-panel admin-settings-group" id="settings-group-([a-z]+)" open data-settings-group data-settings-group-open="(true|false)" data-settings-group-errors="0">#', $footer, $groups, PREG_SET_ORDER);
        $this->assertSame(['identity', 'social', 'navigation', 'service', 'payment', 'copyright'], array_column($groups, 1), 'Six groups, every one rendered open.');
        $this->assertSame(['true', 'false', 'false', 'false', 'false', 'false'], array_column($groups, 2), 'The script keeps only the first open.');
        $this->assertSame(6, substr_count($footer, '</details>'));

        // Summary: icon, title, description and chevron; no button, so toggling never submits the form.
        $this->assertMatchesRegularExpression('#<summary class="admin-settings-group-summary">\s*<span class="admin-settings-group-icon" aria-hidden="true"><i class="ph-duotone ph-share-network"></i></span>\s*<span class="admin-settings-group-text">\s*<span class="admin-settings-group-title">Social links</span>\s*<span class="admin-settings-group-hint">The social media accounts shown as icons.</span>\s*</span>\s*<i class="ph-duotone ph-caret-down admin-settings-group-chevron" aria-hidden="true"></i>\s*</summary>#', $footer);
        $this->assertSame(0, preg_match('#<summary[^>]*>(?:(?!</summary>).)*<button#s', $footer));

        // Every footer field sits in its group, unchanged.
        foreach (['identity' => ['tagline', 'description'], 'social' => ['instagram_url', 'facebook_url', 'x_url'], 'copyright' => ['copyright']] as $group => $fields) {
            $body = $this->between($footer, 'id="settings-group-'.$group.'"', '</details>');
            foreach ($fields as $field) {
                $this->assertStringContainsString('name="footer['.$field.']"', $body, $group.' '.$field);
            }
        }
        $this->assertSame(23, preg_match_all('#name="footer\[[a-z_]+\]"#', $footer));

        $ar = $this->actingAs($this->adminWith(['settings.view']), 'admin')->withSession(['locale' => 'ar'])
            ->get(route('dashboard.setting.index'))->getContent();
        $this->assertStringContainsString('<span class="admin-settings-group-title">روابط التواصل الاجتماعي</span>', $ar);
        $this->assertStringContainsString('<span class="admin-settings-group-hint">حسابات التواصل الاجتماعي المعروضة كأيقونات.</span>', $ar);
    }

    public function test_groups_holding_errors_are_marked_to_open_and_keep_the_entered_values(): void
    {
        $html = $this->failedSave($this->adminWith(['settings.view', 'settings.edit']), ['footer' => [
            'x_url' => 'ftp://x', 'faq_label' => str_repeat('a', 256), 'tagline' => 'شعار جديد',
        ]]);

        foreach (['identity' => 0, 'social' => 1, 'navigation' => 0, 'service' => 1, 'payment' => 0, 'copyright' => 0] as $group => $count) {
            $this->assertMatchesRegularExpression('#id="settings-group-'.$group.'" open data-settings-group data-settings-group-open="[a-z]+" data-settings-group-errors="'.$count.'">#', $html, $group);
        }
        $social = $this->between($html, 'id="settings-group-social"', '</summary>');
        $this->assertMatchesRegularExpression('#<span class="admin-settings-tab-alert"><span aria-hidden="true">1</span><span class="visually-hidden">[^<]+</span></span>#', $social);
        $this->assertStringNotContainsString('admin-settings-tab-alert', $this->between($html, 'id="settings-group-identity"', '</summary>'));
        $this->assertMatchesRegularExpression('#name="footer\[tagline\]"\s+value="شعار جديد"#u', $html);
        $this->assertStringContainsString('<li><a href="#footer[x_url]">', $html, 'The summary link targets the field in its group.');
    }

    private function page(Admin $admin): string
    {
        return $this->actingAs($admin, 'admin')->get(route('dashboard.setting.index'))->assertOk()->getContent();
    }

    private function failedSave(Admin $admin, array $overrides): string
    {
        return $this->actingAs($admin, 'admin')->from(route('dashboard.setting.index'))->followingRedirects()
            ->put(route('dashboard.setting.update'), $this->payload($overrides))->assertOk()->getContent();
    }

    /** @return list<string> */
    private function tabKeys(string $html): array
    {
        preg_match_all('#role="tab" id="settings-tab-([a-z]+)"#', $html, $matches);

        return $matches[1];
    }

    /** @return list<string> */
    private function tabLabels(string $html): array
    {
        preg_match_all('#data-settings-tab="[a-z]+">\s*<span>([^<]+)</span>#u', $html, $matches);

        return $matches[1];
    }

    /** The settings tabs and panels (the layout has forms of its own). */
    private function settingsArea(string $html): string
    {
        $from = strpos($html, '<div class="col-span-12 admin-media admin-cat-form admin-settings"');
        $this->assertNotFalse($from);
        $to = strpos($html, '</form>', strpos($html, 'data-settings-form', $from));

        return substr($html, $from, $to - $from + strlen('</form>'));
    }

    private function between(string $html, string $start, string $end): string
    {
        $from = strpos($html, $start);
        $this->assertNotFalse($from, $start);
        $to = strpos($html, $end, $from);
        $this->assertNotFalse($to, $end);

        return substr($html, $from, $to - $from + strlen($end));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['site_name' => 'Site', 'timezone' => 'UTC', 'default_locale' => 'ar', 'default_currency' => 'ILS'], $overrides);
    }

    private function adminWith(array $abilities): Admin
    {
        $admin = $this->createAdmin();
        foreach ($abilities as $ability) {
            RoleUser::query()->create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }

    private function createAdmin(bool $superAdmin = false): Admin
    {
        $number = Admin::query()->count() + 1;

        return Admin::create([
            'name' => 'Admin '.$number, 'email' => 'admin'.$number.'@example.com',
            'phone' => '05990000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'password' => 'password',
            'status' => 'active', 'super_admin' => $superAdmin,
        ]);
    }
}
