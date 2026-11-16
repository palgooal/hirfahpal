/*
 * HIRFAH customer address location (H4-M): the address's governorate and optional city in the approved hierarchy,
 * Governorate → City. No DOM access: resources/js/storefront-addresses.js reads the server-rendered options and renders
 * the city select, and Node tests the rules (tests/js/storefront-address-locations.test.mjs).
 *
 * Values are the selects' strings ('' for none). The pair the page opened with is kept exactly as it is, even an
 * inactive, mismatched or legacy one (a city without a governorate), until the customer changes the governorate: then a
 * city outside the new governorate is cleared, and no governorate means no city. A city is never chosen for the
 * customer. The server stays authoritative (LocationPair).
 */

/**
 * The city options the server rendered: the active cities of each active governorate, and the stored city the active
 * lists no longer offer (or null), with its own governorate ('' for none).
 *
 * @param {Array<{governorateId: string, cityIds: string[]}>} groups
 * @param {{cityId: string, governorateId: string}|null} unavailable
 */
export function locationModel(groups, unavailable = null) {
    const cities = new Map();

    for (const group of Array.isArray(groups) ? groups : []) {
        cities.set(String(group.governorateId), (group.cityIds ?? []).map(String));
    }

    return {
        cities,
        unavailable: unavailable && unavailable.cityId !== ''
            ? { cityId: String(unavailable.cityId), governorateId: String(unavailable.governorateId ?? '') }
            : null,
    };
}

/** Whether the city belongs to the governorate (the stored unavailable city by its own governorate). */
export function cityBelongs(model, cityId, governorateId) {
    if (cityId === '' || governorateId === '') {
        return false;
    }

    if ((model.cities.get(governorateId) ?? []).includes(cityId)) {
        return true;
    }

    return model.unavailable?.cityId === cityId && model.unavailable.governorateId === governorateId;
}

/** The city kept after the customer chose another governorate (or none): the same city if it belongs, otherwise none. */
export function cityAfterGovernorateChange(model, governorateId, cityId) {
    return cityBelongs(model, cityId, governorateId) ? cityId : '';
}

/**
 * The city option values to show, the empty option first: the selected city when it is not among the governorate's
 * cities (a stored pair kept as it is), the stored unavailable city of this governorate, then its active cities.
 */
export function cityOptionValues(model, governorateId, cityId) {
    const listed = governorateId === '' ? [] : (model.cities.get(governorateId) ?? []);
    const values = [''];

    if (cityId !== '' && !listed.includes(cityId)) {
        values.push(cityId);
    }

    const unavailable = model.unavailable?.cityId;
    if (unavailable && model.unavailable.governorateId === governorateId && governorateId !== '' && !values.includes(unavailable) && !listed.includes(unavailable)) {
        values.push(unavailable);
    }

    return [...values, ...listed];
}

/** The city select is disabled only while there is no governorate and no city (a stored city is never hidden). */
export function cityDisabled(governorateId, cityId) {
    return governorateId === '' && cityId === '';
}

/**
 * The form's location from the pair it opened with: what to render after each change, and the opening pair again on
 * restore() (closing, resetting or reopening the form). A disabled select is not submitted and an omitted city keeps the
 * stored one, so the view says when an empty city must be sent instead (sendEmptyCity).
 */
export function locationState(model, initial) {
    const opening = { governorateId: String(initial?.governorateId ?? ''), cityId: String(initial?.cityId ?? '') };
    let current = { ...opening };

    const view = () => ({
        governorateId: current.governorateId,
        cityId: current.cityId,
        cityOptions: cityOptionValues(model, current.governorateId, current.cityId),
        cityDisabled: cityDisabled(current.governorateId, current.cityId),
        sendEmptyCity: cityDisabled(current.governorateId, current.cityId),
    });

    return {
        view,
        governorateChanged(governorateId) {
            current = { governorateId, cityId: cityAfterGovernorateChange(model, governorateId, current.cityId) };

            return view();
        },
        cityChanged(cityId) {
            current = { governorateId: current.governorateId, cityId };

            return view();
        },
        restore() {
            current = { ...opening };

            return view();
        },
    };
}
