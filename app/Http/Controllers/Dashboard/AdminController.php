<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\RoleUser;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('view', Admin::class);

        $admins = Admin::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($subQuery) use ($request) {
                    $subQuery->where('name', 'like', '%' . $request->search . '%')
                        ->orWhere('email', 'like', '%' . $request->search . '%')
                        ->orWhere('phone', 'like', '%' . $request->search . '%');
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('dashboard.admins.index', compact('admins'));
    }

    public function create()
    {
        $this->authorize('create', Admin::class);

        return view('dashboard.admins.create', [
            'admin' => new Admin(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Admin::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:admins,email'],
            'phone' => ['required', 'string', 'max:30', 'unique:admins,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in(['active', 'pending', 'blocked'])],
            'super_admin' => ['nullable', 'boolean'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string'],
        ]);

        DB::transaction(function () use ($request, $data) {
            $admin = Admin::create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'],
                'super_admin' => $request->user('admin')->can('super', Admin::class)
                    ? $request->boolean('super_admin')
                    : false,
            ]);

            $this->syncAbilities($admin, $data['abilities'] ?? []);
        });

        return redirect()
            ->route('dashboard.admins.index')
            ->with('success', t('dashboard.Admin_created_successfully', 'Admin created successfully.'));
    }

    public function edit(Admin $admin)
    {
        $this->authorize('edit', Admin::class);

        return view('dashboard.admins.edit', compact('admin'));
    }

    public function update(Request $request, Admin $admin)
    {
        $this->authorize('edit', Admin::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($admin->id)],
            'phone' => ['required', 'string', 'max:30', Rule::unique('admins', 'phone')->ignore($admin->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in(['active', 'pending', 'blocked'])],
            'super_admin' => ['nullable', 'boolean'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string'],
        ]);

        DB::transaction(function () use ($request, $admin, $data) {
            $admin->fill([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
                'status' => $data['status'],
            ]);

            if (! empty($data['password'])) {
                $admin->password = Hash::make($data['password']);
            }

            if ($request->user('admin')->can('super', Admin::class)) {
                $admin->super_admin = $request->boolean('super_admin');
            }

            $admin->save();

            $this->syncAbilities($admin, $data['abilities'] ?? []);
        });

        return redirect()
            ->route('dashboard.admins.edit', $admin)
            ->with('success', t('dashboard.Admin_updated_successfully', 'Admin updated successfully.'));
    }

    public function destroy(Admin $admin)
    {
        $this->authorize('delete', Admin::class);

        if (auth('admin')->id() === $admin->id) {
            return redirect()
                ->route('dashboard.admins.index')
                ->with('danger', t('dashboard.Cannot_delete_current_admin', 'You cannot delete the currently signed-in admin.'));
        }

        $admin->delete();

        return redirect()
            ->route('dashboard.admins.index')
            ->with('success', t('dashboard.Admin_deleted_successfully', 'Admin deleted successfully.'));
    }

    private function syncAbilities(Admin $admin, array $abilities): void
    {
        $abilities = collect($abilities)
            ->intersect($this->availableAbilities())
            ->values()
            ->all();

        RoleUser::where('user_id', $admin->id)
            ->whereNotIn('role_name', $abilities)
            ->delete();

        foreach ($abilities as $ability) {
            RoleUser::updateOrCreate(
                ['user_id' => $admin->id, 'role_name' => $ability],
                ['ability' => 'allow']
            );
        }
    }

    private function availableAbilities(): array
    {
        return collect(app('abilities'))
            ->flatMap(function (array $group, string $groupName) {
                return collect($group)
                    ->keys()
                    ->reject(fn ($ability) => $ability === 'name')
                    ->map(fn ($ability) => $groupName . '.' . $ability);
            })
            ->values()
            ->all();
    }
}
