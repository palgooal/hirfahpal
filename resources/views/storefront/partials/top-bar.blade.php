{{-- Pending assets: public/assets/storefront/imgs/mcp/ (copied in a later phase). --}}
    <aside class="bg-sage px-4 py-2 text-ink lg:px-8" data-node-id="223:3409">
      <div class="mx-auto flex h-[32.667px] max-w-[1280px] items-center justify-center md:justify-between">
        <div class="flex shrink-0 items-center gap-3">
          <div class="flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-0.5">
            <img src="{{ asset('assets/storefront/imgs/mcp/header-badge.svg') }}" alt="" class="h-[11.88px] w-[12.441px]">
            <span class="text-[11px] leading-4 text-surface">توثيق التراث الأصيل</span>
          </div>
          <p class="hidden text-xs leading-4 tracking-[.3px] lg:block">توصيل منظم ومؤمّن من أيدي الحرفيين في القدس،
            الخليل ونابلس إلى بابك مباشرة</p>
        </div>
        <div class="hidden shrink-0 items-center gap-5 md:flex">
          <div class="flex items-center gap-1.5">
            <img src="{{ asset('assets/storefront/imgs/mcp/header-lock.svg') }}" alt="" class="h-[12.656px] w-[9.687px]">
            <span class="text-[11px] leading-4">دفع آمن أو عند الاستلام</span>
          </div>
          <span class="text-[11px] leading-4">|</span>
          <div class="flex items-center gap-2">
            <span lang="en" class="text-[11px] font-bold leading-4 text-surface">{{ $currency ?? 'ILS (₪)' }}</span>
            <span class="text-[11px] leading-4">/</span>
            {{-- Locale switching is deferred; retain the original visual only. --}}
            <button type="submit" form="locale-top-bar-0" class="language-toggle flex items-center gap-1 rounded-lg p-2"
              aria-label="Switch to English">
              <span class="language-label text-sm font-bold leading-[14px]" lang="en">{{ strtoupper($languages->firstWhere('code', '!=', app()->getLocale())?->code ?? app()->getLocale()) }}</span>
              <img src="{{ asset('assets/storefront/imgs/mcp/header-globe.svg') }}" alt="" class="h-[16.667px] w-[16.667px]">
            </button><form hidden id="locale-top-bar-0" method="GET" action="{{ url()->current() }}"><input type="hidden" name="change-locale" value="{{ $languages->firstWhere('code', '!=', app()->getLocale())?->code ?? app()->getLocale() }}"></form>
          </div>
        </div>
      </div>
    </aside>
