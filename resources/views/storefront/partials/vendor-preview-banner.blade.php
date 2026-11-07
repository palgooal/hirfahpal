{{--
    PREVIEW-02 / PREVIEW-03: the vendor's private preview bar, in place of the storefront's top bar, header, search and
    footer (their links lead to public pages a closed site does not show, and the header holds the cart). Rendered only
    when the page is given $preview (VendorDashboard\StorefrontPreviewController). Texts in the page language.
    - A product ($previewMode 'product', the default): the page is private, buying is turned off, and the product's
      status as a customer experiences it ($previewStatus), never the administration's reason or dates. Back to the
      products list.
    - The store ($previewMode 'store'): how customers see (or, while the site is not open, will see) the store page, and
      how many of the vendor's products it leaves out ($previewHiddenCount, a count only). Back to My Store.
--}}
@php
    $previewIsStore = ($previewMode ?? 'product') === 'store';
    $previewStatusText = $previewIsStore ? null : match ($previewStatus ?? null) {
        'visible' => t('vendor.Preview_Status_Visible', 'Published, and shown to customers while the store is open.'),
        'draft' => t('vendor.Preview_Status_Draft', 'Draft, not shown to customers.'),
        'inactive' => t('vendor.Preview_Status_Inactive', 'Inactive, not shown to customers.'),
        'out_of_stock' => t('vendor.Preview_Status_Out_Of_Stock', 'Out of stock, not shown to customers.'),
        'suspended' => t('vendor.Preview_Status_Suspended', 'Suspended by the administration, not shown to customers until the suspension is lifted.'),
        'archived' => t('vendor.Preview_Status_Archived', 'Archived by the administration, not shown to customers.'),
        default => null,
    };
@endphp
<div role="region" aria-labelledby="vendor-preview-title" data-vendor-preview="{{ $previewIsStore ? 'store' : 'product' }}" class="bg-ink text-white">
    <div class="mx-auto flex max-w-[1216px] flex-wrap items-center justify-between gap-3 px-4 py-3 lg:px-8">
        <div class="min-w-0">
            @if ($previewIsStore)
                <p id="vendor-preview-title" class="text-sm font-bold leading-6">{{ t('vendor.Preview_Store_Title', 'My store preview') }}</p>
                <p class="text-xs leading-5 text-white/85" data-preview-site="{{ ($previewSiteClosed ?? false) ? 'closed' : 'open' }}">{{ ($previewSiteClosed ?? false)
                    ? t('vendor.Preview_Store_Text_Closed', 'This is how your store page will look to customers when Hirfah opens. Visitors cannot see it yet; buying, the cart and favourites are turned off in the preview.')
                    : t('vendor.Preview_Store_Text_Open', 'This is how your store page looks to customers. Only you see this preview; buying, the cart and favourites are turned off in it.') }}</p>
                @if (($previewHiddenCount ?? 0) > 0)
                    <p class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs leading-5" data-preview-hidden="{{ $previewHiddenCount }}">
                        <span class="rounded-full bg-copper px-2.5 py-0.5 font-bold text-white">{{ str_replace(':count', (string) $previewHiddenCount, t('vendor.Preview_Store_Hidden', 'Products not shown to visitors: :count')) }}</span>
                        <span class="text-white/85">{{ t('vendor.Preview_Store_Hidden_Hint', 'Products that are not published or not available are left out of your store page. Review them on the Products page.') }}</span>
                    </p>
                @endif
            @else
                <p id="vendor-preview-title" class="text-sm font-bold leading-6">{{ t('vendor.Preview_Banner_Title', 'Private preview') }}</p>
                <p class="text-xs leading-5 text-white/85">{{ t('vendor.Preview_Banner_Text', 'Only you can see this page; visitors cannot. Buying, the cart and favourites are turned off in the preview.') }}</p>
                @if ($previewStatusText)
                    <p class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs leading-5" data-preview-status="{{ $previewStatus }}">
                        <span class="font-semibold">{{ t('vendor.Preview_Status_Label', 'Product status:') }}</span>
                        <span @class(['rounded-full px-2.5 py-0.5 font-bold', 'bg-sage text-ink' => $previewStatus === 'visible', 'bg-copper text-white' => $previewStatus !== 'visible'])>{{ $previewStatusText }}</span>
                    </p>
                @endif
            @endif
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{-- PREVIEW-04: from a product's preview to the store's preview (both private). --}}
            @unless ($previewIsStore)
                <a href="{{ route('vendor.dashboard.preview.store') }}" data-preview-store-link class="inline-flex min-h-10 items-center rounded-xl border border-white/40 px-4 text-xs font-bold text-white transition-colors hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">{{ t('vendor.Preview_My_Store', 'Preview my store') }}</a>
            @endunless
            <a href="{{ $previewIsStore ? route('vendor.dashboard.my-store') : route('vendor.dashboard.products.page') }}" class="inline-flex min-h-10 items-center rounded-xl bg-surface px-4 text-xs font-bold text-ink transition-colors hover:bg-canvas focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">{{ $previewIsStore ? t('vendor.Preview_Back_To_My_Store', 'Back to My Store') : t('vendor.Product_Back_To_List', 'Back to products') }}</a>
        </div>
    </div>
</div>
