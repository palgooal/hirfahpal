{{--
    Vendor dashboard shell (VUI-01B). Able Pro page structure with HIRFAH
    identity; vendor navigation only. It never uses admin routes, abilities
    or the admin theme scripts.
--}}
@props(['title'])

@php
    $vendor = auth('vendor')->user();
    // The approval gate guarantees a profile here; fall back to the account name if the store name is blank.
    $displayName = filled($vendor?->profile?->store_name) ? $vendor->profile->store_name : ($vendor?->name ?? '');
    $initial = mb_strtoupper(mb_substr(trim($displayName), 0, 1));
@endphp

@include('layouts.partials.vendor-dashboard.head', ['title' => $title])

    @include('layouts.partials.vendor-dashboard.sidebar', ['displayName' => $displayName, 'initial' => $initial])

    @include('layouts.partials.vendor-dashboard.header', ['displayName' => $displayName, 'initial' => $initial])

    <div class="pc-container">
        <main class="pc-content" id="vendor-main">
            <div class="page-header vendor-page-header">
                <div class="page-block">
                    @isset($breadcrumbs)
                        <nav aria-label="{{ t('vendor.Breadcrumb', 'Breadcrumb') }}">
                            <ul class="breadcrumb">
                                {{ $breadcrumbs }}
                            </ul>
                        </nav>
                    @endisset
                    <h1 class="vendor-page-title">{{ $title }}</h1>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-6 vendor-page-grid">
                {{ $slot }}
            </div>
        </main>
    </div>

@include('layouts.partials.vendor-dashboard.end')
