@php
    $selectedAbilities = old('abilities', $admin->exists ? $admin->roles()->pluck('role_name')->toArray() : []);
@endphp

<div class="row g-4">
    <div class="col-12 col-md-6">
        <x-form.input
            name="name"
            label="{{ t('dashboard.Name', 'Name') }}"
            value="{{ $admin->name }}"
            required
        />
    </div>

    <div class="col-12 col-md-6">
        <x-form.input
            type="email"
            name="email"
            label="{{ t('dashboard.Email', 'Email') }}"
            value="{{ $admin->email }}"
        />
    </div>

    <div class="col-12 col-md-6">
        <x-form.input
            name="phone"
            label="{{ t('dashboard.Phone', 'Phone') }}"
            value="{{ $admin->phone }}"
            required
        />
    </div>

    @unless ($restrictedSelfEdit)
        <div class="col-12 col-md-6">
            <label class="form-label" for="status">{{ t('dashboard.Status', 'Status') }}</label>
            <select name="status" id="status" class="form-control @error('status') is-invalid @enderror">
                @foreach (['active' => 'Active', 'pending' => 'Pending', 'blocked' => 'Blocked'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $admin->status ?: 'active') === $value)>
                        {{ t('dashboard.' . $label, $label) }}
                    </option>
                @endforeach
            </select>
            @error('status')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endunless

    <div class="col-12 col-md-6">
        @if ($admin->exists)
            <x-form.input
                type="password"
                name="password"
                label="{{ t('dashboard.Password', 'Password') }}"
            />
        @else
            <x-form.input
                type="password"
                name="password"
                label="{{ t('dashboard.Password', 'Password') }}"
                required
            />
        @endif
    </div>

    <div class="col-12 col-md-6">
        @if ($admin->exists)
            <x-form.input
                type="password"
                name="password_confirmation"
                label="{{ t('dashboard.Confirm_Password', 'Confirm Password') }}"
            />
        @else
            <x-form.input
                type="password"
                name="password_confirmation"
                label="{{ t('dashboard.Confirm_Password', 'Confirm Password') }}"
                required
            />
        @endif
    </div>

    @if (auth('admin')->user()?->isSuperAdmin())
        <div class="col-12">
            <div class="form-check form-switch">
                <input type="hidden" name="super_admin" value="0">
                <input
                    class="form-check-input"
                    type="checkbox"
                    name="super_admin"
                    id="super_admin"
                    value="1"
                    @checked(old('super_admin', $admin->super_admin))
                >
                <label class="form-check-label fw-semibold" for="super_admin">
                    {{ t('dashboard.Super_Admin', 'Super Admin') }}
                </label>
            </div>
            @error('super_admin')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>
    @endif
</div>

{{-- Only abilities the signed-in admin may grant are listed; the controller enforces the same scope. --}}
@unless ($restrictedSelfEdit || $grantableAbilities === [])
    <hr class="my-4">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h5 class="mb-1">{{ t('dashboard.Permissions', 'Permissions') }}</h5>
            <p class="text-muted mb-0">{{ t('dashboard.Permissions_hint', 'Choose what this admin can access in the dashboard.') }}</p>
        </div>
    </div>

    <div class="row g-3">
        @foreach (app('abilities') as $groupName => $abilityGroup)
            @continue(! collect($abilityGroup)->keys()->contains(fn ($abilityName) => in_array($groupName . '.' . $abilityName, $grantableAbilities, true)))

            <div class="col-12 col-lg-4">
                <div class="card h-100 border">
                    <div class="card-body">
                        <h6 class="mb-3">{{ $abilityGroup['name'] }}</h6>

                        @foreach ($abilityGroup as $abilityName => $abilityLabel)
                            @continue($abilityName === 'name')

                            @php($value = $groupName . '.' . $abilityName)

                            @continue(! in_array($value, $grantableAbilities, true))

                            <div class="form-check mb-2">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="abilities[]"
                                    id="ability-{{ $groupName }}-{{ $abilityName }}"
                                    value="{{ $value }}"
                                    @checked(in_array($value, $selectedAbilities, true))
                                >
                                <label class="form-check-label" for="ability-{{ $groupName }}-{{ $abilityName }}">
                                    {{ $abilityLabel }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endunless

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('dashboard.admins.index') }}" class="btn btn-light-secondary">
        {{ t('dashboard.Cancel', 'Cancel') }}
    </a>
    <button type="submit" class="btn btn-primary">
        {{ $buttonLabel }}
    </button>
</div>
