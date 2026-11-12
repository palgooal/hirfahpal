<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * LOC-UI-7B-2: the locations page script (resources/js/admin-locations.js). Its behaviour is tested with Node's test
 * runner (tests/js/admin-locations.test.mjs); skipped when Node is not available. The page keeps working without it:
 * its add, cancel and close links go through the server (LocationManagementPageTest).
 */
class AdminLocationsScriptTest extends TestCase
{
    private const SCRIPT = 'resources/js/admin-locations.js';

    public function test_the_script_passes_its_node_tests(): void
    {
        $node = $this->node();

        $process = proc_open([$node, '--test', 'tests/js/admin-locations.test.mjs'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), $output);
        $this->assertMatchesRegularExpression('/^(?:ℹ|#) fail 0$/m', $output);
        $this->assertDoesNotMatchRegularExpression('/^(?:ℹ|#) pass 0$/m', $output);
    }

    public function test_the_script_holds_no_wording_no_html_injection_and_no_request(): void
    {
        $source = file_get_contents(base_path(self::SCRIPT));

        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $source, 'Texts come from the page.');
        $this->assertDoesNotMatchRegularExpression('/innerHTML|outerHTML|insertAdjacentHTML/', $source, 'DOM APIs only.');
        $this->assertDoesNotMatchRegularExpression('/fetch\(|XMLHttpRequest|\.submit\(\)/', $source, 'The search runs in the page; the forms post themselves.');
        $this->assertDoesNotMatchRegularExpression('/event\.target === dialog/', $source, 'A click on the backdrop does not close a form dialog (D4).');
        $this->assertStringContainsString("import { createSubmitGuard } from './admin-categories.js';", $source, 'The existing submit guard.');
    }

    public function test_only_the_locations_page_loads_it(): void
    {
        $this->assertStringContainsString("@vite('resources/js/admin-locations.js')", file_get_contents(resource_path('views/dashboard/settings/locations.blade.php')));
        $this->assertStringContainsString("'resources/js/admin-locations.js'", file_get_contents(base_path('vite.config.js')));

        $views = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (str_ends_with($path, '.blade.php') && ! str_ends_with($path, 'dashboard/settings/locations.blade.php')) {
                $views++;
                $this->assertStringNotContainsString('admin-locations.js', file_get_contents($path), $path);
            }
        }
        $this->assertGreaterThan(50, $views, 'Every other view was read.');
    }

    public function test_the_page_keeps_its_links_for_a_browser_without_the_script(): void
    {
        $view = file_get_contents(resource_path('views/dashboard/settings/locations.blade.php'));

        // The add actions and the shortcut are links the server answers (?add=…), Cancel and Close link back.
        $this->assertSame(3, substr_count($view, 'data-location-open="'));
        $this->assertSame(3, substr_count($view, 'href="{{ $addUrl('));
        $this->assertSame(4, substr_count($view, 'href="{{ $locationsUrl }}" data-location-close'));
    }

    private function node(): string
    {
        foreach (['node', 'node.exe'] as $candidate) {
            $found = trim((string) shell_exec((PHP_OS_FAMILY === 'Windows' ? 'where ' : 'command -v ').$candidate.' 2>'.(PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null')));

            if ($found !== '') {
                return strtok($found, "\r\n");
            }
        }

        $this->markTestSkipped('Node.js is not available.');
    }
}
