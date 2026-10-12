@php
    // AD-UI-01D-6: the page title is resolved once here and shared by the header (<h1>) and <title>.
    // Order: an explicit $pageTitle (named slot or string), the label of the matching sidebar entry
    // (same route patterns and keys as nav.blade.php), then the brand subtitle.
    $sidebarTitles = [
        'dashboard.home' => ['dashboard.Nav_Home', 'Home'],
        'dashboard.vendors.*' => ['dashboard.Nav_Vendors', 'Vendors'],
        'dashboard.customers.*' => ['dashboard.Nav_Customers', 'Customers'],
        'dashboard.delivery-drivers.*' => ['dashboard.Nav_Delivery_Drivers', 'Delivery drivers'],
        'dashboard.admins.*' => ['dashboard.Nav_Admins_Permissions', 'Admins and permissions'],
        'dashboard.categories.*' => ['dashboard.Nav_Categories', 'Categories'],
        'dashboard.products.*' => ['dashboard.Nav_Products', 'Products'],
        'dashboard.orders.*' => ['dashboard.Nav_Orders', 'Orders'],
        'dashboard.vendor-orders.*' => ['dashboard.Nav_Vendor_Orders', 'Vendor orders'],
        'dashboard.reviews.*' => ['dashboard.Nav_Reviews', 'Reviews'],
        'dashboard.disputes.*' => ['dashboard.Nav_Disputes', 'Disputes'],
        'dashboard.return-requests.*' => ['dashboard.Nav_Return_Requests', 'Return requests'],
        'dashboard.languages.*' => ['dashboard.Nav_Languages', 'Languages'],
        'dashboard.translation-values.*' => ['dashboard.Nav_Translations', 'Translations'],
        'dashboard.setting.*' => ['dashboard.Nav_Settings', 'Settings'],
        'media-library.*' => ['dashboard.Nav_Media_Library', 'Media Library'],
    ];

    // A slot arrives as HTML: keep its text only; the result is escaped again on output.
    $adminPageTitle = $pageTitle ?? null;
    $adminPageTitle = $adminPageTitle instanceof \Illuminate\Contracts\Support\Htmlable
        ? html_entity_decode(strip_tags($adminPageTitle->toHtml()), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        : (is_string($adminPageTitle) ? $adminPageTitle : null);
    $adminPageTitle = trim((string) $adminPageTitle) !== '' ? trim($adminPageTitle) : null;

    if ($adminPageTitle === null) {
        foreach ($sidebarTitles as $pattern => [$titleKey, $titleDefault]) {
            if (request()->routeIs($pattern)) {
                $adminPageTitle = t($titleKey, $titleDefault);
                break;
            }
        }
    }

    $adminPageTitle ??= t('dashboard.Nav_Brand_Subtitle', 'Admin panel');
    $adminBrandName = t('dashboard.Brand_Name', 'Hirfah');
    $adminDocumentTitle = str_contains($adminPageTitle, $adminBrandName) ? $adminPageTitle : $adminPageTitle.' | '.$adminBrandName;
@endphp
@include('layouts.partials.dashboard.head')
    <!-- [ Sidebar Menu ] start -->
    @include('layouts.partials.dashboard.nav')
    <!-- [ Sidebar Menu ] end -->
    <!-- [ Header Topbar ] start -->
    @include('layouts.partials.dashboard.header')
    <!-- [ Header ] end -->

    <!-- [ Main Content ] start -->
    <div class="pc-container">

        <div class="pc-content">
            <div class="row">
                @if ($errors->any())
                    <div class="alert alert-danger" >
                        <ol>
                            @foreach ($errors->getMessages() as $key => $val)
                                <li>{{ $key . " : " . $val[0] }} </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </div>
            <!-- [ breadcrumb ] start -->
            <div class="page-header">
                <div class="page-block">
                    <ul class="breadcrumb">
                        {{ $breadcrumbs ?? ''}}
                    </ul>
                </div>
            </div>
            <!-- [ breadcrumb ] end -->
            <!-- [ Main Content ] start -->
            <div class="grid grid-cols-12 gap-x-6">

                {{ $slot }}
            </div>
            <!-- [ Main Content ] end -->
        </div>
    </div>
    <!-- [ Main Content ] end -->


    @include('layouts.partials.dashboard.footer')

@include('layouts.partials.dashboard.end')
