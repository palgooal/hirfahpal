<?php

use App\Http\Middleware\DisableTranslationAutoCreate;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureVendorIsApproved;
use App\Http\Middleware\SetLocale;
use App\Support\Auth\AccountGuard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

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

        $middleware->alias([
            'setLocale' => SetLocale::class,
            'disableTranslationAutoCreate' => DisableTranslationAutoCreate::class,
            'vendor.approved' => EnsureVendorIsApproved::class,
        ]);

        $middleware->web(append: [
            EnsureAccountIsActive::class,
        ]);

        // Unapproved vendors are stopped before any route-model lookup (VEN-BE-001).
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureVendorIsApproved::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
