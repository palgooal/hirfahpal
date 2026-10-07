@php
    // Provisional vendor IA. Only pages that exist as browser views are links; the
    // rest are shown as inactive placeholders with no href and no route, so the
    // navigation never points at the JSON vendor.dashboard.* endpoints. Reports is
    // omitted until a backend exists (SRS-001).
    $links = [
        ['route' => 'vendor.dashboard', 'key' => 'vendor.Nav_Dashboard', 'default' => 'Dashboard', 'icon' => 'ph-squares-four'],
        ['route' => 'vendor.dashboard.my-store', 'key' => 'vendor.Nav_My_Store', 'default' => 'My Store', 'icon' => 'ph-storefront'],
    ];
    $comingSoon = [
        ['key' => 'vendor.Nav_Products', 'default' => 'Products', 'icon' => 'ph-package'],
        ['key' => 'vendor.Nav_Orders', 'default' => 'Orders', 'icon' => 'ph-receipt'],
        ['key' => 'vendor.Nav_Reviews', 'default' => 'Reviews', 'icon' => 'ph-star'],
        ['key' => 'vendor.Nav_Returns', 'default' => 'Returns', 'icon' => 'ph-arrow-u-up-left'],
        ['key' => 'vendor.Nav_Disputes', 'default' => 'Disputes', 'icon' => 'ph-scales'],
        ['key' => 'vendor.Nav_Finance', 'default' => 'Finance', 'icon' => 'ph-wallet'],
    ];
    $soonLabel = t('vendor.Nav_Soon', 'Soon');
@endphp
<nav class="pc-sidebar" id="vendor-sidebar" aria-label="{{ t('vendor.Nav_Main', 'Main menu') }}">
    <div class="navbar-wrapper">
        <div class="m-header vendor-brand-header">
            <a href="{{ route('vendor.dashboard') }}" class="b-brand vendor-brand">
                <span class="vendor-brand-mark">
                    <img src="{{ asset('images/brand/hirfah-logo.png') }}" alt="{{ t('dashboard.Hirfah_Logo', 'Hirfah logo') }}" width="40" height="40" />
                </span>
                <span class="vendor-brand-text">
                    <span class="vendor-brand-name">{{ t('dashboard.Brand_Name', 'Hirfah') }}</span>
                    <span class="vendor-brand-sub">{{ t('vendor.Portal', 'Vendor portal') }}</span>
                </span>
            </a>
        </div>

        <div class="navbar-content vendor-navbar-content">
            <div class="vendor-store-card">
                <span class="vendor-monogram" aria-hidden="true">{{ $initial }}</span>
                <span class="vendor-store-card-name"><bdi>{{ $displayName }}</bdi></span>
            </div>

            <ul class="pc-navbar">
                @foreach ($links as $link)
                    {{-- Exact route match: each page is current only on its own route. --}}
                    @php($isCurrent = request()->routeIs($link['route']))
                    <li class="pc-item {{ $isCurrent ? 'active' : '' }}" data-nav-state="link">
                        <a href="{{ route($link['route']) }}" class="pc-link" @if ($isCurrent) aria-current="page" @endif>
                            <span class="pc-micon"><i class="ph-duotone {{ $link['icon'] }}" aria-hidden="true"></i></span>
                            <span class="pc-mtext">{{ t($link['key'], $link['default']) }}</span>
                        </a>
                    </li>
                @endforeach

                @foreach ($comingSoon as $item)
                    {{-- Not available yet: no visible badge; screen readers still hear that the section is coming. --}}
                    <li class="pc-item vendor-nav-disabled" data-nav-state="disabled">
                        <span class="pc-link" aria-disabled="true">
                            <span class="pc-micon"><i class="ph-duotone {{ $item['icon'] }}" aria-hidden="true"></i></span>
                            <span class="pc-mtext">{{ t($item['key'], $item['default']) }}</span>
                            <span class="sr-only">({{ $soonLabel }})</span>
                        </span>
                    </li>
                @endforeach
            </ul>

            <div class="vendor-nav-footer">
                <form method="POST" action="{{ route('vendor.logout') }}">
                    @csrf
                    <button type="submit" class="vendor-nav-logout">
                        <i class="ph-duotone ph-sign-out rtl:-scale-x-100" aria-hidden="true"></i>
                        <span>{{ t('vendor.Sign_Out', 'Sign out') }}</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
    {{-- Mobile drawer overlay, shown by resources/js/vendor-dashboard.js. --}}
    <div class="pc-menu-overlay" data-vendor-overlay hidden></div>
</nav>
