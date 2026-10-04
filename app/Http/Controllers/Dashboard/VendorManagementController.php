<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\City;
use App\Models\Customer;
use App\Models\DeliveryDriver;
use App\Models\Governorate;
use App\Models\User;
use App\Models\Vendor;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VendorManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAbility($request, 'vendors.view');

        $vendors = Vendor::query()
            ->with(['profile.city', 'profile.governorate'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhereHas('profile', function ($profileQuery) use ($search) {
                            $profileQuery->where('store_name', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($request->filled('approval_status'), function ($query) use ($request) {
                $query->whereHas('profile', function ($profileQuery) use ($request) {
                    $profileQuery->where('approval_status', $request->input('approval_status'));
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('dashboard.vendors.index', compact('vendors'));
    }

    public function create(Request $request): View
    {
        $this->authorizeAbility($request, 'vendors.create');

        return view('dashboard.vendors.create', [
            'vendor' => new Vendor,
            'cities' => City::query()->where('status', 'active')->orderBy('name')->get(),
            'governorates' => Governorate::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAbility($request, 'vendors.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', $this->uniqueAcrossAccountTables('email')],
            'phone' => ['required', 'string', 'max:30', $this->uniqueAcrossAccountTables('phone')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in(['active', 'pending', 'blocked'])],
            'store_name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:vendor_profiles,slug'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'governorate_id' => ['nullable', 'exists:governorates,id'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'approval_status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'rejection_reason' => ['nullable', 'required_if:approval_status,rejected', 'string', 'max:1000'],
        ]);

        $vendor = DB::transaction(function () use ($request, $data) {
            $vendor = Vendor::create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'],
            ]);

            $approved = $data['approval_status'] === 'approved';

            $vendor->profile()->create([
                'governorate_id' => $data['governorate_id'] ?? null,
                'city_id' => $data['city_id'] ?? null,
                'store_name' => $data['store_name'],
                'slug' => ($data['slug'] ?? null) ?: $this->uniqueSlug($data['store_name']),
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
                'address_line' => $data['address_line'] ?? null,
                'commission_rate' => $data['commission_rate'] ?? null,
                'approval_status' => $data['approval_status'],
                'approved_at' => $approved ? now() : null,
                'approved_by' => $approved ? $request->user('admin')->id : null,
                'rejection_reason' => $data['approval_status'] === 'rejected' ? $data['rejection_reason'] : null,
            ]);

            return $vendor;
        });

        return redirect()
            ->route('dashboard.vendors.show', $vendor)
            ->with('success', t('dashboard.Vendor_created_successfully', 'Vendor created successfully.'));
    }

    public function show(Request $request, Vendor $vendor): View
    {
        $this->authorizeAbility($request, 'vendors.view');

        $vendor->load([
            'profile.city',
            'profile.governorate',
            'products' => fn ($query) => $query->latest()->limit(10),
            'vendorOrders' => fn ($query) => $query->with('order')->latest()->limit(10),
        ]);

        return view('dashboard.vendors.show', compact('vendor'));
    }

    public function approve(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorizeAbility($request, 'vendors.edit');

        abort_if(! $vendor->profile, 404);

        $vendor->profile()->update([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $request->user('admin')->id,
            'rejection_reason' => null,
        ]);

        $vendor->update(['status' => 'active']);

        return back()->with('success', t('dashboard.Vendor_approved_successfully', 'Vendor approved successfully.'));
    }

    public function reject(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorizeAbility($request, 'vendors.edit');

        abort_if(! $vendor->profile, 404);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $vendor->profile()->update([
            'approval_status' => 'rejected',
            'approved_at' => null,
            'approved_by' => $request->user('admin')->id,
            'rejection_reason' => $data['rejection_reason'],
        ]);

        $vendor->update(['status' => 'blocked']);

        return back()->with('success', t('dashboard.Vendor_rejected_successfully', 'Vendor rejected successfully.'));
    }

    private function authorizeAbility(Request $request, string $ability): void
    {
        $admin = $request->user('admin');

        abort_unless($admin?->isSuperAdmin() || $admin?->hasAbility($ability), 403);
    }

    private function uniqueAcrossAccountTables(string $column): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($column): void {
            if ($value === null || $value === '') {
                return;
            }

            if (
                User::where($column, $value)->exists()
                || Admin::where($column, $value)->exists()
                || Customer::where($column, $value)->exists()
                || Vendor::where($column, $value)->exists()
                || DeliveryDriver::where($column, $value)->exists()
            ) {
                $fail(__('validation.unique', ['attribute' => $attribute]));
            }
        };
    }

    private function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'vendor';
        $slug = $base;
        $counter = 2;

        while (DB::table('vendor_profiles')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
