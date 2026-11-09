{{--
    FOOTER-SOCIAL-02B: the footer's social links (App\Support\Storefront\SocialPlatforms), inside the settings form.
    Every approved platform has a row, posted as footer_social[<platform>][url|enabled|position]; a platform without
    an address is not saved. Without JavaScript all rows are shown, with a number field for the order. admin-settings.js
    shows only the added platforms, hides the order fields and adds Move up / Move down / Remove and an Add platform
    control (accessible buttons, announced in a live region); it keeps the order fields in step.
    After a failed save the rows show what was sent, in its order.
--}}
@php
    $platforms = App\Support\Storefront\SocialPlatforms::PLATFORMS;
    // The platform names of the storefront footer, read without auto-creating rows.
    $platformName = function (string $platform) use ($platforms): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('storefront.Footer_Social_'.$platforms[$platform][0], $platforms[$platform][1]);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };
    $old = old('footer_social');
    $rows = [];

    if (is_array($old)) {
        foreach (array_keys($platforms) as $index => $platform) {
            $sent = is_array($old[$platform] ?? null) ? $old[$platform] : [];
            $url = is_string($sent['url'] ?? null) ? $sent['url'] : '';
            $rows[$platform] = [
                'url' => $url,
                'enabled' => filter_var($sent['enabled'] ?? false, FILTER_VALIDATE_BOOL),
                'configured' => trim($url) !== '' || $errors->has('footer_social.'.$platform.'.*'),
                'position' => is_numeric($sent['position'] ?? null) ? (int) $sent['position'] : 99,
                'index' => $index,
            ];
        }
    } else {
        $saved = App\Support\Storefront\SocialPlatforms::entriesFrom($setting->footer_content);
        foreach ($saved as $position => $entry) {
            $rows[$entry['platform']] = ['url' => $entry['url'], 'enabled' => $entry['enabled'], 'configured' => true, 'position' => $position + 1, 'index' => 0];
        }
        foreach (array_keys($platforms) as $index => $platform) {
            $rows[$platform] ??= ['url' => '', 'enabled' => true, 'configured' => false, 'position' => 99, 'index' => $index];
        }
    }

    // The added platforms first, in their order; then the others, in the platforms' order. Positions follow.
    uksort($rows, fn ($a, $b) => [! $rows[$a]['configured'], $rows[$a]['position'], $rows[$a]['index']] <=> [! $rows[$b]['configured'], $rows[$b]['position'], $rows[$b]['index']]);
    $position = 0;
    foreach ($rows as $platform => $row) {
        $rows[$platform]['position'] = is_array($old) && $row['position'] !== 99 ? $row['position'] : ++$position;
        $position = max($position, $rows[$platform]['position']);
    }
