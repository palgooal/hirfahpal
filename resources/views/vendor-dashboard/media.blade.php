{{--
    Media Library (VENDOR-MEDIA-04A page, VENDOR-MEDIA-04B gallery). Server-rendered labels, states and an empty
    grid only. resources/js/vendor-media-library.js reads the vendor's own images one page at a time from the
    existing listing endpoint (vendor.dashboard.products.media.library: page, per_page=24, search) and shows them
    read-only, with a preview dialog. No upload, edit or delete here; the endpoint is unchanged.

    VENDOR-MEDIA-04B-UI: the markup follows the admin media library (resources/views/vendor/media-library/media.blade.php):
    intro, a panel with the filter bar on top, the card grid with inline states, and the details dialog with the
    preview column. Styles are the admin values restated under vendor-media-* in resources/css/vendor-dashboard.css.
--}}
@php
    $i18n = [
        'count' => t('vendor.Media_Library_Count', 'Images: :count'),
        'pageStatus' => t('vendor.Media_Library_Page_Status', 'Page :page of :last'),
        'open' => t('vendor.Media_Library_Open', 'Preview :name'),
        'untitled' => t('vendor.Media_Library_Untitled', 'Untitled image'),
        'loadError' => t('vendor.Product_Gallery_Library_Error', 'Your media library could not be loaded.'),
        'invalid' => t('vendor.Media_Library_Invalid', 'The search could not be run. Change it and try again.'),
        'session' => t('vendor.Product_Gallery_Session', 'Your session has ended. Sign in again to continue.'),
        'forbidden' => t('vendor.Product_Gallery_Forbidden', 'You do not have permission to do this.'),
        'network' => t('vendor.Product_Gallery_Network', 'The connection failed. Check the network and try again.'),
    ];

    // VPM-01F-FIX-01 (F-1): the pager carets point along the reading direction, chosen on the server from the page
    // language (as the layout's dir). Not a CSS flip: Able Pro's unlayered "--tw-scale-x: 1" reset on every element
    // overrides the layered rtl:-scale-x-100 utility, so that flip never applied.
    $pagerBack = $currentLanguage?->is_rtl ? 'ph-caret-right' : 'ph-caret-left';
    $pagerForward = $currentLanguage?->is_rtl ? 'ph-caret-left' : 'ph-caret-right';
@endphp

