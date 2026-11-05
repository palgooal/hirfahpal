{{--
    VENDOR media picker dialog (VPM-01B), shared by the product editor (resources.blade.php) and My Store
    (MYSTORE-UI-01C), driven by initPicker() in resources/js/vendor-product-editor.js. Expects $galleryText (the
    Product_Gallery_* texts, read without creating translation rows) and $currentLanguage from the including view;
    $uploadHint is the text under the upload button; $multiple (default true) lets the upload take several files.
--}}
{{--
    VPM-01B: the image picker, a native modal dialog (focus kept inside, Escape closes, focus returns
    to the button that opened it) in the admin product gallery chooser's look (gallery-dialog.blade.php),
    under the vendor library's classes (vendor-media-*). Outside the product form, so its search is
    never submitted with the product. It lists the vendor's own images one page of 24 at a time from
    vendor.dashboard.products.media.library (page, per_page, search) and uploads to the same library.
--}}
<dialog class="vendor-media-dialog vendor-product-picker" aria-labelledby="vendor-product-picker-title" aria-describedby="vendor-product-picker-intro" data-product-media-library>
    <div class="vendor-media-dialog-header">
        <h2 class="vendor-media-dialog-title" id="vendor-product-picker-title" data-product-media-title>{{ $galleryText('Panel_Title', 'Choose an image from media') }}</h2>
        <button type="button" class="vendor-media-icon-button" aria-label="{{ $galleryText('Close', 'Close') }}" data-product-media-close>
            <i class="ph-duotone ph-x" aria-hidden="true"></i>
        </button>
    </div>
    <div class="vendor-media vendor-product-picker-body">
        <p class="vendor-media-intro vendor-product-picker-intro" id="vendor-product-picker-intro">{{ $galleryText('Picker_Intro', 'Only images from your own media library are shown.') }}</p>

        <form class="vendor-media-filters vendor-product-picker-filters" role="search" data-product-media-search-form novalidate>
            <label class="form-label sr-only" for="vendor-product-picker-search">{{ $galleryText('Search_Label', 'Search images') }}</label>
            <div class="vendor-media-search">
                <i class="ph-duotone ph-magnifying-glass" aria-hidden="true"></i>
                {{-- VPM-01F-FIX-01 (F-2): the page direction, not dir="auto" (an empty field was LTR in Arabic). --}}
                <input type="search" class="form-control" id="vendor-product-picker-search" maxlength="100" autocomplete="off" placeholder="{{ $galleryText('Search_Placeholder', 'Name or alt text') }}" data-product-media-search>
            </div>
            <p class="vendor-media-count" tabindex="-1" aria-live="polite" data-product-media-summary></p>
        </form>

        <div class="vendor-product-picker-upload">
            <button class="btn btn-primary" type="button" data-product-media-upload aria-describedby="vendor-product-upload-hint">
                <i class="ph-duotone ph-upload-simple" aria-hidden="true"></i>
                <span>{{ $galleryText('Upload', 'Upload new image') }}</span>
            </button>
            <p class="vendor-product-hint" id="vendor-product-upload-hint">{{ $uploadHint }}</p>
            <input class="vendor-product-media-file" type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-product-media-file{{ ($multiple ?? true) ? ' multiple' : '' }} tabindex="-1" aria-hidden="true">
        </div>
        <p class="vendor-product-picker-status" role="status" aria-live="polite" data-product-media-status></p>
        <p class="vendor-product-error" role="alert" data-product-media-alert hidden></p>

        <ul class="vendor-media-grid" aria-label="{{ $galleryText('Picker_Grid', 'Media library images') }}" aria-busy="false" data-product-media-items hidden></ul>
        <div class="vendor-media-state" role="status" data-product-media-loading hidden>
            <span class="vendor-media-spinner" aria-hidden="true"></span>
            <span>{{ $galleryText('Loading', 'Loading images…') }}</span>
        </div>
        <div class="vendor-media-state" data-product-media-empty hidden>
            <i class="ph-duotone ph-images" aria-hidden="true"></i>
            <span>{{ $galleryText('Library_Empty', 'There are no images in your media library yet. Upload one from this window.') }}</span>
        </div>
        <div class="vendor-media-state" data-product-media-no-results hidden>
            <i class="ph-duotone ph-images" aria-hidden="true"></i>
            <span>{{ $galleryText('No_Results', 'No images match your search.') }}</span>
        </div>
        <div class="vendor-media-state vendor-media-state-error" role="alert" data-product-media-error hidden>
            <i class="ph-duotone ph-warning" aria-hidden="true"></i>
            <span data-product-media-error-text></span>
            <button type="button" class="btn btn-sm btn-light-secondary" data-product-media-retry>
                <i class="ph-duotone ph-arrow-clockwise" aria-hidden="true"></i>
                <span>{{ $galleryText('Retry', 'Try again') }}</span>
            </button>
        </div>
        {{-- VPM-01F-FIX-01 (F-1): carets along the reading direction, chosen here (Able Pro's unlayered
             --tw-scale-x reset overrides the rtl:-scale-x-100 flip). --}}
        <nav class="vendor-media-more" aria-label="{{ $galleryText('Pagination', 'Media library pages') }}" data-product-media-pager hidden>
            <button type="button" class="btn btn-light-secondary" data-product-media-previous disabled>
                <i class="ph-duotone {{ $currentLanguage?->is_rtl ? 'ph-caret-right' : 'ph-caret-left' }}" aria-hidden="true"></i>
                <span>{{ $galleryText('Previous', 'Previous') }}</span>
            </button>
            <span class="vendor-media-page-status" data-product-media-page-status></span>
            <button type="button" class="btn btn-light-secondary" data-product-media-next disabled>
                <span>{{ $galleryText('Next', 'Next') }}</span>
                <i class="ph-duotone {{ $currentLanguage?->is_rtl ? 'ph-caret-left' : 'ph-caret-right' }}" aria-hidden="true"></i>
            </button>
        </nav>
    </div>
</dialog>
