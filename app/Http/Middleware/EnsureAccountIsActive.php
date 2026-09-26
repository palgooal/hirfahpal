<?php

namespace App\Http\Middleware;

use App\Support\Auth\AccountGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Status is only checked at login, so this re-checks it on every web request:
 * a HIRFAH account that is no longer active loses its authentication for that
 * guard (session and remember cookie) while other guards stay signed in.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $redirectRoute = null;

        foreach ($this->guards() as $guard => $loginRoute) {
            $user = Auth::guard($guard)->user();

            if ($user === null || $user->status === 'active') {
                continue;
            }

            // logout() also forgets the remember cookie and cycles remember_token.
            Auth::guard($guard)->logout();

            $redirectRoute ??= $loginRoute;
        }

        if ($redirectRoute === null) {
            return $next($request);
        }

        // migrate() instead of invalidate() keeps other guards' sessions intact.
        $request->session()->migrate(true);
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => __('auth.inactive')], 401);
        }

        return redirect()->route($redirectRoute)->withErrors(['login' => __('auth.inactive')]);
    }

    /**
     * Guards in a fixed order, mapped to their own login route.
     *
     * @return array<string, string>
     */
    private function guards(): array
    {
        $guards = ['admin' => 'admin.login'];

        foreach (AccountGuard::all() as $account) {
            $guards[$account['guard']] = $account['route'].'.login';
        }

        return $guards;
    }
}
