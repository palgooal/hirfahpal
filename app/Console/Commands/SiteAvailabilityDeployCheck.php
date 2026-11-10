<?php

namespace App\Console\Commands;

use App\Support\SiteAvailability\DeploymentAvailabilityCheck;
use Illuminate\Console\Command;
use Throwable;

/**
 * Read-only deployment check (deploy/cpanel-deploy.sh): passes only while the storefront is effectively coming_soon
 * (DeploymentAvailabilityCheck). Prints key=value lines for the deploy script; changes nothing.
 */
class SiteAvailabilityDeployCheck extends Command
{
    protected $signature = 'site-availability:deploy-check';

    protected $description = 'Check (read-only) that the effective site availability allows a deployment (coming_soon)';

    public function handle(DeploymentAvailabilityCheck $check): int
    {
        try {
            $report = $check->run();
        } catch (Throwable $exception) {
            // The class name only: a message may carry connection details.
            $report = [
                'result' => 'FAIL',
                'env_override' => 'unknown',
                'config_cache' => 'unknown',
                'state' => 'unknown',
                'source' => 'none',
                'reason' => 'unexpected error ('.$exception::class.')',
            ];
        }

        // LF on every platform: the deploy script parses these lines with sed.
        foreach ($report as $key => $value) {
            $this->output->write(strtoupper($key).'='.$value."\n");
        }

        return $report['result'] === 'PASS' ? self::SUCCESS : self::FAILURE;
    }
}
