{{--
    AD-UI-VENDORS-03: the vendors list in the admin shell (AD-UI-01), on the pattern of the customers and products
    lists: a table on wide screens (5.2) that becomes one card per vendor below 1024px (5.13), the filter bar (5.3) and
    the shared pagination (5.4). It reuses the existing pieces only (admin-media, admin-cats filter bar, admin-products
    table and badges); no CSS or JavaScript of its own. The owner's avatar is shown next to the name, with the checks
    of the vendor details page (a path under vendors/avatars/ that exists on the public disk).
    Search and approval status come from VendorManagementController::index, unchanged (a filled value filters, as
    before; the select shows a known approval status only). The badge tones are those of the vendor details page.
    Texts through t() without auto-creating keys (AdminVendorTranslationSeeder); the reused approved keys are
    Nav_Vendors, Actions and, in the pagination, Pagination_*.
--}}
@php
    $vendText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };

    $vendAdmin = auth('admin')->user();
    $canCreate = (bool) ($vendAdmin?->isSuperAdmin() || $vendAdmin?->hasAbility('vendors.create'));

    // Approval (a vendor without a profile is shown as pending, as before) and account status: key, label, tone.
    $approvalKeys = [
        'pending' => ['Vendors_Approval_Pending', 'Pending review', 'is-low'],
        'approved' => ['Vendors_Approval_Approved', 'Approved', 'is-active'],
        'rejected' => ['Vendors_Approval_Rejected', 'Rejected', 'is-suspended'],
    ];
    $accountKeys = [
        'active' => ['Vendors_Account_Active', 'Active', 'is-active'],
        'pending' => ['Vendors_Account_Pending', 'Pending activation', 'is-low'],
        'blocked' => ['Vendors_Account_Blocked', 'Blocked', 'is-suspended'],
    ];
    $label = fn (array $keys, ?string $value): string => isset($keys[$value]) ? $vendText($keys[$value][0], $keys[$value][1]) : (string) $value;

    // The current filters, read as the controller applies them (a filled value filters).
    $search = is_string(request()->query('search')) ? request()->query('search') : '';
    $approvalFilter = is_string(request()->query('approval_status')) ? request()->query('approval_status') : '';
    $filtered = request()->filled('search') || request()->filled('approval_status');
@endphp

