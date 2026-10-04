<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Vendor_Orders', 'Vendor Orders') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <div class="card">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-light-warning text-warning mb-2">{{ t('dashboard.Operations', 'Operations') }}</span>
                    <h4 class="mb-1">{{ t('dashboard.Vendor_Order_Monitoring', 'Vendor Order Monitoring') }}</h4>
                    <p class="text-muted mb-0">{{ t('dashboard.Vendor_Order_Monitoring_Description', 'Track vendor response windows and delivery assignment without automatic rejection.') }}</p>
                </div>
                <div class="d-flex gap-2">
                    @if (auth('admin')->user()?->isSuperAdmin() || auth('admin')->user()?->hasAbility('vendor-orders.create'))
                        <a href="{{ route('dashboard.vendor-orders.create') }}" class="btn btn-primary">
                            <i class="ti ti-plus me-1"></i>
                            {{ t('dashboard.Add_Vendor_Order', 'Add Vendor Order') }}
                        </a>
                    @endif
                    <a href="{{ route('dashboard.vendor-orders.index', ['needs_attention' => 1]) }}" class="btn btn-outline-warning">
                        <i class="ti ti-alert-triangle me-1"></i>
                        {{ t('dashboard.Needs_attention', 'Needs attention') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-12">
        <div class="card">
            <div class="card-header">
                <form action="{{ route('dashboard.vendor-orders.index') }}" method="GET" class="row g-3 align-items-end">
                    <div class="col-12 col-lg-5">
                        <label class="form-label">{{ t('dashboard.Search', 'Search') }}</label>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ t('dashboard.Search_orders_or_vendors', 'Search orders or vendors...') }}">
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label">{{ t('dashboard.Status', 'Status') }}</label>
                        <select name="status" class="form-select">
                            <option value="">{{ t('dashboard.All', 'All') }}</option>
                            @foreach ($statuses as $key => $label)
                                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" value="1" name="needs_attention" id="needs_attention" @checked(request()->boolean('needs_attention'))>
                            <label class="form-check-label" for="needs_attention">{{ t('dashboard.Needs_attention', 'Needs attention') }}</label>
                        </div>
                    </div>
                    <div class="col-12 col-lg-2 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">{{ t('dashboard.Filter', 'Filter') }}</button>
                        <a href="{{ route('dashboard.vendor-orders.index') }}" class="btn btn-light-secondary">{{ t('dashboard.Reset', 'Reset') }}</a>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>{{ t('dashboard.Vendor_Order', 'Vendor Order') }}</th>
                                <th>{{ t('dashboard.Parent_Order', 'Parent Order') }}</th>
                                <th>{{ t('dashboard.Vendor', 'Vendor') }}</th>
                                <th>{{ t('dashboard.Status', 'Status') }}</th>
                                <th>{{ t('dashboard.Response_Due', 'Response Due') }}</th>
                                <th>{{ t('dashboard.Driver', 'Driver') }}</th>
                                <th class="text-end">{{ t('dashboard.Total', 'Total') }}</th>
                                <th class="text-end">{{ t('dashboard.Actions', 'Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($vendorOrders as $vendorOrder)
                                @php
                                    $dueAt = $vendorOrder->vendor_response_due_at;
                                    $needsAttention = $vendorOrder->status === 'pending' && $dueAt && $dueAt->lte(now()->addHours(3));
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $vendorOrder->number }}</td>
                                    <td>{{ $vendorOrder->order?->number ?? '-' }}</td>
                                    <td>{{ $vendorOrder->vendor?->profile?->store_name ?? $vendorOrder->vendor?->name ?? '-' }}</td>
                                    <td>
                                        <span class="badge bg-light-{{ in_array($vendorOrder->status, ['rejected', 'cancelled'], true) ? 'danger' : ($vendorOrder->status === 'completed' ? 'success' : 'primary') }}">
                                            {{ $statuses[$vendorOrder->status] ?? $vendorOrder->status }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($dueAt)
                                            <span class="{{ $needsAttention ? 'text-warning fw-semibold' : 'text-muted' }}">
                                                {{ $dueAt->diffForHumans() }}
                                            </span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $vendorOrder->deliveryAssignment?->deliveryDriver?->name ?? $vendorOrder->deliveryDriver?->name ?? '-' }}</td>
                                    <td class="text-end">{{ number_format((float) $vendorOrder->total, 2) }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('dashboard.vendor-orders.show', $vendorOrder) }}" class="btn btn-sm btn-light-secondary">
                                            {{ t('dashboard.View', 'View') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">{{ t('dashboard.No_vendor_orders_found', 'No vendor orders found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $vendorOrders->links() }}
                </div>
            </div>
        </div>
    </div>
</x-dashboard-layout>
