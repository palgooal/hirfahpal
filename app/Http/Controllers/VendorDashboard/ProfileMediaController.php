<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileMediaController extends Controller
{
    private const FIELDS = [
        'logo' => [
            'column' => 'logo',
            'directory' => 'vendors/profile/logos',
            'max' => 2048,
        ],
        'cover' => [
            'column' => 'cover_image',
            'directory' => 'vendors/profile/covers',
            'max' => 4096,
        ],
    ];

    public function store(Request $request, string $type): JsonResponse
    {
        $config = $this->config($type);

        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.$config['max'],
            ],
        ]);

        /** @var Vendor $vendor */
        $vendor = $request->user('vendor');
        $profile = $vendor->profile()->firstOrFail();
        $column = $config['column'];
        $previous = $profile->{$column};

        /** @var UploadedFile $file */
        $file = $data['file'];
        $path = $file->store($config['directory'].'/'.$vendor->id, 'public');

        $profile->forceFill([$column => $path])->save();
        $this->deleteOwnedPath($vendor, $previous);

        return $this->response($vendor, $type, $path, 'Vendor media uploaded successfully.');
    }

    public function destroy(Request $request, string $type): JsonResponse
    {
        $config = $this->config($type);

        /** @var Vendor $vendor */
        $vendor = $request->user('vendor');
        $profile = $vendor->profile()->firstOrFail();
        $column = $config['column'];
        $previous = $profile->{$column};

        $profile->forceFill([$column => null])->save();
        $this->deleteOwnedPath($vendor, $previous);

        return $this->response($vendor, $type, null, 'Vendor media deleted successfully.');
    }

    /**
     * @return array{column: string, directory: string, max: int}
     */
    private function config(string $type): array
    {
        validator(
            ['type' => $type],
            ['type' => ['required', Rule::in(array_keys(self::FIELDS))]]
        )->validate();

        return self::FIELDS[$type];
    }

    private function deleteOwnedPath(Vendor $vendor, ?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        $allowedPrefixes = [
            'vendors/profile/logos/'.$vendor->id.'/',
            'vendors/profile/covers/'.$vendor->id.'/',
        ];

        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                Storage::disk('public')->delete($path);

                return;
            }
        }
    }

    private function response(Vendor $vendor, string $type, ?string $path, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'type' => $type,
            'path' => $path,
            'url' => $path ? Storage::disk('public')->url($path) : null,
            'vendor' => $vendor->fresh(['profile.governorate', 'profile.city']),
        ]);
    }
}
