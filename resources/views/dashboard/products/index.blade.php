{{--
    AD-UI-PROD-M3: the products list in the admin shell (AD-UI-01), on the pattern of the categories list (CAT-01F).
    A table on wide screens (5.2) that becomes one card per product on narrow ones (5.13), the filter bar (5.3), the
    shared pagination (5.4). Filters come from ProductManagementController::index, which ignores invalid values.
    Create and edit follow products.create / products.edit; the server enforces them. No deletion (OPEN-PROD-17),
    no suspension, archiving, review request count or thumbnail here (M4-M6, deferred).
    Texts through t() without auto-creating keys (AdminProductTranslationSeeder).
--}}
@php
    $prodAdmin = auth('admin')->user();
    $prodCan = fn (string $ability): bool => (bool) ($prodAdmin?->isSuperAdmin() || $prodAdmin?->hasAbility($ability));
    $can = ['create' => $prodCan('products.create'), 'edit' => $prodCan('products.edit')];

    $prodText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };

    $statusLabel = fn (string $status): string => isset($statusKeys[$status]) ? $prodText(...$statusKeys[$status]) : $status;
    $vendorLabel = fn ($vendor): string => (string) ($vendor?->profile?->store_name ?? $vendor?->name ?? '');
    $categoryLabel = fn ($category): string => $category === null ? '' : ($category->parent ? $category->parent->name.' › '.$category->name : $category->name);
    $amount = fn ($value): string => number_format((float) $value, 2);
@endphp

