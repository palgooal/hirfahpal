<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VendorOrderManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAbility($request, 'vendor-orders.view');

        $vendorOrders = VendorOrder::query()
            ->with(['order.customer', 'vendor.profile', 'deliveryDriver', 'deliveryAssignment.deliveryDriver'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('number', 'like', '%'.$search.'%')
                        ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('number', 'like', '%'.$search.'%'))
                        ->orWhereHas('vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('vendor.profile', fn ($profileQuery) => $profileQuery->where('store_name', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->boolean('needs_attention'), function ($query) {
                $query->where('status', 'pending')
                    ->whereNotNull('vendor_response_due_at')
                    ->where('vendor_response_due_at', '<=', now()->addHours(3));
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('dashboard.vendor-orders.index', [
            'vendorOrders' => $vendorOrders,
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAbility($request, 'vendor-orders.create');

        return view('dashboard.vendor-orders.create', [
            'orders' => Order::query()->with('customer')->latest()->limit(100)->get(),
            'vendors' => Vendor::query()->with('profile')->orderBy('name')->get(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAbility($request, 'vendor-orders.create');

        $data = $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'vendor_id' => ['required', 'exists:vendors,id'],
            'number' => ['nullable', 'string', 'max:255', 'unique:vendor_orders,number'],
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'commission_amount' => ['nullable', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'vendor_response_due_at' => ['nullable', 'date'],
            'rejection_reason' => ['nullable', 'required_if:status,rejected', 'string', 'max:1000'],
        ]);

        $timestamps = $this->timestampsForStatus($data['status']);

        $vendorOrder = VendorOrder::create(array_merge([
            'number' => ($data['number'] ?? null) ?: $this->uniqueNumber(),
            'order_id' => $data['order_id'],
            'vendor_id' => $data['vendor_id'],
            'status' => $data['status'],
            'subtotal' => $data['subtotal'],
            'delivery_fee' => $data['delivery_fee'] ?? 0,
            'commission_amount' => $data['commission_amount'] ?? 0,
            'total' => $data['total'],
            'vendor_response_due_at' => $data['vendor_response_due_at'] ?? null,
            'rejection_reason' => $data['status'] === 'rejected' ? $data['rejection_reason'] : null,
        ], $timestamps));

        return redirect()
            ->route('dashboard.vendor-orders.show', $vendorOrder)
            ->with('success', t('dashboard.Vendor_order_created_successfully', 'Vendor order created successfully.'));
    }

    public function show(Request $request, VendorOrder $vendorOrder): View
    {
        $this->authorizeAbility($request, 'vendor-orders.view');

        $vendorOrder->load([
            'order.customer',
            'order.address',
            'vendor.profile',
            'items.product',
            'deliveryAssignment.deliveryDriver.profile',
            'deliveryDriver.profile',
        ]);

        $drivers = DeliveryDriver::query()
            ->with('profile')
            ->where('status', 'active')
            ->whereHas('profile', function ($query) {
                $query->where('approval_status', 'approved')
                    ->where('is_available', true);
            })
            ->orderBy('name')
            ->get();

        return view('dashboard.vendor-orders.show', [
            'vendorOrder' => $vendorOrder,
            'drivers' => $drivers,
            'statuses' => $this->statuses(),
        ]);
    }

    public function assignDriver(Request $request, VendorOrder $vendorOrder): RedirectResponse
    {
        $this->authorizeAbility($request, 'vendor-orders.assign-driver');

        $data = $request->validate([
            'delivery_driver_id' => [
                'required',
                Rule::exists('delivery_drivers', 'id')->where('status', 'active'),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_if(in_array($vendorOrder->status, ['rejected', 'cancelled', 'completed'], true), 422);

        DB::transaction(function () use ($request, $vendorOrder, $data) {
            DeliveryAssignment::updateOrCreate(
                ['vendor_order_id' => $vendorOrder->id],
                [
                    'delivery_driver_id' => $data['delivery_driver_id'],
                    'assigned_by' => $request->user('admin')->id,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                    'notes' => $data['notes'] ?? null,
                ]
            );

            $vendorOrder->update([
                'delivery_driver_id' => $data['delivery_driver_id'],
                'status' => $vendorOrder->status === 'ready_for_delivery' ? 'assigned' : $vendorOrder->status,
            ]);
        });

        return back()->with('success', t('dashboard.Driver_assigned_successfully', 'Delivery driver assigned successfully.'));
    }

    private function authorizeAbility(Request $request, string $ability): void
    {
        $admin = $request->user('admin');

        abort_unless($admin?->isSuperAdmin() || $admin?->hasAbility($ability), 403);
    }

    private function statuses(): array
    {
        return [
            'pending' => t('dashboard.Pending', 'Pending'),
            'accepted' => t('dashboard.Accepted', 'Accepted'),
            'preparing' => t('dashboard.Preparing', 'Preparing'),
            'ready_for_delivery' => t('dashboard.Ready_for_delivery', 'Ready for delivery'),
            'assigned' => t('dashboard.Assigned', 'Assigned'),
            'out_for_delivery' => t('dashboard.Out_for_delivery', 'Out for delivery'),
            'delivered' => t('dashboard.Delivered', 'Delivered'),
            'completed' => t('dashboard.Completed', 'Completed'),
            'rejected' => t('dashboard.Rejected', 'Rejected'),
            'cancelled' => t('dashboard.Cancelled', 'Cancelled'),
        ];
    }

    private function uniqueNumber(): string
    {
        do {
            $number = 'VO-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (VendorOrder::where('number', $number)->exists());

        return $number;
    }

    /**
     * These timestamps mirror the manually selected state without advancing
     * other domains such as payment, delivery confirmation or parent order.
     */
    private function timestampsForStatus(string $status): array
    {
        $now = now();

        return match ($status) {
            'accepted', 'preparing' => ['accepted_at' => $now],
            'ready_for_delivery', 'assigned', 'out_for_delivery' => ['accepted_at' => $now, 'ready_at' => $now],
            'delivered' => ['accepted_at' => $now, 'ready_at' => $now, 'delivered_at' => $now],
            'completed' => ['accepted_at' => $now, 'ready_at' => $now, 'delivered_at' => $now, 'completed_at' => $now],
            'rejected' => ['rejected_at' => $now],
            default => [],
        };
    }
}
