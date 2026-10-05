<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAbility($request, 'customers.view');

        $customers = Customer::query()
            ->withCount(['orders', 'addresses', 'reviews'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.customers.index', [
            'customers' => $customers,
            'statuses' => $this->statuses(),
        ]);
    }

    public function show(Request $request, Customer $customer): View
    {
        $this->authorizeAbility($request, 'customers.view');

        $customer->load([
            'addresses.city',
            'orders' => fn ($query) => $query->latest()->limit(10),
            'reviews' => fn ($query) => $query->latest()->limit(10),
        ]);

        return view('dashboard.customers.show', [
            'customer' => $customer,
            'statuses' => $this->statuses(),
        ]);
    }

    public function updateStatus(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorizeAbility($request, 'customers.edit');

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
        ]);

        $customer->update($data);

        return back()->with('success', t('dashboard.Customer_status_updated', 'Customer status updated successfully.'));
    }

    private function statuses(): array
    {
        return [
            'active' => t('dashboard.Active', 'Active'),
            'pending' => t('dashboard.Pending', 'Pending'),
            'blocked' => t('dashboard.Blocked', 'Blocked'),
        ];
    }

    private function authorizeAbility(Request $request, string $ability): void
    {
        $admin = $request->user('admin');

        abort_unless($admin?->isSuperAdmin() || $admin?->hasAbility($ability), 403);
    }
}
