const $ = window.jQuery;

if ($) {
    $(function () {
        // Favorites filtering binds only on the Storefront favorites page.
        const $favoritesPage = $('[data-favorites-page]');
        if (!$favoritesPage.length) return;
        if ($favoritesPage.data('storefrontFavoritesInitialized')) return;
        $favoritesPage.data('storefrontFavoritesInitialized', true);

        const $favoritesSearch = $('#favoritesSearch');
        const $favoritesVendorFilter = $('#favoritesVendorFilter');
        const $favoritesResultsSummary = $('#favoritesResultsSummary');
        const $favoritesNoMatchState = $('#favoritesNoMatchState');
        const $favoriteCards = $('[data-favorites-grid] .favorite-card');

        // Local filtering of the static cards only: removing favorites and the empty state stay deferred.
        function runFavoritesFilter() {
            const favoritesQuery = ($favoritesSearch.val() || '').trim().toLowerCase();
            const favoritesVendor = $favoritesVendorFilter.val() || 'all';
            let favoritesVisibleCount = 0;

            $favoriteCards.each(function () {
                const $favoriteCard = $(this);
                const matchesVendor = favoritesVendor === 'all' || $favoriteCard.data('vendor') === favoritesVendor;
                const matchesQuery = !favoritesQuery || $favoriteCard.text().toLowerCase().includes(favoritesQuery);
                const isVisible = matchesVendor && matchesQuery;

                $favoriteCard.toggle(isVisible);
                if (isVisible) favoritesVisibleCount += 1;
            });

            const favoritesTotalCount = $favoriteCards.length;
            $favoritesResultsSummary.html('عرض <bdi>' + favoritesVisibleCount + '</bdi> من <bdi>' + favoritesTotalCount + '</bdi> قطعة');
            $favoritesNoMatchState
                .toggleClass('hidden', favoritesTotalCount === 0 || favoritesVisibleCount > 0)
                .toggleClass('flex', favoritesTotalCount > 0 && favoritesVisibleCount === 0);
        }

        $favoritesSearch.on('input', runFavoritesFilter);
        $favoritesVendorFilter.on('change', runFavoritesFilter);
    });
}
