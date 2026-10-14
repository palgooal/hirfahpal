<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeliveryDriverIsApproved
{
    /** Ensure only approved drivers can access delivery operations. */
    public function handle(Request $request, Closure $next): Response
    {
        $driver = $request->user('delivery_driver');

        if ($driver !== null && $driver->status === 'active' && $driver->profile?->approval_status === 'approved') {
            return $next($request);
        }

        return response()->json([
            'message' => 'Your delivery driver account is not approved for operations.',
            'approval_status' => $driver?->profile?->approval_status,
        ], 403);
    }
}
