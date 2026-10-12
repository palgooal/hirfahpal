{{--
    Admin header language switcher (AD-UI-01D-3): every active language, by its native name,
    through the existing ?change-locale={code} mechanism (SetLocale), keeping the current page.
    A flag is shown only when its file exists. Disclosure driven by resources/js/admin-dashboard.js.
--}}
@php
    $currentUrl = request()->fullUrlWithoutQuery(['change-locale']);
    $separator = str_contains($currentUrl, '?') ? '&' : '?';
    $currentLabel = $currentLanguage?->native ?: ($currentLanguage?->name ?: strtoupper($currentLocale));
@endphp
<li class="dropdown pc-h-item admin-lang" data-admin-dropdown>
    <button type="button" class="pc-head-link dropdown-toggle arrow-none me-0 admin-lang-trigger" data-admin-dropdown-toggle
        aria-haspopup="true" aria-expanded="false" aria-controls="admin-lang-menu"
        aria-label="{{ t('dashboard.Choose_Language', 'Choose language') }}: {{ $currentLabel }}">
        <i class="ti ti-language" aria-hidden="true"></i>
    </button>

    <div class="dropdown-menu dropdown-menu-end pc-h-dropdown admin-header-menu admin-lang-menu" id="admin-lang-menu">
        @foreach ($languages as $lang)
            @php
                $label = $lang->native ?: ($lang->name ?: strtoupper($lang->code));
                $direction = $lang->is_rtl ? 'rtl' : 'ltr';
                $hasFlag = $lang->flag && is_file(public_path($lang->flag));
            @endphp
            @if ($lang->code === $currentLocale)
                <span class="dropdown-item admin-header-menu-item admin-lang-item is-current" aria-current="true" lang="{{ $lang->code }}" dir="{{ $direction }}">
                    @if ($hasFlag)
                        <img src="{{ asset($lang->flag) }}" alt="" class="admin-lang-flag" width="20" height="20" />
                    @endif
                    <span>{{ $label }}</span>
                    <i class="ti ti-check admin-lang-check" aria-hidden="true"></i>
                </span>
            @else
                <a class="dropdown-item admin-header-menu-item admin-lang-item" href="{{ $currentUrl.$separator.'change-locale='.urlencode($lang->code) }}" hreflang="{{ $lang->code }}" lang="{{ $lang->code }}" dir="{{ $direction }}">
                    @if ($hasFlag)
                        <img src="{{ asset($lang->flag) }}" alt="" class="admin-lang-flag" width="20" height="20" />
                    @endif
                    <span>{{ $label }}</span>
                </a>
            @endif
        @endforeach
    </div>
</li>
