{{--
    CUS-UI-06: the customer details page in the admin shell (AD-UI-01), on the pieces of the product details page
    (admin-product-show: head, panels, facts, action form, log list) and the products table, without tabs or JavaScript.
    Data from CustomerManagementController::show, unchanged: the customer, the addresses with their city, the last 10
    orders and the last 10 reviews. The status form is shown only to an admin who may change it (customers.edit or
    super admin); it posts to dashboard.customers.update-status as before (CSRF, PATCH, field "status").
    Texts through t() without auto-creating keys (AdminCustomerTranslationSeeder); the reused approved keys are
    Reviews and City.
--}}
@php
    $custText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };

    $custAdmin = auth('admin')->user();
    $custCan = fn (string $ability): bool => (bool) ($custAdmin?->isSuperAdmin() || $custAdmin?->hasAbility($ability));
    $canEdit = $custCan('customers.edit');
    $canViewOrders = $custCan('orders.view');

    // The account statuses the controller accepts (same order), with the customer-specific labels and badge tones.
    $statusKeys = [
        'active' => ['Customers_Status_Active', 'Active', 'is-active'],
        'pending' => ['Customers_Status_Pending', 'Pending activation', 'is-low'],
        'blocked' => ['Customers_Status_Blocked', 'Blocked', 'is-suspended'],
    ];
    $statusLabel = fn (?string $status): string => isset($statusKeys[$status]) ? $custText($statusKeys[$status][0], $statusKeys[$status][1]) : (string) $status;

    // The values of orders.status (OrderManagementController::statuses()).
    $orderStatusKeys = [
        'pending' => ['Orders_Status_Pending', 'Pending'],
        'processing' => ['Orders_Status_Processing', 'Processing'],
        'completed' => ['Orders_Status_Completed', 'Completed'],
        'partially_cancelled' => ['Orders_Status_Partially_Cancelled', 'Partially cancelled'],
        'cancelled' => ['Orders_Status_Cancelled', 'Cancelled'],
    ];
    $orderStatusLabel = fn (?string $status): string => isset($orderStatusKeys[$status]) ? $custText(...$orderStatusKeys[$status]) : (string) $status;

    $amount = fn ($value): string => number_format((float) $value, 2);
    $when = fn ($date): string => $date ? $date->format('Y-m-d H:i') : '—';
    // The submitted status after a failed save, or the current one when that value is not one of the three.
    $oldStatus = old('status');
    $selectedStatus = is_string($oldStatus) && isset($statusKeys[$oldStatus]) ? $oldStatus : (string) $customer->status;
@endphp

