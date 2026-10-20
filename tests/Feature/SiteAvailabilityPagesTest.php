<?php

namespace Tests\Feature;

use App\Support\SiteAvailability\SiteAvailabilityResponse;
use App\Support\SiteAvailability\SiteAvailabilityState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * SITE-AVAILABILITY Phase 2A (docs/deployment/HIRFAH-SITE-AVAILABILITY-DESIGN.md §8): the converted static pages.
 * Tracked CSS inlined into the page (no Tailwind CDN, no Vite manifest, no public/build, no public/hot), Google Fonts
 * kept (approved optional external dependency), the original logo and texts, no form, no Blade, no database, and a
 * logged built-in fallback if a template or the CSS is missing.
 */
class SiteAvailabilityPagesTest extends TestCase
{
    use RefreshDatabase;

    private const SOURCE_SHA1 = 'a58862f727b16a1d1fcb6651373c79d72a88cadd';

    private const LOGO_SHA1 = '9ca0463014b6261c0ed9674633eae72906899491';

    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Any use of Vite by the pages fails the test.
        $this->app->instance(Vite::class, new class extends Vite
        {
            public function asset($asset, $buildDirectory = null)
            {
                throw new \LogicException('The availability pages must not use Vite.');
            }

            public function __invoke($entrypoints, $buildDirectory = null)
            {
                throw new \LogicException('The availability pages must not use Vite.');
            }
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            $this->deleteDirectory($directory);
        }

        parent::tearDown();
    }

    // ---------------------------------------------------------------- converted pages

    public function test_coming_soon_serves_the_converted_original_design_with_inline_css(): void
    {
        config(['site-availability.override' => 'coming_soon']);

        $response = $this->get('/');

        $response->assertOk();
        $this->assertConvertedPage($response, 'coming_soon');
        $response->assertSee('<title>متجر حِرفة | قريبًا</title>', false);
        $response->assertSee('قريبًا بين أيديكم', false);
        $response->assertSee('مساحة تجمع أصالة الحكاية الفلسطينية', false);
    }

    public function test_maintenance_serves_the_approved_maintenance_copy_with_inline_css(): void
    {
        config(['site-availability.override' => 'maintenance']);

        $response = $this->get('/');

        $response->assertStatus(503);
        $this->assertConvertedPage($response, 'maintenance');
        $response->assertSee('<title>الموقع تحت الصيانة | حِرفة</title>', false);
        $response->assertSee('<meta name="description" content="متجر حِرفة يخضع حاليًا لصيانة مؤقتة. سنعود لخدمتكم قريبًا.">', false);
        $response->assertSee('نعمل على تحسين تجربتكم', false);
        $response->assertSee('<span class="mt-2 block text-[#a34f21]">الموقع تحت الصيانة</span>', false);
        $response->assertSee('نجري حاليًا بعض أعمال الصيانة والتحسين على متجر حِرفة. سنعود إليكم قريبًا. شكرًا لتفهمكم.', false);
        $response->assertDontSee('قريبًا بين أيديكم', false);
        $response->assertDontSee('شيء جميل يُصنع الآن', false);
        $response->assertDontSee('شكرًا لصبركم', false);
    }

    public function test_the_pages_read_no_database(): void
    {
        foreach (['coming_soon', 'maintenance'] as $state) {
            config(['site-availability.override' => $state]);
            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->get('/')->assertSee('data-site-availability="'.$state.'"', false);

            $this->assertSame([], DB::getQueryLog(), $state);
            DB::disableQueryLog();
        }
    }

    public function test_the_pages_stay_styled_without_vite_build_artifacts_and_ignore_the_hot_file(): void
    {
        // A public directory with no build/manifest.json and no compiled CSS, but with a dev-server hot file.
        $public = $this->temporaryDirectory();
        file_put_contents($public.'/hot', 'http://localhost:5173');
        $this->app->usePublicPath($public);
        Log::spy();

        foreach ([SiteAvailabilityState::ComingSoon, SiteAvailabilityState::Maintenance] as $state) {
            $html = SiteAvailabilityResponse::make($state, Request::create('/'))->getContent();

            $this->assertStringContainsString('data-site-availability="'.$state->value.'"', $html);
            $this->assertStringContainsString('<style>'.$this->committedCss().'</style>', $html);
            $this->assertStringNotContainsString('localhost:5173', $html);
            $this->assertStringNotContainsString('/build/', $html);
            $this->assertStringNotContainsString('@vite', $html);
        }

        Log::shouldNotHaveReceived('error');
    }

    // ---------------------------------------------------------------- the committed CSS artifact

