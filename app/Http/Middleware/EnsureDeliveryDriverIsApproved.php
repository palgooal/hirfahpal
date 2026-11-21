<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeliveryDriverIsApproved
{
    /**
     * Ensure only approved drivers can access delivery operations. JSON requests keep the 403; a browser is sent to the
     * approval-status page, which sits outside this gate (no redirect loop). An inactive account never gets here as such:
     * EnsureAccountIsActive (web group) signs it out first.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $driver = $request->user('delivery_driver');

        if ($driver !== null && $driver->status === 'active' && $driver->profile?->approval_status === 'approved') {
            return $next($request);
        }

        if ($driver !== null && ! $request->expectsJson()) {
            return redirect()->route('delivery-driver.approval-status');
        }

        return response()->json([
            'message' => 'Your delivery driver account is not approved for operations.',
            'approval_status' => $driver?->profile?->approval_status,
        ], 403);
    }
}
