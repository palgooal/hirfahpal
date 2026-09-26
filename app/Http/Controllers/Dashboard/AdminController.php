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
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('view', Admin::class);

        $admins = Admin::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($subQuery) use ($request) {
                    $subQuery->where('name', 'like', '%'.$request->search.'%')
                        ->orWhere('email', 'like', '%'.$request->search.'%')
                        ->orWhere('phone', 'like', '%'.$request->search.'%');
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('dashboard.admins.index', compact('admins'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Admin::class);

        return view('dashboard.admins.create', [
            'admin' => new Admin,
            'grantableAbilities' => $this->grantableAbilities($request->user('admin')),
            'restrictedSelfEdit' => false,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Admin::class);

        $actor = $request->user('admin');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:admins,email'],
            'phone' => ['required', 'string', 'max:30', 'unique:admins,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in(['active', 'pending', 'blocked'])],
            'super_admin' => ['nullable', 'boolean'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', Rule::in($this->availableAbilities())],
        ]);

        $this->denySuperFlagChangeByNonSuper($request, $actor, false);
        $abilities = $this->resolveAbilities($actor, [], $data['abilities'] ?? []);

        DB::transaction(function () use ($request, $actor, $data, $abilities) {
            $admin = Admin::create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'],
                'super_admin' => $actor->isSuperAdmin() && $request->boolean('super_admin'),
            ]);

            $this->syncAbilities($admin, $abilities);
        });

        return redirect()
            ->route('dashboard.admins.index')
            ->with('success', t('dashboard.Admin_created_successfully', 'Admin created successfully.'));
    }

    public function edit(Request $request, Admin $admin)
    {
        $this->authorizeTarget('edit', $admin);

        $actor = $request->user('admin');

        return view('dashboard.admins.edit', [
            'admin' => $admin,
            'grantableAbilities' => $this->grantableAbilities($actor),
            'restrictedSelfEdit' => $this->isRestrictedSelfEdit($actor, $admin),
        ]);
    }

    public function update(Request $request, Admin $admin)
    {
        $this->authorizeTarget('edit', $admin);

        $actor = $request->user('admin');
        $restrictedSelfEdit = $this->isRestrictedSelfEdit($actor, $admin);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($admin->id)],
            'phone' => ['required', 'string', 'max:30', Rule::unique('admins', 'phone')->ignore($admin->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'status' => [$restrictedSelfEdit ? 'sometimes' : 'required', Rule::in(['active', 'pending', 'blocked'])],
            'super_admin' => ['nullable', 'boolean'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', Rule::in($this->availableAbilities())],
        ]);

        // Every privilege check runs before the transaction, so a rejected
        // request leaves the profile, password, status and abilities untouched.
        $this->denySuperFlagChangeByNonSuper($request, $actor, $admin->isSuperAdmin());

        $currentAbilities = $admin->abilityNames();

        if ($restrictedSelfEdit) {
            abort_if(isset($data['status']) && $data['status'] !== $admin->status, 403);
            abort_if(
                $request->has('abilities')
                    && ! $this->sameAbilities($data['abilities'] ?? [], array_intersect($currentAbilities, $this->availableAbilities())),
                403
            );

            $abilities = null;
            $status = $admin->status;
        } else {
            $abilities = $this->resolveAbilities($actor, $currentAbilities, $data['abilities'] ?? []);
            $status = $data['status'];
        }

        $superAdmin = $actor->isSuperAdmin() && $request->has('super_admin')
            ? $request->boolean('super_admin')
            : $admin->isSuperAdmin();

        DB::transaction(function () use ($admin, $data, $status, $superAdmin, $abilities) {
            if (! ($superAdmin && $status === 'active') && $this->isLastActiveSuperAdmin($admin)) {
                throw ValidationException::withMessages([
                    'super_admin' => t('dashboard.Last_active_super_admin', 'At least one active super admin must remain.'),
                ]);
            }

            $admin->fill([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
                'status' => $status,
            ]);

            if (! empty($data['password'])) {
                $admin->password = Hash::make($data['password']);
            }

            $admin->super_admin = $superAdmin;
            $admin->save();

            if ($abilities !== null) {
                $this->syncAbilities($admin, $abilities);
            }
        });

        return redirect()
            ->route('dashboard.admins.edit', $admin)
            ->with('success', t('dashboard.Admin_updated_successfully', 'Admin updated successfully.'));
    }

    public function destroy(Admin $admin)
    {
        $this->authorizeTarget('delete', $admin);

        if (auth('admin')->id() === $admin->id) {
            return redirect()
                ->route('dashboard.admins.index')
                ->with('danger', t('dashboard.Cannot_delete_current_admin', 'You cannot delete the currently signed-in admin.'));
        }

        $deleted = DB::transaction(function () use ($admin) {
            if ($this->isLastActiveSuperAdmin($admin)) {
                return false;
            }

            return (bool) $admin->delete();
        });

        if (! $deleted) {
            return redirect()
                ->route('dashboard.admins.index')
                ->with('danger', t('dashboard.Last_active_super_admin', 'At least one active super admin must remain.'));
        }

        return redirect()
            ->route('dashboard.admins.index')
            ->with('success', t('dashboard.Admin_deleted_successfully', 'Admin deleted successfully.'));
    }

    /**
     * Gate::before may short-circuit the policy (see SEC-02), so the super
     * admin guard is repeated here against the stored flags only.
     */
    private function authorizeTarget(string $ability, Admin $admin): void
    {
        $this->authorize($ability, $admin);

        abort_if($admin->isSuperAdmin() && ! auth('admin')->user()->isSuperAdmin(), 403);
    }

    private function isRestrictedSelfEdit(Admin $actor, Admin $admin): bool
    {
        return ! $actor->isSuperAdmin() && $actor->is($admin);
    }

    private function denySuperFlagChangeByNonSuper(Request $request, Admin $actor, bool $current): void
    {
        abort_if(
            ! $actor->isSuperAdmin()
                && $request->has('super_admin')
                && $request->boolean('super_admin') !== $current,
            403
        );
    }

    /**
     * Abilities the actor may grant or revoke: the whole catalogue for a
     * super admin, otherwise only the abilities the actor holds itself.
     *
     * @return array<int, string>
     */
    private function grantableAbilities(Admin $actor): array
    {
        if ($actor->isSuperAdmin()) {
            return $this->availableAbilities();
        }

        return array_values(array_intersect($actor->abilityNames(), $this->availableAbilities()));
    }

    /**
     * Rejects the whole request when it adds an ability outside the actor's
     * scope; abilities the target already holds outside that scope are kept.
     *
     * @return array<int, string>
     */
    private function resolveAbilities(Admin $actor, array $current, array $submitted): array
    {
        $scope = $this->grantableAbilities($actor);

        abort_if(array_diff($submitted, $current, $scope) !== [], 403);

        return array_values(array_unique(array_merge(
            array_diff($current, $scope),
            array_intersect($submitted, $scope),
        )));
    }

    private function sameAbilities(array $first, array $second): bool
    {
        return array_diff($first, $second) === [] && array_diff($second, $first) === [];
    }

    /**
     * Locks the active super admin rows so concurrent demotions, blocks and
     * deletions are serialized; must be called inside a transaction.
     */
    private function isLastActiveSuperAdmin(Admin $admin): bool
    {
        $activeSuperAdminIds = Admin::query()
            ->where('super_admin', true)
            ->where('status', 'active')
            ->lockForUpdate()
            ->pluck('id');

        return $activeSuperAdminIds->count() === 1
            && $activeSuperAdminIds->contains($admin->id);
    }

    private function syncAbilities(Admin $admin, array $abilities): void
    {
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
                    ->map(fn ($ability) => $groupName.'.'.$ability);
            })
            ->values()
            ->all();
    }
}
