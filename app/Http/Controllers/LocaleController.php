<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\Portfolio;
use App\Models\Template;
use App\Models\TranslationValue;
use Illuminate\Http\Request;
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

        $previousUrl = url()->previous();
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
                        'preview'  => redirect()->route('template.preview',       ['slug' => $translated->slug]),
                        default    => redirect()->route('template.show',          ['slug' => $translated->slug]),
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

        return preg_match('/\.(?:css|js|map|png|jpe?g|svg|gif|webp|ico|woff2?|ttf|eot)$/', $normalizedPath) === 1;
    }

    private function normalizeRedirectUrl(?string $redirect): ?string
    {
        $redirect = trim((string) $redirect);

        if ($redirect === '') {
            return null;
        }

        if (Str::startsWith($redirect, '/')) {
            return url($redirect);
        }

        if (! filter_var($redirect, FILTER_VALIDATE_URL)) {
            return null;
        }

        $redirectHost = strtolower((string) parse_url($redirect, PHP_URL_HOST));
        $appHost      = strtolower((string) parse_url(config('app.url') ?: url('/'), PHP_URL_HOST));

        if ($redirectHost === '' || $appHost === '' || $redirectHost !== $appHost) {
            return null;
        }

        return $redirect;
    }
}