    public function test_the_committed_css_covers_every_class_of_both_templates(): void
    {
        $css = $this->committedCss();
        $classes = [];

        foreach (['coming-soon', 'maintenance'] as $template) {
            preg_match_all('/class="([^"]+)"/', file_get_contents(resource_path('site-availability/'.$template.'.html')), $matches);
            foreach ($matches[1] as $attribute) {
                array_push($classes, ...preg_split('/\s+/', trim($attribute)));
            }
        }

        $classes = array_values(array_unique($classes));
        $this->assertNotEmpty($classes);

        $missing = array_values(array_filter($classes, fn (string $class): bool => ! str_contains($css, '.'.addcslashes($class, '[]().:/%#,'))));
        $this->assertSame([], $missing, 'Regenerate the CSS: npx vite build --config vite.site-availability.config.js');

        $this->assertStringContainsString('--font-tajawal:"Tajawal", sans-serif', $css);
        $this->assertStringNotContainsString('url(', $css);
        $this->assertStringNotContainsString('</style', $css);
        $this->assertDoesNotMatchRegularExpression('/@import\s/', $css);
    }

    // ---------------------------------------------------------------- fallbacks

    public function test_a_missing_css_file_is_logged_and_serves_the_readable_fallback_page(): void
    {
        $base = $this->temporaryBase(withTemplates: true, withCss: false);
        Log::spy();

        $this->withBasePath($base, function (): void {
            $this->assertFallbackPages();
        });

        Log::shouldHaveReceived('error')->twice()->withArgs(
            fn (string $message, array $context): bool => str_contains($message, 'stylesheet') && in_array($context['state'] ?? null, ['coming_soon', 'maintenance'], true)
        );
    }

    public function test_an_empty_css_file_is_treated_as_missing(): void
    {
        $base = $this->temporaryBase(withTemplates: true, withCss: false);
        file_put_contents($base.'/resources/site-availability/site-availability.min.css', "  \n");
        Log::spy();

        $this->withBasePath($base, function (): void {
            $this->assertFallbackPages();
        });

        Log::shouldHaveReceived('error')->twice();
    }

    public function test_a_missing_template_is_logged_and_serves_the_readable_fallback_page(): void
    {
        $base = $this->temporaryBase(withTemplates: false, withCss: true);
        Log::spy();

        $this->withBasePath($base, function (): void {
            $this->assertFallbackPages();
        });

        Log::shouldHaveReceived('error')->twice()->withArgs(fn (string $message): bool => str_contains($message, 'template'));
    }

    // ---------------------------------------------------------------- statuses, headers and JSON (unchanged)

    public function test_statuses_headers_and_json_are_unchanged_for_the_page_and_the_fallback(): void
    {
        config(['site-availability.retry_after' => 1800]);
        $fallbackBase = $this->temporaryBase(withTemplates: true, withCss: false);
        Log::spy();

        foreach ([null, $fallbackBase] as $base) {
            $check = function (): void {
                $this->assertBlockedResponse(SiteAvailabilityState::ComingSoon, 'GET', 200, false);
                $this->assertBlockedResponse(SiteAvailabilityState::ComingSoon, 'HEAD', 200, false);
                $this->assertBlockedResponse(SiteAvailabilityState::ComingSoon, 'POST', 503, false);
                $this->assertBlockedResponse(SiteAvailabilityState::Maintenance, 'GET', 503, true);
                $this->assertBlockedResponse(SiteAvailabilityState::Maintenance, 'POST', 503, true);
            };

            $base === null ? $check() : $this->withBasePath($base, $check);
        }

        foreach ([[SiteAvailabilityState::ComingSoon, 200], [SiteAvailabilityState::Maintenance, 503]] as [$state, $status]) {
            $response = SiteAvailabilityResponse::make($state, Request::create('/', 'GET', server: ['HTTP_ACCEPT' => 'application/json']));

            $this->assertSame($status, $response->getStatusCode());
            $this->assertSame('application/json', $response->headers->get('Content-Type'));
            $expected = ['state' => $state->value, 'message' => (string) config('site-availability.messages.'.$state->value, '')];
            if ($state === SiteAvailabilityState::Maintenance) {
                $expected['retry_after'] = 1800;
            }
            $this->assertSame($expected, json_decode($response->getContent(), true));
            $this->assertNoStoreHeaders($response);
        }
    }

    // ---------------------------------------------------------------- source integrity

    public function test_the_original_source_page_and_logo_are_unchanged(): void
    {
        $this->assertSame(self::SOURCE_SHA1, sha1_file(resource_path('site-availability/source/index.html')));
        $this->assertSame(self::LOGO_SHA1, sha1_file(public_path('assets/site-availability/hirfah-logo.jpeg')));
    }

