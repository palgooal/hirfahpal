<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user('vendor');

        $returns = ReturnRequest::query()
            ->with(['order', 'vendorOrder.items.product.primaryImage', 'customer'])
            ->whereHas('vendorOrder', fn ($query) => $query->where('vendor_id', $vendor->id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return response()->json([
            'returns' => $returns,
        ]);
    }

    public function show(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        $this->authorizeReturn($request, $returnRequest);

        return response()->json([
            'return' => $returnRequest->load(['order', 'vendorOrder.items.product.images', 'customer']),
        ]);
    }

    private function authorizeReturn(Request $request, ReturnRequest $returnRequest): void
    {
        abort_unless($returnRequest->vendorOrder()
            ->where('vendor_id', $request->user('vendor')->id)
            ->exists(), 404);
    }
}
