$(function () {
  const $cartPage = $('[data-cart-page]');
  if (!$cartPage.length) return;

  const $populatedState = $('#cartPopulatedState');
  const $emptyState = $('#cartEmptyState');

  function formatNumber(value) {
    return Math.round(value).toLocaleString('en-US');
  }

  function renderNumber(value) {
    return '<bdi>' + formatNumber(value) + '</bdi>';
  }

  function renderPrice(value) {
    return '<bdi>₪' + formatNumber(value) + '</bdi>';
  }

  function getQuantity($item) {
    return Number($item.find('.cart-qty').text()) || 1;
  }

  function getMaxQuantity($item) {
    return Number($item.data('maxQuantity')) || 5;
  }

  function updateStepperState($item) {
    const quantity = getQuantity($item);
    const maxQuantity = getMaxQuantity($item);

    $item.find('.cart-qty-decrease').prop('disabled', quantity <= 1);
    $item.find('.cart-qty-increase').prop('disabled', quantity >= maxQuantity);
  }

  function setQuantity($item, nextQuantity) {
    const maxQuantity = getMaxQuantity($item);
    const quantity = Math.min(maxQuantity, Math.max(1, nextQuantity));

    $item.find('.cart-qty').text(quantity);
    updateStepperState($item);
  }

  function updateLineTotal($item) {
    const unitPrice = Number($item.data('unitPrice')) || 0;
    const lineTotal = unitPrice * getQuantity($item);

    $item.attr('data-line-total-value', lineTotal);
    $item.find('[data-line-total]').html(renderPrice(lineTotal));

    return lineTotal;
  }

  function updateVendorSubtotal($vendorGroup) {
    let vendorSubtotal = 0;

    $vendorGroup.find('.cart-line-item').each(function () {
      vendorSubtotal += updateLineTotal($(this));
    });

    $vendorGroup.attr('data-vendor-subtotal-value', vendorSubtotal);
    $vendorGroup.find('[data-vendor-subtotal]').html(renderPrice(vendorSubtotal));

    return vendorSubtotal;
  }

  function updateCartTotals() {
    let grandTotal = 0;
    let totalQuantity = 0;
    let lineItemCount = 0;
    let vendorCount = 0;

    $('.cart-vendor-group').each(function () {
      const $vendorGroup = $(this);
      const $lineItems = $vendorGroup.find('.cart-line-item');

      if (!$lineItems.length) return;

      vendorCount += 1;
      grandTotal += updateVendorSubtotal($vendorGroup);

      $lineItems.each(function () {
        totalQuantity += getQuantity($(this));
        lineItemCount += 1;
      });
    });

    $('[data-grand-total], [data-grand-total-display], #cartTotal').html(renderPrice(grandTotal));
    $('#cartCount').html(renderNumber(totalQuantity));
    $('[data-cart-line-count]').html(renderNumber(lineItemCount));
    $('[data-cart-vendor-count]').html(renderNumber(vendorCount));

    const isEmpty = lineItemCount === 0;
    $populatedState.toggleClass('hidden', isEmpty);
    $emptyState.toggleClass('hidden', !isEmpty);
  }

  function showCartToast(message) {
    $('#toast').html(message).removeClass('-translate-y-24');
    clearTimeout(window.toastTimer);
    window.toastTimer = setTimeout(function () {
      $('#toast').addClass('-translate-y-24');
    }, 2200);
  }

  $cartPage.on('click', '.cart-qty-increase', function () {
    const $item = $(this).closest('.cart-line-item');
    setQuantity($item, getQuantity($item) + 1);
    updateCartTotals();
  });

  $cartPage.on('click', '.cart-qty-decrease', function () {
    const $item = $(this).closest('.cart-line-item');
    setQuantity($item, getQuantity($item) - 1);
    updateCartTotals();
  });

  $cartPage.on('click', '.cart-remove-item', function () {
    const $item = $(this).closest('.cart-line-item');
    const $vendorGroup = $item.closest('.cart-vendor-group');

    $item.remove();

    if (!$vendorGroup.find('.cart-line-item').length) {
      $vendorGroup.slideUp(160, function () {
        $(this).remove();
        updateCartTotals();
      });
    }

    updateCartTotals();
  });

  $('#cartButton').off('click').on('click', function (event) {
    event.preventDefault();
    const currentTotal = $('[data-grand-total-display]').html() || renderPrice(0);
    const currentQuantity = $('#cartCount').html() || renderNumber(0);
    showCartToast('في السلة ' + currentQuantity + ' قطع بقيمة ' + currentTotal);
  });

  $('.cart-line-item').each(function () {
    updateStepperState($(this));
  });

  updateCartTotals();
});
