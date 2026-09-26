{{--
    Language switcher for the admin auth pages. Uses the shared $languages
    (active only) and $currentLanguage from AppServiceProvider, and the same
    ?change-locale={code} link pattern as the dashboard switcher (handled by
    SetLocale, which only accepts active languages). Plain links, no JS.
--}}
@php
    $currentLocale = app()->getLocale();
    $currentUrl = request()->fullUrlWithoutQuery(['change-locale']);
    $separator = str_contains($currentUrl, '?') ? '&' : '?';
@endphp

@if ($languages->count() > 1)
    <nav aria-label="{{ t('dashboard.Choose_Language', 'Choose language') }}" {{ $attributes }}>
        <ul class="inline-flex flex-wrap items-center gap-1 rounded-full border border-line bg-surface p-1 shadow-sm">
            @foreach ($languages as $language)
                @php($label = $language->native ?: ($language->name ?: strtoupper($language->code)))
                <li>
                    @if ($language->code === $currentLocale)
                        <span
                            aria-current="true"
                            lang="{{ $language->code }}"
                            dir="{{ $language->is_rtl ? 'rtl' : 'ltr' }}"
                            class="inline-flex min-h-11 items-center rounded-full bg-olive px-4 text-sm font-bold text-surface"
                        >{{ $label }}</span>
                    @else
                        <a
                            href="{{ $currentUrl.$separator.'change-locale='.urlencode($language->code) }}"
                            hreflang="{{ $language->code }}"
                            lang="{{ $language->code }}"
                            dir="{{ $language->is_rtl ? 'rtl' : 'ltr' }}"
                            class="inline-flex min-h-11 items-center rounded-full px-4 text-sm font-semibold text-muted transition-colors hover:bg-canvas hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-olive"
                        >{{ $label }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
@endif
