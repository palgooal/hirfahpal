<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Settings', 'Settings') }}</li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ t('dashboard.Settings', 'Settings') }}</h5>
            </div>
            <div class="card-body">
                @if (session('success'))
                    <div class="alert alert-success mb-4" role="status">{{ session('success') }}</div>
                @endif

                <form action="{{ route('dashboard.setting.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-12 gap-4">
                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label" for="site_name">{{ t('dashboard.Site_Name', 'Site Name') }}</label>
                            <input id="site_name" name="site_name" class="form-control" value="{{ old('site_name', $setting->site_name) }}">
                            @error('site_name') <div class="text-danger mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label" for="email">{{ t('dashboard.Email', 'Email') }}</label>
                            <input id="email" type="email" name="email" class="form-control" value="{{ old('email', $setting->email) }}">
                            @error('email') <div class="text-danger mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-span-12">
                            <label class="form-label" for="site_description">{{ t('dashboard.Description', 'Description') }}</label>
                            <textarea id="site_description" name="site_description" class="form-control" rows="3">{{ old('site_description', $setting->site_description) }}</textarea>
                            @error('site_description') <div class="text-danger mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label" for="phone">{{ t('dashboard.Phone', 'Phone') }}</label>
                            <input id="phone" name="phone" class="form-control" value="{{ old('phone', $setting->phone) }}">
                            @error('phone') <div class="text-danger mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label" for="address">{{ t('dashboard.Address', 'Address') }}</label>
                            <input id="address" name="address" class="form-control" value="{{ old('address', $setting->address) }}">
                            @error('address') <div class="text-danger mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-span-12 md:col-span-4">
                            <label class="form-label" for="timezone">{{ t('dashboard.Timezone', 'Timezone') }}</label>
                            <input id="timezone" name="timezone" class="form-control" value="{{ old('timezone', $setting->timezone) }}" required>
                            @error('timezone') <div class="text-danger mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-span-12 md:col-span-4">
                            <label class="form-label" for="default_locale">{{ t('dashboard.Default_Language', 'Default Language') }}</label>
                            <input id="default_locale" name="default_locale" class="form-control" value="{{ old('default_locale', $setting->default_locale) }}" required>
                            @error('default_locale') <div class="text-danger mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-span-12 md:col-span-4">
                            <label class="form-label" for="default_currency">{{ t('dashboard.Default_Currency', 'Default Currency') }}</label>
                            <input id="default_currency" name="default_currency" class="form-control uppercase" maxlength="3" value="{{ old('default_currency', $setting->default_currency) }}" required>
                            @error('default_currency') <div class="text-danger mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label" for="logo">{{ t('dashboard.Logo', 'Logo') }}</label>
                            <input id="logo" type="file" name="logo" class="form-control" accept="image/*">
                            @error('logo') <div class="text-danger mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label" for="favicon">{{ t('dashboard.Favicon', 'Favicon') }}</label>
                            <input id="favicon" type="file" name="favicon" class="form-control" accept="image/*">
                            @error('favicon') <div class="text-danger mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    @can('update', \App\Models\Setting::class)
                        <div class="mt-5">
                            <button type="submit" class="btn btn-primary">{{ t('dashboard.Save', 'Save') }}</button>
                        </div>
                    @endcan
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
