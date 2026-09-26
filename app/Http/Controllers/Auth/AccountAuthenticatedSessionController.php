<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Auth\AccountGuard;
use App\Support\Auth\GuardLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccountAuthenticatedSessionController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.accounts.login', [
            'account' => AccountGuard::get($request->route('account_type')),
        ]);
    }

    public function store(Request $request, GuardLoginService $guardLoginService): RedirectResponse
    {
        $account = AccountGuard::get($request->route('account_type'));

        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = $guardLoginService->authenticate(
            $account['model'],
            $request->string('login')->toString(),
            $request->string('password')->toString(),
            throttleScope: $account['guard'],
        );

        Auth::guard($account['guard'])->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route($account['dashboard_route']));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $account = AccountGuard::get($request->route('account_type'));

        Auth::guard($account['guard'])->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($account['route'].'.login');
    }
}
