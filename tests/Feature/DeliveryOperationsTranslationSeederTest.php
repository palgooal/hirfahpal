<?php

namespace Tests\Feature;

use App\Models\TranslationValue;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DeliveryOperationsTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** E2-MVP batch 04: the texts of the admin delivery operations, as the vendor order and delivery driver pages read them. */
class DeliveryOperationsTranslationSeederTest extends TestCase
{
    use RefreshDatabase;

    private const VIEWS = ['dashboard/vendor-orders/show.blade.php', 'dashboard/delivery-drivers/show.blade.php'];

    public function test_the_seeder_holds_exactly_the_keys_the_pages_read_with_their_english_defaults(): void
    {
        $defaults = [];
        foreach (self::VIEWS as $view) {
            preg_match_all("/t\\('(dashboard\\.Delivery_Ops_[A-Za-z_]+)', '([^']+)'\\)/", file_get_contents(resource_path('views/'.$view)), $matches, PREG_SET_ORDER);
            foreach ($matches as [, $key, $default]) {
                $this->assertSame($defaults[$key] ?? $default, $default, "{$key}: one default everywhere.");
                $defaults[$key] = $default;
            }
        }
        // E2-MVP batch 05: also the cash controller's messages and the two ability labels.
        foreach (['app/Http/Controllers/Dashboard/VendorOrderManagementController.php', 'app/Http/Controllers/Dashboard/DriverCashController.php', 'data/abilities.php'] as $source) {
            preg_match_all("/t\\('(dashboard\\.Delivery_Ops_[A-Za-z_]+)', '([^']+)'\\)/", file_get_contents(base_path($source)), $matches, PREG_SET_ORDER);
            foreach ($matches as [, $key, $default]) {
                $this->assertSame($defaults[$key] ?? $default, $default, "{$key}: one default everywhere.");
                $defaults[$key] = $default;
            }
        }

        $seeded = DeliveryOperationsTranslationSeeder::TRANSLATIONS;
        $this->assertEqualsCanonicalizing(array_keys($seeded), array_keys($defaults));
        foreach ($seeded as $key => $values) {
            $this->assertSame(['ar', 'en'], array_keys($values), $key);
            $this->assertSame($defaults[$key], $values['en'], "{$key}: en is the page's default.");
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], "{$key}: ar is Arabic.");
        }
    }

    public function test_only_missing_rows_are_inserted_twice_safely_and_an_untouched_placeholder_is_repaired(): void
    {
        $key = 'dashboard.Delivery_Ops_Cancel_Reason';
        TranslationValue::query()->create(['key' => 'dashboard.Delivery_Ops_Cancel_Submit', 'locale' => 'ar', 'value' => 'نص معدّل']);
        // As t() leaves it when the page is opened in Arabic before the seeder runs.
        TranslationValue::query()->create(['key' => $key, 'locale' => 'ar', 'value' => DeliveryOperationsTranslationSeeder::TRANSLATIONS[$key]['en']]);

        $this->seed(DeliveryOperationsTranslationSeeder::class);
        $this->seed(DeliveryOperationsTranslationSeeder::class);

        $this->assertSame(count(DeliveryOperationsTranslationSeeder::TRANSLATIONS) * 2, TranslationValue::where('key', 'like', 'dashboard.Delivery_Ops_%')->count());
        $this->assertSame('نص معدّل', TranslationValue::where('key', 'dashboard.Delivery_Ops_Cancel_Submit')->where('locale', 'ar')->value('value'));
        $this->assertSame(DeliveryOperationsTranslationSeeder::TRANSLATIONS[$key]['ar'], TranslationValue::where('key', $key)->where('locale', 'ar')->value('value'));
        $this->assertStringNotContainsString('DeliveryOperationsTranslationSeeder', file_get_contents((new \ReflectionClass(DatabaseSeeder::class))->getFileName()));
    }
}