<x-vendor-dashboard-layout :title="t('vendor.Nav_Media_Library', 'Media Library')">
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('vendor.dashboard') }}">{{ t('vendor.Nav_Dashboard', 'Dashboard') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('vendor.Nav_Media_Library', 'Media Library') }}</li>
    </x-slot:breadcrumbs>

    <div
        class="col-span-12 vendor-media"
        data-vendor-media-library
        data-vendor-media-page
        data-state="loading"
        data-list-url="{{ route('vendor.dashboard.products.media.library') }}"
    >
        <div class="vendor-media-head">
            <p class="vendor-media-intro">{{ t('vendor.Media_Library_Description', 'Keep your store images in one place, ready to add to your products.') }}</p>
        </div>

        <div class="card vendor-media-panel">
            {{-- Filter bar: search and the count, as in the admin library. VPM-01F-FIX-01 (F-2): the search field takes the
                 page direction (no dir="auto", which made an empty field LTR in Arabic), so its placeholder, the icon and
                 the space kept for it are on the same side; either script can still be typed. --}}
            <form class="vendor-media-filters" role="search" data-media-search-form novalidate>
                <label class="form-label sr-only" for="vendor-media-search">{{ t('vendor.Media_Library_Search_Label', 'Search images') }}</label>
                <div class="vendor-media-search">
                    <i class="ph-duotone ph-magnifying-glass" aria-hidden="true"></i>
                    <input
                        type="search"
                        class="form-control"
                        id="vendor-media-search"
                        name="search"
                        maxlength="100"
                        autocomplete="off"
                        placeholder="{{ t('vendor.Media_Library_Search_Placeholder', 'Name or alt text') }}"
                        data-media-search
                    >
                </div>
                <button type="button" class="btn btn-light-secondary" data-media-clear hidden>{{ t('vendor.Media_Library_Search_Clear', 'Clear search') }}</button>
                <p class="vendor-media-count" tabindex="-1" aria-live="polite" data-media-summary></p>
            </form>

            <div class="card-body">
                <ul class="vendor-media-grid" aria-label="{{ t('vendor.Media_Library_Images', 'Your images') }}" aria-busy="true" data-media-grid hidden></ul>

                {{-- Inline states. --}}
                <div class="vendor-media-state" role="status" data-media-loading>
                    <span class="vendor-media-spinner" aria-hidden="true"></span>
                    <span>{{ t('vendor.Product_Gallery_Loading', 'Loading images…') }}</span>
                </div>
                <div class="vendor-media-state" data-media-empty hidden>
                    <i class="ph-duotone ph-images" aria-hidden="true"></i>
                    <span>{{ t('vendor.Media_Library_Empty', 'There are no images in your media library yet. Images you upload from the product editor appear here.') }}</span>
                </div>
                <div class="vendor-media-state" data-media-no-results hidden>
                    <i class="ph-duotone ph-images" aria-hidden="true"></i>
                    <span>{{ t('vendor.Media_Library_No_Results', 'No images match your search.') }}</span>
                </div>
                <div class="vendor-media-state vendor-media-state-error" role="alert" data-media-error hidden>
                    <i class="ph-duotone ph-warning" aria-hidden="true"></i>
                    <span data-media-error-text></span>
                    <button type="button" class="btn btn-sm btn-light-secondary" data-media-retry>
                        <i class="ph-duotone ph-arrow-clockwise" aria-hidden="true"></i>
                        <span>{{ t('vendor.Dashboard_Retry', 'Retry') }}</span>
                    </button>
                </div>

                {{-- Where the admin library has "Load more": previous / next pages, approved for the vendor. --}}
                <nav class="vendor-media-more" aria-label="{{ t('vendor.Media_Library_Pagination', 'Media library pages') }}" data-media-pager hidden>
                    <button type="button" class="btn btn-light-secondary" data-media-previous disabled>
                        <i class="ph-duotone {{ $pagerBack }}" aria-hidden="true"></i>
                        <span>{{ t('vendor.Media_Library_Previous', 'Previous') }}</span>
                    </button>
                    <span class="vendor-media-page-status" data-media-page-status></span>
                    <button type="button" class="btn btn-light-secondary" data-media-next disabled>
                        <span>{{ t('vendor.Media_Library_Next', 'Next') }}</span>
                        <i class="ph-duotone {{ $pagerForward }}" aria-hidden="true"></i>
                    </button>
                </nav>

                <noscript>
                    <div class="vendor-media-state">{{ t('vendor.Product_Gallery_Library_Error', 'Your media library could not be loaded.') }}</div>
                </noscript>
            </div>
        </div>

        {{-- Details: a native modal dialog (focus kept inside, Escape closes, focus returns to the opener). Read-only. --}}
        <dialog class="vendor-media-dialog" aria-labelledby="vendor-media-dialog-title" data-media-dialog>
            <div class="vendor-media-dialog-header">
                <h2 class="vendor-media-dialog-title" id="vendor-media-dialog-title">{{ t('vendor.Media_Library_Preview', 'Image preview') }}</h2>
                <button type="button" class="vendor-media-icon-button" aria-label="{{ t('vendor.Media_Library_Close', 'Close') }}" data-media-dialog-close>
                    <i class="ph-duotone ph-x" aria-hidden="true"></i>
                </button>
            </div>

            <div class="vendor-media-dialog-preview">
                <div class="vendor-media-dialog-image">
                    <img alt="" data-media-dialog-image hidden>
                </div>
                <dl class="vendor-media-facts">
                    <div><dt>{{ t('vendor.Media_Library_Field_Name', 'Name') }}</dt><dd><bdi data-media-dialog-caption></bdi></dd></div>
                </dl>
                <a class="btn btn-sm btn-light-secondary vendor-media-original" href="#" target="_blank" rel="noopener noreferrer" data-media-dialog-original hidden>
                    <i class="ph-duotone ph-arrow-square-out" aria-hidden="true"></i>
                    <span>{{ t('vendor.Media_Library_Open_Original', 'Open the original image') }}</span>
                </a>
            </div>
        </dialog>

        {{-- Interface strings only (no user or media data). --}}
        <script type="application/json" data-media-i18n>@json($i18n)</script>
    </div>

    @push('scripts')
        @vite('resources/js/vendor-media-library.js')
    @endpush
</x-vendor-dashboard-layout>
