<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user('vendor');

        $commissions = Commission::query()
            ->with('vendorOrder.order')
            ->where('vendor_id', $vendor->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return response()->json([
            'commissions' => $commissions,
            'summary' => [
                'pending' => (float) Commission::query()->where('vendor_id', $vendor->id)->where('status', 'pending')->sum('amount'),
                'earned' => (float) Commission::query()->where('vendor_id', $vendor->id)->where('status', 'earned')->sum('amount'),
                'paid' => (float) Commission::query()->where('vendor_id', $vendor->id)->where('status', 'paid')->sum('amount'),
                'waived' => (float) Commission::query()->where('vendor_id', $vendor->id)->where('status', 'waived')->sum('amount'),
            ],
        ]);
    }
}
