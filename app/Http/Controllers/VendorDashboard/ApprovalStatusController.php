<?php

namespace App\Http\Controllers\VendorDashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Approval-status contract for the vendor status experience (VEN-BE-001).
 * Reachable by any authenticated vendor, outside the approval gate. JSON
 * requests get the contract; browsers get the status page, or the dashboard
 * once the store is approved.
 */
class ApprovalStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse|View
    {
        $vendor = $request->user('vendor');
        $profile = $vendor->profile;
        $approvalStatus = $profile?->approval_status;
        $rejectionReason = $approvalStatus === 'rejected' ? $profile->rejection_reason : null;

        if ($request->expectsJson()) {
            return response()->json([
                'approval_status' => $approvalStatus,
                'rejection_reason' => $rejectionReason,
                'store_name' => $profile?->store_name,
                'dashboard_url' => $vendor->isApproved() ? route('vendor.dashboard') : null,
            ]);
        }

        if ($vendor->isApproved()) {
            return redirect()->route('vendor.dashboard');
        }

        return view('auth.vendor.approval-status', [
            // Anything not rejected (including a missing profile) is shown as awaiting review.
            'state' => $approvalStatus === 'rejected' ? 'rejected' : 'pending',
            'rejectionReason' => filled($rejectionReason) ? $rejectionReason : null,
            'storeName' => $profile?->store_name,
        ]);
    }
}
