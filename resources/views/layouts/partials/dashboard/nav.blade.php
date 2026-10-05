<nav class="pc-sidebar">
    @php
        $admin = Auth::guard('admin')->user() ?? Auth::user();
        $canViewLanguages = $admin?->can('view', App\Models\Language::class) ?? false;
        $canViewTranslations = $admin?->can('view', App\Models\TranslationValue::class) ?? false;
        $canViewAdmins = $admin?->can('view', App\Models\Admin::class) ?? false;
        $canCreateAdmins = $admin?->can('create', App\Models\Admin::class) ?? false;
        $canViewSettings = $admin?->can('view', App\Models\Setting::class) ?? false;
        $canViewVendors = $admin?->isSuperAdmin() || $admin?->hasAbility('vendors.view');
        $canCreateVendors = $admin?->isSuperAdmin() || $admin?->hasAbility('vendors.create');
        $canViewCategories = $admin?->isSuperAdmin() || $admin?->hasAbility('categories.view');
        $canViewProducts = $admin?->isSuperAdmin() || $admin?->hasAbility('products.view');
        $canViewCustomers = $admin?->isSuperAdmin() || $admin?->hasAbility('customers.view');
        $canViewDeliveryDrivers = $admin?->isSuperAdmin() || $admin?->hasAbility('delivery-drivers.view');
        $canViewOrders = $admin?->isSuperAdmin() || $admin?->hasAbility('orders.view');
        $canViewVendorOrders = $admin?->isSuperAdmin() || $admin?->hasAbility('vendor-orders.view');
        $canCreateVendorOrders = $admin?->isSuperAdmin() || $admin?->hasAbility('vendor-orders.create');
        $canViewReviews = $admin?->isSuperAdmin() || $admin?->hasAbility('reviews.view');
        $canViewDisputes = $admin?->isSuperAdmin() || $admin?->hasAbility('disputes.view');
        $canViewReturnRequests = $admin?->isSuperAdmin() || $admin?->hasAbility('return-requests.view');
    @endphp
    <div class="navbar-wrapper">
        <div class="m-header flex items-center py-4 px-6 h-header-height">
            <a href="{{route('dashboard.home')}}" class="b-brand flex items-center gap-3">
                <!-- ========   Change your logo from here   ============ -->
                <img src="{{asset('assets-dashboard/images/logo-dark.svg')}}" class="img-fluid logo-lg" alt="logo" style="display: none" />
                <div style="width: 232px;">
                    <img src="{{asset('asset/img/extra/marina.jpg')}}" class="img-fluid logo-lg" alt="logo" />
                </div>
            </a>
        </div>
        <div class="navbar-content h-[calc(100vh_-_74px)] py-2.5">
            <div class="card pc-user-card mx-[15px] mb-[15px] bg-theme-sidebaruserbg dark:bg-themedark-sidebaruserbg">
                <div class="card-body !p-5">
                    <div class="flex items-center">
                        <img class="shrink-0 w-[45px] h-[45px] rounded-full" src="https://ui-avatars.com/api/?name={{ urlencode($admin?->name ?? 'Admin') }}" alt="user-image" />
                        <div class="ml-4 mr-2 grow">
                            <h6 class="mb-0">{{ $admin?->name ?? 'Admin' }}</h6>

                        </div>
                        <a class="shrink-0 btn btn-icon inline-flex btn-link-secondary" data-pc-toggle="collapse" href="#pc_sidebar_userlink">
                            <svg class="pc-icon w-[22px] h-[22px]">
                                <use xlink:href="#custom-sort-outline"></use>
                            </svg>
                        </a>
                    </div>
                    <div class="hidden pc-user-links" id="pc_sidebar_userlink">
                        <div class="pt-3 *:flex *:items-center *:py-2 *:gap-2.5 hover:*:text-primary-500">
                            <a href="javascript:void(0)">
                                <i class="text-lg leading-none ti ti-user"></i>
                                <span>{{__('admin.My_account')}}</span>
                            </a>
                            <a href="{{ route('dashboard.admins.index') }}">
                                <i class="text-lg leading-none ti ti-shield-lock"></i>
                                <span>{{ t('dashboard.Permissions', 'Permissions') }}</span>
                            </a>
                            <form action="{{ route('admin.logout') }}" method="post">
                                @csrf
                                <button type="submit" style="display: flex; align-items: center; gap: 5px;">
                                    <i class="text-lg leading-none ti ti-power"></i>
                                    <span>{{__('admin.Logout')}}</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <ul class="pc-navbar">
                <li class="pc-item">
                    <a href="{{ route('dashboard.home') }}" class="pc-link">
                        <span class="pc-micon">
                            <span class="pc-micon">
                                <i class="fas fa-home"></i>
                            </span>
                        </span>
                        <span class="pc-mtext">{{__('admin.Home')}}</span>
                    </a>
                </li>
                @if ($canViewLanguages)
                <li class="pc-item {{ request()->routeIs('dashboard.languages.*') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.languages.index') }}" class="pc-link">
                        <span class="pc-micon">
                            <i class="fas fa-language"></i>
                        </span>
                        <span class="pc-mtext">{{ t('dashboard.Languages', 'Languages') }}</span>
                    </a>
                </li>
                @endif

                @if ($canViewTranslations)
                <li class="pc-item {{ request()->routeIs('dashboard.translation-values.*') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.translation-values.index') }}" class="pc-link">
                        <span class="pc-micon">
                            <i class="fas fa-language"></i>
                        </span>
                        <span class="pc-mtext">{{ t('dashboard.Translation_Values', 'Translation Values') }}</span>
                    </a>
                </li>
                @endif

                @if ($canViewAdmins)
                <li class="pc-item {{ request()->routeIs('dashboard.admins.index') || request()->routeIs('dashboard.admins.edit') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.admins.index') }}" class="pc-link">
                        <span class="pc-micon">
                            <i class="fas fa-user-shield"></i>
                        </span>
                        <span class="pc-mtext">{{ t('dashboard.Admins_Permissions', 'Admins & Permissions') }}</span>
                    </a>
                </li>
                @endif

                @if ($canCreateAdmins)
                <li class="pc-item {{ request()->routeIs('dashboard.admins.create') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.admins.create') }}" class="pc-link">
                        <span class="pc-micon">
                            <i class="fas fa-user-plus"></i>
                        </span>
                        <span class="pc-mtext">{{ t('dashboard.Add_Admin', 'Add Admin') }}</span>
                    </a>
                </li>
                @endif
                @if ($canViewCategories)
                <li class="pc-item {{ request()->routeIs('dashboard.categories.*') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.categories.index') }}" class="pc-link">
                        <span class="pc-micon"><i class="fas fa-layer-group"></i></span>
                        <span class="pc-mtext">{{ t('dashboard.Categories', 'Categories') }}</span>
                    </a>
                </li>
                @endif
                @if ($canViewProducts)
                <li class="pc-item {{ request()->routeIs('dashboard.products.*') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.products.index') }}" class="pc-link">
                        <span class="pc-micon"><i class="fas fa-box-open"></i></span>
                        <span class="pc-mtext">{{ t('dashboard.Products', 'Products') }}</span>
                    </a>
                </li>
                @endif
                @if ($canViewVendors)
                <li class="pc-item {{ request()->routeIs('dashboard.vendors.*') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.vendors.index') }}" class="pc-link">
                        <span class="pc-micon">
                            <i class="fas fa-store"></i>
                        </span>
                        <span class="pc-mtext">{{ t('dashboard.Vendors', 'Vendors') }}</span>
                    </a>
                </li>
                @endif
                @if ($canCreateVendors)
                <li class="pc-item {{ request()->routeIs('dashboard.vendors.create') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.vendors.create') }}" class="pc-link">
                        <span class="pc-micon">
                            <i class="fas fa-store-alt"></i>
                        </span>
                        <span class="pc-mtext">{{ t('dashboard.Add_Vendor', 'Add Vendor') }}</span>
                    </a>
                </li>
                @endif
                @if ($canViewCustomers)
                <li class="pc-item {{ request()->routeIs('dashboard.customers.*') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.customers.index') }}" class="pc-link">
                        <span class="pc-micon"><i class="fas fa-users"></i></span>
                        <span class="pc-mtext">{{ t('dashboard.Customers', 'Customers') }}</span>
                    </a>
                </li>
                @endif
                @if ($canViewDeliveryDrivers)
                <li class="pc-item {{ request()->routeIs('dashboard.delivery-drivers.*') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.delivery-drivers.index') }}" class="pc-link">
                        <span class="pc-micon"><i class="fas fa-truck"></i></span>
                        <span class="pc-mtext">{{ t('dashboard.Delivery_Drivers', 'Delivery Drivers') }}</span>
                    </a>
                </li>
                @endif
                @if ($canViewOrders)
                <li class="pc-item {{ request()->routeIs('dashboard.orders.*') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.orders.index') }}" class="pc-link">
                        <span class="pc-micon"><i class="fas fa-receipt"></i></span>
                        <span class="pc-mtext">{{ t('dashboard.Orders', 'Orders') }}</span>
                    </a>
                </li>
                @endif

                @if ($canViewVendorOrders)
                <li class="pc-item {{ request()->routeIs('dashboard.vendor-orders.*') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.vendor-orders.index') }}" class="pc-link">
                        <span class="pc-micon">
                            <i class="fas fa-clipboard-list"></i>
                        </span>
                        <span class="pc-mtext">{{ t('dashboard.Vendor_Orders', 'Vendor Orders') }}</span>
                    </a>
                </li>
                @endif

                @if ($canCreateVendorOrders)
                <li class="pc-item {{ request()->routeIs('dashboard.vendor-orders.create') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.vendor-orders.create') }}" class="pc-link">
                        <span class="pc-micon">
                            <i class="fas fa-plus-square"></i>
                        </span>
                        <span class="pc-mtext">{{ t('dashboard.Add_Vendor_Order', 'Add Vendor Order') }}</span>
                    </a>
                </li>
                @endif
                @if ($canViewReviews)
                <li class="pc-item {{ request()->routeIs('dashboard.reviews.*') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.reviews.index') }}" class="pc-link">
                        <span class="pc-micon"><i class="fas fa-star"></i></span>
                        <span class="pc-mtext">{{ t('dashboard.Reviews', 'Reviews') }}</span>
                    </a>
                </li>
                @endif
                @if ($canViewDisputes)
                <li class="pc-item {{ request()->routeIs('dashboard.disputes.*') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.disputes.index') }}" class="pc-link">
                        <span class="pc-micon"><i class="fas fa-life-ring"></i></span>
                        <span class="pc-mtext">{{ t('dashboard.Disputes', 'Disputes') }}</span>
                    </a>
                </li>
                @endif
                @if ($canViewReturnRequests)
                <li class="pc-item {{ request()->routeIs('dashboard.return-requests.*') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.return-requests.index') }}" class="pc-link">
                        <span class="pc-micon"><i class="fas fa-undo"></i></span>
                        <span class="pc-mtext">{{ t('dashboard.Return_Requests', 'Return Requests') }}</span>
                    </a>
                </li>
                @endif
                @if ($canViewSettings)
                <li class="pc-item {{ request()->routeIs('dashboard.setting.*') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.setting.index') }}" class="pc-link">
                        <span class="pc-micon">
                            <i class="fas fa-cog"></i>
                        </span>
                        <span class="pc-mtext">إعدادات الموقع</span>
                    </a>
                </li>
                @endif
                <li class="pc-item">
                    <a href="javascript:void(0)" class="pc-link">
                        <span class="pc-micon">
                            <i class="fas fa-heading"></i>
                        </span>
                        <span class="pc-mtext">مقدمة الصفحة الرئيسية</span>
                    </a>
                </li>
                {{-- <li class="pc-item pc-caption">
                    <label>{{__('Basic')}}</label>
                </li> --}}


               
                <li class="pc-item pc-hasmenu">
                    <a href="javascript:void(0)" class="pc-link">
                        <span class="pc-micon">
                            <i class="fas fa-map-marker-alt"></i>
                        </span>
                        <span class="pc-mtext">
                            {{__('admin.Area')}}
                        </span>
                        @if (App::getLocale() == 'en')
                        <span class="pc-arrow"><i data-feather="chevron-right"></i></span>
                        @else
                        <span class="pc-arrow"><i data-feather="chevron-left"></i></span>
                        @endif
                    </a>
                    <ul class="pc-submenu">
                        <li class="pc-item">
                            <a class="pc-link" href="javascript:void(0)">
                                {{__('admin.Area show')}}
                            </a>
                        </li>
                        <li class="pc-item">
                            <a class="pc-link" href="javascript:void(0)" >
                                {{__('admin.Add Area')}}
                            </a>
                        </li>
                    </ul>
                </li>

               

                

                 


                


                

               

                

               
              


            </ul>

        </div>
    </div>
</nav>
