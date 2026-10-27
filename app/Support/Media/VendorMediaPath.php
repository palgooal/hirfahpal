<?php

namespace App\Support\Media;

use Illuminate\Database\Eloquent\Builder;

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
        return is_string($path) && preg_match(self::OWNED, StoragePath::normalize($path)) === 1;
    }

    /**
     * ADMIN-MEDIA-ORGANIZATION-01 P2: the SQL counterpart of isVendorOwned(), for listings that must leave
     * vendor media out before they paginate. It keeps a row out when its path is a canonical vendor path
     * (vendors/media/...), or is not canonical (a backslash, a leading, doubled or trailing slash, a "." or ".."
     * segment) and contains "vendors". Normalising never changes a segment's text, so every spelling that
     * isVendorOwned() accepts is left out. It errs on hiding: canonical look-alikes such as vendors/media/abc/
     * and non-canonical paths that merely contain "vendors" are left out too; the server never writes either.
     */
    public static function excludeFrom(Builder $query, string $column = 'file_path'): Builder
    {
        $path = $query->getQuery()->getGrammar()->wrap($column);

        return $query->whereRaw(
            "lower({$path}) not like ? and (instr(lower({$path}), ?) = 0 or (instr({$path}, ?) = 0 and {$path} not like ?"
            ." and instr({$path}, ?) = 0 and {$path} not like ? and instr({$path}, ?) = 0 and {$path} not like ?))",
            ['vendors/media/%', 'vendors', '\\', '/%', '//', '%/', '/.', '.%']
        );
    }
}
