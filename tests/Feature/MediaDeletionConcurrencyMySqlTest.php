<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * AD-UI-CAT-M2-C (finding M1-F2): media deletion and category image assignment racing on MySQL. Each side runs
 * in its own PHP process (tests/Support/media-deletion-worker.php) with its own MySQL connection, through the
 * real MediaLibraryController as a super admin.
 *
 * Coordination, not timing: the holder pauses inside its transaction after a given statement; the contender
 * starts only then, and the test waits until performance_schema.data_lock_waits shows the contender's
 * connection waiting before releasing the holder.
 *
 * Runs only against an isolated, throwaway MySQL database named hirfah_fk_test_* passed in
 * CATEGORY_FK_MYSQL_DATABASE (the variable of the other MySQL tests). Skipped otherwise. Files live in a
 * temporary directory used as the public disk.
 */
class MediaDeletionConcurrencyMySqlTest extends TestCase
{
    private const CONNECTION = 'media_deletion_mysql';

    private const WORKER = 'tests/Support/media-deletion-worker.php';

    private const MIGRATION = 'migrations/2026_10_11_000000_add_image_media_id_to_categories.php';

    private const WAIT_SECONDS = 30;

    private string $database = '';

    private string $signals = '';

    private string $disk = '';

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

        $this->clear();
        $this->admin = (new Admin)->setConnection(self::CONNECTION);
        $this->admin->fill(['name' => 'Media race', 'email' => 'media-race-'.Str::random(8).'@example.test', 'phone' => '05'.random_int(10000000, 99999999), 'password' => 'unused', 'status' => 'active', 'super_admin' => true])->save();

