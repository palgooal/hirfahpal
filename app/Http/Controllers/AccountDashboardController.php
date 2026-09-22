<?php

namespace App\Http\Controllers;

use App\Support\Auth\AccountGuard;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('accounts.dashboard', [
            'account' => AccountGuard::get($request->route('account_type')),
        ]);
    }
}
