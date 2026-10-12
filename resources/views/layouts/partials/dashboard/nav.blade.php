@php
    // AD-UI-01C-3: admin sidebar, structure from AD-UI-01 v0.5 section 3.1. Each item is
    // shown only when the admin passes the same check its controller enforces; deferred
    // sections (delivery, finance) have no route yet and are not rendered.
    $admin = Auth::guard('admin')->user();
    $ability = fn (string $name): bool => (bool) ($admin?->isSuperAdmin() || $admin?->hasAbility($name));
    $policy = fn (string $model): bool => (bool) $admin?->can('view', $model);

    $home = ['route' => 'dashboard.home', 'active' => 'dashboard.home', 'key' => 'dashboard.Nav_Home', 'default' => 'Home', 'icon' => 'ph-house'];

    $groups = [
        ['key' => 'dashboard.Nav_Group_Users', 'default' => 'User management', 'items' => [
            ['route' => 'dashboard.vendors.index', 'active' => 'dashboard.vendors.*', 'key' => 'dashboard.Nav_Vendors', 'default' => 'Vendors', 'icon' => 'ph-storefront', 'visible' => $ability('vendors.view')],
            ['route' => 'dashboard.customers.index', 'active' => 'dashboard.customers.*', 'key' => 'dashboard.Nav_Customers', 'default' => 'Customers', 'icon' => 'ph-users-three', 'visible' => $ability('customers.view')],
            ['route' => 'dashboard.delivery-drivers.index', 'active' => 'dashboard.delivery-drivers.*', 'key' => 'dashboard.Nav_Delivery_Drivers', 'default' => 'Delivery drivers', 'icon' => 'ph-truck', 'visible' => $ability('delivery-drivers.view')],
            ['route' => 'dashboard.admins.index', 'active' => 'dashboard.admins.*', 'key' => 'dashboard.Nav_Admins_Permissions', 'default' => 'Admins and permissions', 'icon' => 'ph-user-gear', 'visible' => $policy(App\Models\Admin::class)],
        ]],
        ['key' => 'dashboard.Nav_Group_Store', 'default' => 'Store management', 'items' => [
            ['route' => 'dashboard.categories.index', 'active' => 'dashboard.categories.*', 'key' => 'dashboard.Nav_Categories', 'default' => 'Categories', 'icon' => 'ph-squares-four', 'visible' => $ability('categories.view')],
            ['route' => 'dashboard.products.index', 'active' => 'dashboard.products.*', 'key' => 'dashboard.Nav_Products', 'default' => 'Products', 'icon' => 'ph-package', 'visible' => $ability('products.view')],
            ['route' => 'dashboard.orders.index', 'active' => 'dashboard.orders.*', 'key' => 'dashboard.Nav_Orders', 'default' => 'Orders', 'icon' => 'ph-receipt', 'visible' => $ability('orders.view')],
            ['route' => 'dashboard.vendor-orders.index', 'active' => 'dashboard.vendor-orders.*', 'key' => 'dashboard.Nav_Vendor_Orders', 'default' => 'Vendor orders', 'icon' => 'ph-clipboard-text', 'visible' => $ability('vendor-orders.view')],
        ]],
        ['key' => 'dashboard.Nav_Group_Quality', 'default' => 'Quality and follow-up', 'items' => [
            ['route' => 'dashboard.reviews.index', 'active' => 'dashboard.reviews.*', 'key' => 'dashboard.Nav_Reviews', 'default' => 'Reviews', 'icon' => 'ph-star', 'visible' => $ability('reviews.view')],
            ['route' => 'dashboard.disputes.index', 'active' => 'dashboard.disputes.*', 'key' => 'dashboard.Nav_Disputes', 'default' => 'Disputes', 'icon' => 'ph-scales', 'visible' => $ability('disputes.view')],
            ['route' => 'dashboard.return-requests.index', 'active' => 'dashboard.return-requests.*', 'key' => 'dashboard.Nav_Return_Requests', 'default' => 'Return requests', 'icon' => 'ph-arrow-u-up-left', 'visible' => $ability('return-requests.view')],
        ]],
        ['key' => 'dashboard.Nav_Group_System', 'default' => 'System', 'items' => [
            ['route' => 'dashboard.languages.index', 'active' => 'dashboard.languages.*', 'key' => 'dashboard.Nav_Languages', 'default' => 'Languages', 'icon' => 'ph-globe', 'visible' => $policy(App\Models\Language::class)],
            ['route' => 'dashboard.translation-values.index', 'active' => 'dashboard.translation-values.*', 'key' => 'dashboard.Nav_Translations', 'default' => 'Translations', 'icon' => 'ph-translate', 'visible' => $policy(App\Models\TranslationValue::class)],
            ['route' => 'dashboard.setting.index', 'active' => 'dashboard.setting.*', 'key' => 'dashboard.Nav_Settings', 'default' => 'Settings', 'icon' => 'ph-gear-six', 'visible' => $policy(App\Models\Setting::class)],
            // AD-UI-CAT-M3: the media library (palgoal/media-library routes); the server checks media.view itself.
            ['route' => 'media-library.page', 'active' => 'media-library.*', 'key' => 'dashboard.Nav_Media_Library', 'default' => 'Media Library', 'icon' => 'ph-images', 'visible' => $ability('media.view')],
        ]],
    ];

    // Groups whose items are all hidden for this admin are not rendered.
    $groups = array_values(array_filter(array_map(function (array $group): array {
        $group['items'] = array_values(array_filter($group['items'], fn (array $item): bool => $item['visible']));

        return $group;
    }, $groups), fn (array $group): bool => $group['items'] !== []));
