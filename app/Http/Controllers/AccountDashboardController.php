<?php

namespace App\Http\Controllers;

use App\Support\Auth\AccountGuard;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $account = AccountGuard::get($request->route('account_type'));

        $user = $request->user($account['guard']);

        return view($account['dashboard_view'] ?? 'accounts.dashboard', [
            'account' => $account,
            'user' => $user,
            'latestOrder' => $account['guard'] === 'customer' ? $user->orders()->with(['vendorOrders.vendor.profile', 'items'])->latest('id')->first() : null,
            'favoriteCount' => $account['guard'] === 'customer' ? $user->favorites()->count() : 0,
        ]);
    }
}
