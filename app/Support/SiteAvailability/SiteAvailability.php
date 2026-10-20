<?php

namespace App\Support\SiteAvailability;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SITE-AVAILABILITY (§2.1, §6.3, §6.5): resolves the current availability state.
 *
 * Precedence: the environment override (config site-availability.override) when it is not `auto`; then
 * settings.site_availability_mode of the singleton row; then `open`. A forced override never reads the database, so
 * it works before the migrations. Any failure to read the setting (missing table, column or row, unknown value,
 * connection error) resolves to `open`, logged without request data. Never throws.
 *
 * The result is memoized on the current request (its attributes), so one request reads the setting at most once and
 * a later request never sees a stale value.
 */
class SiteAvailability
{
    public const ABILITY_BYPASS = 'maintenance.bypass';

    private const MEMO = '_site_availability';

    public function __construct(private readonly Request $request) {}

    public function current(): SiteAvailabilityState
    {
        return $this->resolve()['state'];
    }

    /** Which input decided: 'environment', 'database' or 'default'. */
    public function source(): string
    {
        return $this->resolve()['source'];
    }

    /** The override in force from the loaded configuration, or null for `auto` (and for an invalid value). */
    public function override(): ?SiteAvailabilityState
    {
        $value = config('site-availability.override', 'auto');
        $normalized = is_string($value) ? strtolower(trim($value)) : '';

        if ($normalized === '' || $normalized === 'auto') {
            return null;
        }

        $state = SiteAvailabilityState::tryFrom($normalized);

        if ($state === null) {
            Log::warning('Ignoring an invalid HIRFAH_SITE_AVAILABILITY value; using auto.', ['value' => is_scalar($value) ? (string) $value : gettype($value)]);
        }

        return $state;
    }

    /**
     * SA-04, SA-05: an active Super Admin always bypasses; any other active admin only with maintenance.bypass.
     * Bypass never grants anything else: every route keeps its own checks. The admin status is checked here because
     * the gate runs before EnsureAccountIsActive.
     */
    public function canBypass(?Admin $admin): bool
    {
        if ($admin === null || $admin->status !== 'active') {
            return false;
        }

        return $admin->isSuperAdmin() || $admin->hasAbility(self::ABILITY_BYPASS);
    }

    /** Drops the memoized result (after a change in the same request, and for tests). */
    public function forget(): void
    {
        $this->request->attributes->remove(self::MEMO);
    }

    /** @return array{state: SiteAvailabilityState, source: string} */
    private function resolve(): array
    {
        $memo = $this->request->attributes->get(self::MEMO);

        if (is_array($memo)) {
            return $memo;
        }

        $resolved = match (true) {
            ($forced = $this->override()) !== null => ['state' => $forced, 'source' => 'environment'],
            ($stored = $this->stored()) !== null => ['state' => $stored, 'source' => 'database'],
            default => ['state' => SiteAvailabilityState::Open, 'source' => 'default'],
        };

        $this->request->attributes->set(self::MEMO, $resolved);

        return $resolved;
    }

    private function stored(): ?SiteAvailabilityState
    {
        try {
            $value = DB::table('settings')->where('singleton_key', 1)->value('site_availability_mode');
        } catch (Throwable $exception) {
            // Missing table or column (before the migration) or an unreachable database: fail open (§6.5).
            Log::warning('Site availability could not be read from settings; using open.', ['exception' => $exception::class]);

            return null;
        }

        if ($value === null) {
            return null;
        }

        $state = is_string($value) ? SiteAvailabilityState::tryFrom($value) : null;

        if ($state === null) {
            Log::warning('Unknown settings.site_availability_mode value; using open.');
        }

        return $state;
    }
}
