$(function () {
  const $ordersPage = $('[data-orders-page]');
  if (!$ordersPage.length) return;

  const $noMatchState = $('#ordersNoMatchState');
  const $resultsSummary = $('#ordersResultsSummary');
  const $search = $('#ordersSearch');
  const $statusFilter = $('#ordersStatusFilter');
  const $orderCards = $('#ordersPopulatedState [data-order-id]');

  function runOrdersFilter() {
    const query = ($search.val() || '').trim().toLowerCase();
    const status = $statusFilter.val() || 'all';
    let visibleCount = 0;

    $orderCards.each(function () {
      const $card = $(this);
      const orderId = ($card.attr('data-order-id') || '').toLowerCase();
      const vendorNames = ($card.attr('data-vendor-names') || '').toLowerCase();
      const statusText = $card.attr('data-order-status-text') || '';
      const matchesQuery = !query || orderId.includes(query) || vendorNames.includes(query);
      const matchesStatus = status === 'all' || statusText.includes(status);
      const isVisible = matchesQuery && matchesStatus;

      $card.toggle(isVisible);
      if (isVisible) visibleCount += 1;
    });

    const totalCount = $orderCards.length;
    $resultsSummary.html('عرض <bdi>' + visibleCount + '</bdi> من <bdi>' + totalCount + '</bdi> طلب');
    $noMatchState.toggleClass('hidden', totalCount === 0 || visibleCount > 0).toggleClass('flex', totalCount > 0 && visibleCount === 0);
  }

  $search.on('input', runOrdersFilter);
  $statusFilter.on('change', runOrdersFilter);

  runOrdersFilter();
});
