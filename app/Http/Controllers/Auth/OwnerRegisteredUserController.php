<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CreateNewOwner;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OwnerRegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.owner.register');
    }

    public function store(Request $request, CreateNewOwner $createNewOwner): RedirectResponse
    {
        $owner = $createNewOwner->create($request->all());

        Auth::guard('owner')->login($owner);
        $request->session()->regenerate();

        return redirect()->route('owner.dashboard');
    }
}
