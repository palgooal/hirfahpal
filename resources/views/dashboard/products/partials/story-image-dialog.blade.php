{{--
    PRODUCT-STORY-MEDIA (7B-4F-2): the story image chooser, a native modal dialog (focus kept inside, Escape closes,
    focus returns to the button that opened it). Like the gallery chooser (gallery-dialog.blade.php, whose classes it
    reuses), it lists the product vendor's own images from dashboard.products.vendor-media.create / .edit (read only:
    no upload, no deletion) with search and Load more; its ids and state are its own. Outside the product form, so
    its search field is never submitted. Driven by resources/js/admin-product-story-image.js.
--}}
<dialog class="admin-media-dialog admin-product-gallery-dialog" id="product-story-dialog" aria-labelledby="product-story-dialog-title" aria-describedby="product-story-dialog-intro">
    <div class="admin-media-dialog-header">
        <h2 class="admin-media-dialog-title" id="product-story-dialog-title">{{ $prodText('Products_Gallery_Dialog_Title', 'Choose an image') }}</h2>
        <button type="button" class="admin-media-icon-button" id="product-story-dialog-close" aria-label="{{ $prodText('Products_Gallery_Close', 'Close') }}">
            <i class="ph-duotone ph-x" aria-hidden="true"></i>
        </button>
    </div>

    <div class="admin-product-gallery-dialog-body">
        <p class="admin-media-hint" id="product-story-dialog-intro">{{ $prodText('Products_Gallery_Dialog_Intro', 'Only this vendor\'s images are shown. Vendors upload their images from their own dashboard.') }}</p>

        <div class="admin-media-filters admin-product-gallery-filters" role="search">
            <label class="form-label visually-hidden" for="product-story-search">{{ $prodText('Products_Gallery_Search_Label', 'Search images') }}</label>
            <div class="admin-media-search">
                <i class="ph-duotone ph-magnifying-glass" aria-hidden="true"></i>
                <input type="search" class="form-control" id="product-story-search" maxlength="100" autocomplete="off" placeholder="{{ $prodText('Products_Gallery_Search_Placeholder', 'Search by name, title or caption') }}">
            </div>
        </div>

        <ul class="admin-media-grid admin-product-gallery-choices" id="product-story-choices" aria-label="{{ $prodText('Products_Gallery_Choices_Label', 'Vendor images') }}" aria-busy="false"></ul>

        <div class="admin-media-state" id="product-story-loading" role="status" hidden>
            <span class="admin-media-spinner" aria-hidden="true"></span>
            <span>{{ $prodText('Products_Gallery_Loading', 'Loading images…') }}</span>
        </div>
        <div class="admin-media-state" id="product-story-choices-empty" hidden>
            <i class="ph-duotone ph-images" aria-hidden="true"></i>
            {{-- The empty-library explanation is the gallery chooser's only (one per page); this one shows the message. --}}
            <span id="product-story-choices-empty-text"></span>
        </div>
        <div class="admin-media-state admin-media-state-error" id="product-story-error" role="alert" hidden>
            <i class="ph-duotone ph-warning" aria-hidden="true"></i>
            <span id="product-story-error-text"></span>
            <button type="button" class="btn btn-sm btn-light-secondary" id="product-story-retry">
                <i class="ph-duotone ph-arrow-clockwise" aria-hidden="true"></i>
                <span>{{ $prodText('Products_Gallery_Retry', 'Try again') }}</span>
            </button>
        </div>

        <div class="admin-media-more">
            <button type="button" class="btn btn-light-secondary" id="product-story-more" hidden>{{ $prodText('Products_Gallery_Load_More', 'Load more') }}</button>
        </div>
    </div>

    <div class="admin-media-dialog-footer">
        <button type="button" class="btn btn-light-secondary" id="product-story-dialog-cancel">{{ $prodText('Products_Gallery_Close', 'Close') }}</button>
    </div>
</dialog>
