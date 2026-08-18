<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ArabicEncodingTest extends TestCase
{
    public function test_project_text_files_do_not_contain_common_arabic_mojibake_markers(): void
    {
        $paths = [
            __DIR__.'/../../app',
            __DIR__.'/../../ask',
            __DIR__.'/../../database',
            __DIR__.'/../../lang',
            __DIR__.'/../../resources/views',
            __DIR__.'/../../routes',
        ];

        $extensions = ['php', 'blade.php', 'html', 'md'];
        $badFiles = [];

        foreach ($paths as $path) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

            foreach ($files as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }

                $filename = $file->getFilename();
                $matchesExtension = collect($extensions)->contains(
                    fn (string $extension) => str_ends_with($filename, '.'.$extension)
                );

                if (! $matchesExtension) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                if (preg_match('/[ØÙÂâÃ�]/u', $contents) === 1) {
                    $badFiles[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], $badFiles);
    }
}
