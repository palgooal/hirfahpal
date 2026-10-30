<?php

namespace Tests\Feature;

use App\Http\Controllers\VendorDashboard\ProductImageController;
use App\Models\Product;
use App\Models\Vendor;
use App\Rules\VendorLibraryImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * VPM-01C-QA: duplicate vendor product images under real concurrent MySQL/InnoDB transactions. Each side runs in its
 * own PHP process (tests/Support/vendor-product-image-worker.php) with its own MySQL connection, through the real HTTP
 * kernel as an approved vendor.
 *
 * Coordination, not timing (as MediaDeletionConcurrencyMySqlTest, VENDOR-MEDIA-02C): the holder pauses inside its
 * transaction (right after the product row lock, or immediately before COMMIT); the contender starts only then, and the
 * test waits until performance_schema.data_lock_waits shows the contender's connection waiting before releasing the
 * holder. Every scenario runs ROUNDS times on fresh data.
 *
 * Runs only against an isolated, throwaway MySQL database named hirfah_fk_test_* passed in
 * CATEGORY_FK_MYSQL_DATABASE (the variable of the other MySQL tests). Skipped otherwise. Files live in a temporary
 * directory used as the public disk.
 */
class VendorProductImageConcurrencyMySqlTest extends TestCase
{
    private const CONNECTION = 'vendor_product_image_mysql';

    private const WORKER = 'tests/Support/vendor-product-image-worker.php';

    private const WAIT_SECONDS = 30;

    private const ROUNDS = 5;

    private const CONFLICT = ['message' => ProductImageController::DUPLICATE_MESSAGE, 'errors' => ['path' => [ProductImageController::DUPLICATE_MESSAGE]]];

    private string $database = '';

    private string $signals = '';

    private string $disk = '';

    /** @var list<resource> */
    private array $processes = [];

    private ?Vendor $vendor = null;

    private ?Vendor $other = null;

    private ?Product $product = null;

    /** @var array<string, string> name => path */
    private array $paths = [];

    /** @var array<string, int> name => media id */
    private array $media = [];

    /** @var array<string, int> name => product image id */
    private array $images = [];

