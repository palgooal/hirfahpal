<li class="dropdown pc-h-item">
    @php
        $request = request();
        $currentUrl = $request->fullUrlWithoutQuery(['change-locale']);
        $buildLocaleUrl = static function (string $url, string $locale): string {
            $separator = str_contains($url, '?') ? '&' : '?';
            return $url . $separator . 'change-locale=' . urlencode($locale);
        };
    @endphp

    <a
        class="pc-head-link dropdown-toggle me-0"
        data-pc-toggle="dropdown"
        href="#"
        role="button"
        aria-haspopup="false"
        aria-expanded="false"
        title="{{ $currentLanguage?->native ?? strtoupper($currentLocale) }}"
    >
        <i class="ti ti-language text-[18px] leading-none"></i>
    </a>

    <div class="dropdown-menu dropdown-menu-end pc-h-dropdown lng-dropdown">
        @foreach ($languages as $lang)
            @php
                $isCurrent = $lang->code === $currentLocale;
                $label = $lang->native ?: $lang->name ?: strtoupper($lang->code);
                $meta  = $lang->name && $lang->name !== $label ? $lang->name : strtoupper($lang->code);
            @endphp

            <a
                href="{{ $isCurrent ? '#' : $buildLocaleUrl($currentUrl ?: url('/admin/home'), $lang->code) }}"
                class="dropdown-item {{ $isCurrent ? 'active pointer-events-none' : '' }}"
                data-lng="{{ $lang->code }}"
                @if ($isCurrent) aria-current="page" @endif
            >
                <span class="flex items-center gap-3">
                    @if ($lang->flag)
                        <img
                            src="{{ asset($lang->flag) }}"
                            alt="{{ $label }}"
                            class="w-5 h-5 rounded-full object-cover border border-secondary-200"
                        >
                    @else
                        <span class="w-5 h-5 rounded-full bg-light-primary text-primary text-[10px] font-semibold inline-flex items-center justify-center">
                            {{ strtoupper(substr($lang->code, 0, 2)) }}
                        </span>
                    @endif

                    <span class="flex flex-col leading-tight">
                        <span class="font-medium">{{ $label }}</span>
                        <small class="text-muted">{{ $meta }}</small>
                    </span>
                </span>
            </a>
        @endforeach
    </div>
</li>
