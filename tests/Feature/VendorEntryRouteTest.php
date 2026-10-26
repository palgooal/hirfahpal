<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Setting;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * GET /vendor (vendor.entry) sends every visitor through the dashboard's own gates,
 * and the development router (server.php) lets that exact path reach Laravel while
 * public/vendor/ assets stay static files.
 */
class VendorEntryRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
    }

    public function test_entry_route_redirects_to_the_vendor_dashboard(): void
    {
        $this->assertSame(url('/vendor'), route('vendor.entry'));
        $this->get('/vendor')->assertRedirect('/vendor/dashboard');
    }

    public function test_guest_ends_on_the_vendor_login(): void
    {
        $this->get('/vendor')->assertRedirect('/vendor/dashboard');
        $this->get('/vendor/dashboard')->assertRedirect(route('vendor.login'));
    }

    public function test_approved_vendor_ends_on_the_dashboard(): void
    {
        $this->actingAs(Vendor::factory()->approved()->create(), 'vendor');

        $this->get('/vendor')->assertRedirect('/vendor/dashboard');
        $this->get('/vendor/dashboard')->assertOk();
    }

    public function test_unapproved_vendor_ends_on_the_approval_status_page(): void
    {
        $vendors = [
            'pending' => Vendor::factory()->withProfile('pending')->create(),
            'rejected' => Vendor::factory()->withProfile('rejected', 'Incomplete profile.')->create(),
            'no profile' => Vendor::factory()->create(),
        ];

        foreach ($vendors as $case => $vendor) {
            $this->actingAs($vendor, 'vendor');

            $this->get('/vendor')->assertRedirect('/vendor/dashboard');
            $this->get('/vendor/dashboard')->assertRedirect(route('vendor.approval-status'), $case);
            Auth::forgetGuards();
        }
    }

    public function test_inactive_account_is_signed_out_by_the_existing_status_check(): void
    {
        foreach (['blocked', 'pending'] as $status) {
            $this->actingAs(Vendor::factory()->approved()->create(['status' => $status]), 'vendor');

            $this->get('/vendor')
                ->assertRedirect(route('vendor.login'))
                ->assertSessionHasErrors(['login' => __('auth.inactive')]);
            $this->assertGuest('vendor');
            Auth::forgetGuards();
        }
    }

    public function test_vendor_login_and_public_vendors_page_are_unchanged(): void
    {
        $this->get(route('vendor.login'))->assertOk();
        $this->get('/vendors')->assertOk();
        $this->assertSame(url('/vendors'), route('vendors'));
    }

    public function test_entry_route_is_open_during_coming_soon_and_closed_during_maintenance(): void
    {
        $this->siteState('coming_soon');
        $this->get('/vendor')->assertRedirect('/vendor/dashboard');

        $this->siteState('maintenance');
        $this->get('/vendor')->assertStatus(503);
    }

    public function test_dev_router_sends_only_the_exact_vendor_path_to_laravel(): void
    {
        $public = storage_path('framework/testing/vendor-entry-router');
        File::ensureDirectoryExists($public.'/vendor/media-library/js');
        File::put($public.'/index.php', "<?php echo 'LARAVEL';\n");
        File::put($public.'/vendor/media-library/js/media-picker.js', '// asset');

        try {
            $this->assertSame('LARAVEL', $this->route('/vendor', $public));
            $this->assertSame('LARAVEL', $this->route('/vendor/', $public));
            $this->assertSame('LARAVEL', $this->route('/vendor/login', $public));
            $this->assertSame('LARAVEL', $this->route('/vendors', $public));
            $this->assertSame('STATIC', $this->route('/vendor/media-library/js/media-picker.js', $public));
            $this->assertSame('STATIC', $this->route('/vendor/media-library', $public), 'Other folders keep the built-in server behavior.');
        } finally {
            File::deleteDirectory($public);
        }

        $this->assertFileExists(public_path('vendor/media-library/js/media-picker.js'), 'The real asset folder is untouched.');
    }

    /**
     * Runs the project router for one URI in a separate PHP process: "STATIC" when it hands the
     * request to the built-in server (return false), otherwise what the front controller printed.
     */
    private function route(string $uri, string $public): string
    {
        $script = sprintf(
            '$_SERVER = ["REQUEST_URI" => %s, "REQUEST_METHOD" => "GET", "REMOTE_ADDR" => "127.0.0.1", "REMOTE_PORT" => "1"]; chdir(%s); ob_start(); $handled = include %s; $out = ob_get_clean(); echo $handled === false ? "STATIC" : $out;',
            var_export($uri, true),
            var_export($public, true),
            var_export(base_path('server.php'), true),
        );

        $process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), $errors);

        // The router logs each request to php://stdout before the front controller; keep the front controller's output.
        return trim((string) preg_replace('/^\[.*\] .* URI: .*$/m', '', $output));
    }

    private function siteState(string $mode): void
    {
        config(['site-availability.override' => 'auto']);
        Setting::singleton();
        DB::table('settings')->update(['site_availability_mode' => $mode]);
    }
}