    /** @var list<array<int, mixed>> each race: holder op, pause, contender op, holder status, contender status */
    private array $log = [];

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
    }

    protected function tearDown(): void
    {
        $this->stopWorkers();

        if ($this->database !== '') {
            $this->clear();
        }

        parent::tearDown();
    }

    // ---------------------------------------------------------------- duplicate attachments

    /** Two attachments of the same image: the second waits on the product row lock, then sees the first. */
    public function test_two_concurrent_attachments_of_the_same_image(): void
    {
        foreach (['product-lock', 'before-commit'] as $pause) {
            $this->rounds(function () use ($pause) {
                [$holder, $contender] = $this->race('add|'.$this->paths['P'], $pause, 'add|'.$this->paths['P']);

                $this->assertResponse(201, $holder);
                $this->assertResponse(409, $contender);
                $this->assertSame(self::CONFLICT, $contender['body']);
                $this->assertSame(1, $this->rowsWithPath('P'));
                $this->assertGallery(['A', 'B', 'P']);
            });
        }
    }

    /** Two replacements onto the same image: the second is refused, its slot keeps its image. */
    public function test_two_concurrent_replacements_with_the_same_image(): void
    {
        $this->rounds(function () {
            [$holder, $contender] = $this->race("replace|{$this->images['A']}|{$this->paths['P']}", 'before-commit', "replace|{$this->images['B']}|{$this->paths['P']}");

            $this->assertResponse(200, $holder);
            $this->assertResponse(409, $contender);
            $this->assertSame(self::CONFLICT, $contender['body']);
            $this->assertSame(1, $this->rowsWithPath('P'));
            $this->assertGallery(['P', 'B']);
            $this->assertSame([$this->images['A'], $this->images['B']], $this->galleryIds(), 'Same rows, same order.');
        });
    }

    /** A replacement and an attachment of the same image, both ways round. */
    public function test_a_replacement_and_an_attachment_of_the_same_image(): void
    {
        $this->rounds(function () {
            [$holder, $contender] = $this->race("replace|{$this->images['A']}|{$this->paths['P']}", 'before-commit', 'add|'.$this->paths['P']);
            $this->assertResponse(200, $holder);
            $this->assertResponse(409, $contender);
            $this->assertSame(1, $this->rowsWithPath('P'));
            $this->assertGallery(['P', 'B']);
        });

        $this->rounds(function () {
            [$holder, $contender] = $this->race('add|'.$this->paths['P'], 'before-commit', "replace|{$this->images['B']}|{$this->paths['P']}");
            $this->assertResponse(201, $holder);
            $this->assertResponse(409, $contender);
            $this->assertSame(1, $this->rowsWithPath('P'));
            $this->assertGallery(['A', 'B', 'P']);
        });
    }

    /** Control: two different images still both attach (the lock serialises, it does not refuse). */
    public function test_two_concurrent_attachments_of_different_images_both_succeed(): void
    {
        $this->rounds(function () {
            [$holder, $contender] = $this->race('add|'.$this->paths['P'], 'before-commit', 'add|'.$this->paths['Q']);

            $this->assertResponse(201, $holder);
            $this->assertResponse(201, $contender);
            $this->assertGallery(['A', 'B', 'P', 'Q']);
        });
    }

    // ---------------------------------------------------------------- existing protections

    /** VENDOR-MEDIA-02C still holds: deleting the image from the library while it is being attached waits, then 409s. */
    public function test_deleting_the_library_image_while_it_is_being_attached(): void
    {
        $this->rounds(function () {
            [$holder, $contender] = $this->race('add|'.$this->paths['P'], 'before-commit', 'destroy|'.$this->media['P']);

            $this->assertResponse(201, $holder);
            $this->assertResponse(409, $contender);
            $this->assertTrue($this->db()->table('media')->where('id', $this->media['P'])->exists(), 'The library row is kept.');
            $this->assertFileExists($this->disk.DIRECTORY_SEPARATOR.$this->paths['P'], 'The file is kept.');
            $this->assertSame(1, $this->rowsWithPath('P'));
            $this->assertGallery(['A', 'B', 'P']);
        });
    }

    /** Ownership on MySQL: another vendor's image is refused before any lock, with the library error, not a 409. */
    public function test_another_vendors_image_is_refused_as_not_in_the_library(): void
    {
        $this->rounds(function () {
            [$process, $pipes] = $this->startWorker('contender', 'add|'.$this->paths['THEIRS']);
            $result = $this->workerResult($process, $pipes);

            $this->assertResponse(422, $result);
            $this->assertSame(['path' => [VendorLibraryImage::MESSAGE]], $result['body']['errors']);
            $this->assertFalse($result['locked_product'], 'Refused in validation, before the product lock.');
            $this->assertSame(0, $this->rowsWithPath('THEIRS'));
            $this->assertGallery(['A', 'B']);
        }, 2);
    }

    // ---------------------------------------------------------------- the race

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function race(string $holderOperation, string $pause, string $contenderOperation): array
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

        touch($this->signals.'/release');

        $holderResult = $this->workerResult($holder, $holderOut);
        $contenderResult = $this->workerResult($contender, $contenderOut);

        $this->assertTrue($waited, 'The contender waited on an InnoDB lock held by the holder.');
        $this->assertNotSame($holderResult['connection'], $contenderResult['connection'], 'Two distinct MySQL connections.');
        $this->assertLessThan($holderResult['end'], $contenderResult['start'], 'The operations overlapped.');
        $this->assertTrue($holderResult['locked_product'] || str_starts_with($contenderOperation, 'destroy'), 'The holder took the product lock.');

        $this->log[] = [$holderOperation, $pause, $contenderOperation, $holderResult['status'] ?? $holderResult['outcome'], $contenderResult['status'] ?? $contenderResult['outcome']];

        return [$holderResult, $contenderResult];
    }

    private function rounds(callable $scenario, int $rounds = self::ROUNDS): void
    {
        for ($round = 1; $round <= $rounds; $round++) {
            $this->seedRound();

            try {
                $scenario();
            } finally {
                $this->stopWorkers();
                $this->clear();
            }
        }

        fwrite(STDERR, "\n  [{$this->name()}] {$rounds} rounds: ".json_encode(array_slice($this->log, -1)[0] ?? 'single requests').PHP_EOL);
    }

    private function assertResponse(int $status, array $result): void
    {
        $this->assertSame('response', $result['outcome'], json_encode($result));
        $this->assertSame($status, $result['status'], json_encode($result));
    }

    /**
     * The product's gallery: these images in this order, sort_order 0..n-1, exactly the first one main, and no path
     * twice (no partial write, no duplicate).
     *
     * @param  list<string>  $names
     */
    private function assertGallery(array $names): void
    {
        $rows = $this->db()->table('product_images')->where('product_id', $this->product->id)->orderBy('sort_order')->orderBy('id')->get();

        $this->assertSame(array_map(fn ($name) => $this->paths[$name], $names), $rows->pluck('path')->all());
        $this->assertSame(range(0, count($names) - 1), $rows->pluck('sort_order')->map(fn ($v) => (int) $v)->all());
        $this->assertSame([true], $rows->filter(fn ($row) => (bool) $row->is_primary)->keys()->map(fn ($k) => $k === 0)->values()->all(), 'Exactly the first image is main.');
        $this->assertSame($rows->count(), $rows->pluck('path')->unique()->count(), 'No path twice.');
    }

    private function rowsWithPath(string $name): int
    {
        return $this->db()->table('product_images')->where('path', $this->paths[$name])->count();
    }

    /** @return list<int> */
    private function galleryIds(): array
    {
        return $this->db()->table('product_images')->where('product_id', $this->product->id)->orderBy('sort_order')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    // ---------------------------------------------------------------- data

    /** An approved vendor (real factory, on the isolated connection), its product with A and B, free library images P and Q, another vendor's image. */
    private function seedRound(): void
    {
        $this->signals = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hirfah-vpm-race-'.Str::random(12);
        $this->disk = $this->signals.DIRECTORY_SEPARATOR.'disk';
        mkdir($this->disk, 0777, true);

        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection(self::CONNECTION);

        try {
            $this->vendor = Vendor::factory()->approved()->create(['email' => 'vpm-race-'.Str::random(8).'@example.test']);
            $this->other = Vendor::factory()->approved()->create(['email' => 'vpm-race-'.Str::random(8).'@example.test']);
            $this->product = Product::create(['vendor_id' => $this->vendor->id, 'name' => 'Race jug', 'slug' => 'race-jug-'.Str::random(8), 'price' => 10, 'stock_quantity' => 2, 'status' => 'draft']);
        } finally {
            DB::setDefaultConnection($previous);
        }

        foreach (['A', 'B', 'P', 'Q'] as $name) {
            $this->library($name, $this->vendor);
        }
        $this->library('THEIRS', $this->other);

        foreach (['A', 'B'] as $position => $name) {
            $this->images[$name] = (int) $this->db()->table('product_images')->insertGetId([
                'product_id' => $this->product->id, 'path' => $this->paths[$name], 'alt_text' => $name,
                'sort_order' => $position, 'is_primary' => $position === 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function library(string $name, Vendor $vendor): void
    {
        $path = "vendors/media/{$vendor->id}/".Str::uuid().'.png';
        @mkdir(dirname($this->disk.DIRECTORY_SEPARATOR.$path), 0777, true);
        file_put_contents($this->disk.DIRECTORY_SEPARATOR.$path, 'file '.$name);

        $this->paths[$name] = $path;
        $this->media[$name] = (int) $this->db()->table('media')->insertGetId([
            'file_name' => basename($path), 'file_original_name' => $name.'.png', 'file_path' => $path, 'file_extension' => 'png',
            'mime_type' => 'image/png', 'size' => 6, 'file_type' => 'image', 'disk' => 'public', 'uploader_id' => $vendor->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Only this run's rows: its vendors' products, images, profiles and library rows. */
    private function clear(): void
    {
        $vendors = array_values(array_filter([$this->vendor?->id, $this->other?->id]));

        if ($vendors !== []) {
            $products = $this->db()->table('products')->whereIn('vendor_id', $vendors)->pluck('id');
            $this->db()->table('product_images')->whereIn('product_id', $products)->delete();
            $this->db()->table('products')->whereIn('id', $products)->delete();
            $this->db()->table('media')->whereIn('id', array_values($this->media))->delete();
            $this->db()->table('vendor_profiles')->whereIn('vendor_id', $vendors)->delete();
            $this->db()->table('vendors')->whereIn('id', $vendors)->delete();
        }

        if ($this->signals !== '' && is_dir($this->signals)) {
            $this->removeDirectory($this->signals);
        }

        $this->vendor = $this->other = $this->product = null;
        $this->paths = $this->media = $this->images = [];
        $this->signals = '';
    }

    // ---------------------------------------------------------------- processes

    /** @return array{0: resource, 1: array<int, resource>} */
    private function startWorker(string $role, string $operation, string $pause = ''): array
    {
        $environment = array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => $this->database,
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'LOG_CHANNEL' => 'null',
            'QUEUE_CONNECTION' => 'sync',
            'VPM_RACE_DB' => $this->database,
            'VPM_RACE_SIGNALS' => $this->signals,
            'VPM_RACE_DISK' => $this->disk,
            'VPM_RACE_ROLE' => $role,
            'VPM_RACE_OP' => $operation,
            'VPM_RACE_PAUSE' => $pause,
            'VPM_RACE_VENDOR' => (string) $this->vendor->id,
            'VPM_RACE_PRODUCT' => (string) $this->product->id,
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

    private function stopWorkers(): void
    {
        foreach ($this->processes as $process) {
            if (is_resource($process)) {
                if ($this->signals !== '') {
                    @touch($this->signals.'/release');
                }
                proc_terminate($process);
                proc_close($process);
            }
        }

        $this->processes = [];
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
