<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Governorate;
use App\Models\Language;
use App\Models\Vendor;
use Database\Seeders\AdminAuthTranslationSeeder;
use Database\Seeders\VendorAuthTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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

    private const EDITABLE = ['address_line', 'description', 'name', 'preparation_days', 'short_description', 'store_name'];

    private const PRESERVED = ['city_id', 'email', 'governorate_id', 'phone', 'slug'];

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
        $this->assertStringNotContainsString('<select', $html);
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

    public function test_email_phone_and_location_are_display_only(): void
    {
        $html = $this->actingAs($this->vendor(), 'vendor')->page()->getContent();

        foreach (['email', 'phone', 'governorate', 'city'] as $key) {
            $this->assertStringContainsString('<dd data-readonly="'.$key.'">', $html);
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
            'logoUploading', 'logoSaving', 'logoUploaded', 'logoUploadFailed', 'logoConfirmDelete', 'logoDeleted', 'logoDeleteFailed',
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
        // Remove is hidden until a logo is shown; the preview starts hidden too.
        $this->assertMatchesRegularExpression('/<button\b[^>]*data-store-logo-delete hidden>/', $html);
        $this->assertMatchesRegularExpression('/<img\b[^>]*data-store-logo hidden\b/', $html);
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

        // No Bootstrap utilities (the vendor shell does not load them) and no inline styles in the logo section.
        preg_match('/<section[^>]*aria-labelledby="vendor-store-logo-title".*?<\/section>/s', $html, $section);
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
        [$vendor, $governorate] = $this->vendorWithLocation();
        $otherGovernorate = Governorate::query()->create(['name' => 'Hebron', 'slug' => 'hebron', 'status' => 'active']);
        $otherCity = City::query()->create(['governorate_id' => $otherGovernorate->id, 'name' => 'Hebron City', 'slug' => 'hebron-city', 'status' => 'active']);

        $this->actingAs($vendor, 'vendor')
            ->putJson(route('vendor.dashboard.profile.update'), [
                'name' => 'Owner Name',
                'store_name' => 'Olive Workshop',
                'phone' => '0599123123',
                'governorate_id' => $governorate->id,
                'city_id' => $otherCity->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['city_id']);
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

        // Preserved values come from the in-memory state set by the read, never from the form.
        foreach (self::PRESERVED as $name) {
            $this->assertStringContainsString("$name: preserved.$name", $match[1]);
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
