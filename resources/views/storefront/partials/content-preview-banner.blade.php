{{--
    PAGES-03A: the dashboard's private preview bar of a content page, in place of the storefront's top bar, header and
    footer (StorefrontContentPageController is not involved: ContentPageManagementController::preview()). Says that
    this is the saved version, whether visitors can see it, and when a language falls back to the source one; links back to the
    page's edit form. Texts in the page language, read without auto-creating rows (the route disables it).
--}}
@php
    $cpText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };
    $previewPublished = ($previewPage->status ?? 'draft') === 'published';
    // PAGES-05C: when the requested language falls back, say whether its translation is missing or incomplete, with
    // both languages named by the registry.
    $fallbackText = null;
    if ($content['fallback'] ?? false) {
        $requested = $previewLocale ?? app()->getLocale();
        $fallbackText = str_replace([':language', ':source'], [
            \App\Models\StorefrontContentPage::languageName($requested), \App\Models\StorefrontContentPage::languageName($content['locale']),
        ], $previewPage->translationStatus($requested) === 'incomplete'
            ? $cpText('Content_Pages_Preview_Fallback_Incomplete', 'The :language version is not complete yet (it needs a title and content), so the :source one is shown.')
            : $cpText('Content_Pages_Preview_Fallback_Missing', 'There is no :language version yet, so the :source one is shown.'));
    }
@endphp
<div role="region" aria-labelledby="content-preview-title" data-content-preview="{{ $previewPublished ? 'published' : 'draft' }}" class="bg-ink text-white">
    <div class="mx-auto flex max-w-[1216px] flex-wrap items-center justify-between gap-3 px-4 py-3 lg:px-8">
        <div class="min-w-0">
            <p id="content-preview-title" class="text-sm font-bold leading-6">{{ $cpText('Content_Pages_Preview_Title', 'Page preview') }}</p>
            <p class="text-xs leading-5 text-white/85">{{ $previewPublished
                ? $cpText('Content_Pages_Preview_Published', 'The saved page, as visitors see it.')
                : $cpText('Content_Pages_Preview_Draft', 'The saved page. It is a draft: visitors cannot see it.') }}</p>
            @if ($fallbackText !== null)
                <p class="text-xs leading-5 text-white/85" data-content-preview-fallback="{{ $previewPage->translationStatus($previewLocale ?? app()->getLocale()) }}">{{ $fallbackText }}</p>
            @endif
        </div>
        <a href="{{ route('dashboard.content-pages.edit', $previewPage) }}" class="inline-flex min-h-11 items-center rounded-full border border-white/30 px-4 text-sm font-semibold text-white hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sage">{{ $cpText('Content_Pages_Preview_Back', 'Back to editing') }}</a>
    </div>
</div>
