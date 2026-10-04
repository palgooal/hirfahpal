<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Vendors', 'Vendors') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <div class="card">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-light-primary text-primary mb-2">{{ t('dashboard.Admin_Workspace', 'Admin Workspace') }}</span>
                    <h4 class="mb-1">{{ t('dashboard.Vendor_Approvals', 'Vendor Approvals') }}</h4>
                    <p class="text-muted mb-0">{{ t('dashboard.Vendor_Approvals_Description', 'Review vendor accounts before they start selling.') }}</p>
                </div>
                @if (auth('admin')->user()?->isSuperAdmin() || auth('admin')->user()?->hasAbility('vendors.create'))
                    <a href="{{ route('dashboard.vendors.create') }}" class="btn btn-primary">
                        <i class="ti ti-plus me-1"></i>
                        {{ t('dashboard.Add_Vendor', 'Add Vendor') }}
                    </a>
                @else
                    <div class="avtar avtar-xl bg-light-primary">
                        <i class="ti ti-building-store f-30"></i>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-span-12">
        <div class="card">
            <div class="card-header">
                <form action="{{ route('dashboard.vendors.index') }}" method="GET" class="row g-3 align-items-end">
                    <div class="col-12 col-md-6">
                        <label class="form-label">{{ t('dashboard.Search', 'Search') }}</label>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ t('dashboard.Search_vendors', 'Search vendors or stores...') }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">{{ t('dashboard.Approval_Status', 'Approval Status') }}</label>
                        <select name="approval_status" class="form-select">
                            <option value="">{{ t('dashboard.All', 'All') }}</option>
                            @foreach (['pending', 'approved', 'rejected'] as $status)
                                <option value="{{ $status }}" @selected(request('approval_status') === $status)>
                                    {{ t('dashboard.'.ucfirst($status), ucfirst($status)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">{{ t('dashboard.Filter', 'Filter') }}</button>
                        <a href="{{ route('dashboard.vendors.index') }}" class="btn btn-light-secondary">{{ t('dashboard.Reset', 'Reset') }}</a>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>{{ t('dashboard.Store', 'Store') }}</th>
                                <th>{{ t('dashboard.Owner', 'Owner') }}</th>
                                <th>{{ t('dashboard.Location', 'Location') }}</th>
                                <th>{{ t('dashboard.Approval_Status', 'Approval Status') }}</th>
                                <th>{{ t('dashboard.Account_Status', 'Account Status') }}</th>
                                <th class="text-end">{{ t('dashboard.Actions', 'Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($vendors as $vendor)
                                @php
                                    $profile = $vendor->profile;
                                    $approval = $profile?->approval_status ?? 'pending';
                                    $approvalColor = ['approved' => 'success', 'rejected' => 'danger', 'pending' => 'warning'][$approval] ?? 'secondary';
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $profile?->store_name ?? '-' }}</div>
                                        <small class="text-muted">{{ $profile?->slug ?? '' }}</small>
                                    </td>
                                    <td>
                                        <div>{{ $vendor->name }}</div>
                                        <small class="text-muted">{{ $vendor->phone }}</small>
                                    </td>
                                    <td>{{ $profile?->city?->name ?? $profile?->governorate?->name ?? '-' }}</td>
                                    <td>
                                        <span class="badge bg-light-{{ $approvalColor }} text-{{ $approvalColor }}">
                                            {{ t('dashboard.'.ucfirst($approval), ucfirst($approval)) }}
                                        </span>
                                    </td>
                                    <td>{{ t('dashboard.'.ucfirst($vendor->status), ucfirst($vendor->status)) }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('dashboard.vendors.show', $vendor) }}" class="btn btn-sm btn-light-secondary">
                                            {{ t('dashboard.View', 'View') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">{{ t('dashboard.No_vendors_found', 'No vendors found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $vendors->links() }}
                </div>
            </div>
        </div>
    </div>
</x-dashboard-layout>
