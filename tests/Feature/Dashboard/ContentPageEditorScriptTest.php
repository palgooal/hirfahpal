<?php

namespace Tests\Feature\Dashboard;

use Tests\TestCase;

/**
 * PAGES-03B: the content page editor script (resources/js/admin-content-editor.js), tested with Node's test runner
 * (tests/js/admin-content-editor.test.mjs) together with the media picker it calls; skipped when Node is not available.
 */
class ContentPageEditorScriptTest extends TestCase
{
    private const SCRIPT = 'resources/js/admin-content-editor.js';

    public function test_the_script_passes_its_node_tests(): void
    {
        $node = $this->node();

        $process = proc_open([$node, '--test', 'tests/js/admin-content-editor.test.mjs', 'tests/js/admin-media-picker.test.mjs', 'tests/js/admin-content-pages.test.mjs'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
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
        // PAGES-04C: the content pages list script follows the same rules.
        $list = file_get_contents(base_path('resources/js/admin-content-pages.js'));
        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $list);
        $this->assertDoesNotMatchRegularExpression('/innerHTML|outerHTML|insertAdjacentHTML/', $list);
        $this->assertStringContainsString('allowBase64: false', $source);
        $this->assertDoesNotMatchRegularExpression('/@tiptap-pro|extension-(?:color|font-family|text-style|highlight|youtube|code-block)/', $source, 'No colours, fonts, embeds or Pro extensions.');
    }

    public function test_the_tiptap_packages_are_pinned_and_mit(): void
    {
        $package = json_decode(file_get_contents(base_path('package.json')), true);
        $lock = json_decode(file_get_contents(base_path('package-lock.json')), true);
        $tiptap = array_filter($package['dependencies'] ?? [], fn ($name) => str_starts_with($name, '@tiptap/'), ARRAY_FILTER_USE_KEY);

        $this->assertSame(['@tiptap/core', '@tiptap/extension-image', '@tiptap/extension-table', '@tiptap/pm', '@tiptap/starter-kit'], array_keys($tiptap));
        foreach ($tiptap as $name => $version) {
            $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version, $name.' is pinned exactly.');
            $this->assertSame($version, $lock['packages']['node_modules/'.$name]['version'] ?? null, $name);
        }
        foreach ($lock['packages'] as $path => $entry) {
            if (str_contains($path, 'node_modules/@tiptap/')) {
                $this->assertSame('MIT', $entry['license'] ?? null, $path);
            }
        }
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
