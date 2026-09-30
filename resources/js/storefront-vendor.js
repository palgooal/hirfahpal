const $ = window.jQuery;

if ($) {
    $(function () {
        // Vendor Detail behavior binds only when the vendor storefront root is present.
        const $vendorStorefrontRoot = $('[data-vendor-storefront]');
        if (!$vendorStorefrontRoot.length) return;
        if ($vendorStorefrontRoot.data('storefrontVendorInitialized')) return;
        $vendorStorefrontRoot.data('storefrontVendorInitialized', true);

        const $vendorStorefrontChips = $vendorStorefrontRoot.find('.vendor-category-chip');
        const $vendorStorefrontGrid = $vendorStorefrontRoot.find('[data-vendor-product-grid]');
        const $vendorStorefrontCards = $vendorStorefrontGrid.find('.product-card');
        const $vendorStorefrontEmptyState = $vendorStorefrontRoot.find('[data-vendor-empty-state]');

        if (!$vendorStorefrontChips.length || !$vendorStorefrontGrid.length || !$vendorStorefrontCards.length) return;

        // Cards use jQuery's inline display toggle; the empty state uses the `hidden` class, as in the reference.
        function runVendorStorefrontFilter(vendorStorefrontFilter) {
            let vendorStorefrontVisibleCount = 0;

            $vendorStorefrontCards.each(function () {
                const $vendorStorefrontCard = $(this);
                const vendorStorefrontMatches = vendorStorefrontFilter === 'all' || $vendorStorefrontCard.data('category') === vendorStorefrontFilter;

                $vendorStorefrontCard.toggle(vendorStorefrontMatches);
                if (vendorStorefrontMatches) vendorStorefrontVisibleCount += 1;
            });

            $vendorStorefrontEmptyState.toggleClass('hidden', vendorStorefrontVisibleCount !== 0);
        }

        $vendorStorefrontChips.on('click', function () {
            const $vendorStorefrontChip = $(this);
            const vendorStorefrontFilter = $vendorStorefrontChip.data('filter') || 'all';

            $vendorStorefrontChips
                .attr('aria-pressed', 'false')
                .removeClass('border-sage bg-sage font-bold text-ink shadow-sm')
                .addClass('border-line bg-surface font-semibold text-muted');
            $vendorStorefrontChip
                .attr('aria-pressed', 'true')
                .addClass('border-sage bg-sage font-bold text-ink shadow-sm')
                .removeClass('border-line bg-surface font-semibold text-muted');

            runVendorStorefrontFilter(vendorStorefrontFilter);
        });

        const vendorStorefrontInitialFilter = $vendorStorefrontChips.filter('[aria-pressed="true"]').first().data('filter') || 'all';
        runVendorStorefrontFilter(vendorStorefrontInitialFilter);
    });
}
