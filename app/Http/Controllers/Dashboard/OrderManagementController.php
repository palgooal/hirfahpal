<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAbility($request, 'orders.view');

        $orders = Order::query()
            ->with(['customer'])
            ->withCount(['vendorOrders', 'items'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where('number', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$search.'%')->orWhere('phone', 'like', '%'.$search.'%'));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->input('payment_status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.orders.index', [
            'orders' => $orders,
            'statuses' => $this->statuses(),
            'paymentStatuses' => $this->paymentStatuses(),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorizeAbility($request, 'orders.view');

        $order->load([
            'customer',
            'address.city',
            'items.product',
            'vendorOrders.vendor.profile',
            'vendorOrders.deliveryAssignment.deliveryDriver',
            'payment',
            'reviews',
        ]);

        return view('dashboard.orders.show', [
            'order' => $order,
            'statuses' => $this->statuses(),
            'paymentStatuses' => $this->paymentStatuses(),
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeAbility($request, 'orders.edit');

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
        ]);

        $order->update([
            'status' => $data['status'],
            'completed_at' => $data['status'] === 'completed' ? ($order->completed_at ?? now()) : null,
        ]);

        return back()->with('success', t('dashboard.Order_status_updated', 'Order status updated successfully.'));
    }

    public function updatePaymentStatus(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeAbility($request, 'orders.edit');

        $data = $request->validate([
            'payment_status' => ['required', Rule::in(array_keys($this->paymentStatuses()))],
        ]);

        $order->update($data);

        return back()->with('success', t('dashboard.Payment_status_updated', 'Payment status updated successfully.'));
    }

    private function statuses(): array
    {
        return [
            'pending' => t('dashboard.Pending', 'Pending'),
            'processing' => t('dashboard.Processing', 'Processing'),
            'completed' => t('dashboard.Completed', 'Completed'),
            'partially_cancelled' => t('dashboard.Partially_cancelled', 'Partially cancelled'),
            'cancelled' => t('dashboard.Cancelled', 'Cancelled'),
        ];
    }

    private function paymentStatuses(): array
    {
        return [
            'pending' => t('dashboard.Pending', 'Pending'),
            'paid' => t('dashboard.Paid', 'Paid'),
            'failed' => t('dashboard.Failed', 'Failed'),
            'refunded' => t('dashboard.Refunded', 'Refunded'),
            'partially_refunded' => t('dashboard.Partially_refunded', 'Partially refunded'),
        ];
    }

    private function authorizeAbility(Request $request, string $ability): void
    {
        $admin = $request->user('admin');

        abort_unless($admin?->isSuperAdmin() || $admin?->hasAbility($ability), 403);
    }
}