<x-dashboard-layout>
    <div class="col-span-12 admin-media admin-products admin-vendors" id="admin-vendors">

        {{-- Content header (5.1): the page title is in the admin header. --}}
        <div class="admin-media-head">
            <p class="admin-media-intro">{{ $vendText('Vendors_Intro', 'Review vendor accounts and their stores: find them and check their approval and account status.') }}</p>
            @if ($canCreate)
                <a href="{{ route('dashboard.vendors.create') }}" class="btn btn-primary">
                    <i class="ph-duotone ph-plus" aria-hidden="true"></i>
                    <span>{{ $vendText('Vendors_Create_Title', 'Add vendor') }}</span>
                </a>
            @endif
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

        <section class="card admin-media-panel" aria-labelledby="vendors-list-title">
            <h2 class="visually-hidden" id="vendors-list-title">{{ $vendText('Nav_Vendors', 'Vendors') }}</h2>

            {{-- Filter bar (5.3): what the controller supports; Clear appears once a filter is used. --}}
            <form method="GET" action="{{ route('dashboard.vendors.index') }}" class="admin-cats-filters admin-products-filters" role="search" aria-label="{{ $vendText('Vendors_Search_Label', 'Search vendors') }}">
                <div class="admin-cats-field admin-cats-field-search">
                    <label class="form-label" for="vendors-search">{{ $vendText('Vendors_Search_Label', 'Search vendors') }}</label>
                    <div class="admin-media-search">
                        <i class="ph-duotone ph-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" class="form-control" id="vendors-search" name="search" value="{{ $search }}" maxlength="100" autocomplete="off" dir="auto" placeholder="{{ $vendText('Vendors_Search_Placeholder', 'Owner, email, phone or store name') }}">
                    </div>
                </div>
                <div class="admin-cats-field">
                    <label class="form-label" for="vendors-approval-status">{{ $vendText('Vendors_Approval_Status', 'Approval status') }}</label>
                    <select class="form-select" id="vendors-approval-status" name="approval_status">
                        <option value="">{{ $vendText('Vendors_Filter_All', 'All statuses') }}</option>
                        @foreach (array_keys($approvalKeys) as $key)
                            <option value="{{ $key }}" @selected($approvalFilter === $key)>{{ $label($approvalKeys, $key) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-cats-filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph-duotone ph-funnel" aria-hidden="true"></i>
                        <span>{{ $vendText('Vendors_Apply', 'Apply') }}</span>
                    </button>
                    @if ($filtered)
                        <a href="{{ route('dashboard.vendors.index') }}" class="btn btn-light-secondary">{{ $vendText('Vendors_Clear', 'Clear filters') }}</a>
                    @endif
                </div>
            </form>

            <div class="card-body">
                @if ($vendors->isEmpty())
                    <div class="admin-media-state" role="status" data-vendors-state="{{ $vendors->total() > 0 ? 'past-last-page' : ($filtered ? 'no-results' : 'empty') }}">
                        <i class="ph-duotone ph-storefront" aria-hidden="true"></i>
                        @if ($vendors->total() > 0)
                            {{-- A page past the last one: point back to the last page. --}}
                            <span>{{ $vendText('Vendors_No_Results_Title', 'No matching vendors') }}</span>
                            <a href="{{ $vendors->url($vendors->lastPage()) }}" class="btn btn-light-secondary">{{ str_replace(':page', (string) $vendors->lastPage(), $vendText('Pagination_Page', 'Page :page')) }}</a>
                        @elseif ($filtered)
                            <span>{{ $vendText('Vendors_No_Results_Title', 'No matching vendors') }}</span>
                            <span class="admin-media-hint">{{ $vendText('Vendors_No_Results_Text', 'Try another search or clear the filters.') }}</span>
                            <a href="{{ route('dashboard.vendors.index') }}" class="btn btn-light-secondary">{{ $vendText('Vendors_Clear', 'Clear filters') }}</a>
                        @else
                            <span>{{ $vendText('Vendors_Empty_Title', 'No vendors yet') }}</span>
                            <span class="admin-media-hint">{{ $vendText('Vendors_Empty_Text', 'Vendors appear here once they register or are added from the dashboard.') }}</span>
                        @endif
                    </div>
                @else
                    <div class="admin-products-table-wrap">
                        <table class="admin-products-table">
                            <caption class="visually-hidden">{{ $vendText('Nav_Vendors', 'Vendors') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ $vendText('Vendors_Show_Store_Name', 'Store name') }}</th>
                                    <th scope="col">{{ $vendText('Vendors_Show_Owner', 'Owner') }}</th>
                                    <th scope="col">{{ $vendText('Vendors_Show_Location', 'Location') }}</th>
                                    <th scope="col">{{ $vendText('Vendors_Approval_Status', 'Approval status') }}</th>
                                    <th scope="col">{{ $vendText('Vendors_Account_Status', 'Account status') }}</th>
                                    <th scope="col" class="is-actions"><span class="visually-hidden">{{ $vendText('Actions', 'Actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($vendors as $vendor)
                                    @php
                                        $profile = $vendor->profile;
                                        $approval = $profile?->approval_status ?? 'pending';
                                        $location = $profile?->city?->name ?? $profile?->governorate?->name;
                                        // The owner's avatar, with the same checks as the vendor details page.
                                        $avatarUrl = is_string($vendor->avatar) && str_starts_with($vendor->avatar, 'vendors/avatars/') && \Illuminate\Support\Facades\Storage::disk('public')->exists($vendor->avatar)
                                            ? \Illuminate\Support\Facades\Storage::disk('public')->url($vendor->avatar)
                                            : null;
                                    @endphp
                                    <tr data-vendor-id="{{ $vendor->id }}">
                                        <td class="admin-products-cell-product">
                                            <p class="admin-products-name">
                                                <a href="{{ route('dashboard.vendors.show', $vendor) }}" class="admin-products-name-link"><bdi>{{ $profile?->store_name ?? '—' }}</bdi></a>
                                            </p>
                                            @if (filled($profile?->slug))
                                                <p class="admin-products-code" dir="ltr">{{ $profile->slug }}</p>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="admin-products-label">{{ $vendText('Vendors_Show_Owner', 'Owner') }}</span>
                                            @if ($avatarUrl)
                                                <img src="{{ $avatarUrl }}" alt="" width="36" height="36" class="rounded-circle object-fit-cover align-middle">
                                            @endif
                                            <bdi>{{ $vendor->name }}</bdi>
                                            @if (filled($vendor->phone))
                                                <p class="admin-products-code" dir="ltr">{{ $vendor->phone }}</p>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="admin-products-label">{{ $vendText('Vendors_Show_Location', 'Location') }}</span>
                                            @if (filled($location))
                                                <bdi>{{ $location }}</bdi>
                                            @else
                                                <span class="admin-products-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="admin-products-label">{{ $vendText('Vendors_Approval_Status', 'Approval status') }}</span>
                                            <span class="admin-products-badge {{ $approvalKeys[$approval][2] ?? '' }}" data-approval-status="{{ $approval }}">{{ $label($approvalKeys, $approval) }}</span>
                                        </td>
                                        <td>
                                            <span class="admin-products-label">{{ $vendText('Vendors_Account_Status', 'Account status') }}</span>
                                            <span class="admin-products-badge {{ $accountKeys[$vendor->status][2] ?? '' }}" data-account-status="{{ $vendor->status }}">{{ $label($accountKeys, $vendor->status) }}</span>
                                        </td>
                                        <td class="is-actions">
                                            <a href="{{ route('dashboard.vendors.show', $vendor) }}" class="btn btn-sm btn-light-secondary">
                                                <i class="ph-duotone ph-eye" aria-hidden="true"></i>
                                                <span>{{ $vendText('Vendors_View', 'View') }}</span>
                                                <span class="visually-hidden"><bdi>{{ $profile?->store_name ?? $vendor->name }}</bdi></span>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{ $vendors->links('dashboard.partials.pagination') }}
                @endif
            </div>
        </section>
    </div>
</x-dashboard-layout>
