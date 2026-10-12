<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * DEC-CAT-Q13 (AD-UI-CAT-01C-2C): two concurrent category requests on MySQL can never create a cycle or a
 * third level. Each request runs in its own PHP process (tests/Support/category-hierarchy-worker.php) with its
 * own MySQL connection, through the real CategoryManagementController as a super admin.
 *
 * Coordination, not timing: the holder pauses inside its transaction after locking; the contender starts only
 * then, and the test waits until information_schema.INNODB_TRX shows the contender's connection in LOCK WAIT
 * before releasing the holder.
 *
 * Runs only against an isolated, throwaway MySQL database named hirfah_fk_test_* passed in
 * CATEGORY_FK_MYSQL_DATABASE (the same variable as CategoryForeignKeyRestrictMySqlTest). Skipped otherwise.
 */
class CategoryHierarchyConcurrencyMySqlTest extends TestCase
{
    private const CONNECTION = 'category_hierarchy_mysql';

    private const WORKER = 'tests/Support/category-hierarchy-worker.php';

    private const WAIT_SECONDS = 30;

    private const NOT_ROOT = 'Choose a main category as the parent. Subcategories cannot have subcategories of their own.';

    private const HAS_CHILDREN = 'This category has subcategories, so it cannot be moved under another category.';

    private string $database = '';

    private string $signals = '';

    private ?Admin $admin = null;

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

        config(['database.connections.'.self::CONNECTION => array_merge(config('database.connections.mysql'), ['database' => $this->database])]);
        DB::purge(self::CONNECTION);
        $this->assertSame($this->database, $this->db()->selectOne('SELECT DATABASE() AS d')->d);

        $this->artisan('migrate', ['--database' => self::CONNECTION, '--force' => true])->assertSuccessful();

        $this->clearCategories();
        $this->admin = (new Admin)->setConnection(self::CONNECTION);
        $this->admin->fill(['name' => 'Concurrency', 'email' => 'concurrency-'.Str::random(8).'@example.test', 'phone' => '05'.random_int(10000000, 99999999), 'password' => 'unused', 'status' => 'active', 'super_admin' => true])->save();

