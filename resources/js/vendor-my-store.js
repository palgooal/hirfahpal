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
 *
 * MYSTORE-UI-01C: the logo is chosen in the product editor's media picker (shared
 * dialog partial, the same initPicker()): an existing image of the vendor's own
 * library, or one uploaded there (the library endpoint). Only its media id is sent
 * (PUT vendor.dashboard.profile.logo.update); removing the logo only clears it
 * (DELETE, same URL): the library image is never deleted from here.
 */

import { request } from './admin-media/shared.js';
import { initPicker, uploadError, uploadPlan } from './vendor-product-editor.js';

const root = document.querySelector('[data-vendor-my-store]');

/** Editable fields and the client-side constraints the backend is known to enforce (UX only). */
const EDITABLE = {
    name: { required: true, max: 255 },
    store_name: { required: true, max: 255 },
    short_description: { required: false, max: 255 },
    description: { required: false, max: null },
    address_line: { required: false, max: 255 },
    preparation_days: { required: false, max: 3 },
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
            preparation_days: profile.preparation_days ?? '',
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
    const logo = root.querySelector('[data-store-logo]');
    const logoFallback = root.querySelector('[data-store-logo-fallback]');
    const logoPick = root.querySelector('[data-store-logo-pick]');
    const logoDelete = root.querySelector('[data-store-logo-delete]');
    const logoStatus = root.querySelector('[data-store-logo-status]');

    const field = (name) => form.elements.namedItem(name);
    const errorFor = (name) => root.querySelector(`[data-error-for="${name}"]`);

    // Preservation state: in memory only, set once by a successful read.
    let preserved = null;
    let saving = false;
    let logoBusy = false;
    // The stored path of the current logo (from the profile read), to mark it in the picker.
    let logoPath = null;

    // MYSTORE-UX-01D: unsaved changes are the editable profile fields that differ from the values read (or last
    // saved). The logo (its own endpoint) and the media picker are not form fields and never count. The indicator is
    // a polite status whose text changes only when the state flips, so it is announced once, not on every keystroke.
    const unsaved = root.querySelector('[data-store-unsaved]');
    let loaded = null;
    let shownDirty = false;
    const currentValues = () => Object.fromEntries(Object.keys(EDITABLE).map((name) => [name, field(name).value]));
    const isDirty = () => loaded !== null && Object.keys(EDITABLE).some((name) => field(name).value !== loaded[name]);
    const updateUnsaved = () => {
        const dirty = isDirty();
        if (dirty !== shownDirty) {
            shownDirty = dirty;
            unsaved.textContent = dirty ? i18n.unsaved : '';
        }
    };

    const setStatus = (message, tone = '') => {
        status.textContent = message;
        status.dataset.tone = tone;
    };

    const setFieldsEnabled = (enabled) => {
        Object.keys(EDITABLE).forEach((name) => {
            field(name).disabled = !enabled;
        });
        saveButton.disabled = !enabled || preserved === null;
        setLogoEnabled(enabled);
    };

    // MYSTORE-UI-01: the logo controls follow the form (usable once the profile is loaded, not while saving)
    // and are also locked while a logo upload or deletion is in progress.
    function setLogoEnabled(enabled) {
        logoPick.disabled = !enabled || preserved === null || logoBusy;
        logoDelete.disabled = logoPick.disabled;
    }

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

    // MYSTORE-UX-01C: the identity card shows the logo, or the store name's letter when there is none.
    const showLogo = (url) => {
        if (isString(url) && url !== '') { logo.src = url; logo.hidden = false; logoDelete.hidden = false; } else { logo.removeAttribute('src'); logo.hidden = true; logoDelete.hidden = true; }
        logoFallback.hidden = !logo.hidden;
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
        loaded = null; // nothing can be saved: nothing is unsaved
        updateUnsaved();
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

            const responsePayload = await response.json();
            const profile = pickProfile(responsePayload);

            if (profile === null) {
                throw new Error('profile-incomplete');
            }

            Object.entries(profile.editable).forEach(([name, value]) => {
                field(name).value = value;
            });
            Object.entries(profile.display).forEach(([key, value]) => setReadonly(key, value));
            showLogo(responsePayload?.media?.logo_url ?? null);
            logoPath = isString(responsePayload?.vendor?.profile?.logo) ? responsePayload.vendor.profile.logo : null;
            updateCounter();
            loaded = currentValues();
            updateUnsaved();

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
            preparation_days: optional('preparation_days') === null ? null : Number(value('preparation_days')),
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
            // Confirmed by the server: reload so the page and the shell show the saved values. The saved values are
            // the new reference first, so the reload is never held by the unsaved-changes warning.
            loaded = currentValues();
            updateUnsaved();
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

        updateUnsaved();
    });

    // MYSTORE-UX-01D: the browser's own leave-page warning, only while profile changes are unsaved.
    window.addEventListener('beforeunload', (event) => {
        if (isDirty()) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    root.querySelector('[data-store-retry]').addEventListener('click', load);

    // ---- Logo (MYSTORE-UI-01C): the shared media picker ----

    const pickerText = JSON.parse(root.querySelector('[data-store-picker-i18n]').textContent);
    const jsonHeaders = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token };
    let picker = null;

    // The message is shown under the logo and, while the picker is open, inside it too (the page behind is covered).
    const logoSay = (text) => {
        logoStatus.textContent = text || '';
        if (picker?.isOpen) picker.say(text);
    };
    const logoFail = (text) => {
        logoStatus.textContent = text;
        if (picker?.isOpen) { picker.say(''); picker.alert(text); }
    };
    /** A refused logo request's text: never the server's own message. */
    const logoErrorText = (status, fallback) => (status === 0 ? pickerText.network
        : status === 401 || status === 419 ? pickerText.session
            : status === 403 ? pickerText.forbidden
                : fallback);

    const setLogoBusy = (busy) => {
        logoBusy = busy;
        setLogoEnabled(!saving);
        picker?.setBusy(busy);
    };

    /** Sets the chosen library image as the logo (its media id only); true when the server confirmed it. */
    const chooseLogo = async (medium) => {
        if (logoBusy || !Number.isInteger(medium?.id)) return false;
        setLogoBusy(true);
        picker?.alert('');
        logoSay(i18n.logoSaving);
        const answer = await request(root.dataset.logoUrl, { method: 'PUT', credentials: 'same-origin', headers: { ...jsonHeaders, 'Content-Type': 'application/json' }, body: JSON.stringify({ media_id: medium.id }) });
        setLogoBusy(false);

        if (answer.status !== 200 || !isString(answer.body?.logo?.url)) {
            logoFail(logoErrorText(answer.status, i18n.logoUploadFailed));
            return false;
        }

        showLogo(answer.body.logo.url);
        logoPath = isString(medium.path) ? medium.path : null;
        picker.close(); // focus goes back to the choose button
        logoSay(i18n.logoUploaded);
        return true;
    };

    // An upload goes to the vendor's media library (its endpoint and checks); the uploaded image is then chosen.
    const uploadLogo = async (files) => {
        if (logoBusy) return;
        const plan = uploadPlan(files);
        if (!plan.ok) { if (plan.error) logoFail(pickerText[plan.error]); return; }
        const data = new FormData();
        plan.files.forEach((file) => data.append('files[]', file));
        setLogoBusy(true);
        picker.alert('');
        logoSay(i18n.logoUploading);
        const answer = await request(root.dataset.mediaStoreUrl, { method: 'POST', credentials: 'same-origin', headers: jsonHeaders, body: data });
        setLogoBusy(false);

        if (answer.status !== 201) {
            logoFail(logoErrorText(answer.status, pickerText[uploadError(answer)] || pickerText.failed));
            return;
        }

        picker.refresh();
        const media = Array.isArray(answer.body?.images) ? answer.body.images : [];
        if (media[0]) await chooseLogo(media[0]);
    };

    picker = initPicker(root.querySelector('[data-product-media-library]'), {
        url: root.dataset.mediaLibraryUrl,
        messages: pickerText,
        stateOf: (medium) => (logoPath !== null && medium?.path === logoPath ? 'current' : null),
        onChoose: chooseLogo,
        onUpload: uploadLogo,
    });

    logoPick.addEventListener('click', () => {
        if (logoBusy || logoPick.disabled) return;
        logoSay('');
        picker.open({ title: pickerText.title, from: logoPick, fallback: () => logoPick });
    });

    logoDelete.addEventListener('click', async () => {
        if (logoBusy || !window.confirm(i18n.logoConfirmDelete)) return;
        setLogoBusy(true);
        const answer = await request(root.dataset.logoUrl, { method: 'DELETE', credentials: 'same-origin', headers: jsonHeaders });
        setLogoBusy(false);

        if (answer.status !== 200) {
            logoFail(logoErrorText(answer.status, i18n.logoDeleteFailed));
            return;
        }

        showLogo(null);
        logoPath = null;
        logoSay(i18n.logoDeleted);
        logoPick.focus(); // the focused Remove button is now hidden; focus once the button is enabled again
    });

    load();
}

if (root) {
    initMyStore();
}
