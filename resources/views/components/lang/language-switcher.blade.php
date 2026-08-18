@props([
    'variant' => 'front',
    'buttonClass' => null,
    'menuClass' => null,
    'itemClass' => null,
    'activeItemClass' => null,
    'label' => null,
    'showLocaleCode' => false,
]) {{-- front | builder | topbar --}}
@php
    use Illuminate\Support\Facades\View;

    $request = request();
    $currentRoute = request()->route()?->getName();
    $slug = request()->route('slug');
    $currentLocale = app()->getLocale();
    $sharedPage = View::shared('currentPage', null);
    $currentUrl = $request->fullUrlWithoutQuery(['change-locale']);
    $buildLocaleUrl = static function (string $url, string $locale): string {
        return route('change_locale', ['locale' => $locale], false) . '?redirect=' . urlencode($url);
    };

    $defaultButtonClass = match ($variant) {
        'builder'
            => 'flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-slate-700 bg-slate-100 rounded-full hover:bg-slate-200',
        'topbar' => 'flex items-center gap-2 hover:text-red-brand transition-all duration-300 text-sm md:text-base',
        default
            => 'flex items-center gap-1 text-primary dark:text-white font-semibold hover:text-secondary dark:hover:text-yellow-400 text-sm',
    };

    $defaultMenuClass = match ($variant) {
        'builder'
            => 'absolute mt-2 w-40 bg-white border border-slate-200 rounded-xl shadow-lg z-40 py-1 rtl:right-0 rtl:left-auto ltr:left-0 ltr:right-auto',
        'topbar'
            => 'absolute top-full ltr:right-0 rtl:left-0 mt-2 min-w-[140px] bg-white border border-gray-200 rounded-xl shadow-lg z-[100] overflow-hidden',
        default
            => 'absolute left-0 mt-2 w-28 bg-white dark:bg-[#2c2c2c] border border-gray-200 dark:border-gray-700 rounded-md shadow-md z-40',
    };

    $defaultItemClass = match ($variant) {
        'builder' => 'block w-full text-right px-3 py-1.5 text-[12px] hover:bg-slate-100 rounded-lg',
        'topbar'
            => 'block w-full ltr:text-left rtl:text-right px-4 py-3 text-purple-brand hover:bg-gray-100 transition-colors text-sm font-medium',
        default => 'block w-full text-right px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-white/20',
    };

    $buttonClass = trim((string) ($buttonClass ?: $defaultButtonClass));
    $menuClass = trim((string) ($menuClass ?: $defaultMenuClass));
    $itemClass = trim((string) ($itemClass ?: $defaultItemClass));
    $activeItemClass = trim((string) ($activeItemClass ?: (
        $variant === 'topbar' ? 'bg-gray-100 font-semibold' : 'bg-slate-100 dark:bg-white/10 font-bold'
    )));

    $label = trim((string) ($label ?? ''));
    if ($label === '') {
        $label = match ($variant) {
            'builder' => $currentLanguage?->code ?? strtoupper($currentLocale),
            default => $currentLanguage?->native ?? strtoupper($currentLocale),
        };
    }

    $showFlags = $variant !== 'builder';
@endphp

<div class="relative group" id="lang-container">
    <button id="lang-switch" class="{{ $buttonClass }}" type="button" aria-haspopup="true" aria-controls="lang-menu">
        @if ($variant === 'builder')
            <span class="uppercase tracking-wide">{{ $currentLanguage?->code ?? strtoupper($currentLocale) }}</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18z" />
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3.6 9h16.8M3.6 15h16.8M12 3c2.5 2.6 3.9 5.6 3.9 9s-1.4 6.4-3.9 9c-2.5-2.6-3.9-5.6-3.9-9S9.5 5.6 12 3z" />
            </svg>
        @elseif ($variant === 'topbar')
            <svg class="h-4" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path opacity="0.6"
                    d="M8.5 16.5C12.9183 16.5 16.5 12.9183 16.5 8.5C16.5 4.08172 12.9183 0.5 8.5 0.5M8.5 16.5C4.08172 16.5 0.5 12.9183 0.5 8.5C0.5 4.08172 4.08172 0.5 8.5 0.5M8.5 16.5C10.6818 16.5 11.4091 12.8636 11.4091 8.5C11.4091 4.13636 10.6818 0.5 8.5 0.5M8.5 16.5C6.31818 16.5 5.59091 12.8636 5.59091 8.5C5.59091 4.13636 6.31818 0.5 8.5 0.5M1.22727 11.4091H15.7727M1.22727 5.59091H15.7727"
                    stroke="currentColor" />
            </svg>
            <span>{{ $label }}</span>
            <svg class="h-3 w-3 opacity-70" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 11.914 6.457"
                aria-hidden="true">
                <path d="M10.5,0,5.25,5.25,0,0" fill="none" stroke="currentColor" stroke-linecap="round"
                    stroke-linejoin="round" stroke-width="1" />
            </svg>
        @else
            @if ($currentLanguage?->flag)
                <img src="{{ asset($currentLanguage->flag) }}" alt="{{ $currentLanguage->native }}"
                    class="inline w-4 h-4 mr-1">
            @endif
            <span>{{ $currentLanguage?->native ?? strtoupper($currentLocale) }}</span>
        @endif
    </button>

    <div id="lang-menu"
        class="{{ $menuClass }} opacity-0 invisible group-hover:opacity-100 group-hover:visible md:transition-all md:duration-200">

        @foreach ($languages as $lang)
            @php
                $redirectUrl = '#';

                if ($sharedPage && $lang->code !== $currentLocale) {
                    $translatedPage = $sharedPage->translations->firstWhere('locale', $lang->code);
                    $translatedSlug = $translatedPage?->slug;

                    if ($sharedPage->is_home) {
                        $redirectUrl = $buildLocaleUrl(url('/'), $lang->code);
                    } elseif ($translatedSlug) {
                        $redirectUrl = $buildLocaleUrl(url($translatedSlug), $lang->code);
                    } else {
                        $redirectUrl = $buildLocaleUrl($currentUrl ?: url('/'), $lang->code);
                    }
                } elseif ($lang->code !== $currentLocale) {
                    $redirectUrl = $buildLocaleUrl($currentUrl ?: url('/'), $lang->code);
                }
            @endphp

            <a href="{{ $redirectUrl }}"
                class="{{ $itemClass }} {{ $lang->code === $currentLocale ? $activeItemClass : '' }}">
                @if ($showFlags && $lang->flag)
                    <img src="{{ asset($lang->flag) }}" alt="{{ $lang->native }}" class="inline w-4 h-4 mr-1">
                @endif
                @if ($variant === 'topbar' && $showLocaleCode)
                    {{ $lang->native }} ({{ strtoupper((string) $lang->code) }})
                @else
                    {{ $lang->native }}
                @endif
            </a>
        @endforeach

    </div>
</div>
