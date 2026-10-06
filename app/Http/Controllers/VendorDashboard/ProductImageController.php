<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorDashboard\StoreProductImageRequest;
use App\Http\Requests\VendorDashboard\UpdateProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
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

    public function destroy(Request $request, Product $product, ProductImage $productImage): JsonResponse
    {
        $this->authorizeImage($request, $product, $productImage);

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
