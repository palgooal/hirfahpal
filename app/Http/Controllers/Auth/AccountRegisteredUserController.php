<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CreateAccountUser;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Auth\AccountGuard;
use App\Support\Storefront\GuestCartMerger;
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
        ]);
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
