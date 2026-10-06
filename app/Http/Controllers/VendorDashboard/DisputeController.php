<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisputeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user('vendor');

        $disputes = Dispute::query()
            ->with(['order', 'vendorOrder.items.product.primaryImage', 'customer'])
            ->whereHas('vendorOrder', fn ($query) => $query->where('vendor_id', $vendor->id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return response()->json([
            'disputes' => $disputes,
        ]);
    }

    public function show(Request $request, Dispute $dispute): JsonResponse
    {
        $this->authorizeDispute($request, $dispute);

        return response()->json([
            'dispute' => $dispute->load(['order', 'vendorOrder.items.product.images', 'customer']),
        ]);
    }

    private function authorizeDispute(Request $request, Dispute $dispute): void
    {
        abort_unless($dispute->vendorOrder()
            ->where('vendor_id', $request->user('vendor')->id)
            ->exists(), 404);
    }
}
