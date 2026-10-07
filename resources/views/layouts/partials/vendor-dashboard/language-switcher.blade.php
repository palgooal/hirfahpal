{{--
    Vendor dashboard language switcher. Same ?change-locale={code} pattern as the
    other switchers (handled by SetLocale, active languages only); the link keeps
    the current vendor page, with no admin fallback URL. Plain links, no JS.
--}}
@php
    $currentLocale = app()->getLocale();
    $currentUrl = request()->fullUrlWithoutQuery(['change-locale']);
    $separator = str_contains($currentUrl, '?') ? '&' : '?';
@endphp

@if ($languages->count() > 1)
    <nav class="vendor-lang" aria-label="{{ t('dashboard.Choose_Language', 'Choose language') }}">
        <ul class="vendor-lang-list">
            @foreach ($languages as $language)
                @php($label = $language->native ?: ($language->name ?: strtoupper($language->code)))
                <li>
                    @if ($language->code === $currentLocale)
                        <span class="vendor-lang-item is-current" aria-current="true" lang="{{ $language->code }}" dir="{{ $language->is_rtl ? 'rtl' : 'ltr' }}">{{ $label }}</span>
                    @else
                        <a class="vendor-lang-item" href="{{ $currentUrl.$separator.'change-locale='.urlencode($language->code) }}" hreflang="{{ $language->code }}" lang="{{ $language->code }}" dir="{{ $language->is_rtl ? 'rtl' : 'ltr' }}">{{ $label }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
@endif
