<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.vendors.index') }}">{{ t('dashboard.Vendors', 'Vendors') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ $vendor->profile?->store_name ?? $vendor->name }}</li>
    </x-slot:breadcrumbs>

    @php
        $profile = $vendor->profile;
        $approval = $profile?->approval_status ?? 'pending';
        $approvalColor = ['approved' => 'success', 'rejected' => 'danger', 'pending' => 'warning'][$approval] ?? 'secondary';
    @endphp

    <div class="col-span-12 xl:col-span-8">
        <div class="card">
            <div class="card-body">
                <span class="badge bg-light-{{ $approvalColor }} text-{{ $approvalColor }} mb-3">
                    {{ t('dashboard.'.ucfirst($approval), ucfirst($approval)) }}
                </span>
                <h4 class="mb-1">{{ $profile?->store_name ?? $vendor->name }}</h4>
                <p class="text-muted mb-4">{{ $profile?->short_description ?? t('dashboard.No_description_available', 'No description available.') }}</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted">{{ t('dashboard.Owner', 'Owner') }}</small>
                            <div class="fw-semibold">{{ $vendor->name }}</div>
                            <div class="text-muted">{{ $vendor->email ?: '-' }}</div>
                            <div class="text-muted">{{ $vendor->phone }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted">{{ t('dashboard.Location', 'Location') }}</small>
                            <div class="fw-semibold">{{ $profile?->governorate?->name ?? '-' }}</div>
                            <div class="text-muted">{{ $profile?->city?->name ?? '-' }}</div>
                            <div class="text-muted">{{ $profile?->address_line ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                @if ($profile?->description)
                    <div class="mt-4">
                        <h5>{{ t('dashboard.Description', 'Description') }}</h5>
                        <p class="text-muted mb-0">{{ $profile->description }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-span-12 xl:col-span-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ t('dashboard.Admin_Decision', 'Admin Decision') }}</h5>
            </div>
            <div class="card-body d-grid gap-3">
                @if (auth('admin')->user()?->isSuperAdmin() || auth('admin')->user()?->hasAbility('vendors.edit'))
                    <form action="{{ route('dashboard.vendors.approve', $vendor) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-success w-100">
                            <i class="ti ti-check me-1"></i>
                            {{ t('dashboard.Approve_vendor', 'Approve Vendor') }}
                        </button>
                    </form>

                    <form action="{{ route('dashboard.vendors.reject', $vendor) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <label class="form-label">{{ t('dashboard.Rejection_Reason', 'Rejection Reason') }}</label>
                        <textarea name="rejection_reason" class="form-control mb-2" rows="4" required>{{ old('rejection_reason', $profile?->rejection_reason) }}</textarea>
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="ti ti-x me-1"></i>
                            {{ t('dashboard.Reject_vendor', 'Reject Vendor') }}
                        </button>
                    </form>
                @else
                    <p class="text-muted mb-0">{{ t('dashboard.No_permission_for_decision', 'You do not have permission to make this decision.') }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-span-12 xl:col-span-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ t('dashboard.Recent_Products', 'Recent Products') }}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>{{ t('dashboard.Product', 'Product') }}</th>
                                <th>{{ t('dashboard.Status', 'Status') }}</th>
                                <th>{{ t('dashboard.Price', 'Price') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($vendor->products as $product)
                                <tr>
                                    <td>{{ $product->name }}</td>
                                    <td>{{ t('dashboard.'.ucfirst($product->status), ucfirst($product->status)) }}</td>
                                    <td>{{ number_format((float) $product->price, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">{{ t('dashboard.No_products_found', 'No products found') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-12 xl:col-span-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ t('dashboard.Recent_Vendor_Orders', 'Recent Vendor Orders') }}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>{{ t('dashboard.Number', 'Number') }}</th>
                                <th>{{ t('dashboard.Status', 'Status') }}</th>
                                <th>{{ t('dashboard.Total', 'Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($vendor->vendorOrders as $vendorOrder)
                                <tr>
                                    <td><a href="{{ route('dashboard.vendor-orders.show', $vendorOrder) }}">{{ $vendorOrder->number }}</a></td>
                                    <td>{{ $vendorOrder->status }}</td>
                                    <td>{{ number_format((float) $vendorOrder->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">{{ t('dashboard.No_orders_found', 'No orders found') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-dashboard-layout>
