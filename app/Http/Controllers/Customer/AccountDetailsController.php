<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\UpdateAccountDetailsRequest;
use App\Http\Requests\Customer\UpdatePasswordRequest;
use Illuminate\Http\JsonResponse;
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

    public function update(UpdateAccountDetailsRequest $request): JsonResponse
    {
        $customer = $request->user('customer');
        $customer->update($request->validated());

        return response()->json([
            'message' => 'Account details updated.',
            'customer' => $customer->fresh(),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $request->user('customer')->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        return response()->json([
            'message' => 'Password updated.',
        ]);
    }
}
