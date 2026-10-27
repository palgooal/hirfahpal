<?php

namespace App\Support\Media;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

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

    private const ROOT = 'vendors/media/';

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

    /**
     * ADMIN-MEDIA-ORGANIZATION-01 P3: a vendor's library folder, exactly as the vendor's media controller writes
     * it. The trailing slash keeps vendor 1 apart from vendors 10 and 11.
     */
    public static function prefix(int $vendorId): string
    {
        if ($vendorId < 1) {
            throw new InvalidArgumentException('A vendor id is a positive integer.');
        }

        return self::ROOT.$vendorId.'/';
    }

    /**
     * ADMIN-MEDIA-ORGANIZATION-01 P3: the media in one vendor's folder, for read-only listings. Only canonical,
     * server-generated paths match: the prefix with its exact case (hex() compares bytes on MySQL and SQLite
     * alike, where LIKE and a _ci collation would not), then a file name without a slash, a backslash or a
     * "." / ".." name. Every match is vendor-owned for isVendorOwned(); non-canonical spellings stay out of the
     * folder and keep their mutation protections.
     */
    public static function scopeToVendor(Builder $query, int $vendorId, string $column = 'file_path'): Builder
    {
        $prefix = self::prefix($vendorId);
        $path = $query->getQuery()->getGrammar()->wrap($column);
        $file = "substr({$path}, ".(strlen($prefix) + 1).')';

        return $query->whereRaw(
            "({$path} like ? and hex(substr({$path}, 1, ".strlen($prefix).")) = ? and instr({$path}, ?) = 0"
            ." and length({$file}) > 0 and instr({$file}, ?) = 0 and {$file} not in (?, ?))",
            [$prefix.'%', strtoupper(bin2hex($prefix)), '\\', '/', '.', '..']
        );
    }

    /**
     * ADMIN-MEDIA-ORGANIZATION-01 P3: the number of media per vendor folder, in one grouped query, under the same
     * canonical rule as scopeToVendor(). vendor_key is the hex of the folder's id segment, to join on
     * hex(cast(vendors.id as char)): a byte comparison, so "07", "7 " or "7abc" never count for vendor 7.
     * The expressions hold constants only (no bindings), so MySQL's ONLY_FULL_GROUP_BY sees the same
     * expression in the select and the group by.
     */
    public static function folderCounts(Builder $query, string $column = 'file_path'): Builder
    {
        $path = $query->getQuery()->getGrammar()->wrap($column);
        $root = self::ROOT;
        $tail = "substr({$path}, ".(strlen($root) + 1).')';
        $slash = "instr({$tail}, '/')";
        $file = "substr({$tail}, {$slash} + 1)";
        $key = "hex(substr({$tail}, 1, {$slash} - 1))";

        return $query
            ->selectRaw("{$key} as vendor_key, count(*) as media_count")
            ->whereRaw(
                "({$path} like '{$root}%' and hex(substr({$path}, 1, ".strlen($root).")) = '".strtoupper(bin2hex($root))."'"
                ." and instr({$path}, char(92)) = 0 and {$slash} > 1 and length({$file}) > 0 and instr({$file}, '/') = 0"
                ." and {$file} not in ('.', '..'))"
            )
            ->groupByRaw($key);
    }
}
