<?php

namespace Tests\Feature;

use App\Models\Language;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tests\TestCase;

/**
 * SITE-AVAILABILITY Phase 2B (docs/deployment/HIRFAH-SITE-AVAILABILITY-DESIGN.md §5.6, DEC-SA-09, D1–D4): requests no
 * route matches (unknown URLs, unsupported methods) get the availability response while the site is closed, through
 * the exception handler; open keeps Laravel's 404/405; exempt paths and static resources keep their 404; exceptions
 * thrown inside a matched route are never intercepted.
 */
class SiteAvailabilityUnknownUrlTest extends TestCase
{
    use RefreshDatabase;

    private const UNKNOWN = '/no-such-page/at-all';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Language::create(['name' => 'Arabic', 'native' => 'العربية', 'code' => 'ar', 'is_rtl' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'native' => 'English', 'code' => 'en', 'is_rtl' => false, 'is_active' => true]);
        config(['site-availability.retry_after' => 1800]);
    }

    // ---------------------------------------------------------------- unknown URLs, every method, every state

    public function test_unknown_get_and_head_in_every_state(): void
    {
        $this->force('open');
        $this->assertNormalNotFound($this->get(self::UNKNOWN));
        $this->assertNormalNotFound($this->call('HEAD', self::UNKNOWN));

        $this->force('coming_soon');
        $this->assertAvailability($this->get(self::UNKNOWN), 'coming_soon', 200);
        $head = $this->call('HEAD', self::UNKNOWN);
        $this->assertAvailabilityHeaders($head, 'coming_soon', 200);
        $this->assertSame('', $head->getContent());

        $this->force('maintenance');
        $this->assertAvailability($this->get(self::UNKNOWN), 'maintenance', 503);
        $head = $this->call('HEAD', self::UNKNOWN);
        $this->assertAvailabilityHeaders($head, 'maintenance', 503);
        $this->assertSame('', $head->getContent());
    }

    public function test_unknown_state_changing_methods_get_503_in_closed_states_and_404_when_open(): void
    {
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->force('open');
            $this->assertNormalNotFound($this->call($method, self::UNKNOWN), $method);

            foreach (['coming_soon', 'maintenance'] as $state) {
                $this->force($state);
                $this->assertAvailability($this->call($method, self::UNKNOWN), $state, 503, $method);
            }
        }
    }

    public function test_unknown_options_requests_d3(): void
    {
        $this->force('open');
        $this->assertNormalNotFound($this->call('OPTIONS', self::UNKNOWN));

        $this->force('coming_soon');
        $this->assertAvailability($this->call('OPTIONS', self::UNKNOWN), 'coming_soon', 503, 'OPTIONS');

        $this->force('maintenance');
        $this->assertAvailability($this->call('OPTIONS', self::UNKNOWN), 'maintenance', 503, 'OPTIONS');
    }

    public function test_options_on_a_matched_path_is_unchanged(): void
    {
        foreach (['open', 'coming_soon', 'maintenance'] as $state) {
            $this->force($state);

            // The router's automatic OPTIONS answer for a known path (no route middleware, no exception).
            $response = $this->call('OPTIONS', route('newsletter.store'));
            $response->assertOk();
            $this->assertSame('POST', $response->headers->get('Allow'), $state);
            $this->assertSame('', $response->getContent(), $state);
        }
    }

    // ---------------------------------------------------------------- D2: unsupported methods on known paths

    public function test_wrong_method_on_a_known_route(): void
    {
        $this->force('open');
        $this->call('POST', '/')->assertStatus(405)->assertHeader('Allow', 'GET, HEAD');
        $this->get(route('newsletter.store'))->assertStatus(405)->assertHeader('Allow', 'POST');

        $this->force('coming_soon');
        $this->assertAvailability($this->call('POST', '/'), 'coming_soon', 503, 'POST /');
        $this->assertAvailability($this->get(route('newsletter.store')), 'coming_soon', 200, 'GET newsletter');

        $this->force('maintenance');
        $this->assertAvailability($this->call('POST', '/'), 'maintenance', 503, 'POST /');
        $this->assertAvailability($this->get(route('newsletter.store')), 'maintenance', 503, 'GET newsletter');
    }

    // ---------------------------------------------------------------- JSON

    public function test_json_requests_get_the_json_body_with_the_same_statuses(): void
    {
        $this->force('open');
        $open = $this->getJson(self::UNKNOWN);
        $open->assertNotFound()->assertJsonMissingPath('state');

        $this->force('coming_soon');
        $this->getJson(self::UNKNOWN)->assertOk()->assertExactJson(['state' => 'coming_soon', 'message' => config('site-availability.messages.coming_soon')]);
        $this->postJson(self::UNKNOWN)->assertStatus(503)->assertJsonPath('state', 'coming_soon');
        $this->json('OPTIONS', self::UNKNOWN)->assertStatus(503)->assertJsonPath('state', 'coming_soon');

        $this->force('maintenance');
        $json = $this->getJson(self::UNKNOWN);
        $json->assertStatus(503)->assertExactJson(['state' => 'maintenance', 'message' => config('site-availability.messages.maintenance'), 'retry_after' => 1800]);
        $this->assertAvailabilityHeaders($json, 'maintenance', 503);
        $this->deleteJson(self::UNKNOWN)->assertStatus(503)->assertJsonPath('retry_after', 1800);
    }

    // ---------------------------------------------------------------- D1: static resources

    public function test_missing_static_resources_keep_their_404_in_every_state(): void
    {
        $paths = ['/build/assets/missing.js', '/build', '/assets/x/missing.png', '/assets-dashboard/missing.css', '/images/missing.jpg',
            '/storage/media/missing.webp', '/vendor/package/missing.js', '/favicon.ico', '/robots.txt'];

        foreach (['open', 'coming_soon', 'maintenance'] as $state) {
            $this->force($state);

            foreach ($paths as $path) {
                $this->assertNormalNotFound($this->get($path), $state.' '.$path);
            }
        }
    }

    public function test_static_rules_match_whole_segments_only(): void
    {
        $this->force('coming_soon');

        foreach (['/builder', '/buildx/app.js', '/assetsx/a.png', '/images-old/a.jpg', '/storages/a', '/favicon.ico/x', '/robots.txt.bak', '/x/build/app.js'] as $path) {
            $this->assertAvailability($this->get($path), 'coming_soon', 200, $path);
        }
    }

    public function test_the_vendor_static_prefix_also_covers_unknown_vendor_area_urls(): void
    {
        // Documented consequence of D1: the `vendor` prefix is shared with the vendor area. Matched vendor routes are
        // still gated by EnsureSiteIsAvailable; only unmatched /vendor/... URLs keep the normal 404.
        $this->force('maintenance');

        $this->assertNormalNotFound($this->get('/vendor/no-such-page'));
        $this->assertAvailability($this->get(route('vendor.login')), 'maintenance', 503, 'vendor.login', matched: true);
    }

    // ---------------------------------------------------------------- exempt paths

    public function test_exempt_paths_keep_their_normal_responses(): void
    {
        foreach (['coming_soon', 'maintenance'] as $state) {
            $this->force($state);

            $this->assertNormalNotFound($this->get('/admin/no-such-page'), $state.' admin');
            $this->assertNormalNotFound($this->get('/admin'), $state.' admin root');
            $this->assertNormalNotFound($this->get('/dashboard/no-such-page'), $state.' dashboard');
            $this->assertNormalNotFound($this->get('/change-locale/ar/extra'), $state.' change-locale');
            $this->call('POST', '/up')->assertStatus(405)->assertHeader('Allow', 'GET, HEAD');
            $this->call('POST', '/dashboard')->assertStatus(405);
            $this->get('/up')->assertOk();
        }
    }

    // ---------------------------------------------------------------- side effects and database

    public function test_no_session_cookie_no_csrf_failure_and_no_database_access_with_a_forced_state(): void
    {
        foreach (['coming_soon', 'maintenance'] as $state) {
            $this->force($state);
            DB::flushQueryLog();
            DB::enableQueryLog();

            foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
                $response = $this->call($method, self::UNKNOWN, ['name' => 'x', 'email' => 'x@example.com']);

                $this->assertNotSame(419, $response->status(), $method);
                $this->assertSame([], $response->headers->getCookies(), $state.' '.$method.' sets no cookie');
            }

            $this->assertSame([], DB::getQueryLog(), $state.': no query at all');
            DB::disableQueryLog();
        }

        $this->assertSame(0, DB::table('newsletter_subscriptions')->count());
        $this->assertSame(0, DB::table('contact_messages')->count());
    }

    public function test_a_forced_state_works_without_database_tables(): void
    {
        Schema::drop('settings');
        Schema::drop('languages');

        $this->force('maintenance');
        $this->assertAvailability($this->get(self::UNKNOWN), 'maintenance', 503);
        $this->assertAvailability($this->call('POST', '/'), 'maintenance', 503);

        $this->force('coming_soon');
        $this->assertAvailability($this->get(self::UNKNOWN), 'coming_soon', 200);
    }

    public function test_auto_mode_reads_only_the_settings_row(): void
    {
        $this->stored('maintenance');
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertAvailability($this->get(self::UNKNOWN), 'maintenance', 503);

        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('"settings"', $queries[0]);
    }

    public function test_a_database_failure_in_auto_mode_falls_back_to_the_normal_404(): void
    {
        config(['site-availability.override' => 'auto']);
        Schema::drop('settings');

        $this->assertNormalNotFound($this->get(self::UNKNOWN));
        $this->call('POST', '/')->assertStatus(405);
    }

    public function test_the_stored_state_is_used_in_auto_mode(): void
    {
        $this->stored('coming_soon');
        $this->assertAvailability($this->get(self::UNKNOWN), 'coming_soon', 200);

        $this->stored('open');
        $this->assertNormalNotFound($this->get(self::UNKNOWN));
    }

    // ---------------------------------------------------------------- matched routes are never intercepted

    public function test_exceptions_thrown_inside_a_matched_route_are_not_intercepted(): void
    {
        // Routes outside the `web` group, so the gate does not run and the exception comes from the route itself.
        Route::get('__site-availability-probe/abort', fn () => abort(404));
        Route::get('__site-availability-probe/model', fn () => throw (new ModelNotFoundException)->setModel(Language::class));
        Route::get('__site-availability-probe/method', fn () => throw new MethodNotAllowedHttpException(['POST']));

        foreach (['coming_soon', 'maintenance'] as $state) {
            $this->force($state);

            $this->assertNormalNotFound($this->get('/__site-availability-probe/abort'), $state.' abort');
            $this->assertNormalNotFound($this->get('/__site-availability-probe/model'), $state.' model');
            $this->get('/__site-availability-probe/method')->assertStatus(405)->assertHeaderMissing('X-Robots-Tag');
        }
    }

    public function test_matched_public_routes_are_still_answered_by_the_gate(): void
    {
        $this->force('coming_soon');

        // A binding 404 on a gated route: the gate answers before model binding (unchanged Phase 1 behaviour).
        $this->assertAvailability($this->get('/shop/products/does-not-exist'), 'coming_soon', 200, 'shop.products.show', matched: true);

        $this->force('open');
        $this->get('/shop/products/does-not-exist')->assertNotFound()->assertHeaderMissing('X-Robots-Tag');
    }

    // ---------------------------------------------------------------- helpers

    private function force(string $state): void
    {
        config(['site-availability.override' => $state]);
    }

    private function stored(string $state): void
    {
        config(['site-availability.override' => 'auto']);
        DB::table('settings')->updateOrInsert(['singleton_key' => 1], ['site_availability_mode' => $state]);
    }

    /** $matched: a gated route (web middleware, so a session cookie is expected); otherwise an unmatched request. */
    private function assertAvailability(TestResponse $response, string $state, int $status, string $label = '', bool $matched = false): void
    {
        $this->assertAvailabilityHeaders($response, $state, $status, $label, $matched);
        $response->assertSee('data-site-availability="'.$state.'"', false);
    }

    private function assertAvailabilityHeaders(TestResponse $response, string $state, int $status, string $label = '', bool $matched = false): void
    {
        $this->assertSame($status, $response->status(), $label);
        $cacheControl = (string) $response->headers->get('Cache-Control');
        foreach (['no-store', 'no-cache', 'must-revalidate', 'max-age=0'] as $directive) {
            $this->assertStringContainsString($directive, $cacheControl, $label);
        }
        $this->assertSame('no-cache', $response->headers->get('Pragma'), $label);
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'), $label);
        $this->assertSame($state === 'maintenance' ? '1800' : null, $response->headers->get('Retry-After'), $label);
        $this->assertNull($response->headers->get('Allow'), $label);

        if (! $matched) {
            $this->assertSame([], $response->headers->getCookies(), $label.': no session for an unmatched request');
        }
    }

    private function assertNormalNotFound(TestResponse $response, string $label = ''): void
    {
        // Laravel's own 404 (not the availability response, which always carries X-Robots-Tag). The body is not
        // searched: with APP_DEBUG the error page quotes test source code.
        $this->assertSame(404, $response->status(), $label);
        $this->assertNull($response->headers->get('Retry-After'), $label);
        $this->assertNull($response->headers->get('X-Robots-Tag'), $label);
        $this->assertNotSame('no-cache', $response->headers->get('Pragma'), $label);
    }
}
