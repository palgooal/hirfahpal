<?php

namespace App\Http\Requests\Dashboard;

use App\Models\StorefrontContentPage;
use App\Support\Content\PageContentSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PAGES-03A: saving a content page: its state and, per language, the title, excerpt and SEO texts. A slug is refused:
 * the system pages keep theirs. Arabic needs a title; English is optional, but its title is required as soon as
 * anything English is written or the English page has content. A system page stays published. Over-long texts are
 * refused, never cut.
 *
 * PAGES-03B: the body (body_html, from the visual editor) is optional: left out, the saved body stays. Sent, it is
 * at most 100,000 characters before cleaning (PageContentSanitizer::MAX_LENGTH), cleaned by the controller, and the
 * Arabic one must keep some content.
 *
 * PAGES-04C: a custom page's slug may be sent while it was never published (the creation rules, its own slug
 * excepted); once locked (a system page, or published at least once) it is refused. The controller checks the lock
 * again inside its transaction.
 *
 * PAGES-05B: the languages come from the registry (StorefrontContentPage::editableLocales(): the source language and
 * every enabled one); a language that is not enabled is refused, never ignored. The source language needs a title
 * (and, when sent, a body with content). Any other language may stay empty, or be saved incomplete (a title only)
 * for later; its title is required as soon as anything else in it is written or its body has content. Whether a
 * translation is complete enough to be shown is decided by StorefrontContentPage::isTranslationComplete().
 */
class UpdateContentPageRequest extends FormRequest
{
    /** Each translation field's limit (characters). */
    public const LIMITS = ['title' => 255, 'excerpt' => 500, 'meta_title' => 255, 'meta_description' => 500];

    /** The body's limit before cleaning (characters). */
    public const BODY_LIMIT = PageContentSanitizer::MAX_LENGTH;

    public function authorize(): bool
    {
        return $this->user('admin')?->can('edit', StorefrontContentPage::class) ?? false;
    }

    public function rules(): array
    {
        $page = $this->route('contentPage');
        $fields = implode(',', [...array_keys(self::LIMITS), 'body_html']);
        $rules = [
            'status' => ['required', Rule::in(StorefrontContentPage::STATUSES)],
            'slug' => $page instanceof StorefrontContentPage && ! $page->isSlugLocked() ? ['sometimes', ...$this->slugRules($page->getKey())] : ['prohibited'],
            'translations' => ['required', 'array:'.implode(',', StorefrontContentPage::editableLocales())],
        ];

        foreach (StorefrontContentPage::editableLocales() as $locale) {
            $source = $locale === StorefrontContentPage::SOURCE_LOCALE;
            $rules["translations.{$locale}"] = [$source ? 'required' : 'nullable', 'array:'.$fields];

            foreach (self::LIMITS as $field => $max) {
                $rules["translations.{$locale}.{$field}"] = ['nullable', 'string', 'max:'.$max];
            }

            $rules["translations.{$locale}.body_html"] = ['sometimes', 'nullable', 'string', 'max:'.self::BODY_LIMIT];
            $rules["translations.{$locale}.title"] = $source
                ? ['required', 'string', 'max:'.self::LIMITS['title']]
                : [...$rules["translations.{$locale}.title"], "required_with:translations.{$locale}.excerpt,translations.{$locale}.meta_title,translations.{$locale}.meta_description"];
        }

        return $rules;
    }

    /** PAGES-04B / PAGES-04C: a slug as sent, never changed: the pattern, reserved words, and unique (but for $ignore). */
    protected function slugRules(?int $ignore = null): array
    {
        return ['required', 'string', 'max:'.StorefrontContentPage::SLUG_MAX, 'regex:/^'.StorefrontContentPage::SLUG_PATTERN.'$/',
            Rule::notIn(StorefrontContentPage::RESERVED_SLUGS), Rule::unique('storefront_content_pages', 'slug')->ignore($ignore)];
    }