@endphp
{{-- data-admin-sidebar="managed": resources/js/admin-dashboard.js drives collapse, drawer and tooltips (AD-UI-01C-4B). --}}
<nav class="pc-sidebar" id="admin-sidebar" data-admin-sidebar="managed" aria-label="{{ t('dashboard.Nav_Main', 'Main menu') }}">
    <div class="navbar-wrapper">
        <div class="m-header admin-brand-header">
            <a href="{{ route('dashboard.home') }}" class="b-brand admin-brand">
                <span class="admin-brand-mark">
                    <img src="{{ asset('images/brand/hirfah-logo.png') }}" alt="{{ t('dashboard.Hirfah_Logo', 'Hirfah logo') }}" width="40" height="40" />
                </span>
                <span class="admin-brand-text">
                    <span class="admin-brand-name">{{ t('dashboard.Brand_Name', 'Hirfah') }}</span>
                    <span class="admin-brand-sub">{{ t('dashboard.Nav_Brand_Subtitle', 'Admin panel') }}</span>
                </span>
            </a>
        </div>

        {{-- The admin's name, role and sign out live in the header account menu (AD-UI-01D-5). --}}
        <div class="navbar-content admin-navbar-content">
            <ul class="pc-navbar admin-navbar">
                @php($isCurrent = request()->routeIs($home['active']))
                <li class="pc-item {{ $isCurrent ? 'active' : '' }}">
                    <a href="{{ route($home['route']) }}" class="pc-link" @if ($isCurrent) aria-current="page" @endif>
                        <span class="pc-micon"><i class="ph-duotone {{ $home['icon'] }}" aria-hidden="true"></i></span>
                        <span class="pc-mtext">{{ t($home['key'], $home['default']) }}</span>
                    </a>
                </li>

                @foreach ($groups as $index => $group)
                    <li class="admin-nav-group">
                        <span class="admin-nav-group-title" id="admin-nav-group-{{ $index }}">{{ t($group['key'], $group['default']) }}</span>
                        <ul class="admin-nav-list" aria-labelledby="admin-nav-group-{{ $index }}">
                            @foreach ($group['items'] as $item)
                                {{-- Active on the item's route and its sub-routes (create, edit, show). --}}
                                @php($isCurrent = request()->routeIs($item['active']))
                                <li class="pc-item {{ $isCurrent ? 'active' : '' }}">
                                    <a href="{{ route($item['route']) }}" class="pc-link" @if ($isCurrent) aria-current="page" @endif>
                                        <span class="pc-micon"><i class="ph-duotone {{ $item['icon'] }}" aria-hidden="true"></i></span>
                                        <span class="pc-mtext">{{ t($item['key'], $item['default']) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
    {{-- Mobile drawer overlay, shown by resources/js/admin-dashboard.js. --}}
    <div class="pc-menu-overlay" data-admin-overlay hidden></div>
</nav>
