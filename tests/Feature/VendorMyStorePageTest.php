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

    private const EDITABLE = ['address_line', 'description', 'name', 'short_description', 'store_name'];

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
        $this->assertStringNotContainsString('type="file"', $html);
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
                ->assertDontSee('Logo')
                ->assertDontSee('شعار المتجر')
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
        ], array_keys($i18n));
        $this->assertSame('غير محدد', $i18n['notSpecified']);
        $this->assertSame('تم حفظ التغييرات بنجاح.', $i18n['saved']);
        $this->assertSame('هذا الحقل مطلوب.', $i18n['required']);
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

    /**
     * Why the script must always send the preserved values: the existing update
     * replaces omitted fields and regenerates the slug (N1/N2, backend-owned).
     */
    public function test_omitting_preserved_values_would_lose_data(): void
    {
        [$vendor] = $this->vendorWithLocation();

        $this->actingAs($vendor, 'vendor')
            ->putJson(route('vendor.dashboard.profile.update'), [
                'name' => 'Owner Name',
                'store_name' => 'Olive Workshop',
                'phone' => '0599123123',
            ])
            ->assertOk();

        $vendor->refresh()->load('profile');
        $this->assertNull($vendor->email);
        $this->assertNull($vendor->profile->governorate_id);
        $this->assertNull($vendor->profile->city_id);
        $this->assertNotSame('olive-workshop-slug', $vendor->profile->slug);
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

    // ---- Script contract ----

    public function test_the_script_sends_exactly_the_approved_payload(): void
    {
        $script = file_get_contents(resource_path('js/vendor-my-store.js'));

        preg_match('/const payload = \(\) => \{.*?return \{(.*?)\};/s', $script, $match);
        preg_match_all('/^\s*([a-z_]+):/m', $match[1], $keys);
        $sent = $keys[1];
        sort($sent);

        $this->assertSame(['address_line', 'city_id', 'description', 'email', 'governorate_id', 'name', 'phone', 'short_description', 'slug', 'store_name'], $sent);

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
        $this->assertStringContainsString("if (saving || preserved === null) {", $script);
        // Confirmed save, then a reload.
        $this->assertStringContainsString('window.location.reload()', $script);
        // 422: editable keys map to fields, any other key becomes the general error.
        $this->assertStringContainsString('Object.hasOwn(EDITABLE, name)', $script);
        $this->assertStringContainsString('if (other || fields.length === 0) {', $script);
        $this->assertStringContainsString("method: 'PUT'", $script);

        foreach (['avatar', 'logo', 'cover_image', 'commission', 'approval', 'rejection', 'last_login', 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'localStorage', 'sessionStorage', 'console.', 'setAttribute(\'data-'] as $forbidden) {
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
