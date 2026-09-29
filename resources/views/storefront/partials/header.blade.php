@php($onHome = request()->routeIs('home'))
{{-- Remaining desktop-only assets under public/assets/storefront/imgs/mcp/ are pending. --}}
{{-- Navigation and control behavior are deferred; cart values are original design placeholders. --}}
    <div class="h-[70px] bg-surface px-4 shadow-[0_2px_7.5px_rgba(43,43,38,.03)] backdrop-blur-[6px] lg:px-8"
      data-node-id="223:3436">
      <div class="mx-auto hidden h-full max-w-[1216px] items-center justify-between py-[10px] lg:flex">
        <a href="{{ $onHome ? '#home' : route('home') }}" class="flex w-[184px] shrink-0 items-center justify-start gap-3" aria-label="حرفة الرئيسية"
          data-node-id="223:3474">
          <span class="flex h-[58px] w-[58px] items-center justify-center rounded-[16px] bg-surface p-1 shadow-sm"><img
              src="{{ asset('assets/storefront/imgs/mcp/header-logo.png') }}" alt="شعار حرفة" class="h-[50px] w-[50px] object-cover"></span>
          <div class="text-right">
            <div class="flex items-center justify-start gap-1.5"><span
                class="h-1.5 w-1.5 rounded-full bg-copper"></span><strong
                class="text-2xl font-bold leading-8 tracking-[-.6px] text-olive">حِــرْفَة</strong></div><small
              class="block text-[10px] font-medium leading-[15px] tracking-[.5px] text-olive">سوق الصنعة
              الفلسطينية</small>
          </div>
        </a>

        <nav class="flex shrink-0 items-center gap-8 text-[15px] font-semibold leading-[22.5px] text-olive"
          data-node-id="223:3463">
          <a href="{{ $onHome ? '#home' : route('home') }}" class="border-b-2 border-copper pb-1.5 font-bold text-copper">الرئيسية</a>

          <div class="mega-menu group relative py-1">
            <button type="button" class="flex items-center gap-1.5" aria-haspopup="true" aria-expanded="false">
              <span>تسوّق</span>
              <i data-lucide="chevron-down" class="h-4 w-4 transition-transform duration-200 group-[.mega-menu-open]:rotate-180"></i>
            </button>
            @include('storefront.partials.mega-menu')
          </div>

          <a href="{{ route('vendors') }}" class="py-1">الحرفيون والمتاجر</a>
          <a role="link" aria-disabled="true" data-deferred-navigation="browse.html?sale=1" class="py-1">العروض</a>
          <a href="{{ $onHome ? '#season' : route('home') . '#season' }}" class="py-1">مختارات الموسم</a>
          <a href="{{ $onHome ? '#artisans' : route('home') . '#artisans' }}" class="py-1">حكايات حِرفة</a>
        </nav>

        <div class="flex h-[50px] shrink-0 items-center gap-3 p-[10px]" data-node-id="223:3438">
          <button type="button" disabled aria-disabled="true" id="searchButton"
            class="flex h-[30px] w-[30px] items-center justify-center overflow-hidden rounded-full border border-olive bg-olive p-px"
            aria-label="بحث"><img src="{{ asset('assets/storefront/imgs/mcp/header-search-exact.svg') }}" alt=""
              class="h-[28.667px] w-[14.663px] max-w-none"></button>
          <a role="link" aria-disabled="true" data-deferred-navigation="profile.html" class="flex h-[30px] w-[30px] items-center justify-center rounded-full" aria-label="الحساب"><img
              src="{{ asset('assets/storefront/imgs/mcp/header-user.svg') }}" alt="" class="h-[16.283px] w-[17.875px]"></a>
          <button type="button" disabled aria-disabled="true" class="relative flex h-[30px] w-[30px] items-center justify-center rounded-full"
            aria-label="الإشعارات"><img src="{{ asset('assets/storefront/imgs/mcp/header-bell.svg') }}" alt=""
              class="h-[17.963px] w-[14.208px]"><span
              class="absolute right-[5.42px] top-[2.33px] h-2 w-2 rounded-full bg-copper"></span></button>
          <button type="button" disabled aria-disabled="true" class="flex h-[30px] w-[30px] items-center justify-center rounded-full" aria-label="المشاهدة"><img
              src="{{ asset('assets/storefront/imgs/mcp/header-eye.svg') }}" alt="" class="h-[14.032px] w-[14.208px]"></button>
          <button type="button" disabled aria-disabled="true" id="cartButton"
            class="relative flex h-[30px] w-[86.208px] items-center justify-center gap-2.5 rounded-full border border-olive bg-olive px-[11px] text-surface"
            aria-label="سلة المشتريات">
            <span class="relative"><img src="{{ asset('assets/storefront/imgs/mcp/header-cart.svg') }}" alt=""
                class="h-[17.875px] w-[14.208px]"><span id="cartCount"
                class="absolute left-[-12px] top-[-7.73px] flex h-[15px] min-w-[15px] items-center justify-center rounded-full bg-copper px-1 text-[11px] font-bold leading-[11px] text-white">2</span></span>
            <bdi id="cartTotal" class="w-10 text-right text-xs font-bold leading-3">₪365</bdi>
          </button>
        </div>
      </div>

      <div class="flex h-full items-center justify-between lg:hidden">
        <a href="{{ $onHome ? '#home' : route('home') }}"
          class="flex h-[52px] w-[52px] items-center justify-center rounded-[16px] bg-surface p-1 shadow-sm"><img
            src="{{ asset('assets/storefront/imgs/mcp/header-logo.png') }}" alt="شعار حرفة" class="h-11 w-11 object-cover"></a>
        <div class="flex items-center gap-1"><button type="button" disabled aria-disabled="true"
            class="language-toggle flex h-9 items-center gap-1 rounded-full px-2 text-xs font-bold text-olive"
            aria-label="Switch to English"><span class="language-label" lang="en">EN</span><img
              src="{{ asset('assets/storefront/imgs/mcp/header-globe.svg') }}" alt="" class="h-4 w-4"></button><button id="mobileSearchButton"
            class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full border border-olive bg-olive"
            aria-label="بحث"><img src="{{ asset('assets/storefront/imgs/mcp/header-search-exact.svg') }}" alt=""
              class="h-[28.667px] w-[14.663px] max-w-none"></button><button id="menuButton"
            class="flex h-9 w-9 items-center justify-center rounded-full" aria-label="القائمة"><i data-lucide="menu"
              class="h-5 w-5"></i></button></div>
      </div>
    </div>
