const $ = window.jQuery;

if ($) {
    $(function () {
        // Checkout UI binds only on the Storefront checkout page.
        const $checkoutPage = $('[data-checkout-page]');
        if (!$checkoutPage.length) return;
        if ($checkoutPage.data('storefrontCheckoutInitialized')) return;
        $checkoutPage.data('storefrontCheckoutInitialized', true);

        // Local visual state only: placing the order stays deferred until the checkout backend is integrated.
        const paymentLabels = {
            online: 'الدفع الإلكتروني',
            cod: 'الدفع عند الاستلام'
        };

        const $confirmOrderModal = $('#confirmOrderModal');

        function getSelectedPayment() {
            return $('input[name="paymentMethod"]:checked').val() || '';
        }

        function setPrimaryReady(isReady) {
            $('#placeOrderButton')
                .attr('aria-disabled', isReady ? 'false' : 'true')
                .toggleClass('opacity-70', !isReady)
                .toggleClass('hover:bg-[#313923]', isReady);
        }

        function clearPaymentError() {
            $('#paymentError').addClass('hidden').text('');
            $('[data-payment-selector]').removeClass('border-[#A13D2B]/30 bg-[#A13D2B]/10');
        }

        function showPaymentError() {
            $('#paymentError').removeClass('hidden').text('اختر طريقة الدفع قبل تأكيد الطلب.');
            $('[data-payment-selector]').addClass('border-[#A13D2B]/30 bg-[#A13D2B]/10');
        }

        function renderPaymentSelection(value) {
            $('.payment-card').each(function () {
                const $card = $(this);
                const isSelected = $card.data('paymentValue') === value;

                $card
                    .attr('aria-checked', isSelected ? 'true' : 'false')
                    .toggleClass('border-olive bg-olive/5 shadow-card', isSelected)
                    .toggleClass('border-line bg-surface', !isSelected);
                $card.find('.payment-check').toggleClass('opacity-100', isSelected).toggleClass('scale-75 opacity-0', !isSelected);
            });

            $('[data-selected-payment]').text(paymentLabels[value] || 'لم يتم الاختيار بعد');
            setPrimaryReady(Boolean(value));
        }

        function closeModal() {
            $confirmOrderModal.addClass('hidden');
            $('body').removeClass('overflow-hidden');
        }

        function openModal() {
            $('[data-confirm-payment]').text(paymentLabels[getSelectedPayment()]);
            $confirmOrderModal.removeClass('hidden');
            $('body').addClass('overflow-hidden');
            $('#finalConfirmButton').trigger('focus');
        }

        $('input[name="addressChoice"]').on('change', function () {
            $('#newAddressForm').toggleClass('hidden', $(this).val() !== 'new');
        });

        $('.address-card').on('click keydown', function (event) {
            if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();

            $(this).find('input[name="addressChoice"]').prop('checked', true).trigger('change');

            $('.address-card').removeClass('border-olive bg-olive/5 shadow-card').addClass('border-line bg-surface');
            $(this).removeClass('border-line bg-surface').addClass('border-olive bg-olive/5 shadow-card');
        });

        $('.payment-card').on('click keydown', function (event) {
            if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();

            $('#' + $(this).attr('for')).prop('checked', true).trigger('change');
            clearPaymentError();
            renderPaymentSelection($(this).data('paymentValue'));
        });

        $('input[name="paymentMethod"]').on('change', function () {
            clearPaymentError();
            renderPaymentSelection(getSelectedPayment());
        });

        $('#placeOrderButton').on('click', function () {
            if (!getSelectedPayment()) {
                showPaymentError();
                return;
            }

            openModal();
        });

        $('[data-close-confirm-modal]').on('click', closeModal);

        $confirmOrderModal.on('click', function (event) {
            if ($(event.target).is($confirmOrderModal)) closeModal();
        });

        $(document).on('keydown', function (event) {
            if (event.key === 'Escape' && !$confirmOrderModal.hasClass('hidden')) closeModal();
        });

        // #finalConfirmButton is intentionally left without a handler: no order is created from this static page.

        renderPaymentSelection('');
    });
}
