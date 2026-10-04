<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Admin_Dashboard', 'لوحة الإدارة') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <div class="card">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
                <div>
                    <p class="text-muted mb-1">{{ t('dashboard.Hirfah_Operations', 'HIRFAH Operations') }}</p>
                    <h4 class="mb-2">{{ t('dashboard.Admin_Control_Center', 'لوحة الإدارة') }}</h4>
                    <p class="text-muted mb-0">{{ t('dashboard.Admin_Control_Center_Description', 'Monitor vendor approvals, vendor order response windows, and manual delivery assignment.') }}</p>
                </div>
                <div class="avtar avtar-xl bg-light-primary">
                    <i class="ti ti-layout-dashboard f-30"></i>
                </div>
            </div>
        </div>
    </div>

    @foreach ([
        [t('dashboard.Pending_Vendors', 'Pending Vendors'), $stats['pending_vendors'], 'warning', 'ti-building-store', route('dashboard.vendors.index', ['approval_status' => 'pending'])],
        [t('dashboard.Active_Vendors', 'Active Vendors'), $stats['active_vendors'], 'success', 'ti-store', route('dashboard.vendors.index')],
        [t('dashboard.Pending_Vendor_Orders', 'Pending Vendor Orders'), $stats['pending_vendor_orders'], 'primary', 'ti-clipboard-list', route('dashboard.vendor-orders.index', ['status' => 'pending'])],
        [t('dashboard.Needs_attention', 'Needs attention'), $stats['needs_attention'], 'danger', 'ti-alert-triangle', route('dashboard.vendor-orders.index', ['needs_attention' => 1])],
    ] as [$label, $value, $color, $icon, $url])
        <div class="col-span-12 sm:col-span-6 xl:col-span-3">
            <a href="{{ $url }}" class="card d-block text-decoration-none">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-3">
                        <div>
                            <p class="text-muted mb-1">{{ $label }}</p>
                            <h3 class="mb-0">{{ number_format($value) }}</h3>
                        </div>
                        <div class="avtar avtar-s bg-light-{{ $color }}">
                            <i class="ti {{ $icon }} f-20"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach

    <div class="col-span-12 xl:col-span-7">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">{{ t('dashboard.Vendors_waiting_review', 'Vendors Waiting Review') }}</h5>
                <a href="{{ route('dashboard.vendors.index', ['approval_status' => 'pending']) }}" class="btn btn-sm btn-light-secondary">{{ t('dashboard.View_all', 'View all') }}</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>{{ t('dashboard.Store', 'Store') }}</th>
                                <th>{{ t('dashboard.Owner', 'Owner') }}</th>
                                <th>{{ t('dashboard.City', 'City') }}</th>
                                <th class="text-end">{{ t('dashboard.Actions', 'Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pendingVendors as $vendor)
                                <tr>
                                    <td class="fw-semibold">{{ $vendor->profile?->store_name ?? '-' }}</td>
                                    <td>{{ $vendor->name }}</td>
                                    <td>{{ $vendor->profile?->city?->name ?? '-' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('dashboard.vendors.show', $vendor) }}" class="btn btn-sm btn-light-secondary">{{ t('dashboard.Review', 'Review') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">{{ t('dashboard.No_pending_vendors', 'No pending vendors') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-12 xl:col-span-5">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">{{ t('dashboard.Response_Window_Alerts', 'Response Window Alerts') }}</h5>
                <a href="{{ route('dashboard.vendor-orders.index', ['needs_attention' => 1]) }}" class="btn btn-sm btn-light-warning">{{ t('dashboard.Open', 'Open') }}</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>{{ t('dashboard.Order', 'Order') }}</th>
                                <th>{{ t('dashboard.Vendor', 'Vendor') }}</th>
                                <th>{{ t('dashboard.Due', 'Due') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($attentionVendorOrders as $vendorOrder)
                                <tr>
                                    <td><a href="{{ route('dashboard.vendor-orders.show', $vendorOrder) }}">{{ $vendorOrder->number }}</a></td>
                                    <td>{{ $vendorOrder->vendor?->profile?->store_name ?? $vendorOrder->vendor?->name ?? '-' }}</td>
                                    <td class="text-warning fw-semibold">{{ $vendorOrder->vendor_response_due_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">{{ t('dashboard.No_alerts_now', 'No alerts now') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-dashboard-layout>
