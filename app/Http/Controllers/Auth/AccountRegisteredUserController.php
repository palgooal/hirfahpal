<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CreateAccountUser;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Governorate;
use App\Support\Auth\AccountGuard;
use App\Support\Storefront\GuestCartMerger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccountRegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        $account = AccountGuard::get($request->route('account_type'));

        return view($account['register_view'] ?? 'auth.accounts.register', [
            'account' => $account,
            // E2-MVP batch 01: the delivery driver's service area, the active governorates with their active cities.
            ...($request->route('account_type') === 'delivery_driver' ? ['governorates' => $this->activeGovernorates()] : []),
        ]);
    }

    /** The approved hierarchy (cities.governorate_id), active values only, as the server validates them (LocationPair). */
    private function activeGovernorates(): Collection
    {
        return Governorate::query()
            ->where('status', 'active')
            ->with(['cities' => fn ($query) => $query->where('status', 'active')->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function store(Request $request, CreateAccountUser $createAccountUser, GuestCartMerger $guestCarts): RedirectResponse
    {
        $accountType = $request->route('account_type');
        $account = AccountGuard::get($accountType);
        $user = $createAccountUser->create($accountType, $request->all());

        if ($accountType === 'vendor') {
            return redirect()
                ->route('vendor.login')
                ->with('status', __('auth.vendor_registration_pending'));
        }

        // CUSTOMER-AUTH-A1: the guest cart is found by the session id before regenerate() replaces it.
        $guestCartId = $user instanceof Customer ? $guestCarts->guestCartId($request->session()->getId()) : null;

        Auth::guard($account['guard'])->login($user);
        $request->session()->regenerate();

        if ($user instanceof Customer) {
            $guestCarts->mergeAfterAuthentication($guestCartId, $user);
        }

        return redirect()->route($account['dashboard_route']);
    }
}
