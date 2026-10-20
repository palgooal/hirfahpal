@php
    // AD-UI-01D-3: admin header. Start: sidebar control and page title; end: language and account.
    // The page title ($adminPageTitle) comes from dashboard-layout.blade.php (AD-UI-01D-6).
    $admin = Auth::guard('admin')->user();
    $adminName = $admin?->name ?? '';
    $initial = mb_strtoupper(mb_substr(trim($adminName) !== '' ? trim($adminName) : 'A', 0, 1));
    $roleLabel = $admin?->isSuperAdmin()
        ? t('dashboard.Header_Role_Super_Admin', 'Super admin')
        : t('dashboard.Header_Role_Admin', 'Admin');

    // SITE-AVAILABILITY Phase 3C (DEC-SA-06, §7.4): the effective site state for every signed-in admin. One memoized
    // read through SiteAvailability (no query with a forced state; it resolves to open, logged, without the migration
    // or the database). Texts without auto-creating rows (AdminSiteAvailabilityTranslationSeeder).
    $saText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };
    $siteAvailability = app(App\Support\SiteAvailability\SiteAvailability::class);
    $siteState = $siteAvailability->current()->value;
    $siteStateForced = $siteAvailability->source() === 'environment';
    $siteStateLabel = [
        'open' => $saText('Site_Availability_State_Open', 'Open'),
        'coming_soon' => $saText('Site_Availability_State_Coming_Soon', 'Coming soon'),
        'maintenance' => $saText('Site_Availability_State_Maintenance', 'Maintenance'),
    ][$siteState];
    $siteStateTone = ['open' => 'is-active', 'coming_soon' => 'is-low', 'maintenance' => 'is-suspended'][$siteState];
    $siteStateTitle = $saText('Site_Availability_Title', 'Site availability').': '.$siteStateLabel
        .($siteStateForced ? ' ('.$saText('Site_Availability_Badge_Override', 'Server override').')' : '');
@endphp
<header class="pc-header admin-header" id="header">
    <div class="header-wrapper admin-header-wrapper">
        <div class="admin-header-start">
            <ul class="admin-header-group">
                {{-- Sidebar controls, driven by resources/js/admin-dashboard.js (labels swap with the state). --}}
                <li class="pc-h-item pc-sidebar-collapse max-lg:hidden lg:inline-flex">
                    <button type="button" class="pc-head-link admin-sidebar-control ltr:!ml-0 rtl:!mr-0" id="sidebar-hide"
                        aria-controls="admin-sidebar" aria-expanded="true"
                        aria-label="{{ t('dashboard.Nav_Collapse', 'Collapse menu') }}"
                        data-label-collapse="{{ t('dashboard.Nav_Collapse', 'Collapse menu') }}"
                        data-label-expand="{{ t('dashboard.Nav_Expand', 'Expand menu') }}">
                        <i class="ph-duotone ph-sidebar-simple rtl:-scale-x-100" aria-hidden="true"></i>
                    </button>
                </li>
                <li class="pc-h-item pc-sidebar-popup lg:hidden">
                    <button type="button" class="pc-head-link admin-sidebar-control ltr:!ml-0 rtl:!mr-0" id="mobile-collapse"
                        aria-controls="admin-sidebar" aria-expanded="false"
                        aria-label="{{ t('dashboard.Nav_Open', 'Open menu') }}"
                        data-label-open="{{ t('dashboard.Nav_Open', 'Open menu') }}"
                        data-label-close="{{ t('dashboard.Nav_Close', 'Close menu') }}">
                        <i class="ph-duotone ph-list" aria-hidden="true"></i>
                    </button>
                </li>
            </ul>
            {{-- Shared with <title>: resolved once in dashboard-layout.blade.php (AD-UI-01D-6). --}}
            <h1 class="admin-header-title">{{ $adminPageTitle }}</h1>
        </div>

        <ul class="admin-header-group admin-header-end">
            {{-- Site state badge: a link to the settings card for a Super Admin only; plain text for everyone else. --}}
            <li class="pc-h-item d-flex align-items-center admin-products" data-site-availability-badge="{{ $siteState }}" @if ($siteStateForced) data-site-availability-forced @endif>
                @if ($admin?->isSuperAdmin())
                    <a href="{{ route('dashboard.setting.index') }}#site-availability" class="admin-products-badge {{ $siteStateTone }} text-decoration-none mt-0" title="{{ $siteStateTitle }}" aria-label="{{ $siteStateTitle }}">
                @else
                    <span class="admin-products-badge {{ $siteStateTone }} mt-0" title="{{ $siteStateTitle }}">
                @endif
                        @if ($siteStateForced)
                            <i class="ph-duotone ph-lock-simple" aria-hidden="true"></i>
                        @endif
                        <span>{{ $siteStateLabel }}</span>
                        @if ($siteStateForced)
                            <span class="d-none d-md-inline">· {{ $saText('Site_Availability_Badge_Override', 'Server override') }}</span>
                            <span class="visually-hidden d-md-none">({{ $saText('Site_Availability_Badge_Override', 'Server override') }})</span>
                        @endif
                @if ($admin?->isSuperAdmin())
                    </a>
                @else
                    </span>
                @endif
            </li>

            <x-lang.language-switcher-dashboard />

            {{-- Account menu: name, role and sign out only (AD-UI-01D). Disclosure driven by resources/js/admin-dashboard.js. --}}
            <li class="dropdown pc-h-item admin-account" data-admin-dropdown>
                <button type="button" class="pc-head-link dropdown-toggle arrow-none me-0 admin-account-trigger" data-admin-dropdown-toggle
                    aria-haspopup="true" aria-expanded="false" aria-controls="admin-account-menu"
                    aria-label="{{ t('dashboard.Nav_Account_Menu', 'Account menu') }}: {{ $adminName }}">
                    <span class="admin-monogram admin-monogram-sm" aria-hidden="true">{{ $initial }}</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end pc-h-dropdown admin-header-menu admin-account-menu" id="admin-account-menu">
                    <div class="admin-account-identity">
                        <span class="admin-monogram" aria-hidden="true">{{ $initial }}</span>
                        <span class="admin-account-text">
                            <span class="admin-account-name"><bdi>{{ $adminName }}</bdi></span>
                            <span class="admin-account-role">{{ $roleLabel }}</span>
                        </span>
                    </div>
                    <form action="{{ route('admin.logout') }}" method="post">
                        @csrf
                        <button type="submit" class="dropdown-item admin-header-menu-item">
                            <i class="ph-duotone ph-sign-out rtl:-scale-x-100" aria-hidden="true"></i>
                            <span>{{ t('dashboard.Nav_Sign_Out', 'Sign out') }}</span>
                        </button>
                    </form>
                </div>
            </li>
        </ul>
    </div>
</header>
