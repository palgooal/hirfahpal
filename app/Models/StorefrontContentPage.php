<?php

namespace App\Models;

use App\Support\Content\PageContentSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class StorefrontContentPage extends Model
{
    /**
     * PAGES-05B: the content source language: required on every page, the fallback when a language has no complete
     * translation, and the language of the legacy title / excerpt / body columns (the rollback copy). Arabic: all
     * content written so far is Arabic. Not taken from APP_LOCALE (the interface's default, not the content's).
     */
    public const SOURCE_LOCALE = 'ar';

    /** PAGES-05B: the source language's direction when the registry has no row for it (as the storefront layout). */
    public const SOURCE_DIRECTION = 'rtl';

    /**
     * PAGES-05D: the form of a language code in a public page URL (/{locale}/pages/{slug}): lower-case, two or three
     * letters with an optional subtag ("ar", "fil", "pt-br", "zh-hant"). Only the form: whether the language is
     * enabled is checked against the registry for each request (EnsureContentPageLocale).
     */
    public const LOCALE_PATTERN = '[a-z]{2,3}(?:-[a-z0-9]{2,8})?';

    public const STATUSES = ['draft', 'published'];

    /** PAGES-02 / PAGES-03A: the small heading above an Arabic page's title (Arabic pages only; none in English). */
    public const EYEBROW_AR = 'معلومات حِرفة';

    /** PAGES-04B: the seven pages created with the table; they keep their slug and stay published. */
    public const SYSTEM_SLUGS = ['about', 'authenticity', 'shipping', 'returns', 'faq', 'terms', 'privacy'];

    /** PAGES-04B: a custom page's slug: lowercase ASCII words joined by single hyphens, at most SLUG_MAX characters. */
    public const SLUG_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    public const SLUG_MAX = 100;

    /** PAGES-04B: slugs a custom page can never take (the system pages', and words kept for the dashboard's routes). */
    public const RESERVED_SLUGS = [...self::SYSTEM_SLUGS, 'index', 'create', 'edit', 'preview', 'new'];

    /** published_at is not fillable: the saving hook sets it on the first publication and nothing clears it. */
    protected $fillable = ['slug', 'title', 'excerpt', 'body', 'is_published', 'status'];

    private static ?bool $bilingualReady = null;

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'is_system' => 'boolean', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // PAGES-02: status is the page's state; is_published follows it (and the reverse for code that still sets
        // is_published), so both always agree. A new page with neither is a draft.
        static::saving(function (self $page): void {
            if ($page->isDirty('status') && in_array($page->status, self::STATUSES, true)) {
                $page->is_published = $page->status === 'published';
            } elseif ($page->isDirty('is_published')) {
                $page->status = $page->is_published ? 'published' : 'draft';
            } elseif (! $page->exists) {
                $page->status = 'draft';
                $page->is_published = false;
            }

            if (! in_array($page->status, self::STATUSES, true)) {
                $page->status = 'draft';
                $page->is_published = false;
            }

            // PAGES-04B: the first publication's time; kept when the page goes back to draft (the slug lock relies on it).
            if ($page->status === 'published' && $page->published_at === null) {
                $page->published_at = now();
            }
        });
    }

    /**
     * PAGES-04C: a system page's slug never changes; a custom page's is locked from its first publication on
     * (published_at is set then and never cleared, so returning to draft does not unlock it).
     */
    public function isSlugLocked(): bool
    {
        return (bool) $this->is_system || $this->published_at !== null;
    }

    /** PAGES-04C: only a custom page in draft can be deleted (a published one is unpublished first). */
    public function isDeletable(): bool
    {
        return ! $this->is_system && $this->status === 'draft';
    }

    /**
     * PAGES-05B: the languages a page is edited in: the source language first, then every enabled language (the
     * languages registry). A disabled language's saved translation is kept but no longer edited or validated.
     *
     * @return list<string>
     */
    public static function editableLocales(): array
    {
        return array_values(array_unique([self::SOURCE_LOCALE, ...Language::activeCodes()]));
    }

    /**
     * PAGES-05D: the language of a public page URL: the preferred one when it is enabled, else the site's default
     * (config app.locale), else the source language, else the first enabled one; never a disabled language while
     * any is enabled (with an empty registry, the source language).
     */
    public static function publicLocale(?string $preferred = null): string
    {
        foreach ([$preferred, config('app.locale'), self::SOURCE_LOCALE] as $candidate) {
            if (is_string($candidate) && Language::isActiveCode($candidate)) {
                return strtolower($candidate);
            }
        }

        return Language::activeCodes()[0] ?? self::SOURCE_LOCALE;
    }

    /** PAGES-05D: a page's public address, /{locale}/pages/{slug}, in the current language unless one is given. */
    public static function publicUrl(string $slug, ?string $locale = null): string
    {
        return route('content.show', ['locale' => static::publicLocale($locale ?? app()->getLocale()), 'slug' => $slug]);
    }

    /** PAGES-05B: a language's direction from the registry (is_rtl); the source language's when it has no row. */
    public static function directionOf(string $locale): string
    {
        return Language::directionOf($locale) ?? ($locale === self::SOURCE_LOCALE ? self::SOURCE_DIRECTION : 'ltr');
    }

    /**
     * PAGES-05B: the one completeness rule. A translation is complete when its title is not empty, its body (cleaned)
     * holds visible text (empty markup, whitespace or an image alone do not count) and its language is enabled. A
     * saved translation that is not complete is a draft of that language, kept for later editing. Independent of the
     * page's state: see hasPublicTranslation().
     */
    public static function isTranslationComplete(StorefrontContentPageTranslation $translation): bool
    {
        return trim((string) $translation->title) !== ''
            && Language::isActiveCode((string) $translation->locale)
            && PageContentSanitizer::hasText(app(PageContentSanitizer::class)->sanitize($translation->body_html));
    }

    /**
     * PAGES-05C: a language's name as the registry gives it: its own name (native), else its English name, else its
     * code. Never assumed to be "Arabic" or "English".
     */
    public static function languageName(string $locale): string
    {
        $language = Language::active()->get($locale) ?? Language::query()->whereRaw('lower(code) = ?', [$locale])->first();
        $name = trim((string) ($language?->native ?: $language?->name));

        return $name !== '' ? $name : strtoupper($locale);
    }

    /** PAGES-05C: a language's translation: "missing" (no row), "incomplete" (saved, not complete) or "complete". */
    public function translationStatus(string $locale): string
    {
        $row = (static::bilingualReady() ? $this->translations : collect())->firstWhere('locale', $locale);

        return match (true) {
            $row === null => 'missing',
            static::isTranslationComplete($row) => 'complete',
            default => 'incomplete',
        };
    }

    /**
     * PAGES-05B: the languages with a complete translation.
     *
     * @return list<string>
     */
    public function completeLocales(): array
    {
        $translations = static::bilingualReady() ? $this->translations : collect();

        return $translations->filter(fn (StorefrontContentPageTranslation $row) => static::isTranslationComplete($row))
            ->pluck('locale')->values()->all();
    }

    /**
     * PAGES-05E: the page's indexable addresses, language => absolute URL: one per enabled language with a complete
     * translation, in the registry's order; none for a draft. The hreflang alternates and the sitemap entries, so
     * a missing, incomplete or disabled language never appears in either. x-default is the source language's entry,
     * only when it is present.
     *
     * @return array<string, string>
     */
    public function publicAlternates(): array
    {
        if ($this->status !== 'published') {
            return [];
        }

        $complete = $this->completeLocales();

        return collect(Language::activeCodes())->filter(fn (string $locale) => in_array($locale, $complete, true))
            ->mapWithKeys(fn (string $locale) => [$locale => route('content.show', ['locale' => $locale, 'slug' => $this->slug])])->all();
    }

    /**
     * PAGES-05E: an Open Graph locale (language_TERRITORY) for a code that names its territory ("pt-br" → "pt_BR");
     * null for a bare language code ("ar", "en"), whose territory is not known and is not guessed.
     */
    public static function openGraphLocale(string $locale): ?string
    {
        return preg_match('/^([a-z]{2,3})-([a-z]{2})$/', $locale, $parts) ? $parts[1].'_'.strtoupper($parts[2]) : null;
    }

    /** PAGES-05B: whether visitors can read the page in this language: the page is published and that translation complete. */
    public function hasPublicTranslation(string $locale): bool
    {
        return $this->status === 'published' && in_array($locale, $this->completeLocales(), true);
    }

    /** Published pages. is_published is kept in step with status and exists before and after PAGES-02's migration. */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(StorefrontContentPageTranslation::class, 'page_id');
    }

    /**
     * PAGES-02: what the page shows in a language. The language's own translation when it is complete (PAGES-05B:
     * isTranslationComplete()), else the source language's (when it has a title), else the page's original columns
     * (before the migration, or a page without a row). The body is always cleaned by PageContentSanitizer. "locale" /
     * "dir" are the content's own language (PAGES-05B: dir from the registry), which differs from the requested one
     * when it fell back ("fallback").
     *
     * @return array{locale: string, dir: string, title: string, excerpt: ?string, body_html: string, meta_title: string, meta_description: string, fallback: bool}
     */
    public function contentFor(string $locale): array
    {
        $translations = static::bilingualReady() ? $this->translations : collect();
        $translation = $translations->first(fn (StorefrontContentPageTranslation $row) => $row->locale === $locale && static::isTranslationComplete($row))
            ?? $translations->first(fn (StorefrontContentPageTranslation $row) => $row->locale === self::SOURCE_LOCALE && trim((string) $row->title) !== '');
        $sanitizer = app(PageContentSanitizer::class);

        if ($translation !== null) {
            $content = [
                'locale' => $translation->locale,
                'title' => trim($translation->title),
                'excerpt' => filled($translation->excerpt) ? trim($translation->excerpt) : null,
                'body_html' => $sanitizer->sanitize($translation->body_html),
                'meta_title' => trim((string) $translation->meta_title),
                'meta_description' => trim((string) $translation->meta_description),
            ];
        } else {
            $content = [
                'locale' => self::SOURCE_LOCALE,
                'title' => trim((string) $this->title),
                'excerpt' => filled($this->excerpt) ? trim($this->excerpt) : null,
                'body_html' => $sanitizer->sanitize(PageContentSanitizer::fromPlainText($this->body)),
                'meta_title' => '',
                'meta_description' => '',
            ];
        }

        return [
            ...$content,
            'dir' => static::directionOf($content['locale']),
            'meta_title' => $content['meta_title'] !== '' ? $content['meta_title'] : $content['title'],
            'meta_description' => $content['meta_description'] !== '' ? $content['meta_description'] : ($content['excerpt'] ?? $content['title']),
            'fallback' => $content['locale'] !== $locale,
        ];
    }

    /** Whether the translations table exists (PAGES-02's migration ran); checked once per request. */
    public static function bilingualReady(): bool
    {
        return static::$bilingualReady ??= Schema::hasTable('storefront_content_page_translations');
    }

    /** For tests that change the schema within one process. */
    public static function forgetBilingualReady(): void
    {
        static::$bilingualReady = null;
    }
}
