/*
 * HIRFAH vendor "My Store" page (VUI-03B). Consumes the existing Palgoals profile
 * endpoints unchanged:
 *   GET vendor.dashboard.profile.show   - initial, authoritative values
 *   PUT vendor.dashboard.profile.update - replacement-style update
 *
 * The update replaces fields that are omitted (and regenerates the slug), so
 * every save sends the five editable fields plus the current email, phone,
 * slug, governorate_id and city_id, kept in memory from the initial read.
 * avatar/logo/cover_image are never sent: the backend keeps them when omitted.
 * Save stays disabled until that read succeeded. Nothing is stored, logged or
 * written into DOM attributes, and values are set with value/textContent only.
 */

const root = document.querySelector('[data-vendor-my-store]');

/** Editable fields and the client-side constraints the backend is known to enforce (UX only). */
const EDITABLE = {
    name: { required: true, max: 255 },
    store_name: { required: true, max: 255 },
    short_description: { required: false, max: 255 },
    description: { required: false, max: null },
    address_line: { required: false, max: 255 },
};

const isString = (value) => typeof value === 'string';
const isStringOrNull = (value) => value === null || isString(value);

/** A foreign key as an integer, or null; numeric strings are accepted, anything else is invalid. */
function toId(value) {
    if (value === null) {
        return null;
    }

    const id = typeof value === 'string' && /^\d+$/.test(value) ? Number(value) : value;

    return Number.isInteger(id) ? id : undefined;
}

/** Reads only the approved subset of the profile response; returns null if anything required is missing. */
function pickProfile(payload) {
    const vendor = payload?.vendor;
    const profile = vendor?.profile;

    if (!vendor || !profile) {
        return null;
    }

    const governorateId = toId(profile.governorate_id ?? null);
    const cityId = toId(profile.city_id ?? null);

    const valid = isString(vendor.name)
        && isString(vendor.phone)
        && isStringOrNull(vendor.email ?? null)
        && isString(profile.store_name)
        && isString(profile.slug)
        && isStringOrNull(profile.short_description ?? null)
        && isStringOrNull(profile.description ?? null)
        && isStringOrNull(profile.address_line ?? null)
        && governorateId !== undefined
        && cityId !== undefined;

    if (!valid) {
        return null;
    }

    return {
        editable: {
            name: vendor.name,
            store_name: profile.store_name,
            short_description: profile.short_description ?? '',
            description: profile.description ?? '',
            address_line: profile.address_line ?? '',
        },
        preserved: {
            email: vendor.email ?? null,
            phone: vendor.phone,
            slug: profile.slug,
            governorate_id: governorateId,
            city_id: cityId,
        },
        display: {
            email: vendor.email ?? null,
            phone: vendor.phone,
            governorate: isString(profile.governorate?.name) ? profile.governorate.name : null,
            city: isString(profile.city?.name) ? profile.city.name : null,
        },
    };
}

