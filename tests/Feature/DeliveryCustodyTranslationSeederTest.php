<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Language;
use App\Models\TranslationValue;
use Database\Seeders\DeliveryCustodyTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * VEN-BE-020 (Release A): DeliveryCustodyTranslationSeeder. Every key in Arabic and English, the two ability labels
 * as data/abilities.php uses them, insert-only (customised and edited rows kept, nothing deleted, no other key
 * touched), repeatable, stale cache cleared, not called from DatabaseSeeder. An Arabic placeholder auto-created by
 * the admins form before the seeder (the English default, never updated since) is repaired; nothing else is.
 */
class DeliveryCustodyTranslationSeederTest extends TestCase
{
    use RefreshDatabase;

    private const ABILITY_LABELS = ['dashboard.Record_Delivery_Failure', 'dashboard.Confirm_Delivery_Return'];

    public function test_58_keys_each_with_arabic_and_english(): void
    {
        $this->assertCount(58, DeliveryCustodyTranslationSeeder::TRANSLATIONS);

        foreach (DeliveryCustodyTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertMatchesRegularExpression('/^(dashboard|vendor)\.[A-Za-z_]+$/', $key);
            $this->assertSame(['ar', 'en'], array_keys($values), $key);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], $key);
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $values['en'], $key);
            $this->assertNotSame('', trim($values['en']), $key);
        }
    }

    public function test_the_ability_labels_are_seeded_as_data_abilities_uses_them(): void
    {
        $abilities = file_get_contents(base_path('data/abilities.php'));

        foreach (self::ABILITY_LABELS as $key) {
            $this->assertStringContainsString("t('{$key}', '".DeliveryCustodyTranslationSeeder::TRANSLATIONS[$key]['en']."')", $abilities, $key);
        }

        $this->seed(DeliveryCustodyTranslationSeeder::class);
        app()->setLocale('ar');
        $this->assertSame('تسجيل تعذّر التوصيل', app('abilities')['vendor-orders']['record-delivery-failure']);
        $this->assertSame('تأكيد استرجاع البضاعة', app('abilities')['vendor-orders']['confirm-return']);
    }

    public function test_a_new_database_gets_every_key_in_both_languages_and_nothing_else(): void
    {
        $this->seed(DeliveryCustodyTranslationSeeder::class);

        foreach (DeliveryCustodyTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame($values['ar'], $this->value($key, 'ar'), $key);
            $this->assertSame($values['en'], $this->value($key, 'en'), $key);
        }
        $this->assertSame(116, TranslationValue::count(), '58 keys x 2 languages.');
        $this->assertNoDuplicates();
    }

    public function test_running_twice_changes_nothing(): void
    {
        TranslationValue::create(['key' => 'dashboard.Record_Delivery_Failure', 'locale' => 'ar', 'value' => 'Record Delivery Failure']);
        $this->seed(DeliveryCustodyTranslationSeeder::class);
        $first = $this->rows();

        $this->travel(1)->minutes();
        $this->seed(DeliveryCustodyTranslationSeeder::class);

        $this->assertSame($first, $this->rows());
        $this->assertSame(116, TranslationValue::count());
    }

    public function test_customised_and_edited_rows_are_kept(): void
    {
        $kept = [
            ['dashboard.Custody_Status', 'ar', 'حيازة البضاعة'],
            ['dashboard.Custody_Status', 'en', 'Who holds the goods'],
            ['vendor.Order_Return_Pending', 'ar', 'RETURN PENDING'],
            ['vendor.Order_Return_Confirm', 'ar', 'Confirm goods received '],
        ];
        foreach ($kept as [$key, $locale, $value]) {
            TranslationValue::create(['key' => $key, 'locale' => $locale, 'value' => $value]);
        }
        // The exact English default, but saved again after it was created: an edit, so it is kept.
        $edited = TranslationValue::create(['key' => 'dashboard.Confirm_Delivery_Return', 'locale' => 'ar', 'value' => 'Confirm Returned Goods']);
        TranslationValue::whereKey($edited->id)->update(['updated_at' => $edited->created_at->copy()->addMinute()]);

        $this->seed(DeliveryCustodyTranslationSeeder::class);

        foreach ($kept as [$key, $locale, $value]) {
            $this->assertSame($value, $this->value($key, $locale), "{$key} [{$locale}]");
        }
        $this->assertSame('Confirm Returned Goods', $this->value('dashboard.Confirm_Delivery_Return', 'ar'));
        $this->assertSame('Return pending', $this->value('vendor.Order_Return_Pending', 'en'), 'A missing English row is inserted beside a custom Arabic one.');
        $this->assertSame(116, TranslationValue::count());
        $this->assertNoDuplicates();
    }

    public function test_an_exact_placeholder_is_repaired_and_its_cache_cleared(): void
    {
        foreach (self::ABILITY_LABELS as $key) {
            TranslationValue::create(['key' => $key, 'locale' => 'ar', 'value' => DeliveryCustodyTranslationSeeder::TRANSLATIONS[$key]['en']]);
            cache()->put("translation.ar.{$key}", DeliveryCustodyTranslationSeeder::TRANSLATIONS[$key]['en'], 60);
        }

        $this->seed(DeliveryCustodyTranslationSeeder::class);

        foreach (self::ABILITY_LABELS as $key) {
            $this->assertSame(DeliveryCustodyTranslationSeeder::TRANSLATIONS[$key]['ar'], $this->value($key, 'ar'), $key);
            $this->assertNull(cache()->get("translation.ar.{$key}"), "{$key}: the stale cache entry is cleared.");
        }
        $this->assertNoDuplicates();
    }

    public function test_the_admins_form_opened_in_arabic_before_the_seeder_is_repaired_by_it(): void
    {
        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        $super = Admin::factory()->create(['super_admin' => true, 'status' => 'active']);
        $form = fn () => $this->actingAs($super, 'admin')->withSession(['locale' => 'ar'])->get(route('dashboard.admins.create'))->assertOk()->getContent();

        // The root cause: t() (auto-create on) stores the English default as the Arabic row.
        $this->assertStringContainsString('Record Delivery Failure', $form());
        $placeholder = TranslationValue::where('key', 'dashboard.Record_Delivery_Failure')->where('locale', 'ar')->sole();
        $this->assertSame('Record Delivery Failure', $placeholder->value);

        $this->seed(DeliveryCustodyTranslationSeeder::class);

        $page = $form();
        $this->assertStringContainsString('تسجيل تعذّر التوصيل', $page);
        $this->assertStringContainsString('تأكيد استرجاع البضاعة', $page);
        $this->assertNoDuplicates();
    }

    public function test_other_keys_are_untouched_and_nothing_is_deleted(): void
    {
        $others = [
            ['dashboard.Assign_Delivery_Driver', 'ar', 'Assign Delivery Driver'],
            ['dashboard.Vendor_Order_Assignment_Not_Ready', 'ar', 'A driver can be assigned once the vendor marks this order ready for delivery.'],
            ['vendor.Order_Details_Conflict', 'en', 'Changed.'],
        ];
        foreach ($others as [$key, $locale, $value]) {
            TranslationValue::create(['key' => $key, 'locale' => $locale, 'value' => $value]);
        }
        $before = TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();

        $this->seed(DeliveryCustodyTranslationSeeder::class);

        $this->assertSame($before, TranslationValue::whereNotIn('key', array_keys(DeliveryCustodyTranslationSeeder::TRANSLATIONS))->orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
        $this->assertSame(count($others) + 116, TranslationValue::count());
    }

    public function test_t_serves_the_values_without_auto_create(): void
    {
        config(['palgoals-locale.auto_create' => false]);
        cache()->put('translation.ar.vendor.Order_Return_Attestation', 'stale', 60);

        $this->seed(DeliveryCustodyTranslationSeeder::class);

        app()->setLocale('ar');
        $this->assertSame('البضاعة موجودة فعليًا في حوزتنا في المتجر.', t('vendor.Order_Return_Attestation', 'fallback'));
        app()->setLocale('en');
        $this->assertSame('Driver unreachable', t('dashboard.Delivery_Failure_Kind_Unreachable', 'fallback'));
    }

    public function test_not_called_from_database_seeder(): void
    {
        $this->assertStringNotContainsString('DeliveryCustodyTranslationSeeder', file_get_contents(database_path('seeders/DatabaseSeeder.php')));
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(): array
    {
        return TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();
    }

    private function assertNoDuplicates(): void
    {
        $this->assertSame(0, TranslationValue::query()->select('key', 'locale')->groupBy('key', 'locale')->havingRaw('COUNT(*) > 1')->get()->count(), 'No duplicate (key, locale).');
    }

    private function value(string $key, string $locale): ?string
    {
        return TranslationValue::where('key', $key)->where('locale', $locale)->value('value');
    }
}
