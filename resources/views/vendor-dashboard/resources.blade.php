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
@endphp

<x-vendor-dashboard-layout :title="$page['title']">
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('vendor.dashboard') }}">{{ t('vendor.Nav_Dashboard', 'Dashboard') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ $page['title'] }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12" data-vendor-resource-list data-resource="{{ $page['key'] }}" data-endpoint="{{ $page['endpoint'] }}">
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
            </div>
        </section>
    </div>

    @if ($resource === 'products')
        <div class="col-span-12 admin-media admin-cat-form admin-product-form vendor-product-form" data-product-editor data-endpoint="{{ $page['endpoint'] }}" data-media-library-url="{{ route('vendor.dashboard.products.media.library') }}" data-media-store-url="{{ route('vendor.dashboard.products.media.store') }}" hidden>
            <section class="card admin-media-panel admin-cat-form-card">
                <div class="card-body">
                    <div class="admin-media-head admin-product-form-head">
                        <h2 class="admin-product-title" data-product-editor-title>{{ t('vendor.Product_New', 'Add product') }}</h2>
                        <button type="button" class="btn btn-light-secondary" data-product-editor-close>{{ t('vendor.Cancel', 'Cancel') }}</button>
                    </div>
                    <form data-product-form novalidate>
                        @csrf
                        <fieldset class="admin-cat-form-section">
                            <legend class="admin-cat-form-legend">Product details</legend>
                            <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="vendor-product-name">{{ t('vendor.Product_Name', 'Name') }} *</label><input id="vendor-product-name" class="form-control" name="name" required maxlength="255"></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-category">{{ t('vendor.Product_Category', 'Category') }}</label><select id="vendor-product-category" class="form-select" name="category_id"><option value="">{{ t('vendor.Product_No_Category', 'No category') }}</option></select></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-sku">SKU</label><input id="vendor-product-sku" class="form-control" name="sku" maxlength="255"></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-stock">{{ t('vendor.Product_Stock', 'Stock') }} *</label><input id="vendor-product-stock" class="form-control" name="stock_quantity" type="number" min="0" step="1" required></div>
                            <div class="col-12"><label class="form-label" for="vendor-product-short-description">{{ t('vendor.Product_Short_Description', 'Short description') }}</label><textarea id="vendor-product-short-description" class="form-control" name="short_description" rows="2" maxlength="1000"></textarea></div>
                            <div class="col-12"><label class="form-label" for="vendor-product-description">{{ t('vendor.Product_Description', 'Description') }}</label><textarea id="vendor-product-description" class="form-control" name="description" rows="5"></textarea></div>
                            </div>
                        </fieldset>
                        <fieldset class="admin-cat-form-section">
                            <legend class="admin-cat-form-legend">Price, stock and shipping</legend>
                            <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="vendor-product-price">{{ t('vendor.Product_Price', 'Regular price') }} *</label><input id="vendor-product-price" class="form-control" name="price" type="number" min="0.01" step="0.01" required></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-compare-price">{{ t('vendor.Product_Compare_Price', 'Sale price (optional)') }}</label><input id="vendor-product-compare-price" class="form-control" name="compare_at_price" type="number" min="0" step="0.01"><p class="form-text mb-0">Must be lower than the regular price.</p></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-low-stock">{{ t('vendor.Product_Low_Stock', 'Low stock threshold') }}</label><input id="vendor-product-low-stock" class="form-control" name="low_stock_threshold" type="number" min="0" step="1" value="1"></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-weight">{{ t('vendor.Product_Weight', 'Weight') }}</label><input id="vendor-product-weight" class="form-control" name="weight" type="number" min="0" step="0.01"></div>
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
                            ];
                        @endphp
                        <fieldset class="admin-cat-form-section" data-product-gallery aria-busy="false">
                            <legend class="admin-cat-form-legend">Product images</legend>
                            <p class="form-text mt-0" data-product-gallery-count></p>
                            <div class="mb-4"><div class="d-flex justify-content-between align-items-center gap-2"><div><label class="form-label mb-0">Main product image</label><p class="form-text mb-0">Shown first on the store.</p></div><button class="btn btn-light-primary" type="button" data-product-primary-open>Choose from media</button></div><div class="d-flex flex-wrap gap-2 mt-2" data-product-primary-image></div></div>
                            <div><div class="d-flex justify-content-between align-items-center gap-2"><div><label class="form-label mb-0">Product gallery</label><p class="form-text mb-0">Choose additional images from your media library.</p></div><button class="btn btn-light-primary" type="button" data-product-gallery-open data-limited="true">Add gallery image</button></div><div class="d-flex flex-wrap gap-2 mt-2" data-product-gallery-images></div></div>
                            <p class="form-text mb-0 mt-2" role="status" aria-live="polite" data-product-gallery-status></p>
                            <p class="text-danger mb-0 mt-2" role="alert" data-product-gallery-error hidden></p>
                            <script type="application/json" data-product-gallery-messages>@json($galleryMessages)</script>
                            <div class="border rounded p-3 mt-3" data-product-media-library hidden><div class="d-flex justify-content-between align-items-center mb-2"><strong data-product-media-title>Choose an image from media</strong><div class="d-flex gap-2"><button class="btn btn-sm btn-primary" type="button" data-product-media-upload>Upload new image</button><button class="btn btn-sm btn-light-secondary" type="button" data-product-media-close>Close</button></div></div><input class="visually-hidden" type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-product-media-file multiple><div class="d-flex flex-wrap gap-2" data-product-media-items></div></div>
                        </fieldset>
                        <p class="text-danger mt-3 mb-0" role="alert" data-product-error hidden></p>
                        <div class="admin-cat-form-actions"><button class="btn btn-primary" type="submit" data-product-save>{{ t('vendor.Save', 'Save') }}</button><button class="btn btn-light-secondary" type="button" data-product-editor-close>{{ t('vendor.Cancel', 'Cancel') }}</button></div>
                    </form>
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
