<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item">{{ t('dashboard.Customers', 'Customers') }}</li>
    </x-slot:breadcrumbs>
    <div class="col-span-12">
        <div class="card">
            <div class="card-header">
                <form method="GET" class="row g-3">
                    <div class="col-md-8"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="{{ t('dashboard.Search_customers', 'Search customers...') }}"></div>
                    <div class="col-md-2"><select class="form-select" name="status"><option value="">{{ t('dashboard.All', 'All') }}</option>@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary">{{ t('dashboard.Filter', 'Filter') }}</button><a class="btn btn-light-secondary" href="{{ route('dashboard.customers.index') }}">{{ t('dashboard.Reset', 'Reset') }}</a></div>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead><tr><th>{{ t('dashboard.Customer', 'Customer') }}</th><th>{{ t('dashboard.Phone', 'Phone') }}</th><th>{{ t('dashboard.Orders', 'Orders') }}</th><th>{{ t('dashboard.Reviews', 'Reviews') }}</th><th>{{ t('dashboard.Status', 'Status') }}</th><th class="text-end">{{ t('dashboard.Actions', 'Actions') }}</th></tr></thead>
                        <tbody>@forelse($customers as $customer)<tr><td><div class="fw-semibold">{{ $customer->name }}</div><small class="text-muted">{{ $customer->email }}</small></td><td>{{ $customer->phone }}</td><td>{{ $customer->orders_count }}</td><td>{{ $customer->reviews_count }}</td><td>{{ $statuses[$customer->status] ?? $customer->status }}</td><td class="text-end"><a class="btn btn-sm btn-light-secondary" href="{{ route('dashboard.customers.show', $customer) }}">{{ t('dashboard.View', 'View') }}</a></td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">{{ t('dashboard.No_records_found', 'No records found') }}</td></tr>@endforelse</tbody>
                    </table>
                </div>
                {{ $customers->links() }}
            </div>
        </div>
    </div>
</x-dashboard-layout>
