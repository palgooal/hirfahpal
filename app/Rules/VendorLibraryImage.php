<?php

namespace App\Rules;

use App\Support\Media\VendorMediaPath;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Palgoal\MediaLibrary\Models\Media;

/**
 * VENDOR-PRODUCT-MEDIA-G7 / G7-C2-1 (V2): a product image path a vendor may attach. It must be, byte for byte, the
 * file_path of an image in that vendor's own media library: a media row of type image on the public disk, inside
 * the canonical vendors/media/{vendorId}/ folder (VendorMediaPath::scopeToVendor, never uploader_id). A path that
 * only starts with the folder, another vendor's or an admin image, a URL, an absolute, traversal or backslash path
 * and a path without a media row are all refused, with one message that names no other vendor.
 *
 * The vendor id comes from the authenticated vendor, never from the request. $unchanged is the image's current path
 * on update: keeping it is always allowed, so a legacy image (outside the library) stays manageable.
 */
final class VendorLibraryImage implements ValidationRule
{
    public const MESSAGE = 'Each image must be chosen from your media library.';

    public function __construct(private readonly int $vendorId, private readonly ?string $unchanged = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && $this->unchanged !== null && $value === $this->unchanged) {
            return;
        }

        if (! is_string($value) || ! self::inLibrary($this->vendorId, $value)) {
            $fail(self::MESSAGE);
        }
    }

    public static function inLibrary(int $vendorId, string $path): bool
    {
        return self::find($vendorId, $path, false);
    }

    /**
     * VENDOR-MEDIA-02C: the same check inside the transaction that attaches the image, reading the media row with a
     * shared lock. A library deletion locks that row FOR UPDATE first, so the two are serialised on it: either the
     * deletion commits first (the row is gone and the attachment is refused) or it waits for this transaction and
     * then sees the new product image (and refuses with 409).
     */
    public static function lockedInLibrary(int $vendorId, string $path): bool
    {
        return self::find($vendorId, $path, true);
    }

    private static function find(int $vendorId, string $path, bool $lock): bool
    {
        if ($vendorId < 1 || $path === '') {
            return false;
        }

        // The database comparison may ignore case or trailing spaces (MySQL _ci collations, PAD SPACE); the
        // candidates are compared again here, exactly.
        $query = VendorMediaPath::scopeToVendor(Media::query()->images(), $vendorId)
            ->where('disk', 'public')
            ->where('file_path', $path);

        return ($lock ? $query->sharedLock() : $query)
            ->pluck('file_path')
            ->contains(fn ($stored) => $stored === $path);
    }
}
