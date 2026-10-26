<?php

namespace App\Support\Media;

/**
 * MEDIA-SECURITY-01: a storage path written the way the disk resolves it — backslashes become slashes, and a
 * leading slash, empty and "." segments are dropped, ".." removes the segment before it — so two spellings of
 * the same file compare equal. Letter case is kept: the comparison stays exact.
 */
final class StoragePath
{
    public static function normalize(string $path): string
    {
        $segments = [];

        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }
}