    // ---------------------------------------------------------------- helpers

    private function assertConvertedPage(TestResponse $response, string $state): void
    {
        $html = $response->getContent();

        $response->assertSee('data-site-availability="'.$state.'"', false);
        $response->assertSee('<html lang="ar" dir="rtl">', false);
        $response->assertSee('<body class="font-tajawal"', false);
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $response->assertSee('href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap"', false);
        $response->assertSee('src="'.asset('assets/site-availability/hirfah-logo.jpeg').'"', false);
        $response->assertSee('متجر حِرفة', false);
        $response->assertSee('صُنِع بحب في فلسطين', false);

        $this->assertSame(1, substr_count($html, '<style>'));
        $this->assertStringContainsString('<style>'.$this->committedCss().'</style>', $html);
        $this->assertSame(1, substr_count($html, 'rel="stylesheet"'), 'Only the Google Fonts stylesheet is linked.');

        $this->assertStringNotContainsString('/build/', $html);
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $html);
        $this->assertStringNotContainsString('tailwind.config', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('{{', $html);
        $this->assertStringNotContainsString('.woff2', $html);

        preg_match_all('#(?:src|href)="(https?://[^"]+)"#', $html, $matches);
        foreach ($matches[1] as $url) {
            $this->assertMatchesRegularExpression(
                '#^(https://fonts\.(googleapis|gstatic)\.com|'.preg_quote(rtrim(asset('/'), '/'), '#').')#',
                $url,
                'Only Google Fonts and local assets are referenced.'
            );
        }
    }

    private function assertFallbackPages(): void
    {
        $texts = [
            'coming_soon' => 'قريبًا بين أيديكم',
            'maintenance' => 'نجري حاليًا بعض أعمال الصيانة والتحسين على متجر حِرفة. سنعود إليكم قريبًا. شكرًا لتفهمكم.',
        ];

        foreach ([SiteAvailabilityState::ComingSoon, SiteAvailabilityState::Maintenance] as $state) {
            $html = SiteAvailabilityResponse::make($state, Request::create('/'))->getContent();

            $this->assertStringContainsString('<html lang="ar" dir="rtl">', $html);
            $this->assertStringContainsString('data-site-availability="'.$state->value.'"', $html);
            $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
            $this->assertStringContainsString('style="margin:0;font-family:sans-serif;', $html, 'Readable without any stylesheet.');
            $this->assertStringContainsString($texts[$state->value], $html);
            $this->assertStringNotContainsString('class="', $html, 'No unstyled utility markup.');
            $this->assertStringNotContainsString('{{', $html);
            $this->assertStringNotContainsString('<form', $html);
            $this->assertStringNotContainsString('https://', $html);
        }
    }

    private function assertBlockedResponse(SiteAvailabilityState $state, string $method, int $status, bool $retryAfter): void
    {
        $response = SiteAvailabilityResponse::make($state, Request::create('/', $method));
        $label = $state->value.' '.$method;

        $this->assertSame($status, $response->getStatusCode(), $label);
        $this->assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'), $label);
        $this->assertNoStoreHeaders($response);
        $this->assertSame($retryAfter ? '1800' : null, $response->headers->get('Retry-After'), $label);
        $this->assertStringContainsString('data-site-availability="'.$state->value.'"', $response->getContent(), $label);
    }

    private function assertNoStoreHeaders(Response $response): void
    {
        foreach (['no-store', 'no-cache', 'must-revalidate', 'max-age=0'] as $directive) {
            $this->assertStringContainsString($directive, (string) $response->headers->get('Cache-Control'));
        }
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    }

    private function committedCss(): string
    {
        $path = resource_path('site-availability/site-availability.min.css');
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    /** A temporary application base with copies of the real templates and/or CSS only. */
    private function temporaryBase(bool $withTemplates, bool $withCss): string
    {
        $base = $this->temporaryDirectory();
        mkdir($base.'/resources/site-availability', 0777, true);

        $files = array_merge($withTemplates ? ['coming-soon.html', 'maintenance.html'] : [], $withCss ? ['site-availability.min.css'] : []);
        foreach ($files as $file) {
            copy(resource_path('site-availability/'.$file), $base.'/resources/site-availability/'.$file);
        }

        return $base;
    }

    private function withBasePath(string $base, callable $callback): void
    {
        $original = $this->app->basePath();

        try {
            $this->app->setBasePath($base);
            $callback();
        } finally {
            $this->app->setBasePath($original);
        }
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hirfah-sa-'.bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);

        return $this->temporaryDirectories[] = $directory;
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
