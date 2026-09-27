$(function () {
  const $favoritesPage = $('[data-favorites-page]');
  if (!$favoritesPage.length) return;

  const $populatedState = $('#favoritesPopulatedState');
  const $emptyState = $('#favoritesEmptyState');
  const $noMatchState = $('#favoritesNoMatchState');
  const $favoritesCount = $('#favoritesCount');
  const $favoritesVendorCount = $('#favoritesVendorCount');
  const $resultsSummary = $('#favoritesResultsSummary');
  const $search = $('#favoritesSearch');
  const $vendorFilter = $('#favoritesVendorFilter');
  const $grid = $('[data-favorites-grid]');

  function showToast(message) {
    $('#toast').text(message).removeClass('-translate-y-24');
    clearTimeout(window.toastTimer);
    window.toastTimer = setTimeout(() => $('#toast').addClass('-translate-y-24'), 2200);
  }

  function sortCardsByDateAdded() {
    const $cards = $grid.find('.favorite-card').get();
    $cards.sort((a, b) => new Date($(b).data('date-added')) - new Date($(a).data('date-added')));
    $grid.append($cards);
  }

  function runFavoritesFilter() {
    const query = ($search.val() || '').trim().toLowerCase();
    const vendor = $vendorFilter.val() || 'all';
    let visibleCount = 0;

    $grid.find('.favorite-card').each(function () {
      const $card = $(this);
      const matchesVendor = vendor === 'all' || $card.data('vendor') === vendor;
      const matchesQuery = !query || $card.text().toLowerCase().includes(query);
      const isVisible = matchesVendor && matchesQuery;

      $card.toggle(isVisible);
      if (isVisible) visibleCount += 1;
    });

    const totalCount = $grid.find('.favorite-card').length;
    $resultsSummary.html('عرض <bdi>' + visibleCount + '</bdi> من <bdi>' + totalCount + '</bdi> قطعة');
    $noMatchState.toggleClass('hidden', totalCount === 0 || visibleCount > 0).toggleClass('flex', totalCount > 0 && visibleCount === 0);
  }

  function refreshFavoritesSummary() {
    const totalCount = $grid.find('.favorite-card').length;
    const vendorCount = new Set($grid.find('.favorite-card').map(function () { return $(this).data('vendor'); }).get()).size;

    $favoritesCount.text(totalCount);
    $favoritesVendorCount.text(vendorCount);
    $populatedState.toggle(totalCount > 0);
    $emptyState.toggleClass('hidden', totalCount > 0);

    if (totalCount > 0) runFavoritesFilter();
  }

  $(document).on('click', '.remove-favorite', function () {
    const $card = $(this).closest('.favorite-card');

    $card.fadeOut(180, function () {
      $(this).remove();
      refreshFavoritesSummary();
    });

    showToast('أزيلت القطعة من المفضلة');
  });

  $search.on('input', runFavoritesFilter);
  $vendorFilter.on('change', runFavoritesFilter);

  sortCardsByDateAdded();
  refreshFavoritesSummary();
});
