<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * E2-MVP: the delivery and cash operations under real concurrent MySQL/InnoDB transactions. Each side runs in its own
 * PHP process (tests/Support/delivery-operations-worker.php) with its own MySQL connection, through the real HTTP kernel
 * as an admin, a delivery driver or a vendor.
 *
 * Coordination, not timing (as VendorProductImageConcurrencyMySqlTest): the holder pauses inside its transaction
 * (immediately before COMMIT, or right after a given row lock); the contender starts only then, and the test waits until
 * performance_schema.data_lock_waits shows the contender's connection waiting before releasing the holder. Every
 * scenario runs ROUNDS times on fresh rows, and asserts the one correct outcome of that race: which side succeeds, the
 * final rows, and that nothing was written twice or moved to an invalid state.
 *
 * Safety: runs only against an isolated, throwaway MySQL database named hirfah_fk_test_* (CATEGORY_FK_MYSQL_DATABASE,
 * the variable of the other MySQL tests; never the development database), with a dedicated MySQL account
 * (E2MVP_MYSQL_USERNAME / E2MVP_MYSQL_PASSWORD) that is not root and holds no global privilege. Skipped when the
 * database is not set; refuses to run otherwise. It migrates that database and deletes only the rows each round created.
 */
class DeliveryOperationsConcurrencyMySqlTest extends TestCase
{
    private const CONNECTION = 'delivery_operations_mysql';

    private const WORKER = 'tests/Support/delivery-operations-worker.php';

    private const DEVELOPMENT_DATABASE = 'multi_vendor_commerce';

    private const WAIT_SECONDS = 30;

    private const ROUNDS = 3;

    private string $database = '';

    private string $username = '';

    private string $password = '';

    private string $signals = '';

    /** @var list<resource> */
    private array $processes = [];

    /** @var array<string, list<int>> table => ids created this round */
    private array $created = [];

    private ?int $admin = null;

    private ?int $driver = null;

    private ?int $vendor = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = (string) env('CATEGORY_FK_MYSQL_DATABASE', '');

        if ($this->database === '') {
            $this->markTestSkipped('Set CATEGORY_FK_MYSQL_DATABASE to an isolated MySQL database (hirfah_fk_test_*) to run.');
        }

        // Never the development database, whatever the variable says.
        if (! str_starts_with($this->database, 'hirfah_fk_test_')) {
            $this->fail('Refusing to run: the database name must start with hirfah_fk_test_.');
        }
        if (in_array($this->database, [self::DEVELOPMENT_DATABASE, (string) config('database.connections.mysql.database')], true)) {
            $this->fail('Refusing to run: the isolated database must not be the development database.');
        }

        // A dedicated account, never root.
        $this->username = (string) env('E2MVP_MYSQL_USERNAME', '');
        $this->password = (string) env('E2MVP_MYSQL_PASSWORD', '');
        if ($this->username === '' || strtolower($this->username) === 'root') {
            $this->fail('Refusing to run: set E2MVP_MYSQL_USERNAME / E2MVP_MYSQL_PASSWORD to a dedicated MySQL account (not root).');
        }

        // Its own connection, a copy of the mysql settings on the isolated database and account; the default connection
        // (in-memory SQLite in phpunit.xml) and the development mysql connection are never used.
        config(['database.connections.'.self::CONNECTION => array_merge(config('database.connections.mysql'), [
            'url' => null, 'database' => $this->database, 'username' => $this->username, 'password' => $this->password,
        ])]);
        DB::purge(self::CONNECTION);

        $session = $this->db()->selectOne('SELECT DATABASE() AS d, CURRENT_USER() AS u');
        $this->assertSame($this->database, $session->d, 'Connected to the isolated database.');
        $this->assertNotSame('root', strtolower(explode('@', (string) $session->u)[0]), 'Not connected as root.');
        // The account must hold no global privilege (USAGE only on *.*), so it cannot reach another database.
        foreach ($this->db()->select('SHOW GRANTS') as $grant) {
            $line = (string) array_values((array) $grant)[0];
            if (str_contains($line, ' ON *.* ') && ! str_starts_with($line, 'GRANT USAGE ON *.* ')) {
                $this->fail('Refusing to run: the MySQL account holds a global privilege: '.$line);
            }
        }

