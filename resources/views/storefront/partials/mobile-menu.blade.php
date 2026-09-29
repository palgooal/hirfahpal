@php($onHome = request()->routeIs('home'))
    <div id="mobileMenu" class="hidden border-t border-line bg-surface px-5 py-4 lg:hidden">
      <nav class="grid gap-1 text-sm font-semibold">
        <a href="{{ $onHome ? '#home' : route('home') }}" class="py-2">الرئيسية</a>

        <div class="mobile-nav-dropdown">
          <button type="button" class="mobile-nav-dropdown-toggle flex w-full items-center justify-between py-2"
            aria-expanded="false">
            <span>تسوّق</span>
            <i data-lucide="chevron-down" class="h-4 w-4 transition-transform duration-200"></i>
          </button>
          <div class="mobile-nav-dropdown-panel hidden ps-4">
            <a href="{{ route('browse', ['category' => 'pottery']) }}" class="block py-2 text-muted">الفخار والخزف</a>
            <a href="{{ route('browse', ['category' => 'embroidery']) }}" class="block py-2 text-muted">التطريز الفلسطيني</a>
            <a href="{{ route('browse', ['category' => 'baskets']) }}" class="block py-2 text-muted">السلال والقش</a>
            <a href="{{ route('browse', ['category' => 'candles-soaps']) }}" class="block py-2 text-muted">الشموع والصابون</a>
            <a href="{{ route('categories') }}" class="block py-2 font-bold text-olive">استعراض جميع الفئات</a>
          </div>
        </div>

        <a href="{{ route('vendors') }}" class="py-2">الحرفيون والمتاجر</a>
        <a role="link" aria-disabled="true" data-deferred-navigation="browse.html?sale=1" class="py-2">العروض</a>
        <a href="{{ $onHome ? '#season' : route('home') . '#season' }}" class="py-2">مختارات الموسم</a>
        <a href="{{ $onHome ? '#artisans' : route('home') . '#artisans' }}" class="py-2">حكايات حِرفة</a>
      </nav>
    </div>
