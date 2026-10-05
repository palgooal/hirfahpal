<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.customers.index') }}">{{ t('dashboard.Customers', 'Customers') }}</a></li>
        <li class="breadcrumb-item">{{ $customer->name }}</li>
    </x-slot:breadcrumbs>
    <div class="col-span-12 lg:col-span-4">
        <div class="card"><div class="card-body">
            <h4>{{ $customer->name }}</h4><p class="text-muted">{{ $customer->email }}<br>{{ $customer->phone }}</p>
            <form method="POST" action="{{ route('dashboard.customers.update-status', $customer) }}" class="d-flex gap-2">@csrf @method('PATCH')<select name="status" class="form-select">@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected($customer->status === $key)>{{ $label }}</option>@endforeach</select><button class="btn btn-primary">{{ t('dashboard.Save', 'Save') }}</button></form>
        </div></div>
    </div>
    <div class="col-span-12 lg:col-span-8">
        <div class="card"><div class="card-header"><h5>{{ t('dashboard.Recent_Orders', 'Recent Orders') }}</h5></div><div class="card-body table-responsive"><table class="table"><tbody>@forelse($customer->orders as $order)<tr><td>{{ $order->number }}</td><td>{{ $order->status }}</td><td class="text-end">{{ number_format((float) $order->grand_total, 2) }}</td></tr>@empty<tr><td class="text-muted">{{ t('dashboard.No_records_found', 'No records found') }}</td></tr>@endforelse</tbody></table></div></div>
        <div class="card"><div class="card-header"><h5>{{ t('dashboard.Addresses', 'Addresses') }}</h5></div><div class="card-body">@forelse($customer->addresses as $address)<p class="mb-2"><strong>{{ $address->label }}</strong> - {{ $address->recipient_name }} - {{ $address->address_line }} {{ $address->city?->name }}</p>@empty<p class="text-muted mb-0">{{ t('dashboard.No_records_found', 'No records found') }}</p>@endforelse</div></div>
    </div>
</x-dashboard-layout>
