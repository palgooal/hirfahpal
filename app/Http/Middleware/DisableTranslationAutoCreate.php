<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops t() from inserting missing translation keys while a request is
 * handled (used on guest pages whose strings are seeded ahead of time).
 * The previous value is restored afterwards so long-running workers keep
 * the global configuration.
 */
class DisableTranslationAutoCreate
{
    public function handle(Request $request, Closure $next): Response
    {
        $previous = config('palgoals-locale.auto_create');

        config(['palgoals-locale.auto_create' => false]);

        try {
            return $next($request);
        } finally {
            config(['palgoals-locale.auto_create' => $previous]);
        }
    }
}
