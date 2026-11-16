/*
 * HIRFAH My Store location (H1-J): the store's governorate and city in the approved hierarchy, Governorate → City
 * (cities.governorate_id). No DOM access: resources/js/vendor-my-store.js renders the options, and Node tests the rules
 * (tests/js/vendor-my-store-locations.test.mjs).
 *
 * A stored value is never dropped: one the active lists no longer offer (inactive, or a legacy pair) stays listed and
 * marked, and an unchanged stored pair is always accepted, as the server accepts it (LocationPair). A changed pair needs
 * a city of the chosen governorate; a city kept after the governorate changed stays listed so the vendor can see it and
 * choose another city or no city. The server stays authoritative.
 */

/** A foreign key as an integer, or null; numeric strings are accepted, anything else is invalid (undefined). */
export function toId(value) {
    if (value === null) {
        return null;
    }

    const id = typeof value === 'string' && /^\d+$/.test(value) ? Number(value) : value;

    return Number.isInteger(id) ? id : undefined;
}

const isName = (value) => typeof value === 'string' && value.trim() !== '';
const isKey = (value) => Number.isInteger(toId(value));

/** The active governorates with their active cities (the profile read's locations), well-formed entries only. */
export function normalizeLocations(locations) {
    const governorates = Array.isArray(locations?.governorates) ? locations.governorates : [];

    return governorates
        .filter((governorate) => isKey(governorate?.id) && isName(governorate?.name))
        .map((governorate) => ({
            id: toId(governorate.id),
            name: governorate.name,
            cities: (Array.isArray(governorate.cities) ? governorate.cities : [])
                .filter((city) => isKey(city?.id) && isName(city?.name))
                .map((city) => ({ id: toId(city.id), name: city.name })),
        }));
}

/** The stored pair from the profile read, with the names to show and the stored city's own governorate. */
export function storedLocation(profile) {
    const id = (value) => toId(value ?? null) ?? null;

    return {
        governorateId: id(profile?.governorate_id),
        governorateName: isName(profile?.governorate?.name) ? profile.governorate.name : null,
        cityId: id(profile?.city_id),
        cityName: isName(profile?.city?.name) ? profile.city.name : null,
        cityGovernorateId: id(profile?.city?.governorate_id),
    };
}

/** The active city with this id and its governorate, or null. */
function activeCity(locations, cityId) {
    for (const governorate of locations) {
        const city = governorate.cities.find((item) => item.id === cityId);

        if (city) {
            return { city, governorateId: governorate.id };
        }
    }

    return null;
}

/** Whether the city belongs to the governorate, as the server checks it (the stored city may be inactive). */
export function cityBelongs(locations, stored, cityId, governorateId) {
    if (cityId === null || governorateId === null) {
        return false;
    }

    const active = activeCity(locations, cityId);

    if (active) {
        return active.governorateId === governorateId;
    }

    return cityId === stored.cityId && stored.cityGovernorateId === governorateId;
}

/**
 * Why the selection cannot be saved: 'governorate' (a city without a governorate), 'mismatch' (a city outside the
 * chosen governorate), or null. The stored pair, unchanged, is always kept.
 */
export function locationProblem(locations, stored, selection) {
    if (selection.governorateId === stored.governorateId && selection.cityId === stored.cityId) {
        return null;
    }

    if (selection.cityId === null) {
        return null;
    }

    if (selection.governorateId === null) {
        return 'governorate';
    }

    return cityBelongs(locations, stored, selection.cityId, selection.governorateId) ? null : 'mismatch';
}

/** Fills the ":name" placeholder. */
export const fillName = (template, name) => String(template ?? '').split(':name').join(name);

/**
 * The options of both selects, each {value, label}, an empty option first: the active governorates (plus the stored or
 * chosen one when it is not active), and the active cities of the chosen governorate (plus the chosen city, and the
 * stored city while the stored governorate is chosen, when they are not among them). Extra values are marked with
 * texts.unavailable. The selection is always among the options.
 */
export function locationOptions(locations, stored, selection, texts) {
    const unavailable = (name, id) => ({ value: String(id), label: fillName(texts.unavailable, name ?? String(id)) });
    const extras = (ids, listed) => [...new Set(ids)].filter((id) => id !== null && !listed.some((item) => item.id === id));

    const governorates = [
        { value: '', label: texts.selectGovernorate },
        ...extras([stored.governorateId, selection.governorateId], locations)
            .map((id) => unavailable(id === stored.governorateId ? stored.governorateName : null, id)),
        ...locations.map((governorate) => ({ value: String(governorate.id), label: governorate.name })),
    ];

    const listed = locations.find((governorate) => governorate.id === selection.governorateId)?.cities ?? [];
    const keptStored = selection.governorateId === stored.governorateId ? stored.cityId : null;
    const cityName = (id) => activeCity(locations, id)?.city.name ?? (id === stored.cityId ? stored.cityName : null);
    const cities = [
        { value: '', label: texts.selectCity },
        ...extras([keptStored, selection.cityId], listed).map((id) => unavailable(cityName(id), id)),
        ...listed.map((city) => ({ value: String(city.id), label: city.name })),
    ];

    return { governorates, cities };
}
