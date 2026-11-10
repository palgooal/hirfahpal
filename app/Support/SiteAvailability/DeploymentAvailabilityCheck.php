<?php

namespace App\Support\SiteAvailability;

use Dotenv\Dotenv;
use Throwable;

/**
 * SITE-AVAILABILITY (DEC-SA-08, §6.6, §10): the cPanel deployment check (deploy/cpanel-deploy.sh, through
 * `php artisan site-availability:deploy-check`). A deployment may proceed only while the storefront is effectively
 * `coming_soon`, whichever way that is set:
 *
 *  - `.env` defines HIRFAH_SITE_AVAILABILITY exactly once, as `coming_soon` (forced, no database read) or `auto` (the
 *    state saved in settings.site_availability_mode). Missing, repeated or any other value fails: `auto` is never
 *    assumed, and `open`/`maintenance` are not deployable states.
 *  - The override the application actually loaded (the configuration cache when there is one, otherwise `.env` and
 *    the process environment) equals the `.env` value; a stale cache or an environment variable that shadows `.env`
 *    fails instead of being ignored.
 *  - The state resolved by SiteAvailability, the same resolution the gate uses, is `coming_soon`. With `auto` it must
 *    come from the database: the service fails open on a missing table, column or row, an unknown value or an
 *    unreachable database (§6.5), and any such outcome fails here.
 *
 * Read-only: one line of `.env` is parsed (no other key is read), one settings read in `auto`, nothing written. The
 * result holds only the fixed reasons below and the allowed values; nothing else from `.env` or the database
 * connection is ever reported.
 */
class DeploymentAvailabilityCheck
{
    public const KEY = 'HIRFAH_SITE_AVAILABILITY';

    /** The `.env` values a deployment accepts (byte-exact). */
    public const ACCEPTED = ['coming_soon', 'auto'];

    public function __construct(
        private readonly ?string $envPath = null,
        private readonly ?bool $configCached = null,
    ) {}

    /**
     * @return array{result: 'PASS'|'FAIL', env_override: string, config_cache: string, state: string, source: string, reason: string}
     */
    public function run(): array
    {
        $cached = $this->configCached ?? app()->configurationIsCached();
        $report = [
            'result' => 'FAIL',
            'env_override' => 'unknown',
            'config_cache' => $cached ? 'present' : 'absent',
            'state' => 'unknown',
            'source' => 'none',
            'reason' => '',
        ];

        $env = $this->envValue();

        if (! in_array($env, self::ACCEPTED, true)) {
            $report['env_override'] = $env === null ? 'missing' : 'invalid';
            $report['reason'] = match ($env) {
                null => '.env HIRFAH_SITE_AVAILABILITY could not be read (missing .env, missing or repeated key, or invalid line)',
                default => ".env HIRFAH_SITE_AVAILABILITY is not 'coming_soon' or 'auto' (value not shown)",
            };

            return $report;
        }

        $report['env_override'] = $env;

        if (config('site-availability.override') !== $env) {
            $report['reason'] = $cached
                ? 'the configuration cache holds a different site-availability override than .env (stale cache: clear it first; value not shown)'
                : 'the process environment overrides .env HIRFAH_SITE_AVAILABILITY (value not shown)';

            return $report;
        }

        $availability = app(SiteAvailability::class);
        $availability->forget();
        $state = $availability->current();
        $source = $availability->source();
        $report['state'] = $state->value;
        $report['source'] = $source;
        $expectedSource = $env === 'auto' ? 'database' : 'environment';

        if ($source !== $expectedSource) {
            $report['state'] = 'unknown';
            $report['reason'] = $env === 'auto'
                ? 'settings.site_availability_mode could not be read (database unreachable, migration missing, no settings row or unknown value)'
                : 'the environment override is not in force';

            return $report;
        }

        if ($state !== SiteAvailabilityState::ComingSoon) {
            $report['reason'] = "the effective site availability is '{$state->value}' (from {$source}), not 'coming_soon'";

            return $report;
        }

        $report['result'] = 'PASS';
        $report['reason'] = $env === 'auto'
            ? 'auto: settings.site_availability_mode is coming_soon'
            : 'forced coming_soon by the environment override';

        return $report;
    }

    /**
     * The value of the single HIRFAH_SITE_AVAILABILITY line of `.env`, parsed by the same parser Laravel uses; null
     * when the file is unreadable, the key is absent or defined more than once, or the line does not parse.
     */
    private function envValue(): ?string
    {
        $path = $this->envPath ?? app()->environmentFilePath();
        $content = is_file($path) ? @file_get_contents($path) : false;

        if ($content === false) {
            return null;
        }

        $lines = preg_grep('/^\s*(?:export\s+)?'.self::KEY.'\s*=/', preg_split('/\r\n|\r|\n/', $content));

        if (count($lines) !== 1) {
            return null;
        }

        try {
            $value = Dotenv::parse(reset($lines))[self::KEY] ?? null;
        } catch (Throwable) {
            return null;
        }

        return is_string($value) ? $value : null;
    }
}
