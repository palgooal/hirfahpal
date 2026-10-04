<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.vendors.index') }}">{{ t('dashboard.Vendors', 'Vendors') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Add_Vendor', 'Add Vendor') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <div class="card">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-light-primary text-primary mb-2">{{ t('dashboard.Vendors', 'Vendors') }}</span>
                    <h4 class="mb-1">{{ t('dashboard.Add_Vendor', 'Add Vendor') }}</h4>
                    <p class="text-muted mb-0">{{ t('dashboard.Add_Vendor_Description', 'Create a vendor account and store profile from the admin dashboard.') }}</p>
                </div>
                <a href="{{ route('dashboard.vendors.index') }}" class="btn btn-light-secondary">
                    {{ t('dashboard.Back_to_vendors', 'Back to vendors') }}
                </a>
            </div>
        </div>
    </div>

    <div class="col-span-12">
        <form action="{{ route('dashboard.vendors.store') }}" method="POST" class="card">
            @csrf

            <div class="card-header">
                <h5 class="mb-0">{{ t('dashboard.Account_Information', 'Account Information') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">{{ t('dashboard.Owner_Name', 'Owner Name') }}</label>
                        <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ t('dashboard.Email', 'Email') }}</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ t('dashboard.Phone', 'Phone') }}</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ t('dashboard.Password', 'Password') }}</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ t('dashboard.Confirm_Password', 'Confirm Password') }}</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ t('dashboard.Account_Status', 'Account Status') }}</label>
                        <select name="status" class="form-select" required>
                            @foreach (['active', 'pending', 'blocked'] as $status)
                                <option value="{{ $status }}" @selected(old('status', 'active') === $status)>
                                    {{ t('dashboard.'.ucfirst($status), ucfirst($status)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-header border-top">
                <h5 class="mb-0">{{ t('dashboard.Store_Profile', 'Store Profile') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ t('dashboard.Store_Name', 'Store Name') }}</label>
                        <input type="text" name="store_name" value="{{ old('store_name') }}" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ t('dashboard.Slug', 'Slug') }}</label>
                        <input type="text" name="slug" value="{{ old('slug') }}" class="form-control" placeholder="{{ t('dashboard.Auto_generated_if_empty', 'Auto-generated if empty') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ t('dashboard.Governorate', 'Governorate') }}</label>
                        <select name="governorate_id" class="form-select">
                            <option value="">{{ t('dashboard.Select_governorate', 'Select governorate') }}</option>
                            @foreach ($governorates as $governorate)
                                <option value="{{ $governorate->id }}" @selected(old('governorate_id') == $governorate->id)>
                                    {{ $governorate->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ t('dashboard.City', 'City') }}</label>
                        <select name="city_id" class="form-select">
                            <option value="">{{ t('dashboard.Select_city', 'Select city') }}</option>
                            @foreach ($cities as $city)
                                <option value="{{ $city->id }}" @selected(old('city_id') == $city->id)>
                                    {{ $city->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">{{ t('dashboard.Address', 'Address') }}</label>
                        <input type="text" name="address_line" value="{{ old('address_line') }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ t('dashboard.Commission_Rate', 'Commission Rate') }}</label>
                        <input type="number" name="commission_rate" value="{{ old('commission_rate') }}" min="0" max="100" step="0.01" class="form-control">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">{{ t('dashboard.Short_Description', 'Short Description') }}</label>
                        <input type="text" name="short_description" value="{{ old('short_description') }}" class="form-control">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">{{ t('dashboard.Description', 'Description') }}</label>
                        <textarea name="description" rows="4" class="form-control">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card-header border-top">
                <h5 class="mb-0">{{ t('dashboard.Approval', 'Approval') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">{{ t('dashboard.Approval_Status', 'Approval Status') }}</label>
                        <select name="approval_status" class="form-select" required>
                            @foreach (['approved', 'pending', 'rejected'] as $status)
                                <option value="{{ $status }}" @selected(old('approval_status', 'approved') === $status)>
                                    {{ t('dashboard.'.ucfirst($status), ucfirst($status)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">{{ t('dashboard.Rejection_Reason', 'Rejection Reason') }}</label>
                        <textarea name="rejection_reason" rows="3" class="form-control">{{ old('rejection_reason') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-end gap-2">
                <a href="{{ route('dashboard.vendors.index') }}" class="btn btn-light-secondary">{{ t('dashboard.Cancel', 'Cancel') }}</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-device-floppy me-1"></i>
                    {{ t('dashboard.Create_Vendor', 'Create Vendor') }}
                </button>
            </div>
        </form>
    </div>
</x-dashboard-layout>
