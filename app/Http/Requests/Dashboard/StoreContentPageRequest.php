<?php

namespace App\Http\Requests\Dashboard;

use App\Models\StorefrontContentPage;

/**
 * PAGES-04B: creating a custom content page (content-pages.view and content-pages.create). The same fields and checks
 * as saving one (UpdateContentPageRequest), plus the slug, and the source language's body is required. The slug is checked as
 * sent, never changed: lowercase ASCII words joined by single hyphens (StorefrontContentPage::SLUG_PATTERN), at most
 * SLUG_MAX characters, not reserved, and not used by another page (also unique in the database).
 */
class StoreContentPageRequest extends UpdateContentPageRequest
{
    public function authorize(): bool
    {
        $admin = $this->user('admin');

        return $admin !== null && $admin->can('view', StorefrontContentPage::class) && $admin->can('create', StorefrontContentPage::class);
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'slug' => $this->slugRules(),
            'translations.'.StorefrontContentPage::SOURCE_LOCALE.'.body_html' => ['required', 'string', 'max:'.self::BODY_LIMIT],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'translations.'.StorefrontContentPage::SOURCE_LOCALE.'.body_html.required' => $this->sourceBodyRequired(),
        ];
    }
}
