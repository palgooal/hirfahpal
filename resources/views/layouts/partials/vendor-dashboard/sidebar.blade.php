@php
    // Browser pages use the existing JSON endpoints without exposing those API URLs
    // directly in navigation.
    $links = [
        ['route' => 'vendor.dashboard', 'key' => 'vendor.Nav_Dashboard', 'default' => 'Dashboard', 'icon' => 'ph-squares-four'],
        ['route' => 'vendor.dashboard.my-store', 'key' => 'vendor.Nav_My_Store', 'default' => 'My Store', 'icon' => 'ph-storefront'],
        ['route' => 'vendor.dashboard.products.page', 'key' => 'vendor.Nav_Products', 'default' => 'Products', 'icon' => 'ph-package'],
        ['route' => 'vendor.dashboard.orders.page', 'key' => 'vendor.Nav_Orders', 'default' => 'Orders', 'icon' => 'ph-receipt'],
        ['route' => 'vendor.dashboard.reviews.page', 'key' => 'vendor.Nav_Reviews', 'default' => 'Reviews', 'icon' => 'ph-star'],
        ['route' => 'vendor.dashboard.returns.page', 'key' => 'vendor.Nav_Returns', 'default' => 'Returns', 'icon' => 'ph-arrow-u-up-left'],
        ['route' => 'vendor.dashboard.disputes.page', 'key' => 'vendor.Nav_Disputes', 'default' => 'Disputes', 'icon' => 'ph-scales'],
        ['route' => 'vendor.dashboard.commissions.page', 'key' => 'vendor.Nav_Finance', 'default' => 'Finance', 'icon' => 'ph-wallet'],
    ];
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
                @if ($logo)<img src="{{ $logo }}" alt="" class="vendor-monogram object-fit-cover">@else<span class="vendor-monogram" aria-hidden="true">{{ $initial }}</span>@endif
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
