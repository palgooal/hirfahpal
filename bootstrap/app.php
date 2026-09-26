<?php

use App\Http\Middleware\DisableTranslationAutoCreate;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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

            return route('login');
        });

        $middleware->alias([
            'setLocale' => SetLocale::class,
            'disableTranslationAutoCreate' => DisableTranslationAutoCreate::class,
        ]);

        $middleware->web(append: [
            EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
