<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * SITE-AVAILABILITY-MIGRATION-FIX-01: MySQL path of 2026_10_20_000000_add_site_availability_to_settings_table. Exact
 * columns, the named CHECK comparing bytes (rejects case variants and trailing spaces), the foreign key ON DELETE SET
 * NULL, one ALTER TABLE for up() and for down(), a no-op on the applied schema, and every partial state refused by up()
 * and cleared by down() without touching anything else.
 *
 * Runs only against an isolated, throwaway MySQL database named hirfah_fk_test_* passed in CATEGORY_FK_MYSQL_DATABASE
 * (the project's MySQL test variable; connection settings otherwise come from the mysql connection, whose database is
 * never used). Skipped otherwise, so the regular suite and the development database are never touched.
 */
class SiteAvailabilityMigrationMySqlTest extends TestCase
{
    private const CONNECTION = 'site_availability_mysql';

    private const MIGRATION = 'migrations/2026_10_20_000000_add_site_availability_to_settings_table.php';

    private const PROTECTED = ['multi_vendor_commerce', 'hirfah_fk_test_ordbe001', 'hirfah_site_availability_test'];

    private ?string $previousConnection = null;

    private string $database = '';

    /** @var list<string> */
    private array $alters = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = (string) env('CATEGORY_FK_MYSQL_DATABASE', '');

        if ($this->database === '') {
            $this->markTestSkipped('Set CATEGORY_FK_MYSQL_DATABASE to an isolated MySQL database (hirfah_fk_test_*) to run.');
        }

        if (! str_starts_with($this->database, 'hirfah_fk_test_') || in_array($this->database, self::PROTECTED, true)) {
            $this->fail('Refusing to run: the database must be a new hirfah_fk_test_* database, never a protected one.');
        }

        config(['database.connections.'.self::CONNECTION => array_merge(config('database.connections.mysql'), ['url' => null, 'database' => $this->database])]);
        DB::purge(self::CONNECTION);
        $this->assertSame($this->database, DB::connection(self::CONNECTION)->selectOne('SELECT DATABASE() AS d')->d);

        $this->artisan('migrate', ['--database' => self::CONNECTION, '--force' => true])->assertSuccessful();

        $this->previousConnection = DB::getDefaultConnection();
        DB::setDefaultConnection(self::CONNECTION);
        $this->assertSame($this->database, DB::selectOne('SELECT DATABASE() AS d')->d, 'The default connection is the isolated database.');

