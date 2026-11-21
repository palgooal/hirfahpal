<?php

/*
 * Helper process for Tests\Feature\DeliveryOperationsConcurrencyMySqlTest (E2-MVP).
 *
 * Runs ONE request in its own PHP process, so it has its own MySQL connection, through the real HTTP kernel (routes,
 * middleware, validation, route model binding, the real controllers), as an admin, a delivery driver or a vendor, and
 * prints one JSON line. Operations (DOR_RACE_OP, "|"-separated):
 *   assign|<vendor order>|<driver>              admin   PATCH  dashboard.vendor-orders.assign-driver {delivery_driver_id}
 *   reject-driver|<driver>                      admin   PATCH  dashboard.delivery-drivers.reject      {rejection_reason}
 *   confirm|<vendor order>                      admin   PATCH  dashboard.vendor-orders.confirm-return
 *   cancel|<vendor order>                       admin   PATCH  dashboard.vendor-orders.cancel          {cancel_reason}
 *   settle|<driver>|<amount>                    admin   POST   dashboard.delivery-drivers.cash-settlements.store {amount}
 *   reverse|<driver>|<settlement>               admin   POST   dashboard.delivery-drivers.cash-settlements.reverse {note}
 *   create|<order>|<vendor>|<total>             admin   POST   dashboard.vendor-orders.store {pending, subtotal = total}
 *   unavailable                                 driver  PUT    delivery-driver.dashboard.profile.update {is_available: false}
 *   accept|<assignment>                         driver  PATCH  delivery-driver.dashboard.assignments.accept
 *   decline|<assignment>                        driver  PATCH  delivery-driver.dashboard.assignments.decline {decline_code}
 *   pickup|<assignment>                         driver  PATCH  delivery-driver.dashboard.assignments.pick-up
 *   delivered|<assignment>|<amount>             driver  PATCH  delivery-driver.dashboard.assignments.delivered {collected_amount}
 *   declare|<assignment>                        driver  PATCH  delivery-driver.dashboard.assignments.return-to-vendor
 *   vendor-accept|<vendor order>                vendor  PATCH  vendor.dashboard.orders.accept
 *
 * Environment (set by the test):
 *   DB_CONNECTION=mysql, DB_DATABASE=<isolated database>, DB_USERNAME / DB_PASSWORD (the dedicated test account),
 *   APP_ENV=testing (as the feature tests: no CSRF token), TRANSLATION_AUTO_CREATE=false (no translation rows written)
 *   DOR_RACE_DB       the isolated database name, re-checked here with SELECT DATABASE() before anything else
 *   DOR_RACE_SIGNALS  directory used to coordinate with the test
 *   DOR_RACE_ROLE     holder | contender
 *   DOR_RACE_OP       the operation above
 *   DOR_RACE_PAUSE    holder only: before-commit (after all the transaction's work, immediately before COMMIT) |
 *                     lock:<table> (right after the first SELECT ... FROM <table> ... FOR UPDATE); the holder pauses there,
 *                     still inside its transaction, until the test creates "release"
 *   DOR_RACE_ADMIN, DOR_RACE_DRIVER, DOR_RACE_VENDOR  ids of the actors
 */

use App\Models\Admin;
use App\Models\DeliveryDriver;
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

// Guard first: the isolated database, never the development one, never as root.
$expected = (string) getenv('DOR_RACE_DB');
$connection = DB::selectOne('SELECT DATABASE() AS d, CONNECTION_ID() AS c, CURRENT_USER() AS u');
$user = strtolower(explode('@', (string) $connection->u)[0]);

if (! str_starts_with($expected, 'hirfah_fk_test_') || $expected === 'multi_vendor_commerce' || $connection->d !== $expected || $user === 'root') {
    $finish(['outcome' => 'guard_failed', 'database' => $connection->d, 'user' => $user]);
}

config(['palgoals-locale.auto_create' => false]);

$signals = (string) getenv('DOR_RACE_SIGNALS');
$role = (string) getenv('DOR_RACE_ROLE');
$operation = explode('|', (string) getenv('DOR_RACE_OP'));
$queries = [];

