<?php

namespace Tests\Feature;

use App\Models\TranslationValue;
use Database\Seeders\AdminContactMessageTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CONTACT-ADMIN-IMPLEMENTATION-01: AdminContactMessageTranslationSeeder. Exactly the keys the contact-message pages
 * and their controller read (CONTACT-ADMIN-DELETE-02 added 6, CONTACT-ADMIN-TRASH-02 20), Arabic and English,
 * English equal to the defaults in the code, edited values kept, nothing deleted, idempotent, stale cache cleared,
 * not called from DatabaseSeeder.
 */
class AdminContactMessageTranslationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_covers_exactly_the_contact_message_keys_the_code_reads_with_their_english_defaults(): void
    {
        $this->assertCount(46, AdminContactMessageTranslationSeeder::TRANSLATIONS);

        // The views read their keys through $msgText(), the controller (delete, restore and purge messages) through
        // $this->text(). CONTACT-ADMIN-TRASH-02 added the Trash list and the Inbox / Trash switcher.
        $sources = [
            resource_path('views/dashboard/contact-messages/index.blade.php'),
            resource_path('views/dashboard/contact-messages/show.blade.php'),
            resource_path('views/dashboard/contact-messages/trash.blade.php'),
            resource_path('views/dashboard/contact-messages/partials/switcher.blade.php'),
            app_path('Http/Controllers/Dashboard/ContactMessageManagementController.php'),
        ];
        $defaults = [];
        foreach ($sources as $source) {
            preg_match_all('/(?:\$msgText|\$this->text)\(\'(Contact_Messages_[A-Za-z_]+)\', \'([^\']+)\'\)/', file_get_contents($source), $matches, PREG_SET_ORDER);
            foreach ($matches as [, $key, $default]) {
                $defaults['dashboard.'.$key] = $default;
            }
        }

        ksort($defaults);
        $seeded = AdminContactMessageTranslationSeeder::TRANSLATIONS;
        ksort($seeded);
        $this->assertSame(array_keys($defaults), array_keys($seeded), 'The seeder covers exactly the keys the views and the controller read.');

        foreach ($seeded as $key => $values) {
            $this->assertSame(['ar', 'en'], array_keys(AdminContactMessageTranslationSeeder::TRANSLATIONS[$key]), $key);
            $this->assertSame($defaults[$key], $values['en'], "{$key} en is the default in the code.");
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $values['ar'], "{$key} ar must be Arabic.");
        }
    }

    public function test_every_key_is_inserted_in_both_languages(): void
    {
        $this->seed(AdminContactMessageTranslationSeeder::class);

        foreach (AdminContactMessageTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame($values['ar'], $this->value($key, 'ar'));
            $this->assertSame($values['en'], $this->value($key, 'en'));
        }
        $this->assertSame(92, TranslationValue::count());
    }

    public function test_edited_values_are_never_overwritten_and_nothing_is_deleted(): void
    {
        TranslationValue::create(['key' => 'dashboard.Contact_Messages_Status_Unread', 'locale' => 'ar', 'value' => 'غير مقروءة']);
        TranslationValue::create(['key' => 'dashboard.Something_else', 'locale' => 'ar', 'value' => 'قيمة أخرى']);

        $this->seed(AdminContactMessageTranslationSeeder::class);

        $this->assertSame('غير مقروءة', $this->value('dashboard.Contact_Messages_Status_Unread', 'ar'));
        $this->assertSame('New', $this->value('dashboard.Contact_Messages_Status_Unread', 'en'));
        $this->assertSame('قيمة أخرى', $this->value('dashboard.Something_else', 'ar'));
        $this->assertSame(92 + 1, TranslationValue::count());
    }

    public function test_running_twice_changes_nothing(): void
    {
        $this->seed(AdminContactMessageTranslationSeeder::class);
        $first = TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray();

        $this->travel(1)->minutes();
        $this->seed(AdminContactMessageTranslationSeeder::class);

        $this->assertSame($first, TranslationValue::orderBy('id')->get(['id', 'key', 'locale', 'value', 'updated_at'])->toArray());
    }

    public function test_t_serves_the_seeded_values_after_a_stale_cache_entry(): void
    {
        config(['palgoals-locale.auto_create' => false]);
        cache()->put('translation.ar.dashboard.Contact_Messages_Status_Read', 'stale', 60);

        $this->seed(AdminContactMessageTranslationSeeder::class);

        app()->setLocale('ar');
        $this->assertSame('مقروءة', t('dashboard.Contact_Messages_Status_Read', 'fallback'));
        app()->setLocale('en');
        $this->assertSame('Back to contact messages', t('dashboard.Contact_Messages_Show_Back', 'fallback'));
    }

    public function test_not_called_from_database_seeder(): void
    {
        $this->assertStringNotContainsString('AdminContactMessageTranslationSeeder', file_get_contents(database_path('seeders/DatabaseSeeder.php')));
    }

    private function value(string $key, string $locale): ?string
    {
        return TranslationValue::where('key', $key)->where('locale', $locale)->value('value');
    }
}
