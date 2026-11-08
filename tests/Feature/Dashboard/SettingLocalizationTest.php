<?php

namespace Tests\Feature\Dashboard;

use App\Http\Requests\Dashboard\UpdateSettingRequest;
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
 * SETTINGS-FIX-02. SET-04: the footer section of the settings page reads its heading, hint and labels from
 * dashboard.Settings_Footer_* (AdminSettingsTranslationSeeder) without auto-creating rows. SET-05: a footer[x] field
 * shows its own validation error (validated as footer.x) and keeps the entered value. SET-06: the textarea has an id
 * and its label points to it. SET-08: the storefront footer's social links have translated accessible names; an
 * inactive icon stays a hidden, non-interactive span.
 */
class SettingLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    // ---------------------------------------------------------------- SET-04: localization

    public function test_the_seeder_covers_exactly_the_keys_the_views_read_with_their_english_defaults(): void
    {
        $this->assertCount(54, AdminSettingsTranslationSeeder::TRANSLATIONS);

        $view = file_get_contents(resource_path('views/dashboard/settings/index.blade.php'));
        $defaults = [];
        preg_match_all("/\\\$settingsText\('([A-Za-z_]+)', '([^']+)'\)/", $view, $matches, PREG_SET_ORDER);
        foreach ($matches as [, $key, $default]) {
            $defaults['dashboard.'.$key] = $default;
        }
        unset($defaults['dashboard.Site_Availability_Title']); // AdminSiteAvailabilityTranslationSeeder's key.
        // SETTINGS-UX-01: the footer groups' legends ([legend key, English legend, [fields]]).
        preg_match_all("/\['(Settings_Group_[A-Za-z_]+)', '([^']+)', \[/", $view, $matches, PREG_SET_ORDER);
        foreach ($matches as [, $key, $default]) {
            $defaults['dashboard.'.$key] = $default;
        }
        // Field labels: dashboard.Settings_Footer_<Field> built from each footer key (x_url → X_Url).
        preg_match_all("/\['([a-z_]+)', '([^']+)'(?:, 'textarea')?\]/", $view, $matches, PREG_SET_ORDER);
        foreach ($matches as [, $key, $default]) {
            $defaults['dashboard.Settings_Footer_'.implode('_', array_map('ucfirst', explode('_', $key)))] = $default;
        }
        $footer = file_get_contents(resource_path('views/storefront/partials/footer.blade.php'));
        preg_match_all("/\\\$socialText\('([A-Za-z]+)', '([^']+)'\)/", $footer, $matches, PREG_SET_ORDER);
        foreach ($matches as [, $key, $default]) {
            $defaults['storefront.Footer_Social_'.$key] = $default;
        }

        $this->assertCount(51, array_filter(array_keys($defaults), fn ($key) => str_starts_with($key, 'dashboard.')));
        $labels = array_map(
            fn ($key) => strtolower(substr($key, strlen('dashboard.Settings_Footer_'))),
            array_values(array_filter(array_keys($defaults), fn ($key) => str_starts_with($key, 'dashboard.Settings_Footer_') && ! in_array($key, ['dashboard.Settings_Footer_Title', 'dashboard.Settings_Footer_Hint'], true)))
        );
        $fields = array_keys(Setting::footerDefaults());
        sort($labels);
        sort($fields);
        $this->assertSame($fields, $labels, 'One label per footer field.');

        ksort($defaults);
        $seeded = AdminSettingsTranslationSeeder::TRANSLATIONS;
        ksort($seeded);
        $this->assertSame(array_keys($defaults), array_keys($seeded));
        foreach ($seeded as $key => $values) {
            $this->assertSame(['ar', 'en'], array_keys($values), $key);
            $this->assertSame($defaults[$key], $values['en'], "{$key} en is the default in the view.");
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], "{$key} ar must be Arabic.");
        }
    }

    public function test_the_footer_section_is_arabic_in_arabic_and_english_in_english(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);

        $ar = $this->actingAs($this->editor(), 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.setting.index'))->assertOk();
        $ar->assertSee('إعدادات التذييل')->assertDontSee('إعدادات الفوتر')->assertSee('هذه البيانات تظهر مباشرة في تذييل الموقع.')
            ->assertSee('رابط إنستغرام')->assertSee('نص الدفع عند الاستلام')->assertDontSee('Footer settings');

        $en = $this->actingAs($this->editor(), 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.setting.index'))->assertOk();
        $en->assertSee('Footer settings')->assertSee('These details appear directly in the site footer.')
            ->assertSee('Instagram link')->assertSee('Cash on delivery label')->assertDontSee('إعدادات التذييل');

        // Every one of the 23 labels, bound to its field.
        foreach (Setting::footerDefaults() as $key => $value) {
            $label = AdminSettingsTranslationSeeder::TRANSLATIONS['dashboard.Settings_Footer_'.implode('_', array_map('ucfirst', explode('_', $key)))]['en'];
            $this->assertMatchesRegularExpression('#for="footer\['.$key.'\]">\s*'.preg_quote(e($label), '#').'\s*</label>#u', $en->getContent(), $key);
        }
    }

    public function test_the_page_creates_no_translation_row_before_the_seeder_runs(): void
    {
        $this->actingAs($this->editor(), 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.setting.index'))
            ->assertOk()->assertSee('Footer settings');
        $this->get(route('home'))->assertOk();

        $this->assertSame(0, TranslationValue::where('key', 'like', 'dashboard.Settings_Footer_%')->count());
        $this->assertSame(0, TranslationValue::where('key', 'like', 'storefront.Footer_Social_%')->count());
    }

    public function test_the_seeder_only_inserts_missing_rows_and_is_idempotent(): void
    {
        TranslationValue::create(['key' => 'dashboard.Settings_Footer_Title', 'locale' => 'ar', 'value' => 'تذييل الموقع']);
        TranslationValue::create(['key' => 'dashboard.Something_else', 'locale' => 'ar', 'value' => 'قيمة أخرى']);
        cache()->put('translation.en.dashboard.Settings_Footer_Hint', 'stale', 60);

        $this->seed(AdminSettingsTranslationSeeder::class);
        $first = TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();
        $this->seed(AdminSettingsTranslationSeeder::class);

        $this->assertSame($first, TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
        $this->assertSame(135, TranslationValue::count(), '133 missing rows (67 keys in two languages, one existing) inserted, the two existing rows kept.');
        $this->assertSame('تذييل الموقع', TranslationValue::where('key', 'dashboard.Settings_Footer_Title')->where('locale', 'ar')->value('value'));
        $this->assertSame('قيمة أخرى', TranslationValue::where('key', 'dashboard.Something_else')->value('value'));
        $this->assertFalse(cache()->has('translation.en.dashboard.Settings_Footer_Hint'), 'A newly inserted row clears its stale cache entry.');
        $this->assertStringNotContainsString('AdminSettingsTranslationSeeder', file_get_contents(database_path('seeders/DatabaseSeeder.php')));
    }

    // ---------------------------------------------------------------- SETTINGS-I18N-01: general labels

    public function test_the_general_labels_match_the_view_and_the_request(): void
    {
        $view = file_get_contents(resource_path('views/dashboard/settings/index.blade.php'));
        preg_match_all("/t\('dashboard\.([A-Za-z_]+)', '([^']+)'\)/", $view, $matches, PREG_SET_ORDER);
        $read = [];
        foreach ($matches as [, $key, $default]) {
            $read['dashboard.'.$key] = $default;
        }
        unset($read['dashboard.Settings']); // AdminAbilityTranslationSeeder already corrects it.

        $corrections = array_map(fn (array $correction) => $correction['from'], AdminSettingsTranslationSeeder::CORRECTIONS);
        // SETTINGS-UX-02B: the page's button now reads dashboard.Settings_Save; the shared dashboard.Save correction
        // stays for the other pages that read it.
        unset($corrections['dashboard.Save']);
        ksort($read);
        ksort($corrections);
        $this->assertSame($read, $corrections, 'Every general label the page reads, with its English default.');

        foreach (UpdateSettingRequest::GENERAL_LABELS as $field => [$key, $default]) {
            $this->assertSame($corrections['dashboard.'.$key], $default, $field);
        }
        foreach (UpdateSettingRequest::FOOTER_LABELS as $field => $default) {
            $key = 'dashboard.Settings_Footer_'.implode('_', array_map('ucfirst', explode('_', $field)));
            $this->assertSame(AdminSettingsTranslationSeeder::TRANSLATIONS[$key]['en'], $default, $field);
        }
        $this->assertSame(array_keys(Setting::footerDefaults()), array_keys(UpdateSettingRequest::FOOTER_LABELS));
        foreach (AdminSettingsTranslationSeeder::CORRECTIONS as $key => $correction) {
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $correction['to'], $key);
        }
    }

    public function test_auto_created_english_arabic_rows_are_corrected_and_edited_rows_are_kept(): void
    {
        // What t()'s auto-create stored when the page was first viewed in Arabic: the English default in both locales.
        foreach (['dashboard.Site_Name' => 'Site Name', 'dashboard.Timezone' => 'Timezone', 'dashboard.Save' => 'Save'] as $key => $value) {
            TranslationValue::create(['key' => $key, 'locale' => 'ar', 'value' => $value]);
            TranslationValue::create(['key' => $key, 'locale' => 'en', 'value' => $value]);
        }
        // An admin's own wording (and one that differs from the default only in case) is never replaced.
        TranslationValue::where('key', 'dashboard.Timezone')->where('locale', 'ar')->update(['value' => 'التوقيت']);
        TranslationValue::where('key', 'dashboard.Save')->where('locale', 'ar')->update(['value' => 'save']);
        cache()->put('translation.ar.dashboard.Site_Name', 'Site Name', 60);

        $this->seed(AdminSettingsTranslationSeeder::class);

        $this->assertSame('اسم الموقع', $this->value('dashboard.Site_Name', 'ar'));
        $this->assertSame('Site Name', $this->value('dashboard.Site_Name', 'en'));
        $this->assertSame('التوقيت', $this->value('dashboard.Timezone', 'ar'));
        $this->assertSame('save', $this->value('dashboard.Save', 'ar'));
        $this->assertFalse(cache()->has('translation.ar.dashboard.Site_Name'), 'A corrected row clears its cache entry.');

        $html = $this->actingAs($this->editor(), 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.setting.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#for="site_name">\s*اسم الموقع#u', $html);
        $this->assertMatchesRegularExpression('#for="default_currency">\s*العملة الافتراضية#u', $html);
        $this->assertStringContainsString('إدارة الإعدادات الأساسية للموقع.', $html);
        $this->assertStringNotContainsString('Site Description', $html);

        $en = $this->actingAs($this->editor(), 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.setting.index'))->getContent();
        $this->assertMatchesRegularExpression('#for="site_name">\s*Site Name#u', $en);
    }

    public function test_the_old_arabic_footer_heading_is_corrected_and_an_edited_one_is_kept(): void
    {
        TranslationValue::create(['key' => 'dashboard.Settings_Footer_Title', 'locale' => 'ar', 'value' => 'إعدادات الفوتر']);
        TranslationValue::create(['key' => 'dashboard.Settings_Footer_Title', 'locale' => 'en', 'value' => 'Footer settings']);
        cache()->put('translation.ar.dashboard.Settings_Footer_Title', 'إعدادات الفوتر', 60);

        $this->seed(AdminSettingsTranslationSeeder::class);

        $this->assertSame('إعدادات التذييل', $this->value('dashboard.Settings_Footer_Title', 'ar'));
        $this->assertSame('Footer settings', $this->value('dashboard.Settings_Footer_Title', 'en'));
        $this->assertFalse(cache()->has('translation.ar.dashboard.Settings_Footer_Title'));

        TranslationValue::where('key', 'dashboard.Settings_Footer_Title')->where('locale', 'ar')->update(['value' => 'تذييل الموقع']);
        $this->seed(AdminSettingsTranslationSeeder::class);
        $this->assertSame('تذييل الموقع', $this->value('dashboard.Settings_Footer_Title', 'ar'), 'An edited heading is kept.');
    }

    public function test_validation_messages_name_fields_by_their_label_in_each_language(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);
        $rows = TranslationValue::count();
        $invalid = $this->payload(['site_name' => str_repeat('a', 256), 'footer' => ['about_title' => str_repeat('ع', 101), 'x_url' => 'ftp://x']]);

        foreach (['ar' => ['اسم الموقع', 'عنوان قسم عن الموقع', 'رابط إكس (X)'], 'en' => ['Site Name', 'About section title', 'X link']] as $locale => $labels) {
            $this->actingAs($this->editor(), 'admin')->withSession(['locale' => $locale])->from(route('dashboard.setting.index'))
                ->put(route('dashboard.setting.update'), $invalid)->assertSessionHasErrors(['site_name', 'footer.about_title', 'footer.x_url']);

            $errors = session('errors')->getBag('default');
            foreach (['site_name', 'footer.about_title', 'footer.x_url'] as $i => $field) {
                $message = $errors->first($field);
                $this->assertStringContainsString($labels[$i], $message, "{$locale} {$field}");
                $this->assertDoesNotMatchRegularExpression('/footer|about[ _]title|x[ _]url|site[ _]name/', $message, "{$locale} {$field}: no technical key.");
            }
        }

        $this->assertSame($rows, TranslationValue::count(), 'A failed save creates no translation row.');
    }

    // ---------------------------------------------------------------- SET-05: inline errors

    public function test_invalid_footer_inputs_show_their_error_under_the_field_and_keep_the_entered_values(): void
    {
        $page = route('dashboard.setting.index');
        $html = $this->actingAs($this->editor(), 'admin')->from($page)->followingRedirects()
            ->put(route('dashboard.setting.update'), $this->payload(['footer' => [
                'instagram_url' => 'ftp://example.com/a',
                'about_title' => str_repeat('ع', 101),
                'tagline' => 'شعار جديد',
            ]]))
            ->assertOk()->getContent();

        foreach (['instagram_url' => 'ftp://example.com/a', 'about_title' => str_repeat('ع', 101)] as $key => $value) {
            $this->assertMatchesRegularExpression(
                '#name="footer\['.$key.'\]"\s+value="'.preg_quote(e($value), '#').'"\s+class="form-control is-invalid"[^>]*/>\s*<div class="invalid-feedback">\s*\S#u',
                $html,
                "{$key}: marked invalid, the entered value kept, the message right under it."
            );
        }
        // A valid field keeps its entered value and is not marked invalid.
        $this->assertMatchesRegularExpression('#name="footer\[tagline\]"\s+value="شعار جديد"\s+class="form-control"[^>]*/>#u', $html);
        $this->assertSame(2, substr_count($html, 'class="invalid-feedback"'));
        $this->assertNull(DB::table('settings')->value('footer_content'), 'Nothing is saved.');
    }

    public function test_an_invalid_footer_description_shows_its_error_under_the_textarea_and_keeps_the_entered_value(): void
    {
        $page = route('dashboard.setting.index');
        $long = str_repeat('ص', 2001);
        $html = $this->actingAs($this->editor(), 'admin')->from($page)->followingRedirects()
            ->put(route('dashboard.setting.update'), $this->payload(['footer' => ['description' => $long]]))
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<textarea\s+id="footer\[description\]"\s+name="footer\[description\]"\s+rows="4"\s+class="form-control is-invalid"[^>]*>'.$long.'</textarea>\s*<div class="invalid-feedback">\s*\S#u',
            $html
        );
        $this->assertSame(1, substr_count($html, 'class="invalid-feedback"'));
    }

    public function test_another_form_input_consumer_still_shows_its_errors_by_field_name(): void
    {
        $super = $this->createAdmin(superAdmin: true);

        $html = $this->actingAs($super, 'admin')->from(route('dashboard.admins.create'))->followingRedirects()
            ->post(route('dashboard.admins.store'), ['name' => '', 'email' => 'new-admin@example.com', 'phone' => '0599111222', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1'])
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#id="name"\s+name="name"\s+value=""\s+class="form-control is-invalid" required="required"\s*/>\s*<div class="invalid-feedback">#', $html);
        $this->assertMatchesRegularExpression('#id="phone"\s+name="phone"\s+value="0599111222"#', $html, 'The entered value is kept.');
        $this->assertDoesNotMatchRegularExpression('#name="phone"[^>]*is-invalid#', $html);
    }

    // ---------------------------------------------------------------- SET-06: textarea label

    public function test_every_settings_textarea_has_an_id_its_label_points_to_and_no_stray_semicolon(): void
    {
        $html = $this->actingAs($this->editor(), 'admin')->get(route('dashboard.setting.index'))->assertOk()->getContent();

        $this->assertSame(3, preg_match_all('#<textarea\b#', $html));
        foreach (['site_description', 'address', 'footer[description]'] as $name) {
            $quoted = preg_quote($name, '#');
            $this->assertMatchesRegularExpression('#<label for="'.$quoted.'">#', $html, $name);
            $this->assertMatchesRegularExpression('#<textarea\s+id="'.$quoted.'"\s+name="'.$quoted.'"#', $html, $name);
        }
        $this->assertDoesNotMatchRegularExpression('#</textarea>\s*;#', $html);
        $this->assertDoesNotMatchRegularExpression('#rows="4"\s*;#', $html);
    }

    public function test_the_textarea_component_keeps_a_given_id(): void
    {
        $html = (string) $this->withViewErrors([])->blade('<x-form.textarea name="notes" id="custom-notes" label="Notes" />');

        $this->assertMatchesRegularExpression('#<label for="custom-notes">#', $html);
        $this->assertMatchesRegularExpression('#<textarea\s+id="custom-notes"\s+name="notes"#', $html);
        $this->assertSame(1, substr_count($html, 'id="'));
    }

    // ---------------------------------------------------------------- SET-08: social links

    public function test_social_links_have_translated_accessible_names_and_inactive_icons_stay_hidden(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);
        Setting::singleton()->update(['footer_content' => array_replace(Setting::footerDefaults(), [
            'instagram_url' => 'https://instagram.com/hirfah', 'facebook_url' => 'https://facebook.com/hirfah', 'x_url' => '',
        ])]);

        $ar = $this->footerHtml($this->withSession(['locale' => 'ar'])->get(route('home'))->assertOk()->getContent());
        $this->assertStringContainsString('href="https://instagram.com/hirfah" class="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/10" aria-label="إنستغرام"', $ar);
        $this->assertStringContainsString('href="https://facebook.com/hirfah" class="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/10" aria-label="فيسبوك"', $ar);
        $this->assertStringNotContainsString('aria-label="instagram"', $ar);

        $en = $this->footerHtml($this->withSession(['locale' => 'en'])->get(route('home'))->assertOk()->getContent());
        $this->assertStringContainsString('aria-label="Instagram"', $en);
        $this->assertStringContainsString('aria-label="Facebook"', $en);

        foreach ([$ar, $en] as $footer) {
            // X has no address: the same icon, hidden, not a link, without a name of its own.
            $this->assertSame(1, preg_match_all('#<span class="flex h-8 w-8[^"]*" aria-hidden="true"><img src="[^"]*footer-x\.svg" alt="" class="h-4 w-4"></span>#', $footer));
            $this->assertStringNotContainsString('footer-x.svg" alt="" class="h-4 w-4"></a>', $footer);
            $this->assertStringNotContainsString('aria-label="X"', $footer);
            $this->assertStringNotContainsString('aria-label="إكس (X)"', $footer);
        }
    }

    public function test_the_x_link_is_named_in_both_languages_when_it_has_an_address(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);
        Setting::singleton()->update(['footer_content' => array_replace(Setting::footerDefaults(), ['x_url' => 'https://x.com/hirfah'])]);

        $this->assertStringContainsString('href="https://x.com/hirfah" class="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/10" aria-label="إكس (X)"',
            $this->footerHtml($this->withSession(['locale' => 'ar'])->get(route('home'))->getContent()));
        $this->assertStringContainsString('aria-label="X"', $this->footerHtml($this->withSession(['locale' => 'en'])->get(route('home'))->getContent()));
    }

    private function value(string $key, string $locale): ?string
    {
        return TranslationValue::where('key', $key)->where('locale', $locale)->value('value');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['site_name' => 'Site', 'timezone' => 'UTC', 'default_locale' => 'ar', 'default_currency' => 'ILS'], $overrides);
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