    public function messages(): array
    {
        return [
            'slug.prohibited' => $this->text('Content_Pages_Slug_Locked_Error', 'This address can no longer be changed: the page is a system page or has been published.'),
            'slug.regex' => $this->text('Content_Pages_Slug_Format', 'Use lowercase English letters, digits and single hyphens, for example our-story.'),
            'slug.not_in' => $this->text('Content_Pages_Slug_Reserved', 'This address is reserved. Choose another one.'),
            'slug.unique' => $this->text('Content_Pages_Slug_Taken', 'Another page already uses this address.'),
            'translations.array' => $this->text('Content_Pages_Language_Unsupported', 'Content was sent for a language that is not enabled.'),
        ];
    }

    /** PAGES-05B / 05C: a language's name in messages and field labels, from the registry (its own name). */
    protected function languageLabel(string $locale): string
    {
        return StorefrontContentPage::languageName($locale);
    }

    /** PAGES-05B: the source language's body is required (StoreContentPageRequest) and must keep content. */
    protected function sourceBodyRequired(): string
    {
        return str_replace(':language', $this->languageLabel(StorefrontContentPage::SOURCE_LOCALE),
            $this->text('Content_Pages_Source_Body_Required', 'The :language page needs some content.'));
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            // PAGES-04B: no page when a new one is created (StoreContentPageRequest).
            $page = $this->route('contentPage');
            $page = $page instanceof StorefrontContentPage ? $page : null;

            if ($page?->is_system && $this->input('status') !== 'published' && ! $validator->errors()->has('status')) {
                $validator->errors()->add('status', $this->text('Content_Pages_System_Published', 'A system page stays published.'));
            }

            if ($validator->errors()->has('translations')) {
                return;
            }

            $sanitizer = app(PageContentSanitizer::class);
            $source = StorefrontContentPage::SOURCE_LOCALE;

            // A sent source-language body must keep some content (cleaning removes what is not allowed).
            if ($this->has("translations.{$source}.body_html") && ! $validator->errors()->has("translations.{$source}.body_html")
                && ! PageContentSanitizer::hasContent($sanitizer->sanitize((string) $this->input("translations.{$source}.body_html")))) {
                $validator->errors()->add("translations.{$source}.body_html", $this->sourceBodyRequired());
            }

            // Any other language needs a title when its page has content: the content sent, or else the saved one.
            foreach (StorefrontContentPage::editableLocales() as $locale) {
                // A language not sent is not changed, so its saved translation is not checked again here.
                if ($locale === $source || ! $this->has("translations.{$locale}")) {
                    continue;
                }

                $body = $this->has("translations.{$locale}.body_html")
                    ? (string) $this->input("translations.{$locale}.body_html")
                    : (string) $page?->translations()->where('locale', $locale)->value('body_html');
                $title = $this->input("translations.{$locale}.title");

                if (PageContentSanitizer::hasContent($sanitizer->sanitize($body)) && (! is_string($title) || trim($title) === '')
                    && ! $validator->errors()->hasAny(["translations.{$locale}.title", "translations.{$locale}.body_html"])) {
                    $validator->errors()->add("translations.{$locale}.title", str_replace(':language', $this->languageLabel($locale),
                        $this->text('Content_Pages_Language_Title_Required', 'The :language page has content, so it needs a title.')));
                }
            }
        }];
    }

    public function attributes(): array
    {
        $languages = collect(StorefrontContentPage::editableLocales())->mapWithKeys(fn (string $locale) => [$locale => $this->languageLabel($locale)]);
        $labels = [
            'title' => $this->text('Content_Pages_Field_Title', 'Page title'),
            'excerpt' => $this->text('Content_Pages_Field_Excerpt', 'Summary'),
            'meta_title' => $this->text('Content_Pages_Field_Meta_Title', 'SEO title'),
            'meta_description' => $this->text('Content_Pages_Field_Meta_Description', 'SEO description'),
            'body_html' => $this->text('Content_Pages_Field_Body', 'Page content'),
        ];
        $attributes = ['status' => $this->text('Content_Pages_Field_Status', 'Status'), 'slug' => $this->text('Content_Pages_Field_Slug', 'Address')];

        foreach ($languages as $locale => $language) {
            foreach ($labels as $field => $label) {
                $attributes["translations.{$locale}.{$field}"] = $label.' ('.$language.')';
            }
        }

        return $attributes;
    }

    protected function text(string $key, string $default): string
    {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    }
}
