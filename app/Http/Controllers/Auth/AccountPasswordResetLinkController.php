<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Auth\AccountGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class AccountPasswordResetLinkController extends Controller
{
    public function create(Request $request): View
    {
        $account = AccountGuard::get($request->route('account_type'));

        return view($account['forgot_password_view'] ?? 'auth.accounts.forgot-password', [
            'account' => $account,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $account = AccountGuard::get($request->route('account_type'));

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::broker($account['broker'])->sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withErrors(['email' => __($status)]);
        }

        return back()->with('status', __($status));
    }
}
