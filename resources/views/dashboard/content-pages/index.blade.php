{{--
    PAGES-03A: the storefront content pages (content-pages.view). One row per page: its Arabic title and address, its
    state, its translations (PAGES-05C), the system mark and the last update; Edit (content-pages.edit) or View, and the
    preview (new tab). A list, not a table: seven pages, the same on every screen.

    PAGES-04B: "New page" (content-pages.create) opens the same form for a custom page.

    PAGES-04C: each row says whether it is a system or a custom page. A published page's public address can be copied;
    a custom draft can be deleted (content-pages.delete) after a confirmation dialog. Both buttons are revealed by
    resources/js/admin-content-pages.js, so without the script nothing is deleted unconfirmed.

    PAGES-04E: one Preview per page, in the dashboard's language when pages can have content in it, otherwise in the
    source language. A page without that language previews in the source language, as before.

    PAGES-05C: every language of the registry (StorefrontContentPage::editableLocales()) shows its translation's state
    for each page: complete, incomplete or missing (StorefrontContentPage::translationStatus()), by colour, icon and
    text for screen readers. The title is shown in the source language and its direction.
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
    $newTab = $cpText('Content_Pages_Opens_New_Tab', 'opens in a new tab');
    $copyLabel = $cpText('Content_Pages_Copy_Link', 'Copy link');
    $deleteLabel = $cpText('Content_Pages_Delete', 'Delete');
    $previewLabel = $cpText('Content_Pages_Preview', 'Preview');
    $editable = \App\Models\StorefrontContentPage::editableLocales();
    $sourceLocale = \App\Models\StorefrontContentPage::SOURCE_LOCALE;
    $sourceDirection = \App\Models\StorefrontContentPage::directionOf($sourceLocale);
    $previewLocale = in_array(app()->getLocale(), $editable, true) ? app()->getLocale() : $sourceLocale;
    $languageNames = collect($editable)->mapWithKeys(fn (string $locale) => [$locale => [
        \App\Models\StorefrontContentPage::languageName($locale), \App\Models\StorefrontContentPage::directionOf($locale),
    ]]);
    $statusBadges = [
        'complete' => ['is-active', 'ph-check-circle', $cpText('Content_Pages_Translation_Complete', 'Complete')],
        'incomplete' => ['is-draft', 'ph-circle-half', $cpText('Content_Pages_Translation_Incomplete', 'Incomplete')],
        'missing' => ['is-inactive', 'ph-circle-dashed', $cpText('Content_Pages_Translation_Missing', 'Missing')],
    ];
@endphp
<x-dashboard-layout>
    <x-slot:pageTitle>{{ $cpText('Nav_Content_Pages', 'Content pages') }}</x-slot:pageTitle>

    <div class="col-span-12 admin-media admin-products admin-content-pages" id="admin-content-pages">
        <div class="admin-media-head">
            <p class="admin-media-intro">{{ $cpText('Content_Pages_Intro_Languages', 'The information pages of the store, in each enabled language: their titles, summaries, content and search engine texts.') }}</p>
            @if ($canCreate)
                <a href="{{ route('dashboard.content-pages.create') }}" class="btn btn-primary" data-content-page-create>
                    <i class="ph-duotone ph-plus" aria-hidden="true"></i><span>{{ $cpText('Content_Pages_Create', 'New page') }}</span>
                </a>
            @endif
        </div>

        @foreach (['success' => ['is-success', 'ph-check-circle', 'status'], 'error' => ['is-error', 'ph-warning', 'alert']] as $flash => [$tone, $icon, $role])
            @if (session($flash))
                <div class="admin-media-alert {{ $tone }}" role="{{ $role }}">
                    <i class="ph-duotone {{ $icon }}" aria-hidden="true"></i>
                    <div class="admin-media-alert-content"><p>{{ session($flash) }}</p></div>
                </div>
            @endif
        @endforeach

        <section class="card admin-media-panel" aria-labelledby="content-pages-list-title">
            <h2 class="visually-hidden" id="content-pages-list-title">{{ $cpText('Content_Pages_List', 'Pages list') }}</h2>
            <p class="visually-hidden" role="status" data-copy-status></p>
            <ul class="admin-content-pages-list" data-content-pages-list
                data-copied="{{ $cpText('Content_Pages_Link_Copied', 'The link was copied.') }}"
                data-copy-failed="{{ $cpText('Content_Pages_Link_Copy_Failed', 'The link could not be copied. The address is :url') }}">
                @foreach ($pages as $page)
                    @php($title = $page->translations->firstWhere('locale', $sourceLocale)?->title ?: $page->title)
                    <li class="admin-content-pages-item" data-content-page="{{ $page->slug }}">
                        <div class="admin-content-pages-main">
                            <a href="{{ route('dashboard.content-pages.edit', $page) }}" class="admin-content-pages-title" lang="{{ $sourceLocale }}" dir="{{ $sourceDirection }}"
                                aria-label="{{ str_replace(':title', $title, $canEdit ? $cpText('Content_Pages_Edit_Page', 'Edit :title') : $cpText('Content_Pages_View_Page', 'View :title')) }}">{{ $title }}</a>
                            <span class="admin-content-pages-slug" dir="ltr">/pages/{{ $page->slug }}</span>
                        </div>
                        <div class="admin-content-pages-meta">
                            <span class="admin-products-badge {{ $page->status === 'published' ? 'is-active' : 'is-draft' }}" data-content-status="{{ $page->status }}">{{ $page->status === 'published' ? $cpText('Content_Pages_Status_Published', 'Published') : $cpText('Content_Pages_Status_Draft', 'Draft') }}</span>
                            <span class="admin-content-pages-languages" role="list" aria-label="{{ $cpText('Content_Pages_Translations', 'Translations') }}">
                                @foreach ($languageNames as $locale => [$languageName, $direction])
                                    @php([$tone, $icon, $statusText] = $statusBadges[$page->translationStatus($locale)])
                                    <span role="listitem" class="admin-products-badge {{ $tone }}" data-translation-status="{{ $locale }}:{{ $page->translationStatus($locale) }}" title="{{ $languageName }}: {{ $statusText }}">
                                        <i class="ph-duotone {{ $icon }}" aria-hidden="true"></i>
                                        <span lang="{{ $locale }}" dir="{{ $direction }}">{{ $languageName }}</span><span class="visually-hidden">: {{ $statusText }}</span>
                                    </span>
                                @endforeach
                            </span>
                            @if ($page->is_system)
                                <span class="admin-content-pages-system" data-content-kind="system"><i class="ph-duotone ph-lock-simple" aria-hidden="true"></i>{{ $cpText('Content_Pages_System', 'System page') }}</span>
                            @else
                                <span class="admin-content-pages-system" data-content-kind="custom"><i class="ph-duotone ph-file-text" aria-hidden="true"></i>{{ $cpText('Content_Pages_Custom', 'Custom page') }}</span>
                            @endif
                            <span class="admin-content-pages-date">{!! str_replace(':date', '<bdi dir="ltr">'.e($page->updated_at?->format('Y-m-d')).'</bdi>', e($cpText('Content_Pages_Updated', 'Last updated: :date'))) !!}</span>
                        </div>
                        <div class="admin-content-pages-actions">
                            <a href="{{ route('dashboard.content-pages.edit', $page) }}" class="btn btn-light-secondary" tabindex="-1" aria-hidden="true">{{ $canEdit ? $cpText('Content_Pages_Edit', 'Edit') : $cpText('Content_Pages_View', 'View') }}</a>
                            <a href="{{ route('dashboard.content-pages.preview', [$page, $previewLocale]) }}" class="btn btn-light-secondary" target="_blank" rel="noopener"
                                aria-label="{{ $previewLabel }} – {{ $title }} ({{ $newTab }})" data-content-preview-link="{{ $previewLocale }}">
                                <i class="ph-duotone ph-eye" aria-hidden="true"></i><span>{{ $previewLabel }}</span>
                            </a>
                            @if ($page->status === 'published')
                                {{-- PAGES-05D: the public address in the dashboard's language when the page is complete in it, else in the source language. --}}
                                <button type="button" class="btn btn-light-secondary" data-copy-url="{{ \App\Models\StorefrontContentPage::publicUrl($page->slug, $page->hasPublicTranslation($previewLocale) ? $previewLocale : $sourceLocale) }}" aria-label="{{ $copyLabel }} – {{ $title }}" hidden>
                                    <i class="ph-duotone ph-link" aria-hidden="true"></i><span>{{ $copyLabel }}</span>
                                </button>
                            @endif
                            @if ($canDelete && $page->isDeletable())
                                <button type="button" class="btn btn-light-danger" data-delete-url="{{ route('dashboard.content-pages.destroy', $page) }}" data-delete-title="{{ $title }}"
                                    data-delete-address="/pages/{{ $page->slug }}" aria-label="{{ $deleteLabel }} – {{ $title }}" aria-haspopup="dialog" hidden>
                                    <i class="ph-duotone ph-trash" aria-hidden="true"></i><span>{{ $deleteLabel }}</span>
                                </button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        @if ($canDelete)
            {{-- PAGES-04C: one dialog for every row; the script fills in the page and the form's address. --}}
            <dialog class="admin-media-dialog admin-content-dialog" aria-labelledby="content-delete-title" aria-describedby="content-delete-text" data-delete-dialog>
                <form method="POST" action="" class="admin-content-dialog-body" data-delete-form>
                    @csrf
                    @method('DELETE')
                    <h2 class="admin-content-dialog-title" id="content-delete-title">{{ $cpText('Content_Pages_Delete_Title', 'Delete this page?') }}</h2>
                    <p class="mb-0" id="content-delete-text" data-delete-text data-template="{{ $cpText('Content_Pages_Delete_Confirm_All', '“:title” will be deleted with all its translations. Links to :address will stop working. This cannot be undone.') }}"></p>
                    <div class="admin-content-dialog-actions">
                        <button type="submit" class="btn btn-danger" data-delete-confirm>{{ $deleteLabel }}</button>
                        <button type="button" class="btn btn-light-secondary" data-dialog-cancel>{{ $cpText('Content_Editor_Cancel', 'Cancel') }}</button>
                    </div>
                </form>
            </dialog>
        @endif
    </div>

    @push('scripts')
        @vite('resources/js/admin-content-pages.js')
    @endpush
</x-dashboard-layout>
