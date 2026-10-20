<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Support\SiteAvailability\SiteAvailability;
use App\Support\SiteAvailability\SiteAvailabilityResponse;
use App\Support\SiteAvailability\SiteAvailabilityState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * SITE-AVAILABILITY (docs/deployment/HIRFAH-SITE-AVAILABILITY-DESIGN.md §2, §5): the single gate of the business
 * availability state. In the `web` group it is ordered (bootstrap/app.php, middleware priority) after the session and
 * the CSRF check and before route authentication, throttling, model binding and every controller, so a blocked
 * request — mutations included — never reaches its route.
 *
 * Lets the request through when the state is open, when the path is exempt (admin login and password reset, the
 * dashboard, /up, change-locale), when an active admin may bypass (Super Admin, or maintenance.bypass), or — during
 * coming_soon only — when the route is on the vendor allowlist (DEC-SA-01, by route name). Otherwise answers with
 * SiteAvailabilityResponse. It never signs anyone out and never writes to the session.
 */
class EnsureSiteIsAvailable
{
    /** Request attribute holding the state an admin is previewing (for the Phase 3 storefront indicator). */
    public const PREVIEW_ATTRIBUTE = 'site_availability_preview';

    public function __construct(private readonly SiteAvailability $availability) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Exempt paths first: they never cost a settings read (the dashboard keeps its query budget).
        if ($request->is(...(array) config('site-availability.exempt', []))) {
            return $next($request);
        }

        $state = $this->availability->current();

        if ($state === SiteAvailabilityState::Open) {
            return $next($request);
        }

        $admin = Auth::guard('admin')->user();

        if ($admin instanceof Admin && $this->availability->canBypass($admin)) {
            $request->attributes->set(self::PREVIEW_ATTRIBUTE, $state->value);

            return $next($request);
        }

        if ($state === SiteAvailabilityState::ComingSoon && $this->isAllowedDuringComingSoon($request)) {
            return $next($request);
        }

        return SiteAvailabilityResponse::make($state, $request);
    }

    /** DEC-SA-01: exact route names, or names under an allowed prefix; unnamed or unmatched routes never pass. */
    private function isAllowedDuringComingSoon(Request $request): bool
    {
        $name = $request->route()?->getName();

        if (! is_string($name) || $name === '') {
            return false;
        }

        $allowed = (array) config('site-availability.coming_soon_allowed_routes', []);

        return in_array($name, (array) ($allowed['names'] ?? []), true)
            || Str::startsWith($name, array_values(array_filter((array) ($allowed['prefixes'] ?? []), fn ($prefix) => is_string($prefix) && $prefix !== '')));
    }
}
