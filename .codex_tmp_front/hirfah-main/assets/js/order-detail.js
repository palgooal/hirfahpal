$(function () {
  const $orderDetailPage = $('[data-order-detail-page]');
  if (!$orderDetailPage.length) return;

  $orderDetailPage.on('click', '[data-confirm-receipt-button]', function () {
    const $button = $(this);
    const $vendorGroup = $button.closest('.cart-vendor-group');
    const $timeline = $vendorGroup.find('.order-status-timeline');
    const $statusBadge = $vendorGroup.find('[data-status-badge]');

    $vendorGroup.attr('data-order-status', 'completed');

    $timeline.find('.order-status-step').each(function () {
      const $step = $(this);
      $step.removeClass('is-current is-pending').addClass('is-complete');
      $step.find('.order-status-dot')
        .removeClass('border border-line bg-canvas text-muted bg-copper')
        .addClass('bg-olive text-surface')
        .html('<i data-lucide="check" class="h-3.5 w-3.5"></i>');
      $step.find('.order-status-line').removeClass('bg-line').addClass('bg-olive');
      $step.find('span.text-xs').removeClass('text-copper text-muted font-bold font-medium').addClass('text-ink font-semibold');
    });

    if ($statusBadge.length) {
      $statusBadge
        .removeClass('border-sage/40 bg-sage/20 text-olive')
        .addClass('border-olive/20 bg-olive/10 text-olive')
        .text('مكتمل');
    }

    $vendorGroup.find('[data-confirm-receipt-block]').addClass('hidden');
    $vendorGroup.find('[data-confirmed-block]').removeClass('hidden').addClass('flex');

    if (window.lucide) {
      window.lucide.createIcons();
    }
  });
});
