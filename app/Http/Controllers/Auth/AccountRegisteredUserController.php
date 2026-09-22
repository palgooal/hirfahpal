<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CreateAccountUser;
use App\Http\Controllers\Controller;
use App\Support\Auth\AccountGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccountRegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.accounts.register', [
            'account' => AccountGuard::get($request->route('account_type')),
        ]);
    }

    public function store(Request $request, CreateAccountUser $createAccountUser): RedirectResponse
    {
        $accountType = $request->route('account_type');
        $account = AccountGuard::get($accountType);
        $user = $createAccountUser->create($accountType, $request->all());

        Auth::guard($account['guard'])->login($user);
        $request->session()->regenerate();

        return redirect()->route($account['dashboard_route']);
    }
}
