<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\Language;
use App\Models\RoleUser;
use App\Models\Setting;
use Database\Seeders\AdminSettingsTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FOOTER-I18N-01C: the 20 footer texts edited in Arabic and English. Saved under footer_content.texts.<locale> (only
 * what differs from the language's default), the flat Arabic fields kept as the resolved copy; a language or field
 * not sent keeps its value; an empty one goes back to its language's default; links and other keys untouched.
 */
class SettingFooterLanguagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->withoutVite();
    }

    public function test_the_form_pairs_each_text_and_never_gives_english_the_arabic_text(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);
        $this->store(['tagline' => 'شعار مخصص', 'texts' => ['en' => ['copyright' => 'Copyright Hirfah.']]]);

        $html = $this->form('ar');

        $this->assertMatchesRegularExpression('#name="footer\[ar\]\[tagline\]"\s+value="شعار مخصص"\s+class="form-control"\s+lang="ar" dir="rtl" placeholder="'.preg_quote(Setting::footerDefaults('ar')['tagline'], '#').'"#u', $html);
        $this->assertMatchesRegularExpression('#name="footer\[en\]\[tagline\]"\s+value=""\s+class="form-control"\s+lang="en" dir="ltr" placeholder="Authentic Palestinian crafts"#', $html, 'English: empty, its default shown as the placeholder.');
        $this->assertMatchesRegularExpression('#name="footer\[en\]\[copyright\]"\s+value="Copyright Hirfah."#', $html);
        $this->assertMatchesRegularExpression('#<textarea\s+id="footer\[en\]\[description\]"\s+name="footer\[en\]\[description\]"\s+rows="4"\s+class="form-control" lang="en" dir="ltr" placeholder="A digital marketplace#', $html);
        $this->assertSame(1, substr_count($html, 'شعار مخصص'), 'The custom Arabic text is in the Arabic field only.');
        $this->assertMatchesRegularExpression('#for="footer\[en\]\[tagline\]">\s*الشعار النصي \(الإنجليزية\)\s*</label>#u', $html);
        $this->assertMatchesRegularExpression('#for="footer\[ar\]\[tagline\]">\s*الشعار النصي \(العربية\)\s*</label>#u', $html);
        $this->assertSame(20, substr_count($html, 'class="admin-cat-form-field w-100 admin-settings-text-pair" role="group"'));
        $this->assertStringContainsString('لكل نص حقل بالعربية وآخر بالإنجليزية.', $html);
    }

    public function test_both_languages_are_saved_with_the_flat_arabic_copy_and_everything_else_kept(): void
    {
        $this->store(['copyright' => 'حقوق قديمة', 'instagram_url' => 'https://instagram.com/keep', 'custom_key' => 'kept',
            'social_links' => [['platform' => 'telegram', 'url' => 'https://t.me/keep', 'enabled' => true]]]);
        Setting::query()->update(['site_name' => 'Name', 'default_currency' => 'ILS']);

        $this->save(['footer' => [
            'ar' => ['tagline' => 'شعار جديد', 'about_title' => Setting::footerDefaults('ar')['about_title']],
            'en' => ['tagline' => ' Handmade in Palestine ', 'faq_label' => 'FAQ', 'copyright' => 'All rights reserved.'],
        ]])->assertSessionHasNoErrors();

        $content = Setting::query()->first()->footer_content;
        $this->assertSame(['en' => ['tagline' => 'Handmade in Palestine', 'faq_label' => 'FAQ']], array_intersect_key($content['texts'], ['en' => 1]), 'English: only what differs from its defaults, trimmed.');
        $arabic = $content['texts']['ar'];
        ksort($arabic);
        $this->assertSame(['copyright' => 'حقوق قديمة', 'tagline' => 'شعار جديد'], $arabic, 'Arabic: the flat custom texts carried over, defaults not stored.');
        $this->assertSame(['شعار جديد', 'حقوق قديمة', Setting::footerDefaults('ar')['about_title']], [$content['tagline'], $content['copyright'], $content['about_title']], 'The flat copy.');
        $this->assertSame('https://instagram.com/keep', $content['instagram_url']);
        $this->assertSame('kept', $content['custom_key']);
        $this->assertSame([['platform' => 'telegram', 'url' => 'https://t.me/keep', 'enabled' => true]], $content['social_links']);
        $this->assertSame(['Site', 'ILS'], [Setting::query()->value('site_name'), Setting::query()->value('default_currency')], 'Only what the form sent.');

        // The storefront in each language.
        $this->assertSame('Handmade in Palestine', Setting::footerFor(Setting::query()->first(), 'en')['tagline']);
        $english = Setting::footerFor(Setting::query()->first(), 'en');
        $this->assertSame(['About Hirfah', 'FAQ'], [$english['about_title'], $english['faq_label']], 'Unsaved English: its default; saved: its text.');
        $this->assertSame('شعار جديد', Setting::footerFor(Setting::query()->first(), 'ar')['tagline']);
    }

    public function test_an_omitted_language_or_field_is_kept_and_an_empty_one_goes_back_to_its_default(): void
    {
        $this->store(['tagline' => 'شعار عربي', 'texts' => ['en' => ['tagline' => 'English tagline', 'copyright' => 'English copyright']]]);
        $flat = Setting::query()->first()->footer_content;

        // English only: Arabic untouched (no texts.ar created, the flat fields as they were).
        $this->save(['footer' => ['en' => ['copyright' => '']]])->assertSessionHasNoErrors();
        $content = Setting::query()->first()->footer_content;
        $this->assertSame(['tagline' => 'English tagline'], $content['texts']['en'], 'Empty: back to the default; the omitted field kept.');
        $this->assertArrayNotHasKey('ar', $content['texts']);
        $this->assertSame($flat['tagline'], $content['tagline']);
        $this->assertSame('All rights reserved.', Setting::footerFor(Setting::query()->first(), 'en')['copyright']);

        // Arabic only, one field emptied: English untouched; the flat copy follows.
        $this->save(['footer' => ['ar' => ['tagline' => '   ']]])->assertSessionHasNoErrors();
        $content = Setting::query()->first()->footer_content;
        $this->assertSame([], $content['texts']['ar']);
        $this->assertSame(Setting::footerDefaults('ar')['tagline'], $content['tagline']);
        $this->assertSame(['tagline' => 'English tagline'], $content['texts']['en']);

        // Nothing sent for the footer: nothing changes.
        $before = DB::table('settings')->value('footer_content');
        $this->save([])->assertSessionHasNoErrors();
        $this->assertSame($before, DB::table('settings')->value('footer_content'));
    }

    public function test_saving_the_same_form_twice_gives_the_same_content(): void
    {
        $this->store(['copyright' => 'حقوق']);
        $payload = ['footer' => [
            'ar' => array_replace(array_intersect_key(Setting::footerDefaults('ar'), array_flip(Setting::footerTextFields())), ['tagline' => 'شعار']),
            'en' => array_fill_keys(Setting::footerTextFields(), '') + ['faq_label' => 'FAQ'],
        ]];

        $this->save($payload)->assertSessionHasNoErrors();
        $first = DB::table('settings')->value('footer_content');
        $this->save($payload)->assertSessionHasNoErrors();

        $this->assertSame($first, DB::table('settings')->value('footer_content'));
        $this->assertSame(['tagline' => 'شعار'], Setting::query()->first()->footer_content['texts']['ar'], 'The form shows the defaults; saving them stores nothing extra.');
    }

    public function test_unsupported_languages_unknown_fields_and_malformed_input_are_rejected_and_nothing_is_saved(): void
    {
        $this->store(['tagline' => 'محفوظ']);
        $before = DB::table('settings')->value('footer_content');

        $cases = [
            'footer' => ['fr' => ['tagline' => 'Artisanat']],
            'footer.ar' => ['ar' => ['instagram_url' => 'https://instagram.com/a']],
            'footer.en' => ['en' => 'text'],
            'footer.en.tagline' => ['en' => ['tagline' => ['nested']]],
            'footer.en.about_title' => ['en' => ['about_title' => str_repeat('a', 101)]],
            'footer.ar.description' => ['ar' => ['description' => str_repeat('ع', 2001)]],
        ];
        foreach ($cases as $field => $footer) {
            $this->save(['footer' => $footer])->assertSessionHasErrors($field);
            $this->assertSame($before, DB::table('settings')->value('footer_content'), $field);
        }
        $this->save(['footer' => 'text'])->assertSessionHasErrors('footer');
        $this->assertSame($before, DB::table('settings')->value('footer_content'));
    }

    public function test_after_a_failed_save_both_languages_keep_their_values_and_the_error_is_under_its_field(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);
        $html = $this->actingAs($this->editor(), 'admin')->withSession(['locale' => 'en'])->from(route('dashboard.setting.index'))->followingRedirects()
            ->put(route('dashboard.setting.update'), $this->payload(['footer' => [
                'ar' => ['tagline' => 'شعار مرسل'],
                'en' => ['tagline' => 'Sent tagline', 'faq_label' => str_repeat('q', 256)],
            ]]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#name="footer\[ar\]\[tagline\]"\s+value="شعار مرسل"#u', $html);
        $this->assertMatchesRegularExpression('#name="footer\[en\]\[tagline\]"\s+value="Sent tagline"#', $html);
        $this->assertMatchesRegularExpression('#name="footer\[en\]\[faq_label\]"\s+value="q{256}"\s+class="form-control is-invalid"[^>]*/>\s*<div class="invalid-feedback">\s*\S#', $html);
        $this->assertDoesNotMatchRegularExpression('#name="footer\[ar\]\[faq_label\]"[^>]*is-invalid#', $html, 'The Arabic field is not marked.');
        $this->assertStringContainsString('<li><a href="#footer[en][faq_label]">FAQ link text (English)</a>: ', $html);
        $this->assertMatchesRegularExpression('#id="settings-group-service" open data-settings-group data-settings-group-open="false" data-settings-group-errors="1">#', $html);
        $this->assertStringContainsString('data-initial-tab="footer"', $html);
        $this->assertStringContainsString('id="footer[en][faq_label]"', $html, 'The summary link has its target.');
    }

    public function test_only_an_admin_who_may_edit_settings_can_save_the_texts(): void
    {
        $viewer = $this->createAdmin();
        RoleUser::query()->create(['role_name' => 'settings.view', 'user_id' => $viewer->id, 'ability' => 'allow']);

        $this->actingAs($viewer, 'admin')->put(route('dashboard.setting.update'), $this->payload(['footer' => ['en' => ['tagline' => 'X']]]))->assertForbidden();
        $this->assertSame(0, Setting::query()->count());
    }

    public function test_malformed_stored_content_still_shows_the_form_and_a_save_repairs_it(): void
    {
        Setting::singleton();
        foreach (['"a string"', '{"texts": "x"}', '{"texts": {"en": "x", "ar": 5}, "tagline": "شعار"}'] as $raw) {
            DB::table('settings')->update(['footer_content' => $raw]);
            $this->form('ar');
            $this->assertSame($raw, DB::table('settings')->value('footer_content'), 'Viewing never rewrites it.');

            $this->save(['footer' => ['en' => ['tagline' => 'Repaired']]])->assertSessionHasNoErrors();
            $content = Setting::query()->first()->footer_content;
            $this->assertSame(['tagline' => 'Repaired'], $content['texts']['en'], $raw);
            $this->assertSame('Repaired', Setting::footerFor(Setting::query()->first(), 'en')['tagline']);
        }
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

    private function form(string $locale): string
    {
        return $this->actingAs($this->editor(), 'admin')->withSession(['locale' => $locale])->get(route('dashboard.setting.index'))->assertOk()->getContent();
    }

    private function editor(): Admin
    {
        $admin = Admin::query()->where('email', 'footer-editor@example.com')->first() ?? $this->createAdmin('footer-editor@example.com');
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