function initMyStore() {
    const i18n = JSON.parse(root.querySelector('[data-store-i18n]').textContent);
    const form = root.querySelector('[data-store-form]');
    const loadError = root.querySelector('[data-store-load-error]');
    const saveButton = root.querySelector('[data-store-save]');
    const saveLabel = root.querySelector('[data-save-label]');
    const status = root.querySelector('[data-store-status]');
    const counter = root.querySelector('[data-counter-for="short_description"] bdi');
    const token = form.querySelector('input[name="_token"]').value;
    const defaultSaveLabel = saveLabel.textContent;

    const field = (name) => form.elements.namedItem(name);
    const errorFor = (name) => root.querySelector(`[data-error-for="${name}"]`);

    // Preservation state: in memory only, set once by a successful read.
    let preserved = null;
    let saving = false;

    const setStatus = (message, tone = '') => {
        status.textContent = message;
        status.dataset.tone = tone;
    };

    const setFieldsEnabled = (enabled) => {
        Object.keys(EDITABLE).forEach((name) => {
            field(name).disabled = !enabled;
        });
        saveButton.disabled = !enabled || preserved === null;
    };

    const clearErrors = () => {
        Object.keys(EDITABLE).forEach((name) => {
            field(name).removeAttribute('aria-invalid');
            errorFor(name).hidden = true;
            errorFor(name).textContent = '';
        });
    };

    const showFieldError = (name, message) => {
        field(name).setAttribute('aria-invalid', 'true');
        errorFor(name).textContent = message;
        errorFor(name).hidden = false;
    };

    /** Our own wording for a field: required, too long, or a neutral fallback. */
    const messageFor = (name) => {
        const value = field(name).value.trim();
        const rules = EDITABLE[name];

        if (rules.required && value === '') {
            return i18n.required;
        }

        if (rules.max !== null && value.length > rules.max) {
            return i18n.tooLong;
        }

        return i18n.invalid;
    };

    const updateCounter = () => {
        counter.textContent = `${field('short_description').value.length} / ${EDITABLE.short_description.max}`;
    };

    const setReadonly = (key, value) => {
        const target = root.querySelector(`[data-readonly="${key}"]`);
        const hasValue = isString(value) && value.trim() !== '';
        const text = document.createElement(hasValue ? 'bdi' : 'span');

        text.textContent = hasValue ? value : i18n.notSpecified;
        if (!hasValue) {
            text.className = 'vendor-readonly-empty';
        }
        target.replaceChildren(text);
    };

    const showLoading = () => {
        root.dataset.state = 'loading';
        loadError.hidden = true;
        form.hidden = false;
        form.setAttribute('aria-busy', 'true');
        setFieldsEnabled(false);
        setStatus('');
    };

    const showLoadError = () => {
        root.dataset.state = 'load-error';
        preserved = null;
        form.hidden = true;
        loadError.hidden = false;
        setFieldsEnabled(false);
    };

    const load = async () => {
        showLoading();

        try {
            const response = await fetch(root.dataset.profileUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (!response.ok) {
                throw new Error('profile-unavailable');
            }

            const profile = pickProfile(await response.json());

            if (profile === null) {
                throw new Error('profile-incomplete');
            }

            Object.entries(profile.editable).forEach(([name, value]) => {
                field(name).value = value;
            });
            Object.entries(profile.display).forEach(([key, value]) => setReadonly(key, value));
            updateCounter();

            preserved = profile.preserved;
            root.dataset.state = 'ready';
            form.setAttribute('aria-busy', 'false');
            setFieldsEnabled(true);
        } catch {
            showLoadError();
        }
    };

    const validate = () => {
        const invalid = Object.keys(EDITABLE).filter((name) => {
            const value = field(name).value.trim();
            const rules = EDITABLE[name];

            return (rules.required && value === '') || (rules.max !== null && value.length > rules.max);
        });

        invalid.forEach((name) => showFieldError(name, messageFor(name)));

        return invalid;
    };

    /** The full replacement payload: editable values plus the preserved current values. */
    const payload = () => {
        const value = (name) => field(name).value.trim();
        const optional = (name) => (value(name) === '' ? null : value(name));

        return {
            name: value('name'),
            store_name: value('store_name'),
            short_description: optional('short_description'),
            description: optional('description'),
            address_line: optional('address_line'),
            email: preserved.email,
            phone: preserved.phone,
            slug: preserved.slug,
            governorate_id: preserved.governorate_id,
            city_id: preserved.city_id,
        };
    };

    const finishSaving = () => {
        saving = false;
        saveLabel.textContent = defaultSaveLabel;
        form.setAttribute('aria-busy', 'false');
        setFieldsEnabled(true);
    };

    const save = async () => {
        if (saving || preserved === null) {
            return;
        }

        clearErrors();
        const invalid = validate();

        if (invalid.length > 0) {
            setStatus(i18n.fixErrors, 'error');
            field(invalid[0]).focus();

            return;
        }

        saving = true;
        saveLabel.textContent = i18n.saving;
        form.setAttribute('aria-busy', 'true');
        setFieldsEnabled(false);
        setStatus(i18n.saving);

        let response;

        try {
            response = await fetch(root.dataset.updateUrl, {
                method: 'PUT',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token,
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload()),
            });
        } catch {
            finishSaving();
            setStatus(i18n.saveError, 'error');

            return;
        }

        if (response.ok) {
            // Confirmed by the server: reload so the page and the shell show the saved values.
            setStatus(i18n.saved, 'success');
            window.setTimeout(() => window.location.reload(), 900);

            return;
        }

        finishSaving();

        if (response.status === 422) {
            let errors = {};

            try {
                errors = (await response.json())?.errors ?? {};
            } catch {
                errors = {};
            }

            const fields = Object.keys(errors).filter((name) => Object.hasOwn(EDITABLE, name));
            const other = Object.keys(errors).some((name) => !Object.hasOwn(EDITABLE, name));

            fields.forEach((name) => showFieldError(name, messageFor(name)));

            // An error on a preserved/read-only value cannot be fixed here: general message only.
            if (other || fields.length === 0) {
                setStatus(i18n.saveError, 'error');
            } else {
                setStatus(i18n.fixErrors, 'error');
                field(fields[0]).focus();
            }

            return;
        }

        if (response.status === 401 || response.status === 419) {
            setStatus(i18n.sessionError, 'error');
        } else if (response.status === 403) {
            setStatus(i18n.accessError, 'error');
        } else {
            setStatus(i18n.saveError, 'error');
        }
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        save();
    });

    form.addEventListener('input', (event) => {
        const name = event.target.name;

        if (Object.hasOwn(EDITABLE, name)) {
            event.target.removeAttribute('aria-invalid');
            errorFor(name).hidden = true;
        }

        if (name === 'short_description') {
            updateCounter();
        }
    });

    root.querySelector('[data-store-retry]').addEventListener('click', load);

    load();
}

if (root) {
    initMyStore();
}
