<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user('vendor');

        $reviews = Review::query()
            ->with(['customer', 'order', 'product.primaryImage'])
            ->where(function ($query) use ($vendor) {
                $query->where('vendor_id', $vendor->id)
                    ->orWhereHas('product', fn ($productQuery) => $productQuery->where('vendor_id', $vendor->id));
            })
            ->when($request->filled('rating'), fn ($query) => $query->where('rating', $request->integer('rating')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('reviewable_type', $request->input('type')))
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return response()->json([
            'reviews' => $reviews,
            'summary' => [
                'average_rating' => round((float) Review::query()
                    ->where('vendor_id', $vendor->id)
                    ->where('status', 'published')
                    ->avg('rating'), 2),
                'published_count' => Review::query()
                    ->where('vendor_id', $vendor->id)
                    ->where('status', 'published')
                    ->count(),
            ],
        ]);
    }
}
