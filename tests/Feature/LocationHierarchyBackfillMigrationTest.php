<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * H4: 2026_11_16_000001_backfill_city_governorates_from_legacy_links and its record table (2026_11_16_000000), on
 * SQLite. Categories A–E, missing references, the record, re-runs, rollback refusals, a failure part way through and
 * the records that must not change. SQLite serialises writers by itself, so the MySQL isolation level and locks are
 * covered by LocationHierarchyBackfillMySqlTest, not here.
 */
class LocationHierarchyBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const BACKFILL = 'migrations/2026_11_16_000001_backfill_city_governorates_from_legacy_links.php';

    private const RECORD_TABLE = 'migrations/2026_11_16_000000_create_location_hierarchy_backfills_table.php';

    public function test_an_empty_database_is_left_as_it_is(): void
    {
        Log::spy();

        $this->backfill()->up();
        $this->backfill()->down();

        $this->assertSame(0, DB::table('location_hierarchy_backfills')->count());
        Log::shouldHaveReceived('info')->with('Location hierarchy backfill completed.', ['backfilled' => 0, 'already_assigned' => 0, 'left_unassigned' => 0, 'recorded' => 0]);
        Log::shouldHaveReceived('info')->with('Location hierarchy backfill rolled back.', ['reverted' => 0]);
    }

    public function test_only_category_b_is_linked_and_recorded_while_a_and_d_are_kept(): void
    {
        Log::spy();
        $gaza = $this->governorate('Gaza Governorate');
        $north = $this->governorate('North Governorate');
        $assigned = $this->city('Rafah', $gaza);                      // A, nobody else claims it
        $claimedByOwn = $this->city('Jabalia', $north);               // A, claimed by its own governorate only
        $this->claim($north, $claimedByOwn);
        $legacy = $this->city('Gaza');                                // B
        $this->claim($gaza, $legacy);
        $orphan = $this->city('Orphan');                              // D
        $governoratesBefore = $this->governorates();
        $citiesBefore = DB::table('cities')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        $this->backfill()->up();

        $this->assertSame([$assigned => $gaza, $claimedByOwn => $north, $legacy => $gaza, $orphan => null], $this->cities());
        $this->assertSame($governoratesBefore, $this->governorates(), 'governorates.city_id is never written.');
        $this->assertSame([['city_id' => $legacy, 'legacy_governorate_id' => $gaza, 'previous_governorate_id' => null, 'assigned_governorate_id' => $gaza]], $this->record());
        // Only governorate_id changed on the linked city: its other columns, updated_at included, are as before.
        $after = DB::table('cities')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        foreach ($citiesBefore as $index => $row) {
            $this->assertSame(array_diff_key($row, ['governorate_id' => true]), array_diff_key($after[$index], ['governorate_id' => true]));
        }
        Log::shouldHaveReceived('info')->with('Location hierarchy backfill completed.', ['backfilled' => 1, 'already_assigned' => 2, 'left_unassigned' => 1, 'recorded' => 1]);
    }

    public function test_a_city_claimed_by_several_governorates_stops_before_any_write(): void
    {
        $first = $this->governorate('First');
        $second = $this->governorate('Second');
        $third = $this->governorate('Third');
        $two = $this->city('Two claims');
        $this->claim($first, $two);
        $this->claim($second, $two);
        $three = $this->city('Three claims');
        $this->claim($third, $three);
        $this->claim($this->governorate('Fourth'), $three);
        $this->claim($this->governorate('Fifth'), $three);
        $valid = $this->city('Valid B');
        $this->claim($this->governorate('Sixth'), $valid);

        $this->assertStopsWithoutWriting(fn () => $this->backfill()->up(), [
            "city {$two} claimed by governorates {$first}, {$second}",
            "city {$three} claimed by governorates {$third}, ",
            'Several claimants (C)',
        ]);
        $this->assertNull(DB::table('cities')->where('id', $valid)->value('governorate_id'), 'A valid B city is not linked either.');
    }

    public function test_a_conflicting_assignment_stops_before_any_write(): void
    {
        $gaza = $this->governorate('Gaza');
        $north = $this->governorate('North');
        $city = $this->city('Jabalia', $gaza);
        $this->claim($north, $city);
        $this->claim($this->governorate('Other'), $this->city('Valid B'));

        $this->assertStopsWithoutWriting(fn () => $this->backfill()->up(), ["city {$city} belongs to governorate {$gaza} but is claimed by governorates {$north}", 'Conflicts (E)']);
    }

    public function test_missing_references_stop_before_any_write(): void
    {
        // Planted with the foreign key checks deferred: the test's transaction is rolled back, so they are never checked.
        DB::statement('PRAGMA defer_foreign_keys = ON');
        DB::table('cities')->insert(['governorate_id' => 9001, 'name' => 'Lost parent', 'slug' => 'lost-parent', 'status' => 'active']);
        $lost = (int) DB::table('cities')->where('slug', 'lost-parent')->value('id');
        DB::table('governorates')->insert(['city_id' => 9002, 'name' => 'Lost city', 'slug' => 'lost-city', 'status' => 'active']);
        $pointing = (int) DB::table('governorates')->where('slug', 'lost-city')->value('id');
        $this->claim($this->governorate('Valid'), $this->city('Valid B'));

        $this->assertStopsWithoutWriting(fn () => $this->backfill()->up(), ["cities pointing to a missing governorate [{$lost}]", "governorates pointing to a missing city [{$pointing}]"]);
    }

    public function test_a_second_run_is_harmless_and_keeps_the_record(): void
    {
        $gaza = $this->governorate('Gaza');
        $city = $this->city('Gaza city');
        $this->claim($gaza, $city);

        $this->backfill()->up();
        $record = $this->record();
        $this->backfill()->up();
        $this->backfill()->up();

        $this->assertSame([$city => $gaza], $this->cities());
        $this->assertSame($record, $this->record());
        $this->assertSame(1, DB::table('location_hierarchy_backfills')->count());
    }

    public function test_a_stale_record_stops_a_re_run(): void
    {
        $gaza = $this->governorate('Gaza');
        $north = $this->governorate('North');
        $changed = $this->city('Changed');
        $this->claim($gaza, $changed);
        $this->backfill()->up();

        // A later manual change (the city moved to another governorate, its legacy claim cleared).
        DB::table('governorates')->where('id', $gaza)->update(['city_id' => null]);
        DB::table('cities')->where('id', $changed)->update(['governorate_id' => $north]);
        $this->assertStopsWithoutWriting(fn () => $this->backfill()->up(), ["city {$changed} now belongs to governorate {$north} instead of {$gaza}", 'Re-running']);

        // A recorded city that was deleted.
        DB::table('cities')->where('id', $changed)->delete();
        $this->assertStopsWithoutWriting(fn () => $this->backfill()->up(), ["city {$changed} was deleted"]);
    }

    public function test_rollback_returns_exactly_the_recorded_cities_and_empties_the_record(): void
    {
        Log::spy();
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
        $this->backfill()->down();

        $this->assertSame([$kept => $gaza, $first => null, $second => null, $orphan => null], $this->cities(), 'Back to the state before the backfill.');
        $this->assertSame($governorates, $this->governorates());
        $this->assertSame([], $this->record());
        Log::shouldHaveReceived('info')->with('Location hierarchy backfill rolled back.', ['reverted' => 2]);

        // And it can be applied again.
        $this->backfill()->up();
        $this->assertSame([$kept => $gaza, $first => $gaza, $second => $north, $orphan => null], $this->cities());
    }

    public function test_rollback_refuses_after_a_later_manual_edit_and_changes_nothing(): void
    {
        $gaza = $this->governorate('Gaza');
        $north = $this->governorate('North');
        $edited = $this->city('Edited');
        $this->claim($gaza, $edited);
        $untouched = $this->city('Untouched');
        $this->claim($north, $untouched);
        $this->backfill()->up();

        DB::table('cities')->where('id', $edited)->update(['governorate_id' => $north]);
        $this->assertStopsWithoutWriting(fn () => $this->backfill()->down(), ["city {$edited} now belongs to governorate {$north} instead of {$gaza}", 'Rolling back']);
        $this->assertSame([$edited => $north, $untouched => $north], $this->cities(), 'The untouched city is not reverted either.');
        $this->assertCount(2, $this->record(), 'The record is kept until a successful reversal.');

        // Unlinked by hand counts as a change too.
        DB::table('cities')->where('id', $edited)->update(['governorate_id' => null]);
        $this->assertStopsWithoutWriting(fn () => $this->backfill()->down(), ["city {$edited} now belongs to no governorate instead of {$gaza}"]);
    }

    public function test_rollback_refuses_after_a_recorded_city_was_deleted(): void
    {
        $gaza = $this->governorate('Gaza');
        $deleted = $this->city('Deleted');
        $this->claim($gaza, $deleted);
        $other = $this->city('Other');
        $this->claim($this->governorate('North'), $other);
        $this->backfill()->up();

        DB::table('governorates')->where('id', $gaza)->update(['city_id' => null]);
        DB::table('cities')->where('id', $deleted)->delete();

        $this->assertStopsWithoutWriting(fn () => $this->backfill()->down(), ["city {$deleted} was deleted"]);
        $this->assertNotNull(DB::table('cities')->where('id', $other)->value('governorate_id'), 'Nothing is reverted.');
        $this->assertCount(2, $this->record());
    }

    public function test_a_failure_part_way_through_rolls_back_every_change(): void
    {
        $gaza = $this->governorate('Gaza');
        $north = $this->governorate('North');
        $first = $this->city('First');
        $this->claim($gaza, $first);
        $second = $this->city('Second');
        $this->claim($north, $second);

        // The second update fails after the first city was linked and recorded.
        DB::statement("CREATE TRIGGER fail_second_backfill BEFORE UPDATE OF governorate_id ON cities WHEN NEW.id = {$second} BEGIN SELECT RAISE(ABORT, 'forced failure'); END");
        try {
            $this->backfill()->up();
            $this->fail('The forced failure should stop the backfill.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('forced failure', $exception->getMessage());
        }
        $this->assertSame([$first => null, $second => null], $this->cities(), 'The first city is not left linked.');
        $this->assertSame([], $this->record());

        // The same on the way back: a failing reversal keeps every city linked and the whole record.
        DB::statement('DROP TRIGGER fail_second_backfill');
        $this->backfill()->up();
        DB::statement("CREATE TRIGGER fail_second_revert BEFORE UPDATE OF governorate_id ON cities WHEN OLD.id = {$second} BEGIN SELECT RAISE(ABORT, 'forced failure'); END");
        try {
            $this->backfill()->down();
            $this->fail('The forced failure should stop the rollback.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('forced failure', $exception->getMessage());
        }
        $this->assertSame([$first => $gaza, $second => $north], $this->cities());
        $this->assertCount(2, $this->record());
        DB::statement('DROP TRIGGER fail_second_revert');
    }

    public function test_the_record_table_is_kept_while_it_records_changes(): void
    {
        $gaza = $this->governorate('Gaza');
        $this->claim($gaza, $this->city('Gaza city'));
        $this->backfill()->up();

        try {
            $this->recordTable()->down();
            $this->fail('A table that still records a change must not be dropped.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Refusing to drop location_hierarchy_backfills: it still records 1 backfilled city', $exception->getMessage());
        }
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('location_hierarchy_backfills'));
        $this->assertCount(1, $this->record());

        // Once the backfill is rolled back, the empty table can go (and come back).
        $this->backfill()->down();
        $this->recordTable()->down();
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('location_hierarchy_backfills'));
        $this->recordTable()->up();
        $this->assertSame(['id', 'city_id', 'legacy_governorate_id', 'previous_governorate_id', 'assigned_governorate_id', 'created_at'], DB::getSchemaBuilder()->getColumnListing('location_hierarchy_backfills'));
        $this->assertSame([], DB::getSchemaBuilder()->getForeignKeys('location_hierarchy_backfills'), 'No foreign key: a row outlives its city.');
    }

    public function test_the_record_allows_one_row_per_city(): void
    {
        DB::table('location_hierarchy_backfills')->insert(['city_id' => 7, 'legacy_governorate_id' => 1, 'assigned_governorate_id' => 1]);

        $this->expectException(QueryException::class);
        DB::table('location_hierarchy_backfills')->insert(['city_id' => 7, 'legacy_governorate_id' => 2, 'assigned_governorate_id' => 2]);
    }

    public function test_records_pointing_to_locations_and_product_locations_are_never_changed(): void
    {
        $gaza = $this->governorate('Gaza');
        $legacy = $this->city('Gaza city');
        $this->claim($gaza, $legacy);
        $other = $this->governorate('North');
        // Stored pairs, including one that only works in the legacy direction.
        $vendor = Vendor::factory()->approved()->create();
        $vendor->profile()->update(['governorate_id' => $gaza, 'city_id' => $legacy]);
        $customer = Customer::factory()->create();
        $customer->addresses()->create(['governorate_id' => $other, 'city_id' => $legacy, 'label' => 'Home', 'recipient_name' => 'R', 'phone' => '0590000000', 'address_line' => 'Street']);
        $driver = DeliveryDriver::factory()->create();
        $driver->profile()->create(['approval_status' => 'approved', 'governorate_id' => $gaza, 'city_id' => $legacy]);
        $order = Order::query()->create(['number' => 'H4-ORDER', 'customer_id' => $customer->id, 'payment_method' => 'cod', 'grand_total' => 10]);
        DB::table('orders')->where('id', $order->id)->update(['delivery_governorate_id' => $gaza, 'delivery_city_id' => $legacy]);
        $product = Product::query()->create(['vendor_id' => $vendor->id, 'name' => 'Jug', 'slug' => 'jug', 'price' => 10, 'stock_quantity' => 1, 'status' => 'draft']);
        DB::table('products')->where('id', $product->id)->update(['governorate_id' => $other, 'city_id' => $legacy]);
        $before = $this->dependents();

        $this->backfill()->up();
        $this->assertSame($before, $this->dependents());
        $this->backfill()->down();
        $this->assertSame($before, $this->dependents());
    }

    // ---- helpers

    /** Runs $action, expects it to stop with every fragment in its message, and checks that nothing was written. */
    private function assertStopsWithoutWriting(callable $action, array $fragments): void
    {
        $cities = $this->cities();
        $governorates = $this->governorates();
        $record = $this->record();

        try {
            $action();
            $this->fail('The migration should have stopped.');
        } catch (RuntimeException $exception) {
            foreach ($fragments as $fragment) {
                $this->assertStringContainsString($fragment, $exception->getMessage());
            }
            $this->assertStringContainsString('Nothing was changed.', $exception->getMessage());
        }

        $this->assertSame($cities, $this->cities());
        $this->assertSame($governorates, $this->governorates());
        $this->assertSame($record, $this->record());
    }

    private function backfill(): object
    {
        return require database_path(self::BACKFILL);
    }

    private function recordTable(): object
    {
        return require database_path(self::RECORD_TABLE);
    }

    private function governorate(string $name): int
    {
        return DB::table('governorates')->insertGetId(['name' => $name, 'slug' => 'g-'.str()->slug($name).'-'.str()->random(4), 'status' => 'active']);
    }

    private function city(string $name, ?int $governorateId = null): int
    {
        return DB::table('cities')->insertGetId(['governorate_id' => $governorateId, 'name' => $name, 'slug' => 'c-'.str()->slug($name).'-'.str()->random(4), 'status' => 'active', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-02 00:00:00']);
    }

    /** The legacy reverse link: the governorate points to the city. */
    private function claim(int $governorateId, int $cityId): void
    {
        DB::table('governorates')->where('id', $governorateId)->update(['city_id' => $cityId]);
    }

    /** @return array<int, int|null> */
    private function cities(): array
    {
        return DB::table('cities')->orderBy('id')->pluck('governorate_id', 'id')->map(fn ($id) => $id === null ? null : (int) $id)->all();
    }

    /** @return array<int, int|null> */
    private function governorates(): array
    {
        return DB::table('governorates')->orderBy('id')->pluck('city_id', 'id')->map(fn ($id) => $id === null ? null : (int) $id)->all();
    }

    private function record(): array
    {
        return DB::table('location_hierarchy_backfills')->orderBy('id')->get(['city_id', 'legacy_governorate_id', 'previous_governorate_id', 'assigned_governorate_id'])
            ->map(fn ($row) => array_map(fn ($value) => $value === null ? null : (int) $value, (array) $row))->all();
    }

    private function dependents(): array
    {
        $rows = fn (string $table) => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        return array_map($rows, ['vendor_profiles' => 'vendor_profiles', 'customer_addresses' => 'customer_addresses', 'delivery_driver_profiles' => 'delivery_driver_profiles', 'orders' => 'orders', 'products' => 'products']);
    }
}
