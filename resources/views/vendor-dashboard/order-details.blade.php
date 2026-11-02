{{--
    ORD-02A: the vendor order details page (FR-VEN-011–015, FR-VEN-020; UC-VEN-005/006). $details is the controller's
    whitelist (VendorDashboard\OrderController::details()), embedded below; a JSON request to this page's URL returns the
    same data, which the script uses to refresh after an action. The page renders the structure and labels; the script
    (resources/js/vendor-order-details.js) fills in the values as plain text from that data only. Order actions reuse the
    orders list's dialogs, feedback, texts and request logic (vendor-order-actions.js). Seeded translations only.
--}}
@php
    $title = str_replace(':number', $details['number'], t('vendor.Order_Details_Title', 'Order :number'));
    // ORD-02A (step 4): the way back to the orders list as the vendor left it (search, status, needs-response, page),
    // only those parameters and only when valid; always the orders list's own route (no address taken from the request).
    $backUrl = \App\Support\VendorDashboard\VendorOrderListContext::listUrl(request()->query());
    $backIcon = ($currentLanguage ?? null)?->is_rtl ? 'ph-arrow-right' : 'ph-arrow-left';
    $statuses = \App\Support\VendorDashboard\VendorOrderTexts::statuses();
    // The action outcomes of the orders list, except that a refused action refreshes this order, not the list.
    $outcomes = \App\Support\VendorDashboard\VendorOrderTexts::outcomes();
    $outcomes['errors']['conflict'] = t('vendor.Order_Details_Conflict', 'This order could not be updated because it has changed or is no longer available. The order has been refreshed.');
    $outcomes['errors']['conflictAccept'] = t('vendor.Order_Details_Conflict_Accept', 'This order could not be accepted. It may have changed, or the stock reserved for it may no longer be available. The order has been refreshed.');
    $columns = [
        'product_name' => t('vendor.Product_List_Product', 'Product'),
        'product_sku' => t('vendor.Product_Sku', 'SKU'),
        'quantity' => t('vendor.Order_Details_Quantity', 'Quantity'),
        'unit_price' => t('vendor.Order_Details_Unit_Price', 'Unit price'),
        'line_total' => t('vendor.Order_Details_Line_Total', 'Line total'),
    ];
    $texts = [
        'statuses' => $statuses,
        'actions' => \App\Support\VendorDashboard\VendorOrderTexts::actions(),
        'columns' => $columns,
        // Timeline labels, by the contract's timestamp key (created_at first); there is no preparing timestamp.
        'timeline' => [
            'created_at' => t('vendor.Order_Details_Placed', 'Order placed'),
            'accepted_at' => $statuses['accepted'],
            'rejected_at' => $statuses['rejected'],
            'ready_at' => $statuses['ready_for_delivery'],
            'delivered_at' => $statuses['delivered'],
            'customer_receipt_confirmed_at' => t('vendor.Order_Details_Receipt_Confirmed', 'Receipt confirmed by the customer'),
            'completed_at' => $statuses['completed'],
        ],
    ];
@endphp