<x-dashboard-layout>
    <div class="col-span-12 admin-media admin-products admin-product-show admin-customer-show" id="admin-customer-show">

        {{-- Content header (5.1): the page title is in the admin header; the customer is named here. --}}
        <div class="admin-media-head admin-product-head">
            <div class="admin-product-identity">
                <a href="{{ route('dashboard.customers.index') }}" class="admin-product-back">
                    <i class="ph-duotone ph-arrow-left admin-product-back-arrow" aria-hidden="true"></i>
                    <span>{{ $custText('Customers_Show_Back', 'Back to customers') }}</span>
                </a>
                <h2 class="admin-product-title"><bdi>{{ $customer->name }}</bdi></h2>
                <p class="admin-products-badges">
                    <span class="admin-products-badge {{ $statusKeys[$customer->status][2] ?? '' }}">{{ $statusLabel($customer->status) }}</span>
                </p>
            </div>
        </div>

        {{-- Result messages (success, danger), inline as on the other admin pages. --}}
        @foreach (['success' => 'success', 'danger' => 'error'] as $flash => $tone)
            @if (session($flash))
                <div class="admin-media-alert is-{{ $tone }}" role="{{ $tone === 'success' ? 'status' : 'alert' }}">
                    <i class="ph-duotone {{ $tone === 'success' ? 'ph-check-circle' : 'ph-info' }}" aria-hidden="true"></i>
                    <div class="admin-media-alert-content"><p>{{ session($flash) }}</p></div>
                </div>
            @endif
        @endforeach

        {{-- 1. Contact details --}}
        <section class="card admin-media-panel admin-product-panel" aria-labelledby="customer-heading-contact">
            <h2 class="admin-product-panel-title" id="customer-heading-contact">{{ $custText('Customers_Show_Contact', 'Contact details') }}</h2>
            <dl class="admin-product-facts">
                <div><dt>{{ $custText('Customers_Column_Customer', 'Customer') }}</dt><dd><bdi>{{ $customer->name }}</bdi></dd></div>
                <div><dt>{{ $custText('Customers_Show_Email', 'Email') }}</dt><dd>@if (filled($customer->email))<span dir="ltr">{{ $customer->email }}</span>@else — @endif</dd></div>
                <div><dt>{{ $custText('Customers_Column_Phone', 'Phone') }}</dt><dd>@if (filled($customer->phone))<span dir="ltr">{{ $customer->phone }}</span>@else — @endif</dd></div>
                <div><dt>{{ $custText('Customers_Column_Status', 'Status') }}</dt><dd>{{ $statusLabel($customer->status) }}</dd></div>
            </dl>

            {{-- The account status form, only for an admin who may change it; the server checks the ability again. --}}
            @if ($canEdit)
                <form method="POST" action="{{ route('dashboard.customers.update-status', $customer) }}" class="admin-product-moderation is-lift" aria-labelledby="customer-status-title">
                    @csrf
                    @method('PATCH')
                    <h3 class="admin-product-moderation-title" id="customer-status-title">{{ $custText('Customers_Status_Title', 'Account status') }}</h3>
                    <p class="admin-product-moderation-hint" id="customer-status-hint">{{ $custText('Customers_Status_Hint', 'A blocked or pending customer cannot sign in and is signed out of the current session.') }}</p>
                    <label class="form-label" for="customer-status">{{ $custText('Customers_Status_Label', 'New status') }}</label>
                    <select class="form-select @error('status') is-invalid @enderror" id="customer-status" name="status" required
                        aria-describedby="customer-status-hint{{ $errors->has('status') ? ' customer-status-error' : '' }}"
                        @error('status') aria-invalid="true" @enderror>
                        @foreach (array_keys($statusKeys) as $key)
                            <option value="{{ $key }}" @selected($selectedStatus === $key)>{{ $statusLabel($key) }}</option>
                        @endforeach
                    </select>
                    @error('status')
                        <p class="admin-product-moderation-error" id="customer-status-error" role="alert">{{ $message }}</p>
                    @enderror
                    <div class="admin-product-moderation-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="ph-duotone ph-floppy-disk" aria-hidden="true"></i>
                            <span>{{ $custText('Customers_Status_Save', 'Save status') }}</span>
                        </button>
                    </div>
                </form>
            @endif
        </section>

        {{-- 2. Recent orders (the last 10 the controller loads) --}}
        <section class="card admin-media-panel admin-product-panel" aria-labelledby="customer-heading-orders">
            <h2 class="admin-product-panel-title" id="customer-heading-orders">{{ $custText('Customers_Recent_Orders', 'Recent orders') }}</h2>
            @if ($customer->orders->isEmpty())
                <p class="admin-products-muted">{{ $custText('Customers_No_Orders', 'This customer has no orders.') }}</p>
            @else
                <div class="admin-products-table-wrap">
                    <table class="admin-products-table">
                        <caption class="visually-hidden">{{ $custText('Customers_Recent_Orders', 'Recent orders') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ $custText('Customers_Order_Number', 'Order number') }}</th>
                                <th scope="col">{{ $custText('Customers_Column_Status', 'Status') }}</th>
                                <th scope="col" class="is-number">{{ $custText('Customers_Order_Total', 'Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customer->orders as $order)
                                <tr data-order-id="{{ $order->id }}">
                                    <td class="admin-products-cell-product">
                                        <span class="admin-products-label">{{ $custText('Customers_Order_Number', 'Order number') }}</span>
                                        @if ($canViewOrders)
                                            <a href="{{ route('dashboard.orders.show', $order) }}" class="admin-products-name-link" dir="ltr">{{ $order->number }}</a>
                                        @else
                                            <span dir="ltr">{{ $order->number }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="admin-products-label">{{ $custText('Customers_Column_Status', 'Status') }}</span>
                                        {{ $orderStatusLabel($order->status) }}
                                    </td>
                                    <td class="is-number">
                                        <span class="admin-products-label">{{ $custText('Customers_Order_Total', 'Total') }}</span>
                                        <span dir="ltr">{{ $amount($order->grand_total) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- 3. Addresses --}}
        <section class="card admin-media-panel admin-product-panel" aria-labelledby="customer-heading-addresses">
            <h2 class="admin-product-panel-title" id="customer-heading-addresses">{{ $custText('Customers_Addresses', 'Addresses') }}</h2>
            @if ($customer->addresses->isEmpty())
                <p class="admin-products-muted">{{ $custText('Customers_No_Addresses', 'No saved addresses.') }}</p>
            @else
                <dl class="admin-product-facts">
                    @foreach ($customer->addresses as $address)
                        <div class="is-wide" data-address-id="{{ $address->id }}">
                            <dt>
                                <bdi>{{ filled($address->label) ? $address->label : $address->recipient_name }}</bdi>
                                @if ($address->is_default)
                                    <span class="admin-products-badge is-active">{{ $custText('Customers_Address_Default', 'Default address') }}</span>
                                @endif
                            </dt>
                            <dd>
                                @if (filled($address->recipient_name))<bdi>{{ $address->recipient_name }}</bdi>@endif
                                @if (filled($address->phone)) · <span dir="ltr">{{ $address->phone }}</span>@endif
                                @if (filled($address->address_line)) · <bdi>{{ $address->address_line }}</bdi>@endif
                                @if ($address->city) · {{ $custText('City', 'City') }}: <bdi>{{ $address->city->name }}</bdi>@endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>

        {{-- 4. Reviews (the last 10 the controller loads) --}}
        <section class="card admin-media-panel admin-product-panel" aria-labelledby="customer-heading-reviews">
            <h2 class="admin-product-panel-title" id="customer-heading-reviews">{{ $custText('Reviews', 'Reviews') }}</h2>
            @if ($customer->reviews->isEmpty())
                <p class="admin-products-muted">{{ $custText('Customers_No_Reviews', 'This customer has no reviews.') }}</p>
            @else
                <ol class="admin-product-log">
                    @foreach ($customer->reviews as $review)
                        <li class="admin-product-log-entry" data-review-id="{{ $review->id }}">
                            <div class="admin-product-log-head">
                                <span class="admin-product-log-action">{{ str_replace(':rating', (string) (int) $review->rating, $custText('Customers_Review_Rating', 'Rating: :rating of 5')) }}</span>
                                <span class="admin-product-log-when" dir="ltr">{{ $when($review->created_at) }}</span>
                            </div>
                            @if (filled($review->comment))
                                <p class="admin-product-log-reason admin-product-text" dir="auto">{{ $review->comment }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>
</x-dashboard-layout>
