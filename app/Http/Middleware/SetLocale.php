<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

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
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ── Resolve the default language ───────────────────────────────────────
        $default_language = $this->resolveDefaultLanguage();

        // ── Handle ?change-locale=xx on any URL ────────────────────────────────
        if ($request->has('change-locale')) {
            $requested = $request->query('change-locale');
            $newLocale = is_string($requested) ? Str::lower($requested) : null;
            $supportedLocales = $this->getSupportedLocales();

            if (in_array($newLocale, $supportedLocales)) {
                session(['locale' => $newLocale]);
                app()->setLocale($newLocale);
            }

            // VQA-FIX-02: redirect to the same URL without the change-locale param only; every other query parameter
            // (a product's slug, browse filters, search, sorting) is kept exactly as sent. Same path and host, and the
            // switch itself is gone, so this can neither leave the site nor loop.
            $query = implode('&', self::queryPairsWithoutSwitch($request));

            return redirect()->to($request->url().($query !== '' ? '?'.$query : ''));
        }

        // ── Apply locale from session or default ───────────────────────────────
        $locale = Str::lower(session('locale', $default_language));
        $supportedLocales = $this->getSupportedLocales();

        app()->setLocale(
            in_array($locale, $supportedLocales) ? $locale : $default_language
        );

        return $next($request);
    }

    /**
     * VQA-FIX-02: the request's query string as raw "key=value" pairs, without change-locale. Read from the raw
     * QUERY_STRING rather than the parsed query, so repeated keys (category=a&category=b, which PHP's parsing would
     * reduce to the last one), their order and their encoding are kept. Also used by the storefront language switcher
     * (storefront.partials.locale-query-inputs) to carry the current parameters through its GET form.
     *
     * @return list<string>
     */
    public static function queryPairsWithoutSwitch(Request $request): array
    {
        $pairs = explode('&', (string) $request->server->get('QUERY_STRING', ''));

        return array_values(array_filter($pairs, function (string $pair): bool {
            $name = urldecode(explode('=', $pair, 2)[0]);

            // change-locale and change-locale[...] alike.
            return $pair !== '' && preg_replace('/\[.*$/s', '', $name) !== 'change-locale';
        }));
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
