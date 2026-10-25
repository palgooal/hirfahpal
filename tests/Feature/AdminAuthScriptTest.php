<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * VEN-F1: the auth pages script (resources/js/admin-auth.js), shared by the admin
 * and vendor auth pages. Its submit lock and password toggle are tested with Node's
 * test runner (tests/js/admin-auth.test.mjs); skipped when Node is not available.
 */
class AdminAuthScriptTest extends TestCase
{
    private const SCRIPT = 'resources/js/admin-auth.js';

    public function test_the_script_passes_its_node_tests(): void
    {
        $node = $this->node();

        $process = proc_open([$node, '--test', 'tests/js/admin-auth.test.mjs'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), $output);
        $this->assertMatchesRegularExpression('/^(?:ℹ|#) fail 0$/m', $output);
        $this->assertDoesNotMatchRegularExpression('/^(?:ℹ|#) pass 0$/m', $output);
    }

    public function test_the_script_holds_no_wording_and_no_html_injection(): void
    {
        $source = file_get_contents(base_path(self::SCRIPT));

        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $source, 'Texts come from the page.');
        $this->assertDoesNotMatchRegularExpression('/innerHTML|outerHTML|insertAdjacentHTML/', $source, 'DOM APIs only.');
        $this->assertStringNotContainsString('.disabled', $source, 'The submit button is never disabled.');
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