<x-dashboard-layout>
    <div class="col-span-12 admin-media admin-products" id="admin-products">

        {{-- Content header (5.1): the page title is in the admin header. --}}
        <div class="admin-media-head">
            <p class="admin-media-intro">{{ $prodText('Products_Intro', 'Oversee the products vendors offer in the store: find them, check their stock and status.') }}</p>
            @if ($can['create'])
                <a href="{{ route('dashboard.products.create') }}" class="btn btn-primary">
                    <i class="ph-duotone ph-plus" aria-hidden="true"></i>
                    <span>{{ $prodText('Products_Add', 'Add product') }}</span>
                </a>
            @endif
        </div>

        {{-- Result messages from the controller (success, danger), inline until the admin toast exists. --}}
        @foreach (['success' => 'success', 'danger' => 'error'] as $flash => $tone)
            @if (session($flash))
                <div class="admin-media-alert is-{{ $tone }}" role="{{ $tone === 'success' ? 'status' : 'alert' }}">
                    <i class="ph-duotone {{ $tone === 'success' ? 'ph-check-circle' : 'ph-info' }}" aria-hidden="true"></i>
                    <div class="admin-media-alert-content"><p>{{ session($flash) }}</p></div>
                </div>
            @endif
        @endforeach

        <section class="card admin-media-panel" aria-labelledby="products-list-title">
            <h2 class="visually-hidden" id="products-list-title">{{ $prodText('Products_List', 'Products list') }}</h2>

            {{-- Filter bar (5.3): only what the controller supports; Reset clears it. --}}
            <form method="GET" action="{{ route('dashboard.products.index') }}" class="admin-cats-filters admin-products-filters" role="search" aria-label="{{ $prodText('Products_Filters', 'Filter products') }}">
                <div class="admin-cats-field admin-cats-field-search">
                    <label class="form-label" for="products-search">{{ $prodText('Products_Search_Label', 'Search products') }}</label>
                    <div class="admin-media-search">
                        <i class="ph-duotone ph-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" class="form-control" id="products-search" name="search" value="{{ $filters['search'] }}" maxlength="100" autocomplete="off" placeholder="{{ $prodText('Products_Search_Placeholder', 'Name or SKU') }}">
                    </div>
                </div>
                <div class="admin-cats-field">
                    <label class="form-label" for="products-vendor">{{ $prodText('Products_Filter_Vendor', 'Vendor') }}</label>
                    <select class="form-select" id="products-vendor" name="vendor_id">
                        <option value="">{{ $prodText('Products_Filter_Vendor_All', 'All vendors') }}</option>
                        @foreach ($vendors as $vendor)
                            <option value="{{ $vendor->id }}" @selected($filters['vendor_id'] === $vendor->id)>{{ $vendorLabel($vendor) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-cats-field">
                    <label class="form-label" for="products-category">{{ $prodText('Products_Filter_Category', 'Category') }}</label>
                    <select class="form-select" id="products-category" name="category_id">
                        <option value="">{{ $prodText('Products_Filter_Category_All', 'All categories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($filters['category_id'] === $category->id)>{{ $categoryLabel($category) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-cats-field">
                    <label class="form-label" for="products-status">{{ $prodText('Products_Filter_Status', 'Product status') }}</label>
                    <select class="form-select" id="products-status" name="status">
                        <option value="">{{ $prodText('Products_Filter_Status_All', 'All statuses') }}</option>
                        @foreach (array_keys($statusKeys) as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $statusLabel($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-cats-field">
                    <label class="form-label" for="products-suspension">{{ $prodText('Products_Filter_Suspension', 'Suspension') }}</label>
                    <select class="form-select" id="products-suspension" name="suspension">
                        <option value="">{{ $prodText('Products_Filter_Suspension_All', 'Suspended or not') }}</option>
                        <option value="suspended" @selected($filters['suspension'] === 'suspended')>{{ $prodText('Products_Suspended', 'Suspended') }}</option>
                        <option value="not_suspended" @selected($filters['suspension'] === 'not_suspended')>{{ $prodText('Products_Not_Suspended', 'Not suspended') }}</option>
                    </select>
                </div>
                <div class="admin-cats-field">
                    <label class="form-label" for="products-archive">{{ $prodText('Products_Filter_Archive', 'Archive') }}</label>
                    <select class="form-select" id="products-archive" name="archive">
                        <option value="">{{ $prodText('Products_Filter_Archive_Current', 'Not archived') }}</option>
                        <option value="archived" @selected($filters['archive'] === 'archived')>{{ $prodText('Products_Filter_Archive_Archived', 'Archived') }}</option>
                        <option value="all" @selected($filters['archive'] === 'all')>{{ $prodText('Products_Filter_Archive_All', 'All, archived included') }}</option>
                    </select>
                </div>
                <div class="admin-cats-field">
                    <label class="form-label" for="products-stock">{{ $prodText('Products_Filter_Stock', 'Stock') }}</label>
                    <select class="form-select" id="products-stock" name="stock">
                        <option value="">{{ $prodText('Products_Filter_Stock_All', 'Any stock level') }}</option>
                        <option value="low" @selected($filters['stock'] === 'low')>{{ $prodText('Products_Filter_Stock_Low', 'Low stock') }}</option>
                    </select>
                </div>
                <div class="admin-cats-filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph-duotone ph-funnel" aria-hidden="true"></i>
                        <span>{{ $prodText('Products_Apply', 'Apply') }}</span>
                    </button>
                    @if ($filtered)
                        <a href="{{ route('dashboard.products.index') }}" class="btn btn-light-secondary">{{ $prodText('Products_Reset', 'Reset') }}</a>
                    @endif
                </div>
            </form>

            <div class="card-body">
                @if ($products->isEmpty())
                    <div class="admin-media-state" role="status">
                        <i class="ph-duotone ph-package" aria-hidden="true"></i>
                        @if ($products->total() > 0)
                            <span>{{ $prodText('Products_Page_Out_Of_Range', 'There are no products on this page.') }}</span>
                            <a href="{{ $products->url($products->lastPage()) }}" class="btn btn-light-secondary">{{ $prodText('Products_Go_Last_Page', 'Go to the last page') }}</a>
                        @elseif ($filtered)
                            <span>{{ $prodText('Products_Empty_Filtered', 'No products match these filters.') }}</span>
                        @else
                            <span>{{ $prodText('Products_Empty', 'No products yet.') }}</span>
                            <span class="admin-media-hint">{{ $prodText('Products_Empty_Hint', 'Products appear here once vendors add them.') }}</span>
                        @endif
                    </div>
                @else
                    <div class="admin-products-table-wrap">
                        <table class="admin-products-table">
                            <caption class="visually-hidden">{{ $prodText('Products_List', 'Products list') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ $prodText('Products_Column_Product', 'Product') }}</th>
                                    <th scope="col">{{ $prodText('Products_Column_Vendor', 'Vendor') }}</th>
                                    <th scope="col">{{ $prodText('Products_Column_Category', 'Category') }}</th>
                                    <th scope="col" class="is-number">{{ $prodText('Products_Column_Price', 'Price') }}</th>
                                    <th scope="col">{{ $prodText('Products_Column_Stock', 'Stock') }}</th>
                                    <th scope="col">{{ $prodText('Products_Column_Status', 'Status') }}</th>
                                    @if ($can['edit'])
                                        <th scope="col" class="is-actions"><span class="visually-hidden">{{ $prodText('Products_Column_Actions', 'Actions') }}</span></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($products as $product)
                                    @php
                                        $reserved = (int) $product->reserved_quantity;
                                        $available = max(0, (int) $product->stock_quantity - $reserved);
                                        // OPEN-PROD-16, the same rule as Product::scopeLowAvailableStock().
                                        $isLow = $available <= (int) $product->low_stock_threshold;
                                    @endphp
                                    <tr data-product-id="{{ $product->id }}">
                                        <td class="admin-products-cell-product">
                                            <p class="admin-products-name"><a href="{{ route('dashboard.products.show', $product) }}" class="admin-products-name-link"><bdi>{{ $product->name }}</bdi></a></p>
                                            <p class="admin-products-code" dir="ltr">{{ $product->sku ?? $product->slug }}</p>
                                        </td>
                                        <td>
                                            <span class="admin-products-label">{{ $prodText('Products_Column_Vendor', 'Vendor') }}</span>
                                            <bdi>{{ $vendorLabel($product->vendor) }}</bdi>
                                        </td>
                                        <td>
                                            <span class="admin-products-label">{{ $prodText('Products_Column_Category', 'Category') }}</span>
                                            @if ($product->category)
                                                <bdi>{{ $categoryLabel($product->category) }}</bdi>
                                            @else
                                                <span class="admin-products-muted">{{ $prodText('Products_No_Category', 'No category') }}</span>
                                            @endif
                                        </td>
                                        <td class="is-number">
                                            <span class="admin-products-label">{{ $prodText('Products_Column_Price', 'Price') }}</span>
                                            <span dir="ltr">{{ $amount($product->price) }}</span>
                                        </td>
                                        <td class="admin-products-cell-stock">
                                            <span class="admin-products-label">{{ $prodText('Products_Column_Stock', 'Stock') }}</span>
                                            <dl class="admin-products-stock">
                                                <div>
                                                    <dt>{{ $prodText('Products_Stock_On_Hand', 'On hand') }}</dt>
                                                    <dd>{{ (int) $product->stock_quantity }}</dd>
                                                </div>
                                                <div>
                                                    <dt>{{ $prodText('Products_Stock_Reserved', 'Reserved') }}</dt>
                                                    <dd>{{ $reserved }}</dd>
                                                </div>
                                                <div>
                                                    <dt>{{ $prodText('Products_Stock_Available', 'Available') }}</dt>
                                                    <dd>{{ $available }}</dd>
                                                </div>
                                            </dl>
                                            @if ($isLow)
                                                <span class="admin-products-badge is-low">{{ $prodText('Products_Filter_Stock_Low', 'Low stock') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="admin-products-label">{{ $prodText('Products_Column_Status', 'Status') }}</span>
                                            <span class="admin-products-badges">
                                                <span class="admin-products-badge is-{{ $product->status }}">{{ $statusLabel($product->status) }}</span>
                                                @if ($product->isSuspended())
                                                    <span class="admin-products-badge is-suspended">{{ $prodText('Products_Suspended', 'Suspended') }}</span>
                                                @endif
                                                @if ($product->isArchived())
                                                    <span class="admin-products-badge is-archived">{{ $prodText('Products_Archived', 'Archived') }}</span>
                                                @endif
                                            </span>
                                        </td>
                                        @if ($can['edit'])
                                            <td class="is-actions">
                                                <a href="{{ route('dashboard.products.edit', $product) }}" class="btn btn-sm btn-light-secondary" aria-label="{{ str_replace(':name', $product->name, $prodText('Products_Edit_Named', 'Edit :name')) }}">
                                                    <i class="ph-duotone ph-pencil-simple" aria-hidden="true"></i>
                                                    <span>{{ $prodText('Products_Edit', 'Edit') }}</span>
                                                </a>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{ $products->links('dashboard.partials.pagination') }}
                @endif
            </div>
        </section>
    </div>
</x-dashboard-layout>
