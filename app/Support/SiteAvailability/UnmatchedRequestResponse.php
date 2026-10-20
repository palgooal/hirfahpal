<?php

namespace App\Support\SiteAvailability;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * SITE-AVAILABILITY Phase 2B (docs/deployment/HIRFAH-SITE-AVAILABILITY-DESIGN.md §5.6, DEC-SA-09, D1–D4): the answer
 * for a request the router could not match (unknown URL, or a known path with an unsupported method). Such a request
 * fails before the `web` middleware, so the gate never sees it; the exception handler (bootstrap/app.php) asks this
 * class instead.
 *
 * Returns the availability response while the site is closed, or null — Laravel's normal 404/405 rendering — when the
 * site is open, a route was matched (exceptions thrown inside a route are never intercepted), the path is exempt from
 * the gate, or the path is a static resource (D1). No session, no authentication (D4: admin bypass is not evaluated),
 * no CSRF; the only possible database access is the state read the gate also makes. Never throws.
 */
class UnmatchedRequestResponse
{
    public static function make(Request $request): ?Response
    {
        try {
            if ($request->route() !== null || $request->is(...(array) config('site-availability.exempt', [])) || self::isStaticResource($request)) {
                return null;
            }

            $state = app(SiteAvailability::class)->current();

            if ($state === SiteAvailabilityState::Open) {
                return null;
            }

            $response = SiteAvailabilityResponse::make($state, $request);

            // D3: only GET and HEAD may get 200 (coming_soon); OPTIONS and any other method get 503.
            if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
                $response->setStatusCode(503);
            }

            return $response->prepare($request);
        } catch (Throwable) {
            return null;
        }
    }

    /** D1: whole path segments only — `build` matches `build` and `build/…`, never `builder`. */
    private static function isStaticResource(Request $request): bool
    {
        $path = trim($request->path(), '/');
        $rules = (array) config('site-availability.unmatched_static', []);

        if (in_array($path, (array) ($rules['paths'] ?? []), true)) {
            return true;
        }

        foreach ((array) ($rules['prefixes'] ?? []) as $prefix) {
            $prefix = is_string($prefix) ? trim($prefix, '/') : '';

            if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix.'/'))) {
                return true;
            }
        }

        return false;
    }
}
