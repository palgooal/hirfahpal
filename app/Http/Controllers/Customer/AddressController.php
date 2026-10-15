<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerAddressRequest;
use App\Models\CustomerAddress;
use App\Models\Governorate;
use App\Support\Storefront\BrowserResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function index(Request $request): View
    {
        $editingAddress = $request->filled('edit') ? $request->user('customer')->addresses()->findOrFail($request->input('edit')) : null;

        return view('pages.customer-addresses', [
            'editingAddress' => $editingAddress,
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

    public function store(StoreCustomerAddressRequest $request): JsonResponse|RedirectResponse
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

        return BrowserResponse::saved($request, $address->load(['governorate', 'city']), 201, 'customer.addresses');
    }

    public function update(StoreCustomerAddressRequest $request, CustomerAddress $address): JsonResponse|RedirectResponse
    {
        $this->authorizeAddress($request, $address);

        DB::transaction(function () use ($request, $address): void {
            $data = $request->validated();

            if ($data['is_default'] ?? false) {
                $request->user('customer')->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            }

            $address->update($data);
        });

        return BrowserResponse::saved($request, $address->fresh(['governorate', 'city']), 200, 'customer.addresses');
    }

    public function destroy(Request $request, CustomerAddress $address): JsonResponse|RedirectResponse
    {
        $this->authorizeAddress($request, $address);
        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $request->user('customer')->addresses()->oldest()->first()?->update(['is_default' => true]);
        }

        return BrowserResponse::saved($request, ['message' => 'Address deleted.']);
    }

    private function authorizeAddress(Request $request, CustomerAddress $address): void
    {
        abort_unless((int) $address->customer_id === (int) $request->user('customer')->id, 403);
    }
}
