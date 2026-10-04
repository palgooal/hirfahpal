<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.vendor-orders.index') }}">{{ t('dashboard.Vendor_Orders', 'Vendor Orders') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ $vendorOrder->number }}</li>
    </x-slot:breadcrumbs>

    @php
        $assignment = $vendorOrder->deliveryAssignment;
        $canAssign = auth('admin')->user()?->isSuperAdmin() || auth('admin')->user()?->hasAbility('vendor-orders.assign-driver');
        $closed = in_array($vendorOrder->status, ['rejected', 'cancelled', 'completed'], true);
    @endphp

    <div class="col-span-12 xl:col-span-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div>
                        <span class="badge bg-light-primary text-primary mb-2">{{ $statuses[$vendorOrder->status] ?? $vendorOrder->status }}</span>
                        <h4 class="mb-1">{{ $vendorOrder->number }}</h4>
                        <p class="text-muted mb-0">
                            {{ t('dashboard.Parent_Order', 'Parent Order') }}:
                            {{ $vendorOrder->order?->number ?? '-' }}
                        </p>
                    </div>
                    <div class="text-lg-end">
                        <small class="text-muted">{{ t('dashboard.Total', 'Total') }}</small>
                        <h4 class="mb-0">{{ number_format((float) $vendorOrder->total, 2) }}</h4>
                    </div>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted">{{ t('dashboard.Vendor', 'Vendor') }}</small>
                            <div class="fw-semibold">{{ $vendorOrder->vendor?->profile?->store_name ?? $vendorOrder->vendor?->name ?? '-' }}</div>
                            <div class="text-muted">{{ $vendorOrder->vendor?->phone ?? '-' }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted">{{ t('dashboard.Customer', 'Customer') }}</small>
                            <div class="fw-semibold">{{ $vendorOrder->order?->customer?->name ?? '-' }}</div>
                            <div class="text-muted">{{ $vendorOrder->order?->customer?->phone ?? '-' }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted">{{ t('dashboard.Response_Due', 'Response Due') }}</small>
                            <div class="fw-semibold">{{ $vendorOrder->vendor_response_due_at?->format('Y-m-d H:i') ?? '-' }}</div>
                            <div class="text-muted">
                                {{ $vendorOrder->vendor_response_due_at ? $vendorOrder->vendor_response_due_at->diffForHumans() : '-' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ t('dashboard.Order_Items', 'Order Items') }}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>{{ t('dashboard.Product', 'Product') }}</th>
                                <th>{{ t('dashboard.Quantity', 'Quantity') }}</th>
                                <th>{{ t('dashboard.Unit_Price', 'Unit Price') }}</th>
                                <th class="text-end">{{ t('dashboard.Total', 'Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($vendorOrder->items as $item)
                                <tr>
                                    <td>{{ $item->product?->name ?? $item->product_name ?? '-' }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>{{ number_format((float) $item->unit_price, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $item->line_total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">{{ t('dashboard.No_items_found', 'No items found') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-12 xl:col-span-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ t('dashboard.Manual_Delivery_Assignment', 'Manual Delivery Assignment') }}</h5>
            </div>
            <div class="card-body">
                @if ($assignment)
                    <div class="alert alert-info">
                        <div class="fw-semibold">{{ $assignment->deliveryDriver?->name }}</div>
                        <div>{{ t('dashboard.Assigned', 'Assigned') }}: {{ $assignment->assigned_at?->format('Y-m-d H:i') ?? '-' }}</div>
                    </div>
                @endif

                @if ($canAssign && ! $closed)
                    <form action="{{ route('dashboard.vendor-orders.assign-driver', $vendorOrder) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <div class="mb-3">
                            <label class="form-label">{{ t('dashboard.Delivery_Driver', 'Delivery Driver') }}</label>
                            <select name="delivery_driver_id" class="form-select" required>
                                <option value="">{{ t('dashboard.Select_driver', 'Select driver') }}</option>
                                @foreach ($drivers as $driver)
                                    <option value="{{ $driver->id }}" @selected(old('delivery_driver_id', $assignment?->delivery_driver_id) == $driver->id)>
                                        {{ $driver->name }} - {{ $driver->phone }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ t('dashboard.Notes', 'Notes') }}</label>
                            <textarea name="notes" class="form-control" rows="3">{{ old('notes', $assignment?->notes) }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ti ti-truck-delivery me-1"></i>
                            {{ t('dashboard.Assign_driver', 'Assign Driver') }}
                        </button>
                    </form>
                @elseif ($closed)
                    <p class="text-muted mb-0">{{ t('dashboard.Closed_order_cannot_be_assigned', 'Closed orders cannot be assigned to a driver.') }}</p>
                @else
                    <p class="text-muted mb-0">{{ t('dashboard.No_permission_for_assignment', 'You do not have permission to assign drivers.') }}</p>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ t('dashboard.Domain_Separation', 'Domain Separation') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span>{{ t('dashboard.Order_Status', 'Order Status') }}</span>
                    <span class="fw-semibold">{{ $vendorOrder->order?->status ?? '-' }}</span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span>{{ t('dashboard.Vendor_Order_Status', 'Vendor Order Status') }}</span>
                    <span class="fw-semibold">{{ $vendorOrder->status }}</span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span>{{ t('dashboard.Payment_Status', 'Payment Status') }}</span>
                    <span class="fw-semibold">{{ $vendorOrder->order?->payment_status ?? '-' }}</span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span>{{ t('dashboard.Delivery_Status', 'Delivery Status') }}</span>
                    <span class="fw-semibold">{{ $assignment?->status ?? '-' }}</span>
                </div>
            </div>
        </div>
    </div>
</x-dashboard-layout>
