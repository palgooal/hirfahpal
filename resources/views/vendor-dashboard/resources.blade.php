@php
    $pages = [
        'products' => ['title' => t('vendor.Nav_Products', 'Products'), 'endpoint' => route('vendor.dashboard.products.index'), 'key' => 'products'],
        'orders' => ['title' => t('vendor.Nav_Orders', 'Orders'), 'endpoint' => route('vendor.dashboard.orders.index'), 'key' => 'orders'],
        'reviews' => ['title' => t('vendor.Nav_Reviews', 'Reviews'), 'endpoint' => route('vendor.dashboard.reviews.index'), 'key' => 'reviews'],
        'returns' => ['title' => t('vendor.Nav_Returns', 'Returns'), 'endpoint' => route('vendor.dashboard.returns.index'), 'key' => 'returns'],
        'disputes' => ['title' => t('vendor.Nav_Disputes', 'Disputes'), 'endpoint' => route('vendor.dashboard.disputes.index'), 'key' => 'disputes'],
        'commissions' => ['title' => t('vendor.Nav_Finance', 'Finance'), 'endpoint' => route('vendor.dashboard.commissions.index'), 'key' => 'commissions'],
    ];
    abort_unless(isset($pages[$resource]), 404);
    $page = $pages[$resource];

    // VENDOR-UI-CLEANUP-01A (F-3): the products list's action column and buttons, translated (the other lists are unchanged).
    // VENDOR-UI-CLEANUP-01B: and its column headers (by column path), its status labels (by the stored products.status
    // value: draft, active, inactive, out_of_stock) and the actions' accessible names.
    $listTexts = $resource === 'products' ? [
        'actions' => t('vendor.Product_List_Actions', 'Actions'),
        'edit' => t('vendor.Product_List_Edit', 'Edit'),
        'delete' => t('vendor.Product_List_Delete', 'Delete'),
        'editNamed' => t('vendor.Product_List_Edit_Named', 'Edit product: :name'),
        'deleteNamed' => t('vendor.Product_List_Delete_Named', 'Delete product: :name'),
        'columns' => [
            'name' => t('vendor.Product_List_Product', 'Product'),
            'sku' => t('vendor.Product_Sku', 'SKU'),
            'status' => t('vendor.Product_List_Status', 'Status'),
            'price' => t('vendor.Product_List_Price', 'Price'),
            'stock_quantity' => t('vendor.Product_Stock', 'Stock'),
        ],
        'statuses' => [
            'draft' => t('vendor.Product_Status_Draft', 'Draft'),
            'active' => t('vendor.Product_Status_Active', 'Active'),
            'inactive' => t('vendor.Product_Status_Inactive', 'Inactive'),
            'out_of_stock' => t('vendor.Product_Status_Out_Of_Stock', 'Out of stock'),
        ],
    ] : null;
    // VENDOR-PRODUCT-LIST-PAGINATION-01B: the products list's pager carets point along the reading direction, chosen
    // here from the page language as on the media library page (VPM-01F-FIX-01: a CSS rtl: flip never applies here).
    $pagerBack = $currentLanguage?->is_rtl ? 'ph-caret-right' : 'ph-caret-left';
    $pagerForward = $currentLanguage?->is_rtl ? 'ph-caret-left' : 'ph-caret-right';
    // VENDOR-PRODUCT-PUBLISH-UI-01E: the products list's moderation badges (shown next to the publication status).
    $moderationTexts = $resource === 'products' ? [
        'archived' => t('vendor.Product_Badge_Archived', 'Archived'),
        'suspended' => t('vendor.Product_Badge_Suspended', 'Suspended by administration'),
    ] : null;
@endphp

