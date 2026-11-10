<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * PAGES-05D: the language of a public content page comes from its URL (/{locale}/pages/{slug}), in place of SetLocale
 * on that route only. A language that is not enabled in the registry is not found. The URL language is set for this
 * request and never written to the session, so it does not change the language of the rest of the site.
 *
 * The storefront switcher sends ?change-locale=xx to the current URL. Here that choice is saved as the visitor's
 * language, as SetLocale does, and the visitor is sent to the same page in that language (an unknown choice: the same
 * page without the parameter), so the URL never contradicts the switch.
 */
class EnsureContentPageLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->route('locale');

        abort_unless(Language::isActiveCode($locale) && $locale === Str::lower($locale), 404);

        if ($request->has('change-locale')) {
            $requested = $request->query('change-locale');
            $requested = is_string($requested) ? Str::lower($requested) : null;

            if ($requested !== null && Language::isActiveCode($requested)) {
                session(['locale' => $requested]);
                $locale = $requested;
            }

            return redirect()->route('content.show', ['locale' => $locale, 'slug' => $request->route('slug')]);
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
