<?php

namespace App\Support\SiteAvailability;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SITE-AVAILABILITY Phase 3B (docs/deployment/HIRFAH-SITE-AVAILABILITY-DESIGN.md §7): the stored state as the Super
 * Admin settings card sees it. Callers authorize first (Super Admin only); nothing here checks the actor.
 *
 * schemaReady() reports whether the Phase 1 migration has been applied (recorded and its three columns exist). Database errors are
 * not caught: an unexpected failure surfaces as an error, never as a successful read or save.
 */
class SiteAvailabilitySettings
{
    public const MODE = 'site_availability_mode';

    public const CHANGED_AT = 'site_availability_changed_at';

    public const CHANGED_BY = 'site_availability_changed_by';

    /** The Phase 1 migration, as Laravel records it once up() has completed (up() verifies the whole schema first). */
    public const MIGRATION = '2026_10_20_000000_add_site_availability_to_settings_table';

    /**
     * Ready only when the migration is recorded as completed AND its three columns exist: columns alone may be a
     * partial or manual state that `migrate` itself would refuse. A missing migrations table means not ready. The
     * record is checked first, so an unmigrated database costs one existence check and one query.
     */
    public static function schemaReady(): bool
    {
        $migrations = (string) (config('database.migrations.table') ?? 'migrations');

        return Schema::hasTable($migrations)
            && DB::table($migrations)->where('migration', self::MIGRATION)->exists()
            && Schema::hasColumns('settings', [self::MODE, self::CHANGED_AT, self::CHANGED_BY]);
    }

    /**
     * Everything the card shows.
     *
     * @return array{schema_ready: bool, effective: SiteAvailabilityState, source: string, override: ?SiteAvailabilityState, stored: ?SiteAvailabilityState, changed_at: mixed, changed_by: ?string, changed_by_deleted: bool}
     */
    public static function card(Setting $setting): array
    {
        $availability = app(SiteAvailability::class);
        $ready = self::schemaReady();
        // Read from the row itself: a model just created by Setting::singleton() does not hold the column defaults.
        $row = $ready ? DB::table('settings')->where('id', $setting->getKey())->first([self::MODE, self::CHANGED_AT, self::CHANGED_BY]) : null;
        $changedById = $row?->{self::CHANGED_BY};
        $changedAt = $row?->{self::CHANGED_AT} !== null ? Carbon::parse($row->{self::CHANGED_AT}) : null;
        $changedBy = $changedById !== null ? Admin::query()->whereKey($changedById)->value('name') : null;

        return [
            'schema_ready' => $ready,
            'effective' => $availability->current(),
            'source' => $availability->source(),
            'override' => $availability->override(),
            'stored' => $row !== null ? SiteAvailabilityState::tryFrom((string) $row->{self::MODE}) : null,
            'changed_at' => $changedAt,
            'changed_by' => $changedBy,
            // A change whose admin no longer exists (the foreign key clears changed_by on deletion; the date stays).
            'changed_by_deleted' => $changedAt !== null && $changedBy === null,
        ];
    }

    /**
     * Stores $state on the settings singleton inside a transaction that locks the row. Returns false (nothing
     * written) when it is already the stored state. Written with the query builder: the columns are never
     * mass-assignable on Setting.
     */
    public static function store(SiteAvailabilityState $state, Admin $admin): bool
    {
        Setting::singleton();

        return DB::transaction(function () use ($state, $admin): bool {
            $current = DB::table('settings')->where('singleton_key', 1)->lockForUpdate()->value(self::MODE);

            if ($current === $state->value) {
                return false;
            }

            $now = now();

            DB::table('settings')->where('singleton_key', 1)->update([
                self::MODE => $state->value,
                self::CHANGED_AT => $now,
                self::CHANGED_BY => $admin->getKey(),
                'updated_at' => $now,
            ]);

            return true;
        });
    }
}
