@php
    // VPM-UX-01A: the products page has its own view (vendor-dashboard.products); these are the other lists.
    $pages = [
        'orders' => ['title' => t('vendor.Nav_Orders', 'Orders'), 'endpoint' => route('vendor.dashboard.orders.index'), 'key' => 'orders'],
        'reviews' => ['title' => t('vendor.Nav_Reviews', 'Reviews'), 'endpoint' => route('vendor.dashboard.reviews.index'), 'key' => 'reviews'],
        'returns' => ['title' => t('vendor.Nav_Returns', 'Returns'), 'endpoint' => route('vendor.dashboard.returns.index'), 'key' => 'returns'],
        'disputes' => ['title' => t('vendor.Nav_Disputes', 'Disputes'), 'endpoint' => route('vendor.dashboard.disputes.index'), 'key' => 'disputes'],
        'commissions' => ['title' => t('vendor.Nav_Finance', 'Finance'), 'endpoint' => route('vendor.dashboard.commissions.index'), 'key' => 'commissions'],
    ];
    abort_unless(isset($pages[$resource]), 404);
    $page = $pages[$resource];

    // The lists' translated texts (the orders list sets them below; the other lists use the script's defaults).
    $listTexts = null;
    // VENDOR-PRODUCT-LIST-PAGINATION-01B / ORD-01A: the orders list's pager, its carets pointing along the reading
    // direction (chosen here from the page language, as on the media library page); the other lists have none.
    $pagerLabel = match ($resource) {
        'orders' => t('vendor.Order_List_Pagination', 'Order pages'),
        default => null,
    };
    $pagerBack = $currentLanguage?->is_rtl ? 'ph-caret-right' : 'ph-caret-left';
    $pagerForward = $currentLanguage?->is_rtl ? 'ph-caret-left' : 'ph-caret-right';
    // ORD-01B: the orders list's status filter, one option per status the orders endpoint returns (its "statuses"),
    // with the labels of the dashboard home. Display only: this does not define or change the order lifecycle.
    // ORD-02A (step 2): the status, action and outcome texts are shared with the order details page (VendorOrderTexts).
    $orderStatuses = $resource === 'orders' ? \App\Support\VendorDashboard\VendorOrderTexts::statuses() : null;
    // ORD-01C: the orders list's texts: column headers (by column path; the dashboard home's order labels where they are
    // the same), status labels, and each action's label and accessible name (":number" is the order number). The
    // actions shown are unchanged (the endpoint decides them; see orderActionsFor in vendor-resource-list.js).
    if ($orderStatuses) {
        $listTexts = [
            'actions' => t('vendor.Product_List_Actions', 'Actions'),
            'columns' => [
                'number' => t('vendor.Recent_Orders_Number', 'Order number'),
                'status' => t('vendor.Recent_Orders_Status', 'Status'),
                'order.customer.name' => t('vendor.Order_List_Customer', 'Customer'),
                'created_at' => t('vendor.Recent_Orders_Date', 'Date'),
                'total' => t('vendor.Order_List_Total', 'Total'),
            ],
            'statuses' => $orderStatuses,
            'orderActions' => \App\Support\VendorDashboard\VendorOrderTexts::actions(),
            'actionError' => \App\Support\VendorDashboard\VendorOrderTexts::actionError(),
            // ORD-02A (step 4): each order's link to its details page (":number" is the order number).
            'detailsLink' => ['label' => t('vendor.Order_List_View_Details', 'View details'), 'named' => t('vendor.Order_List_View_Details_Named', 'View details of order :number')],
        ];
        // ORD-01D: the action outcomes (":number" is the order number, ":max" the reason limit). A server's own message
        // is never shown: each outcome has its text (vendor-order-actions.js).
        $orderActionTexts = \App\Support\VendorDashboard\VendorOrderTexts::outcomes();
    }
    // The products list's moderation badges moved with it (vendor-dashboard.products); no other list has them.
    $moderationTexts = null;
@endphp

