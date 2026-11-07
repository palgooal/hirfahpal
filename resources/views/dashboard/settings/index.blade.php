<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Settings', 'Settings') }}</li>
    </x-slot:breadcrumbs>

    @if ($siteAvailability ?? null)
        @include('dashboard.settings.partials.site-availability', ['card' => $siteAvailability])
    @endif

    <div class="col-span-12">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-1">{{ t('dashboard.Settings', 'Settings') }}</h4>
                <p class="text-muted mb-0">{{ t('dashboard.Settings_hint', 'Manage the core site configuration.') }}</p>
            </div>

            <div class="card-body">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @php($canEditSettings = auth('admin')->user()->can('edit', App\Models\Setting::class))
                @php($footer = array_replace(App\Models\Setting::footerDefaults(), $setting->footer_content ?? []))

                <form method="POST" action="{{ route('dashboard.setting.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <fieldset @disabled(! $canEditSettings)>
                        <div class="row g-4">
                            <div class="col-12 col-md-6">
                                <x-form.input name="site_name" label="{{ t('dashboard.Site_Name', 'Site Name') }}" value="{{ $setting->site_name }}" />
                            </div>

                            <div class="col-12">
                                <x-form.textarea name="site_description" label="{{ t('dashboard.Site_Description', 'Site Description') }}" value="{{ $setting->site_description }}" />
                            </div>

                            <div class="col-12 col-md-6">
                                <x-form.input type="file" name="logo" label="{{ t('dashboard.Logo', 'Logo') }}" accept="image/*" />
                                @if ($setting->logo)
                                    <img src="{{ Storage::disk('public')->url($setting->logo) }}" alt="{{ t('dashboard.Logo', 'Logo') }}" class="img-fluid mt-2" style="max-height: 80px">
                                @endif
                            </div>

                            <div class="col-12 col-md-6">
                                <x-form.input type="file" name="favicon" label="{{ t('dashboard.Favicon', 'Favicon') }}" accept="image/*" />
                                @if ($setting->favicon)
                                    <img src="{{ Storage::disk('public')->url($setting->favicon) }}" alt="{{ t('dashboard.Favicon', 'Favicon') }}" class="img-fluid mt-2" style="max-height: 48px">
                                @endif
                            </div>

                            <div class="col-12 col-md-6">
                                <x-form.input type="email" name="email" label="{{ t('dashboard.Email', 'Email') }}" value="{{ $setting->email }}" />
                            </div>

                            <div class="col-12 col-md-6">
                                <x-form.input name="phone" label="{{ t('dashboard.Phone', 'Phone') }}" value="{{ $setting->phone }}" />
                            </div>

                            <div class="col-12">
                                <x-form.textarea name="address" label="{{ t('dashboard.Address', 'Address') }}" value="{{ $setting->address }}" />
                            </div>

                            <div class="col-12 col-md-4">
                                <x-form.input name="timezone" label="{{ t('dashboard.Timezone', 'Timezone') }}" value="{{ $setting->timezone }}" required />
                            </div>

                            <div class="col-12 col-md-4">
                                <x-form.input name="default_locale" label="{{ t('dashboard.Default_Locale', 'Default Locale') }}" value="{{ $setting->default_locale }}" required />
                            </div>

                            <div class="col-12 col-md-4">
                                <x-form.input name="default_currency" label="{{ t('dashboard.Default_Currency', 'Default Currency') }}" value="{{ $setting->default_currency }}" maxlength="3" required />
                            </div>

                            <div class="col-12"><hr class="my-1"><h5 class="mb-1">إعدادات الفوتر</h5><p class="text-muted mb-0">هذه البيانات تظهر مباشرة في تذييل الموقع.</p></div>

                            @php($footerFields = [
                                ['tagline', 'الشعار النصي'], ['description', 'وصف الفوتر', 'textarea'],
                                ['instagram_url', 'رابط إنستغرام'], ['facebook_url', 'رابط فيسبوك'], ['x_url', 'رابط إكس (X)'],
                                ['about_title', 'عنوان قسم عن الموقع'], ['about_story_label', 'نص رابط القصة والرؤية'], ['about_authenticity_label', 'نص رابط الأصالة والجودة'], ['about_contact_label', 'نص رابط التواصل'],
                                ['links_title', 'عنوان الروابط المفيدة'], ['orders_label', 'نص رابط الطلبات'], ['cart_label', 'نص رابط السلة'], ['login_label', 'نص رابط تسجيل الدخول'], ['account_label', 'نص رابط الحساب'],
                                ['customer_service_title', 'عنوان خدمة العملاء'], ['shipping_label', 'نص رابط الشحن'], ['returns_label', 'نص رابط الاستبدال والإرجاع'], ['faq_label', 'نص رابط الأسئلة الشائعة'],
                                ['copyright', 'نص الحقوق'], ['payment_methods_title', 'عنوان طرق الدفع'], ['card_payment_label', 'نص بطاقة الائتمان'], ['apple_pay_label', 'نص Apple Pay'], ['cash_on_delivery_label', 'نص الدفع عند الاستلام'],
                            ])
                            @foreach ($footerFields as $field)
                                @php([$key, $label] = $field)
                                @php($type = $field[2] ?? 'input')
                                <div class="col-12 {{ $type === 'textarea' ? '' : 'col-md-6' }}">
                                    @if ($type === 'textarea')
                                        <x-form.textarea name="footer[{{ $key }}]" label="{{ $label }}" :value="old('footer.'.$key, $footer[$key])" />
                                    @else
                                        <x-form.input name="footer[{{ $key }}]" label="{{ $label }}" :value="old('footer.'.$key, $footer[$key])" />
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </fieldset>

                    @can('edit', App\Models\Setting::class)
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary">{{ t('dashboard.Save', 'Save') }}</button>
                        </div>
                    @endcan
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
