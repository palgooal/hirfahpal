<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Rules\VendorLibraryImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * MYSTORE-MEDIA-01: the store logo, chosen from the vendor's own media library by media id. The browser never sends a
 * path: the media row is resolved here (VendorLibraryImage::lockedPathOf(), the attaching rule of product images) and
 * its stored file_path becomes profile.logo. Clearing the logo only empties the column; no file is ever deleted here,
 * so the library image stays. A library deletion refuses (409) an image that is a store logo
 * (MediaLibraryController::destroy()); both sides take the media row lock first, so they are serialised on it.
 */
class ProfileLogoController extends Controller
{
    public const NOT_IN_LIBRARY = 'The logo must be an image from your media library.';

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['media_id' => ['required', 'integer', 'min:1']]);
        $vendor = $request->user('vendor');

        $path = DB::transaction(function () use ($vendor, $data): string {
            // The media row first, with a shared lock (as every product attachment), then the profile row.
            $path = VendorLibraryImage::lockedPathOf((int) $vendor->id, (int) $data['media_id']);

            if ($path === null) {
                throw ValidationException::withMessages(['media_id' => self::NOT_IN_LIBRARY]);
            }

            $vendor->profile()->lockForUpdate()->firstOrFail()->forceFill(['logo' => $path])->save();

            return $path;
        });

        return response()->json([
            'message' => 'Store logo updated.',
            'logo' => ['media_id' => (int) $data['media_id'], 'url' => Storage::disk('public')->url($path)],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user('vendor')->profile()->firstOrFail()->forceFill(['logo' => null])->save();

        return response()->json(['message' => 'Store logo removed.', 'logo' => null]);
    }
}
