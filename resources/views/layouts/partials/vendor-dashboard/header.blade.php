<header class="pc-header">
    <div class="header-wrapper vendor-header-wrapper">
        <ul class="vendor-header-group">
            {{-- Desktop: hide/show the sidebar. --}}
            <li class="pc-h-item vendor-desktop-only">
                <button
                    type="button"
                    class="pc-head-link vendor-icon-button"
                    data-vendor-sidebar-toggle
                    aria-controls="vendor-sidebar"
                    aria-expanded="true"
                    aria-label="{{ t('vendor.Menu_Collapse', 'Collapse menu') }}"
                    data-label-collapse="{{ t('vendor.Menu_Collapse', 'Collapse menu') }}"
                    data-label-expand="{{ t('vendor.Menu_Expand', 'Expand menu') }}"
                >
                    <i class="ti ti-menu-2" aria-hidden="true"></i>
                </button>
            </li>
            {{-- Mobile and tablet: open the sidebar as a drawer. --}}
            <li class="pc-h-item vendor-mobile-only">
                <button
                    type="button"
                    class="pc-head-link vendor-icon-button"
                    data-vendor-drawer-toggle
                    aria-controls="vendor-sidebar"
                    aria-expanded="false"
                    aria-label="{{ t('vendor.Menu', 'Menu') }}"
                >
                    <i class="ti ti-menu-2" aria-hidden="true"></i>
                </button>
            </li>
        </ul>

        <ul class="vendor-header-group">
            <li class="pc-h-item">
                @include('layouts.partials.vendor-dashboard.language-switcher')
            </li>

            <li class="pc-h-item vendor-user-menu" data-vendor-dropdown>
                <button
                    type="button"
                    class="vendor-user-trigger"
                    data-vendor-dropdown-toggle
                    aria-haspopup="true"
                    aria-expanded="false"
                    aria-controls="vendor-user-dropdown"
                    aria-label="{{ t('vendor.Account_Menu', 'Account menu') }}: {{ $displayName }}"
                >
                    <span class="vendor-monogram vendor-monogram-sm" aria-hidden="true">{{ $initial }}</span>
                    <span class="vendor-user-trigger-name" dir="auto">{{ $displayName }}</span>
                    <i class="ti ti-chevron-down vendor-user-trigger-caret" aria-hidden="true"></i>
                </button>

                <div class="vendor-dropdown" id="vendor-user-dropdown" hidden>
                    <div class="vendor-dropdown-identity">
                        <span class="vendor-monogram" aria-hidden="true">{{ $initial }}</span>
                        <span class="vendor-dropdown-identity-text">
                            <span class="vendor-dropdown-name"><bdi>{{ $displayName }}</bdi></span>
                            <span class="vendor-dropdown-role">{{ t('vendor.Portal', 'Vendor portal') }}</span>
                        </span>
                    </div>
                    <form method="POST" action="{{ route('vendor.logout') }}">
                        @csrf
                        <button type="submit" class="vendor-dropdown-item">
                            <i class="ph-duotone ph-sign-out rtl:-scale-x-100" aria-hidden="true"></i>
                            <span>{{ t('vendor.Sign_Out', 'Sign out') }}</span>
                        </button>
                    </form>
                </div>
            </li>
        </ul>
    </div>
</header>
