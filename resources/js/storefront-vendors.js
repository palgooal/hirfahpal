const $ = window.jQuery;

if ($) {
    $(function () {
        // Vendors behavior binds only when the Vendors search markup is present.
        const $vendorDirectorySearch = $('#vendorSearch');
        if (!$vendorDirectorySearch.length) return;
        if ($vendorDirectorySearch.data('storefrontVendorsInitialized')) return;
        $vendorDirectorySearch.data('storefrontVendorsInitialized', true);

        const $vendorDirectoryCards = $('#vendorsGrid .vendor-card');
        const $vendorDirectoryEmptyState = $('#vendorEmptyState');

        // Plain substring match of the whole trimmed query against name, city and all card text.
        function runVendorDirectoryFilter() {
            const vendorDirectoryQuery = $vendorDirectorySearch.val().trim().toLowerCase();
            let vendorDirectoryVisibleCount = 0;

            $vendorDirectoryCards.each(function () {
                const $vendorDirectoryCard = $(this);
                const vendorDirectoryHaystack = [
                    $vendorDirectoryCard.data('name'),
                    $vendorDirectoryCard.data('city'),
                    $vendorDirectoryCard.text()
                ].join(' ').toLowerCase();
                const vendorDirectoryMatches = !vendorDirectoryQuery || vendorDirectoryHaystack.includes(vendorDirectoryQuery);

                $vendorDirectoryCard.toggle(vendorDirectoryMatches);
                if (vendorDirectoryMatches) vendorDirectoryVisibleCount += 1;
            });

            // Inline display intentionally overrides the markup's `hidden` class, as in the reference.
            $vendorDirectoryEmptyState.toggle(vendorDirectoryVisibleCount === 0);
        }

        $vendorDirectorySearch.on('input', runVendorDirectoryFilter);
        $('#clearVendorSearch').on('click', function () {
            $vendorDirectorySearch.val('').trigger('input').trigger('focus');
        });

        runVendorDirectoryFilter();
    });
}
