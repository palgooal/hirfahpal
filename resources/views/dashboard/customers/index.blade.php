{{--
    CUS-UI-05: the customers list in the admin shell (AD-UI-01), on the pattern of the products list (AD-UI-PROD-M3):
    a table on wide screens (5.2) that becomes one card per customer below 1024px (5.13), the filter bar (5.3) and the
    shared pagination (5.4). It reuses the existing pieces only (admin-media, admin-cats filter bar, admin-products
    table and badges); no CSS or JavaScript of its own.
    Search and status come from CustomerManagementController::index (search grouped with the status filter, CUS-BE-001;
    arrays and unknown statuses ignored, CUS-BE-002). Texts through t() without auto-creating keys (AdminCustomerTranslationSeeder);
    the reused approved keys are Nav_Customers, Orders, Reviews, Actions and, in the pagination, Pagination_*.
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

    // The account statuses the controller accepts, with the customer-specific labels and the badge tone of the
    // shared badge styles (olive for active, copper for waiting, red for blocked).
    $statusKeys = [
        'active' => ['Customers_Status_Active', 'Active', 'is-active'],
        'pending' => ['Customers_Status_Pending', 'Pending activation', 'is-low'],
        'blocked' => ['Customers_Status_Blocked', 'Blocked', 'is-suspended'],
    ];
    $statusLabel = fn (?string $status): string => isset($statusKeys[$status]) ? $custText($statusKeys[$status][0], $statusKeys[$status][1]) : (string) $status;

    // The current filters, read as the controller applies them: plain strings only, and a known status only.
    $search = is_string(request()->query('search')) ? request()->query('search') : '';
    $status = is_string(request()->query('status')) ? trim(request()->query('status')) : '';
    $status = isset($statusKeys[$status]) ? $status : '';
    $filtered = trim($search) !== '' || $status !== '';
@endphp

<x-dashboard-layout>
    <div class="col-span-12 admin-media admin-products admin-customers" id="admin-customers">

        {{-- Content header (5.1): the page title is in the admin header. --}}
        <div class="admin-media-head">
            <p class="admin-media-intro">{{ $custText('Customers_Intro', 'Follow customer accounts in the store: find them and check their orders and account status.') }}</p>
        </div>

        {{-- Result messages (success, danger), inline as on the other admin lists. --}}
        @foreach (['success' => 'success', 'danger' => 'error'] as $flash => $tone)
            @if (session($flash))
                <div class="admin-media-alert is-{{ $tone }}" role="{{ $tone === 'success' ? 'status' : 'alert' }}">
                    <i class="ph-duotone {{ $tone === 'success' ? 'ph-check-circle' : 'ph-info' }}" aria-hidden="true"></i>
                    <div class="admin-media-alert-content"><p>{{ session($flash) }}</p></div>
                </div>
            @endif
        @endforeach

        <section class="card admin-media-panel" aria-labelledby="customers-list-title">
            <h2 class="visually-hidden" id="customers-list-title">{{ $custText('Nav_Customers', 'Customers') }}</h2>

            {{-- Filter bar (5.3): what the controller supports; Clear appears once a filter is used. --}}
            <form method="GET" action="{{ route('dashboard.customers.index') }}" class="admin-cats-filters admin-products-filters" role="search" aria-label="{{ $custText('Customers_Search_Label', 'Search customers') }}">
                <div class="admin-cats-field admin-cats-field-search">
                    <label class="form-label" for="customers-search">{{ $custText('Customers_Search_Label', 'Search customers') }}</label>
                    <div class="admin-media-search">
                        <i class="ph-duotone ph-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" class="form-control" id="customers-search" name="search" value="{{ $search }}" maxlength="100" autocomplete="off" dir="auto" placeholder="{{ $custText('Customers_Search_Placeholder', 'Name, email or phone') }}">
                    </div>
                </div>
                <div class="admin-cats-field">
                    <label class="form-label" for="customers-status">{{ $custText('Customers_Filter_Status', 'Account status') }}</label>
                    <select class="form-select" id="customers-status" name="status">
                        <option value="">{{ $custText('Customers_Filter_All', 'All statuses') }}</option>
                        @foreach (array_keys($statusKeys) as $key)
                            <option value="{{ $key }}" @selected($status === $key)>{{ $statusLabel($key) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-cats-filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph-duotone ph-funnel" aria-hidden="true"></i>
                        <span>{{ $custText('Customers_Apply', 'Apply') }}</span>
                    </button>
                    @if ($filtered)
                        <a href="{{ route('dashboard.customers.index') }}" class="btn btn-light-secondary">{{ $custText('Customers_Clear', 'Clear filters') }}</a>
                    @endif
                </div>
            </form>

            <div class="card-body">
                @if ($customers->isEmpty())
                    <div class="admin-media-state" role="status">
                        <i class="ph-duotone ph-users-three" aria-hidden="true"></i>
                        @if ($customers->total() > 0)
                            {{-- A page past the last one: point back to the last page. --}}
                            <span>{{ $custText('Customers_No_Results_Title', 'No matching customers') }}</span>
                            <a href="{{ $customers->url($customers->lastPage()) }}" class="btn btn-light-secondary">{{ str_replace(':page', (string) $customers->lastPage(), $custText('Pagination_Page', 'Page :page')) }}</a>
                        @elseif ($filtered)
                            <span>{{ $custText('Customers_No_Results_Title', 'No matching customers') }}</span>
                            <span class="admin-media-hint">{{ $custText('Customers_No_Results_Text', 'Try another search or clear the filters.') }}</span>
                            <a href="{{ route('dashboard.customers.index') }}" class="btn btn-light-secondary">{{ $custText('Customers_Clear', 'Clear filters') }}</a>
                        @else
                            <span>{{ $custText('Customers_Empty_Title', 'No customers yet') }}</span>
                            <span class="admin-media-hint">{{ $custText('Customers_Empty_Text', 'Customers appear here once they sign up in the store.') }}</span>
                        @endif
                    </div>
                @else
                    <div class="admin-products-table-wrap">
                        <table class="admin-products-table">
                            <caption class="visually-hidden">{{ $custText('Nav_Customers', 'Customers') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ $custText('Customers_Column_Customer', 'Customer') }}</th>
                                    <th scope="col">{{ $custText('Customers_Column_Phone', 'Phone') }}</th>
                                    <th scope="col" class="is-number">{{ $custText('Orders', 'Orders') }}</th>
                                    <th scope="col" class="is-number">{{ $custText('Reviews', 'Reviews') }}</th>
                                    <th scope="col">{{ $custText('Customers_Column_Status', 'Status') }}</th>
                                    <th scope="col" class="is-actions"><span class="visually-hidden">{{ $custText('Actions', 'Actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($customers as $customer)
                                    <tr data-customer-id="{{ $customer->id }}">
                                        <td class="admin-products-cell-product">
                                            <p class="admin-products-name"><a href="{{ route('dashboard.customers.show', $customer) }}" class="admin-products-name-link"><bdi>{{ $customer->name }}</bdi></a></p>
                                            @if (filled($customer->email))
                                                <p class="admin-products-code" dir="ltr">{{ $customer->email }}</p>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="admin-products-label">{{ $custText('Customers_Column_Phone', 'Phone') }}</span>
                                            @if (filled($customer->phone))
                                                <span dir="ltr">{{ $customer->phone }}</span>
                                            @else
                                                <span class="admin-products-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="is-number">
                                            <span class="admin-products-label">{{ $custText('Orders', 'Orders') }}</span>
                                            {{ (int) $customer->orders_count }}
                                        </td>
                                        <td class="is-number">
                                            <span class="admin-products-label">{{ $custText('Reviews', 'Reviews') }}</span>
                                            {{ (int) $customer->reviews_count }}
                                        </td>
                                        <td>
                                            <span class="admin-products-label">{{ $custText('Customers_Column_Status', 'Status') }}</span>
                                            <span class="admin-products-badge {{ $statusKeys[$customer->status][2] ?? '' }}">{{ $statusLabel($customer->status) }}</span>
                                        </td>
                                        <td class="is-actions">
                                            <a href="{{ route('dashboard.customers.show', $customer) }}" class="btn btn-sm btn-light-secondary">
                                                <i class="ph-duotone ph-eye" aria-hidden="true"></i>
                                                <span>{{ $custText('Customers_View', 'View') }}</span>
                                                <span class="visually-hidden"><bdi>{{ $customer->name }}</bdi></span>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{ $customers->links('dashboard.partials.pagination') }}
                @endif
            </div>
        </section>
    </div>
</x-dashboard-layout>
