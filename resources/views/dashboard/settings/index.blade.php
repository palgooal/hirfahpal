<x-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a href="{{ route('dashboard.home') }}">{{ t('dashboard.Home', 'Home') }}</a></li>
        <li class="breadcrumb-item" aria-current="page">{{ t('dashboard.Settings', 'Settings') }}</li>
    </x-slot:breadcrumbs>

    {{-- SETTINGS-FIX-02 (SET-04), SETTINGS-UX-01: the page's interface texts in the dashboard's language
         (AdminSettingsTranslationSeeder), read without auto-creating rows; the footer content itself stays as the
         merchant wrote it. --}}
    @php($settingsText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    })
    @php($canEditSettings = auth('admin')->user()->can('edit', App\Models\Setting::class))
    @php($footer = App\Models\Setting::footerFor($setting))

    {{-- SETTINGS-UX-01: three tabs. Site availability (Super Admin only) keeps its own form; general and footer
         settings stay one form and one save, each in its own panel. The tab opened first is the one holding this
         request's validation errors or site-availability message; otherwise admin-settings.js follows the URL
         fragment (kept through the redirect after saving), else the first tab. --}}
    @php($generalErrors = collect(array_keys(App\Http\Requests\Dashboard\UpdateSettingRequest::GENERAL_LABELS))->filter(fn ($field) => $errors->has($field))->count())
    @php($footerErrors = collect($errors->keys())->filter(fn ($field) => $field === 'footer' || $field === 'footer_social' || str_starts_with($field, 'footer.') || str_starts_with($field, 'footer_social.'))->count())
    @php($tabs = array_filter([
        'availability' => ($siteAvailability ?? null) ? $settingsText('Site_Availability_Title', 'Site availability') : null,
        'general' => $settingsText('Settings_Tab_General', 'General settings'),
        'footer' => $settingsText('Settings_Tab_Footer', 'Footer settings'),
    ]))
    @php($tabErrors = ['general' => $generalErrors, 'footer' => $footerErrors])
    {{-- FOOTER-SOCIAL-02D: saved links (legacy fields included) that fail the platform check would stop the next save;
         each gets a warning on its row. Only on a fresh page: after a failed save the sent values and their errors
         take over. They suggest the Footer tab, used only when neither the server nor the URL fragment picks one. --}}
    @php($socialWarnings = old('footer_social') === null
        ? collect(App\Support\Storefront\SocialPlatforms::entriesFrom($setting->footer_content))
            ->reject(fn (array $entry) => App\Support\Storefront\SocialPlatforms::isAllowedUrl($entry['platform'], $entry['url']))
            ->pluck('platform')->all()
        : [])
    @php($initialTab = match (true) {
        isset($tabs['availability']) && session()->has(App\Http\Controllers\Dashboard\SiteAvailabilityController::FLASH) => 'availability',
        $generalErrors > 0 => 'general',
        $footerErrors > 0 => 'footer',
        default => '',
    })

    {{-- SETTINGS-UX-02B: the approved admin pieces (UX-02A): media page head and alerts, the category form's card,
         sections, fields and actions (DEC-AD-UI-12 error summary). The breadcrumb band and the layout's raw error list
         are hidden on this page (admin-dashboard.css), as on the category form. --}}
    @php($required = $settingsText('Settings_Required', 'Required'))
    @php($fieldLabels = (new App\Http\Requests\Dashboard\UpdateSettingRequest)->attributes())

    <div class="col-span-12 admin-media admin-cat-form admin-settings" data-settings-tabs data-initial-tab="{{ $initialTab }}" data-suggested-tab="{{ $socialWarnings !== [] ? 'footer' : '' }}">
        <div class="admin-media-head">
            <p class="admin-media-intro">{{ t('dashboard.Settings_hint', 'Manage the core site configuration.') }}</p>
        </div>

        {{-- Revealed by admin-settings.js; hidden without JavaScript, when every panel is shown in turn. --}}
        <div class="admin-settings-tablist" role="tablist" aria-label="{{ $settingsText('Settings_Tabs', 'Settings sections') }}" data-settings-tablist hidden>
            @foreach ($tabs as $key => $label)
                @php($selected = $initialTab === $key || ($initialTab === '' && $loop->first))
                <button type="button" class="admin-settings-tab" role="tab" id="settings-tab-{{ $key }}" aria-controls="settings-panel-{{ $key }}" aria-selected="{{ $selected ? 'true' : 'false' }}" tabindex="{{ $selected ? '0' : '-1' }}" data-settings-tab="{{ $key }}">
                    <span>{{ $label }}</span>
                    @if (($tabErrors[$key] ?? 0) > 0)
                        <span class="admin-settings-tab-alert" data-settings-tab-errors="{{ $tabErrors[$key] }}"><span aria-hidden="true">{{ $tabErrors[$key] }}</span><span class="visually-hidden">{{ $settingsText('Settings_Tab_Has_Errors', 'Contains errors') }}</span></span>
                    @endif
                </button>
            @endforeach
        </div>

        @isset($tabs['availability'])
            <section class="admin-settings-panel" id="settings-panel-availability" aria-label="{{ $tabs['availability'] }}" data-settings-panel="availability">
                @include('dashboard.settings.partials.site-availability', ['card' => $siteAvailability])
            </section>
        @endisset

        {{-- The settings form's messages and card, hidden together while the site availability tab is selected. --}}
        <div class="admin-media" data-settings-form-area>
            @if (session('success'))
                <div class="admin-media-alert is-success" role="status">
                    <i class="ph-duotone ph-check-circle" aria-hidden="true"></i>
                    <div class="admin-media-alert-content"><p>{{ session('success') }}</p></div>
                </div>
            @endif

            @if ($errors->any())
                {{-- Error summary: translated field names, each linked to its field (DEC-AD-UI-12); admin-settings.js opens
                     the field's tab. --}}
                <div class="admin-media-alert is-error admin-cat-form-summary" role="alert" tabindex="-1" id="settings-form-errors">
                    <i class="ph-duotone ph-warning" aria-hidden="true"></i>
                    <div class="admin-media-alert-content">
                        <p>{{ $settingsText('Settings_Form_Errors', 'Please correct the following:') }}</p>
                        <ul>
                            @foreach ($errors->getMessages() as $field => $messages)
                                {{-- footer.en.tagline is the field footer[en][tagline] (FOOTER-I18N-01C). --}}
                                @php($fieldId = match (true) {
                                    str_starts_with($field, 'footer.') => 'footer['.implode('][', explode('.', substr($field, 7))).']',
                                    str_starts_with($field, 'footer_social.') => str_replace('.', '_', $field),
                                    $field === 'footer_social' => 'settings-social-add',
                                    // SETTINGS-MEDIA-01: the logo and favicon errors link to their media picker.
                                    in_array($field, ['logo_media_id', 'remove_logo', 'remove_legacy_logo'], true) => 'media-picker-logo-media-id',
                                    in_array($field, ['favicon_media_id', 'remove_favicon', 'remove_legacy_favicon'], true) => 'media-picker-favicon-media-id',
                                    default => $field,
                                })
                                <li><a href="#{{ $fieldId }}">{{ $fieldLabels[$field] ?? $field }}</a>: {{ $messages[0] }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('dashboard.setting.update') }}" enctype="multipart/form-data" class="card admin-media-panel admin-cat-form-card" data-settings-form>
                @csrf
                @method('PUT')

                <fieldset class="admin-settings-fields" @disabled(! $canEditSettings)>
                    <section class="admin-settings-panel" id="settings-panel-general" aria-labelledby="settings-heading-general" data-settings-panel="general">
                        <h2 class="admin-settings-panel-title" id="settings-heading-general">{{ $tabs['general'] }}</h2>

                        <fieldset class="admin-cat-form-section">
                            <legend class="admin-cat-form-legend">{{ $settingsText('Settings_Group_Identity', 'Site identity') }}</legend>

                            <div class="admin-cat-form-field">
                                <x-form.input name="site_name" label="{{ t('dashboard.Site_Name', 'Site Name') }}" value="{{ $setting->site_name }}" dir="auto" />
                            </div>

                            <div class="admin-cat-form-field">
                                <x-form.textarea name="site_description" label="{{ t('dashboard.Site_Description', 'Site Description') }}" value="{{ $setting->site_description }}" dir="auto" />
                            </div>

                            {{-- SETTINGS-MEDIA-01: the logo and favicon are chosen from the media library (the shared picker;
                                 unlinking keeps the file in the library). An image uploaded before, with no library image
                                 linked, is still shown and used by the site; it can be removed (its file is kept). --}}
                            <div class="admin-cat-form-row">
                                <div class="admin-cat-form-field">
                                    <x-admin.media-picker name="logo_media_id" remove-name="remove_logo" :value="$setting->logo_media_id" label-key="Logo" label-default="Logo"
                                        removed-note-key="Settings_Logo_Removed_Note" removed-note-default="The logo will be removed from the site when you save. The file stays in the media library." />
                                    @if (filled($setting->logo) && ! $setting->logo_media_id)
                                        <div class="admin-settings-legacy-image" data-settings-legacy-image="logo">
                                            <img src="{{ App\Support\Storefront\HomepageMedia::url($setting->logo) }}" alt="{{ t('dashboard.Logo', 'Logo') }}" class="img-fluid mt-2" style="max-height: 80px">
                                            <p class="admin-media-hint">{{ $settingsText('Settings_Legacy_Image_Note', 'A previously uploaded image. It is shown on the site until you choose an image from the media library.') }}</p>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="remove_legacy_logo" name="remove_legacy_logo" value="1" @checked(old('remove_legacy_logo'))>
                                                <label class="form-check-label" for="remove_legacy_logo">{{ $settingsText('Settings_Legacy_Image_Remove', 'Remove the previously uploaded image (the file is kept)') }}</label>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="admin-cat-form-field">
                                    <x-admin.media-picker name="favicon_media_id" remove-name="remove_favicon" :value="$setting->favicon_media_id" label-key="Favicon" label-default="Favicon"
                                        removed-note-key="Settings_Favicon_Removed_Note" removed-note-default="The favicon will be removed from the site when you save. The file stays in the media library." />
                                    @if (filled($setting->favicon) && ! $setting->favicon_media_id)
                                        <div class="admin-settings-legacy-image" data-settings-legacy-image="favicon">
                                            <img src="{{ App\Support\Storefront\HomepageMedia::url($setting->favicon) }}" alt="{{ t('dashboard.Favicon', 'Favicon') }}" class="img-fluid mt-2" style="max-height: 48px">
                                            <p class="admin-media-hint">{{ $settingsText('Settings_Legacy_Image_Note', 'A previously uploaded image. It is shown on the site until you choose an image from the media library.') }}</p>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="remove_legacy_favicon" name="remove_legacy_favicon" value="1" @checked(old('remove_legacy_favicon'))>
                                                <label class="form-check-label" for="remove_legacy_favicon">{{ $settingsText('Settings_Legacy_Image_Remove', 'Remove the previously uploaded image (the file is kept)') }}</label>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="admin-cat-form-section">
                            <legend class="admin-cat-form-legend">{{ $settingsText('Settings_Group_Contact', 'Contact details') }}</legend>

                            <div class="admin-cat-form-row">
                                <div class="admin-cat-form-field">
                                    <x-form.input type="email" name="email" label="{{ t('dashboard.Email', 'Email') }}" value="{{ $setting->email }}" dir="ltr" />
                                </div>

                                <div class="admin-cat-form-field">
                                    <x-form.input name="phone" label="{{ t('dashboard.Phone', 'Phone') }}" value="{{ $setting->phone }}" dir="ltr" />
                                </div>
                            </div>

                            <div class="admin-cat-form-field">
                                <x-form.textarea name="address" label="{{ t('dashboard.Address', 'Address') }}" value="{{ $setting->address }}" dir="auto" />
                            </div>
                        </fieldset>

                        <fieldset class="admin-cat-form-section">
                            <legend class="admin-cat-form-legend">{{ $settingsText('Settings_Group_Regional', 'Regional settings') }}</legend>

                            {{-- The required fields carry the category form's mark; their label is written here, the
                                 component draws the field. --}}
                            <div class="admin-cat-form-row">
                                <div class="admin-cat-form-field">
                                    <label class="form-label" for="timezone">{{ t('dashboard.Timezone', 'Timezone') }} <span class="admin-cat-form-mark">({{ $required }})</span></label>
                                    <x-form.input name="timezone" value="{{ $setting->timezone }}" required dir="ltr" aria-describedby="settings-hint-timezone" />
                                    <p class="admin-media-hint mb-0" id="settings-hint-timezone">{{ $settingsText('Settings_Hint_Timezone', 'Time zone, such as Asia/Hebron.') }}</p>
                                </div>

                                <div class="admin-cat-form-field admin-cat-form-field-narrow">
                                    <label class="form-label" for="default_locale">{{ t('dashboard.Default_Locale', 'Default Locale') }} <span class="admin-cat-form-mark">({{ $required }})</span></label>
                                    <x-form.input name="default_locale" value="{{ $setting->default_locale }}" required dir="ltr" aria-describedby="settings-hint-locale" />
                                    <p class="admin-media-hint mb-0" id="settings-hint-locale">{{ $settingsText('Settings_Hint_Locale', 'Language code, such as ar or en.') }}</p>
                                </div>

                                <div class="admin-cat-form-field admin-cat-form-field-narrow">
                                    <label class="form-label" for="default_currency">{{ t('dashboard.Default_Currency', 'Default Currency') }} <span class="admin-cat-form-mark">({{ $required }})</span></label>
                                    <x-form.input name="default_currency" value="{{ $setting->default_currency }}" maxlength="3" required dir="ltr" aria-describedby="settings-hint-currency" />
                                    <p class="admin-media-hint mb-0" id="settings-hint-currency">{{ $settingsText('Settings_Hint_Currency', 'Three-letter currency code, such as ILS.') }}</p>
                                </div>
                            </div>
                        </fieldset>
                    </section>

                    <section class="admin-settings-panel" id="settings-panel-footer" aria-labelledby="settings-heading-footer" data-settings-panel="footer">
                        <h2 class="admin-settings-panel-title" id="settings-heading-footer">{{ $settingsText('Settings_Footer_Title', 'Footer settings') }}</h2>
                        <p class="admin-media-hint admin-settings-panel-hint">{{ $settingsText('Settings_Footer_Hint', 'These details appear directly in the site footer.') }}</p>
                        <p class="admin-media-hint admin-settings-panel-hint pt-1">{{ $settingsText('Settings_Footer_Language_Hint', 'Each text has an Arabic and an English field. An empty field shows the default text displayed in it.') }}</p>

                        {{-- FOOTER-I18N-01C: each text in both languages, side by side (stacked on narrow screens). Arabic shows its
                             current text (flat fields before the first bilingual save); English shows only what was written in
                             English, its approved default as the placeholder, never the Arabic text. --}}
                        @php($footerEnglish = App\Models\Setting::savedFooterTexts($setting, 'en'))
                        @php($footerLanguages = [
                            'ar' => [$settingsText('Settings_Language_Ar', 'Arabic'), 'rtl', $footer, App\Models\Setting::footerDefaults('ar')],
                            'en' => [$settingsText('Settings_Language_En', 'English'), 'ltr', $footerEnglish, App\Models\Setting::footerDefaults('en')],
                        ])

                        {{-- [legend key, English legend, [[footer key, English label(, 'textarea')], ...]] --}}
                        @php($footerGroups = [
                            ['Settings_Group_Footer_Identity', 'Footer identity', [
                                ['tagline', 'Tagline'], ['description', 'Footer description', 'textarea'],
                            ]],
                            ['Settings_Group_Footer_Social', 'Social links', [
                            ]],
                            ['Settings_Group_Footer_Navigation', 'Navigation links', [
                                ['about_title', 'About section title'], ['about_story_label', 'Story and vision link text'], ['about_authenticity_label', 'Authenticity and quality link text'], ['about_contact_label', 'Contact link text'],
                                ['links_title', 'Useful links title'], ['orders_label', 'Orders link text'], ['cart_label', 'Cart link text'], ['login_label', 'Sign-in link text'], ['account_label', 'Account link text'],
                            ]],
                            ['Settings_Group_Footer_Service', 'Customer service and policies', [
                                ['customer_service_title', 'Customer service title'], ['shipping_label', 'Shipping link text'], ['returns_label', 'Exchanges and returns link text'], ['faq_label', 'FAQ link text'],
                            ]],
                            ['Settings_Group_Footer_Payment', 'Payment methods', [
                                ['payment_methods_title', 'Payment methods title'], ['card_payment_label', 'Credit card label'], ['apple_pay_label', 'Apple Pay label'], ['cash_on_delivery_label', 'Cash on delivery label'],
                            ]],
                            ['Settings_Group_Footer_Copyright', 'Copyright', [
                                ['copyright', 'Copyright text'],
                            ]],
                        ])
                        {{-- SETTINGS-UX-03: each group is a native disclosure (<details>). All are rendered open, so every field
                             is shown without JavaScript; admin-settings.js then closes all but the first and those with
                             errors. A closed group's fields are still submitted with the form. --}}
                        @php($groupDetails = [
                            'Settings_Group_Footer_Identity' => ['ph-identification-card', $settingsText('Settings_Group_Footer_Identity_Hint', 'The tagline and the short text under the logo.')],
                            'Settings_Group_Footer_Social' => ['ph-share-network', $settingsText('Settings_Group_Footer_Social_Hint', 'The social media accounts shown as icons.')],
                            'Settings_Group_Footer_Navigation' => ['ph-list-bullets', $settingsText('Settings_Group_Footer_Navigation_Hint', 'Titles and link texts of the About and Useful links columns.')],
                            'Settings_Group_Footer_Service' => ['ph-headset', $settingsText('Settings_Group_Footer_Service_Hint', 'The customer service column title and its link texts.')],
                            'Settings_Group_Footer_Payment' => ['ph-credit-card', $settingsText('Settings_Group_Footer_Payment_Hint', 'The payment methods shown at the bottom of the footer.')],
                            'Settings_Group_Footer_Copyright' => ['ph-copyright', $settingsText('Settings_Group_Footer_Copyright_Hint', 'The text shown after the site name and the year.')],
                        ])
                        <div class="admin-settings-groups">
                        @foreach ($footerGroups as [$groupKey, $groupDefault, $footerFields])
                            @php([$groupIcon, $groupHint] = $groupDetails[$groupKey])
                            @php($groupId = 'settings-group-'.strtolower(substr($groupKey, strlen('Settings_Group_Footer_'))))
                            @php($groupErrors = collect($footerFields)->sum(fn ($field) => collect(App\Models\Setting::FOOTER_LOCALES)->filter(fn ($locale) => $errors->has('footer.'.$locale.'.'.$field[0]))->count())
                                + ($groupKey === 'Settings_Group_Footer_Social' ? collect($errors->keys())->filter(fn ($field) => $field === 'footer_social' || str_starts_with($field, 'footer_social.'))->count() : 0))
                            <details class="card admin-media-panel admin-settings-group" id="{{ $groupId }}" open data-settings-group data-settings-group-open="{{ $loop->first || ($groupKey === 'Settings_Group_Footer_Social' && $socialWarnings !== []) ? 'true' : 'false' }}" data-settings-group-errors="{{ $groupErrors }}">
                                <summary class="admin-settings-group-summary">
                                    <span class="admin-settings-group-icon" aria-hidden="true"><i class="ph-duotone {{ $groupIcon }}"></i></span>
                                    <span class="admin-settings-group-text">
                                        <span class="admin-settings-group-title">{{ $settingsText($groupKey, $groupDefault) }}</span>
                                        <span class="admin-settings-group-hint">{{ $groupHint }}</span>
                                    </span>
                                    @if ($groupErrors > 0)
                                        <span class="admin-settings-tab-alert"><span aria-hidden="true">{{ $groupErrors }}</span><span class="visually-hidden">{{ $settingsText('Settings_Tab_Has_Errors', 'Contains errors') }}</span></span>
                                    @endif
                                    <i class="ph-duotone ph-caret-down admin-settings-group-chevron" aria-hidden="true"></i>
                                </summary>
                            <fieldset class="admin-cat-form-section">
                                <legend class="admin-cat-form-legend visually-hidden">{{ $settingsText($groupKey, $groupDefault) }}</legend>
                                @if ($groupKey === 'Settings_Group_Footer_Social')
                                    <p class="admin-media-hint mb-0" id="settings-hint-social">{{ $settingsText('Settings_Hint_Social', 'Enter the full address, starting with https://. Leave it empty to hide the icon.') }}</p>
                                    @error('footer_social')
                                        <p class="admin-cat-form-error" id="footer_social_error">{{ $message }}</p>
                                    @enderror
                                    @include('dashboard.settings.partials.social-links')
                                @endif

                                @if ($footerFields !== [])
                                <div class="admin-cat-form-row">
                                    @foreach ($footerFields as $field)
                                        @php([$key, $default] = $field)
                                        @php($type = $field[2] ?? 'input')
                                        {{-- dashboard.Settings_Footer_<Field> (tagline → Tagline, x_url → X_Url). --}}
                                        @php($label = $settingsText('Settings_Footer_'.implode('_', array_map('ucfirst', explode('_', $key))), $default))
                                        {{-- One field, two languages: footer[<locale>][key], validated as footer.<locale>.key (SET-05). --}}
                                        <div class="admin-cat-form-field w-100 admin-settings-text-pair" role="group" aria-label="{{ $label }}" data-footer-text="{{ $key }}">
                                            <div class="admin-cat-form-row">
                                                @foreach ($footerLanguages as $locale => [$language, $direction, $values, $defaults])
                                                    <div class="admin-cat-form-field">
                                                        @if ($type === 'textarea')
                                                            <x-form.textarea name="footer[{{ $locale }}][{{ $key }}]" error-key="footer.{{ $locale }}.{{ $key }}" label="{{ $label }} ({{ $language }})" :value="$values[$key] ?? ''" lang="{{ $locale }}" dir="{{ $direction }}" placeholder="{{ $defaults[$key] }}" />
                                                        @else
                                                            <x-form.input name="footer[{{ $locale }}][{{ $key }}]" error-key="footer.{{ $locale }}.{{ $key }}" label="{{ $label }} ({{ $language }})" :value="$values[$key] ?? ''" lang="{{ $locale }}" dir="{{ $direction }}" placeholder="{{ $defaults[$key] }}" />
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @endif
                            </fieldset>
                            </details>
                        @endforeach
                        </div>
                    </section>
                </fieldset>

                @can('edit', App\Models\Setting::class)
                    <div class="admin-cat-form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="ph-duotone ph-floppy-disk" aria-hidden="true"></i>
                            <span>{{ $settingsText('Settings_Save', 'Save settings') }}</span>
                        </button>
                    </div>
                @endcan
            </form>
        </div>
    </div>

    @push('scripts')
        @vite('resources/js/admin-settings.js')
    @endpush
</x-dashboard-layout>
