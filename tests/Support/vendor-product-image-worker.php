<?php

/*
 * Helper process for Tests\Feature\VendorProductImageConcurrencyMySqlTest (VPM-01C-QA).
 *
 * Runs ONE vendor request in its own PHP process, so it has its own MySQL connection, through the real HTTP kernel
 * (routes, middleware, form request validation, route model binding, the vendor controllers), and prints one JSON line:
 *   add|<path>                   POST   vendor.dashboard.products.images.store   {path, alt_text: null, is_primary: false}
 *   replace|<image id>|<path>    PATCH  vendor.dashboard.products.images.update  {path, alt_text: null}
 *   destroy|<media id>           DELETE vendor.dashboard.products.media.destroy
 *   move|<image id>|up|down      PATCH  vendor.dashboard.products.images.move    {direction} (VPM-01D)
 *   main|<image id>              PATCH  vendor.dashboard.products.images.update  {is_primary: true}
 *   unlink|<image id>            DELETE vendor.dashboard.products.images.destroy (VENDOR-PRODUCT-PUBLISH-BE-01B)
 *
 * Environment (set by the test):
 *   DB_CONNECTION=mysql, DB_DATABASE=<isolated database>, APP_ENV=testing (as the feature tests: no CSRF token)
 *   VPM_RACE_DB       the isolated database name, re-checked here with SELECT DATABASE()
 *   VPM_RACE_SIGNALS  directory used to coordinate with the test
 *   VPM_RACE_DISK     root of the public disk for this run (a temporary directory)
 *   VPM_RACE_ROLE     holder | contender
 *   VPM_RACE_OP       the operation above
 *   VPM_RACE_PAUSE    holder only: product-lock (right after SELECT ... FROM products ... FOR UPDATE) | before-commit
 *                     (after all the transaction's work, immediately before COMMIT): the holder pauses there, still
 *                     inside its transaction, until the test creates "release"
 *   VPM_RACE_VENDOR   id of the vendor to act as
 *   VPM_RACE_PRODUCT  id of the product
 */

use App\Models\Vendor;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$finish = function (array $result): never {
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE).PHP_EOL);
    exit(0);
};

$expected = (string) getenv('VPM_RACE_DB');
$connection = DB::selectOne('SELECT DATABASE() AS d, CONNECTION_ID() AS c');

if (! str_starts_with($expected, 'hirfah_fk_test_') || $connection->d !== $expected) {
    $finish(['outcome' => 'guard_failed', 'database' => $connection->d]);
}

config(['filesystems.disks.public.root' => (string) getenv('VPM_RACE_DISK')]);

$signals = (string) getenv('VPM_RACE_SIGNALS');
$role = (string) getenv('VPM_RACE_ROLE');
$operation = explode('|', (string) getenv('VPM_RACE_OP'));
$queries = [];

DB::listen(function (QueryExecuted $query) use (&$queries): void {
    $queries[] = strtolower(ltrim($query->sql));
});

if ($role === 'holder') {
    $pause = (string) getenv('VPM_RACE_PAUSE');
    $paused = false;

    $hold = function () use (&$paused, $signals): void {
        if ($paused) {
            return;
        }

        $paused = true;
        touch($signals.'/holder.locked');
        $deadline = microtime(true) + 30;

        while (! file_exists($signals.'/release') && microtime(true) < $deadline) {
            usleep(20_000);
        }
    };

    if ($pause === 'before-commit') {
        Event::listen(TransactionCommitting::class, $hold);
    } else {
        DB::listen(function (QueryExecuted $query) use ($hold): void {
            $sql = strtolower(ltrim($query->sql));

            if (str_contains($sql, 'from `products`') && str_ends_with($sql, 'for update')) {
                $hold();
            }
        });
    }
}

$vendor = Vendor::query()->findOrFail((int) getenv('VPM_RACE_VENDOR'));
$product = (int) getenv('VPM_RACE_PRODUCT');
Auth::guard('vendor')->setUser($vendor);

[$method, $url, $body] = match ($operation[0]) {
    'add' => ['POST', route('vendor.dashboard.products.images.store', $product), ['path' => $operation[1], 'alt_text' => null, 'is_primary' => false]],
    'replace' => ['PATCH', route('vendor.dashboard.products.images.update', [$product, (int) $operation[1]]), ['path' => $operation[2], 'alt_text' => null]],
    'destroy' => ['DELETE', route('vendor.dashboard.products.media.destroy', (int) $operation[1]), []],
    // VPM-01D.
    'move' => ['PATCH', route('vendor.dashboard.products.images.move', [$product, (int) $operation[1]]), ['direction' => $operation[2]]],
    'main' => ['PATCH', route('vendor.dashboard.products.images.update', [$product, (int) $operation[1]]), ['is_primary' => true]],
    // VENDOR-PRODUCT-PUBLISH-BE-01B.
    'unlink' => ['DELETE', route('vendor.dashboard.products.images.destroy', [$product, (int) $operation[1]]), []],
    default => throw new InvalidArgumentException('Unknown operation '.$operation[0]),
};

// The connection id lets the test watch this connection in performance_schema.data_lock_waits.
file_put_contents($signals.'/'.$role.'.connection', (string) $connection->c);

$start = microtime(true);

try {
    $request = Request::create($url, $method, [], [], [], ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'], json_encode($body));
    $response = $app->make(HttpKernel::class)->handle($request);
    $result = ['outcome' => 'response', 'status' => $response->getStatusCode(), 'body' => json_decode((string) $response->getContent(), true)];
} catch (Throwable $exception) {
    $result = ['outcome' => 'exception', 'class' => $exception::class, 'message' => $exception->getMessage()];
}

$finish($result + [
    'role' => $role,
    'connection' => (int) $connection->c,
    'start' => $start,
    'end' => microtime(true),
    'locked_product' => (bool) array_filter($queries, fn ($sql) => str_contains($sql, 'from `products`') && str_ends_with($sql, 'for update')),
]);
