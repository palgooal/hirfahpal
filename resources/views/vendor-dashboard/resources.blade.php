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
        <div class="col-span-12" data-product-editor data-endpoint="{{ $page['endpoint'] }}" hidden>
            <section class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                        <h2 class="vendor-section-title mb-0" data-product-editor-title>{{ t('vendor.Product_New', 'Add product') }}</h2>
                        <button type="button" class="btn btn-light-secondary" data-product-editor-close>{{ t('vendor.Cancel', 'Cancel') }}</button>
                    </div>
                    <form data-product-form novalidate>
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="vendor-product-name">{{ t('vendor.Product_Name', 'Name') }} *</label><input id="vendor-product-name" class="form-control" name="name" required maxlength="255"></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-category">{{ t('vendor.Product_Category', 'Category') }}</label><select id="vendor-product-category" class="form-select" name="category_id"><option value="">{{ t('vendor.Product_No_Category', 'No category') }}</option></select></div>
                            <div class="col-md-4"><label class="form-label" for="vendor-product-price">{{ t('vendor.Product_Price', 'Price') }} *</label><input id="vendor-product-price" class="form-control" name="price" type="number" min="0" step="0.01" required></div>
                            <div class="col-md-4"><label class="form-label" for="vendor-product-stock">{{ t('vendor.Product_Stock', 'Stock') }} *</label><input id="vendor-product-stock" class="form-control" name="stock_quantity" type="number" min="0" step="1" required></div>
                            <div class="col-md-4"><label class="form-label" for="vendor-product-status">{{ t('vendor.Product_Status', 'Status') }} *</label><select id="vendor-product-status" class="form-select" name="status"><option value="draft">Draft</option><option value="active">Active</option><option value="inactive">Inactive</option><option value="out_of_stock">Out of stock</option></select></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-sku">SKU</label><input id="vendor-product-sku" class="form-control" name="sku" maxlength="255"></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-compare-price">{{ t('vendor.Product_Compare_Price', 'Compare-at price') }}</label><input id="vendor-product-compare-price" class="form-control" name="compare_at_price" type="number" min="0" step="0.01"></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-low-stock">{{ t('vendor.Product_Low_Stock', 'Low stock threshold') }}</label><input id="vendor-product-low-stock" class="form-control" name="low_stock_threshold" type="number" min="0" step="1" value="1"></div>
                            <div class="col-md-6"><label class="form-label" for="vendor-product-weight">{{ t('vendor.Product_Weight', 'Weight') }}</label><input id="vendor-product-weight" class="form-control" name="weight" type="number" min="0" step="0.01"></div>
                            <div class="col-12"><label class="form-label" for="vendor-product-short-description">{{ t('vendor.Product_Short_Description', 'Short description') }}</label><textarea id="vendor-product-short-description" class="form-control" name="short_description" rows="2" maxlength="1000"></textarea></div>
                            <div class="col-12"><label class="form-label" for="vendor-product-description">{{ t('vendor.Product_Description', 'Description') }}</label><textarea id="vendor-product-description" class="form-control" name="description" rows="5"></textarea></div>
                            <div class="col-12"><label class="form-label" for="vendor-product-images">{{ t('vendor.Product_Images', 'Product images') }}</label><input id="vendor-product-images" class="form-control" name="images" type="file" accept="image/jpeg,image/png,image/webp" multiple><p class="form-text mb-0">JPEG, PNG or WebP; up to 5 MB each.</p></div>
                        </div>
                        <p class="text-danger mt-3 mb-0" role="alert" data-product-error hidden></p>
                        <div class="mt-3 d-flex gap-2"><button class="btn btn-primary" type="submit" data-product-save>{{ t('vendor.Save', 'Save') }}</button><button class="btn btn-light-secondary" type="button" data-product-editor-close>{{ t('vendor.Cancel', 'Cancel') }}</button></div>
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
