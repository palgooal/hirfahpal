<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.vendor-orders.index') }}">{{ t('dashboard.Vendor_Orders', 'Vendor Orders') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Add_Vendor_Order', 'Add Vendor Order') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <div class="card">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-light-primary text-primary mb-2">{{ t('dashboard.Vendor_Orders', 'Vendor Orders') }}</span>
                    <h4 class="mb-1">{{ t('dashboard.Add_Vendor_Order', 'Add Vendor Order') }}</h4>
                    <p class="text-muted mb-0">{{ t('dashboard.Add_Vendor_Order_Description', 'Create a vendor-specific order under an existing parent order.') }}</p>
                </div>
                <a href="{{ route('dashboard.vendor-orders.index') }}" class="btn btn-light-secondary">
                    {{ t('dashboard.Back_to_vendor_orders', 'Back to vendor orders') }}
                </a>
            </div>
        </div>
    </div>

    <div class="col-span-12">
        <form action="{{ route('dashboard.vendor-orders.store') }}" method="POST" class="card">
            @csrf

            <div class="card-header">
                <h5 class="mb-0">{{ t('dashboard.Vendor_Order_Details', 'Vendor Order Details') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ t('dashboard.Parent_Order', 'Parent Order') }}</label>
                        <select name="order_id" class="form-select" required>
                            <option value="">{{ t('dashboard.Select_order', 'Select order') }}</option>
                            @foreach ($orders as $order)
                                <option value="{{ $order->id }}" @selected(old('order_id') == $order->id)>
                                    {{ $order->number }} - {{ $order->customer?->name ?? t('dashboard.Unknown_customer', 'Unknown customer') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ t('dashboard.Vendor', 'Vendor') }}</label>
                        <select name="vendor_id" class="form-select" required>
                            <option value="">{{ t('dashboard.Select_vendor', 'Select vendor') }}</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}" @selected(old('vendor_id') == $vendor->id)>
                                    {{ $vendor->profile?->store_name ?? $vendor->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ t('dashboard.Number', 'Number') }}</label>
                        <input type="text" name="number" value="{{ old('number') }}" class="form-control" placeholder="{{ t('dashboard.Auto_generated_if_empty', 'Auto-generated if empty') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ t('dashboard.Status', 'Status') }}</label>
                        <select name="status" class="form-select" required>
                            @foreach ($statuses as $key => $label)
                                <option value="{{ $key }}" @selected(old('status', 'pending') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ t('dashboard.Response_Due', 'Response Due') }}</label>
                        <input type="datetime-local" name="vendor_response_due_at" value="{{ old('vendor_response_due_at') }}" class="form-control">
                    </div>
                </div>
            </div>

            <div class="card-header border-top">
                <h5 class="mb-0">{{ t('dashboard.Financials', 'Financials') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">{{ t('dashboard.Subtotal', 'Subtotal') }}</label>
                        <input type="number" name="subtotal" value="{{ old('subtotal', '0.00') }}" min="0" step="0.01" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ t('dashboard.Delivery_Fee', 'Delivery Fee') }}</label>
                        <input type="number" name="delivery_fee" value="{{ old('delivery_fee', '0.00') }}" min="0" step="0.01" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ t('dashboard.Commission', 'Commission') }}</label>
                        <input type="number" name="commission_amount" value="{{ old('commission_amount', '0.00') }}" min="0" step="0.01" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ t('dashboard.Total', 'Total') }}</label>
                        <input type="number" name="total" value="{{ old('total', '0.00') }}" min="0" step="0.01" class="form-control" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">{{ t('dashboard.Rejection_Reason', 'Rejection Reason') }}</label>
                        <textarea name="rejection_reason" rows="3" class="form-control">{{ old('rejection_reason') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-end gap-2">
                <a href="{{ route('dashboard.vendor-orders.index') }}" class="btn btn-light-secondary">{{ t('dashboard.Cancel', 'Cancel') }}</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-device-floppy me-1"></i>
                    {{ t('dashboard.Create_Vendor_Order', 'Create Vendor Order') }}
                </button>
            </div>
        </form>
    </div>
</x-dashboard-layout>
