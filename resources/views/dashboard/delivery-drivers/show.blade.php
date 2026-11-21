<x-dashboard-layout>
    <x-slot:breadcrumbs><li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li><li class="breadcrumb-item"><a href="{{ route('dashboard.delivery-drivers.index') }}">{{ t('dashboard.Delivery_Drivers', 'Delivery Drivers') }}</a></li><li class="breadcrumb-item">{{ $deliveryDriver->name }}</li></x-slot:breadcrumbs>
    <div class="col-span-12 lg:col-span-4"><div class="card"><div class="card-body"><h4>{{ $deliveryDriver->name }}</h4><p class="text-muted">{{ $deliveryDriver->phone }}<br>{{ $deliveryDriver->email }}</p>{{-- E2-MVP batch 01: what the driver applied with, for the review. --}}<dl class="mb-3" data-driver-application><dt>{{ t('dashboard.Approval_status', 'Approval status') }}</dt><dd>{{ $deliveryDriver->profile?->approval_status ?? '—' }}</dd><dt>{{ t('dashboard.Governorate', 'Governorate') }}</dt><dd>{{ $deliveryDriver->profile?->governorate?->name ?? '—' }}</dd><dt>{{ t('dashboard.City', 'City') }}</dt><dd>{{ $deliveryDriver->profile?->city?->name ?? '—' }}</dd><dt>{{ t('dashboard.Vehicle_type', 'Vehicle type') }}</dt><dd>{{ $deliveryDriver->profile?->vehicle_type ?? '—' }}</dd>@if ($deliveryDriver->profile?->approval_status === 'rejected')<dt>{{ t('dashboard.Rejection_reason', 'Rejection reason') }}</dt><dd>{{ $deliveryDriver->profile->rejection_reason }}</dd>@endif</dl><form method="POST" action="{{ route('dashboard.delivery-drivers.availability', $deliveryDriver) }}" class="row g-2">@csrf @method('PATCH')<div class="col-12"><select name="status" class="form-select"><option value="active" @selected($deliveryDriver->status === 'active')>{{ t('dashboard.Active', 'Active') }}</option><option value="pending" @selected($deliveryDriver->status === 'pending')>{{ t('dashboard.Pending', 'Pending') }}</option><option value="blocked" @selected($deliveryDriver->status === 'blocked')>{{ t('dashboard.Blocked', 'Blocked') }}</option></select></div><div class="col-12"><div class="form-check"><input type="hidden" name="is_available" value="0"><input class="form-check-input" type="checkbox" name="is_available" value="1" @checked($deliveryDriver->profile?->is_available)><label class="form-check-label">{{ t('dashboard.Available', 'Available') }}</label></div></div><div class="col-12"><button class="btn btn-primary">{{ t('dashboard.Save', 'Save') }}</button></div></form></div></div></div>
    <div class="col-span-12 lg:col-span-8"><div class="card"><div class="card-body d-flex gap-2"><form method="POST" action="{{ route('dashboard.delivery-drivers.approve', $deliveryDriver) }}">@csrf @method('PATCH')<button class="btn btn-success">{{ t('dashboard.Approve', 'Approve') }}</button></form><form method="POST" action="{{ route('dashboard.delivery-drivers.reject', $deliveryDriver) }}" class="d-flex gap-2">@csrf @method('PATCH')<input name="rejection_reason" class="form-control" placeholder="{{ t('dashboard.Rejection_reason', 'Rejection reason') }}"><button class="btn btn-danger">{{ t('dashboard.Reject', 'Reject') }}</button></form></div></div>{{-- E2-MVP batch 04: every task the driver still holds, linked to its order page; nothing is unassigned automatically. --}}<div class="card" data-active-tasks><div class="card-header"><h5>{{ t('dashboard.Delivery_Ops_Active_Tasks', 'Active and in-custody tasks') }}</h5></div><div class="card-body">@if ($notEligible && $activeTasks->isNotEmpty())<div class="alert alert-danger" data-driver-not-eligible>{{ t('dashboard.Delivery_Ops_Driver_Not_Eligible', 'This driver is no longer approved or active. Reassign the task before pickup, or record the failure and confirm the return after pickup.') }}</div>@endif @forelse ($activeTasks as $task)<div class="d-flex justify-content-between border-bottom py-2"><a href="{{ route('dashboard.vendor-orders.show', $task->vendor_order_id) }}" data-active-task="{{ $task->id }}">{{ $task->vendorOrder?->number }}</a><span>{{ $task->status }}</span></div>@empty<p class="text-muted mb-0">{{ t('dashboard.Delivery_Ops_No_Active_Tasks', 'No active tasks.') }}</p>@endforelse</div></div>@if ($cash !== null)
{{-- E2-MVP batch 05: the cash the driver collected and still holds (computed, never stored), the settlements and their reversals. --}}
<div class="card" data-driver-cash>
    <div class="card-header"><h5>{{ t('dashboard.Delivery_Ops_Cash_Custody', 'Cash custody') }}</h5></div>
    <div class="card-body">
        <div class="d-flex justify-content-between border-bottom py-2"><span>{{ t('dashboard.Delivery_Ops_Cash_Collected', 'Collected on delivery') }}</span><span dir="ltr">{{ \App\Support\Orders\DriverCash::format($cash['collected']) }}</span></div>
        <div class="d-flex justify-content-between border-bottom py-2"><span>{{ t('dashboard.Delivery_Ops_Cash_Settled', 'Received by the platform') }}</span><span dir="ltr">{{ \App\Support\Orders\DriverCash::format($cash['settled'] - $cash['reversed']) }}</span></div>
        <div class="d-flex justify-content-between py-2 fw-semibold"><span>{{ t('dashboard.Delivery_Ops_Cash_Balance', 'Balance held by the driver') }}</span><span dir="ltr" data-cash-balance>{{ \App\Support\Orders\DriverCash::format($cash['balance']) }}</span></div>

        @if ($canSettleCash)
            <form method="POST" action="{{ route('dashboard.delivery-drivers.cash-settlements.store', $deliveryDriver) }}" class="row g-2 mt-2" data-cash-settlement-form>
                @csrf
                <div class="col-sm-4"><label class="form-label" for="settlement_amount">{{ t('dashboard.Delivery_Ops_Cash_Amount_Received', 'Amount received') }}</label><input id="settlement_amount" name="amount" inputmode="decimal" dir="ltr" class="form-control" required value="{{ old('amount') }}"></div>
                <div class="col-sm-8"><label class="form-label" for="settlement_note">{{ t('dashboard.Notes', 'Notes') }}</label><input id="settlement_note" name="note" maxlength="1000" class="form-control" value="{{ old('note') }}"></div>
                <div class="col-12"><button class="btn btn-primary">{{ t('dashboard.Delivery_Ops_Cash_Record', 'Record cash received') }}</button></div>
            </form>
        @endif

        <div class="table-responsive mt-3">
            <table class="table" data-cash-entries>
                <tbody>
                    @forelse ($cashEntries as $entry)
                        <tr data-cash-entry="{{ $entry->id }}">
                            <td>{{ $entry->kind === \App\Models\DriverCashSettlement::REVERSAL ? t('dashboard.Delivery_Ops_Cash_Reversal', 'Reversal') : t('dashboard.Delivery_Ops_Cash_Settlement', 'Cash received') }}@if ($entry->reverses_id) (#{{ $entry->reverses_id }})@endif</td>
                            <td dir="ltr">{{ $entry->kind === \App\Models\DriverCashSettlement::REVERSAL ? '+' : '-' }}{{ $entry->amount }}</td>
                            <td>{{ $entry->recorder?->name }} — {{ $entry->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="text-break" dir="auto">{{ $entry->note }}</td>
                            <td>
                                @if ($canSettleCash && $entry->kind === \App\Models\DriverCashSettlement::SETTLEMENT && ! $entry->reversal)
                                    <form method="POST" action="{{ route('dashboard.delivery-drivers.cash-settlements.reverse', [$deliveryDriver, $entry]) }}" class="d-flex gap-2" data-cash-reverse-form>
                                        @csrf
                                        <input name="note" required maxlength="1000" class="form-control form-control-sm" placeholder="{{ t('dashboard.Delivery_Ops_Cash_Reversal_Reason', 'Reason for the correction') }}">
                                        <button class="btn btn-sm btn-outline-danger">{{ t('dashboard.Delivery_Ops_Cash_Reverse', 'Reverse') }}</button>
                                    </form>
                                @elseif ($entry->reversal)
                                    <span class="text-muted">{{ t('dashboard.Delivery_Ops_Cash_Reversed', 'Reversed') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-muted">{{ t('dashboard.No_records_found', 'No records found') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
<div class="card"><div class="card-header"><h5>{{ t('dashboard.Assignments', 'Assignments') }}</h5></div><div class="card-body table-responsive"><table class="table"><tbody>@forelse($deliveryDriver->deliveryAssignments as $assignment)<tr><td>{{ $assignment->vendorOrder?->number }}</td><td>{{ $assignment->status }}</td><td>{{ $assignment->assigned_at?->format('Y-m-d H:i') }}</td></tr>@empty<tr><td class="text-muted">{{ t('dashboard.No_records_found', 'No records found') }}</td></tr>@endforelse</tbody></table></div></div></div>
</x-dashboard-layout>
