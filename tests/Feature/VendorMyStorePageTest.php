<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Governorate;
use App\Models\Language;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * My Store (VUI-03B). The page is server-rendered with labels and empty disabled
 * fields; resources/js/vendor-my-store.js reads and saves through the existing
 * Palgoals profile endpoints (unchanged). These tests cover the page markup, the
 * consumed contract, and that the script sends exactly the approved payload.
 */
class VendorMyStorePageTest extends TestCase
{
    use RefreshDatabase;

    private const EDITABLE = ['address_line', 'city_id', 'description', 'governorate_id', 'name', 'preparation_days', 'short_description', 'store_name'];

    private const PRESERVED = ['email', 'phone', 'slug'];

    private string $hotFile;

    protected function setUp(): void
    {
        parent::setUp();

        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $this->seed(AdminAuthTranslationSeeder::class);
        $this->seed(VendorAuthTranslationSeeder::class);
        $this->seed(VendorDashboardTranslationSeeder::class);

        $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot');
        file_put_contents($this->hotFile, 'http://localhost:5173');
        Vite::useHotFile($this->hotFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    // ---- Route and navigation ----

    public function test_route_is_a_gated_browser_page(): void
    {
        $route = Route::getRoutes()->getByName('vendor.dashboard.my-store');

        $this->assertSame('vendor/dashboard/my-store', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        foreach (['auth:vendor', 'vendor.approved', 'setLocale', 'disableTranslationAutoCreate'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
    }

    public function test_pending_and_rejected_vendors_are_gated(): void
    {
        foreach (['pending' => null, 'rejected' => 'Reason.'] as $state => $reason) {
            $vendor = Vendor::factory()->withProfile($state, $reason)->create();

            $this->actingAs($vendor, 'vendor')->get(route('vendor.dashboard.my-store'))->assertRedirect(route('vendor.approval-status'));
            $this->getJson(route('vendor.dashboard.my-store'))->assertForbidden();
        }
    }

    public function test_my_store_is_the_current_nav_item_only_on_its_page(): void
    {
        $this->actingAs($this->vendor(), 'vendor');

        $store = $this->page()->getContent();
        $this->assertMatchesRegularExpression('/<li class="pc-item active" data-nav-state="link">\s*<a href="'.preg_quote(route('vendor.dashboard.my-store'), '/').'" class="pc-link"\s+aria-current="page"/', $store);
        $this->assertSame(1, preg_match_all('/<li class="pc-item active"/', $store));
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('vendor.dashboard'), '/').'" class="pc-link"\s*>/', $store);

        $dashboard = $this->get(route('vendor.dashboard'))->getContent();
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('vendor.dashboard.my-store'), '/').'" class="pc-link"\s*>/', $dashboard);
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('vendor.dashboard'), '/').'" class="pc-link"\s+aria-current="page"/', $dashboard);
    }

    // ---- Page markup ----

    public function test_page_renders_empty_disabled_fields_until_the_profile_is_loaded(): void
    {
        $html = $this->actingAs($this->vendor(), 'vendor')
            ->page()
            ->assertOk()
            ->assertViewIs('vendor-dashboard.my-store')
            ->assertSee('data-profile-url="'.route('vendor.dashboard.profile.show').'"', false)
            ->assertSee('data-update-url="'.route('vendor.dashboard.profile.update').'"', false)
            ->assertSee('data-state="loading"', false)
            ->assertSee('resources/js/vendor-my-store.js', false)
            ->assertSee('name="_token"', false)
            ->getContent();

        // Save is unavailable before the read succeeds.
        $this->assertMatchesRegularExpression('/<button type="submit" class="vendor-save-button" data-store-save disabled>/', $html);

        // Every field is disabled and carries no value from the server.
        preg_match_all('/<(input|textarea)\b[^>]*\bname="(?!_token)([a-z_]+)"[^>]*>/', $html, $controls, PREG_SET_ORDER);
        foreach ($controls as [$tag]) {
            $this->assertStringContainsString(' disabled', $tag);
            $this->assertStringNotContainsString('value=', $tag);
        }
    }

    public function test_only_the_approved_fields_are_editable(): void
    {
        $html = $this->actingAs($this->vendor(), 'vendor')->page()->getContent();

        preg_match_all('/<(?:input|textarea|select)\b[^>]*\bname="([a-z_]+)"/', $html, $matches);
        $names = array_values(array_diff($matches[1], ['_token']));
        sort($names);

        $this->assertSame(self::EDITABLE, $names);
        $this->assertSame(2, substr_count($html, '<select'));
        $this->assertStringContainsString('data-store-logo-pick', $html);
        $this->assertStringNotContainsString('type="password"', $html);
    }

    public function test_field_length_guards(): void
    {
        $html = $this->actingAs($this->vendor(), 'vendor')->page()->getContent();

        foreach (['name', 'store_name', 'short_description', 'address_line'] as $name) {
            $this->assertMatchesRegularExpression('/<input\b[^>]*name="'.$name.'"[^>]*maxlength="255"|<input\b[^>]*maxlength="255"[^>]*name="'.$name.'"/', $html, $name);
        }
        $this->assertMatchesRegularExpression('/<textarea\b(?![^>]*maxlength)[^>]*name="description"/', $html);
        $this->assertMatchesRegularExpression('/<input\b[^>]*name="name"[^>]*required/', $html);
        $this->assertMatchesRegularExpression('/<input\b[^>]*name="store_name"[^>]*required/', $html);
        $this->assertStringContainsString('data-counter-for="short_description"', $html);
    }

    public function test_email_and_phone_are_display_only_while_location_is_editable(): void
    {
        $html = $this->actingAs($this->vendor(), 'vendor')->page()->getContent();

        foreach (['email', 'phone'] as $key) {
            $this->assertStringContainsString('<dd data-readonly="'.$key.'">', $html);
        }
        foreach (['city_id', 'governorate_id'] as $name) {
            $this->assertStringContainsString('name="'.$name.'"', $html);
        }
        foreach (self::PRESERVED as $name) {
            $this->assertStringNotContainsString('name="'.$name.'"', $html);
        }
    }

    public function test_no_media_slug_or_system_metadata_ui(): void
    {
        $this->actingAs($this->vendor(), 'vendor');

        foreach (['ar', 'en'] as $locale) {
            $this->get(route('vendor.dashboard.my-store').'?change-locale='.$locale);
            $this->page()
                ->assertDontSee('name="logo"', false)
                ->assertDontSee('name="cover_image"', false)
                ->assertDontSee('name="avatar"', false)
                ->assertDontSee('name="slug"', false)
                ->assertDontSee('commission_rate')
                ->assertDontSee('approval_status')
                ->assertDontSee('rejection_reason')
                ->assertDontSee('approved_by')
                ->assertDontSee('last_login_at')
                // The store logo has its own section (upload endpoint, not a form field); the cover has none.
                ->assertDontSee('Cover')
                ->assertDontSee('الغلاف')
                ->assertDontSee('Password')
                ->assertDontSee('كلمة المرور');
        }
    }

    public function test_arabic_labels(): void
    {
        $this->actingAs($this->vendor(), 'vendor')
            ->page()
            ->assertSee('<html lang="ar" dir="rtl"', false)
            ->assertSee('متجري')
            ->assertSee('هوية المتجر')
            ->assertSeeInOrder(['معلومات المتجر', 'اسم المتجر', 'وصف مختصر', 'الوصف'])
            ->assertSeeInOrder(['الموقع', 'المحافظة', 'المدينة', 'العنوان'])
            ->assertSeeInOrder(['بيانات الحساب', 'الاسم الكامل', 'البريد الإلكتروني', 'رقم الهاتف'])
            ->assertSee('حفظ التغييرات')
            ->assertSee('تعذّر تحميل بيانات المتجر.')
            ->assertSee('إعادة المحاولة');
    }

    public function test_english_labels(): void
    {
        $this->actingAs($this->vendor(), 'vendor');
        $this->get(route('vendor.dashboard.my-store').'?change-locale=en')->assertRedirect(route('vendor.dashboard.my-store'));

        $this->page()
            ->assertSee('<html lang="en" dir="ltr"', false)
            ->assertSee('My Store')
            ->assertSee('Store identity')
            ->assertSeeInOrder(['Store information', 'Store name', 'Short description', 'Description'])
            ->assertSeeInOrder(['Location', 'Governorate', 'City', 'Address'])
            ->assertSeeInOrder(['Account information', 'Full name', 'Email', 'Phone'])
            ->assertSee('Save changes')
            ->assertSee('Store details could not be loaded.')
            ->assertSee('Retry');
    }

    public function test_interface_strings_for_the_script(): void
    {
        $i18n = $this->i18n($this->actingAs($this->vendor(), 'vendor')->page());

        $this->assertSame([
            'saving', 'saved', 'notSpecified', 'fixErrors', 'saveError', 'sessionError', 'accessError', 'required', 'tooLong', 'invalid',
            'selectCity', 'selectGovernorate', 'cityMismatch', 'locationUnavailable', 'unsaved', 'logoUploading', 'logoSaving', 'logoUploaded', 'logoUploadFailed', 'logoConfirmDelete', 'logoDeleted', 'logoDeleteFailed',
        ], array_keys($i18n));
        $this->assertSame('غير محدد', $i18n['notSpecified']);
        $this->assertSame('تم حفظ التغييرات بنجاح.', $i18n['saved']);
        $this->assertSame('هذا الحقل مطلوب.', $i18n['required']);
        // MYSTORE-UI-01C: the media library wording; removing the logo keeps the image.
        $this->assertSame('جارٍ الرفع…', $i18n['logoUploading']);
        $this->assertSame('جارٍ الحفظ…', $i18n['logoSaving']);
        $this->assertSame('تم تعيين الشعار.', $i18n['logoUploaded']);
        $this->assertSame('تعذّر تعيين هذه الصورة شعارًا. اختر صورة أخرى من مكتبة الوسائط.', $i18n['logoUploadFailed']);
        $this->assertSame('هل تريد إزالة الشعار من متجرك؟ تبقى الصورة في مكتبة الوسائط.', $i18n['logoConfirmDelete']);
        $this->assertSame('تمت إزالة الشعار. تبقى الصورة في مكتبة الوسائط.', $i18n['logoDeleted']);
        $this->assertSame('تعذّرت إزالة الشعار. حاول مرة أخرى.', $i18n['logoDeleteFailed']);
    }

    public function test_picker_strings_for_the_script(): void
    {
        $response = $this->actingAs($this->vendor(), 'vendor')->page();
        preg_match('/<script type="application\/json" data-store-picker-i18n>(.*?)<\/script>/s', $response->getContent(), $match);
        $picker = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame([
            'title', 'libraryCount', 'pageStatus', 'choose', 'mediaAlt', 'attached', 'current', 'libraryError', 'uploadInvalid',
            'uploadMax', 'session', 'forbidden', 'invalid', 'network', 'failed',
        ], array_keys($picker));
        $this->assertSame('الشعار الحالي', $picker['current']);
        $this->assertSame('اختر صورة من الوسائط', $picker['title']);
        $this->assertSame('ليست لديك صلاحية للقيام بذلك.', $picker['forbidden']);
    }

    // ---- Logo section (MYSTORE-UI-01, chosen from the media library since MYSTORE-UI-01C) and preparation days ----

    public function test_logo_section_and_preparation_days_in_arabic(): void
    {
        $this->actingAs($this->vendor(), 'vendor')
            ->page()
            ->assertSeeInOrder(['شعار المتجر', 'اختر صورة من مكتبة الوسائط لتظهر بجانب اسم متجرك في لوحة البائع. إزالة الشعار لا تحذف الصورة من المكتبة.', 'اختيار من مكتبة الوسائط', 'إزالة الشعار'])
            ->assertSeeInOrder(['مدة التجهيز (بالأيام)', 'المدة المعتادة لتجهيز الطلب قبل شحنه، بالأيام (من 0 إلى 365).'])
            ->assertDontSee('Store logo')
            ->assertDontSee('Preparation days')
            ->assertDontSee('حذف الشعار')
            ->assertDontSee('2 ميغابايت');
    }

    public function test_logo_section_and_preparation_days_in_english(): void
    {
        $this->actingAs($this->vendor(), 'vendor');
        $this->get(route('vendor.dashboard.my-store').'?change-locale=en');

        $this->page()
            ->assertSeeInOrder(['Store logo', 'Removing the logo does not delete the image from the library.', 'Choose from media library', 'Remove logo'])
            ->assertSeeInOrder(['Preparation days', 'Your usual time to prepare an order before shipping, in days (0 to 365).']);
    }

    public function test_the_logo_is_chosen_in_the_shared_media_picker_and_remove_starts_hidden(): void
    {
        $html = $this->actingAs($this->vendor(), 'vendor')->page()->getContent();

        // Choose: opens the picker dialog, described by the hint, disabled until the profile is loaded.
        $this->assertMatchesRegularExpression('/<button\b[^>]*data-store-logo-pick[^>]*aria-describedby="vendor-store-logo-hint"[^>]*aria-haspopup="dialog"[^>]*disabled>/', $html);
        $this->assertStringContainsString('id="vendor-store-logo-hint"', $html);
        // Remove is hidden until a logo is shown; without a logo the preview is hidden too (the letter shows).
        $this->assertMatchesRegularExpression('/<button\b[^>]*data-store-logo-delete hidden>/', $html);
        $this->assertMatchesRegularExpression('/<img\b[^>]*data-store-logo\s+hidden\b/', $html);
        // The shared picker (one dialog, outside the profile form) with the library's endpoints; one file per upload.
        $this->assertSame(1, substr_count($html, '<dialog class="vendor-media-dialog vendor-product-picker"'));
        $this->assertMatchesRegularExpression('/<\/form>\s*<dialog class="vendor-media-dialog vendor-product-picker"/', $html);
        $this->assertMatchesRegularExpression('/<input class="vendor-product-media-file" type="file"[^>]*data-product-media-file tabindex="-1" aria-hidden="true">/', $html);
        $this->assertStringContainsString('data-logo-url="'.route('vendor.dashboard.profile.logo.update').'"', $html);
        $this->assertStringContainsString('data-media-library-url="'.route('vendor.dashboard.products.media.library').'"', $html);
        $this->assertStringContainsString('data-media-store-url="'.route('vendor.dashboard.products.media.store').'"', $html);
        // The retired direct upload (MYSTORE-SEC-02B) is not referenced by the page.
        $this->assertStringNotContainsString('/vendor/dashboard/profile/media/', $html);
        $this->assertStringNotContainsString('data-store-logo-input', $html);
        // The hint is described by the preparation days field, before its error.
        $this->assertStringContainsString('aria-describedby="vendor-hint-preparation_days vendor-error-preparation_days"', $html);

        // No Bootstrap utilities (the vendor shell does not load them) and no inline styles in the identity/logo card.
        preg_match('/<section[^>]*aria-labelledby="vendor-store-identity-title".*?<\/section>/s', $html, $section);
        foreach (['d-flex', 'align-items-center', 'rounded-circle', 'object-fit-cover', 'form-control', 'btn-light-danger', 'style=', 'type="file"'] as $absent) {
            $this->assertStringNotContainsString($absent, $section[0], $absent);
        }
    }

    public function test_logo_styles_keep_hidden_controls_hidden_and_logos_cropped(): void
    {
        $css = file_get_contents(resource_path('css/vendor-dashboard.css'));

        $this->assertMatchesRegularExpression('/\.vendor-store-logo-row \[hidden\] \{\s*display: none;/', $css);
        $this->assertMatchesRegularExpression('/\.vendor-store-logo-preview \{[^}]*object-fit: cover;/', $css);
        // The header and sidebar logos (img.vendor-monogram) are cropped too.
        $this->assertMatchesRegularExpression('/\.vendor-shell img\.vendor-monogram \{\s*object-fit: cover;/', $css);
        // The picker's own texts keep their look outside the product editor.
        $this->assertMatchesRegularExpression('/\.vendor-product-picker \.vendor-product-error \{\s*color: #a13d2b;/', $css);
        $this->assertStringNotContainsString('.vendor-store-logo-file', $css);
    }

    public function test_the_logo_script_reuses_the_picker_and_sends_only_the_media_id(): void
    {
        $script = file_get_contents(resource_path('js/vendor-my-store.js'));

        $this->assertStringContainsString("import { initPicker, uploadError, uploadPlan } from './vendor-product-editor.js';", $script);
        $this->assertStringNotContainsString('function initPicker', $script);
        $this->assertStringContainsString('body: JSON.stringify({ media_id: medium.id })', $script);
        $this->assertStringContainsString("request(root.dataset.logoUrl, { method: 'PUT'", $script);
        $this->assertStringContainsString("request(root.dataset.logoUrl, { method: 'DELETE'", $script);
        $this->assertStringContainsString('request(root.dataset.mediaStoreUrl', $script);
        $this->assertStringContainsString("medium?.path === logoPath ? 'current' : null", $script);
        $this->assertStringNotContainsString('logoUploadUrl', $script);
        $this->assertStringNotContainsString('logoDeleteUrl', $script);
        // Locked until the profile is loaded; texts from the page only.
        $this->assertStringContainsString('logoPick.disabled = !enabled || preserved === null || logoBusy;', $script);
        $this->assertStringContainsString('window.confirm(i18n.logoConfirmDelete)', $script);
        foreach (["'Uploading…'", "'Logo saved.'", "'Logo deleted.'", 'Delete the store logo?', 'could not be'] as $english) {
            $this->assertStringNotContainsString($english, $script, $english);
        }
    }

    public function test_the_layout_groups_the_sections_into_a_main_and_a_supporting_column(): void
    {
        $html = $this->actingAs($this->vendor(), 'vendor')->page()->getContent();

        // MYSTORE-UX-01B: main column (store information with preparation days, then location), then the supporting
        // column (logo, then account), inside the form; the identity card above it and the save bar below it.
        preg_match('/<div class="vendor-store-main">(.*?)<div class="vendor-store-side">(.*?)<div class="vendor-form-actions">/s', $html, $columns);
        $this->assertCount(3, $columns);
        [, $main, $side] = $columns;
        $this->assertMatchesRegularExpression('/aria-labelledby="vendor-store-info-title".*aria-labelledby="vendor-store-location-title"/s', $main);
        $this->assertMatchesRegularExpression('/aria-labelledby="vendor-store-identity-title".*aria-labelledby="vendor-store-account-title"/s', $side);
        $this->assertStringNotContainsString('vendor-store-identity-title', $main);
        $this->assertStringNotContainsString('vendor-store-info-title', $side);
        // MYSTORE-UX-01C: the identity is one card in the supporting column (no full-width identity strip above).
        $this->assertMatchesRegularExpression('/<form class="vendor-store-form".*<div class="vendor-store-layout">/s', $html);
        $this->assertSame(1, substr_count($html, 'id="vendor-store-identity-title"'));
        $this->assertStringNotContainsString('vendor-welcome-card', $html);

        // Preparation days: inside Store information, after the description, as a narrow field; location keeps the
        // governorate first, then its city (Governorate → City), then the address.
        preg_match('/aria-labelledby="vendor-store-info-title".*?<\/section>/s', $main, $info);
        $this->assertMatchesRegularExpression('/name="description".*<div class="vendor-field vendor-field-narrow">.*name="preparation_days"/s', $info[0]);
        preg_match('/aria-labelledby="vendor-store-location-title".*?<\/section>/s', $main, $location);
        $this->assertMatchesRegularExpression('/name="governorate_id".*name="city_id".*name="address_line"/s', $location[0]);
        $this->assertStringNotContainsString('preparation_days', $location[0]);

        $css = file_get_contents(resource_path('css/vendor-dashboard.css'));
        $this->assertMatchesRegularExpression('/@media \(min-width: 1024px\) \{\s*\.vendor-shell \.vendor-store-layout \{\s*grid-template-columns: minmax\(0, 2fr\) minmax\(16rem, 1fr\);/', $css);
        $this->assertMatchesRegularExpression('/\.vendor-field-narrow \.vendor-input \{\s*max-width: 12rem;/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.vendor-(field|readonly|field-hint) \{[^}]*max-width: 800px/', $css);
    }

    public function test_the_identity_card_shows_the_stored_logo(): void
    {
        $vendor = $this->vendor();
        $vendor->profile()->update(['store_name' => 'Olive Workshop', 'logo' => 'vendors/media/'.$vendor->id.'/logo.png']);

        $html = $this->actingAs($vendor, 'vendor')->page()->getContent();
        preg_match('/<section[^>]*aria-labelledby="vendor-store-identity-title".*?<\/section>/s', $html, $card);

        // MYSTORE-UX-01C: the stored logo at once (96px, named «شعار المتجر»), the letter hidden, the store name shown.
        $url = Storage::disk('public')->url('vendors/media/'.$vendor->id.'/logo.png');
        $this->assertMatchesRegularExpression('/<img class="vendor-store-logo-preview" data-store-logo\s+src="'.preg_quote($url, '/').'"\s+alt="شعار المتجر" width="96" height="96">/u', $card[0]);
        $this->assertMatchesRegularExpression('/<span class="vendor-store-logo-fallback" data-store-logo-fallback aria-hidden="true"\s+hidden\s*>O<\/span>/', $card[0]);
        $this->assertStringContainsString('<p class="vendor-store-identity-name"><bdi>Olive Workshop</bdi></p>', $card[0]);
        // The heading, then the logo and name, then the hint and the two controls (Remove hidden until loaded).
        $this->assertMatchesRegularExpression('/هوية المتجر.*data-store-logo.*vendor-store-identity-name.*vendor-store-logo-hint.*data-store-logo-pick.*data-store-logo-delete hidden/su', $card[0]);
    }

    public function test_without_a_logo_the_identity_card_shows_the_store_names_letter(): void
    {
        $vendor = $this->vendor();
        $vendor->profile()->update(['store_name' => 'متجر الزيتون', 'logo' => null]);

        $html = $this->actingAs($vendor, 'vendor')->page()->getContent();
        preg_match('/<section[^>]*aria-labelledby="vendor-store-identity-title".*?<\/section>/s', $html, $card);

        $this->assertMatchesRegularExpression('/<img class="vendor-store-logo-preview" data-store-logo\s+hidden\s+alt="شعار المتجر"/u', $card[0]);
        $this->assertMatchesRegularExpression('/<span class="vendor-store-logo-fallback" data-store-logo-fallback aria-hidden="true"\s*>م<\/span>/u', $card[0]);
        $this->assertStringContainsString('<bdi>متجر الزيتون</bdi>', $card[0]);
    }

    public function test_the_identity_card_preview_follows_the_logo_requests(): void
    {
        $script = file_get_contents(resource_path('js/vendor-my-store.js'));
        $css = file_get_contents(resource_path('css/vendor-dashboard.css'));

        // The preview and the letter swap only through showLogo(), which runs after a confirmed answer only.
        $this->assertStringContainsString("const logoFallback = root.querySelector('[data-store-logo-fallback]');", $script);
        $this->assertStringContainsString('logoFallback.hidden = !logo.hidden;', $script);
        $this->assertMatchesRegularExpression('/if \(answer\.status !== 200 \|\| !isString\(answer\.body\?\.logo\?\.url\)\) \{.*?return false;\s*\}\s*showLogo\(answer\.body\.logo\.url\);/s', $script);
        $this->assertMatchesRegularExpression('/if \(answer\.status !== 200\) \{\s*logoFail\(logoErrorText\(answer\.status, i18n\.logoDeleteFailed\)\);\s*return;\s*\}\s*showLogo\(null\);/s', $script);

        // 96px circle, cropped; the copper start accent of the dashboard's welcome card.
        $this->assertMatchesRegularExpression('/\.vendor-store-logo-preview \{[^}]*width: 96px;[^}]*height: 96px;[^}]*border-radius: 50%;[^}]*object-fit: cover;/', $css);
        $this->assertMatchesRegularExpression('/\.vendor-form-card\.vendor-store-identity-card \{[^}]*border-inline-start: 4px solid var\(--color-hf-copper\);/', $css);
        $this->assertMatchesRegularExpression('/\.vendor-store-logo-frame \[hidden\] \{\s*display: none;/', $css);
        $this->assertStringNotContainsString('.vendor-monogram-lg', $css);
    }

    public function test_the_save_bar_shows_unsaved_changes_and_guards_leaving(): void
    {
        $response = $this->actingAs($this->vendor(), 'vendor')->page();
        $html = $response->getContent();

        // MYSTORE-UX-01D: the unsaved-changes line is a polite status in the save bar, empty until there are changes.
        $this->assertMatchesRegularExpression('/<div class="vendor-form-actions">\s*<p class="vendor-unsaved" data-store-unsaved role="status" aria-live="polite"><\/p>\s*<p class="vendor-form-status" data-store-status role="status" aria-live="polite"><\/p>\s*<button type="submit" class="vendor-save-button" data-store-save disabled>/', $html);
        $this->assertSame('لديك تغييرات غير محفوظة', $this->i18n($response)['unsaved']);
        $this->assertSame(['ar' => 'لديك تغييرات غير محفوظة', 'en' => 'You have unsaved changes'], VendorDashboardTranslationSeeder::TRANSLATIONS['vendor.My_Store_Unsaved']);

        $script = file_get_contents(resource_path('js/vendor-my-store.js'));
        // Dirty = an editable field differs from the values read (or last saved); only the form's input events count.
        $this->assertStringContainsString('const isDirty = () => loaded !== null && Object.keys(EDITABLE).some((name) => field(name).value !== loaded[name]);', $script);
        $this->assertMatchesRegularExpression('/if \(dirty !== shownDirty\) \{\s*shownDirty = dirty;\s*unsaved\.textContent = dirty \? i18n\.unsaved : \'\';/', $script);
        $this->assertMatchesRegularExpression("/form\.addEventListener\('input', \(event\) => \{.*?updateUnsaved\(\);\s*\}\);/s", $script);
        // The values read are the reference; a confirmed save makes the saved values the reference before the reload.
        $this->assertMatchesRegularExpression('/updateCounter\(\);\s*loaded = currentValues\(\);\s*updateUnsaved\(\);/', $script);
        $this->assertMatchesRegularExpression('/loaded = currentValues\(\);\s*updateUnsaved\(\);\s*setStatus\(i18n\.saved, \'success\'\);\s*window\.setTimeout\(\(\) => window\.location\.reload\(\), 900\);/', $script);
        // The browser's own warning, only while dirty (no custom text); Save stays enabled when nothing changed.
        $this->assertMatchesRegularExpression("/window\.addEventListener\('beforeunload', \(event\) => \{\s*if \(isDirty\(\)\) \{\s*event\.preventDefault\(\);\s*event\.returnValue = '';\s*\}\s*\}\);/", $script);
        $this->assertStringContainsString('saveButton.disabled = !enabled || preserved === null;', $script);
        $this->assertDoesNotMatchRegularExpression('/saveButton\.disabled = [^;]*isDirty/', $script);
        // The logo and the picker never touch the form's reference values.
        preg_match('/\/\/ ---- Logo \(MYSTORE-UI-01C\).*$/s', $script, $logoPart);
        $this->assertDoesNotMatchRegularExpression('/\bloaded\b/', $logoPart[0]);
        $this->assertDoesNotMatchRegularExpression('/\bupdateUnsaved\b/', $logoPart[0]);

        $css = file_get_contents(resource_path('css/vendor-dashboard.css'));
        $this->assertMatchesRegularExpression('/\.vendor-store-form > \.vendor-form-actions \{\s*position: sticky;\s*bottom: 0;\s*z-index: 2;\s*padding-bottom: calc\(0\.875rem \+ env\(safe-area-inset-bottom, 0px\)\);/', $css);
        $this->assertMatchesRegularExpression('/html\.vendor-shell:has\(\.vendor-store-form\) \{\s*scroll-padding-bottom: 7\.5rem;/', $css);
    }

    public function test_the_required_legend_and_read_only_hints(): void
    {
        $html = $this->actingAs($this->vendor(), 'vendor')->page()->getContent();

        // MYSTORE-UX-01E: the legend opens the form; an asterisk marks exactly the two required editable fields.
        $this->assertMatchesRegularExpression('/<form class="vendor-store-form"[^>]*>\s*<input type="hidden" name="_token"[^>]*>\s*<p class="vendor-required-legend">الحقول المعلَّمة بـ \* مطلوبة\.<\/p>/u', $html);
        preg_match_all('/<label class="vendor-label" for="([a-z-]+)">[^<]*<span class="vendor-required" aria-hidden="true">\*<\/span><\/label>/u', $html, $marked);
        $this->assertSame(['vendor-field-store-name', 'vendor-field-name'], $marked[1]);
        foreach (['store_name', 'name'] as $required) {
            $this->assertMatchesRegularExpression('/<input\b[^>]*name="'.$required.'"[^>]*\brequired\b/', $html);
        }
        $this->assertSame(2, preg_match_all('/<input\b[^>]*\brequired\b/', $html));

        // The account's read-only hint remains; location is editable on this page.
        $hint = '<p class="vendor-readonly-hint"><i class="ph-duotone ph-lock-simple" aria-hidden="true"></i><span>هذه البيانات غير قابلة للتعديل من هذه الصفحة.</span></p>';
        $this->assertSame(1, substr_count($html, $hint));
        $this->assertMatchesRegularExpression('/data-readonly="phone".*?<\/dl>\s*'.preg_quote($hint, '/').'/su', $html);
        $this->assertStringNotContainsString('الدعم', $html);

        $this->assertSame(['ar' => 'هذه البيانات غير قابلة للتعديل من هذه الصفحة.', 'en' => 'These details cannot be edited on this page.'], VendorDashboardTranslationSeeder::TRANSLATIONS['vendor.My_Store_Readonly_Hint']);
        $this->assertSame(['ar' => 'الحقول المعلَّمة بـ * مطلوبة.', 'en' => 'Fields marked * are required.'], VendorDashboardTranslationSeeder::TRANSLATIONS['vendor.My_Store_Required_Legend']);
    }

    public function test_the_required_legend_and_read_only_hints_in_english(): void
    {
        $this->actingAs($this->vendor(), 'vendor');
        $this->get(route('vendor.dashboard.my-store').'?change-locale=en');

        $this->page()
            ->assertSee('Fields marked * are required.')
            ->assertSeeInOrder(['Location', 'Governorate', 'City', 'Address'])
            ->assertSeeInOrder(['Account information', 'Email', 'Phone', 'These details cannot be edited on this page.']);
    }

    public function test_the_remove_button_has_its_own_key_and_delete_stays_generic(): void
    {
        $html = $this->actingAs($this->vendor(), 'vendor')->page()->getContent();

        $this->assertMatchesRegularExpression('/data-store-logo-delete hidden>\s*<i[^>]*><\/i>\s*<span>إزالة الشعار<\/span>/u', $html);
        $view = file_get_contents(resource_path('views/vendor-dashboard/my-store.blade.php'));
        $this->assertStringContainsString("t('vendor.My_Store_Logo_Remove', 'Remove logo')", $view);
        $this->assertStringNotContainsString("t('vendor.Delete'", $view);
        $this->assertSame(['ar' => 'إزالة الشعار', 'en' => 'Remove logo'], VendorDashboardTranslationSeeder::TRANSLATIONS['vendor.My_Store_Logo_Remove']);
        $this->assertSame(['ar' => 'حذف', 'en' => 'Delete'], VendorDashboardTranslationSeeder::TRANSLATIONS['vendor.Delete']);
    }

    public function test_the_picker_shows_no_english_on_the_arabic_page(): void
    {
        $html = $this->actingAs($this->vendor(), 'vendor')->page()->getContent();
        preg_match('/<dialog class="vendor-media-dialog vendor-product-picker".*?<\/dialog>/s', $html, $dialog);

        // Visible text, and the texts assistive technology reads (aria-label, placeholder), of every picker state:
        // header, intro, search, upload, loading, empty, no results, error and retry, pagination.
        $visible = trim(preg_replace('/\s+/u', ' ', strip_tags($dialog[0])));
        preg_match_all('/\b(?:aria-label|placeholder)="([^"]*)"/', $dialog[0], $attributes);
        foreach (array_merge([$visible], $attributes[1]) as $text) {
            $english = preg_replace('/\b(?:JPEG|PNG|GIF|WebP)\b/', '', $text);
            $this->assertDoesNotMatchRegularExpression('/[A-Za-z]{2,}/', $english, $text);
        }
        foreach (['اختر صورة من الوسائط', 'تظهر هنا صور مكتبة الوسائط الخاصة بك فقط.', 'ارفع صورة جديدة', 'جارٍ تحميل الصور…', 'إعادة المحاولة'] as $arabic) {
            $this->assertStringContainsString($arabic, $dialog[0]);
        }

        // The texts the script shows (counts, cards, errors, the current logo marker).
        preg_match('/<script type="application\/json" data-store-picker-i18n>(.*?)<\/script>/s', $html, $match);
        foreach (json_decode($match[1], true, flags: JSON_THROW_ON_ERROR) as $name => $text) {
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $text, $name);
            $this->assertDoesNotMatchRegularExpression('/[A-Za-z]{2,}/', preg_replace('/:[a-z]+|\b(?:JPEG|PNG|GIF|WebP)\b/', '', $text), $name);
        }
    }

    public function test_the_product_editor_still_includes_the_same_picker(): void
    {
        $html = $this->actingAs($this->vendor(), 'vendor')->get(route('vendor.dashboard.products.page'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-product-media-library'));
        $this->assertMatchesRegularExpression('/data-product-media-file multiple tabindex="-1"/', $html);
        $this->assertStringContainsString('تُضاف أول صورة مرفوعة إلى المنتج', $html);
        $this->assertStringNotContainsString('data-store-picker-i18n', $html);
    }

    public function test_the_page_never_embeds_profile_data(): void
    {
        [$vendor, $governorate, $city] = $this->vendorWithLocation();

        $this->actingAs($vendor, 'vendor')
            ->page()
            ->assertOk()
            ->assertSee('Olive Workshop') // the store name in the shell/identity card is expected
            ->assertDontSee('owner.private@example.com')
            ->assertDontSee('0599123123')
            ->assertDontSee('olive-workshop-slug')
            ->assertDontSee('Handmade olive wood')
            ->assertDontSee('Long store story')
            ->assertDontSee('Old City, Street 5')
            ->assertDontSee($governorate->name)
            ->assertDontSee($city->name)
            ->assertDontSee('12.50');
    }

    // ---- Consumed contract (existing endpoints, unchanged) ----

    public function test_profile_read_returns_the_values_the_script_needs(): void
    {
        [$vendor, $governorate, $city] = $this->vendorWithLocation();

        $response = $this->actingAs($vendor, 'vendor')->getJson(route('vendor.dashboard.profile.show'))->assertOk();

        $this->assertSame('Owner Name', $response->json('vendor.name'));
        $this->assertSame('owner.private@example.com', $response->json('vendor.email'));
        $this->assertSame('0599123123', $response->json('vendor.phone'));
        $this->assertSame('Olive Workshop', $response->json('vendor.profile.store_name'));
        $this->assertSame('olive-workshop-slug', $response->json('vendor.profile.slug'));
        $this->assertSame($governorate->id, $response->json('vendor.profile.governorate_id'));
        $this->assertSame($city->id, $response->json('vendor.profile.city_id'));
        $this->assertSame($governorate->name, $response->json('vendor.profile.governorate.name'));
        $this->assertSame($city->name, $response->json('vendor.profile.city.name'));
    }

    public function test_saving_the_script_payload_updates_editable_fields_and_preserves_the_rest(): void
    {
        [$vendor, $governorate, $city] = $this->vendorWithLocation();

        $this->actingAs($vendor, 'vendor')
            ->putJson(route('vendor.dashboard.profile.update'), [
                'name' => 'New Owner',
                'store_name' => 'Renamed Workshop',
                'short_description' => 'New short text',
                'description' => 'New long text',
                'address_line' => 'New address',
                'email' => 'owner.private@example.com',
                'phone' => '0599123123',
                'slug' => 'olive-workshop-slug',
                'governorate_id' => $governorate->id,
                'city_id' => $city->id,
            ])
            ->assertOk();

        $vendor->refresh()->load('profile');
        $this->assertSame('New Owner', $vendor->name);
        $this->assertSame('owner.private@example.com', $vendor->email);
        $this->assertSame('0599123123', $vendor->phone);
        $this->assertSame('Renamed Workshop', $vendor->profile->store_name);
        $this->assertSame('olive-workshop-slug', $vendor->profile->slug);
        $this->assertSame($governorate->id, $vendor->profile->governorate_id);
        $this->assertSame($city->id, $vendor->profile->city_id);
        $this->assertSame('New address', $vendor->profile->address_line);
        $this->assertSame('logos/olive.png', $vendor->profile->logo);
        $this->assertSame('covers/olive.png', $vendor->profile->cover_image);
        $this->assertSame('approved', $vendor->profile->approval_status);
        $this->assertSame('12.50', $vendor->profile->commission_rate);
    }

    public function test_omitting_preserved_values_keeps_backend_owned_data(): void
    {
        [$vendor, $governorate, $city] = $this->vendorWithLocation();

        $this->actingAs($vendor, 'vendor')
            ->putJson(route('vendor.dashboard.profile.update'), [
                'name' => 'Owner Name',
                'store_name' => 'Olive Workshop',
                'phone' => '0599123123',
            ])
            ->assertOk();

        $vendor->refresh()->load('profile');
        $this->assertSame('owner.private@example.com', $vendor->email);
        $this->assertSame($governorate->id, $vendor->profile->governorate_id);
        $this->assertSame($city->id, $vendor->profile->city_id);
        $this->assertSame('olive-workshop-slug', $vendor->profile->slug);
    }

    public function test_validation_errors_are_keyed_by_field(): void
    {
        [$vendor] = $this->vendorWithLocation();

        $this->actingAs($vendor, 'vendor')
            ->putJson(route('vendor.dashboard.profile.update'), [
                'name' => '',
                'store_name' => '',
                'phone' => '0599123123',
                'city_id' => 999999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'store_name', 'city_id']);
    }

    public function test_profile_update_rejects_a_city_that_does_not_belong_to_the_selected_governorate(): void
    {
        [$vendor, $governorate, $city] = $this->vendorWithLocation();
        $otherGovernorate = Governorate::query()->create(['name' => 'Hebron', 'slug' => 'hebron', 'status' => 'active']);
        $otherCity = City::query()->create(['governorate_id' => $otherGovernorate->id, 'name' => 'Hebron City', 'slug' => 'hebron-city', 'status' => 'active']);

        // H1-J: Governorate → City; the mismatch is the city's error, with the translated text. The JSON endpoint has no
        // setLocale middleware, so it answers in the default language; the page shows its own text (i18n.cityMismatch).
        $this->actingAs($vendor, 'vendor')
            ->putJson(route('vendor.dashboard.profile.update'), $this->storePayload(['governorate_id' => $governorate->id, 'city_id' => $otherCity->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['city_id' => 'المدينة المختارة لا تتبع المحافظة المحددة.'])
            ->assertJsonMissingValidationErrors('governorate_id');

        $this->assertStoredLocation($vendor, $governorate->id, $city->id);
    }

    // ---- Store location (H1-J: Governorate → City, effective pair through LocationPair) ----

    public function test_location_a_both_omitted_keep_the_stored_pair_and_b_both_null_clear_it(): void
    {
        [$vendor, $governorate, $city] = $this->vendorWithLocation();

        $this->saveStore($vendor, ['address_line' => 'New address'], omitLocation: true)->assertOk();
        $this->assertStoredLocation($vendor, $governorate->id, $city->id);

        $this->saveStore($vendor, ['governorate_id' => null, 'city_id' => null])->assertOk()
            ->assertJsonPath('vendor.profile.governorate_id', null)->assertJsonPath('vendor.profile.city_id', null);
        $this->assertStoredLocation($vendor, null, null);
        $this->assertSame('New address', $vendor->profile()->first()->address_line, 'The rest is saved as before.');
    }

    public function test_location_c_d_changing_one_field_is_checked_against_the_stored_other(): void
    {
        [$vendor, $governorate, $city] = $this->vendorWithLocation();
        $sebastia = City::query()->create(['governorate_id' => $governorate->id, 'name' => 'Sebastia', 'slug' => 'sebastia', 'status' => 'active']);
        $hebron = Governorate::query()->create(['name' => 'Hebron', 'slug' => 'hebron', 'status' => 'active']);
        $dura = City::query()->create(['governorate_id' => $hebron->id, 'name' => 'Dura', 'slug' => 'dura', 'status' => 'active']);

        // The governorate alone, against the stored city; the city alone, against the stored governorate.
        $this->saveStore($vendor, ['governorate_id' => $hebron->id], omitLocation: true)->assertUnprocessable()
            ->assertJsonValidationErrors('city_id')->assertJsonMissingValidationErrors('governorate_id');
        $this->saveStore($vendor, ['city_id' => $dura->id], omitLocation: true)->assertUnprocessable()->assertJsonValidationErrors('city_id');
        $this->assertStoredLocation($vendor, $governorate->id, $city->id);

        $this->saveStore($vendor, ['city_id' => $sebastia->id], omitLocation: true)->assertOk();
        $this->assertStoredLocation($vendor, $governorate->id, $sebastia->id);
    }

    public function test_location_e_f_clearing_one_field(): void
    {
        [$vendor, $governorate, $city] = $this->vendorWithLocation();

        // F: the governorate cannot be cleared under the city.
        $this->saveStore($vendor, ['governorate_id' => null, 'city_id' => $city->id])->assertUnprocessable()
            ->assertJsonValidationErrors('governorate_id')->assertJsonMissingValidationErrors('city_id');
        $this->assertStoredLocation($vendor, $governorate->id, $city->id);

        // E: the city cleared, the governorate kept; a governorate without a city is valid.
        $this->saveStore($vendor, ['governorate_id' => $governorate->id, 'city_id' => null])->assertOk();
        $this->assertStoredLocation($vendor, $governorate->id, null);
    }

    public function test_location_g_h_legacy_and_inactive_pairs_survive_unrelated_saves(): void
    {
        $nablus = Governorate::query()->create(['name' => 'Nablus Governorate', 'slug' => 'nablus', 'status' => 'active']);
        $gaza = Governorate::query()->create(['name' => 'Gaza Governorate', 'slug' => 'gaza', 'status' => 'active']);
        $gazaCity = City::query()->create(['governorate_id' => $gaza->id, 'name' => 'Gaza', 'slug' => 'gaza-city', 'status' => 'active']);
        $closedGovernorate = Governorate::query()->create(['name' => 'Closed', 'slug' => 'closed', 'status' => 'inactive']);
        $closedCity = City::query()->create(['governorate_id' => $closedGovernorate->id, 'name' => 'Closed Town', 'slug' => 'closed-town', 'status' => 'inactive']);
        // The local data shape before the hierarchy fix: a city with no governorate, a governorate pointing to it.
        $orphan = City::query()->create(['governorate_id' => null, 'name' => 'Orphan', 'slug' => 'orphan', 'status' => 'active']);
        $inverted = Governorate::query()->create(['city_id' => $orphan->id, 'name' => 'Inverted', 'slug' => 'inverted', 'status' => 'active']);

        foreach ([
            'mismatched' => [$nablus->id, $gazaCity->id],
            'inactive' => [$closedGovernorate->id, $closedCity->id],
            'city without governorate' => [null, $orphan->id],
            'reverse-linked pair' => [$inverted->id, $orphan->id],
        ] as $label => [$governorateId, $cityId]) {
            $vendor = $this->vendorWith($governorateId, $cityId);

            // What the script sends (the stored pair as read), and a save with the location omitted.
            $this->saveStore($vendor, ['store_name' => 'Renamed '.$label, 'governorate_id' => $governorateId, 'city_id' => $cityId])->assertOk();
            $this->saveStore($vendor, ['short_description' => 'Kept'], omitLocation: true)->assertOk();

            $this->assertStoredLocation($vendor, $governorateId, $cityId, $label);
            $this->assertSame('Renamed '.$label, $vendor->profile()->first()->store_name, $label);
        }
    }

    public function test_location_i_j_new_values_must_be_active_existing_and_matching(): void
    {
        $vendor = $this->vendorWith(null, null);
        $gaza = Governorate::query()->create(['name' => 'Gaza Governorate', 'slug' => 'gaza', 'status' => 'active']);
        $gazaCity = City::query()->create(['governorate_id' => $gaza->id, 'name' => 'Gaza', 'slug' => 'gaza-city', 'status' => 'active']);
        $nablus = Governorate::query()->create(['name' => 'Nablus Governorate', 'slug' => 'nablus', 'status' => 'active']);
        $closedGovernorate = Governorate::query()->create(['name' => 'Closed', 'slug' => 'closed', 'status' => 'inactive']);
        $closedCity = City::query()->create(['governorate_id' => $gaza->id, 'name' => 'Closed City', 'slug' => 'closed-city', 'status' => 'inactive']);

        foreach ([
            'city only' => [['governorate_id' => null, 'city_id' => $gazaCity->id], 'governorate_id'],
            'mismatched' => [['governorate_id' => $nablus->id, 'city_id' => $gazaCity->id], 'city_id'],
            'inactive governorate' => [['governorate_id' => $closedGovernorate->id, 'city_id' => null], 'governorate_id'],
            'inactive city' => [['governorate_id' => $gaza->id, 'city_id' => $closedCity->id], 'city_id'],
            'unknown governorate' => [['governorate_id' => 999, 'city_id' => null], 'governorate_id'],
            'malformed city' => [['governorate_id' => $gaza->id, 'city_id' => 'abc'], 'city_id'],
        ] as $label => [$location, $field]) {
            $response = $this->saveStore($vendor, $location)->assertUnprocessable();
            $this->assertSame([$field], array_keys($response->json('errors')), $label);
            $this->assertCount(1, $response->json("errors.{$field}"), "{$label}: one message.");
        }
        $this->assertStoredLocation($vendor, null, null, 'Nothing refused is saved.');

        $this->saveStore($vendor, ['governorate_id' => $nablus->id, 'city_id' => null])->assertOk();
        $this->assertStoredLocation($vendor, $nablus->id, null, 'A governorate without a city.');
        $this->saveStore($vendor, ['governorate_id' => (string) $gaza->id, 'city_id' => (string) $gazaCity->id])->assertOk();
        $this->assertStoredLocation($vendor, $gaza->id, $gazaCity->id);
    }

    public function test_location_validation_reads_the_signed_in_vendors_own_stored_pair(): void
    {
        [$vendor, $governorate, $city] = $this->vendorWithLocation();
        $hebron = Governorate::query()->create(['name' => 'Hebron', 'slug' => 'hebron', 'status' => 'active']);
        $dura = City::query()->create(['governorate_id' => $hebron->id, 'name' => 'Dura', 'slug' => 'dura', 'status' => 'active']);
        $other = $this->vendorWith($hebron->id, $dura->id);

        // Hebron fits the other vendor's stored city, not this one's.
        $this->saveStore($vendor, ['governorate_id' => $hebron->id], omitLocation: true)->assertUnprocessable()->assertJsonValidationErrors('city_id');
        $this->saveStore($other, ['governorate_id' => $hebron->id], omitLocation: true)->assertOk();
        $this->assertStoredLocation($vendor, $governorate->id, $city->id);
    }

    public function test_the_location_texts_are_translated_and_unseeded_keys_create_no_rows(): void
    {
        foreach (['ar' => ['المدينة المختارة لا تتبع المحافظة المحددة.', ':name (غير متاح)'], 'en' => ['The selected city does not belong to the selected governorate.', ':name (unavailable)']] as $locale => [$mismatch, $unavailable]) {
            $this->assertSame(VendorDashboardTranslationSeeder::TRANSLATIONS['vendor.My_Store_Error_City_Governorate'][$locale], $mismatch);
            $this->assertSame(VendorDashboardTranslationSeeder::TRANSLATIONS['vendor.My_Store_Location_Unavailable'][$locale], $unavailable);

            $this->actingAs($this->vendor(), 'vendor');
            $this->get(route('vendor.dashboard.my-store').'?change-locale='.$locale);
            $i18n = $this->i18n($this->page());
            $this->assertSame([$mismatch, $unavailable], [$i18n['cityMismatch'], $i18n['locationUnavailable']], $locale);
        }

        // Not seeded yet (as before a deployment's seeder run): the English defaults, and no row is created by the page
        // or by a refused save.
        $keys = ['vendor.My_Store_Error_City_Governorate', 'vendor.My_Store_Location_Unavailable'];
        TranslationValue::query()->whereIn('key', $keys)->delete();
        cache()->flush();
        $before = TranslationValue::count();
        [$vendor, $governorate] = $this->vendorWithLocation();
        $foreign = City::query()->create(['governorate_id' => Governorate::query()->create(['name' => 'Hebron', 'slug' => 'hebron', 'status' => 'active'])->id, 'name' => 'Dura', 'slug' => 'dura', 'status' => 'active']);

        $this->actingAs($vendor, 'vendor');
        $this->get(route('vendor.dashboard.my-store').'?change-locale=ar');
        $i18n = $this->i18n($this->page());
        $this->assertSame(['The selected city does not belong to the selected governorate.', ':name (unavailable)'], [$i18n['cityMismatch'], $i18n['locationUnavailable']]);
        $this->saveStore($vendor, ['governorate_id' => $governorate->id, 'city_id' => $foreign->id])->assertUnprocessable()
            ->assertJsonValidationErrors(['city_id' => 'The selected city does not belong to the selected governorate.']);

        $this->assertSame(0, TranslationValue::query()->whereIn('key', $keys)->count());
        $this->assertSame($before, TranslationValue::count());
    }

    public function test_the_profile_read_gives_active_governorates_with_their_active_cities(): void
    {
        $nablus = Governorate::query()->create(['name' => 'Nablus Governorate', 'slug' => 'nablus', 'status' => 'active']);
        $gaza = Governorate::query()->create(['name' => 'Gaza Governorate', 'slug' => 'gaza', 'status' => 'active']);
        $rafah = City::query()->create(['governorate_id' => $gaza->id, 'name' => 'Rafah', 'slug' => 'rafah', 'status' => 'active']);
        $gazaCity = City::query()->create(['governorate_id' => $gaza->id, 'name' => 'Gaza', 'slug' => 'gaza-city', 'status' => 'active']);
        City::query()->create(['governorate_id' => $gaza->id, 'name' => 'Closed City', 'slug' => 'closed-city', 'status' => 'inactive']);
        $closedGovernorate = Governorate::query()->create(['name' => 'Closed', 'slug' => 'closed', 'status' => 'inactive']);
        $closedTown = City::query()->create(['governorate_id' => $closedGovernorate->id, 'name' => 'Closed Town', 'slug' => 'closed-town', 'status' => 'inactive']);
        $vendor = $this->vendorWith($closedGovernorate->id, $closedTown->id);

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $response = $this->actingAs($vendor, 'vendor')->getJson(route('vendor.dashboard.profile.show'))->assertOk();

        $this->assertSame(['vendor', 'media', 'locations'], array_keys($response->json()));
        $this->assertSame(['governorates'], array_keys($response->json('locations')));
        $this->assertSame([
            ['id' => $gaza->id, 'name' => 'Gaza Governorate', 'cities' => [
                ['id' => $gazaCity->id, 'governorate_id' => $gaza->id, 'name' => 'Gaza'],
                ['id' => $rafah->id, 'governorate_id' => $gaza->id, 'name' => 'Rafah'],
            ]],
            ['id' => $nablus->id, 'name' => 'Nablus Governorate', 'cities' => []],
        ], $response->json('locations.governorates'), 'Active only, by name, through cities.governorate_id.');

        // The stored inactive pair stays readable through the profile, with the city's own governorate.
        $this->assertSame([$closedGovernorate->id, 'Closed', $closedTown->id, 'Closed Town', $closedGovernorate->id], [
            $response->json('vendor.profile.governorate_id'), $response->json('vendor.profile.governorate.name'),
            $response->json('vendor.profile.city_id'), $response->json('vendor.profile.city.name'), $response->json('vendor.profile.city.governorate_id'),
        ]);
        $this->assertLessThanOrEqual(8, $queries, 'Two queries for the locations, as before.');
    }

    public function test_the_location_script_passes_its_node_tests(): void
    {
        $node = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node 2>NUL' : 'command -v node 2>/dev/null'));
        $node = strtok($node, "\r\n");
        if ($node === false || $node === '') {
            $this->markTestSkipped('Node is not available.');
        }

        $process = proc_open([$node, '--test', 'tests/js/vendor-my-store-locations.test.mjs'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), $output);
        $this->assertMatchesRegularExpression('/^(?:ℹ|#) fail 0$/m', $output);
        $this->assertDoesNotMatchRegularExpression('/^(?:ℹ|#) pass 0$/m', $output);
    }

    public function test_the_script_never_clears_the_location_and_uses_the_location_module(): void
    {
        $script = file_get_contents(resource_path('js/vendor-my-store.js'));
        $module = file_get_contents(resource_path('js/vendor-my-store-locations.js'));

        $this->assertStringContainsString("import { locationOptions, locationProblem, normalizeLocations, storedLocation, toId } from './vendor-my-store-locations.js';", $script);
        $this->assertStringNotContainsString('function toId', $script);
        // The selection is held in the script and is always among the options; the governorate change keeps the city.
        $this->assertMatchesRegularExpression("/field\('governorate_id'\)\.addEventListener\('change', \(\) => \{\s*selection = \{ governorateId: selectedId\('governorate_id'\), cityId: selection\.cityId \};/", $script);
        $this->assertStringNotContainsString("field('governorate_id').value = '';", $script);
        $this->assertStringNotContainsString("field('city_id').value = '';", $script);
        $this->assertMatchesRegularExpression('/selection = \{ governorateId: stored\.governorateId, cityId: stored\.cityId \};\s*renderLocations\(\);/', $script);
        // A newly incompatible pair is not sent; the mismatch text only where the selection shows a mismatch.
        $this->assertMatchesRegularExpression("/if \(problem === 'mismatch'\) \{\s*showFieldError\('city_id', i18n\.cityMismatch\);\s*invalid\.push\('city_id'\);/", $script);
        $this->assertMatchesRegularExpression("/if \(name === 'city_id' && problem === 'mismatch'\) \{\s*return i18n\.cityMismatch;/", $script);
        foreach ([$script, $module] as $source) {
            $this->assertDoesNotMatchRegularExpression('/innerHTML|outerHTML|insertAdjacentHTML/', $source);
        }
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}|document\.|window\.|fetch\(/u', $module, 'The module holds rules only: no texts, DOM or requests.');
    }

    public function test_profile_update_enforces_the_database_short_description_limit(): void
    {
        [$vendor] = $this->vendorWithLocation();

        $this->actingAs($vendor, 'vendor')
            ->putJson(route('vendor.dashboard.profile.update'), [
                'name' => 'Owner Name',
                'store_name' => 'Olive Workshop',
                'phone' => '0599123123',
                'short_description' => str_repeat('a', 256),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['short_description']);
    }

    // ---- Script contract ----

    public function test_the_script_sends_exactly_the_approved_payload(): void
    {
        $script = file_get_contents(resource_path('js/vendor-my-store.js'));

        preg_match('/const payload = \(\) => \{.*?return \{(.*?)\};/s', $script, $match);
        preg_match_all('/^\s*([a-z_]+):/m', $match[1], $keys);
        $sent = $keys[1];
        sort($sent);

        $this->assertSame(['address_line', 'city_id', 'description', 'email', 'governorate_id', 'name', 'phone', 'preparation_days', 'short_description', 'slug', 'store_name'], $sent);

        // Account values come from the in-memory state set by the read; location values come from the store form.
        foreach (self::PRESERVED as $name) {
            $this->assertStringContainsString("$name: preserved.$name", $match[1]);
        }
        foreach (['city_id', 'governorate_id'] as $name) {
            $this->assertStringContainsString("$name: field('$name').value", $match[1]);
        }
    }

    public function test_the_script_guards_save_errors_and_data_handling(): void
    {
        $script = file_get_contents(resource_path('js/vendor-my-store.js'));

        // Save is enabled only once the preservation state exists.
        $this->assertStringContainsString('saveButton.disabled = !enabled || preserved === null;', $script);
        $this->assertStringContainsString('if (saving || preserved === null) {', $script);
        // Confirmed save, then a reload.
        $this->assertStringContainsString('window.location.reload()', $script);
        // 422: editable keys map to fields, any other key becomes the general error.
        $this->assertStringContainsString('Object.hasOwn(EDITABLE, name)', $script);
        $this->assertStringContainsString('if (other || fields.length === 0) {', $script);
        $this->assertStringContainsString("method: 'PUT'", $script);

        foreach (['avatar', 'cover_image', 'commission', 'approval', 'rejection', 'last_login', 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'localStorage', 'sessionStorage', 'console.', 'setAttribute(\'data-'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, preg_replace('/^\s*\*.*$/m', '', $script), $forbidden);
        }
    }

    private function vendor(): Vendor
    {
        return Vendor::factory()->approved()->create();
    }

    /**
     * @return array{0: Vendor, 1: Governorate, 2: City}
     */
    private function vendorWithLocation(): array
    {
        // Governorate → City (cities.governorate_id).
        $governorate = Governorate::query()->create(['name' => 'Nablus Governorate', 'slug' => 'nablus', 'status' => 'active']);
        $city = City::query()->create(['governorate_id' => $governorate->id, 'name' => 'Nablus City', 'slug' => 'nablus-city', 'status' => 'active']);

        $vendor = Vendor::factory()->create(['name' => 'Owner Name', 'email' => 'owner.private@example.com', 'phone' => '0599123123']);
        $vendor->profile()->create([
            'store_name' => 'Olive Workshop',
            'slug' => 'olive-workshop-slug',
            'short_description' => 'Handmade olive wood',
            'description' => 'Long store story',
            'address_line' => 'Old City, Street 5',
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'logo' => 'logos/olive.png',
            'cover_image' => 'covers/olive.png',
            'commission_rate' => 12.5,
            'approval_status' => 'approved',
            'approved_at' => now(),
        ]);

        return [$vendor->load('profile'), $governorate, $city];
    }

    /** An approved vendor whose store holds this pair, written as stored data (legacy or inactive values included). */
    private function vendorWith(?int $governorateId, ?int $cityId): Vendor
    {
        $vendor = Vendor::factory()->approved()->create();
        $vendor->profile()->update(['governorate_id' => $governorateId, 'city_id' => $cityId]);

        return $vendor;
    }

    /** The script's replacement payload for the vendor's current values, with $changes applied. */
    private function storePayload(array $changes = [], ?Vendor $vendor = null, bool $omitLocation = false): array
    {
        $profile = $vendor?->profile()->first();
        $payload = $changes + [
            'name' => $vendor?->fresh()->name ?? 'Owner Name',
            'store_name' => $profile?->store_name ?? 'Olive Workshop',
            'phone' => $vendor?->fresh()->phone ?? '0599123123',
            'governorate_id' => $profile?->governorate_id,
            'city_id' => $profile?->city_id,
        ];

        return $omitLocation ? array_diff_key($payload, array_diff_key(['governorate_id' => true, 'city_id' => true], $changes)) : $payload;
    }

    private function saveStore(Vendor $vendor, array $changes, bool $omitLocation = false): TestResponse
    {
        return $this->actingAs($vendor, 'vendor')->putJson(route('vendor.dashboard.profile.update'), $this->storePayload($changes, $vendor, $omitLocation));
    }

    private function assertStoredLocation(Vendor $vendor, ?int $governorateId, ?int $cityId, string $message = ''): void
    {
        $profile = $vendor->profile()->first();

        $this->assertSame([$governorateId, $cityId], [$profile->governorate_id, $profile->city_id], $message);
    }

    private function page(): TestResponse
    {
        return $this->get(route('vendor.dashboard.my-store'));
    }

    /**
     * @return array<string, string>
     */
    private function i18n(TestResponse $response): array
    {
        preg_match('/<script type="application\/json" data-store-i18n>(.*?)<\/script>/s', $response->getContent(), $match);

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }
}
