<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * VEN-BE-001: operational vendor routes require an approved store.
 * Non-approved vendors are sent to the approval-status route instead of the
 * operational dashboard.
 *
 * It is prioritised before SubstituteBindings (bootstrap/app.php) so an
 * unapproved vendor never reaches route-model lookups. That places it before
 * EnsureAccountIsActive, so inactive accounts are passed through untouched:
 * EnsureAccountIsActive runs next and signs them out, keeping account status
 * ahead of approval status.
 */
class EnsureVendorIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $vendor = $request->user('vendor');

        if ($vendor === null || $vendor->status !== 'active' || $vendor->isApproved()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your vendor account is not approved yet.',
                'approval_status' => $vendor->profile?->approval_status,
                'status_url' => route('vendor.approval-status'),
            ], 403);
        }

        return redirect()->route('vendor.approval-status');
    }
}
