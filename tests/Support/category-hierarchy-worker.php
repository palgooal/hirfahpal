<?php

/*
 * Helper process for Tests\Feature\CategoryHierarchyConcurrencyMySqlTest (AD-UI-CAT-01C-2C).
 *
 * Runs ONE category request in its own PHP process, so it has its own MySQL connection, through the real
 * CategoryManagementController (store/update) as an authenticated super admin. Prints one JSON line.
 *
 * Environment (set by the test):
 *   DB_CONNECTION=mysql, DB_DATABASE=<isolated database>   connection of this process
 *   CATEGORY_HIERARCHY_DB       the isolated database name, re-checked here with SELECT DATABASE()
 *   CATEGORY_HIERARCHY_SIGNALS  directory used to coordinate with the test
 *   CATEGORY_HIERARCHY_ROLE     holder | contender
 *   CATEGORY_HIERARCHY_OP       move:X:Y (move X under Y) | create:X:Y (create X under Y)
 *   CATEGORY_HIERARCHY_ADMIN    email of the super admin to act as
 *
 * The holder pauses inside the controller's transaction (after its row locks and checks, before its write)
 * until the test creates the "release" signal, so the contender is guaranteed to run while the locks are held.
 */

use App\Http\Controllers\Dashboard\CategoryManagementController;
use App\Models\Admin;
use App\Models\Category;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$finish = function (array $result): never {
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE).PHP_EOL);
    exit(0);
};

$expected = (string) getenv('CATEGORY_HIERARCHY_DB');
$connection = DB::selectOne('SELECT DATABASE() AS d, CONNECTION_ID() AS c');

if (! str_starts_with($expected, 'hirfah_fk_test_') || $connection->d !== $expected) {
    $finish(['outcome' => 'guard_failed', 'database' => $connection->d]);
}

$signals = (string) getenv('CATEGORY_HIERARCHY_SIGNALS');
$role = (string) getenv('CATEGORY_HIERARCHY_ROLE');
[$kind, $name, $parentName] = explode(':', (string) getenv('CATEGORY_HIERARCHY_OP'));

if ($role === 'holder') {
    $hold = function () use ($signals): void {
        touch($signals.'/holder.locked');
        $deadline = microtime(true) + 30;

        while (! file_exists($signals.'/release') && microtime(true) < $deadline) {
            usleep(20_000);
        }
    };
    Category::creating($hold);
    Category::updating($hold);
}

$admin = Admin::query()->where('email', (string) getenv('CATEGORY_HIERARCHY_ADMIN'))->firstOrFail();
$parent = Category::query()->where('name', $parentName)->firstOrFail();
$target = $kind === 'move' ? Category::query()->where('name', $name)->firstOrFail() : null;
$data = $kind === 'move'
    ? ['name' => $name, 'slug' => $target->slug, 'parent_id' => $parent->id, 'is_active' => $target->is_active ? 1 : 0]
    : ['name' => $name, 'parent_id' => $parent->id, 'is_active' => 1];

$request = Request::create('http://localhost/dashboard/categories', $kind === 'move' ? 'PUT' : 'POST', $data);
$session = app('session')->driver();
$session->start();
$request->setLaravelSession($session);
app()->instance('request', $request);
app('url')->setRequest($request);
Auth::guard('admin')->setUser($admin);

// The connection id lets the test watch this transaction in information_schema.INNODB_TRX.
file_put_contents($signals.'/'.$role.'.connection', (string) $connection->c);

$start = microtime(true);

try {
    $kind === 'move'
        ? app(CategoryManagementController::class)->update($request, $target)
        : app(CategoryManagementController::class)->store($request);
    $result = ['outcome' => 'saved'];
} catch (ValidationException $exception) {
    $result = ['outcome' => 'refused', 'errors' => $exception->errors()];
} catch (QueryException $exception) {
    $result = ['outcome' => 'db_error', 'code' => $exception->errorInfo[1] ?? null, 'message' => $exception->getMessage()];
} catch (Throwable $exception) {
    $result = ['outcome' => 'exception', 'class' => $exception::class, 'message' => $exception->getMessage()];
}

$finish($result + ['role' => $role, 'connection' => (int) $connection->c, 'start' => $start, 'end' => microtime(true)]);
