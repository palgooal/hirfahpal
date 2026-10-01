const $ = window.jQuery;

if ($) {
    $(function () {
        // Address modal UI binds only on the Storefront addresses page.
        const $addressModal = $('#addressModal');
        if (!$('[data-profile-page]').length || !$addressModal.length) return;
        if ($addressModal.data('storefrontAddressesInitialized')) return;
        $addressModal.data('storefrontAddressesInitialized', true);

        const $addressForm = $('#addressForm');
        const $addressModalTitle = $('#addressModalTitle');
        const defaultAddressModalTitle = $addressModalTitle.text();

        // Open/close only: saving, editing and deleting addresses stay deferred until the backend exists.
        function openAddressModal(title) {
            $addressModalTitle.text(title);
            $addressModal.removeClass('hidden');
            $('body').addClass('overflow-hidden');
        }

        function closeAddressModal() {
            $addressModal.addClass('hidden');
            $('body').removeClass('overflow-hidden');
            $addressForm[0].reset();
            $addressModalTitle.text(defaultAddressModalTitle);
        }

        $('[data-open-address-modal]').on('click', function () {
            openAddressModal(defaultAddressModalTitle);
        });
        $('[data-edit-address]').on('click', function () {
            openAddressModal('تعديل العنوان');
        });

        $('[data-close-address-modal]').on('click', closeAddressModal);
        $addressModal.on('click', function (event) {
            if ($(event.target).is('#addressModal')) {
                closeAddressModal();
            }
        });
        $(document).on('keydown.storefrontAddresses', function (event) {
            if (event.key === 'Escape' && !$addressModal.hasClass('hidden')) {
                closeAddressModal();
            }
        });
    });
}
