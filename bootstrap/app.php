<?php

use App\Http\Middleware\DisableTranslationAutoCreate;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureDeliveryDriverIsApproved;
use App\Http\Middleware\EnsureSiteIsAvailable;
use App\Http\Middleware\EnsureVendorIsApproved;
use App\Http\Middleware\SetLocale;
use App\Support\Auth\AccountGuard;
use App\Support\SiteAvailability\UnmatchedRequestResponse;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('dashboard*')) {
                return route('admin.login');
            }

            if ($request->is('owner*')) {
                return route('owner.login');
            }

            // Exact prefix match so public storefront paths such as /vendors never match an account prefix.
            foreach (AccountGuard::all() as $account) {
                if ($request->is($account['prefix'], $account['prefix'].'/*')) {
                    return route($account['route'].'.login');
                }
            }

            return route('login');
        });

        // A signed-in vendor on the vendor login goes to their own area; the approval gate still guards
        // the dashboard. Every other guest page keeps Laravel's default ("dashboard", else "home").
        $middleware->redirectUsersTo(function (Request $request) {
            $vendor = Auth::guard('vendor')->user();

            if ($vendor !== null && $request->routeIs('vendor.login', 'vendor.login.store')) {
                return route($vendor->isApproved() ? 'vendor.dashboard' : 'vendor.approval-status');
            }

            foreach (['dashboard', 'home'] as $name) {
                if (Route::has($name)) {
                    return route($name);
                }
            }

            return '/';
        });

        $middleware->alias([
            'setLocale' => SetLocale::class,
            'disableTranslationAutoCreate' => DisableTranslationAutoCreate::class,
            'vendor.approved' => EnsureVendorIsApproved::class,
            'delivery-driver.approved' => EnsureDeliveryDriverIsApproved::class,
        ]);

        $middleware->web(append: [
            EnsureAccountIsActive::class,
            EnsureSiteIsAvailable::class,
        ]);

        // Unapproved vendors are stopped before any route-model lookup (VEN-BE-001).
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureVendorIsApproved::class);

        // SITE-AVAILABILITY: the availability gate runs after the session and the CSRF check, and before route
        // authentication, throttling, model binding and the controller, so a blocked request never reaches its route.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EnsureSiteIsAvailable::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // SITE-AVAILABILITY (§5.6): a request no route matched never reaches the gate; while the site is closed it gets
        // the availability response instead of the 404/405 page. Null keeps Laravel's normal rendering.
        $exceptions->render(fn (NotFoundHttpException|MethodNotAllowedHttpException $e, Request $request) => UnmatchedRequestResponse::make($request));
    })->create();
