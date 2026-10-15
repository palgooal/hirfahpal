<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * AD-UI-CAT-M3: the admin media library script (resources/js/admin-media-library.js). Its response handling is
 * tested with Node's own test runner (tests/js/admin-media-library.test.mjs); skipped when Node is not available.
 */
class MediaLibraryAdminScriptTest extends TestCase
{
    private const SCRIPT = 'resources/js/admin-media-library.js';

    public function test_the_response_handling_passes_its_node_tests(): void
    {
        $node = $this->node();

        // The library page and the media picker (AD-UI-CAT-M4-B) share resources/js/admin-media/shared.js.
        $process = proc_open([$node, '--test', 'tests/js/admin-media-library.test.mjs', 'tests/js/admin-media-picker.test.mjs'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, $output);
        $this->assertMatchesRegularExpression('/^(?:ℹ|#) fail 0$/m', $output);
        $this->assertDoesNotMatchRegularExpression('/^(?:ℹ|#) pass 0$/m', $output, 'The JS tests must actually run.');
    }

    public function test_the_script_holds_no_hard_coded_wording(): void
    {
        $source = file_get_contents(base_path(self::SCRIPT));

        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $source, 'Every text comes from the translated page config.');

        // Each message it shows is a key of the config the page provides.
        preg_match_all('/messages\.([a-zA-Z]+)/', $source, $matches);
        $view = file_get_contents(resource_path('views/vendor/media-library/media.blade.php'));

        foreach (array_unique($matches[1]) as $key) {
            $this->assertMatchesRegularExpression("/'{$key}' => \\\$mediaText\\(/", $view, "messages.{$key} must be provided by the page.");
        }
    }

    public function test_the_picker_script_holds_no_hard_coded_wording_and_no_html_injection(): void
    {
        $scripts = [
            'resources/js/admin-media-picker.js' => resource_path('views/components/admin/media-picker.blade.php'),
            'resources/js/admin-media/shared.js' => null,
            'resources/js/admin-media-library.js' => null,
        ];

        foreach ($scripts as $script => $view) {
            $source = file_get_contents(base_path($script));

            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $source, $script);
            $this->assertDoesNotMatchRegularExpression('/innerHTML|outerHTML|insertAdjacentHTML/', $source, "{$script}: DOM APIs only.");

            if ($view) {
                preg_match_all('/messages\.([a-zA-Z]+)/', $source, $matches);
                $component = file_get_contents($view);

                foreach (array_unique($matches[1]) as $key) {
                    $this->assertMatchesRegularExpression("/'{$key}' => \\\$pickerText\\(/", $component, "messages.{$key} must be provided by the picker.");
                }
            }
        }
    }

    public function test_the_package_script_is_left_untouched_and_no_longer_loaded(): void
    {
        $published = public_path('vendor/media-library/js/media-library.js');

        $this->assertFileExists($published);
        $this->assertSame(
            hash_file('sha256', base_path('vendor/palgoal/media-library/public/js/media-library.js')),
            hash_file('sha256', $published),
            'The published package script is not edited: the admin page uses its own Vite entry.'
        );
        $this->assertStringNotContainsString("asset('vendor/media-library/js", file_get_contents(resource_path('views/vendor/media-library/media.blade.php')));
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
