<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\SiteAvailability\DeploymentAvailabilityCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * DEC-SA-08: the cPanel deployment's site-availability check (DeploymentAvailabilityCheck,
 * `site-availability:deploy-check`, deploy/cpanel-deploy.sh step 4b). Deployable only while effectively coming_soon:
 * forced by `.env`, or `auto` with coming_soon stored. Everything else fails: other stored states, an unreadable
 * stored state, a missing, repeated or unexpected `.env` value, and a loaded override (configuration cache or process
 * environment) that differs from `.env`. Nothing is written and no `.env` value is reported.
 */
class SiteAvailabilityDeployCheckTest extends TestCase
{
    use RefreshDatabase;

    private string $envPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envPath = tempnam(sys_get_temp_dir(), 'hirfah-env-');
    }

    protected function tearDown(): void
    {
        @unlink($this->envPath);

        parent::tearDown();
    }

    public function test_forced_coming_soon_passes_without_reading_the_database(): void
    {
        $this->store('open');
        Schema::drop('settings');
        DB::enableQueryLog();

        $report = $this->check("APP_KEY=base64:secret-key-value\nHIRFAH_SITE_AVAILABILITY=coming_soon\n", 'coming_soon');

        $this->assertSame('PASS', $report['result'], $report['reason']);
        $this->assertSame(['coming_soon', 'environment', 'absent'], [$report['state'], $report['source'], $report['config_cache']]);
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_auto_passes_only_when_coming_soon_is_stored(): void
    {
        $this->store('coming_soon');

        $report = $this->check("HIRFAH_SITE_AVAILABILITY=auto\n", 'auto');

        $this->assertSame('PASS', $report['result'], $report['reason']);
        $this->assertSame(['auto', 'coming_soon', 'database'], [$report['env_override'], $report['state'], $report['source']]);
    }

    public function test_auto_fails_when_the_stored_state_is_open_or_maintenance(): void
    {
        foreach (['open', 'maintenance'] as $stored) {
            $this->store($stored);

            $report = $this->check("HIRFAH_SITE_AVAILABILITY=auto\n", 'auto');

            $this->assertSame('FAIL', $report['result'], $stored);
            $this->assertSame([$stored, 'database'], [$report['state'], $report['source']]);
            $this->assertStringContainsString("'{$stored}'", $report['reason']);
        }
    }

    public function test_auto_fails_when_the_stored_state_cannot_be_determined(): void
    {
        // No settings row: the gate would fall back to open.
        $report = $this->check("HIRFAH_SITE_AVAILABILITY=auto\n", 'auto');
        $this->assertSame(['FAIL', 'unknown', 'default'], [$report['result'], $report['state'], $report['source']]);
        $this->assertStringContainsString('could not be read', $report['reason']);

        // Unknown stored value (bypassing the CHECK constraint, which normally prevents it).
        Setting::singleton();
        DB::statement('PRAGMA ignore_check_constraints = ON');
        DB::table('settings')->update(['site_availability_mode' => 'closed']);
        DB::statement('PRAGMA ignore_check_constraints = OFF');
        $report = $this->check("HIRFAH_SITE_AVAILABILITY=auto\n", 'auto');
        $this->assertSame(['FAIL', 'unknown', 'default'], [$report['result'], $report['state'], $report['source']]);

        // Column missing (migration not applied).
        Schema::table('settings', fn ($table) => $table->dropColumn('site_availability_mode'));
        $report = $this->check("HIRFAH_SITE_AVAILABILITY=auto\n", 'auto');
        $this->assertSame(['FAIL', 'unknown'], [$report['result'], $report['state']]);

        // Table missing or database unreachable.
        Schema::drop('settings');
        $report = $this->check("HIRFAH_SITE_AVAILABILITY=auto\n", 'auto');
        $this->assertSame(['FAIL', 'unknown'], [$report['result'], $report['state']]);
    }

    public function test_unexpected_env_values_fail_without_showing_the_value(): void
    {
        $this->store('coming_soon');

        foreach (['open', 'maintenance', 'AUTO', 'Coming_Soon', ' closed', '', 'coming_soon2'] as $value) {
            $report = $this->check("HIRFAH_SITE_AVAILABILITY=\"{$value}\"\n", $value);

            $this->assertSame(['FAIL', 'invalid', 'unknown'], [$report['result'], $report['env_override'], $report['state']], var_export($value, true));
            if ($value !== '') {
                $this->assertStringNotContainsString($value, implode("\n", $report), var_export($value, true));
            }
        }
    }

    public function test_a_missing_repeated_or_unparsable_key_fails(): void
    {
        $this->store('coming_soon');
        $cases = [
            'missing key' => "APP_ENV=production\n",
            'commented out' => "# HIRFAH_SITE_AVAILABILITY=coming_soon\n",
            'repeated' => "HIRFAH_SITE_AVAILABILITY=coming_soon\nHIRFAH_SITE_AVAILABILITY=auto\n",
            'unparsable' => "HIRFAH_SITE_AVAILABILITY=\"coming_soon\n",
        ];

        foreach ($cases as $case => $env) {
            $report = $this->check($env, 'coming_soon');

            $this->assertSame(['FAIL', 'missing'], [$report['result'], $report['env_override']], $case);
        }

        @unlink($this->envPath);
        $report = (new DeploymentAvailabilityCheck($this->envPath, false))->run();
        $this->assertSame(['FAIL', 'missing'], [$report['result'], $report['env_override']], 'no .env file');
    }

    public function test_quoted_exported_and_commented_values_are_parsed_like_laravel(): void
    {
        $this->store('coming_soon');

        foreach (["HIRFAH_SITE_AVAILABILITY='auto'", 'export HIRFAH_SITE_AVAILABILITY=auto', 'HIRFAH_SITE_AVAILABILITY=auto # closed', "HIRFAH_SITE_AVAILABILITY=auto\r"] as $line) {
            $report = $this->check($line."\n", 'auto');

            $this->assertSame('PASS', $report['result'], $line.': '.$report['reason']);
        }
    }

    public function test_a_stale_configuration_cache_fails(): void
    {
        $this->store('coming_soon');

        // .env now says auto, the cache still holds an earlier value: whichever state either would give, a mismatch fails.
        foreach (['open', 'coming_soon', null] as $cachedValue) {
            $report = $this->check("HIRFAH_SITE_AVAILABILITY=auto\n", $cachedValue, cached: true);

            $this->assertSame(['FAIL', 'present'], [$report['result'], $report['config_cache']], var_export($cachedValue, true));
            $this->assertStringContainsString('stale cache', $report['reason']);
        }

        // A cache consistent with .env is accepted.
        $report = $this->check("HIRFAH_SITE_AVAILABILITY=auto\n", 'auto', cached: true);
        $this->assertSame(['PASS', 'present'], [$report['result'], $report['config_cache']]);
    }

    public function test_a_process_environment_that_shadows_env_fails(): void
    {
        $this->store('coming_soon');

        $report = $this->check("HIRFAH_SITE_AVAILABILITY=coming_soon\n", 'open');

        $this->assertSame('FAIL', $report['result']);
        $this->assertStringContainsString('process environment', $report['reason']);
    }

    public function test_the_check_writes_nothing(): void
    {
        $this->store('coming_soon');
        $before = DB::table('settings')->get()->toArray();
        DB::enableQueryLog();

        $this->check("HIRFAH_SITE_AVAILABILITY=auto\n", 'auto');

        $statements = array_column(DB::getQueryLog(), 'query');
        $this->assertCount(1, $statements);
        $this->assertStringStartsWith('select', strtolower($statements[0]));
        $this->assertEquals($before, DB::table('settings')->get()->toArray());
    }

    public function test_the_command_prints_the_result_and_sets_the_exit_code(): void
    {
        $this->store('coming_soon');
        file_put_contents($this->envPath, "DB_PASSWORD=do-not-print\nHIRFAH_SITE_AVAILABILITY=auto\n");
        config(['site-availability.override' => 'auto']);
        $this->app->instance(DeploymentAvailabilityCheck::class, new DeploymentAvailabilityCheck($this->envPath, false));

        $this->assertSame(0, Artisan::call('site-availability:deploy-check'));
        $output = Artisan::output();
        $this->assertStringContainsString("RESULT=PASS\nENV_OVERRIDE=auto\nCONFIG_CACHE=absent\nSTATE=coming_soon\nSOURCE=database\n", $output);
        $this->assertStringNotContainsString('do-not-print', $output);

        $this->store('open');
        $this->assertSame(1, Artisan::call('site-availability:deploy-check'));
        $this->assertStringContainsString("RESULT=FAIL\n", Artisan::output());
    }

    public function test_the_command_reports_an_unexpected_error_by_class_only(): void
    {
        $this->app->instance(DeploymentAvailabilityCheck::class, new class extends DeploymentAvailabilityCheck
        {
            public function run(): array
            {
                throw new \RuntimeException('SQLSTATE: access denied for user secret-user@db-host');
            }
        });

        $this->assertSame(1, Artisan::call('site-availability:deploy-check'));
        $output = Artisan::output();
        $this->assertStringContainsString('RESULT=FAIL', $output);
        $this->assertStringContainsString('REASON=unexpected error (RuntimeException)', $output);
        $this->assertStringNotContainsString('secret-user', $output);
    }

    public function test_the_deploy_script_uses_the_check_instead_of_requiring_a_forced_coming_soon(): void
    {
        $script = file_get_contents(base_path('deploy/cpanel-deploy.sh'));

        $this->assertStringContainsString('artisan site-availability:deploy-check --no-interaction --no-ansi 2>/dev/null', $script);
        $this->assertStringContainsString('availability_problem="$(availability_report_error)"', $script);
        $this->assertStringContainsString('[ "$state" = coming_soon ] || { echo "RESULT=PASS with STATE=$state"; return 0; }', $script);
        $this->assertStringNotContainsString('HIRFAH_SITE_AVAILABILITY=coming_soon APP_ENV', $script);
        $this->assertStringNotContainsString('bootstrap/cache/config.php"; echo', $script);
        // The other .env checks stay.
        $this->assertStringContainsString('for pair in APP_ENV=production APP_DEBUG=false; do', $script);
    }

    /** Writes $env as the .env file, sets the loaded override to $loaded, and runs the check. */
    private function check(string $env, ?string $loaded, bool $cached = false): array
    {
        file_put_contents($this->envPath, $env);
        config(['site-availability.override' => $loaded]);

        return (new DeploymentAvailabilityCheck($this->envPath, $cached))->run();
    }

    private function store(string $mode): void
    {
        Setting::singleton();
        DB::table('settings')->update(['site_availability_mode' => $mode]);
    }
}
