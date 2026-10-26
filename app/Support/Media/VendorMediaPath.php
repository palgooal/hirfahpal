<?php

namespace App\Support\Media;

/**
 * MEDIA-SECURITY-01: vendor-owned media is the media whose file lives in a vendor's own library folder,
 * vendors/media/{vendorId}/, a path only the vendor's media controller writes (server-generated). Ownership is
 * read from the path because media.uploader_id holds admin and vendor ids alike.
 *
 * The path is normalised the way the storage disk resolves it (backslashes, a leading slash, empty and "."
 * segments, ".." segments), so a spelling that points at a vendor file is still recognised. The match is
 * case-insensitive, because some file systems are: refusing a delete is the safe side. Only the exact segments
 * vendors / media / a positive vendor id / a file match; look-alikes such as vendors/media-old/ or
 * vendors/media/abc/ do not.
 */
final class VendorMediaPath
{
    private const OWNED = '#^vendors/media/[1-9][0-9]*/[^/]#i';

    public static function isVendorOwned(?string $path): bool
    {
        return is_string($path) && preg_match(self::OWNED, self::normalize($path)) === 1;
    }

    private static function normalize(string $path): string
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
