<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 7B-8F-3: the admin product form script (resources/js/admin-product-form.js), which moves focus to the server's
 * error summary after a refused save. Its behaviour is tested with Node's test runner
 * (tests/js/admin-product-form.test.mjs); skipped when Node is not available. The summary keeps its autofocus as the
 * fallback without the script (AdminProductFormTest).
 */
class AdminProductFormScriptTest extends TestCase
{
    private const SCRIPT = 'resources/js/admin-product-form.js';

    public function test_the_script_passes_its_node_tests(): void
    {
        $node = $this->node();

        $process = proc_open([$node, '--test', 'tests/js/admin-product-form.test.mjs'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), $output);
        $this->assertMatchesRegularExpression('/^(?:ℹ|#) fail 0$/m', $output);
        $this->assertDoesNotMatchRegularExpression('/^(?:ℹ|#) pass 0$/m', $output);
    }

    public function test_the_script_only_focuses_the_summary_and_holds_no_wording(): void
    {
        $source = file_get_contents(base_path(self::SCRIPT));

        $this->assertStringContainsString("getElementById('product-form-errors')", $source);
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $source, 'Texts come from the page.');
        $this->assertDoesNotMatchRegularExpression('/innerHTML|outerHTML|insertAdjacentHTML|\.submit\(|fetch\(/', $source);
        $this->assertDoesNotMatchRegularExpression('/admin-product-(gallery|story-image)/', $source, 'Independent of the gallery and story scripts.');
    }

    public function test_only_the_product_form_loads_it(): void
    {
        $this->assertStringContainsString("@vite('resources/js/admin-product-form.js')", file_get_contents(resource_path('views/dashboard/products/form.blade.php')));
        $this->assertStringContainsString("'resources/js/admin-product-form.js'", file_get_contents(base_path('vite.config.js')));

        $views = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (str_ends_with($path, '.blade.php') && ! str_ends_with($path, 'dashboard/products/form.blade.php')) {
                $views++;
                $this->assertStringNotContainsString('admin-product-form.js', file_get_contents($path), $path);
            }
        }
        $this->assertGreaterThan(50, $views);
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
