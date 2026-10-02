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

        return view($account['dashboard_view'] ?? 'accounts.dashboard', [
            'account' => $account,
            'user' => $request->user($account['guard']),
        ]);
    }
}
