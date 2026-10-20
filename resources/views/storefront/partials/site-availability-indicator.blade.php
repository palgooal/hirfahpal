{{--
    SITE-AVAILABILITY Phase 3C (docs/deployment/HIRFAH-SITE-AVAILABILITY-DESIGN.md §7.4): a compact, non-dismissable
    bar for an admin previewing the closed storefront. Rendered only when EnsureSiteIsAvailable let an active Super
    Admin or maintenance.bypass admin through in coming_soon or maintenance (it sets the request attribute; customers,
    vendors on the allowlist and an open site never get it). No query: the state comes from that attribute. In the page
    flow at the top (not fixed), so it never covers the navigation, buttons or forms. Arabic in the view, like the
    other storefront views.
--}}
@php
    $previewState = request()->attributes->get(App\Http\Middleware\EnsureSiteIsAvailable::PREVIEW_ATTRIBUTE);
@endphp

@if (in_array($previewState, ['coming_soon', 'maintenance'], true))
    <div role="note" dir="rtl" lang="ar" data-site-availability-preview="{{ $previewState }}" class="bg-ink px-4 py-2 text-center text-sm font-semibold leading-6 text-white">
        @if ($previewState === 'coming_soon')
            الموقع في وضع «قريبًا» ومغلق أمام الزوار — تشاهده لأنك مشرف في وضع المعاينة.
        @else
            الموقع في وضع الصيانة ومغلق أمام الزوار — تشاهده لأنك مشرف في وضع المعاينة.
        @endif
    </div>
@endif
