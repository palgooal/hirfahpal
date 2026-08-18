<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * Priority order:
     * 1. ?change-locale=xx  query param  → saves to session, redirects clean
     * 2. session('locale')               → previously selected language
     * 3. palgoals-locale.default_language_resolver → optional callable in config
     * 4. config('app.locale')             → .env fallback
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ── Resolve the default language ───────────────────────────────────────
        $default_language = $this->resolveDefaultLanguage();

        // ── Handle ?change-locale=xx on any URL ────────────────────────────────
        if ($request->has('change-locale')) {
            $newLocale        = Str::lower($request->query('change-locale'));
            $supportedLocales = $this->getSupportedLocales();

            if (in_array($newLocale, $supportedLocales)) {
                session(['locale' => $newLocale]);
                app()->setLocale($newLocale);
            }

            // Redirect to the same URL without the query param (clean URL)
            return redirect()->to($request->url());
        }

        // ── Apply locale from session or default ───────────────────────────────
        $locale           = Str::lower(session('locale', $default_language));
        $supportedLocales = $this->getSupportedLocales();

        app()->setLocale(
            in_array($locale, $supportedLocales) ? $locale : $default_language
        );

        return $next($request);
    }

    /**
     * Resolve the default language code.
     *
     * Uses the callable defined in config('palgoals-locale.default_language_resolver')
     * if provided, otherwise falls back to config('app.locale').
     *
     * To integrate with your own settings model, add this to config/palgoals-locale.php:
     *
     *   'default_language_resolver' => function () {
     *       return \App\Models\GeneralSetting::first()?->default_language_code
     *           ?? config('app.locale');
     *   },
     */
    private function resolveDefaultLanguage(): string
    {
        $resolver = config('palgoals-locale.default_language_resolver');

        if (is_callable($resolver)) {
            $result = call_user_func($resolver);
            if ($result) {
                return (string) $result;
            }
        }

        return config('app.locale', 'en');
    }

    /**
     * Return an array of active supported locale codes (lower-cased).
     */
    private function getSupportedLocales(): array
    {
        return array_map(
            'strtolower',
            Language::where('is_active', true)->pluck('code')->toArray()
        );
    }
}