<x-vendor-dashboard-layout :title="$page['title']">
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('vendor.dashboard') }}">{{ t('vendor.Nav_Dashboard', 'Dashboard') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ $page['title'] }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12" data-vendor-resource-list data-resource="{{ $page['key'] }}" data-endpoint="{{ $page['endpoint'] }}" @if ($listTexts) data-list-texts="{{ json_encode($listTexts, JSON_UNESCAPED_UNICODE) }}" @endif @if ($moderationTexts) data-moderation-texts="{{ json_encode($moderationTexts, JSON_UNESCAPED_UNICODE) }}" @endif @if ($orderStatuses) data-timezone="{{ config('app.timezone') }}" data-order-action-texts="{{ json_encode($orderActionTexts, JSON_UNESCAPED_UNICODE) }}" data-details-base="{{ route('vendor.dashboard.orders.page') }}" @endif>
        <section class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                    <h2 class="vendor-section-title mb-0">{{ $page['title'] }}</h2>
                    <button type="button" class="btn btn-light-primary" data-resource-retry>{{ t('vendor.Dashboard_Retry', 'Refresh') }}</button>
                </div>
                @if ($orderStatuses)
                    {{-- ORD-01B: search and filters of the orders list, sent as the endpoint's own search, status and
                         needs_response parameters (Apply, or Enter in the search field); page 1 on every change. The
                         search field takes the page direction, as in the media library (VPM-01F-FIX-01). --}}
                    <form class="vendor-order-filters" role="search" aria-label="{{ t('vendor.Order_Filters_Label', 'Search and filter orders') }}" data-order-filters novalidate>
                        <div class="vendor-order-filter is-search">
                            <label class="form-label" for="vendor-order-search">{{ t('vendor.Order_Filter_Search', 'Search') }}</label>
                            <div class="vendor-media-search">
                                <i class="ph-duotone ph-magnifying-glass" aria-hidden="true"></i>
                                <input type="search" class="form-control" id="vendor-order-search" name="search" autocomplete="off" placeholder="{{ t('vendor.Order_Filter_Search_Placeholder', 'Order number or customer name') }}" data-order-search>
                            </div>
                        </div>
                        <div class="vendor-order-filter">
                            <label class="form-label" for="vendor-order-status">{{ t('vendor.Order_Filter_Status', 'Status') }}</label>
                            <select class="form-select" id="vendor-order-status" name="status" data-order-status>
                                <option value="">{{ t('vendor.Order_Filter_All_Statuses', 'All statuses') }}</option>
                                @foreach ($orderStatuses as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="vendor-order-needs-response" name="needs_response" value="1" data-order-needs-response>
                            <label class="form-check-label" for="vendor-order-needs-response">{{ t('vendor.Order_Filter_Needs_Response', 'Needs your response') }}</label>
                        </div>
                        <div class="vendor-order-filter-actions">
                            <button type="submit" class="btn btn-primary">{{ t('vendor.Order_Filter_Apply', 'Apply') }}</button>
                            <button type="button" class="btn btn-light-secondary" data-order-filters-reset>{{ t('vendor.Order_Filter_Reset', 'Clear filters') }}</button>
                        </div>
                    </form>
                    {{-- ORD-01D: the outcome of an order action (shared with the order details page). --}}
                    @include('vendor-dashboard.partials.order-action-feedback')
                @endif
                <p class="vendor-empty" data-resource-loading>{{ t('vendor.Dashboard_Loading', 'Loading…') }}</p>
                <p class="vendor-home-error-text" role="alert" data-resource-error hidden>{{ t('vendor.Dashboard_Load_Error', 'Data could not be loaded.') }}</p>
                <div class="table-responsive" data-resource-table hidden>
                    {{-- ORD-01C: the orders list uses the dashboard home's orders table (cards with labelled rows on a phone). --}}
                    <table class="{{ $orderStatuses ? 'vendor-recent-table vendor-order-table' : 'table table-hover align-middle mb-0' }}">
                        <thead data-resource-head></thead>
                        <tbody data-resource-body></tbody>
                    </table>
                </div>
                <p class="vendor-empty" data-resource-empty hidden>{{ t('vendor.No_records_found', 'No records found.') }}</p>
                @if ($orderStatuses)
                    {{-- ORD-01B: shown instead of the empty text above when a search or filter is active. --}}
                    <p class="vendor-empty" role="status" data-resource-empty-filtered hidden>{{ t('vendor.Order_Filter_No_Results', 'No orders match your search or filters.') }}</p>
                @endif
                @if ($pagerLabel)
                    {{-- ORD-01A: previous / next pages of the orders list (the media library's pager and texts, as on the
                         products page); shown by the script when there is more than one page. --}}
                    <nav class="vendor-media-more" aria-label="{{ $pagerLabel }}" data-resource-pager data-page-status="{{ t('vendor.Media_Library_Page_Status', 'Page :page of :last') }}" hidden>
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

        @if ($orderStatuses)
            {{-- ORD-01D: the Accept and Reject dialogs (shared with the order details page). --}}
            @include('vendor-dashboard.partials.order-action-dialogs')
        @endif
    </div>

    @push('scripts')
        @vite('resources/js/vendor-resource-list.js')
    @endpush
</x-vendor-dashboard-layout>
