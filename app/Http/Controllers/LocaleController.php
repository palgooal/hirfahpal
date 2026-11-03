<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\Portfolio;
use App\Models\Template;
use App\Models\TranslationValue;
use Illuminate\Support\Str;

/**
 * LocaleController
 *
 * Handles:
 *  - GET /change-locale/{locale}         → switch active language + smart redirect
 *  - GET /translate-json/{locale}        → return all translations as JSON (for JS/SPA)
 */
class LocaleController extends Controller
{
    /**
     * Change the active language, save it in the session,
     * and redirect the user back to the same page with the translated slug (if available).
     */
    public function change($locale)
    {
        $language = Language::where('code', $locale)->where('is_active', true)->first();

        if ($language) {
            session(['locale' => $locale]);
        }

        $redirect = trim((string) request()->query('redirect', ''));
        $safeRedirect = $this->normalizeRedirectUrl($redirect);

        if ($safeRedirect) {
            return redirect()->to($safeRedirect);
        }

        // SEC-01: the previous URL comes from the Referer header first, so only a same-site one is followed.
        $previousUrl = url()->previous();
        if (! $this->isSameSiteUrl($previousUrl)) {
            $previousUrl = url('/');
        }
        $parsed = parse_url($previousUrl);
        $path = $parsed['path'] ?? '/';

        // If on a Template page → redirect to the translated template slug
        if (preg_match('#^/templates/([^/]+)(?:/(redesign|preview))?$#', $path, $matches)) {
            $currentSlug = $matches[1];
            $variant = $matches[2] ?? null;

            $template = Template::whereHas('translations', function ($q) use ($currentSlug) {
                $q->where('slug', $currentSlug);
            })->first();

            if ($template) {
                $translated = $template->getTranslation($locale);
                if ($translated) {
                    return match ($variant) {
                        'redesign' => redirect()->route('template.show.redesign', ['slug' => $translated->slug]),
                        'preview' => redirect()->route('template.preview', ['slug' => $translated->slug]),
                        default => redirect()->route('template.show', ['slug' => $translated->slug]),
                    };
                }
            }
        }

        // If on a Portfolio page → redirect to the translated portfolio slug
        if (preg_match('#/portfolio/([^/]+)#', $path, $matches)) {
            $currentSlug = $matches[1];

            $portfolio = Portfolio::whereHas('translations', function ($q) use ($currentSlug) {
                $q->where('slug', $currentSlug);
            })->first();

            if ($portfolio) {
                $translated = $portfolio->getTranslation($locale);
                if ($translated) {
                    return redirect()->route('portfolio.show', ['slug' => $translated->slug]);
                }
            }
        }

        if ($this->isUnsafePreviousPath($path)) {
            return redirect()->to(url('/'));
        }

        return redirect()->to($previousUrl ?: url('/'));
    }

    /**
     * Return all translations for a given locale as a flat JSON object.
     * Useful for JavaScript / SPA frontends.
     *
     * Example response:
     *  { "frontend.nav.home": "الرئيسية", "frontend.hero.title": "..." }
     */
    public function translateJson($locale)
    {
        $translations = TranslationValue::where('locale', $locale)->pluck('value', 'key');

        return response()->json($translations);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function isUnsafePreviousPath(string $path): bool
    {
        $normalizedPath = ltrim(strtolower(trim($path)), '/');

        if ($normalizedPath === '') {
            return false;
        }

        if (str_starts_with($normalizedPath, 'assets/') || str_starts_with($normalizedPath, 'storage/')) {
            return true;
        }

        // SEC-01: never back to a language switch itself (the session's previous URL can be one), which would loop.
        if (str_starts_with($normalizedPath, 'change-locale/')) {
            return true;
        }

        return preg_match('/\.(?:css|js|map|png|jpe?g|svg|gif|webp|ico|woff2?|ttf|eot)$/', $normalizedPath) === 1;
    }

    private function normalizeRedirectUrl(?string $redirect): ?string
    {
        $redirect = trim((string) $redirect);

        if ($redirect === '') {
            return null;
        }

        // SEC-01: a same-site path has exactly one leading slash. "//host" is protocol-relative, "/\host" is read as
        // "//host" by browsers, and browsers drop tabs and line breaks ("/\t/host"), so all of these are refused.
        if (Str::startsWith($redirect, '/')) {
            if (preg_match('~^/[/\\\\]~', $redirect) === 1 || preg_match('/[\x00-\x1F\x7F]/', $redirect) === 1) {
                return null;
            }

            return url($redirect);
        }

        return $this->isSameSiteUrl($redirect) ? $redirect : null;
    }

    /**
     * SEC-01: an absolute http(s) URL on this site's host (the configured app URL's, or the current request's), with
     * no embedded credentials, backslashes or control characters.
     */
    private function isSameSiteUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || preg_match('/[\x00-\x1F\x7F\\\\]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $siteHosts = array_filter([
            strtolower((string) parse_url(config('app.url') ?: url('/'), PHP_URL_HOST)),
            strtolower(request()->getHost()),
        ]);

        return in_array($scheme, ['http', 'https'], true)
            && $host !== ''
            && in_array($host, $siteHosts, true)
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }
}
