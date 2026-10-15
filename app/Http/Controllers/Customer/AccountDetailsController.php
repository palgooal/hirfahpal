<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\UpdateAccountDetailsRequest;
use App\Http\Requests\Customer\UpdatePasswordRequest;
use App\Support\Storefront\BrowserResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AccountDetailsController extends Controller
{
    public function edit(): View
    {
        return view('pages.customer-account-details', [
            'customer' => request()->user('customer'),
        ]);
    }

    public function update(UpdateAccountDetailsRequest $request): JsonResponse|RedirectResponse
    {
        $customer = $request->user('customer');
        $customer->update($request->validated());

        return BrowserResponse::saved($request, [
            'message' => 'Account details updated.',
            'customer' => $customer->fresh(),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse|RedirectResponse
    {
        $request->user('customer')->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        return BrowserResponse::saved($request, [
            'message' => 'Password updated.',
        ]);
    }
}
