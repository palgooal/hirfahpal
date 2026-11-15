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
 * The cities and governorates page (dashboard/settings/locations) and its two notices: in the page language, from the
 * seeders, without creating translation rows; the page's authorization unchanged. Products inherit the store's location,
 * so the vendor product endpoints ignore a submitted city or governorate.
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
            'ar' => ['rtl', ['إدارة المدن والمحافظات', 'المدن', 'أضف المدينة أولًا، ثم أضف محافظاتها.', 'المدينة', 'إضافة مدينة', 'المحافظات', 'اختر المدينة', 'المحافظة', 'إضافة محافظة', 'المواقع المضافة', 'لم تُضف محافظات بعد.', 'Rimal', 'Shati', 'عدد المحافظات: 2'], ['Add city', 'Configured locations', 'No governorates added yet.']],
            'en' => ['ltr', ['Manage cities and governorates', 'Cities', 'Add a city before adding its governorates.', 'Add city', 'Governorates', 'Choose a city', 'Add governorate', 'Configured locations', 'No governorates added yet.', 'Rimal', 'Shati', 'Governorates: 2'], ['إضافة مدينة', 'المواقع المضافة']],
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

    public function test_multiple_governorates_are_saved_for_the_same_city_in_one_submission(): void
    {
        $this->seed(AdminSettingsTranslationSeeder::class);
        $admin = $this->admin(['settings.view', 'settings.edit']);
        $city = City::query()->create(['name' => 'Gaza', 'slug' => 'gaza']);

        $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])
            ->post(route('dashboard.setting.locations.governorates.store'), [
                'city_id' => $city->id,
                'governorates' => ['Rimal', 'Shati', ''],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Governorates added successfully.');

        $this->assertSame(['Rimal', 'Shati'], Governorate::query()->orderBy('name')->pluck('name')->all());
        $this->assertSame([$city->id, $city->id], Governorate::query()->orderBy('name')->pluck('city_id')->all());
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

    /**
     * Products inherit the store's location: the vendor product endpoints never take a city or a governorate. A
     * submitted pair (matching or not) is dropped, the product is saved, and no location message is shown.
     */
    public function test_a_new_product_ignores_a_submitted_location_in_either_language(): void
    {
        $this->seed(VendorDashboardTranslationSeeder::class);
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::query()->create(['name' => 'Crafts', 'slug' => 'crafts', 'is_active' => true]);
        [$governorate, $city, $foreign] = $this->locations();
        $pairs = ['matching' => [$city->id, $governorate->id], 'mismatched' => [$city->id, $foreign->id]];

        foreach (['ar', 'en'] as $locale) {
            foreach ($pairs as $label => [$cityId, $governorateId]) {
                $response = $this->actingAs($vendor, 'vendor')->withSession(['locale' => $locale])
                    ->postJson(route('vendor.dashboard.products.store'), ['category_id' => $category->id, 'name' => "Bowl {$locale} {$label}", 'price' => 25, 'stock_quantity' => 4, 'city_id' => $cityId, 'governorate_id' => $governorateId])
                    ->assertCreated()
                    ->assertJsonMissingPath('errors')
                    ->assertJsonPath('product.city_id', null)
                    ->assertJsonPath('product.governorate_id', null);

                $product = Product::query()->findOrFail($response->json('product.id'));
                $this->assertSame([null, null], [$product->city_id, $product->governorate_id], "{$locale} {$label}");
            }
        }

        $this->assertSame(4, Product::query()->count());
        $this->assertSame(0, Product::query()->whereNotNull('city_id')->orWhereNotNull('governorate_id')->count());
    }

    public function test_an_update_never_sets_changes_or_clears_the_product_location(): void
    {
        $this->seed(VendorDashboardTranslationSeeder::class);
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::query()->create(['name' => 'Crafts', 'slug' => 'crafts', 'is_active' => true]);
        [$governorate, $city, $foreign, $otherCity] = $this->locations();
        // A product without a location, and one holding a location saved before the store-level decision.
        $plain = Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Jug', 'slug' => 'jug', 'price' => 10, 'stock_quantity' => 5, 'status' => 'draft']);
        $legacy = Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'city_id' => $otherCity->id, 'governorate_id' => $foreign->id, 'name' => 'Vase', 'slug' => 'vase', 'price' => 10, 'stock_quantity' => 5, 'status' => 'draft']);
        $body = ['category_id' => $category->id, 'price' => 25, 'stock_quantity' => 4];
        $payloads = [
            'a matching pair' => ['city_id' => $city->id, 'governorate_id' => $governorate->id],
            'a mismatched pair' => ['city_id' => $city->id, 'governorate_id' => $foreign->id],
            'empty values' => ['city_id' => null, 'governorate_id' => null],
        ];

        foreach (['ar', 'en'] as $locale) {
            foreach ($payloads as $label => $location) {
                foreach ([[$plain, [null, null]], [$legacy, [$otherCity->id, $foreign->id]]] as [$product, $kept]) {
                    $this->actingAs($vendor, 'vendor')->withSession(['locale' => $locale])
                        ->putJson(route('vendor.dashboard.products.update', $product), $location + $body + ['name' => $product->name])
                        ->assertOk()
                        ->assertJsonMissingPath('errors');

                    $fresh = $product->fresh();
                    $this->assertSame($kept, [$fresh->city_id, $fresh->governorate_id], "{$locale}, {$label}, {$product->name}");
                    $this->assertEquals(25, $fresh->price, 'The rest of the update is saved.');
                }
            }
        }
    }

    public function test_malformed_location_values_are_ignored_and_create_no_translation_rows(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::query()->create(['name' => 'Crafts', 'slug' => 'crafts', 'is_active' => true]);
        [$governorate, $city] = $this->locations();
        $product = Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Jug', 'slug' => 'jug', 'price' => 10, 'stock_quantity' => 5, 'status' => 'draft']);
        $locationRows = fn () => TranslationValue::query()->where(fn ($query) => $query->where('key', 'like', '%Governorate%')->orWhere('key', 'like', '%City%'))->count();
        $before = $locationRows();
        // Values the old product rules refused: none of them can reach the product or produce a message now.
        $cases = [
            'governorate without its city' => ['governorate_id' => $governorate->id],
            'city without its governorate' => ['city_id' => $city->id],
            'not integers' => ['city_id' => 'gaza', 'governorate_id' => 'rimal'],
            'unknown ids' => ['city_id' => 999, 'governorate_id' => 999],
        ];

        foreach (['ar', 'en'] as $locale) {
            foreach ($cases as $label => $location) {
                $created = $this->actingAs($vendor, 'vendor')->withSession(['locale' => $locale])
                    ->postJson(route('vendor.dashboard.products.store'), $location + ['category_id' => $category->id, 'name' => "Bowl {$locale} {$label}", 'price' => 25, 'stock_quantity' => 4])
                    ->assertCreated()->assertJsonMissingPath('errors');
                $this->actingAs($vendor, 'vendor')->withSession(['locale' => $locale])
                    ->putJson(route('vendor.dashboard.products.update', $product), $location + ['category_id' => $category->id, 'name' => 'Jug', 'price' => 25, 'stock_quantity' => 4])
                    ->assertOk()->assertJsonMissingPath('errors');

                $new = Product::query()->findOrFail($created->json('product.id'));
                $this->assertSame([null, null, null, null], [$new->city_id, $new->governorate_id, $product->fresh()->city_id, $product->fresh()->governorate_id], "{$locale}, {$label}");
            }
        }

        $this->assertSame(0, TranslationValue::query()->where('key', 'vendor.Product_Governorate_Mismatch')->count());
        $this->assertSame($before, $locationRows(), 'No location text is created by the product endpoints.');
    }

    /**
     * Fixtures in the approved hierarchy (a governorate holds its cities): a governorate and its city, and another
     * governorate with its own city.
     *
     * @return array{0: Governorate, 1: City, 2: Governorate, 3: City}
     */
    private function locations(): array
    {
        $governorate = Governorate::query()->create(['name' => 'Gaza Governorate', 'slug' => 'gaza-governorate']);
        $city = City::query()->create(['governorate_id' => $governorate->id, 'name' => 'Gaza', 'slug' => 'gaza']);
        $foreign = Governorate::query()->create(['name' => 'Nablus Governorate', 'slug' => 'nablus-governorate']);
        $otherCity = City::query()->create(['governorate_id' => $foreign->id, 'name' => 'Nablus', 'slug' => 'nablus']);

        return [$governorate, $city, $foreign, $otherCity];
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
