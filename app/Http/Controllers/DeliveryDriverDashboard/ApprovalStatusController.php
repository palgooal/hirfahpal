<?php

namespace App\Http\Controllers\DeliveryDriverDashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * E2-MVP batch 01 (FR-DEL-013): the delivery driver's application status, as the vendor one (VEN-BE-001). Reachable by
 * any signed-in driver, outside the approval gate. A browser gets the status page, or the dashboard once approved; JSON
 * gets the driver's own status only (never who reviewed it or when).
 */
class ApprovalStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse|View
    {
        $driver = $request->user('delivery_driver');
        $approvalStatus = $driver->profile?->approval_status;
        $approved = $driver->status === 'active' && $approvalStatus === 'approved';
        $rejectionReason = $approvalStatus === 'rejected' ? $driver->profile->rejection_reason : null;

        if ($request->expectsJson()) {
            return response()->json([
                'approval_status' => $approvalStatus,
                'rejection_reason' => $rejectionReason,
                'dashboard_url' => $approved ? route('delivery-driver.dashboard') : null,
            ]);
        }

        if ($approved) {
            return redirect()->route('delivery-driver.dashboard');
        }

        return view('auth.accounts.approval-status', [
            // Anything not rejected (including a missing profile) is shown as awaiting review.
            'state' => $approvalStatus === 'rejected' ? 'rejected' : 'pending',
            'rejectionReason' => filled($rejectionReason) ? $rejectionReason : null,
            'driverName' => $driver->name,
        ]);
    }
}
