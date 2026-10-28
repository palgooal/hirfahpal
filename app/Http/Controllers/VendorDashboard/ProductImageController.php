<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorDashboard\StoreProductImageRequest;
use App\Http\Requests\VendorDashboard\UpdateProductImageRequest;
use App\Http\Requests\VendorDashboard\UploadProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductImageController extends Controller
{
    private const UPLOAD_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function store(StoreProductImageRequest $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        $image = $product->images()->create($this->imageData($request->validated()));
        $this->normalizePrimaryImage($product, $image);

        return response()->json([
            'message' => 'Product image added successfully.',
            'image' => $image->fresh(),
            'product' => $product->fresh('images'),
        ], 201);
    }

    /** Images previously uploaded by this vendor, available for reuse on another product. */
    public function library(Request $request): JsonResponse
    {
        $vendor = $request->user('vendor');
        $images = ProductImage::query()
            ->whereHas('product', fn ($query) => $query->where('vendor_id', $vendor->id))
            ->latest('id')
            ->get()
            ->unique('path')
            ->values()
            ->map(fn (ProductImage $image) => [
                'path' => $image->path,
                'alt_text' => $image->alt_text,
                'url' => Storage::disk('public')->url($image->path),
            ]);

        return response()->json(['images' => $images]);
    }

    public function update(UpdateProductImageRequest $request, Product $product, ProductImage $productImage): JsonResponse
    {
        $this->authorizeImage($request, $product, $productImage);

        $productImage->update($this->imageData($request->validated(), false));

        if ($request->boolean('is_primary')) {
            $this->normalizePrimaryImage($product, $productImage);
        }

        return response()->json([
            'message' => 'Product image updated successfully.',
            'image' => $productImage->fresh(),
            'product' => $product->fresh('images'),
        ]);
    }

    /** Store a vendor-owned image under a server generated, content-derived name. */
    public function upload(UploadProductImageRequest $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $extension = $this->safeExtension($file);
        $vendor = $request->user('vendor');
        $directory = 'vendors/products/'.$vendor->id.'/'.$product->id;
        $path = $file->storeAs($directory, (string) Str::uuid().'.'.$extension, 'public');
        $hasImages = $product->images()->exists();

        $image = $product->images()->create([
            'path' => $path,
            'alt_text' => $request->validated('alt_text') ?: $product->name,
            'sort_order' => (int) $product->images()->max('sort_order') + 1,
            'is_primary' => ! $hasImages,
        ]);

        return response()->json([
            'message' => 'Product image uploaded successfully.',
            'image' => $image,
            'url' => Storage::disk('public')->url($path),
            'product' => $product->fresh('images'),
        ], 201);
    }

    public function destroy(Request $request, Product $product, ProductImage $productImage): JsonResponse
    {
        $this->authorizeImage($request, $product, $productImage);

        // G7-B (V1): removing an image from a product only removes the association. The file is never deleted
        // here, whatever its stored path: a path is not trusted for filesystem operations (a ".." segment in it
        // used to reach files outside the product's folder). Files are removed from the media library only.
        $wasPrimary = $productImage->is_primary;
        $productImage->delete();

        if ($wasPrimary) {
            $first = $product->images()->orderBy('sort_order')->first();
            $first?->update(['is_primary' => true]);
        }

        return response()->json([
            'message' => 'Product image deleted successfully.',
            'product' => $product->fresh('images'),
        ]);
    }

    private function safeExtension(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        $mime = $path ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;
        $size = $path ? @getimagesize($path) : false;

        if (! is_string($mime) || ! isset(self::UPLOAD_MIMES[$mime]) || $size === false
            || ($size['mime'] ?? null) !== $mime || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > 25_000_000) {
            throw ValidationException::withMessages([
                'file' => 'The file must be a valid JPEG, PNG or WebP image with safe dimensions.',
            ]);
        }

        return self::UPLOAD_MIMES[$mime];
    }

    private function imageData(array $data, bool $creating = true): array
    {
        $payload = [
            'alt_text' => $data['alt_text'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_primary' => (bool) ($data['is_primary'] ?? false),
        ];

        if ($creating || array_key_exists('path', $data)) {
            $payload['path'] = $data['path'];
        }

        return $payload;
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless((int) $product->vendor_id === (int) $request->user('vendor')->id, 404);
    }

    private function authorizeImage(Request $request, Product $product, ProductImage $productImage): void
    {
        $this->authorizeProduct($request, $product);

        abort_unless((int) $productImage->product_id === (int) $product->id, 404);
    }

    private function normalizePrimaryImage(Product $product, ProductImage $primary): void
    {
        if (! $primary->is_primary) {
            return;
        }

        $product->images()
            ->whereKeyNot($primary->id)
            ->update(['is_primary' => false]);
    }
}