        DB::connection(self::CONNECTION)->listen(function ($query) {
            if (str_starts_with(strtoupper(ltrim($query->sql)), 'ALTER TABLE')) {
                $this->alters[] = $query->sql;
            }
        });
        $this->resetRows();
    }

    protected function tearDown(): void
    {
        if ($this->previousConnection !== null) {
            $this->migration()->down(); // clears any partial state a test left
            $this->migration()->up(); // leave the isolated database migrated
            $this->resetRows();
            DB::setDefaultConnection($this->previousConnection);
        }

        parent::tearDown();
    }

    public function test_the_applied_schema_is_exactly_as_designed(): void
    {
        $columns = collect(DB::select("SELECT COLUMN_NAME n, COLUMN_TYPE t, IS_NULLABLE nl, COLUMN_DEFAULT d FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME LIKE 'site_availability%'"))->keyBy('n');

        $this->assertSame(['varchar(20)', 'NO', 'open'], [$columns['site_availability_mode']->t, $columns['site_availability_mode']->nl, $columns['site_availability_mode']->d]);
        $this->assertSame(['timestamp', 'YES', null], [$columns['site_availability_changed_at']->t, $columns['site_availability_changed_at']->nl, $columns['site_availability_changed_at']->d]);
        $this->assertSame(['bigint unsigned', 'YES', null], [$columns['site_availability_changed_by']->t, $columns['site_availability_changed_by']->nl, $columns['site_availability_changed_by']->d]);

        $check = DB::selectOne("SELECT c.CHECK_CLAUSE clause, t.ENFORCED enforced FROM information_schema.CHECK_CONSTRAINTS c JOIN information_schema.TABLE_CONSTRAINTS t ON t.CONSTRAINT_SCHEMA = c.CONSTRAINT_SCHEMA AND t.CONSTRAINT_NAME = c.CONSTRAINT_NAME WHERE c.CONSTRAINT_SCHEMA = DATABASE() AND c.CONSTRAINT_NAME = 'settings_site_availability_mode_check'");
        $this->assertNotNull($check);
        $this->assertSame('YES', $check->enforced);
        $this->assertStringContainsStringIgnoringCase('binary', $check->clause, 'The CHECK compares bytes.');

        $key = DB::selectOne("SELECT k.CONSTRAINT_NAME n, k.REFERENCED_TABLE_NAME t, k.REFERENCED_COLUMN_NAME c, r.DELETE_RULE d FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = 'settings' AND k.COLUMN_NAME = 'site_availability_changed_by'");
        $this->assertSame(['settings_site_availability_changed_by_foreign', 'admins', 'id', 'SET NULL'], [$key->n, $key->t, $key->c, $key->d]);
    }

    public function test_only_the_exact_values_are_accepted(): void
    {
        $this->settingsRow();

        foreach (['open', 'coming_soon', 'maintenance'] as $state) {
            DB::table('settings')->update(['site_availability_mode' => $state]);
            $this->assertSame($state, DB::table('settings')->value('site_availability_mode'));
        }

        foreach (['OPEN', 'Open', 'MAINTENANCE', 'maintenance ', ' maintenance', "maintenance\t", 'coming soon', 'coming-soon', '', 'closed'] as $invalid) {
            try {
                DB::table('settings')->update(['site_availability_mode' => $invalid]);
                $this->fail(json_encode($invalid).' was accepted.');
            } catch (QueryException $e) {
                $this->assertSame(3819, $e->errorInfo[1] ?? null, json_encode($invalid).' is rejected by the CHECK.');
            }
            $this->assertSame('maintenance', DB::table('settings')->value('site_availability_mode'), 'No silent normalization.');
        }
    }

    public function test_an_existing_row_gets_open_and_the_admin_reference_is_cleared_on_delete(): void
    {
        $this->migration()->down();
        $this->settingsRow();
        $this->migration()->up();
        $this->assertSame('open', DB::table('settings')->value('site_availability_mode'));
        $this->assertSame('Verify', DB::table('settings')->value('site_name'));

        $admin = $this->admin();
        DB::table('settings')->update(['site_availability_mode' => 'coming_soon', 'site_availability_changed_by' => $admin, 'site_availability_changed_at' => now()]);

        try {
            DB::table('settings')->update(['site_availability_changed_by' => 999999]);
            $this->fail('An unknown admin was accepted.');
        } catch (QueryException $e) {
            $this->assertSame(1452, $e->errorInfo[1] ?? null);
        }

        DB::table('admins')->where('id', $admin)->delete();
        $this->assertNull(DB::table('settings')->value('site_availability_changed_by'));
        $this->assertSame('coming_soon', DB::table('settings')->value('site_availability_mode'));
        $this->assertNotNull(DB::table('settings')->value('site_availability_changed_at'));
    }

    public function test_up_and_down_are_each_one_alter_and_up_on_the_applied_schema_is_a_no_op(): void
    {
        $this->alters = [];
        $this->migration()->up();
        $this->assertSame([], $this->alters, 'Applied schema: up() changes nothing.');

        $this->migration()->down();
        $this->assertCount(1, $this->alters, implode(' | ', $this->alters));
        $this->assertSame([], $this->ownedObjects());

        $this->alters = [];
        $this->migration()->up();
        $this->assertCount(1, $this->alters, implode(' | ', $this->alters));
        $this->assertCount(6, $this->ownedObjects(), 'Check, key, index and three columns.');

        $this->alters = [];
        $this->migration()->down();
        $this->migration()->down();
        $this->assertCount(1, $this->alters, 'A second down() on a clean schema issues nothing.');
    }

    public function test_up_refuses_each_partial_state_and_down_clears_it_without_touching_anything_else(): void
    {
        $this->settingsRow();
        $baselineColumns = $this->settingsColumns();
        $this->migration()->down();
        $cleanColumns = $this->settingsColumns();
        $otherConstraints = $this->otherConstraints();

        $partials = [
            'changed_at only' => ['ALTER TABLE settings ADD COLUMN site_availability_changed_at TIMESTAMP NULL'],
            'changed_by with its key' => [
                'ALTER TABLE settings ADD COLUMN site_availability_changed_by BIGINT UNSIGNED NULL, ADD CONSTRAINT settings_site_availability_changed_by_foreign FOREIGN KEY (site_availability_changed_by) REFERENCES admins (id) ON DELETE SET NULL',
            ],
            'mode with its check' => [
                "ALTER TABLE settings ADD COLUMN site_availability_mode VARCHAR(20) NOT NULL DEFAULT 'open', ADD CONSTRAINT settings_site_availability_mode_check CHECK (CAST(site_availability_mode AS BINARY) IN ('open', 'coming_soon', 'maintenance'))",
            ],
            'all columns, no check' => [
                "ALTER TABLE settings ADD COLUMN site_availability_mode VARCHAR(20) NOT NULL DEFAULT 'open', ADD COLUMN site_availability_changed_at TIMESTAMP NULL, ADD COLUMN site_availability_changed_by BIGINT UNSIGNED NULL",
            ],
            'orphan index of the key' => [
                'ALTER TABLE settings ADD COLUMN site_availability_changed_by BIGINT UNSIGNED NULL, ADD INDEX settings_site_availability_changed_by_foreign (site_availability_changed_by)',
            ],
        ];

        foreach ($partials as $label => $statements) {
            foreach ($statements as $sql) {
                DB::statement($sql);
            }
            $partial = [$this->settingsColumns(), $this->ownedObjects()];

            $this->alters = [];
            try {
                $this->migration()->up();
                // "all columns, no check" has the full column set: up() verifies the target state and refuses it.
                $this->fail("up() must refuse: {$label}.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Nothing was changed.', $e->getMessage(), $label);
            }
            $this->assertSame([], $this->alters, "{$label}: up() issued no ALTER.");
            $this->assertSame($partial, [$this->settingsColumns(), $this->ownedObjects()], "{$label}: nothing changed.");

            $this->migration()->down();
            $this->assertSame($cleanColumns, $this->settingsColumns(), "{$label}: down() removed only the owned columns.");
            $this->assertSame([], $this->ownedObjects(), "{$label}: down() removed the owned constraints and index.");
            $this->assertSame($otherConstraints, $this->otherConstraints(), "{$label}: unrelated constraints kept.");
            $this->assertSame('Verify', DB::table('settings')->value('site_name'), "{$label}: data kept.");
        }

        $this->migration()->up();
        $this->assertSame($baselineColumns, $this->settingsColumns());
        $this->assertSame('open', DB::table('settings')->value('site_availability_mode'));
    }

    private function migration(): object
    {
        return require database_path(self::MIGRATION);
    }

    /** @return list<string> settings columns, in order */
    private function settingsColumns(): array
    {
        return array_column(DB::select("SELECT COLUMN_NAME n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' ORDER BY ORDINAL_POSITION"), 'n');
    }

    /** @return list<string> constraints and indexes this migration owns that currently exist */
    private function ownedObjects(): array
    {
        $constraints = array_column(DB::select("SELECT CONCAT(CONSTRAINT_TYPE, ':', CONSTRAINT_NAME) x FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND CONSTRAINT_NAME LIKE 'settings_site_availability%' ORDER BY x"), 'x');
        $indexes = array_column(DB::select("SELECT DISTINCT CONCAT('INDEX:', INDEX_NAME) x FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND INDEX_NAME LIKE 'settings_site_availability%' ORDER BY x"), 'x');
        $columns = array_values(array_filter($this->settingsColumns(), fn ($column) => str_starts_with($column, 'site_availability')));

        return [...$constraints, ...$indexes, ...array_map(fn ($c) => 'COLUMN:'.$c, $columns)];
    }

    /** @return list<string> */
    private function otherConstraints(): array
    {
        return array_column(DB::select("SELECT CONSTRAINT_NAME n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND CONSTRAINT_NAME NOT LIKE 'settings_site_availability%' ORDER BY n"), 'n');
    }

    private function settingsRow(): void
    {
        if (! DB::table('settings')->exists()) {
            DB::table('settings')->insert(['singleton_key' => 1, 'site_name' => 'Verify', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function admin(): int
    {
        return DB::table('admins')->insertGetId(['name' => 'Verify', 'email' => 'verify'.uniqid().'@example.test', 'phone' => '05'.random_int(10000000, 99999999), 'password' => 'x', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Isolated database only: empties the two tables this test writes. */
    private function resetRows(): void
    {
        DB::table('settings')->delete();
        DB::table('admins')->delete();
    }
}