        $this->artisan('migrate', ['--database' => self::CONNECTION, '--force' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        $this->stopWorkers();

        if ($this->database !== '' && $this->created !== []) {
            $this->clear();
        }

        parent::tearDown();
    }

    // ---------------------------------------------------------------- driver eligibility (batch 01)

    /** The driver turns unavailable while the admin assigns them: the assignment waits on the profile row and is refused. */
    public function test_a_driver_turning_unavailable_during_an_assignment_refuses_it(): void
    {
        $this->rounds(function () {
            $vendorOrder = $this->vendorOrder('ready_for_delivery');

            [$holder, $contender] = $this->race('unavailable', 'before-commit', "assign|{$vendorOrder}|{$this->driver}");

            $this->assertResponse(200, $holder);
            $this->assertResponse(422, $contender);
            $this->assertSame(0, $this->assignments($vendorOrder));
            $this->assertSame(['ready_for_delivery', null], $this->vendorOrderState($vendorOrder));
            $this->assertSame(0, (int) $this->profile($this->driver)->is_available);
        });
    }

    /** The admin assigns while the driver is rejected: the rejection waits for the assignment, which stands (no automatic unassignment). */
    public function test_a_rejection_arriving_during_an_assignment_waits_for_it(): void
    {
        $this->rounds(function () {
            $vendorOrder = $this->vendorOrder('ready_for_delivery');

            [$holder, $contender] = $this->race("assign|{$vendorOrder}|{$this->driver}", 'before-commit', "reject-driver|{$this->driver}");

            $this->assertResponse(302, $holder);
            $this->assertResponse(302, $contender);
            $this->assertSame(['assigned', $this->driver], $this->vendorOrderState($vendorOrder));
            $this->assertSame(1, $this->assignments($vendorOrder));
            // The rejection's UPDATE waited for the assignment's profile lock (asserted in race()), so it landed after it.
            $this->assertSame('rejected', $this->profile($this->driver)->approval_status);
        });
    }

    // ---------------------------------------------------------------- decline, accept, reassignment (batch 03)

    /** A reassignment holds the order while the first driver declines: the decline sees the task gone and is refused (404). */
    public function test_a_reassignment_during_a_decline_wins(): void
    {
        $this->rounds(function () {
            $vendorOrder = $this->vendorOrder('assigned', $this->driver);
            $assignment = $this->assignment($vendorOrder, $this->driver, 'assigned');
            $next = $this->newDriver();

            [$holder, $contender] = $this->race("assign|{$vendorOrder}|{$next}", 'before-commit', "decline|{$assignment}");

            $this->assertResponse(302, $holder);
            $this->assertResponse(404, $contender);
            $this->assertSame(['assigned', $next], $this->vendorOrderState($vendorOrder));
            $row = $this->assignmentRow($assignment);
            $this->assertSame([$next, 'assigned', null], [(int) $row->delivery_driver_id, $row->status, $row->declined_at], 'Corrected in place, not declined.');
            $this->assertSame(0, $this->history($vendorOrder, 'delivery_declined'));
        });
    }

    /** The driver declines while the admin reassigns: the reassignment waits, then starts a new attempt; the declined one is kept. */
    public function test_a_decline_during_a_reassignment_is_followed_by_a_new_attempt(): void
    {
        $this->rounds(function () {
            $vendorOrder = $this->vendorOrder('assigned', $this->driver);
            $assignment = $this->assignment($vendorOrder, $this->driver, 'assigned');
            $next = $this->newDriver();

            [$holder, $contender] = $this->race("decline|{$assignment}", 'before-commit', "assign|{$vendorOrder}|{$next}");

            $this->assertResponse(200, $holder);
            $this->assertResponse(302, $contender);
            $this->assertSame(['assigned', $next], $this->vendorOrderState($vendorOrder));
            $attempts = $this->db()->table('delivery_assignments')->where('vendor_order_id', $vendorOrder)->orderBy('id')->get();
            $this->assertSame([[$this->driver, 'cancelled', true], [$next, 'assigned', false]],
                $attempts->map(fn ($a) => [(int) $a->delivery_driver_id, $a->status, $a->declined_at !== null])->all());
            $this->assertSame(1, $this->history($vendorOrder, 'delivery_declined'));
        });
    }

    /** The same driver accepts and declines at once (two tabs): the second sees the locked row's new state and is refused. */
    public function test_an_accept_during_a_decline_by_the_same_driver_wins(): void
    {
        $this->rounds(function () {
            $vendorOrder = $this->vendorOrder('assigned', $this->driver);
            $assignment = $this->assignment($vendorOrder, $this->driver, 'assigned');

            [$holder, $contender] = $this->race("accept|{$assignment}", 'before-commit', "decline|{$assignment}");

            $this->assertResponse(200, $holder);
            $this->assertResponse(422, $contender);
            $row = $this->assignmentRow($assignment);
            $this->assertSame(['accepted', null], [$row->status, $row->declined_at]);
            $this->assertSame(['assigned', $this->driver], $this->vendorOrderState($vendorOrder));
        });
    }

    // ---------------------------------------------------------------- the return of the goods (custody fix)

    /** The admin confirms the return while the driver declares it: the declaration finds the custody closed and is refused. */
    public function test_a_confirmation_during_a_return_declaration(): void
    {
        $this->rounds(function () {
            $vendorOrder = $this->vendorOrder('out_for_delivery', $this->driver);
            $assignment = $this->assignment($vendorOrder, $this->driver, 'failed');

            [$holder, $contender] = $this->race("confirm|{$vendorOrder}", 'before-commit', "declare|{$assignment}");

            $this->assertResponse(302, $holder);
            $this->assertResponse(422, $contender);
            $row = $this->assignmentRow($assignment);
            $this->assertSame(['cancelled', $this->admin, null], [$row->status, (int) $row->return_confirmed_by, $row->return_declared_at]);
            $this->assertNotNull($row->returned_at);
            $this->assertSame(['ready_for_delivery', null], $this->vendorOrderState($vendorOrder));
        });
    }

    /** The driver declares while the admin confirms: the confirmation waits, then closes the custody; both are recorded. */
    public function test_a_declaration_during_a_confirmation(): void
    {
        $this->rounds(function () {
            $vendorOrder = $this->vendorOrder('out_for_delivery', $this->driver);
            $assignment = $this->assignment($vendorOrder, $this->driver, 'failed');

            [$holder, $contender] = $this->race("declare|{$assignment}", 'before-commit', "confirm|{$vendorOrder}");

            $this->assertResponse(200, $holder);
            $this->assertResponse(302, $contender);
            $row = $this->assignmentRow($assignment);
            $this->assertSame(['cancelled', $this->admin], [$row->status, (int) $row->return_confirmed_by]);
            $this->assertNotNull($row->return_declared_at);
            $this->assertSame([1, 1], [$this->history($vendorOrder, 'return_declared'), $this->history($vendorOrder, 'return_confirmed')]);
        });
    }

    /** Two admins confirm the same return: one closes the custody, the other is refused; one history row. */
    public function test_two_concurrent_confirmations_close_the_custody_once(): void
    {
        $this->rounds(function () {
            $vendorOrder = $this->vendorOrder('out_for_delivery', $this->driver);
            $this->assignment($vendorOrder, $this->driver, 'failed');

            [$holder, $contender] = $this->race("confirm|{$vendorOrder}", 'before-commit', "confirm|{$vendorOrder}");

            $this->assertResponse(302, $holder);
            $this->assertResponse(422, $contender);
            $this->assertSame(1, $this->history($vendorOrder, 'return_confirmed'));
            $this->assertSame(['ready_for_delivery', null], $this->vendorOrderState($vendorOrder));
        });
    }

    // ---------------------------------------------------------------- cancellation (batch 04)

    /** The driver picks up while the admin cancels: the cancellation finds the goods with the driver and is refused. */
    public function test_a_pickup_during_a_cancellation_wins(): void
    {
        $this->rounds(function () {
            $vendorOrder = $this->vendorOrder('assigned', $this->driver);
            $assignment = $this->assignment($vendorOrder, $this->driver, 'accepted');

            [$holder, $contender] = $this->race("pickup|{$assignment}", 'before-commit', "cancel|{$vendorOrder}");

            $this->assertResponse(200, $holder);
            $this->assertResponse(422, $contender);
            $this->assertSame(['out_for_delivery', $this->driver], $this->vendorOrderState($vendorOrder));
            $this->assertSame('picked_up', $this->assignmentRow($assignment)->status);
            $this->assertSame(0, $this->history($vendorOrder, 'cancelled_by_admin'));
        });
    }

    /** The admin cancels while the driver picks up: the pickup finds the task cancelled and is refused. */
    public function test_a_cancellation_during_a_pickup_wins(): void
    {
        $this->rounds(function () {
            $vendorOrder = $this->vendorOrder('assigned', $this->driver);
            $assignment = $this->assignment($vendorOrder, $this->driver, 'accepted');

            [$holder, $contender] = $this->race("cancel|{$vendorOrder}", 'before-commit', "pickup|{$assignment}");

            $this->assertResponse(302, $holder);
            $this->assertResponse(422, $contender);
            $this->assertSame(['cancelled', null], $this->vendorOrderState($vendorOrder));
            $row = $this->assignmentRow($assignment);
            $this->assertSame(['cancelled', null], [$row->status, $row->picked_up_at]);
        });
    }

    /** The vendor accepts while the admin cancels a pending order: the cancellation waits, then cancels without restoring the committed stock. */
    public function test_a_vendor_accept_during_a_cancellation_keeps_the_committed_stock(): void
    {
        $this->rounds(function () {
            [$vendorOrder, $product, $reservation] = $this->pendingOrderWithStock(5, 2);

            [$holder, $contender] = $this->race("vendor-accept|{$vendorOrder}", 'before-commit', "cancel|{$vendorOrder}");

            $this->assertResponse(200, $holder);
            $this->assertResponse(302, $contender);
            $this->assertSame(['cancelled', null], $this->vendorOrderState($vendorOrder));
            $this->assertSame('committed', $this->db()->table('stock_reservations')->where('id', $reservation)->value('status'));
            $this->assertSame(3, (int) $this->db()->table('products')->where('id', $product)->value('stock_quantity'), 'Deducted once at the accept, not restored.');
        });
    }

    /** The admin cancels a pending order while the vendor accepts: the accept finds it cancelled; the reservation is released, the stock untouched. */
    public function test_a_cancellation_during_a_vendor_accept_releases_the_reservation(): void
    {
        $this->rounds(function () {
            [$vendorOrder, $product, $reservation] = $this->pendingOrderWithStock(5, 2);

            [$holder, $contender] = $this->race("cancel|{$vendorOrder}", 'before-commit', "vendor-accept|{$vendorOrder}");

            $this->assertResponse(302, $holder);
            $this->assertResponse(422, $contender);
            $this->assertSame(['cancelled', null], $this->vendorOrderState($vendorOrder));
            $this->assertSame('released', $this->db()->table('stock_reservations')->where('id', $reservation)->value('status'));
            $this->assertSame(5, (int) $this->db()->table('products')->where('id', $product)->value('stock_quantity'));
        });
    }

    // ---------------------------------------------------------------- cash (batch 05)

    /** The driver submits the delivery twice at once: the cash is collected once, the second submission is refused. */
    public function test_a_double_delivery_collects_the_cash_once(): void
    {
        $this->rounds(function () {
            $vendorOrder = $this->vendorOrder('out_for_delivery', $this->driver, '42.50');
            $assignment = $this->assignment($vendorOrder, $this->driver, 'picked_up', ['cod_amount' => 42.50]);

            [$holder, $contender] = $this->race("delivered|{$assignment}|42.50", 'before-commit', "delivered|{$assignment}|42.50");

            $this->assertResponse(200, $holder);
            $this->assertResponse(422, $contender);
            $row = $this->assignmentRow($assignment);
            $this->assertSame(['delivered', '42.50'], [$row->status, number_format((float) $row->cod_collected_amount, 2, '.', '')]);
            $this->assertSame(1, $this->db()->table('delivery_assignments')->where('vendor_order_id', $vendorOrder)->whereNotNull('cod_collected_at')->count());
            $this->assertSame(1, $this->history($vendorOrder, 'delivered'));
        });
    }

    /** Two settlements on one driver: they are serialized on the driver row; the one above the remaining balance is refused, a fitting one passes. */
    public function test_concurrent_settlements_never_exceed_the_balance(): void
    {
        foreach (['30.00' => 422, '20.00' => 302] as $second => $expected) {
            $this->rounds(function () use ($second, $expected) {
                $this->collected('50.00');

                [$holder, $contender] = $this->race("settle|{$this->driver}|30.00", 'before-commit', "settle|{$this->driver}|{$second}");

                $this->assertResponse(302, $holder);
                $this->assertResponse($expected, $contender);
                $settled = $this->db()->table('driver_cash_settlements')->where('delivery_driver_id', $this->driver)->where('kind', 'settlement')->sum('amount');
                $this->assertSame($expected === 302 ? '50.00' : '30.00', number_format((float) $settled, 2, '.', ''), 'Never above the 50.00 collected.');
            });
        }
    }

    /** Two reversals of the same settlement: one is recorded, the other is refused (the locked re-read, backed by unique reverses_id). */
    public function test_concurrent_reversals_record_one(): void
    {
        $this->rounds(function () {
            $this->collected('50.00');
            $settlement = $this->insert('driver_cash_settlements', ['delivery_driver_id' => $this->driver, 'kind' => 'settlement', 'amount' => 20,
                'recorded_by' => $this->admin, 'created_at' => now(), 'updated_at' => now()]);

            [$holder, $contender] = $this->race("reverse|{$this->driver}|{$settlement}", 'before-commit', "reverse|{$this->driver}|{$settlement}");

            $this->assertResponse(302, $holder);
            $this->assertResponse(422, $contender);
            $this->assertSame(1, $this->db()->table('driver_cash_settlements')->where('reverses_id', $settlement)->count());
            $this->trackWhere('driver_cash_settlements', 'reverses_id', $settlement);
        });
    }

    // ---------------------------------------------------------------- admin-created vendor orders (batch 05)

    /** Two admin creations on one parent order: serialized on the parent row; the one above the grand total is refused, a fitting one passes. */
    public function test_concurrent_creations_never_exceed_the_grand_total(): void
    {
        foreach (['30.00' => 422, '20.00' => 302] as $second => $expected) {
            $this->rounds(function () use ($second, $expected) {
                $order = $this->order('50.00');
                $otherVendor = $this->newVendor();

                [$holder, $contender] = $this->race("create|{$order}|{$this->vendor}|30.00", 'before-commit', "create|{$order}|{$otherVendor}|{$second}");

                $this->assertResponse(302, $holder);
                $this->assertResponse($expected, $contender);
                $total = $this->db()->table('vendor_orders')->where('order_id', $order)->sum('total');
                $this->assertSame($expected === 302 ? '50.00' : '30.00', number_format((float) $total, 2, '.', ''), 'Never above the 50.00 grand total.');
                $this->trackWhere('vendor_orders', 'order_id', $order);
            });
        }
    }

    // ---------------------------------------------------------------- the race

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function race(string $holderOperation, string $pause, string $contenderOperation): array
    {
        $this->signals = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hirfah-dor-race-'.Str::random(12);
        mkdir($this->signals, 0777, true);

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

        $this->removeDirectory($this->signals);
        $this->signals = '';

        return [$holderResult, $contenderResult];
    }

    private function rounds(callable $scenario, int $rounds = self::ROUNDS): void
    {
        for ($round = 1; $round <= $rounds; $round++) {
            $this->seedActors();

            try {
                $scenario();
            } finally {
                $this->stopWorkers();
                $this->clear();
            }
        }
    }

    private function assertResponse(int $status, array $result): void
    {
        $this->assertSame('response', $result['outcome'], json_encode($result));
        $this->assertSame($status, $result['status'], json_encode($result));
    }

    // ---------------------------------------------------------------- reads

    /** @return array{0: string, 1: int|null} the vendor order's status and driver */
    private function vendorOrderState(int $vendorOrder): array
    {
        $row = $this->db()->table('vendor_orders')->where('id', $vendorOrder)->first(['status', 'delivery_driver_id']);

        return [$row->status, $row->delivery_driver_id === null ? null : (int) $row->delivery_driver_id];
    }

    private function assignmentRow(int $assignment): object
    {
        return $this->db()->table('delivery_assignments')->where('id', $assignment)->first();
    }

    private function assignments(int $vendorOrder): int
    {
        return $this->db()->table('delivery_assignments')->where('vendor_order_id', $vendorOrder)->count();
    }

    private function history(int $vendorOrder, string $status): int
    {
        return $this->db()->table('order_status_histories')->where('vendor_order_id', $vendorOrder)->where('status', $status)->count();
    }

    private function profile(int $driver): object
    {
        return $this->db()->table('delivery_driver_profiles')->where('delivery_driver_id', $driver)->first();
    }

    // ---------------------------------------------------------------- data (only on the isolated connection)

    /** A super admin, an approved and available driver, an approved vendor. */
    private function seedActors(): void
    {
        $this->created = [];
        $this->onIsolatedConnection(function () {
            $admin = Admin::factory()->create(['super_admin' => true, 'status' => 'active', 'email' => 'dor-race-'.Str::random(8).'@example.test']);
            $this->track('admins', $admin->id);
            $this->admin = (int) $admin->id;
        });
        $this->driver = $this->newDriver();
        $this->vendor = $this->newVendor();
    }

    private function newDriver(): int
    {
        return $this->onIsolatedConnection(function () {
            $driver = DeliveryDriver::factory()->create(['status' => 'active', 'email' => 'dor-race-'.Str::random(8).'@example.test']);
            $driver->profile()->create(['approval_status' => 'approved', 'approved_at' => now(), 'is_available' => true]);
            $this->track('delivery_drivers', $driver->id);

            return (int) $driver->id;
        });
    }

    private function newVendor(): int
    {
        return $this->onIsolatedConnection(function () {
            $vendor = Vendor::factory()->approved()->create(['email' => 'dor-race-'.Str::random(8).'@example.test']);
            $this->track('vendors', $vendor->id);

            return (int) $vendor->id;
        });
    }

    /** A cash-on-delivery parent order (its own customer) with the given grand total. */
    private function order(string $grandTotal): int
    {
        $customer = $this->onIsolatedConnection(function () {
            $customer = Customer::factory()->create(['email' => 'dor-race-'.Str::random(8).'@example.test']);
            $this->track('customers', $customer->id);

            return (int) $customer->id;
        });

        return $this->insert('orders', ['number' => 'DOR-'.Str::random(10), 'customer_id' => $customer, 'status' => 'processing', 'payment_method' => 'cod',
            'payment_status' => 'pending', 'subtotal' => $grandTotal, 'delivery_total' => 0, 'discount_total' => 0, 'grand_total' => $grandTotal,
            'created_at' => now(), 'updated_at' => now()]);
    }

    private function vendorOrder(string $status, ?int $driver = null, string $total = '20.00'): int
    {
        $order = $this->order($total);

        return $this->insert('vendor_orders', ['number' => 'DOR-VO-'.Str::random(10), 'order_id' => $order, 'vendor_id' => $this->vendor,
            'delivery_driver_id' => $driver, 'status' => $status, 'subtotal' => $total, 'delivery_fee' => 0, 'commission_amount' => 0, 'total' => $total,
            'ready_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function assignment(int $vendorOrder, int $driver, string $status, array $attributes = []): int
    {
        $pickedUp = in_array($status, ['picked_up', 'out_for_delivery', 'failed'], true);

        return $this->insert('delivery_assignments', array_merge(['vendor_order_id' => $vendorOrder, 'delivery_driver_id' => $driver,
            'assigned_by' => $this->admin, 'status' => $status, 'assigned_at' => now(), 'picked_up_at' => $pickedUp ? now() : null,
            'failed_at' => $status === 'failed' ? now() : null, 'failure_code' => $status === 'failed' ? 'customer_absent' : null,
            'created_at' => now(), 'updated_at' => now()], $attributes));
    }

    /** A delivered cash-on-delivery task of the driver with the given amount collected (their cash balance). */
    private function collected(string $amount): void
    {
        $vendorOrder = $this->vendorOrder('delivered', $this->driver, $amount);
        $this->assignment($vendorOrder, $this->driver, 'delivered', ['delivered_at' => now(), 'cod_amount' => $amount,
            'cod_collected_amount' => $amount, 'cod_collected_at' => now()]);
    }

    /** @return array{0: int, 1: int, 2: int} a pending vendor order with one item reserving $quantity of a product in stock */
    private function pendingOrderWithStock(int $stock, int $quantity): array
    {
        $vendorOrder = $this->vendorOrder('pending');
        $order = (int) $this->db()->table('vendor_orders')->where('id', $vendorOrder)->value('order_id');
        $product = $this->insert('products', ['vendor_id' => $this->vendor, 'name' => 'Race jar', 'slug' => 'dor-race-jar-'.Str::random(8), 'price' => 10,
            'stock_quantity' => $stock, 'low_stock_threshold' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $item = $this->insert('order_items', ['order_id' => $order, 'vendor_order_id' => $vendorOrder, 'product_id' => $product, 'product_name' => 'Race jar',
            'quantity' => $quantity, 'unit_price' => 10, 'line_total' => 10 * $quantity, 'created_at' => now(), 'updated_at' => now()]);
        $reservation = $this->insert('stock_reservations', ['product_id' => $product, 'order_id' => $order, 'vendor_order_id' => $vendorOrder,
            'order_item_id' => $item, 'quantity' => $quantity, 'status' => 'reserved', 'reserved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return [$vendorOrder, $product, $reservation];
    }

    private function insert(string $table, array $row): int
    {
        $id = (int) $this->db()->table($table)->insertGetId($row);
        $this->track($table, $id);

        return $id;
    }

    private function track(string $table, int $id): void
    {
        $this->created[$table][] = $id;
    }

    /** Rows the operations created (a vendor order, a reversal) are tracked by their parent so the round leaves nothing. */
    private function trackWhere(string $table, string $column, int $value): void
    {
        foreach ($this->db()->table($table)->where($column, $value)->pluck('id') as $id) {
            $this->track($table, (int) $id);
        }
    }

    /** Factories write on the isolated connection only, for the duration of the callback. */
    private function onIsolatedConnection(callable $callback): mixed
    {
        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection(self::CONNECTION);

        try {
            return $callback();
        } finally {
            DB::setDefaultConnection($previous);
        }
    }

    /**
     * Deletes only this round's rows, children first: by the parent ids it created (the operations' own rows, such as
     * history entries, new attempts and settlements, hang from them), never a whole table.
     */
    private function clear(): void
    {
        $ids = fn (string $table) => array_values(array_unique($this->created[$table] ?? []));
        $orders = $ids('orders');
        $drivers = $ids('delivery_drivers');
        $vendors = $ids('vendors');
        $vendorOrders = array_values(array_unique([...$ids('vendor_orders'),
            ...($orders === [] ? [] : $this->db()->table('vendor_orders')->whereIn('order_id', $orders)->pluck('id')->map(fn ($id) => (int) $id)->all())]));

        if ($orders !== [] || $vendorOrders !== []) {
            $this->db()->table('order_status_histories')->where(fn ($q) => $q->whereIn('vendor_order_id', $vendorOrders)->orWhereIn('order_id', $orders))->delete();
        }
        if ($drivers !== []) {
            $this->db()->table('driver_cash_settlements')->whereIn('delivery_driver_id', $drivers)->whereNotNull('reverses_id')->delete();
            $this->db()->table('driver_cash_settlements')->whereIn('delivery_driver_id', $drivers)->delete();
        }
        if ($vendorOrders !== []) {
            $this->db()->table('delivery_assignments')->whereIn('vendor_order_id', $vendorOrders)->delete();
        }
        if ($orders !== []) {
            $this->db()->table('stock_reservations')->whereIn('order_id', $orders)->delete();
            $this->db()->table('order_items')->whereIn('order_id', $orders)->delete();
            $this->db()->table('payments')->whereIn('order_id', $orders)->delete();
            $this->db()->table('vendor_orders')->whereIn('order_id', $orders)->delete();
            $this->db()->table('orders')->whereIn('id', $orders)->delete();
        }
        if ($vendors !== []) {
            $this->db()->table('products')->whereIn('vendor_id', $vendors)->delete();
            $this->db()->table('vendor_profiles')->whereIn('vendor_id', $vendors)->delete();
            $this->db()->table('vendors')->whereIn('id', $vendors)->delete();
        }
        if ($drivers !== []) {
            $this->db()->table('delivery_driver_profiles')->whereIn('delivery_driver_id', $drivers)->delete();
            $this->db()->table('delivery_drivers')->whereIn('id', $drivers)->delete();
        }
        foreach (['customers', 'admins'] as $table) {
            if ($ids($table) !== []) {
                $this->db()->table($table)->whereIn('id', $ids($table))->delete();
            }
        }

        $this->created = [];
        $this->admin = $this->driver = $this->vendor = null;
    }

    // ---------------------------------------------------------------- processes

    /** @return array{0: resource, 1: array<int, resource>} */
    private function startWorker(string $role, string $operation, string $pause = ''): array
    {
        $environment = array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => $this->database,
            'DB_USERNAME' => $this->username,
            'DB_PASSWORD' => $this->password,
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'LOG_CHANNEL' => 'null',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
            'TRANSLATION_AUTO_CREATE' => 'false',
            'DOR_RACE_DB' => $this->database,
            'DOR_RACE_SIGNALS' => $this->signals,
            'DOR_RACE_ROLE' => $role,
            'DOR_RACE_OP' => $operation,
            'DOR_RACE_PAUSE' => $pause,
            'DOR_RACE_ADMIN' => (string) $this->admin,
            'DOR_RACE_DRIVER' => (string) $this->driver,
            'DOR_RACE_VENDOR' => (string) $this->vendor,
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
        $this->assertNotSame('guard_failed', $result['outcome'], 'The worker was not connected to the isolated database with the dedicated account.');

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

    /** Releases and terminates every worker still running (also when a scenario failed), then removes the signals directory. */
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

        if ($this->signals !== '' && is_dir($this->signals)) {
            $this->removeDirectory($this->signals);
            $this->signals = '';
        }
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
