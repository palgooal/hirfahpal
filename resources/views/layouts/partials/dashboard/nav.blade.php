<nav class="pc-sidebar">
    @php
        $admin = Auth::guard('admin')->user() ?? Auth::user();
        $canViewLanguages = $admin?->can('view', App\Models\Language::class) ?? false;
        $canViewTranslations = $admin?->can('view', App\Models\TranslationValue::class) ?? false;
        $canViewAdmins = $admin?->can('view', App\Models\Admin::class) ?? false;
        $canCreateAdmins = $admin?->can('create', App\Models\Admin::class) ?? false;
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
                <li class="pc-item">
                    <a href="javascript:void(0)" class="pc-link">
                        <span class="pc-micon">
                            <i class="fas fa-cog"></i>
                        </span>
                        <span class="pc-mtext">إعدادات الموقع</span>
                    </a>
                </li>
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
