{{--
    Vendor dashboard home (VUI-02B). The page is server-rendered with labels and
    loading placeholders only; resources/js/vendor-dashboard-home.js fills in the
    approved fields of the existing Overview endpoint (vendor.dashboard.overview).
    Only these fields are used: stats.orders_pending, stats.orders_in_progress,
    stats.products_active, stats.products_total, stats.products_low_stock and,
    per recent order, number, created_at, status and the item count.
--}}
@php
    $vendor = auth('vendor')->user();
    $storeName = filled($vendor->profile?->store_name) ? $vendor->profile->store_name : $vendor->name;

    $kpis = [
        ['key' => 'orders_pending', 'label' => t('vendor.Kpi_Awaiting_Response', 'Awaiting your response'), 'icon' => 'ph-hourglass-medium'],
        ['key' => 'orders_in_progress', 'label' => t('vendor.Kpi_In_Progress', 'In progress'), 'icon' => 'ph-arrows-clockwise'],
        ['key' => 'products_active', 'label' => t('vendor.Kpi_Active_Products', 'Active products'), 'icon' => 'ph-package'],
        ['key' => 'products_low_stock', 'label' => t('vendor.Kpi_Low_Available_Stock', 'Low available stock'), 'icon' => 'ph-stack'],
    ];

    // Display labels for the vendor order status values the endpoint may return.
    // Display only: this does not define or change the order lifecycle.
    $statusLabels = [
        'pending' => t('vendor.Order_Status_Pending', 'Pending'),
        'accepted' => t('vendor.Order_Status_Accepted', 'Accepted'),
        'preparing' => t('vendor.Order_Status_Preparing', 'Preparing'),
        'ready_for_delivery' => t('vendor.Order_Status_Ready_For_Delivery', 'Ready for delivery'),
        'assigned' => t('vendor.Order_Status_Assigned', 'Assigned to a driver'),
        'out_for_delivery' => t('vendor.Order_Status_Out_For_Delivery', 'Out for delivery'),
        'delivered' => t('vendor.Order_Status_Delivered', 'Delivered'),
        'completed' => t('vendor.Order_Status_Completed', 'Completed'),
        'rejected' => t('vendor.Order_Status_Rejected', 'Rejected'),
        'cancelled' => t('vendor.Order_Status_Cancelled', 'Cancelled'),
    ];

    $columns = [
        'number' => t('vendor.Recent_Orders_Number', 'Order number'),
        'date' => t('vendor.Recent_Orders_Date', 'Date'),
        'status' => t('vendor.Recent_Orders_Status', 'Status'),
        'items' => t('vendor.Recent_Orders_Items', 'Items'),
    ];

    $i18n = [
        'ofTotal' => t('vendor.Kpi_Of_Total', 'of :total'),
        'noProducts' => t('vendor.Kpi_No_Products', 'No products added yet'),
        'statuses' => $statusLabels,
        'columns' => $columns,
    ];
@endphp

<x-vendor-dashboard-layout :title="t('vendor.Nav_Dashboard', 'Dashboard')">
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item" aria-current="page">{{ t('vendor.Nav_Dashboard', 'Dashboard') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <section class="card vendor-welcome-card" aria-labelledby="vendor-welcome-title">
            <div class="card-body">
                <p class="vendor-welcome-eyebrow">{{ t('vendor.Dashboard_Welcome', 'Welcome to your store') }}</p>
                {{-- The heading keeps the page direction (alignment); <bdi> lets the store name keep its own. --}}
                <h2 id="vendor-welcome-title" class="vendor-welcome-name"><bdi>{{ $storeName }}</bdi></h2>
            </div>
        </section>
    </div>

    <div
        class="col-span-12 vendor-home"
        data-vendor-dashboard-home
        data-state="loading"
        data-overview-url="{{ route('vendor.dashboard.overview') }}"
        data-timezone="{{ config('app.timezone') }}"
    >
        <div class="vendor-home-content" data-home-content>
            <section class="vendor-home-section" aria-labelledby="vendor-overview-title" aria-busy="true" data-home-busy>
                <h2 id="vendor-overview-title" class="vendor-section-title">{{ t('vendor.Dashboard_Overview', 'Store overview') }}</h2>

                <div class="vendor-kpi-grid">
                    @foreach ($kpis as $kpi)
                        <article class="card vendor-kpi" data-kpi-card="{{ $kpi['key'] }}">
                            <div class="card-body">
                                <span class="vendor-kpi-icon" aria-hidden="true"><i class="ph-duotone {{ $kpi['icon'] }}"></i></span>
                                <div class="vendor-kpi-body">
                                    <h3 class="vendor-kpi-label">{{ $kpi['label'] }}</h3>
                                    <p class="vendor-kpi-value">
                                        <span class="vendor-skeleton vendor-skeleton-value" data-kpi="{{ $kpi['key'] }}"></span>
                                    </p>
                                    @if ($kpi['key'] === 'products_active')
                                        <p class="vendor-kpi-meta" data-kpi-meta="products_total"><span class="vendor-skeleton vendor-skeleton-meta"></span></p>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="card vendor-recent" aria-labelledby="vendor-recent-title" aria-busy="true" data-home-busy>
                <div class="card-body">
                    <h2 id="vendor-recent-title" class="vendor-section-title">{{ t('vendor.Recent_Orders', 'Recent orders') }}</h2>

                    <div class="vendor-recent-loading" data-recent-loading aria-hidden="true">
                        @for ($i = 0; $i < 3; $i++)
                            <span class="vendor-skeleton vendor-skeleton-row"></span>
                        @endfor
                    </div>

                    <p class="vendor-empty" data-recent-empty hidden>{{ t('vendor.Recent_Orders_Empty', 'No orders yet') }}</p>

                    <table class="vendor-recent-table" data-recent-table hidden>
                        <thead>
                            <tr>
                                @foreach ($columns as $column)
                                    <th scope="col">{{ $column }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody data-recent-body></tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="card vendor-home-error" data-home-error hidden>
            <div class="card-body">
                <span class="vendor-kpi-icon" aria-hidden="true"><i class="ph-duotone ph-cloud-slash"></i></span>
                <p class="vendor-home-error-text" role="alert">{{ t('vendor.Dashboard_Load_Error', 'Dashboard data could not be loaded.') }}</p>
                <button type="button" class="vendor-retry-button" data-home-retry>
                    <i class="ph-duotone ph-arrow-clockwise" aria-hidden="true"></i>
                    <span>{{ t('vendor.Dashboard_Retry', 'Retry') }}</span>
                </button>
            </div>
        </div>

        <noscript>
            <p class="vendor-empty">{{ t('vendor.Dashboard_Load_Error', 'Dashboard data could not be loaded.') }}</p>
        </noscript>

        {{-- Interface strings only (no user or business data). --}}
        <script type="application/json" data-home-i18n>@json($i18n)</script>
    </div>

    @push('scripts')
        @vite('resources/js/vendor-dashboard-home.js')
    @endpush
</x-vendor-dashboard-layout>
