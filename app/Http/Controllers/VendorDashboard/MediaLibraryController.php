<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Palgoal\MediaLibrary\Models\Media;

/** A vendor-scoped facade over the shared Media Library table. */
class MediaLibraryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user('vendor');
        $prefix = $this->prefix($vendor->id);

        $media = Media::query()->images()->where('file_path', 'like', $prefix.'%')->latest()->get()
            ->map(fn (Media $item) => $this->item($item));

        return response()->json(['images' => $media]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['files' => ['required', 'array', 'min:1'], 'files.*' => ['file', 'mimes:jpeg,jpg,png,gif,webp', 'max:10240']]);
        $vendor = $request->user('vendor');
        $uploaded = [];

        foreach ($data['files'] as $file) {
            // G7-C2-4: the folder without its trailing slash, so the stored path is canonical
            // (vendors/media/{id}/{uuid}.ext, not ...//...): only canonical paths can be attached to a product.
            $path = $file->storeAs(rtrim($this->prefix($vendor->id), '/'), Str::uuid().'.'.strtolower($file->extension()), 'public');
            $uploaded[] = Media::create([
                'file_name' => basename($path), 'file_original_name' => $file->getClientOriginalName(), 'file_path' => $path,
                'file_extension' => strtolower($file->extension()), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
                'file_type' => 'image', 'disk' => 'public', 'uploader_id' => $vendor->id,
            ]);
        }

        return response()->json(['images' => collect($uploaded)->map(fn (Media $item) => $this->item($item))], 201);
    }

    public function destroy(Request $request, Media $media): JsonResponse
    {
        abort_unless(str_starts_with((string) $media->file_path, $this->prefix($request->user('vendor')->id)), 404);
        abort_if(ProductImage::query()->where('path', $media->file_path)->exists(), 409, 'This image is currently used by a product.');
        Storage::disk($media->disk ?: 'public')->delete($media->file_path);
        $media->delete();

        return response()->json(['message' => 'Media deleted.']);
    }

    private function prefix(int $vendorId): string
    {
        return 'vendors/media/'.$vendorId.'/';
    }

    private function item(Media $media): array
    {
        return ['id' => $media->id, 'path' => $media->file_path, 'alt_text' => $media->alt ?: $media->file_original_name, 'url' => $media->url];
    }
}
