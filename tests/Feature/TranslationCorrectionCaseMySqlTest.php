<?php

namespace Tests\Feature;

use App\Models\TranslationValue;
use Database\Seeders\AdminCategoryTranslationSeeder;
use Database\Seeders\AdminDashboardTranslationSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AD-UI-CAT-M3-FIX: the guarded translation corrections compare exactly. MySQL's collation is case-insensitive, so
 * a SQL "value = ?" would also replace an edit that differs only in case; SQLite compares case-sensitively and
 * cannot show the difference, hence this test on MySQL.
 *
 * Runs only against an isolated, throwaway MySQL database named hirfah_fk_test_* passed in
 * CATEGORY_FK_MYSQL_DATABASE (the variable of the other MySQL tests). Skipped otherwise.
 */
class TranslationCorrectionCaseMySqlTest extends TestCase
{
    private const CONNECTION = 'translation_case_mysql';

    private ?string $previousConnection = null;

    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) env('CATEGORY_FK_MYSQL_DATABASE', '');

        if ($database === '') {
            $this->markTestSkipped('Set CATEGORY_FK_MYSQL_DATABASE to an isolated MySQL database (hirfah_fk_test_*) to run.');
        }

        if (! str_starts_with($database, 'hirfah_fk_test_')) {
            $this->fail('Refusing to run: the database name must start with hirfah_fk_test_.');
        }

        config(['database.connections.'.self::CONNECTION => array_merge(config('database.connections.mysql'), ['database' => $database])]);
        DB::purge(self::CONNECTION);
        $this->assertSame($database, DB::connection(self::CONNECTION)->selectOne('SELECT DATABASE() AS d')->d);

        $this->artisan('migrate', ['--database' => self::CONNECTION, '--force' => true])->assertSuccessful();

        $this->previousConnection = DB::getDefaultConnection();
        DB::setDefaultConnection(self::CONNECTION);
        $this->clear();
    }

    protected function tearDown(): void
    {
        if ($this->previousConnection !== null) {
            $this->clear();
            DB::setDefaultConnection($this->previousConnection);
        }

        parent::tearDown();
    }

    public function test_the_collation_is_case_insensitive_here(): void
    {
        TranslationValue::create(['key' => 'dashboard.Nav_Media_Library', 'locale' => 'ar', 'value' => 'MEDIA LIBRARY']);

        $this->assertSame(1, TranslationValue::query()->where('value', 'Media Library')->count(), 'Precondition: a SQL "=" ignores case on this database.');
    }

    public function test_a_case_variant_of_the_auto_created_label_is_kept(): void
    {
        TranslationValue::create(['key' => 'dashboard.Nav_Media_Library', 'locale' => 'ar', 'value' => 'MEDIA LIBRARY']);

        $this->seed(AdminDashboardTranslationSeeder::class);

        $this->assertSame('MEDIA LIBRARY', $this->value('dashboard.Nav_Media_Library', 'ar'));
    }

    public function test_the_exact_auto_created_label_is_corrected(): void
    {
        TranslationValue::create(['key' => 'dashboard.Nav_Media_Library', 'locale' => 'ar', 'value' => 'Media Library']);

        $this->seed(AdminDashboardTranslationSeeder::class);

        $this->assertSame('مكتبة الوسائط', $this->value('dashboard.Nav_Media_Library', 'ar'));
    }

    public function test_a_case_variant_of_the_old_category_message_is_kept(): void
    {
        $old = collect(AdminCategoryTranslationSeeder::CORRECTIONS)->firstWhere('locale', 'en')['from'];
        TranslationValue::create(['key' => 'dashboard.Category_delete_has_products', 'locale' => 'en', 'value' => mb_strtoupper($old)]);

        $this->seed(AdminCategoryTranslationSeeder::class);

        $this->assertSame(mb_strtoupper($old), $this->value('dashboard.Category_delete_has_products', 'en'));
    }

    private function value(string $key, string $locale): ?string
    {
        return TranslationValue::query()->where('key', $key)->where('locale', $locale)->value('value');
    }

    private function clear(): void
    {
        DB::connection(self::CONNECTION)->table('translation_values')->delete();
    }
}
