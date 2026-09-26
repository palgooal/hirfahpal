$(function () {
  const $rateOrderPage = $('[data-rate-order-page]');
  if (!$rateOrderPage.length) return;

  function renderStars($group, value) {
    $group.find('.star-rating-star').each(function () {
      const $star = $(this);
      const starValue = Number($star.data('starValue'));
      const isFilled = starValue <= value;

      $star
        .attr('aria-pressed', isFilled ? 'true' : 'false')
        .toggleClass('text-copper', isFilled)
        .toggleClass('text-line', !isFilled);
    });
  }

  $rateOrderPage.on('click', '.star-rating-star', function () {
    const $star = $(this);
    const $group = $star.closest('.star-rating-input');
    const value = Number($star.data('starValue'));

    $group.find('[data-rating-value]').val(value);
    renderStars($group, value);
  });

  $('.star-rating-input').each(function () {
    renderStars($(this), 0);
  });

  const $form = $('#rateOrderForm');
  const $error = $('[data-rate-order-error]');

  $form.on('submit', function (event) {
    event.preventDefault();
    $error.addClass('hidden').text('');

    const productRating = Number($('input[name="productRating"]').val()) || 0;
    const vendorRating = Number($('input[name="vendorRating"]').val()) || 0;
    const driverRating = Number($('input[name="driverRating"]').val()) || 0;

    if (!productRating || !vendorRating || !driverRating) {
      $error.removeClass('hidden').text('الرجاء تقييم المنتج والمشغل والسائق بنجمة واحدة على الأقل قبل الإرسال.');
      return;
    }

    $form.addClass('hidden');
    $('[data-rate-order-success]').removeClass('hidden').addClass('flex');
  });
});
