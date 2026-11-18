<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * H4: MySQL path of 2026_11_16_000001_backfill_city_governorates_from_legacy_links. What SQLite cannot show: the
 * REPEATABLE READ transaction is forced and the session's level restored; while the backfill holds its locks, a second
 * connection cannot insert or update cities, governorates or the record (gap and row locks, lock wait timeout); a lock
 * held elsewhere makes the backfill stop with nothing written; a real deadlock (with a second PHP process) ends with
 * one side rolled back and consistent data; a failure part way through (a trigger) rolls everything back.
 *
 * Runs only against an isolated, throwaway MySQL database named hirfah_fk_test_* passed in
 * CATEGORY_FK_MYSQL_DATABASE (connection settings otherwise come from the mysql connection). Skipped otherwise, so
 * the regular suite and the development database are never touched.
 */
class LocationHierarchyBackfillMySqlTest extends TestCase
{
    private const CONNECTION = 'location_backfill_mysql';

    private const CONTENDER = 'location_backfill_mysql_contender';

    private const BACKFILL = 'migrations/2026_11_16_000001_backfill_city_governorates_from_legacy_links.php';

    private const LOCK_WAIT_TIMEOUT = 1205;

    private const DEADLOCK = 1213;

    private string $database = '';

    private ?string $previousConnection = null;

    /** @var list<resource> */
    private array $processes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = (string) env('CATEGORY_FK_MYSQL_DATABASE', '');

        if ($this->database === '') {
            $this->markTestSkipped('Set CATEGORY_FK_MYSQL_DATABASE to an isolated MySQL database (hirfah_fk_test_*) to run.');
        }

        if (! str_starts_with($this->database, 'hirfah_fk_test_')) {
            $this->fail('Refusing to run: the database name must start with hirfah_fk_test_.');
        }

        foreach ([self::CONNECTION, self::CONTENDER] as $name) {
            config(['database.connections.'.$name => array_merge(config('database.connections.mysql'), ['database' => $this->database])]);
            DB::purge($name);
            $this->assertSame($this->database, DB::connection($name)->selectOne('SELECT DATABASE() AS d')->d);
        }

        // Leftovers of an interrupted run would make the backfill (run by migrate) stop: cleared first.
        if (DB::connection(self::CONNECTION)->getSchemaBuilder()->hasTable('governorates')) {
            $this->clearLocations();
        }
        $this->artisan('migrate', ['--database' => self::CONNECTION, '--force' => true])->assertSuccessful();