DB::listen(function (QueryExecuted $query) use (&$queries): void {
    $queries[] = strtolower(ltrim($query->sql));
});

if ($role === 'holder') {
    $pause = (string) getenv('DOR_RACE_PAUSE');
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
    } elseif (str_starts_with($pause, 'lock:')) {
        $table = substr($pause, 5);
        DB::listen(function (QueryExecuted $query) use ($hold, $table): void {
            $sql = strtolower(ltrim($query->sql));

            if (str_contains($sql, 'from `'.$table.'`') && str_ends_with($sql, 'for update')) {
                $hold();
            }
        });
    } else {
        $finish(['outcome' => 'bad_pause', 'pause' => $pause]);
    }
}

$admin = Admin::query()->find((int) getenv('DOR_RACE_ADMIN'));
$driver = DeliveryDriver::query()->find((int) getenv('DOR_RACE_DRIVER'));
$vendor = Vendor::query()->find((int) getenv('DOR_RACE_VENDOR'));

[$guard, $method, $url, $body] = match ($operation[0]) {
    'assign' => ['admin', 'PATCH', route('dashboard.vendor-orders.assign-driver', (int) $operation[1]), ['delivery_driver_id' => (int) $operation[2]]],
    'reject-driver' => ['admin', 'PATCH', route('dashboard.delivery-drivers.reject', (int) $operation[1]), ['rejection_reason' => 'Rejected during the race.']],
    'confirm' => ['admin', 'PATCH', route('dashboard.vendor-orders.confirm-return', (int) $operation[1]), []],
    'cancel' => ['admin', 'PATCH', route('dashboard.vendor-orders.cancel', (int) $operation[1]), ['cancel_reason' => 'Cancelled during the race.']],
    'settle' => ['admin', 'POST', route('dashboard.delivery-drivers.cash-settlements.store', (int) $operation[1]), ['amount' => $operation[2]]],
    'reverse' => ['admin', 'POST', route('dashboard.delivery-drivers.cash-settlements.reverse', [(int) $operation[1], (int) $operation[2]]), ['note' => 'Reversed during the race.']],
    'create' => ['admin', 'POST', route('dashboard.vendor-orders.store'), ['order_id' => (int) $operation[1], 'vendor_id' => (int) $operation[2], 'status' => 'pending', 'subtotal' => $operation[3], 'total' => $operation[3]]],
    'unavailable' => ['delivery_driver', 'PUT', route('delivery-driver.dashboard.profile.update'), ['is_available' => false]],
    'accept' => ['delivery_driver', 'PATCH', route('delivery-driver.dashboard.assignments.accept', (int) $operation[1]), []],
    'decline' => ['delivery_driver', 'PATCH', route('delivery-driver.dashboard.assignments.decline', (int) $operation[1]), ['decline_code' => 'out_of_area']],
    'pickup' => ['delivery_driver', 'PATCH', route('delivery-driver.dashboard.assignments.pick-up', (int) $operation[1]), []],
    'delivered' => ['delivery_driver', 'PATCH', route('delivery-driver.dashboard.assignments.delivered', (int) $operation[1]), ['collected_amount' => $operation[2]]],
    'declare' => ['delivery_driver', 'PATCH', route('delivery-driver.dashboard.assignments.return-to-vendor', (int) $operation[1]), []],
    'vendor-accept' => ['vendor', 'PATCH', route('vendor.dashboard.orders.accept', (int) $operation[1]), []],
    default => $finish(['outcome' => 'bad_operation', 'operation' => $operation[0]]),
};

$actor = match ($guard) {
    'admin' => $admin,
    'delivery_driver' => $driver,
    'vendor' => $vendor,
};

if ($actor === null) {
    $finish(['outcome' => 'missing_actor', 'guard' => $guard]);
}

Auth::guard($guard)->setUser($actor);

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
    'locked' => array_values(array_unique(array_filter(array_map(
        fn (string $sql) => str_ends_with($sql, 'for update') && preg_match('/from `([a-z_]+)`/', $sql, $match) ? $match[1] : null,
        $queries
    )))),
]);
