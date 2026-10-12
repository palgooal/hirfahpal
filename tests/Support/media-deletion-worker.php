<?php

/*
 * Helper process for Tests\Feature\MediaDeletionConcurrencyMySqlTest (AD-UI-CAT-M2-C).
 *
 * Runs ONE operation in its own PHP process, so it has its own MySQL connection, and prints one JSON line:
 *   destroy:<media>              MediaLibraryController::destroy, resolved through the package controller binding
 *   bulk:<media>,<media>,...     MediaLibraryController::bulkDestroy
 *   assign:<category>:<media>    sets the category image in a transaction that locks the category row, as the
 *                                category form will (3 attempts on deadlock)
 *   category:<category>:<media>  the real category form save, CategoryManagementController@update, choosing that
 *   category:<category>:remove   image or removing it (AD-UI-CAT-M4-B)
 * Media are named by file_original_name, categories by name.
 *
 * Environment (set by the test):
 *   DB_CONNECTION=mysql, DB_DATABASE=<isolated database>   connection of this process
 *   MEDIA_RACE_DB       the isolated database name, re-checked here with SELECT DATABASE()
 *   MEDIA_RACE_SIGNALS  directory used to coordinate with the test
 *   MEDIA_RACE_DISK     root of the public disk for this run (a temporary directory)
 *   MEDIA_RACE_ROLE     holder | contender
 *   MEDIA_RACE_OP       the operation above
 *   MEDIA_RACE_PAUSE    holder only: media-lock | category-update (after that statement) | before-commit (after
 *                       all the transaction's work, immediately before COMMIT): the holder pauses there, still
 *                       inside its transaction, until the test creates "release"
 *   MEDIA_RACE_ADMIN    email of the super admin to act as
 */

use App\Http\Controllers\Dashboard\CategoryManagementController;
use App\Models\Admin;
use App\Models\Category;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Palgoal\MediaLibrary\Http\Controllers\MediaController as PackageMediaController;
use Palgoal\MediaLibrary\Models\Media;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$finish = function (array $result): never {
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE).PHP_EOL);
    exit(0);
};

$expected = (string) getenv('MEDIA_RACE_DB');
$connection = DB::selectOne('SELECT DATABASE() AS d, CONNECTION_ID() AS c');

if (! str_starts_with($expected, 'hirfah_fk_test_') || $connection->d !== $expected) {
    $finish(['outcome' => 'guard_failed', 'database' => $connection->d]);
}

config(['filesystems.disks.public.root' => (string) getenv('MEDIA_RACE_DISK')]);

$signals = (string) getenv('MEDIA_RACE_SIGNALS');
$role = (string) getenv('MEDIA_RACE_ROLE');
$operation = explode(':', (string) getenv('MEDIA_RACE_OP'));

if ($role === 'holder') {
    $pause = (string) getenv('MEDIA_RACE_PAUSE');
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
        // Fired after everything the transaction does and immediately before COMMIT.
        Event::listen(TransactionCommitting::class, $hold);
    } else {
        DB::listen(function (QueryExecuted $query) use ($pause, $hold): void {
            $sql = strtolower(ltrim($query->sql));
            $matches = match ($pause) {
                'media-lock' => str_contains($sql, 'from `media`') && str_ends_with($sql, 'for update'),
                'category-update' => str_starts_with($sql, 'update `categories`'),
            };

            if ($matches) {
                $hold();
            }
        });
    }
}

$admin = Admin::query()->where('email', (string) getenv('MEDIA_RACE_ADMIN'))->firstOrFail();
$mediaId = fn (string $name): int => (int) Media::query()->where('file_original_name', $name)->valueOrFail('id');

Auth::shouldUse('admin');
Auth::guard('admin')->setUser($admin);

// The connection id lets the test watch this connection in performance_schema.data_lock_waits.
file_put_contents($signals.'/'.$role.'.connection', (string) $connection->c);

$start = microtime(true);

try {
    switch ($operation[0]) {
        case 'destroy':
            $response = app(PackageMediaController::class)->destroy((string) $mediaId($operation[1]));
            $result = ['outcome' => 'response', 'status' => $response->getStatusCode(), 'body' => $response->getData(true)];
            break;

        case 'bulk':
            $ids = array_map($mediaId, explode(',', $operation[1]));
            $request = Request::create('http://localhost/dashboard/media-library/media/bulk', 'DELETE', ['ids' => $ids]);
            app()->instance('request', $request);
            $response = app(PackageMediaController::class)->bulkDestroy($request);
            $result = ['outcome' => 'response', 'status' => $response->getStatusCode(), 'body' => $response->getData(true)];
            break;

        case 'category':
            // The real category form save (AD-UI-CAT-M4-B): CategoryManagementController@update with
            // image_media_id, or remove_image=1.
            $category = Category::query()->where('name', $operation[1])->firstOrFail();
            $fields = ['name' => $category->name, 'slug' => $category->slug, 'parent_id' => $category->parent_id];
            $fields += $operation[2] === 'remove' ? ['remove_image' => '1'] : ['image_media_id' => (string) $mediaId($operation[2])];
            $request = Request::create("http://localhost/dashboard/categories/{$category->id}", 'PUT', $fields);
            $session = app('session')->driver();
            $session->start();
            $request->setLaravelSession($session);
            app()->instance('request', $request);
            app('url')->setRequest($request);
            $response = app(CategoryManagementController::class)->update($request, $category);
            $errors = $session->get('errors')?->getBag('default')->toArray() ?? [];
            $result = ['outcome' => $errors === [] ? 'saved' : 'refused', 'status' => $response->getStatusCode(), 'errors' => $errors];
            break;

        case 'assign':
            $media = $mediaId($operation[2]);
            DB::transaction(function () use ($operation, $media): void {
                Category::query()->where('name', $operation[1])->lockForUpdate()->firstOrFail()
                    ->forceFill(['image_media_id' => $media])->save();
            }, 3);
            $result = ['outcome' => 'saved'];
            break;

        default:
            throw new InvalidArgumentException('Unknown operation '.$operation[0]);
    }
} catch (HttpExceptionInterface $exception) {
    $result = ['outcome' => 'response', 'status' => $exception->getStatusCode()];
} catch (ValidationException $exception) {
    $result = ['outcome' => 'response', 'status' => 422, 'errors' => $exception->errors()];
} catch (QueryException $exception) {
    $result = ['outcome' => 'db_error', 'code' => $exception->errorInfo[1] ?? null, 'message' => $exception->getMessage()];
} catch (Throwable $exception) {
    $result = ['outcome' => 'exception', 'class' => $exception::class, 'message' => $exception->getMessage()];
}

$finish($result + ['role' => $role, 'connection' => (int) $connection->c, 'start' => $start, 'end' => microtime(true)]);