        $this->previousConnection = DB::getDefaultConnection();
        DB::setDefaultConnection(self::CONNECTION);
        $this->clearLocations();
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }

        if ($this->previousConnection !== null) {
            $this->db()->unprepared('DROP TRIGGER IF EXISTS h4_fail_second_backfill');
            $this->clearLocations();
            DB::setDefaultConnection($this->previousConnection);
        }

        parent::tearDown();
    }

    public function test_the_backfill_and_its_rollback_on_mysql(): void
    {
        $gaza = $this->governorate('Gaza');
        $north = $this->governorate('North');
        $kept = $this->city('Rafah', $gaza);
        $first = $this->city('Gaza city');
        $this->claim($gaza, $first);
        $second = $this->city('Jabalia');
        $this->claim($north, $second);
        $orphan = $this->city('Orphan');
        $governorates = $this->governorates();

        $this->backfill()->up();
        $this->assertSame([$kept => $gaza, $first => $gaza, $second => $north, $orphan => null], $this->cities());
        $this->assertSame($governorates, $this->governorates());
        $this->assertSame([$first => $gaza, $second => $north], $this->db()->table('location_hierarchy_backfills')->orderBy('city_id')->pluck('assigned_governorate_id', 'city_id')->map(fn ($id) => (int) $id)->all());

        $this->backfill()->up(); // harmless
        $this->backfill()->down();
        $this->assertSame([$kept => $gaza, $first => null, $second => null, $orphan => null], $this->cities());
        $this->assertSame(0, $this->db()->table('location_hierarchy_backfills')->count());
    }

    public function test_ambiguity_and_conflicts_stop_without_writing_on_mysql(): void
    {
        $first = $this->governorate('First');
        $second = $this->governorate('Second');
        $claimed = $this->city('Claimed twice');
        $this->claim($first, $claimed);
        $this->claim($second, $claimed);
        $owner = $this->governorate('Owner');
        $conflict = $this->city('Conflict', $owner);
        $this->claim($this->governorate('Claimant'), $conflict);
        $this->claim($this->governorate('Valid'), $valid = $this->city('Valid B'));
        $before = [$this->cities(), $this->governorates()];

        try {
            $this->backfill()->up();
            $this->fail('The backfill should stop.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString("city {$claimed} claimed by governorates {$first}, {$second}", $exception->getMessage());
            $this->assertStringContainsString("city {$conflict} belongs to governorate {$owner}", $exception->getMessage());
        }

        $this->assertSame($before, [$this->cities(), $this->governorates()]);
        $this->assertNull($this->db()->table('cities')->where('id', $valid)->value('governorate_id'));
        $this->assertSame(0, $this->db()->table('location_hierarchy_backfills')->count());
        $this->assertSame(0, $this->db()->transactionLevel());
    }

    public function test_repeatable_read_is_forced_for_the_transaction_and_the_session_level_restored(): void
    {
        $this->claim($this->governorate('Gaza'), $this->city('Gaza city'));
        $this->db()->statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $seen = null;

        $backfill = $this->backfill();
        $backfill->afterLocking = function () use (&$seen) {
            $seen = $this->db()->selectOne('SELECT @@SESSION.transaction_isolation AS level')->level;
        };
        $backfill->up();

        $this->assertSame('REPEATABLE-READ', $seen);
        $this->assertSame('READ-COMMITTED', $this->db()->selectOne('SELECT @@SESSION.transaction_isolation AS level')->level, 'The session level is restored.');
        $this->db()->statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    }

    public function test_concurrent_inserts_and_updates_of_the_hierarchy_wait_for_the_backfill(): void
    {
        $gaza = $this->governorate('Gaza');
        $legacy = $this->city('Gaza city');
        $this->claim($gaza, $legacy);
        $orphan = $this->city('Orphan');
        $contender = DB::connection(self::CONTENDER);
        $contender->statement('SET SESSION innodb_lock_wait_timeout = 1');
        $outcomes = [];

        $backfill = $this->backfill();
        $backfill->afterLocking = function () use ($contender, $gaza, $legacy, $orphan, &$outcomes) {
            $attempts = [
                // Gap locks: new rows in each locked table (a city with no governorate needs no parent check).
                'insert a city' => fn () => $contender->table('cities')->insert(['name' => 'Inserted', 'slug' => 'h4-inserted-'.Str::random(6), 'status' => 'active']),
                'insert a governorate' => fn () => $contender->table('governorates')->insert(['name' => 'Inserted', 'slug' => 'h4-inserted-'.Str::random(6), 'status' => 'active']),
                'insert a record row' => fn () => $contender->table('location_hierarchy_backfills')->insert(['city_id' => 999999, 'legacy_governorate_id' => 1, 'assigned_governorate_id' => 1]),
                // Row locks: the claim, the city about to be linked, and an untouched city.
                'move the legacy claim' => fn () => $contender->table('governorates')->where('id', $gaza)->update(['city_id' => $orphan]),
                'link the legacy city elsewhere' => fn () => $contender->table('cities')->where('id', $legacy)->update(['governorate_id' => $gaza]),
                'rename an untouched city' => fn () => $contender->table('cities')->where('id', $orphan)->update(['name' => 'Renamed']),
            ];
            foreach ($attempts as $label => $attempt) {
                try {
                    $attempt();
                    $outcomes[$label] = 'went through';
                } catch (QueryException $exception) {
                    $outcomes[$label] = $exception->errorInfo[1] ?? $exception->getCode();
                }
            }
        };
        $backfill->up();

        $this->assertSame(array_fill_keys(array_keys($outcomes), self::LOCK_WAIT_TIMEOUT), $outcomes, 'Every concurrent write waited and timed out while the locks were held.');
        $this->assertCount(6, $outcomes);
        // After the commit the same writes go through, and the backfill's result is as expected.
        $this->assertSame([$legacy => $gaza, $orphan => null], $this->cities());
        $contender->table('cities')->insert(['name' => 'After', 'slug' => 'h4-after-'.Str::random(6), 'status' => 'active']);
        $this->assertSame(3, $this->db()->table('cities')->count());
    }

    public function test_a_lock_held_elsewhere_stops_the_backfill_with_nothing_written_and_a_re_run_completes(): void
    {
        $gaza = $this->governorate('Gaza');
        $legacy = $this->city('Gaza city');
        $this->claim($gaza, $legacy);
        $contender = DB::connection(self::CONTENDER);
        $contender->beginTransaction();
        $contender->table('cities')->where('id', $legacy)->lockForUpdate()->get();
        $this->db()->statement('SET SESSION innodb_lock_wait_timeout = 1');

        try {
            $this->backfill()->up();
            $this->fail('The backfill should time out on the held lock.');
        } catch (QueryException $exception) {
            $this->assertSame(self::LOCK_WAIT_TIMEOUT, $exception->errorInfo[1]);
        } finally {
            $contender->rollBack();
        }

        $this->assertSame([$legacy => null], $this->cities());
        $this->assertSame(0, $this->db()->table('location_hierarchy_backfills')->count());
        $this->assertSame(0, $this->db()->transactionLevel());

        $this->db()->statement('SET SESSION innodb_lock_wait_timeout = 50');
        $this->backfill()->up();
        $this->assertSame([$legacy => $gaza], $this->cities());
    }

    public function test_a_deadlock_with_another_process_leaves_consistent_data(): void
    {
        $first = $this->governorate('First');
        $second = $this->governorate('Second');
        $legacy = $this->city('Gaza city');
        $this->claim($second, $legacy);
        $this->db()->statement('SET SESSION innodb_lock_wait_timeout = 20');

        // The other process locks the second governorate, waits until the backfill is blocked on it (holding the first),
        // then asks for the first: InnoDB detects the deadlock and rolls one side back. Coordination through a file and
        // INNODB_TRX, not timing (a pipe may deliver its line only when the process ends).
        $signal = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hirfah-h4-'.Str::random(12);
        [$process, $pipes] = $this->startContender($first, $second, $signal);
        $deadline = microtime(true) + 20;
        while (! is_file($signal) && microtime(true) < $deadline) {
            usleep(20_000);
        }
        $this->assertFileExists($signal, 'The other process did not take its lock.');
        @unlink($signal);

        $backfillError = null;
        try {
            $this->backfill()->up();
        } catch (QueryException $exception) {
            $backfillError = $exception->errorInfo[1] ?? null;
        }
        $contender = json_decode(trim((string) stream_get_contents($pipes[1])), true);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $this->processes = [];
        $this->assertIsArray($contender, 'Other process: '.$errors);

        $this->assertTrue($contender['waited_for_the_backfill'], 'The other process saw the backfill blocked on its lock: a real deadlock.');
        if ($backfillError !== null) {
            // The backfill was the victim: nothing of it remains.
            $this->assertSame(self::DEADLOCK, $backfillError);
            $this->assertSame('committed', $contender['outcome']);
            $this->assertSame([$legacy => null], $this->cities());
            $this->assertSame(0, $this->db()->table('location_hierarchy_backfills')->count());
        } else {
            // The other process was the victim: the backfill completed.
            $this->assertSame(['outcome' => 'error', 'code' => self::DEADLOCK, 'waited_for_the_backfill' => true], $contender);
            $this->assertSame([$legacy => $second], $this->cities());
            $this->assertSame(1, $this->db()->table('location_hierarchy_backfills')->count());
        }
        $this->assertSame(0, $this->db()->transactionLevel());
    }

    public function test_a_failure_part_way_through_rolls_back_everything_on_mysql(): void
    {
        $gaza = $this->governorate('Gaza');
        $north = $this->governorate('North');
        $first = $this->city('First');
        $this->claim($gaza, $first);
        $second = $this->city('Second');
        $this->claim($north, $second);

        try {
            $this->db()->unprepared("CREATE TRIGGER h4_fail_second_backfill BEFORE UPDATE ON cities FOR EACH ROW BEGIN IF NEW.id = {$second} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced failure'; END IF; END");
        } catch (QueryException $exception) {
            $this->markTestSkipped('The isolated database user cannot create triggers: '.$exception->getMessage());
        }

        try {
            $this->backfill()->up();
            $this->fail('The forced failure should stop the backfill.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('forced failure', $exception->getMessage());
        }

        $this->assertSame([$first => null, $second => null], $this->cities(), 'The first city is not left linked.');
        $this->assertSame(0, $this->db()->table('location_hierarchy_backfills')->count());
        $this->assertSame(0, $this->db()->transactionLevel());
    }

    public function test_rollback_refuses_a_later_manual_edit_on_mysql(): void
    {
        $gaza = $this->governorate('Gaza');
        $north = $this->governorate('North');
        $edited = $this->city('Edited');
        $this->claim($gaza, $edited);
        $other = $this->city('Other');
        $this->claim($north, $other);
        $this->backfill()->up();
        $this->db()->table('cities')->where('id', $edited)->update(['governorate_id' => $north]);

        try {
            $this->backfill()->down();
            $this->fail('The rollback should refuse.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString("city {$edited} now belongs to governorate {$north} instead of {$gaza}", $exception->getMessage());
        }

        $this->assertSame([$edited => $north, $other => $north], $this->cities());
        $this->assertSame(2, $this->db()->table('location_hierarchy_backfills')->count());
    }

    public function test_the_backfill_refuses_to_start_inside_an_open_transaction(): void
    {
        $this->claim($this->governorate('Gaza'), $legacy = $this->city('Gaza city'));
        $this->db()->beginTransaction();

        try {
            $this->backfill()->up();
            $this->fail('The backfill should refuse an open transaction.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('must start outside a transaction', $exception->getMessage());
        } finally {
            $this->db()->rollBack();
        }

        $this->assertSame([$legacy => null], $this->cities());
    }

    // ---- helpers

    /** @return array{0: resource, 1: array<int, resource>} */
    private function startContender(int $first, int $second, string $signal): array
    {
        $connection = config('database.connections.'.self::CONNECTION);
        $code = <<<'PHP'
            $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s', getenv('H4_HOST'), getenv('H4_PORT'), getenv('H4_DATABASE')), getenv('H4_USERNAME'), getenv('H4_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('SET SESSION innodb_lock_wait_timeout = 20');
            $own = (int) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
            $pdo->beginTransaction();
            $pdo->query('SELECT id FROM governorates WHERE id = '.(int) getenv('H4_SECOND').' FOR UPDATE')->fetchAll();
            file_put_contents(getenv('H4_SIGNAL'), 'locked');
            $deadline = microtime(true) + 20;
            do {
                usleep(20000);
                $waiting = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_state = 'LOCK WAIT' AND trx_mysql_thread_id <> {$own}")->fetchColumn();
            } while ($waiting === 0 && microtime(true) < $deadline);
            try {
                $pdo->query('SELECT id FROM governorates WHERE id = '.(int) getenv('H4_FIRST').' FOR UPDATE')->fetchAll();
                $pdo->commit();
                echo json_encode(['outcome' => 'committed', 'waited_for_the_backfill' => $waiting > 0]);
            } catch (PDOException $exception) {
                echo json_encode(['outcome' => 'error', 'code' => $exception->errorInfo[1] ?? null, 'waited_for_the_backfill' => $waiting > 0]);
            }
            PHP;

        $environment = array_merge(getenv(), [
            'H4_HOST' => (string) $connection['host'], 'H4_PORT' => (string) $connection['port'], 'H4_DATABASE' => $this->database,
            'H4_USERNAME' => (string) $connection['username'], 'H4_PASSWORD' => (string) $connection['password'],
            'H4_FIRST' => (string) $first, 'H4_SECOND' => (string) $second, 'H4_SIGNAL' => $signal,
        ]);
        $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $environment);
        $this->assertIsResource($process, 'Could not start the other process.');
        $this->processes[] = $process;

        return [$process, $pipes];
    }

    private function backfill(): object
    {
        return require database_path(self::BACKFILL);
    }

    private function governorate(string $name): int
    {
        return $this->db()->table('governorates')->insertGetId(['name' => $name, 'slug' => 'h4-g-'.Str::random(8), 'status' => 'active']);
    }

    private function city(string $name, ?int $governorateId = null): int
    {
        return $this->db()->table('cities')->insertGetId(['governorate_id' => $governorateId, 'name' => $name, 'slug' => 'h4-c-'.Str::random(8), 'status' => 'active']);
    }

    private function claim(int $governorateId, int $cityId): void
    {
        $this->db()->table('governorates')->where('id', $governorateId)->update(['city_id' => $cityId]);
    }

    /** @return array<int, int|null> */
    private function cities(): array
    {
        return $this->db()->table('cities')->orderBy('id')->pluck('governorate_id', 'id')->map(fn ($id) => $id === null ? null : (int) $id)->all();
    }

    /** @return array<int, int|null> */
    private function governorates(): array
    {
        return $this->db()->table('governorates')->orderBy('id')->pluck('city_id', 'id')->map(fn ($id) => $id === null ? null : (int) $id)->all();
    }

    /** Clears the location rows of the isolated database (the references to them are set to null by their keys). */
    private function clearLocations(): void
    {
        $db = DB::connection(self::CONNECTION);
        if ($db->getSchemaBuilder()->hasTable('location_hierarchy_backfills')) {
            $db->table('location_hierarchy_backfills')->delete();
        }
        // Before migrating, an older isolated database may not have the legacy column yet.
        if ($db->getSchemaBuilder()->hasColumn('governorates', 'city_id')) {
            $db->table('governorates')->update(['city_id' => null]);
        }
        $db->table('cities')->delete();
        $db->table('governorates')->delete();
    }

    private function db()
    {
        return DB::connection(self::CONNECTION);
    }
}
