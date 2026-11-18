import { locationModel, locationState } from './storefront-address-locations.js';

const $ = window.jQuery;

/*
 * H4-M: the city select follows the chosen governorate (storefront-address-locations.js). The server renders every
 * option, so the form also works without JavaScript; here the city list is rebuilt from those options. A disabled
 * select is not submitted, and an omitted city keeps the stored one, so an empty city_id is sent in its place.
 */
function addressLocations(form) {
    const governorate = form.querySelector('#address-governorate');
    const city = form.querySelector('#address-city');
    if (!governorate || !city) return null;

    const labels = new Map();
    const groups = [];
    let unavailable = null;

    for (const option of city.options) {
        labels.set(option.value, option.textContent);
        const group = option.parentElement;

        if (group && group.tagName === 'OPTGROUP') {
            const governorateId = group.dataset.governorateId ?? '';
            let entry = groups.find((item) => item.governorateId === governorateId);
            if (!entry) {
                entry = { governorateId, cityIds: [] };
                groups.push(entry);
            }
            entry.cityIds.push(option.value);
        } else if (option.value !== '') {
            unavailable = { cityId: option.value, governorateId: option.dataset.governorateId ?? '' };
        }
    }

    // The pair the page opened with (the stored or submitted values) is restored on closing and on reopening.
    const state = locationState(locationModel(groups, unavailable), { governorateId: governorate.value, cityId: city.value });
    const emptyCity = document.createElement('input');
    emptyCity.type = 'hidden';
    emptyCity.name = 'city_id';
    emptyCity.value = '';
    city.after(emptyCity);

    const render = (view) => {
        governorate.value = view.governorateId;
        city.replaceChildren(...view.cityOptions.map((value) => new Option(labels.get(value) ?? value, value)));
        city.value = view.cityId;
        city.disabled = view.cityDisabled;
        emptyCity.disabled = !view.sendEmptyCity;
    };

    governorate.addEventListener('change', () => render(state.governorateChanged(governorate.value)));
    city.addEventListener('change', () => render(state.cityChanged(city.value)));
    render(state.view());

    return { restore: () => render(state.restore()) };
}

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
        const locations = addressLocations($addressForm[0]);

        // Open/close only: saving, editing and deleting addresses stay deferred until the backend exists.
        function openAddressModal(title) {
            $addressModalTitle.text(title);
            locations?.restore();
            $addressModal.removeClass('hidden');
            $('body').addClass('overflow-hidden');
        }

        // reset() alone would not restore the rebuilt city options, so the opening pair is rendered again.
        function closeAddressModal() {
            $addressModal.addClass('hidden');
            $('body').removeClass('overflow-hidden');
            $addressForm[0].reset();
            locations?.restore();
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
