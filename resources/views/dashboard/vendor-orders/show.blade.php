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
        // VEN-BE-020 (slice 2A): the form only where the server accepts an assignment; orders not ready yet and
        // delivered orders get an explanation instead.
        $assignable = in_array($vendorOrder->status, \App\Http\Controllers\Dashboard\VendorOrderManagementController::ASSIGNABLE_STATUSES, true);
        $notReady = in_array($vendorOrder->status, \App\Http\Controllers\Dashboard\VendorOrderManagementController::NOT_READY_STATUSES, true);
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
                        @if ($assignment->status === 'failed' && $vendorOrder->status === 'out_for_delivery')
                            <div class="mt-2 text-warning">{{ t('dashboard.Return_required_before_reassignment', 'The shipment must be returned to the vendor before it can be assigned again.') }}</div>
                        @endif
                    </div>
                    {{-- E2-MVP batch 03: why the driver declined the task or could not deliver it; the admin handles it by hand. --}}
                    @if ($assignment->declined_at !== null || $assignment->status === 'failed')
                        @php
                            $declined = $assignment->declined_at !== null;
                            $reasonLabel = $declined
                                ? \App\Support\Orders\DeliveryReasons::label(\App\Support\Orders\DeliveryReasons::DECLINE, $assignment->decline_code)
                                : \App\Support\Orders\DeliveryReasons::label(\App\Support\Orders\DeliveryReasons::FAILURE, $assignment->failure_code);
                            $reasonText = $declined ? $assignment->decline_note : $assignment->failure_reason;
                        @endphp
                        <div class="alert alert-warning" data-delivery-issue="{{ $declined ? 'declined' : 'failed' }}">
                            <div class="fw-semibold">{{ t('dashboard.Vendor_Order_Assignment_Delivery_Issue', 'Delivery issue') }}</div>
                            <div>
                                {{ $declined ? t('dashboard.Vendor_Order_Assignment_Declined', 'The driver declined the task before accepting it') : t('dashboard.Delivery_History_Failed_By_Driver', 'Delivery failure reported by the driver') }}
                                — {{ ($declined ? $assignment->declined_at : $assignment->failed_at)?->format('Y-m-d H:i') ?? '-' }}
                            </div>
                            @if ($reasonLabel !== null || filled($reasonText))
                                <div class="mt-1" dir="auto">{{ collect([$reasonLabel, $reasonText])->filter(fn ($part) => filled($part))->join(': ') }}</div>
                            @endif
                        </div>
                    @endif
                    {{-- E2-MVP custody fix: a failed delivery after pickup stays in the driver's custody until the admin
                         confirms, after checking with the vendor, that the goods are back (the driver's declaration is
                         shown when there is one, and is not required). --}}
                    @if ($assignment->status === 'failed' && $vendorOrder->status === 'out_for_delivery')
                        @php
                            $canConfirmReturn = auth('admin')->user()?->isSuperAdmin() || auth('admin')->user()?->hasAbility('vendor-orders.confirm-return');
                        @endphp
                        <div class="border rounded p-3 mb-3" data-custody="{{ $assignment->return_declared_at ? 'return-pending' : 'with-driver' }}">
                            <div>
                                <span class="text-muted">{{ t('dashboard.Custody_Status', 'Goods custody') }}:</span>
                                <span class="fw-semibold">
                                    @if ($assignment->return_declared_at)
                                        {{ t('dashboard.Custody_Return_Pending', 'Return pending confirmation') }} — {{ $assignment->return_declared_at->format('Y-m-d H:i') }}
                                    @else
                                        {{ t('dashboard.Custody_With_Driver', 'With the driver') }}
                                    @endif
                                </span>
                            </div>
                            @if ($canConfirmReturn)
                                <form method="POST" action="{{ route('dashboard.vendor-orders.confirm-return', $vendorOrder) }}" class="mt-3">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-warning" data-confirm-return>{{ t('dashboard.Confirm_Delivery_Return', 'Confirm Returned Goods') }}</button>
                                </form>
                            @endif
                        </div>
                    @endif
                @endif

                @if ($notReady)
                    <p class="text-muted mb-0">{{ t('dashboard.Vendor_Order_Assignment_Not_Ready', 'A driver can be assigned once the vendor marks this order ready for delivery.') }}</p>
                @elseif ($vendorOrder->status === 'delivered')
                    <p class="text-muted mb-0">{{ t('dashboard.Vendor_Order_Assignment_Delivered', 'This order has been delivered, so a driver can no longer be assigned.') }}</p>
                @elseif ($vendorOrder->status === 'out_for_delivery')
                    <p class="text-muted mb-0">{{ t('dashboard.Vendor_Order_Assignment_In_Custody', 'This shipment is in delivery custody. If delivery failed after pickup, the driver must return it to the vendor before a new driver can be assigned.') }}</p>
                @elseif ($assignable && $awaitingPayment)
                    {{-- E2-MVP batch 05: an online order is assigned only once the admin marked it paid. --}}
                    <p class="text-warning mb-0" data-awaiting-payment>{{ t('dashboard.Delivery_Ops_Awaiting_Payment', 'This online order is not marked paid yet, so it cannot be assigned or delivered.') }}</p>
                @elseif ($canAssign && $assignable)
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

        {{-- E2-MVP batch 04: the admin delivery operations: a driver no longer eligible, cancelling before pickup, recording
             a failed delivery after pickup (the custody then closes only through the return confirmation above). --}}
        @php
            $admin = auth('admin')->user();
            $canCancel = ($admin?->isSuperAdmin() || $admin?->hasAbility('orders.edit'))
                && in_array($vendorOrder->status, \App\Http\Controllers\Dashboard\VendorOrderManagementController::CANCELLABLE_STATUSES, true)
                && ! in_array($assignment?->status, ['picked_up', 'out_for_delivery'], true);
            $canRecordFailure = ($admin?->isSuperAdmin() || $admin?->hasAbility('vendor-orders.record-delivery-failure'))
                && $vendorOrder->status === 'out_for_delivery'
                && in_array($assignment?->status, ['picked_up', 'out_for_delivery'], true);
        @endphp
        @if ($driverNotEligible || $adminCancellation || $canCancel || $canRecordFailure)
            <div class="card" data-delivery-operations>
                <div class="card-body">
                    @if ($driverNotEligible)
                        <div class="alert alert-danger" data-driver-not-eligible>{{ t('dashboard.Delivery_Ops_Driver_Not_Eligible', 'This driver is no longer approved or active. Reassign the task before pickup, or record the failure and confirm the return after pickup.') }}</div>
                    @endif

                    @if ($adminCancellation)
                        <div class="alert alert-secondary" data-admin-cancellation>
                            <div class="fw-semibold">{{ t('dashboard.Delivery_Ops_Cancelled_By_Admin', 'Cancelled by administration') }} — {{ $adminCancellation->created_at?->format('Y-m-d H:i') }}</div>
                            <div class="mt-1 text-break" dir="auto">{{ $adminCancellation->note }}</div>
                        </div>
                    @endif

                    @if ($canRecordFailure)
                        <form action="{{ route('dashboard.vendor-orders.record-failure', $vendorOrder) }}" method="POST" data-record-failure>
                            @csrf
                            @method('PATCH')
                            <h6>{{ t('dashboard.Record_Delivery_Failure', 'Record Delivery Failure') }}</h6>
                            <div class="mb-2">
                                <label class="form-label" for="failure_code">{{ t('dashboard.Custody_Reason', 'Reason') }}</label>
                                <select id="failure_code" name="failure_code" class="form-select" required>
                                    <option value=""></option>
                                    @foreach ($failureReasons as $code => $label)
                                        <option value="{{ $code }}" @selected(old('failure_code') === $code)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="failure_reason">{{ t('dashboard.Delivery_Ops_Failure_Note', 'Administration note') }}</label>
                                <textarea id="failure_reason" name="failure_reason" class="form-control" rows="2" maxlength="1000" required>{{ old('failure_reason') }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-outline-danger w-100">{{ t('dashboard.Record_Delivery_Failure', 'Record Delivery Failure') }}</button>
                        </form>
                    @endif

                    @if ($canCancel)
                        <form action="{{ route('dashboard.vendor-orders.cancel', $vendorOrder) }}" method="POST" data-cancel-vendor-order>
                            @csrf
                            @method('PATCH')
                            <h6>{{ t('dashboard.Delivery_Ops_Cancel_Title', 'Cancel this vendor order') }}</h6>
                            <p class="text-muted small">{{ t('dashboard.Delivery_Ops_Cancel_Notice', 'The vendor order is cancelled and its active delivery task ends. Stock deducted after the vendor accepted is not restored automatically, and no refund is made automatically.') }}</p>
                            <div class="mb-2">
                                <label class="form-label" for="cancel_reason">{{ t('dashboard.Delivery_Ops_Cancel_Reason', 'Cancellation reason') }}</label>
                                <textarea id="cancel_reason" name="cancel_reason" class="form-control" rows="2" maxlength="1000" required>{{ old('cancel_reason') }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-danger w-100">{{ t('dashboard.Delivery_Ops_Cancel_Submit', 'Cancel vendor order') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

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
