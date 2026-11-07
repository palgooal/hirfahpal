{{--
    VPM-UX-01A: the vendor products page, isolated from the shared vendor-dashboard.resources view. The list keeps the
    hooks resources/js/vendor-resource-list.js reads (data-vendor-resource-list, data-resource-*), so loading, paging,
    the moderation badges and the editor's refresh events work as before; the editor is the unchanged
    partials.product-editor, bound by resources/js/vendor-product-editor.js (which adds "Add product" before Refresh).
    One page title (the layout's h1); Refresh is an icon; Retry appears only in the error state.
--}}
@php
    $endpoint = route('vendor.dashboard.products.index');

    // VENDOR-UI-CLEANUP-01A/01B: the products list's column headers, status labels and actions, translated.
    $listTexts = [
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
    ];
    // VENDOR-PRODUCT-PUBLISH-UI-01E: the moderation badges shown next to the publication status.
    $moderationTexts = [
        'archived' => t('vendor.Product_Badge_Archived', 'Archived'),
        'suspended' => t('vendor.Product_Badge_Suspended', 'Suspended by administration'),
    ];
    // VENDOR-PRODUCT-LIST-PAGINATION-01B: the pager carets follow the reading direction.
    $pagerBack = $currentLanguage?->is_rtl ? 'ph-caret-right' : 'ph-caret-left';
    $pagerForward = $currentLanguage?->is_rtl ? 'ph-caret-left' : 'ph-caret-right';
    $refreshLabel = t('vendor.Product_List_Refresh', 'Refresh product list');
@endphp

<x-vendor-dashboard-layout :title="t('vendor.Nav_Products', 'Products')">
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('vendor.dashboard') }}">{{ t('vendor.Nav_Dashboard', 'Dashboard') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('vendor.Nav_Products', 'Products') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12 vendor-products" data-vendor-resource-list data-resource="products" data-endpoint="{{ $endpoint }}" data-list-texts="{{ json_encode($listTexts, JSON_UNESCAPED_UNICODE) }}" data-moderation-texts="{{ json_encode($moderationTexts, JSON_UNESCAPED_UNICODE) }}">
        <div class="card vendor-products-card">
            <div class="card-body">
                {{-- "Add product" is inserted here by the editor script, before the Refresh icon (the list's
                     data-resource-retry, which reloads the page shown). --}}
                <div class="vendor-products-toolbar">
                    <button type="button" class="vendor-icon-button vendor-products-refresh" data-resource-retry aria-label="{{ $refreshLabel }}" title="{{ $refreshLabel }}">
                        <i class="ph-duotone ph-arrow-clockwise" aria-hidden="true"></i>
                    </button>
                </div>

                {{-- Loading: placeholder rows, announced by their text (the list script shows and hides this block). --}}
                <div class="vendor-products-loading" role="status" data-resource-loading>
                    <span class="sr-only">{{ t('vendor.Product_List_Loading', 'Loading…') }}</span>
                    @for ($row = 0; $row < 4; $row++)
                        <span class="vendor-skeleton vendor-skeleton-row" aria-hidden="true"></span>
                    @endfor
                </div>

                {{-- Error: shown while the list script shows its message (it rewrites that paragraph's text, so it stays
                     text only); Retry reloads through the same Refresh control (vendor-products-page.js). --}}
                <div class="vendor-products-error" data-products-error>
                    <span class="vendor-kpi-icon" aria-hidden="true"><i class="ph-duotone ph-cloud-slash"></i></span>
                    <p class="vendor-home-error-text" role="alert" data-resource-error hidden>{{ t('vendor.Dashboard_Load_Error', 'Data could not be loaded.') }}</p>
                    <button type="button" class="vendor-retry-button" data-products-error-retry>
                        <i class="ph-duotone ph-arrow-clockwise" aria-hidden="true"></i>
                        <span>{{ t('vendor.Dashboard_Retry', 'Retry') }}</span>
                    </button>
                </div>

                <div class="table-responsive" data-resource-table hidden>
                    <table class="table table-hover align-middle mb-0">
                        <thead data-resource-head></thead>
                        <tbody data-resource-body></tbody>
                    </table>
                </div>
                <p class="vendor-empty" data-resource-empty hidden>{{ t('vendor.Product_List_Empty', 'No records found.') }}</p>
                {{-- VENDOR-PRODUCT-LIST-PAGINATION-01B: previous / next pages (the media library's pager and texts),
                     shown by the list script when there is more than one page. --}}
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
            </div>
        </div>
    </div>

    @include('vendor-dashboard.partials.product-editor', ['endpoint' => $endpoint])

    @push('scripts')
        @vite('resources/js/vendor-resource-list.js')
        @vite('resources/js/vendor-product-editor.js')
        @vite('resources/js/vendor-products-page.js')
    @endpush
</x-vendor-dashboard-layout>
