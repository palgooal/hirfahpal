<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/*
 * H4: links each city to its governorate in the approved hierarchy (Governorate → City, cities.governorate_id) from
 * the legacy reverse link (governorates.city_id). Data only; the record table is 2026_11_16_000000.
 *
 * Every city is classified first:
 *   A. it has a governorate and no other governorate claims it: kept as it is;
 *   B. it has none and exactly one governorate claims it: linked to that governorate (the only change);
 *   C. it has none and several governorates claim it: stop, the parent is a manual decision;
 *   D. it has none and no governorate claims it: kept as it is (shown read-only in Location Management);
 *   E. it has a governorate and another governorate claims it: stop, a conflict to resolve by hand.
 * A reference to a missing city or governorate also stops it. A stop happens before any write and names the ids.
 *
 * All or nothing, in one transaction: REPEATABLE READ is set explicitly (READ COMMITTED takes no gap locks), then
 * governorates, cities and the record are read with FOR UPDATE, always in that order, so concurrent inserts and
 * updates of the hierarchy wait until the end. The checks run on the locked rows; each update must change exactly one
 * row; the final state is verified before the commit. A lock wait timeout or a deadlock ends the migration with
 * everything rolled back. governorates.city_id, the records that point to locations and product locations are never
 * written; only cities.governorate_id changes (its updated_at is left as it was).
 *
 * Re-running is harmless: linked cities are then category A, and the record must match them. down() reverses only the
 * recorded changes, and only while each city still holds the governorate this migration gave it; otherwise it stops
 * before changing anything. MySQL and MariaDB run the transaction this way; SQLite (the test suite) serialises writers
 * on its own, so its locks are not a guarantee of the MySQL behaviour.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    private const RECORD = 'location_hierarchy_backfills';

    /** Test hook: called inside the transaction right after the locks are taken (never set in production). */
    public ?Closure $afterLocking = null;

    public function up(): void
    {
        $counts = $this->atomically('up', function (array $governorates, array $cities, array $record): array {
            $claims = $this->claims($governorates);
            $this->assertReferences($governorates, $cities);
            $this->assertNoAmbiguity($cities, $claims);
            $this->assertRecordMatches($cities, $record, 'Re-running');

            $backfill = [];
            $counts = ['A' => 0, 'B' => 0, 'D' => 0];
            foreach ($cities as $cityId => $governorateId) {
                $claimants = $claims[$cityId] ?? [];
                if ($governorateId !== null) {
                    $counts['A']++;
                } elseif (count($claimants) === 1) {
                    $backfill[$cityId] = $claimants[0];
                    $counts['B']++;
                } else {
                    $counts['D']++;
                }
            }

            $now = now();
            foreach ($backfill as $cityId => $governorateId) {
                $changed = DB::table('cities')->where('id', $cityId)->whereNull('governorate_id')->update(['governorate_id' => $governorateId]);
                if ($changed !== 1) {
                    throw new RuntimeException("City {$cityId} could not be linked to governorate {$governorateId} ({$changed} rows changed). Nothing was changed.");
                }

                DB::table(self::RECORD)->insert([
                    'city_id' => $cityId,
                    'legacy_governorate_id' => $governorateId,
                    'previous_governorate_id' => null,
                    'assigned_governorate_id' => $governorateId,
                    'created_at' => $now,
                ]);
            }

            $this->assertFinalState($governorates, array_replace($cities, $backfill), count($record) + count($backfill));

            return $counts + ['record' => count($record) + count($backfill)];
        });

        Log::info('Location hierarchy backfill completed.', [
            'backfilled' => $counts['B'],
            'already_assigned' => $counts['A'],
            'left_unassigned' => $counts['D'],
            'recorded' => $counts['record'],
        ]);
    }

    public function down(): void
    {
        $reverted = $this->atomically('down', function (array $governorates, array $cities, array $record): int {
            $this->assertRecordMatches($cities, $record, 'Rolling back');

            foreach ($record as $row) {
                $changed = DB::table('cities')->where('id', $row->city_id)->where('governorate_id', $row->assigned_governorate_id)
                    ->update(['governorate_id' => $row->previous_governorate_id]);
                if ($changed !== 1) {
                    throw new RuntimeException("City {$row->city_id} could not be returned to its previous governorate ({$changed} rows changed). Nothing was changed.");
                }

                DB::table(self::RECORD)->where('id', $row->id)->delete();
                $cities[$row->city_id] = $row->previous_governorate_id === null ? null : (int) $row->previous_governorate_id;
            }

            $this->assertFinalState($governorates, $cities, 0);

            return count($record);
        });

        Log::info('Location hierarchy backfill rolled back.', ['reverted' => $reverted]);
    }

    /**
     * Runs $work in one transaction after locking governorates, cities and the record (in that order), and hands it
     * the locked rows. On MySQL and MariaDB the session's isolation level is REPEATABLE READ for the transaction and
     * restored afterwards.
     */
    private function atomically(string $direction, Closure $work): mixed
    {
        $connection = DB::connection();
        $locking = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true);

        if ($locking && $connection->transactionLevel() > 0) {
            throw new RuntimeException('The location hierarchy backfill must start outside a transaction, so its isolation level and locks apply. Nothing was changed.');
        }

        $previous = $locking ? $this->isolationLevel($connection) : null;
        if ($locking) {
            $connection->statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }

        try {
            return $connection->transaction(function () use ($connection, $locking, $direction, $work) {
                if ($locking && $this->isolationLevel($connection) !== 'REPEATABLE-READ') {
                    throw new RuntimeException('The transaction is not REPEATABLE READ, so its locks would not cover new rows. Nothing was changed.');
                }

                $governorates = DB::table('governorates')->orderBy('id')->lockForUpdate()->get(['id', 'city_id'])
                    ->mapWithKeys(fn ($row) => [(int) $row->id => $row->city_id === null ? null : (int) $row->city_id])->all();
                $cities = DB::table('cities')->orderBy('id')->lockForUpdate()->get(['id', 'governorate_id'])
                    ->mapWithKeys(fn ($row) => [(int) $row->id => $row->governorate_id === null ? null : (int) $row->governorate_id])->all();
                $record = DB::table(self::RECORD)->orderBy('id')->lockForUpdate()->get()->all();

                if ($this->afterLocking) {
                    ($this->afterLocking)($direction);
                }

                return $work($governorates, $cities, $record);
            });
        } catch (Throwable $exception) {
            Log::warning('Location hierarchy backfill stopped; nothing was changed.', ['direction' => $direction, 'reason' => $exception->getMessage()]);

            throw $exception;
        } finally {
            if ($locking && in_array($previous, ['READ-UNCOMMITTED', 'READ-COMMITTED', 'REPEATABLE-READ', 'SERIALIZABLE'], true)) {
                $connection->statement('SET SESSION TRANSACTION ISOLATION LEVEL '.str_replace('-', ' ', $previous));
            }
        }
    }

    private function isolationLevel(Connection $connection): string
    {
        try {
            return strtoupper((string) $connection->selectOne('SELECT @@SESSION.transaction_isolation AS level')->level);
        } catch (QueryException) {
            // MariaDB before 11.1 names it tx_isolation.
            return strtoupper((string) $connection->selectOne('SELECT @@SESSION.tx_isolation AS level')->level);
        }
    }

    /**
     * @param  array<int, int|null>  $governorates  governorate id => legacy city_id
     * @return array<int, list<int>> city id => the governorates claiming it (legacy link), by id
     */
    private function claims(array $governorates): array
    {
        $claims = [];
        foreach ($governorates as $governorateId => $cityId) {
            if ($cityId !== null) {
                $claims[$cityId][] = $governorateId;
            }
        }

        return $claims;
    }

    /**
     * @param  array<int, int|null>  $governorates
     * @param  array<int, int|null>  $cities
     */
    private function assertReferences(array $governorates, array $cities): void
    {
        $citiesToMissing = array_keys(array_filter($cities, fn ($governorateId) => $governorateId !== null && ! array_key_exists($governorateId, $governorates)));
        $governoratesToMissing = array_keys(array_filter($governorates, fn ($cityId) => $cityId !== null && ! array_key_exists($cityId, $cities)));

        if ($citiesToMissing !== [] || $governoratesToMissing !== []) {
            throw new RuntimeException(sprintf(
                'Invalid location references: cities pointing to a missing governorate [%s]; governorates pointing to a missing city [%s]. Nothing was changed.',
                implode(', ', $citiesToMissing), implode(', ', $governoratesToMissing)
            ));
        }
    }

    /**
     * @param  array<int, int|null>  $cities
     * @param  array<int, list<int>>  $claims
     */
    private function assertNoAmbiguity(array $cities, array $claims): void
    {
        $ambiguous = [];
        $conflicting = [];
        foreach ($cities as $cityId => $governorateId) {
            $claimants = $claims[$cityId] ?? [];
            if ($governorateId === null && count($claimants) > 1) {
                $ambiguous[] = "city {$cityId} claimed by governorates ".implode(', ', $claimants);
            }
            $others = array_values(array_diff($claimants, [$governorateId]));
            if ($governorateId !== null && $others !== []) {
                $conflicting[] = "city {$cityId} belongs to governorate {$governorateId} but is claimed by governorates ".implode(', ', $others);
            }
        }

        if ($ambiguous !== [] || $conflicting !== []) {
            throw new RuntimeException(sprintf(
                'The legacy location links need a manual decision. Several claimants (C): [%s]. Conflicts (E): [%s]. Nothing was changed.',
                implode('; ', $ambiguous), implode('; ', $conflicting)
            ));
        }
    }

    /**
     * Each recorded change must still be in place: its city exists and holds the governorate this migration gave it.
     *
     * @param  array<int, int|null>  $cities
     * @param  list<object>  $record
     */
    private function assertRecordMatches(array $cities, array $record, string $action): void
    {
        $stale = [];
        foreach ($record as $row) {
            if (! array_key_exists((int) $row->city_id, $cities)) {
                $stale[] = "city {$row->city_id} was deleted";
            } elseif ($cities[(int) $row->city_id] !== (int) $row->assigned_governorate_id) {
                $stale[] = "city {$row->city_id} now belongs to ".($cities[(int) $row->city_id] === null ? 'no governorate' : 'governorate '.$cities[(int) $row->city_id])." instead of {$row->assigned_governorate_id}";
            }
        }

        if ($stale !== []) {
            throw new RuntimeException(sprintf(
                '%s the location hierarchy backfill is refused: the record no longer matches the cities (%s). Nothing was changed.',
                $action, implode('; ', $stale)
            ));
        }
    }

    /**
     * Re-reads the hierarchy inside the transaction: the cities hold exactly $expected, the governorates are unchanged,
     * and the record has $recorded rows.
     *
     * @param  array<int, int|null>  $governorates
     * @param  array<int, int|null>  $expected
     */
    private function assertFinalState(array $governorates, array $expected, int $recorded): void
    {
        $cities = DB::table('cities')->orderBy('id')->get(['id', 'governorate_id'])
            ->mapWithKeys(fn ($row) => [(int) $row->id => $row->governorate_id === null ? null : (int) $row->governorate_id])->all();
        $after = DB::table('governorates')->orderBy('id')->get(['id', 'city_id'])
            ->mapWithKeys(fn ($row) => [(int) $row->id => $row->city_id === null ? null : (int) $row->city_id])->all();

        if ($cities !== $expected || $after !== $governorates || DB::table(self::RECORD)->count() !== $recorded) {
            throw new RuntimeException('The location hierarchy is not in the expected state after the backfill step. Nothing was changed.');
        }
    }
};