<x-vendor-dashboard-layout :title="$page['title']">
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('vendor.dashboard') }}">{{ t('vendor.Nav_Dashboard', 'Dashboard') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ $page['title'] }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12" data-vendor-resource-list data-resource="{{ $page['key'] }}" data-endpoint="{{ $page['endpoint'] }}" @if ($listTexts) data-list-texts="{{ json_encode($listTexts, JSON_UNESCAPED_UNICODE) }}" data-moderation-texts="{{ json_encode($moderationTexts, JSON_UNESCAPED_UNICODE) }}" @endif>
        <section class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                    <h2 class="vendor-section-title mb-0">{{ $page['title'] }}</h2>
                    <button type="button" class="btn btn-light-primary" data-resource-retry>{{ t('vendor.Dashboard_Retry', 'Refresh') }}</button>
                </div>
                <p class="vendor-empty" data-resource-loading>{{ t('vendor.Dashboard_Loading', 'Loading…') }}</p>
                <p class="vendor-home-error-text" role="alert" data-resource-error hidden>{{ t('vendor.Dashboard_Load_Error', 'Data could not be loaded.') }}</p>
                <div class="table-responsive" data-resource-table hidden>
                    <table class="table table-hover align-middle mb-0">
                        <thead data-resource-head></thead>
                        <tbody data-resource-body></tbody>
                    </table>
                </div>
                <p class="vendor-empty" data-resource-empty hidden>{{ t('vendor.No_records_found', 'No records found.') }}</p>
                @if ($resource === 'products')
                    {{-- VENDOR-PRODUCT-LIST-PAGINATION-01B: previous / next pages of the products list only (the media
                         library's pager and texts); shown by the script when there is more than one page. --}}
                    <nav class="vendor-media-more" aria-label="{{ t('vendor.Product_List_Pagination', 'Product pages') }}" data-resource-pager data-page-status="{{ t('vendor.Media_Library_Page_Status', 'Page :page of :last') }}" hidden>
                        <button type="button" class="btn btn-light-secondary" data-resource-previous disabled>
                            <i class="ph-duotone {{ $pagerBack }}" aria-hidden="true"></i>
                            <span>{{ t('vendor.Media_Library_Previous', 'Previous') }}</span>
                        </button>
                        <span class="vendor-media-page-status" aria-live="polite" data-resource-page-status></span>
                        <button type="button" class="btn btn-light-secondary" data-resource-next disabled>
                            <span>{{ t('vendor.Media_Library_Next', 'Next') }}</span>
                            <i class="ph-duotone {{ $pagerForward }}" aria-hidden="true"></i>
                        </button>
                    </nav>
                @endif
            </div>
        </section>
    </div>

    @if ($resource === 'products')
        <div class="col-span-12 admin-media admin-cat-form admin-product-form vendor-product-form" data-product-editor data-endpoint="{{ $page['endpoint'] }}" data-media-library-url="{{ route('vendor.dashboard.products.media.library') }}" data-media-store-url="{{ route('vendor.dashboard.products.media.store') }}" data-library-prefix="{{ \App\Support\Media\VendorMediaPath::prefix((int) auth('vendor')->id()) }}" hidden>
            <section class="card admin-media-panel admin-cat-form-card">
                <div class="card-body">
                    <div class="admin-media-head admin-product-form-head">
                        <h2 class="admin-product-title" data-product-editor-title>{{ t('vendor.Product_New', 'Add product') }}</h2>
                        <button type="button" class="btn btn-light-secondary" data-product-editor-close>{{ t('vendor.Cancel', 'Cancel') }}</button>
                    </div>
                    <form data-product-form novalidate>
                        @csrf
                        {{-- VENDOR-PRODUCT-PUBLISH-UI-01D (DETAIL-02, DETAIL-03): the administration's state, from the product's
                             moderation flags (shown by the script; archived wins). data-refused is the text announced when a
                             change is refused because the product was archived while it was open. --}}
                        <div class="vendor-product-notice is-archived" role="note" tabindex="-1" data-product-notice="archived" data-refused="{{ t('vendor.Product_Archived_Refused', 'This product was archived and can no longer be edited.') }}" hidden><i class="ph-duotone ph-archive" aria-hidden="true"></i><p>{{ t('vendor.Product_Notice_Archived', 'This product is archived by the administration and cannot be edited. Contact the administration to restore it.') }}</p></div>
                        <div class="vendor-product-notice is-suspended" role="note" data-product-notice="suspended" hidden><i class="ph-duotone ph-pause-circle" aria-hidden="true"></i><p>{{ t('vendor.Product_Notice_Suspended', 'This product is suspended by the administration. It is not shown in the store or available for sale until the suspension is lifted, whatever its publication status. You can still edit it.') }}</p></div>
                        <fieldset class="admin-cat-form-section">
                            <legend class="admin-cat-form-legend">{{ t('vendor.Product_Section_Details', 'Product details') }}</legend>
                            <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="vendor-product-name">{{ t('vendor.Product_Name', 'Name') }} *</label><input id="vendor-product-name" class="form-control" name="name" required maxlength="255"></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-category">{{ t('vendor.Product_Category', 'Category') }}</label><select id="vendor-product-category" class="form-select" name="category_id"><option value="">{{ t('vendor.Product_No_Category', 'No category') }}</option></select></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-sku">{{ t('vendor.Product_Sku', 'SKU') }}</label><input id="vendor-product-sku" class="form-control" name="sku" maxlength="255" dir="ltr"></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-stock">{{ t('vendor.Product_Stock', 'Stock') }} *</label><input id="vendor-product-stock" class="form-control" name="stock_quantity" type="number" min="0" step="1" required></div>
                            {{-- VENDOR-PRODUCT-PUBLISH-UI-01B (OPEN-PROD-19, 20; DETAIL-01): the vendor's three statuses. A product
                                 stored as out_of_stock gets a "keep" option (value "", added by the script from data-keep-label),
                                 so its status is left out of the update; out_of_stock itself is never an option. --}}
                            <div class="col-md-6"><label class="form-label" for="vendor-product-status">{{ t('vendor.Product_Status', 'Publication status') }}</label><select id="vendor-product-status" class="form-select" name="status" aria-describedby="vendor-product-status-hint" data-keep-label="{{ t('vendor.Product_Status_Keep_Out_Of_Stock', 'Out of stock (keep current status)') }}"><option value="draft" selected>{{ t('vendor.Product_Status_Draft', 'Draft') }}</option><option value="active">{{ t('vendor.Product_Status_Active', 'Active') }}</option><option value="inactive">{{ t('vendor.Product_Status_Inactive', 'Inactive') }}</option></select><p class="vendor-product-hint" id="vendor-product-status-hint">{{ t('vendor.Product_Status_Hint', 'Active products are shown in the store only when they meet its existing conditions. Draft and inactive products are not shown.') }}</p></div>
                            <div class="col-12"><label class="form-label" for="vendor-product-short-description">{{ t('vendor.Product_Short_Description', 'Short description') }}</label><textarea id="vendor-product-short-description" class="form-control" name="short_description" rows="2" maxlength="1000"></textarea></div>
                            <div class="col-12"><label class="form-label" for="vendor-product-description">{{ t('vendor.Product_Description', 'Description') }}</label><textarea id="vendor-product-description" class="form-control" name="description" rows="5"></textarea></div>
                            </div>
                        </fieldset>
                        <fieldset class="admin-cat-form-section">
                            <legend class="admin-cat-form-legend">{{ t('vendor.Product_Section_Pricing', 'Price, stock and shipping') }}</legend>
                            <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="vendor-product-price">{{ t('vendor.Product_Price', 'Regular price') }} *</label><input id="vendor-product-price" class="form-control" name="price" type="number" min="0.01" step="0.01" required></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-compare-price">{{ t('vendor.Product_Compare_Price', 'Sale price (optional)') }}</label><input id="vendor-product-compare-price" class="form-control" name="compare_at_price" type="number" min="0" step="0.01" aria-describedby="vendor-product-compare-price-hint"><p class="vendor-product-hint" id="vendor-product-compare-price-hint">{{ t('vendor.Product_Compare_Price_Hint', 'Must be lower than the regular price.') }}</p></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-low-stock">{{ t('vendor.Product_Low_Stock', 'Low stock threshold') }}</label><input id="vendor-product-low-stock" class="form-control" name="low_stock_threshold" type="number" min="0" step="1" value="1"></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-weight">{{ t('vendor.Product_Weight', 'Weight') }}</label><div class="input-group"><input id="vendor-product-weight" class="form-control" name="weight" type="number" min="0" step="0.01"><select class="form-select" name="weight_unit" aria-label="{{ t('vendor.Product_Weight_Unit', 'Weight unit') }}"><option value="">{{ t('vendor.Product_Weight_Unit_Select', 'Unit') }}</option><option value="kg">kg</option><option value="g">g</option><option value="t">ton</option></select></div></div>
                            <div class="col-12"><p class="form-label mb-2">{{ t('vendor.Product_Dimensions', 'Dimensions (cm)') }}</p><div class="row g-2"><div class="col-md-4"><label class="sr-only" for="vendor-product-length">{{ t('vendor.Product_Length', 'Length') }}</label><input id="vendor-product-length" class="form-control" name="dimensions[length]" type="number" min="0" step="0.01" placeholder="{{ t('vendor.Product_Length', 'Length') }}"></div><div class="col-md-4"><label class="sr-only" for="vendor-product-width">{{ t('vendor.Product_Width', 'Width') }}</label><input id="vendor-product-width" class="form-control" name="dimensions[width]" type="number" min="0" step="0.01" placeholder="{{ t('vendor.Product_Width', 'Width') }}"></div><div class="col-md-4"><label class="sr-only" for="vendor-product-height">{{ t('vendor.Product_Height', 'Height') }}</label><input id="vendor-product-height" class="form-control" name="dimensions[height]" type="number" min="0" step="0.01" placeholder="{{ t('vendor.Product_Height', 'Height') }}"></div></div></div>
                            </div>
                        </fieldset>
                        <fieldset class="admin-cat-form-section">
                            <legend class="admin-cat-form-legend">{{ t('vendor.Product_Section_Craft', 'Craft, care and delivery') }}</legend>
                            <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="vendor-product-material">{{ t('vendor.Product_Material', 'Main material') }}</label><input id="vendor-product-material" class="form-control" name="material" maxlength="255"></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-place">{{ t('vendor.Product_Place_Of_Manufacture', 'Place of manufacture') }}</label><input id="vendor-product-place" class="form-control" name="place_of_manufacture" maxlength="255"></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-preparation-days">{{ t('vendor.Product_Preparation_Days', 'Preparation time (days)') }}</label><input id="vendor-product-preparation-days" class="form-control" name="preparation_days" type="number" min="0" max="3650" step="1"></div>
                            <div class="col-12"><label class="form-label" for="vendor-product-care">{{ t('vendor.Product_Care_Instructions', 'Care and storage instructions') }}</label><textarea id="vendor-product-care" class="form-control" name="care_instructions" rows="3" maxlength="5000"></textarea></div>
                            <div class="col-12"><label class="form-label" for="vendor-product-shipping">{{ t('vendor.Product_Shipping_Details', 'Packaging and shipping details') }}</label><textarea id="vendor-product-shipping" class="form-control" name="shipping_details" rows="3" maxlength="5000"></textarea></div>
                            </div>
                        </fieldset>
                        @php
                            // G7-C2-5: the gallery's texts, read without creating translation rows
                            // (VendorDashboardTranslationSeeder holds them in Arabic and English).
                            $galleryText = function (string $key, string $default): string {
                                $autoCreate = config('palgoals-locale.auto_create');
                                config(['palgoals-locale.auto_create' => false]);

                                try {
                                    return t('vendor.Product_Gallery_'.$key, $default);
                                } finally {
                                    config(['palgoals-locale.auto_create' => $autoCreate]);
                                }
                            };
                            $galleryMessages = [
                                'change' => $galleryText('Change', 'Change'),
                                'setMain' => $galleryText('Set_Main', 'Set as main'),
                                'remove' => $galleryText('Remove', 'Remove'),
                                'confirmRemove' => $galleryText('Confirm_Remove', 'Remove this image from the product? The file stays in your media library.'),
                                'noMain' => $galleryText('No_Main', 'No main image selected.'),
                                'noGallery' => $galleryText('No_Gallery', 'No gallery images selected.'),
                                'count' => $galleryText('Count', ':count of :max images'),
                                'titleMain' => $galleryText('Title_Main', 'Choose the main product image'),
                                'titleReplace' => $galleryText('Title_Replace', 'Choose a replacement image'),
                                'titleGallery' => $galleryText('Title_Gallery', 'Choose a gallery image'),
                                'loading' => $galleryText('Loading', 'Loading images…'),
                                'libraryEmpty' => $galleryText('Library_Empty', 'There are no images in your media library yet. Upload one from this window.'),
                                'libraryError' => $galleryText('Library_Error', 'Your media library could not be loaded.'),
                                'saving' => $galleryText('Saving', 'Saving…'),
                                'uploading' => $galleryText('Uploading', 'Uploading…'),
                                'added' => $galleryText('Added', 'Image added.'),
                                'replaced' => $galleryText('Replaced', 'Image replaced.'),
                                'removed' => $galleryText('Removed', 'Image removed. The file stays in your media library.'),
                                'mainChanged' => $galleryText('Main_Changed', 'Main image changed.'),
                                'limit' => $galleryText('Limit', 'A product can have up to 6 images. Remove or replace an image to add another.'),
                                'library' => $galleryText('Not_In_Library', 'This image is not in your media library, or it was removed. Choose another image.'),
                                'gone' => $galleryText('Gone', 'This image or product is no longer available. Reload the page and try again.'),
                                'session' => $galleryText('Session', 'Your session has ended. Sign in again to continue.'),
                                'forbidden' => $galleryText('Forbidden', 'You do not have permission to do this.'),
                                'invalid' => $galleryText('Invalid', 'The request could not be processed. Reload the page and try again.'),
                                'network' => $galleryText('Network', 'The connection failed. Check the network and try again.'),
                                'failed' => $galleryText('Failed', 'Something went wrong. Try again.'),
                                'uploadInvalid' => $galleryText('Upload_Invalid', 'The file must be a JPEG, PNG, GIF or WebP image of up to 10 MB.'),
                                'imageAlt' => $galleryText('Image_Alt', 'Product image'),
                                'mediaAlt' => $galleryText('Media_Alt', 'Media library image'),
                                // VPM-01A: the admin gallery card's position line and main badge.
                                'position' => $galleryText('Position', 'Image :position'),
                                'mainBadge' => $galleryText('Main_Badge', 'Main image'),
                                // VPM-01B: the picker dialog.
                                'libraryCount' => $galleryText('Library_Count', 'Images: :count'),
                                'pageStatus' => $galleryText('Page_Status', 'Page :page of :last'),
                                'choose' => $galleryText('Choose', 'Choose :name'),
                                'attached' => $galleryText('Attached', 'Already in this product'),
                                'uploaded' => $galleryText('Uploaded', 'Images uploaded: :count. The first one was added to the product; the others are in your media library.'),
                                'uploadMax' => $galleryText('Upload_Max', 'You can upload up to 10 images at a time.'),
                                // VPM-01C: duplicates.
                                'duplicate' => $galleryText('Duplicate', 'This image is already in this product. Choose another image.'),
                                'current' => $galleryText('Current', 'The image being replaced'),
                                // VPM-01D: moving images (the first image is the main one).
                                'moveUp' => $galleryText('Move_Up', 'Move up'),
                                'moveDown' => $galleryText('Move_Down', 'Move down'),
                                'moved' => $galleryText('Moved', ':name is now image :position.'),
                                // VPM-01E: images from before the media library, missing files, and the six-image limit.
                                'legacy' => $galleryText('Legacy', 'Not in your media library'),
                                'missing' => $galleryText('Missing', 'Image file not found'),
                                'full' => $galleryText('Full', 'This product has the maximum of :max images. Remove or replace one to add another.'),
                                'overMax' => $galleryText('Over_Max', 'This product has :count images, more than the current maximum of :max. Nothing can be added. You can still remove, replace, reorder and set the main image; no image is removed automatically.'),
                            ];
                            // VPM-01A: the editor's own texts (title, list button, product load, save and delete).
                            $editorMessages = [
                                'add' => t('vendor.Product_New', 'Add product'),
                                'edit' => t('vendor.Product_Edit', 'Edit product'),
                                'noCategory' => t('vendor.Product_No_Category', 'No category'),
                                'confirmDelete' => t('vendor.Product_Confirm_Delete', 'Delete this product? This cannot be undone.'),
                                'deleteFailed' => t('vendor.Product_Delete_Failed', 'The product could not be deleted.'),
                                'loadFailed' => t('vendor.Product_Load_Failed', 'The product could not be loaded.'),
                                'saveFailed' => t('vendor.Product_Save_Failed', 'The product could not be saved.'),
                                'saveInvalid' => t('vendor.Product_Save_Invalid', 'The product could not be saved. Check these details:'),
                            ];
                        @endphp
                        {{--
                            VPM-01A: the image section in the admin product gallery's look (partials/gallery.blade.php:
                            bar with the count, cards with thumbnail, position, name, main badge and actions), under
                            vendor-product-gallery-* in resources/css/vendor-dashboard.css; the vendor shell has no
                            Bootstrap, so no Bootstrap layout utilities here. Behaviour unchanged: main image box,
                            gallery box, inline chooser with upload (VPM-01B replaces the chooser).
                        --}}
                        <fieldset class="admin-cat-form-section vendor-product-gallery" data-product-gallery aria-busy="false">
                            <legend class="admin-cat-form-legend">{{ $galleryText('Section', 'Product images') }}</legend>
                            <p class="vendor-product-hint" id="vendor-product-gallery-hint">{{ $galleryText('Hint', 'Choose up to 6 images from your media library. The main image is shown first in the store.') }}</p>
                            <div class="vendor-product-gallery-bar">
                                <p class="vendor-product-gallery-count" data-product-gallery-count></p>
                            </div>
                            {{-- VPM-01E: one note at or above the six-image limit (a gallery from before it keeps every image). --}}
                            <p class="vendor-product-gallery-note" id="vendor-product-gallery-limit" role="status" aria-live="polite" data-product-gallery-limit hidden></p>

                            <section class="vendor-product-gallery-group" aria-labelledby="vendor-product-main-label">
                                <div class="vendor-product-gallery-group-head">
                                    <div class="vendor-product-gallery-group-text">
                                        <h3 class="vendor-product-gallery-label" id="vendor-product-main-label">{{ $galleryText('Main_Label', 'Main product image') }}</h3>
                                        <p class="vendor-product-hint">{{ $galleryText('Main_Help', 'Shown first on the store.') }}</p>
                                    </div>
                                    <button class="btn btn-light-secondary vendor-product-gallery-add" type="button" data-product-primary-open>
                                        <i class="ph-duotone ph-image" aria-hidden="true"></i>
                                        <span>{{ $galleryText('Choose_Main', 'Choose from media') }}</span>
                                    </button>
                                </div>
                                <div class="vendor-product-gallery-box" data-product-primary-image></div>
                            </section>

                            <section class="vendor-product-gallery-group" aria-labelledby="vendor-product-gallery-label">
                                <div class="vendor-product-gallery-group-head">
                                    <div class="vendor-product-gallery-group-text">
                                        <h3 class="vendor-product-gallery-label" id="vendor-product-gallery-label">{{ $galleryText('Gallery_Label', 'Product gallery') }}</h3>
                                        <p class="vendor-product-hint">{{ $galleryText('Gallery_Help', 'Choose additional images from your media library.') }}</p>
                                    </div>
                                    <button class="btn btn-light-secondary vendor-product-gallery-add" type="button" data-product-gallery-open data-limited="true" aria-describedby="vendor-product-gallery-hint vendor-product-gallery-limit">
                                        <i class="ph-duotone ph-plus" aria-hidden="true"></i>
                                        <span>{{ $galleryText('Add', 'Add gallery image') }}</span>
                                    </button>
                                </div>
                                <div class="vendor-product-gallery-box" data-product-gallery-images></div>
                            </section>

                            <p class="vendor-product-gallery-status" role="status" aria-live="polite" data-product-gallery-status></p>
                            <p class="vendor-product-error" role="alert" data-product-gallery-error hidden></p>
                            <script type="application/json" data-product-gallery-messages>@json($galleryMessages)</script>
                        </fieldset>
                        <p class="vendor-product-error" role="alert" data-product-error hidden></p>
                        <script type="application/json" data-product-editor-messages>@json($editorMessages)</script>
                        <div class="admin-cat-form-actions"><button class="btn btn-primary" type="submit" data-product-save>{{ t('vendor.Save', 'Save') }}</button><button class="btn btn-light-secondary" type="button" data-product-editor-close>{{ t('vendor.Cancel', 'Cancel') }}</button></div>
                    </form>

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
                                <p class="vendor-product-hint" id="vendor-product-upload-hint">{{ $galleryText('Upload_Hint', 'JPEG, PNG, GIF or WebP, up to 10 MB each and up to 10 at a time. The first uploaded image is added to the product; the others stay in your media library.') }}</p>
                                <input class="vendor-product-media-file" type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-product-media-file multiple tabindex="-1" aria-hidden="true">
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
                </div>
            </section>
        </div>
    @endif

    @push('scripts')
        @vite('resources/js/vendor-resource-list.js')
        @if ($resource === 'products')
            @vite('resources/js/vendor-product-editor.js')
        @endif
    @endpush
</x-vendor-dashboard-layout>
