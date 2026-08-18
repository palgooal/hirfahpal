<?php

/**
 * ─────────────────────────────────────────────────────────────────────────────
 * PalGoals Locale Helpers
 * ─────────────────────────────────────────────────────────────────────────────
 * Drop this file anywhere that is auto-loaded (e.g. add to composer.json:
 *
 *   "autoload": {
 *       "files": ["app/helpers_locale.php"]
 *   }
 *
 * These helpers require:
 *   - App\Models\Language
 *   - App\Models\TranslationValue
 *   - App\Models\Page  (only for page_slug())
 * ─────────────────────────────────────────────────────────────────────────────
 */

use App\Models\Language;
use App\Models\TranslationValue;

// ═════════════════════════════════════════════════════════════════════════════
// 1) current_dir() — RTL / LTR direction for the active language
// ═════════════════════════════════════════════════════════════════════════════
if (! function_exists('current_dir')) {
    /**
     * Return 'rtl' or 'ltr' based on the current language's is_rtl flag.
     *
     * Usage in Blade:
     *   <html dir="{{ current_dir() }}" lang="{{ app()->getLocale() }}">
     */
    function current_dir(): string
    {
        $language = Language::where('code', app()->getLocale())
            ->where('is_active', true)
            ->first();

        return ($language && $language->is_rtl) ? 'rtl' : 'ltr';
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// 2) available_locales() — all active languages
// ═════════════════════════════════════════════════════════════════════════════
if (! function_exists('available_locales')) {
    /**
     * Return a Collection of all active Language models.
     *
     * @return \Illuminate\Support\Collection|\App\Models\Language[]
     */
    function available_locales()
    {
        return Language::where('is_active', true)->get();
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// 3) t() — DB-driven translation with cache + auto-create + fallback
// ═════════════════════════════════════════════════════════════════════════════
if (! function_exists('t')) {
    /**
     * Fetch a translation value from the database (table: translation_values).
     *
     * Features:
     *  - Cache: TTL from config('palgoals-locale.cache_ttl'), default 60 s
     *  - Auto-create: if a key is missing and palgoals-locale.auto_create=true,
     *    an empty row is created so it shows up in the admin panel.
     *  - Fallback: if the current locale has no value, tries fallback_locale,
     *    then returns $default (or the key itself as last resort).
     *
     * @param  string      $key     Dot-notation key, e.g. "frontend.nav.home"
     * @param  string|null $default Value to return when no translation exists
     * @return string
     *
     * Usage:
     *   {{ t('frontend.nav.home') }}
     *   {{ t('frontend.hero.title', 'Welcome') }}
     *   {!! t_html('frontend.about.description') !!}
     */
    function t(string $key, ?string $default = null): string
    {
        $locale         = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');
        $cacheTtl       = (int) config('palgoals-locale.cache_ttl', 60);
        $autoCreate     = (bool) config('palgoals-locale.auto_create', true);

        $cacheKey = "translation.{$locale}.{$key}";

        $value = cache()->remember($cacheKey, $cacheTtl, function () use ($key, $locale, $default, $autoCreate) {
            $translation = TranslationValue::where('key', $key)
                ->where('locale', $locale)
                ->first();

            if (! $translation && $autoCreate) {
                TranslationValue::create([
                    'key'    => $key,
                    'locale' => $locale,
                    'value'  => $default ?? '',
                ]);
            }

            return $translation?->value;
        });

        if ($value !== null) {
            return $value;
        }

        // Fallback to fallback_locale
        if ($locale !== $fallbackLocale) {
            $fallbackCacheKey = "translation.{$fallbackLocale}.{$key}";

            $fallbackValue = cache()->remember(
                $fallbackCacheKey,
                $cacheTtl,
                function () use ($key, $fallbackLocale, $default, $autoCreate) {
                    $translation = TranslationValue::where('key', $key)
                        ->where('locale', $fallbackLocale)
                        ->first();

                    if (! $translation && $autoCreate) {
                        TranslationValue::create([
                            'key'    => $key,
                            'locale' => $fallbackLocale,
                            'value'  => $default ?? '',
                        ]);
                    }

                    return $translation?->value;
                }
            );

            if ($fallbackValue !== null) {
                return $fallbackValue;
            }
        }

        return $default ?? $key;
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// 4) t_html() — same as t() but intended for {!! !!} (no double-escaping)
// ═════════════════════════════════════════════════════════════════════════════
if (! function_exists('t_html')) {
    /**
     * HTML-safe translation helper. Use inside {!! t_html('key') !!} blocks.
     */
    function t_html(string $key, ?string $default = null): string
    {
        return t($key, $default);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// 5) page_slug() — resolve a CMS page slug for the current locale
// ═════════════════════════════════════════════════════════════════════════════
if (! function_exists('page_slug')) {
    /**
     * Return the correct URL slug for a CMS page in the current (or given) locale.
     *
     * Requires App\Models\Page to exist in your project with a `translations()`
     * relationship. If the model is not found, the canonical key is returned as-is.
     *
     * Fallback chain:
     *   translated slug (current locale)
     *   → translated slug (fallback locale)
     *   → $canonicalKey as-is
     *
     * @param  string      $canonicalKey  Slug in the fallback/default language
     * @param  string|null $locale        Force a specific locale (optional)
     * @return string
     *
     * Usage:
     *   <a href="/{{ page_slug('about') }}">{{ t('frontend.nav.about') }}</a>
     */
    function page_slug(string $canonicalKey, ?string $locale = null): string
    {
        // Guard: Page model is optional — only available in projects that have it
        if (! class_exists(\App\Models\Page::class)) {
            return $canonicalKey;
        }

        $locale         = $locale ?: app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');

        // 1. Find the page by its canonical slug in the fallback locale
        $page = \App\Models\Page::query()
            ->whereHas('translations', fn ($q) => $q
                ->where('slug', $canonicalKey)
                ->where('locale', $fallbackLocale))
            ->first();

        // 2. If not found, try any locale
        if (! $page) {
            $page = \App\Models\Page::query()
                ->whereHas('translations', fn ($q) => $q->where('slug', $canonicalKey))
                ->first();

            if (! $page) {
                return $canonicalKey;
            }
        }

        // 3. Get translation for the requested locale
        $trans = $page->translations()->where('locale', $locale)->first();

        // 4. Fallback to fallback_locale if no translation for current locale
        if (! $trans && $locale !== $fallbackLocale) {
            $trans = $page->translations()->where('locale', $fallbackLocale)->first();
        }

        return $trans->slug ?? $canonicalKey;
    }
}