<x-vendor-dashboard-layout :title="$title">
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('vendor.dashboard') }}">{{ t('vendor.Nav_Dashboard', 'Dashboard') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ $backUrl }}">{{ t('vendor.Nav_Orders', 'Orders') }}</a></li>
        <li class="breadcrumb-item" aria-current="page"><bdi dir="ltr">{{ $details['number'] }}</bdi></li>
    </x-slot:breadcrumbs>

    <div class="col-span-12 vendor-order-details"
        data-vendor-order-details
        data-endpoint="{{ route('vendor.dashboard.orders.details', $details['id']) }}"
        data-action-endpoint="{{ route('vendor.dashboard.orders.index') }}"
        data-timezone="{{ config('app.timezone') }}"
        data-details-texts="{{ json_encode($texts, JSON_UNESCAPED_UNICODE) }}"
        data-order-action-texts="{{ json_encode($outcomes, JSON_UNESCAPED_UNICODE) }}">
        <script type="application/json" data-order-details>@json($details)</script>

        {{-- ORD-02A (step 4): back to the orders list with the filters and page the vendor came from. --}}
        <a class="vendor-order-details-back" href="{{ $backUrl }}" data-details-back>
            <i class="ph-duotone {{ $backIcon }}" aria-hidden="true"></i>
            <span>{{ t('vendor.Order_Details_Back', 'Back to orders') }}</span>
        </a>

        @include('vendor-dashboard.partials.order-action-feedback')

        {{-- States: refreshing after an action (the content stays), a failed load (Retry) and an order no longer available. --}}
        <p class="vendor-empty vendor-order-details-refreshing" role="status" data-details-refreshing hidden>{{ t('vendor.Order_Details_Refreshing', 'Updating the order…') }}</p>
        <section class="card vendor-order-details-state" role="alert" data-details-error hidden>
            <div class="card-body">
                <p class="vendor-home-error-text" data-details-error-text></p>
                <button type="button" class="btn btn-light-primary" data-details-retry>{{ t('vendor.Dashboard_Retry', 'Retry') }}</button>
            </div>
        </section>
        <section class="card vendor-order-details-state" data-details-gone hidden>
            <div class="card-body">
                <h2 class="vendor-section-title" tabindex="-1" data-details-gone-title>{{ t('vendor.Order_Details_Gone', 'This order is no longer available.') }}</h2>
                <a class="btn btn-light-secondary" href="{{ $backUrl }}">{{ t('vendor.Order_Details_Back', 'Back to orders') }}</a>
            </div>
        </section>

        <div class="vendor-order-details-layout" data-details-content>
            <div class="vendor-order-details-main">
                <section class="card" aria-labelledby="vendor-order-summary-title">
                    <div class="card-body">
                        <h2 class="vendor-section-title" id="vendor-order-summary-title" tabindex="-1" data-details-summary-title>{{ t('vendor.Order_Details_Summary', 'Order summary') }}</h2>
                        <dl class="vendor-order-facts">
                            <div>
                                <dt>{{ t('vendor.Recent_Orders_Status', 'Status') }}</dt>
                                <dd data-details-status></dd>
                            </div>
                            <div>
                                <dt>{{ t('vendor.Order_Details_Placed', 'Order placed') }}</dt>
                                <dd data-details-created></dd>
                            </div>
                            <div data-details-reason-row hidden>
                                <dt>{{ t('vendor.Order_Reject_Reason', 'Reason for rejection') }}</dt>
                                <dd data-details-reason></dd>
                            </div>
                        </dl>
                        <h3 class="vendor-order-details-subtitle" id="vendor-order-actions-title">{{ t('vendor.Product_List_Actions', 'Actions') }}</h3>
                        <div class="vendor-order-actions vendor-order-details-actions" role="group" aria-labelledby="vendor-order-actions-title" data-details-actions></div>
                        <p class="vendor-order-details-muted" data-details-no-actions hidden>{{ t('vendor.Order_Details_No_Actions', 'No actions are available for this order in its current status.') }}</p>
                    </div>
                </section>

                <section class="card" aria-labelledby="vendor-order-items-title">
                    <div class="card-body">
                        <h2 class="vendor-section-title" id="vendor-order-items-title">{{ t('vendor.Order_Details_Items', 'Items') }}</h2>
                        <div class="table-responsive" data-details-items-table>
                            <table class="vendor-recent-table vendor-order-items-table">
                                <thead>
                                    <tr>
                                        @foreach ($columns as $label)
                                            <th scope="col">{{ $label }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody data-details-items></tbody>
                            </table>
                        </div>
                        <p class="vendor-empty" data-details-no-items hidden>{{ t('vendor.Order_Details_No_Items', 'This order has no items.') }}</p>
                    </div>
                </section>
            </div>

            <div class="vendor-order-details-side">
                <section class="card" aria-labelledby="vendor-order-amounts-title">
                    <div class="card-body">
                        <h2 class="vendor-section-title" id="vendor-order-amounts-title">{{ t('vendor.Order_Details_Amounts', 'Amounts') }}</h2>
                        <dl class="vendor-order-facts vendor-order-amounts">
                            <div>
                                <dt>{{ t('vendor.Order_Details_Subtotal', 'Subtotal') }}</dt>
                                <dd data-details-amount="subtotal"></dd>
                            </div>
                            <div>
                                <dt>{{ t('vendor.Order_Details_Delivery_Fee', 'Delivery fee') }}</dt>
                                <dd data-details-amount="delivery_fee"></dd>
                            </div>
                            <div class="is-total">
                                <dt>{{ t('vendor.Order_List_Total', 'Total') }}</dt>
                                <dd data-details-amount="total"></dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <section class="card" aria-labelledby="vendor-order-timeline-title">
                    <div class="card-body">
                        <h2 class="vendor-section-title" id="vendor-order-timeline-title">{{ t('vendor.Order_Details_Timeline', 'Order timeline') }}</h2>
                        <ol class="vendor-order-timeline" data-details-timeline></ol>
                    </div>
                </section>
            </div>
        </div>

        @include('vendor-dashboard.partials.order-action-dialogs')
    </div>

    @push('scripts')
        @vite('resources/js/vendor-order-details.js')
    @endpush
</x-vendor-dashboard-layout>
