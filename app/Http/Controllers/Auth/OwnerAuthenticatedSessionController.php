<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Support\Auth\GuardLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OwnerAuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.owner.login');
    }

    public function store(Request $request, GuardLoginService $guardLoginService): RedirectResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $owner = $guardLoginService->authenticate(
            Owner::class,
            $request->string('login')->toString(),
            $request->string('password')->toString()
        );

        Auth::guard('owner')->login($owner, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('owner.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('owner')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('owner.login');
    }
}
