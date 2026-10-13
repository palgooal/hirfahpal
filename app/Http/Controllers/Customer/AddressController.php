<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerAddressRequest;
use App\Models\CustomerAddress;
use App\Models\Governorate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function index(Request $request): View
    {
        return view('pages.customer-addresses', [
            'addresses' => $request->user('customer')
                ->addresses()
                ->with(['governorate', 'city'])
                ->latest('is_default')
                ->latest()
                ->get(),
            'governorates' => Governorate::query()
                ->where('status', 'active')
                ->with(['cities' => fn ($query) => $query->where('status', 'active')->orderBy('name')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreCustomerAddressRequest $request): JsonResponse
    {
        $address = DB::transaction(function () use ($request): CustomerAddress {
            $customer = $request->user('customer');
            $data = $request->validated();

            if (($data['is_default'] ?? false) || $customer->addresses()->doesntExist()) {
                $customer->addresses()->update(['is_default' => false]);
                $data['is_default'] = true;
            }

            return $customer->addresses()->create($data);
        });

        return response()->json($address->load(['governorate', 'city']), 201);
    }

    public function update(StoreCustomerAddressRequest $request, CustomerAddress $address): JsonResponse
    {
        $this->authorizeAddress($request, $address);

        DB::transaction(function () use ($request, $address): void {
            $data = $request->validated();

            if ($data['is_default'] ?? false) {
                $request->user('customer')->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            }

            $address->update($data);
        });

        return response()->json($address->fresh(['governorate', 'city']));
    }

    public function destroy(Request $request, CustomerAddress $address): JsonResponse
    {
        $this->authorizeAddress($request, $address);
        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $request->user('customer')->addresses()->oldest()->first()?->update(['is_default' => true]);
        }

        return response()->json(['message' => 'Address deleted.']);
    }

    private function authorizeAddress(Request $request, CustomerAddress $address): void
    {
        abort_unless((int) $address->customer_id === (int) $request->user('customer')->id, 403);
    }
}
