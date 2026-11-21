<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\DeliveryDriver;
use App\Models\DriverCashSettlement;
use App\Support\Orders\DriverCash;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * E2-MVP batch 05: the cash an admin receives from a driver (delivery-drivers.settle-cash). A settlement records the
 * amount actually received (partial allowed, never above the balance); a correction is a new reversal entry cancelling
 * one settlement, at most once (unique reverses_id). Entries are never updated or deleted. Both lock the driver's row
 * first, so concurrent entries for one driver are serialized and the balance read under the lock is the current one.
 */
class DriverCashController extends Controller
{
    public function store(Request $request, DeliveryDriver $deliveryDriver): RedirectResponse
    {
        $this->authorizeAbility($request, 'delivery-drivers.settle-cash');
        $data = $request->validate([
            'amount' => ['required', 'regex:'.DriverCash::AMOUNT_PATTERN],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $amount = DriverCash::cents($data['amount']);
        $adminId = $request->user('admin')->id;

        DB::transaction(function () use ($deliveryDriver, $data, $amount, $adminId): void {
            DeliveryDriver::query()->whereKey($deliveryDriver->id)->lockForUpdate()->firstOrFail();
            $balance = DriverCash::summary($deliveryDriver->id)['balance'];

            if ($amount <= 0 || $amount > $balance) {
                throw ValidationException::withMessages([
                    'amount' => t('dashboard.Delivery_Ops_Settlement_Exceeds', 'The amount must be more than zero and not above the balance the driver holds.'),
                ]);
            }

            DriverCashSettlement::query()->create([
                'delivery_driver_id' => $deliveryDriver->id,
                'kind' => DriverCashSettlement::SETTLEMENT,
                'amount' => DriverCash::format($amount),
                'recorded_by' => $adminId,
                'note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
            ]);
        });

        return back()->with('success', t('dashboard.Delivery_Ops_Settlement_Recorded', 'The cash received from the driver was recorded.'));
    }

    public function reverse(Request $request, DeliveryDriver $deliveryDriver, DriverCashSettlement $settlement): RedirectResponse
    {
        $this->authorizeAbility($request, 'delivery-drivers.settle-cash');
        abort_unless((int) $settlement->delivery_driver_id === (int) $deliveryDriver->id, 404);
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        $adminId = $request->user('admin')->id;

        try {
            DB::transaction(function () use ($deliveryDriver, $settlement, $data, $adminId): void {
                DeliveryDriver::query()->whereKey($deliveryDriver->id)->lockForUpdate()->firstOrFail();
                $original = DriverCashSettlement::query()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();

                // Only a settlement is corrected, and only once; a reversal is never reversed.
                if ($original->kind !== DriverCashSettlement::SETTLEMENT
                    || DriverCashSettlement::query()->where('reverses_id', $original->id)->exists()) {
                    $this->refuseReversal();
                }

                DriverCashSettlement::query()->create([
                    'delivery_driver_id' => $deliveryDriver->id,
                    'kind' => DriverCashSettlement::REVERSAL,
                    // The amount of the entry it cancels, never typed.
                    'amount' => $original->amount,
                    'reverses_id' => $original->id,
                    'recorded_by' => $adminId,
                    'note' => trim($data['note']),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent correction of the same settlement committed first.
            $this->refuseReversal();
        }

        return back()->with('success', t('dashboard.Delivery_Ops_Settlement_Reversed', 'The settlement was reversed.'));
    }

    private function refuseReversal(): never
    {
        throw ValidationException::withMessages([
            'note' => t('dashboard.Delivery_Ops_Reversal_Not_Allowed', 'Only a settlement not reversed yet can be reversed.'),
        ]);
    }

    private function authorizeAbility(Request $request, string $ability): void
    {
        $admin = $request->user('admin');

        abort_unless($admin?->isSuperAdmin() || $admin?->hasAbility($ability), 403);
    }
}
