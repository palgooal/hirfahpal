<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\Category;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Language;
use App\Models\Product;
use App\Models\RoleUser;
use App\Models\TranslationValue;
use App\Models\Vendor;
use Database\Seeders\AdminHomeTranslationSeeder;
use Database\Seeders\AdminSettingsTranslationSeeder;
use Database\Seeders\VendorDashboardTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cities and governorates page (dashboard/settings/locations), its two notices, and the vendor product's
 * governorate/city mismatch message: in the page language, from the seeders, without creating translation rows;
 * the page's authorization and the product validation unchanged.
 */
class LocationManagementLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    public function test_the_page_reads_in_arabic_and_in_english(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);
        $this->seed(AdminHomeTranslationSeeder::class);
        $city = City::query()->create(['name' => 'Gaza', 'slug' => 'gaza']);
        Governorate::query()->create(['city_id' => $city->id, 'name' => 'Rimal', 'slug' => 'rimal']);
        Governorate::query()->create(['city_id' => $city->id, 'name' => 'Shati', 'slug' => 'shati']);
        City::query()->create(['name' => 'Nablus', 'slug' => 'nablus']);
        $admin = $this->admin(['settings.view', 'settings.edit']);

        $pages = [
            'ar' => ['rtl', ['إدارة المدن والمحافظات', 'المدن', 'أضف المدينة أولًا، ثم أضف محافظاتها.', 'المدينة', 'إضافة مدينة', 'المحافظات', 'اختر المدينة', 'المحافظة', 'إضافة محافظة', 'المواقع المضافة', 'لم تُضف محافظات بعد.', 'Rimal، Shati'], ['Add city', 'Configured locations', 'No governorates added yet.']],
            'en' => ['ltr', ['Manage cities and governorates', 'Cities', 'Add a city before adding its governorates.', 'Add city', 'Governorates', 'Choose a city', 'Add governorate', 'Configured locations', 'No governorates added yet.', 'Rimal, Shati'], ['إضافة مدينة', 'المواقع المضافة']],
        ];

        foreach ($pages as $locale => [$direction, $seen, $unseen]) {
            $html = $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])
                ->get(route('dashboard.setting.locations.index'))->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/<html[^>]* dir="'.$direction.'"/', $html, $locale);
            foreach ($seen as $text) {
                $this->assertStringContainsString(e($text), $html, "{$locale}: {$text}");
            }
            foreach ($unseen as $text) {
                $this->assertStringNotContainsString(e($text), $html, "{$locale}: {$text}");
            }
        }
    }

    public function test_the_empty_page_says_so_in_the_page_language(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);
        $admin = $this->admin(['settings.view']);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.setting.locations.index'))
            ->assertOk()->assertSee('لم تُضف مدن بعد.')->assertDontSee('No cities added yet.');
        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('dashboard.setting.locations.index'))
            ->assertOk()->assertSee('No cities added yet.');
    }

    public function test_the_page_and_its_notices_create_no_translation_row_before_the_seeder_runs(): void
    {
        $admin = $this->admin(['settings.view', 'settings.edit']);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.setting.locations.index'))
            ->assertOk()->assertSee('Add city');
        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->post(route('dashboard.setting.locations.cities.store'), ['name' => 'Gaza'])
            ->assertSessionHas('success', 'City added successfully.');

        // Location_Management is the breadcrumb shared with the settings page (a CORRECTIONS key, read with t()).
        $this->assertSame(0, TranslationValue::query()->where('key', 'like', 'dashboard.Location\_%')
            ->where('key', '!=', 'dashboard.Location_Management')->count());
    }

    public function test_the_notices_are_in_the_page_language(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);
        $admin = $this->admin(['settings.view', 'settings.edit']);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->post(route('dashboard.setting.locations.cities.store'), ['name' => 'Gaza'])
            ->assertRedirect()->assertSessionHas('success', 'تمت إضافة المدينة بنجاح.');
        $city = City::query()->where('name', 'Gaza')->firstOrFail();
        $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->post(route('dashboard.setting.locations.governorates.store'), ['city_id' => $city->id, 'name' => 'Rimal'])
            ->assertRedirect()->assertSessionHas('success', 'تمت إضافة المحافظة بنجاح.');

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->post(route('dashboard.setting.locations.cities.store'), ['name' => 'Nablus'])
            ->assertRedirect()->assertSessionHas('success', 'City added successfully.');
        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->post(route('dashboard.setting.locations.governorates.store'), ['city_id' => $city->id, 'name' => 'Shati'])
            ->assertRedirect()->assertSessionHas('success', 'Governorate added successfully.');

        $this->assertSame(['Gaza', 'Nablus'], City::query()->orderBy('name')->pluck('name')->all());
        $this->assertSame([$city->id, $city->id], Governorate::query()->pluck('city_id')->all());
    }

    public function test_the_page_needs_settings_view_and_adding_needs_settings_edit(): void
    {
        $this->get(route('dashboard.setting.locations.index'))->assertRedirect(route('admin.login'));

        $this->actingAs($this->admin([]), 'admin')->get(route('dashboard.setting.locations.index'))->assertForbidden();

        $viewer = $this->admin(['settings.view']);
        $this->actingAs($viewer, 'admin')->get(route('dashboard.setting.locations.index'))->assertOk();
        $this->actingAs($viewer, 'admin')->post(route('dashboard.setting.locations.cities.store'), ['name' => 'Gaza'])->assertForbidden();
        $city = City::query()->create(['name' => 'Nablus', 'slug' => 'nablus']);
        $this->actingAs($viewer, 'admin')->post(route('dashboard.setting.locations.governorates.store'), ['city_id' => $city->id, 'name' => 'Old City'])->assertForbidden();

        $this->assertSame(['Nablus'], City::query()->pluck('name')->all());
        $this->assertSame(0, Governorate::query()->count());
    }

    public function test_the_governorate_mismatch_is_reported_in_the_page_language_on_create_and_update(): void
    {
        $this->seed(VendorDashboardTranslationSeeder::class);
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::query()->create(['name' => 'Crafts', 'slug' => 'crafts', 'is_active' => true]);
        $city = City::query()->create(['name' => 'Gaza', 'slug' => 'gaza']);
        $other = City::query()->create(['name' => 'Nablus', 'slug' => 'nablus']);
        $foreign = Governorate::query()->create(['city_id' => $other->id, 'name' => 'Old City', 'slug' => 'old-city']);
        $own = Governorate::query()->create(['city_id' => $city->id, 'name' => 'Rimal', 'slug' => 'rimal']);
        $product = Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Jug', 'slug' => 'jug', 'price' => 10, 'stock_quantity' => 5, 'status' => 'draft']);
        $body = ['category_id' => $category->id, 'name' => 'Bowl', 'price' => 25, 'stock_quantity' => 4, 'city_id' => $city->id, 'governorate_id' => $foreign->id];

        foreach (['ar' => 'المحافظة المختارة لا تتبع المدينة المختارة.', 'en' => 'The selected governorate does not belong to the selected city.'] as $locale => $message) {
            $this->actingAs($vendor, 'vendor')->withSession(['locale' => $locale])
                ->postJson(route('vendor.dashboard.products.store'), $body)
                ->assertUnprocessable()->assertJsonPath('errors.governorate_id', [$message]);
            $this->actingAs($vendor, 'vendor')->withSession(['locale' => $locale])
                ->putJson(route('vendor.dashboard.products.update', $product), $body)
                ->assertUnprocessable()->assertJsonPath('errors.governorate_id', [$message]);
        }

        $this->assertSame(1, Product::query()->count());
        $this->assertNull($product->fresh()->governorate_id);

        // The governorate of the chosen city is still accepted.
        $this->actingAs($vendor, 'vendor')->withSession(['locale' => 'ar'])
            ->putJson(route('vendor.dashboard.products.update', $product), ['governorate_id' => $own->id] + $body)->assertOk();
        $this->assertSame([$city->id, $own->id], [$product->fresh()->city_id, $product->fresh()->governorate_id]);
    }

    public function test_the_mismatch_message_creates_no_translation_row(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::query()->create(['name' => 'Crafts', 'slug' => 'crafts', 'is_active' => true]);
        $city = City::query()->create(['name' => 'Gaza', 'slug' => 'gaza']);
        $other = City::query()->create(['name' => 'Nablus', 'slug' => 'nablus']);
        $foreign = Governorate::query()->create(['city_id' => $other->id, 'name' => 'Old City', 'slug' => 'old-city']);

        $this->actingAs($vendor, 'vendor')->withSession(['locale' => 'ar'])
            ->postJson(route('vendor.dashboard.products.store'), ['category_id' => $category->id, 'name' => 'Bowl', 'price' => 25, 'stock_quantity' => 4, 'city_id' => $city->id, 'governorate_id' => $foreign->id])
            ->assertUnprocessable()->assertJsonPath('errors.governorate_id', ['The selected governorate does not belong to the selected city.']);

        $this->assertSame(0, TranslationValue::query()->where('key', 'vendor.Product_Governorate_Mismatch')->count());
    }

    public function test_the_location_fields_are_named_in_the_page_language_on_create_and_update(): void
    {
        $this->seed(VendorDashboardTranslationSeeder::class);
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::query()->create(['name' => 'Crafts', 'slug' => 'crafts', 'is_active' => true]);
        $city = City::query()->create(['name' => 'Gaza', 'slug' => 'gaza']);
        $other = City::query()->create(['name' => 'Nablus', 'slug' => 'nablus']);
        $own = Governorate::query()->create(['city_id' => $city->id, 'name' => 'Rimal', 'slug' => 'rimal']);
        $foreign = Governorate::query()->create(['city_id' => $other->id, 'name' => 'Old City', 'slug' => 'old-city']);
        $product = Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Jug', 'slug' => 'jug', 'price' => 10, 'stock_quantity' => 5, 'status' => 'draft']);
        $body = ['category_id' => $category->id, 'name' => 'Bowl', 'price' => 25, 'stock_quantity' => 4];

        // [payload, field, Arabic message, English message]: the first message of the field.
        $cases = [
            'governorate without its city' => [['governorate_id' => $own->id], 'city_id', 'حقل المدينة مطلوب عند وجود المحافظة.', 'The city field is required when governorate is present.'],
            'city without its governorate' => [['city_id' => $city->id], 'governorate_id', 'حقل المحافظة مطلوب عند وجود المدينة.', 'The governorate field is required when city is present.'],
            'city not an integer' => [['city_id' => 'gaza', 'governorate_id' => $own->id], 'city_id', 'يجب أن يكون حقل المدينة عددًا صحيحًا.', 'The city field must be an integer.'],
            'governorate not an integer' => [['city_id' => $city->id, 'governorate_id' => 'rimal'], 'governorate_id', 'يجب أن يكون حقل المحافظة عددًا صحيحًا.', 'The governorate field must be an integer.'],
            'unknown city' => [['city_id' => 999, 'governorate_id' => $own->id], 'city_id', 'قيمة المدينة المختارة غير صالحة.', 'The selected city is invalid.'],
            'unknown governorate' => [['city_id' => $city->id, 'governorate_id' => 999], 'governorate_id', 'قيمة المحافظة المختارة غير صالحة.', 'The selected governorate is invalid.'],
            // The custom mismatch message is kept as it is.
            'governorate of another city' => [['city_id' => $city->id, 'governorate_id' => $foreign->id], 'governorate_id', 'المحافظة المختارة لا تتبع المدينة المختارة.', 'The selected governorate does not belong to the selected city.'],
        ];

        foreach ($cases as $label => [$location, $field, $ar, $en]) {
            foreach (['ar' => $ar, 'en' => $en] as $locale => $message) {
                $this->actingAs($vendor, 'vendor')->withSession(['locale' => $locale])
                    ->postJson(route('vendor.dashboard.products.store'), $location + $body)
                    ->assertUnprocessable()->assertJsonPath("errors.{$field}.0", $message);
                $this->actingAs($vendor, 'vendor')->withSession(['locale' => $locale])
                    ->putJson(route('vendor.dashboard.products.update', $product), $location + $body)
                    ->assertUnprocessable()->assertJsonPath("errors.{$field}.0", $message);
            }

            $this->assertSame(1, Product::query()->count(), $label);
            $this->assertSame(['Jug', null, null], [$product->fresh()->name, $product->fresh()->city_id, $product->fresh()->governorate_id], $label);
        }

        // No raw field name reaches either language.
        foreach (['ar', 'en'] as $locale) {
            $content = $this->actingAs($vendor, 'vendor')->withSession(['locale' => $locale])
                ->postJson(route('vendor.dashboard.products.store'), ['city_id' => 'x', 'governorate_id' => 'y'] + $body)->assertUnprocessable()->getContent();
            $this->assertStringNotContainsString('city id', $content, $locale);
            $this->assertStringNotContainsString('governorate id', $content, $locale);
        }
    }

    /** @param  list<string>  $abilities */
    private function admin(array $abilities): Admin
    {
        $number = Admin::query()->count() + 1;
        $admin = Admin::create([
            'name' => 'Admin '.$number, 'email' => 'admin'.$number.'@example.com',
            'phone' => '05990000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'password' => 'password',
            'status' => 'active', 'super_admin' => false,
        ]);

        foreach ($abilities as $ability) {
            RoleUser::create(['role_name' => $ability, 'user_id' => $admin->id, 'ability' => 'allow']);
        }

        return $admin;
    }
}
