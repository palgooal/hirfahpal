{{--
    PRODUCT-MEDIA-01 P5-C: the image chooser of the product gallery, a native modal dialog (focus kept inside, Escape
    closes, focus returns to the button that opened it). It lists the product vendor's own images from
    dashboard.products.vendor-media.create / .edit (read only: no upload, no deletion) with search and Load more.
    Outside the product form, so its search field is never submitted. Driven by resources/js/admin-product-gallery.js.
--}}
<dialog class="admin-media-dialog admin-product-gallery-dialog" id="product-gallery-dialog" aria-labelledby="product-gallery-dialog-title" aria-describedby="product-gallery-dialog-intro">
    <div class="admin-media-dialog-header">
        <h2 class="admin-media-dialog-title" id="product-gallery-dialog-title">{{ $prodText('Products_Gallery_Dialog_Title', 'Choose an image') }}</h2>
        <button type="button" class="admin-media-icon-button" id="product-gallery-dialog-close" aria-label="{{ $prodText('Products_Gallery_Close', 'Close') }}">
            <i class="ph-duotone ph-x" aria-hidden="true"></i>
        </button>
    </div>

    <div class="admin-product-gallery-dialog-body">
        <p class="admin-media-hint" id="product-gallery-dialog-intro">{{ $prodText('Products_Gallery_Dialog_Intro', 'Only this vendor\'s images are shown. Vendors upload their images from their own dashboard.') }}</p>

        <div class="admin-media-filters admin-product-gallery-filters" role="search">
            <label class="form-label visually-hidden" for="product-gallery-search">{{ $prodText('Products_Gallery_Search_Label', 'Search images') }}</label>
            <div class="admin-media-search">
                <i class="ph-duotone ph-magnifying-glass" aria-hidden="true"></i>
                <input type="search" class="form-control" id="product-gallery-search" maxlength="100" autocomplete="off" placeholder="{{ $prodText('Products_Gallery_Search_Placeholder', 'Search by name, title or caption') }}">
            </div>
        </div>

        <ul class="admin-media-grid admin-product-gallery-choices" id="product-gallery-choices" aria-label="{{ $prodText('Products_Gallery_Choices_Label', 'Vendor images') }}" aria-busy="false"></ul>

        <div class="admin-media-state" id="product-gallery-loading" role="status" hidden>
            <span class="admin-media-spinner" aria-hidden="true"></span>
            <span>{{ $prodText('Products_Gallery_Loading', 'Loading images…') }}</span>
        </div>
        <div class="admin-media-state" id="product-gallery-choices-empty" hidden>
            <i class="ph-duotone ph-images" aria-hidden="true"></i>
            <span id="product-gallery-choices-empty-text"></span>
        </div>
        <div class="admin-media-state admin-media-state-error" id="product-gallery-error" role="alert" hidden>
            <i class="ph-duotone ph-warning" aria-hidden="true"></i>
            <span id="product-gallery-error-text"></span>
            <button type="button" class="btn btn-sm btn-light-secondary" id="product-gallery-retry">
                <i class="ph-duotone ph-arrow-clockwise" aria-hidden="true"></i>
                <span>{{ $prodText('Products_Gallery_Retry', 'Try again') }}</span>
            </button>
        </div>

        <div class="admin-media-more">
            <button type="button" class="btn btn-light-secondary" id="product-gallery-more" hidden>{{ $prodText('Products_Gallery_Load_More', 'Load more') }}</button>
        </div>
    </div>

    <div class="admin-media-dialog-footer">
        <button type="button" class="btn btn-light-secondary" id="product-gallery-dialog-cancel">{{ $prodText('Products_Gallery_Close', 'Close') }}</button>
    </div>
</dialog>