@endphp
<div class="admin-settings-social" data-social-links
    data-text-moved="{{ $settingsText('Settings_Social_Moved', ':platform is now in position :position of :count.') }}"
    data-text-added="{{ $settingsText('Settings_Social_Added', ':platform added.') }}"
    data-text-removed="{{ $settingsText('Settings_Social_Removed', ':platform removed.') }}">
    <ol class="admin-settings-social-list" data-social-list>
        @foreach ($rows as $platform => $row)
            @php($name = $platformName($platform))
            @php($field = 'footer_social_'.$platform)
            @php($urlError = $errors->first('footer_social.'.$platform.'.url'))
            @php($positionError = $errors->first('footer_social.'.$platform.'.position'))
            @php($warning = in_array($platform, $socialWarnings ?? [], true))
            @php($describedBy = trim('settings-hint-social'.($urlError ? ' '.$field.'_url_error' : '').($warning ? ' '.$field.'_url_warning' : '')))
            <li class="admin-settings-social-row" data-social-row="{{ $platform }}" data-social-configured="{{ $row['configured'] ? '1' : '0' }}" data-social-name="{{ $name }}">
                <div class="admin-settings-social-head">
                    <span class="admin-settings-social-icon" aria-hidden="true"><x-storefront.social-icon :platform="$platform" /></span>
                    <span class="admin-settings-social-name">{{ $name }}</span>
                    <span class="admin-settings-social-actions" data-social-actions hidden>
                        <button type="button" class="btn btn-light-secondary" data-social-move="up" aria-label="{{ str_replace(':platform', $name, $settingsText('Settings_Social_Move_Up', 'Move :platform up')) }}"><i class="ph-duotone ph-arrow-up" aria-hidden="true"></i></button>
                        <button type="button" class="btn btn-light-secondary" data-social-move="down" aria-label="{{ str_replace(':platform', $name, $settingsText('Settings_Social_Move_Down', 'Move :platform down')) }}"><i class="ph-duotone ph-arrow-down" aria-hidden="true"></i></button>
                        <button type="button" class="btn btn-light-secondary" data-social-remove aria-label="{{ str_replace(':platform', $name, $settingsText('Settings_Social_Remove', 'Remove :platform')) }}"><i class="ph-duotone ph-trash" aria-hidden="true"></i></button>
                    </span>
                </div>

                <div class="admin-cat-form-row">
                    <div class="admin-cat-form-field">
                        <label class="form-label" for="{{ $field }}_url">{{ str_replace(':platform', $name, $settingsText('Settings_Social_Url', ':platform link')) }}</label>
                        <input type="text" inputmode="url" dir="ltr" id="{{ $field }}_url" name="footer_social[{{ $platform }}][url]" value="{{ $row['url'] }}" maxlength="255" autocomplete="off" spellcheck="false"
                            @class(['form-control', 'is-invalid' => $urlError]) aria-describedby="{{ $describedBy }}" @if ($urlError) aria-invalid="true" @endif data-social-url>
                        @if ($urlError)
                            <p class="admin-cat-form-error" id="{{ $field }}_url_error">{{ $urlError }}</p>
                        @endif
                        @if ($warning)
                            {{-- FOOTER-SOCIAL-02D: a saved address the platform check rejects (kept until corrected or removed). --}}
                            <div class="admin-media-alert is-warning mt-2" id="{{ $field }}_url_warning" data-social-warning>
                                <i class="ph-duotone ph-warning" aria-hidden="true"></i>
                                <div class="admin-media-alert-content"><p>{{ str_replace(':platform', $name, $settingsText('Settings_Social_Saved_Invalid', 'This saved address is not a valid :platform address, so it is not shown in the store. Correct it or remove the platform before saving.')) }}</p></div>
                            </div>
                        @endif
                    </div>

                    <div class="admin-cat-form-field admin-cat-form-field-narrow" data-social-order-field>
                        <label class="form-label" for="{{ $field }}_position">{{ $settingsText('Settings_Social_Order', 'Order') }}</label>
                        <input type="number" min="1" max="{{ count($platforms) }}" step="1" inputmode="numeric" dir="ltr" id="{{ $field }}_position" name="footer_social[{{ $platform }}][position]" value="{{ $row['position'] }}"
                            @class(['form-control', 'is-invalid' => $positionError]) @if ($positionError) aria-invalid="true" aria-describedby="{{ $field }}_position_error" @endif data-social-position>
                        @if ($positionError)
                            <p class="admin-cat-form-error" id="{{ $field }}_position_error">{{ $positionError }}</p>
                        @endif
                    </div>
                </div>

                <div class="admin-settings-social-toggle">
                    <input type="hidden" name="footer_social[{{ $platform }}][enabled]" value="0">
                    <input class="form-check-input" type="checkbox" id="{{ $field }}_enabled" name="footer_social[{{ $platform }}][enabled]" value="1" @checked($row['enabled']) data-social-enabled>
                    <label class="form-check-label" for="{{ $field }}_enabled">{{ $settingsText('Settings_Social_Enabled', 'Show in the footer') }}</label>
                </div>
            </li>
        @endforeach
    </ol>

    <p class="admin-media-hint mb-0" data-social-empty hidden>{{ $settingsText('Settings_Social_Empty', 'No platforms added yet.') }}</p>

    <div class="admin-settings-social-add" data-social-add hidden>
        <label class="form-label" for="settings-social-add">{{ $settingsText('Settings_Social_Add', 'Add platform') }}</label>
        <div class="admin-settings-social-add-row">
            <select class="form-select" id="settings-social-add" data-social-add-select>
                <option value="">{{ $settingsText('Settings_Social_Choose', 'Choose a platform') }}</option>
                @foreach (array_keys($platforms) as $platform)
                    <option value="{{ $platform }}">{{ $platformName($platform) }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-light-secondary" data-social-add-button><i class="ph-duotone ph-plus" aria-hidden="true"></i><span>{{ $settingsText('Settings_Social_Add_Button', 'Add') }}</span></button>
        </div>
    </div>

    <div class="visually-hidden" aria-live="polite" data-social-status></div>
</div>