        $this->signals = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hirfah-hierarchy-'.Str::random(12);
        mkdir($this->signals);
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            if (is_resource($process)) {
                @touch($this->signals.'/release');
                proc_terminate($process);
                proc_close($process);
            }
        }

        if ($this->database !== '' && $this->admin) {
            $this->clearCategories();
            $this->db()->table('admins')->where('id', $this->admin->id)->delete();
        }

        if ($this->signals !== '' && is_dir($this->signals)) {
            array_map('unlink', glob($this->signals.'/*') ?: []);
            rmdir($this->signals);
        }

        parent::tearDown();
    }

    public function test_moving_a_under_b_while_b_moves_under_a_creates_no_cycle(): void
    {
        [$holder, $contender, $lockWait] = $this->race('move:A:B', 'move:B:A');

        $this->assertRace($holder, $contender, $lockWait, self::NOT_ROOT);
        $this->assertTree(['A' => 'B', 'B' => null]);
    }

    public function test_the_same_race_in_the_opposite_order_creates_no_cycle(): void
    {
        [$holder, $contender, $lockWait] = $this->race('move:B:A', 'move:A:B');

        $this->assertRace($holder, $contender, $lockWait, self::NOT_ROOT);
        $this->assertTree(['A' => null, 'B' => 'A']);
    }

    public function test_creating_c_under_a_while_a_moves_under_b_creates_no_third_level(): void
    {
        [$holder, $contender, $lockWait] = $this->race('move:A:B', 'create:C:A');

        $this->assertRace($holder, $contender, $lockWait, self::NOT_ROOT);
        $this->assertTree(['A' => 'B', 'B' => null]);
    }

    public function test_moving_a_under_b_while_c_is_created_under_a_creates_no_third_level(): void
    {
        [$holder, $contender, $lockWait] = $this->race('create:C:A', 'move:A:B');

        $this->assertRace($holder, $contender, $lockWait, self::HAS_CHILDREN);
        $this->assertTree(['A' => null, 'B' => null, 'C' => 'A']);
    }

    /**
     * Seeds A (active) and B (inactive) as roots, runs the holder then the contender, and releases the holder
     * only once the contender is waiting for its lock.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: bool}
     */
    private function race(string $holderOperation, string $contenderOperation): array
    {
        $now = now();
        $this->db()->table('categories')->insert([
            ['name' => 'A', 'slug' => 'a', 'is_active' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'B', 'slug' => 'b', 'is_active' => false, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);

        [$holder, $holderOut] = $this->startWorker('holder', $holderOperation);
        $this->waitFor(fn () => file_exists($this->signals.'/holder.locked'), 'the holder to take its locks');

        [$contender, $contenderOut] = $this->startWorker('contender', $contenderOperation);
        $this->waitFor(fn () => file_exists($this->signals.'/contender.connection'), 'the contender to connect');
        $contenderConnection = (int) file_get_contents($this->signals.'/contender.connection');

        // performance_schema.data_lock_waits is read live; information_schema.INNODB_TRX is a cache that is only
        // refreshed when it has not been read for 0.1 s, so polling it would keep returning a stale snapshot.
        $lockWait = $this->waitFor(function () use ($contender, $contenderOut, $contenderConnection) {
            if (! proc_get_status($contender)['running']) {
                touch($this->signals.'/release');
                $this->fail('The contender finished without waiting for the lock: '.stream_get_contents($contenderOut[1]).stream_get_contents($contenderOut[2]));
            }

            return $this->db()->table('performance_schema.data_lock_waits as waits')
                ->join('performance_schema.threads as threads', 'threads.THREAD_ID', '=', 'waits.REQUESTING_THREAD_ID')
                ->where('threads.PROCESSLIST_ID', $contenderConnection)
                ->exists();
        }, "the contender (connection {$contenderConnection}) to wait for the holder's lock");

        touch($this->signals.'/release');

        return [$this->workerResult($holder, $holderOut), $this->workerResult($contender, $contenderOut), $lockWait];
    }

    private function assertRace(array $holder, array $contender, bool $lockWait, string $expectedError): void
    {
        $this->assertTrue($lockWait, 'The contender must actually wait for the holder\'s row lock.');
        $this->assertSame('saved', $holder['outcome'], json_encode($holder));
        $this->assertSame('refused', $contender['outcome'], 'The conflicting request must be refused, not fail: '.json_encode($contender));
        $this->assertSame([$expectedError], $contender['errors']['parent_id'] ?? null);
        $this->assertNotSame($holder['connection'], $contender['connection'], 'Two distinct MySQL connections.');
        $this->assertLessThan($holder['end'], $contender['start'], 'The requests overlapped.');
    }

    /**
     * @param  array<string, ?string>  $expected  name => parent name (null for a root)
     */
    private function assertTree(array $expected): void
    {
        $rows = $this->db()->table('categories')->orderBy('name')->get(['id', 'name', 'parent_id', 'is_active']);
        $names = $rows->pluck('name', 'id');

        $this->assertSame($expected, $rows->mapWithKeys(fn ($row) => [$row->name => $row->parent_id ? $names[$row->parent_id] : null])->all());

        // No third level, no self parent, no cycle (each parent is a root), no orphan.
        foreach ($rows->whereNotNull('parent_id') as $row) {
            $parent = $rows->firstWhere('id', $row->parent_id);
            $this->assertNotNull($parent, "{$row->name} has a missing parent.");
            $this->assertNotSame($row->id, $row->parent_id);
            $this->assertNull($parent->parent_id, "{$row->name} would be a third level.");
        }

        // Moves never change any active state: A stays active, B stays inactive, a created C is active.
        $active = $rows->pluck('is_active', 'name')->map(fn ($value) => (bool) $value)->all();
        $this->assertSame(array_intersect_key(['A' => true, 'B' => false, 'C' => true], $active), $active);
    }

    /** @return array{0: resource, 1: resource} */
    private function startWorker(string $role, string $operation): array
    {
        $environment = array_merge(getenv(), [
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => $this->database,
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'LOG_CHANNEL' => 'null',
            'QUEUE_CONNECTION' => 'sync',
            'CATEGORY_HIERARCHY_DB' => $this->database,
            'CATEGORY_HIERARCHY_SIGNALS' => $this->signals,
            'CATEGORY_HIERARCHY_ROLE' => $role,
            'CATEGORY_HIERARCHY_OP' => $operation,
            'CATEGORY_HIERARCHY_ADMIN' => $this->admin->email,
        ]);

        $process = proc_open([PHP_BINARY, base_path(self::WORKER)], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $environment);
        $this->assertIsResource($process, "Could not start the {$role} process.");
        $this->processes[] = $process;

        return [$process, $pipes];
    }

    /** @return array<string, mixed> */
    private function workerResult($process, array $pipes): array
    {
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $this->processes = array_values(array_filter($this->processes, fn ($p) => $p !== $process));

        $result = json_decode(trim((string) $output), true);
        $this->assertIsArray($result, 'Worker output: '.$output.' '.$errors);
        $this->assertNotSame('guard_failed', $result['outcome'], 'The worker was not connected to the isolated database.');

        return $result;
    }

    private function waitFor(callable $condition, string $what): bool
    {
        $deadline = microtime(true) + self::WAIT_SECONDS;

        while (microtime(true) < $deadline) {
            if ($condition()) {
                return true;
            }

            usleep(20_000);
        }

        $connections = $this->db()->select('SELECT id, command, state, LEFT(info, 120) AS info FROM information_schema.PROCESSLIST WHERE db = ?', [$this->database]);
        @touch($this->signals.'/release');
        $this->fail("Timed out waiting for {$what}. PROCESSLIST: ".json_encode($connections));
    }

    private function clearCategories(): void
    {
        $this->db()->table('categories')->whereNotNull('parent_id')->delete();
        $this->db()->table('categories')->delete();
    }

    private function db()
    {
        return DB::connection(self::CONNECTION);
    }
}