        $this->signals = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hirfah-media-race-'.Str::random(12);
        $this->disk = $this->signals.DIRECTORY_SEPARATOR.'disk';
        mkdir($this->disk, 0777, true);
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
            $this->clear();
            $this->db()->table('admins')->where('id', $this->admin->id)->delete();
        }

        if ($this->signals !== '' && is_dir($this->signals)) {
            $this->removeDirectory($this->signals);
        }

        parent::tearDown();
    }

    public function test_the_column_and_its_key_on_mysql(): void
    {
        $this->assertColumnState();

        // down() and up() are single ALTER statements; a round trip ends in the same state, rows untouched.
        $category = $this->db()->table('categories')->insertGetId(['name' => 'Kept', 'slug' => 'kept', 'image' => 'legacy.png', 'is_active' => true]);
        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection(self::CONNECTION);

        try {
            $migration = require database_path(self::MIGRATION);
            $migration->down();
            $this->assertFalse(Schema::connection(self::CONNECTION)->hasColumn('categories', 'image_media_id'));
            $migration->up();
            $migration->up(); // Already migrated: no change.
        } finally {
            DB::setDefaultConnection($previous);
        }

        $this->assertColumnState();
        $this->assertSame(['name' => 'Kept', 'image' => 'legacy.png', 'image_media_id' => null], (array) $this->db()->table('categories')->where('id', $category)->first(['name', 'image', 'image_media_id']));
    }

    /** The delete holds the media row; a category assignment must wait, then fail because the row is gone. */
    public function test_assigning_an_image_while_it_is_being_deleted(): void
    {
        $this->seedMedia(['X']);
        $this->seedCategory('C', null);

        [$holder, $contender, $waited] = $this->race('destroy:X', 'media-lock', 'assign:C:X');

        $this->assertTrue($waited);
        $this->assertResponse(200, $holder);
        $this->assertSame('db_error', $contender['outcome'], json_encode($contender));
        $this->assertSame(1452, $contender['code'], 'The assignment is refused by the foreign key: the image no longer exists.');

        $this->assertMediaGone('X');
        $this->assertSame([], $this->categoryImages()->filter()->all(), 'No category points at a deleted image.');
        $this->assertConsistent();
    }

    /** An assignment not yet committed holds the media row; the delete must wait, then see the reference. */
    public function test_deleting_an_image_while_an_uncommitted_assignment_holds_it(): void
    {
        $this->seedMedia(['X']);
        $this->seedCategory('C', null);

        [$holder, $contender, $waited] = $this->race('assign:C:X', 'category-update', 'destroy:X');

        $this->assertTrue($waited);
        $this->assertSame('saved', $holder['outcome'], json_encode($holder));
        $this->assertResponse(409, $contender);
        $this->assertSame([$this->mediaId('X')], $contender['body']['in_use']);

        $this->assertMediaKept('X');
        $this->assertSame(['C' => 'X'], $this->categoryImages()->all());
        $this->assertConsistent();
    }

    /** A category moving from X to Y while X is deleted: the delete waits for the move, then deletes X. */
    public function test_moving_a_category_to_another_image_while_the_old_one_is_deleted(): void
    {
        $this->seedMedia(['X', 'Y']);
        $this->seedCategory('C', 'X');

        [$holder, $contender, $waited] = $this->race('assign:C:Y', 'category-update', 'destroy:X');

        $this->assertTrue($waited);
        $this->assertSame('saved', $holder['outcome'], json_encode($holder));
        $this->assertResponse(200, $contender);

        $this->assertMediaGone('X');
        $this->assertMediaKept('Y');
        $this->assertSame(['C' => 'Y'], $this->categoryImages()->all());
        $this->assertConsistent();
    }

    /** The same move, with the delete of the NEW image racing it: the delete waits, then sees the reference. */
    public function test_deleting_the_new_image_while_a_category_moves_onto_it(): void
    {
        $this->seedMedia(['X', 'Y']);
        $this->seedCategory('C', 'X');

        [$holder, $contender, $waited] = $this->race('assign:C:Y', 'category-update', 'destroy:Y');

        $this->assertTrue($waited);
        $this->assertSame('saved', $holder['outcome'], json_encode($holder));
        $this->assertResponse(409, $contender);

        $this->assertMediaKept('X');
        $this->assertMediaKept('Y');
        $this->assertSame(['C' => 'Y'], $this->categoryImages()->all());
        $this->assertConsistent();
    }

    // ---- AD-UI-CAT-M4-B: the same races through the real category form save (CategoryManagementController) ----

    /** The form chooses X while X is being deleted: the save waits, then is refused on the image field, no 500. */
    public function test_the_category_form_choosing_an_image_while_it_is_being_deleted(): void
    {
        $this->seedMedia(['X']);
        $this->seedCategory('C', null);

        [$holder, $contender, $waited] = $this->race('destroy:X', 'media-lock', 'category:C:X');

        $this->assertTrue($waited);
        $this->assertResponse(200, $holder);
        $this->assertSame('refused', $contender['outcome'], json_encode($contender));
        $this->assertSame(['The selected image no longer exists. Choose another image.'], $contender['errors']['image_media_id'] ?? null);
        $this->assertArrayNotHasKey('parent_id', $contender['errors']);

        $this->assertMediaGone('X');
        $this->assertSame(['C' => null], $this->categoryImages()->all());
        $this->assertConsistent();
    }

    /** The form's save of X is not committed yet: the delete waits, then sees the reference and refuses. */
    public function test_deleting_an_image_the_category_form_is_saving(): void
    {
        $this->seedMedia(['X']);
        $this->seedCategory('C', null);

        [$holder, $contender, $waited] = $this->race('category:C:X', 'category-update', 'destroy:X');

        $this->assertTrue($waited);
        $this->assertSame('saved', $holder['outcome'], json_encode($holder));
        $this->assertResponse(409, $contender);

        $this->assertMediaKept('X');
        $this->assertSame(['C' => 'X'], $this->categoryImages()->all());
        $this->assertConsistent();
    }

    /** The form replaces X with Y while X is deleted: the delete waits for the replacement, then deletes X. */
    public function test_the_category_form_replacing_an_image_while_the_old_one_is_deleted(): void
    {
        $this->seedMedia(['X', 'Y']);
        $this->seedCategory('C', 'X');

        [$holder, $contender, $waited] = $this->race('category:C:Y', 'category-update', 'destroy:X');

        $this->assertTrue($waited);
        $this->assertSame('saved', $holder['outcome'], json_encode($holder));
        $this->assertResponse(200, $contender);

        $this->assertMediaGone('X');
        $this->assertMediaKept('Y');
        $this->assertSame(['C' => 'Y'], $this->categoryImages()->all());
        $this->assertConsistent();
    }

    /** The form removes the image while the image is deleted: the delete waits for the unlink, then deletes it. */
    public function test_the_category_form_removing_an_image_while_it_is_deleted(): void
    {
        $this->seedMedia(['X']);
        $this->seedCategory('C', 'X');

        [$holder, $contender, $waited] = $this->race('category:C:remove', 'category-update', 'destroy:X');

        $this->assertTrue($waited);
        $this->assertSame('saved', $holder['outcome'], json_encode($holder));
        $this->assertResponse(200, $contender);

        $this->assertMediaGone('X');
        $this->assertSame(['C' => null], $this->categoryImages()->all());
        $this->assertConsistent();
    }

    /** The form replaces X with Y while Y is being deleted: refused on the image field; C keeps X. */
    public function test_the_category_form_replacing_with_an_image_that_is_being_deleted(): void
    {
        $this->seedMedia(['X', 'Y']);
        $this->seedCategory('C', 'X');

        [$holder, $contender, $waited] = $this->race('destroy:Y', 'media-lock', 'category:C:Y');

        $this->assertTrue($waited);
        $this->assertResponse(200, $holder);
        $this->assertSame('refused', $contender['outcome'], json_encode($contender));
        $this->assertSame(['The selected image no longer exists. Choose another image.'], $contender['errors']['image_media_id'] ?? null);

        $this->assertMediaGone('Y');
        $this->assertMediaKept('X');
        $this->assertSame(['C' => 'X'], $this->categoryImages()->all());
        $this->assertConsistent();
    }

    /** Two bulk deletes sharing B: locks are taken in ascending id order, so no deadlock, no double count. */
    public function test_overlapping_bulk_deletes(): void
    {
        $this->seedMedia(['A', 'B', 'C']);

        [$holder, $contender, $waited] = $this->race('bulk:A,B', 'media-lock', 'bulk:C,B');

        $this->assertTrue($waited);
        $this->assertResponse(200, $holder);
        $this->assertSame(2, $holder['body']['deleted']);
        $this->assertResponse(200, $contender);
        $this->assertSame(1, $contender['body']['deleted'], 'B was deleted by the holder and is not counted again.');

        foreach (['A', 'B', 'C'] as $name) {
            $this->assertMediaGone($name);
        }

        $this->assertConsistent();
    }

    /** A bulk delete that contains a used item, racing a bulk delete of free items: nothing partial. */
    public function test_a_refused_bulk_delete_racing_another_bulk_delete(): void
    {
        $this->seedMedia(['A', 'B', 'C']);
        $this->seedCategory('Uses C', 'C');

        [$holder, $contender, $waited] = $this->race('bulk:A,B', 'media-lock', 'bulk:B,C');

        $this->assertTrue($waited);
        $this->assertResponse(200, $holder);
        $this->assertResponse(409, $contender);
        $this->assertSame([$this->mediaId('C')], $contender['body']['in_use']);

        $this->assertMediaGone('A');
        $this->assertMediaGone('B');
        $this->assertMediaKept('C');
        $this->assertConsistent();
    }

    /**
     * Two deletes of the same image. The holder pauses after all its transaction's work, immediately before its
     * COMMIT: the file is still on disk then, and the row still visible to other connections. The second
     * delete waits for the lock, then answers 404 and does not count the image again.
     */
    public function test_two_deletes_of_the_same_image_and_no_file_removed_before_the_commit(): void
    {
        $this->seedMedia(['X']);
        $path = $this->filePath('X');
        $seenWhilePaused = null;

        [$holder, $contender, $waited] = $this->race('destroy:X', 'before-commit', 'destroy:X', function () use ($path, &$seenWhilePaused) {
            $seenWhilePaused = [
                'file' => is_file($this->disk.DIRECTORY_SEPARATOR.$path),
                'row_visible_to_others' => $this->db()->table('media')->where('file_original_name', 'X')->exists(),
            ];
        });

        $this->assertSame(['file' => true, 'row_visible_to_others' => true], $seenWhilePaused, 'Before the commit the file must still exist.');
        $this->assertTrue($waited);
        $this->assertResponse(200, $holder);
        $this->assertResponse(404, $contender);
        $this->assertMediaGone('X');
        $this->assertConsistent();
    }

    /**
     * Seeds nothing; runs the holder, waits until it is paused, runs the contender, waits until it waits for a
     * lock, calls $whilePaused, then releases the holder.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: bool}
     */
    private function race(string $holderOperation, string $pause, string $contenderOperation, ?callable $whilePaused = null): array
    {
        [$holder, $holderOut] = $this->startWorker('holder', $holderOperation, $pause);
        $this->waitFor(fn () => file_exists($this->signals.'/holder.locked'), 'the holder to pause in its transaction', $holder, $holderOut);

        [$contender, $contenderOut] = $this->startWorker('contender', $contenderOperation);
        $this->waitFor(fn () => file_exists($this->signals.'/contender.connection'), 'the contender to connect', $contender, $contenderOut);
        $contenderConnection = (int) file_get_contents($this->signals.'/contender.connection');

        // performance_schema.data_lock_waits is read live (information_schema.INNODB_TRX is a cached snapshot).
        $waited = $this->waitFor(fn () => $this->db()->table('performance_schema.data_lock_waits as waits')
            ->join('performance_schema.threads as threads', 'threads.THREAD_ID', '=', 'waits.REQUESTING_THREAD_ID')
            ->where('threads.PROCESSLIST_ID', $contenderConnection)
            ->exists(), "the contender (connection {$contenderConnection}) to wait for the holder's lock", $contender, $contenderOut);

        if ($whilePaused) {
            $whilePaused();
        }

        touch($this->signals.'/release');

        $holderResult = $this->workerResult($holder, $holderOut);
        $contenderResult = $this->workerResult($contender, $contenderOut);

        $this->assertNotSame($holderResult['connection'], $contenderResult['connection'], 'Two distinct MySQL connections.');
        $this->assertLessThan($holderResult['end'], $contenderResult['start'], 'The operations overlapped.');

        return [$holderResult, $contenderResult, $waited];
    }

    private function assertResponse(int $status, array $result): void
    {
        $this->assertSame('response', $result['outcome'], json_encode($result));
        $this->assertSame($status, $result['status'], json_encode($result));
    }

    private function assertColumnState(): void
    {
        $column = $this->db()->selectOne("SELECT COLUMN_TYPE AS type, IS_NULLABLE AS nullable, COLUMN_DEFAULT AS dflt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'image_media_id'");
        $this->assertSame(['bigint unsigned', 'YES', null], [$column->type, $column->nullable, $column->dflt]);

        $key = $this->db()->selectOne("SELECT rc.CONSTRAINT_NAME AS name, rc.DELETE_RULE AS on_delete, kcu.REFERENCED_TABLE_NAME AS ref_table, kcu.REFERENCED_COLUMN_NAME AS ref_column FROM information_schema.REFERENTIAL_CONSTRAINTS rc JOIN information_schema.KEY_COLUMN_USAGE kcu ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA AND kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME WHERE rc.CONSTRAINT_SCHEMA = DATABASE() AND kcu.TABLE_NAME = 'categories' AND kcu.COLUMN_NAME = 'image_media_id'");
        $this->assertSame(['categories_image_media_restrict_fk', 'RESTRICT', 'media', 'id'], [$key->name, $key->on_delete, $key->ref_table, $key->ref_column]);

        $index = $this->db()->select("SELECT INDEX_NAME AS name FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'image_media_id'");
        $this->assertSame(['categories_image_media_id_index'], array_column($index, 'name'));
    }

    /** No category points at a missing media row; every remaining media row has its file. */
    private function assertConsistent(): void
    {
        $broken = $this->db()->table('categories')
            ->leftJoin('media', 'media.id', '=', 'categories.image_media_id')
            ->whereNotNull('categories.image_media_id')
            ->whereNull('media.id')
            ->count();
        $this->assertSame(0, $broken, 'A category points at a deleted image.');

        foreach ($this->db()->table('media')->pluck('file_path') as $path) {
            $this->assertFileExists($this->disk.DIRECTORY_SEPARATOR.$path, 'A remaining media row lost its file.');
        }
    }

    private function assertMediaGone(string $name): void
    {
        $this->assertFalse($this->db()->table('media')->where('file_original_name', $name)->exists(), "{$name} must be deleted.");
        $this->assertFileDoesNotExist($this->disk.DIRECTORY_SEPARATOR.'media/race/'.$name.'.png', "{$name}'s file must be removed.");
    }

    private function assertMediaKept(string $name): void
    {
        $this->assertTrue($this->db()->table('media')->where('file_original_name', $name)->exists(), "{$name} must be kept.");
        $this->assertFileExists($this->disk.DIRECTORY_SEPARATOR.$this->filePath($name), "{$name}'s file must be kept.");
    }

    /** @param  list<string>  $names */
    private function seedMedia(array $names): void
    {
        foreach ($names as $name) {
            $path = 'media/race/'.$name.'.png';
            @mkdir(dirname($this->disk.DIRECTORY_SEPARATOR.$path), 0777, true);
            file_put_contents($this->disk.DIRECTORY_SEPARATOR.$path, 'file '.$name);

            $this->db()->table('media')->insert([
                'file_name' => $name.'.png', 'file_original_name' => $name, 'file_path' => $path, 'file_extension' => 'png',
                'mime_type' => 'image/png', 'size' => 6, 'file_type' => 'image', 'disk' => 'public',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function seedCategory(string $name, ?string $image): void
    {
        $this->db()->table('categories')->insert([
            'name' => $name, 'slug' => Str::slug($name), 'is_active' => true, 'sort_order' => 0,
            'image_media_id' => $image ? $this->mediaId($image) : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function mediaId(string $name): int
    {
        return (int) $this->db()->table('media')->where('file_original_name', $name)->value('id');
    }

    private function filePath(string $name): string
    {
        return 'media/race/'.$name.'.png';
    }

    /** @return Collection<string, ?string> category name => image name */
    private function categoryImages()
    {
        return $this->db()->table('categories')
            ->leftJoin('media', 'media.id', '=', 'categories.image_media_id')
            ->orderBy('categories.name')
            ->pluck('media.file_original_name', 'categories.name');
    }

    /** @return array{0: resource, 1: array<int, resource>} */
    private function startWorker(string $role, string $operation, string $pause = ''): array
    {
        $environment = array_merge(getenv(), [
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => $this->database,
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'LOG_CHANNEL' => 'null',
            'QUEUE_CONNECTION' => 'sync',
            'MEDIA_RACE_DB' => $this->database,
            'MEDIA_RACE_SIGNALS' => $this->signals,
            'MEDIA_RACE_DISK' => $this->disk,
            'MEDIA_RACE_ROLE' => $role,
            'MEDIA_RACE_OP' => $operation,
            'MEDIA_RACE_PAUSE' => $pause,
            'MEDIA_RACE_ADMIN' => $this->admin->email,
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

    private function waitFor(callable $condition, string $what, $process, array $pipes): bool
    {
        $deadline = microtime(true) + self::WAIT_SECONDS;

        while (microtime(true) < $deadline) {
            if ($condition()) {
                return true;
            }

            if (! proc_get_status($process)['running']) {
                @touch($this->signals.'/release');
                $this->fail("Process finished before {$what}: ".stream_get_contents($pipes[1]).stream_get_contents($pipes[2]));
            }

            usleep(20_000);
        }

        $connections = $this->db()->select('SELECT id, command, state, LEFT(info, 120) AS info FROM information_schema.PROCESSLIST WHERE db = ?', [$this->database]);
        @touch($this->signals.'/release');
        $this->fail("Timed out waiting for {$what}. PROCESSLIST: ".json_encode($connections));
    }

    private function clear(): void
    {
        $this->db()->table('categories')->whereNotNull('parent_id')->delete();
        $this->db()->table('categories')->delete();
        $this->db()->table('media')->delete();
    }

    private function removeDirectory(string $directory): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }

    private function db()
    {
        return DB::connection(self::CONNECTION);
    }
}
