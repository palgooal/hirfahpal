<main data-favorites-page>
  @include('storefront.account.page-header', [
      'current' => 'المفضلة',
      'eyebrow' => 'القطع التي حفظتها لوقت لاحق',
      'title' => 'مفضلتي',
      'subtitle' => new \Illuminate\Support\HtmlString('<bdi id="favoritesCount">5</bdi> قطع محفوظة من <bdi id="favoritesVendorCount">3</bdi> حرفيين ومشاغل — كل قطعة عليها اسم التاجر عشان تعرف مصدرها بسرعة.'),
  ])
  <section class="bg-canvas px-4 py-10 sm:py-12 lg:px-8">
    <div class="mx-auto grid max-w-[1216px] gap-6 lg:grid-cols-[250px_minmax(0,1fr)] lg:items-start">
      @include('storefront.account.sidebar', ['active' => 'favorites'])
      <div class="min-w-0">
    <section id="favoritesPopulatedState">
      <div
        class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 flex flex-col gap-6">

        <div class="flex flex-col gap-3 rounded-[20px] border border-line bg-surface p-3 shadow-card sm:flex-row sm:items-center sm:justify-between sm:p-4">
          <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative flex-1 sm:max-w-[260px]">
              <i data-lucide="search" class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"></i>
              <input id="favoritesSearch" type="search" placeholder="ابحث باسم القطعة أو التاجر..."
                class="h-10 w-full rounded-[12px] border border-line bg-canvas ps-9 pe-3 text-sm outline-none focus:border-sage">
            </div>
            <div class="relative sm:w-[220px]">
              <select id="favoritesVendorFilter"
                class="h-10 w-full appearance-none rounded-[12px] border border-line bg-canvas ps-3 pe-8 text-sm font-semibold text-ink outline-none focus:border-sage">
                <option value="all">كل التجار</option>
                <option value="دار الكرمة للخزف">دار الكرمة للخزف</option>
                <option value="تعاونية نساء نابلس">تعاونية نساء نابلس</option>
                <option value="جمعية إحسان بيت لحم">جمعية إحسان بيت لحم</option>
              </select>
              <i data-lucide="chevron-down" class="pointer-events-none absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"></i>
            </div>
          </div>
          <p id="favoritesResultsSummary" class="text-start text-sm font-semibold text-muted">عرض <bdi>5</bdi> من <bdi>5</bdi> قطعة</p>
        </div>

        <div id="favoritesNoMatchState" class="hidden flex-col items-center gap-3 rounded-[20px] border border-dashed border-line bg-surface px-4 py-12 text-center">
          <i data-lucide="search-x" class="h-8 w-8 text-muted"></i>
          <p class="text-sm font-semibold text-muted">ما لقينا قطع تطابق بحثك أو التاجر المختار.</p>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6" data-favorites-grid>
          <article
            class="favorite-card product-card [&_img]:transition-transform [&_img]:duration-500 [&_img]:ease-out hover:[&_img]:scale-[1.045] group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-product-id="pitcher-blue-hebron" data-vendor="دار الكرمة للخزف" data-date-added="2026-09-24">
            <div class="relative h-[284px] shrink-0 overflow-hidden bg-canvas"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-pitcher.png') }}"
                alt="إبريق خزفي أزرق من دار الكرمة" class="h-full w-full object-cover"><span
                class="absolute end-3 top-3 rounded-lg bg-copper px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">جديد</span><button
                type="button"
                class="remove-favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface text-copper shadow-sm"
                aria-label="إزالة إبريق الخليل الأزرق من المفضلة"><i data-lucide="heart" class="h-[15px] w-[15px] fill-current"></i></button><button
                type="button"
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="145"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <a role="link" aria-disabled="true" data-deferred-navigation="vendor.html" class="flex justify-start pb-3"><span
                  class="flex items-center gap-1 rounded-lg bg-line/50 px-2 py-0.5 text-xs font-bold leading-4 text-olive">دار
                  الكرمة للخزف • الخليل<img src="{{ asset('assets/storefront/imgs/mcp/arrival-shop.svg') }}" alt=""
                    class="h-[10.354px] w-[11.584px]"></span></a>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6">إبريق الخليل الأزرق</h3></a>
              <div class="flex items-center justify-end gap-1 pb-3"><span
                  class="text-[11px] leading-4 text-[#918f83]">(<bdi>14</bdi>)</span><bdi
                  class="text-xs font-bold leading-4">4.9</bdi><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt=""
                  class="h-[11.083px] w-[11.667px]"></div>
              <div class="mt-auto flex items-center justify-between border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7">₪145</bdi><span
                  class="rounded-md bg-sage px-2 py-0.5 text-[11px] font-medium leading-[16.5px] text-surface">متوفر
                  <bdi>4</bdi> قطع</span></div>
            </div>
          </article>

          <article
            class="favorite-card product-card [&_img]:transition-transform [&_img]:duration-500 [&_img]:ease-out hover:[&_img]:scale-[1.045] group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-product-id="silk-embroidered-cushion" data-vendor="تعاونية نساء نابلس" data-date-added="2026-09-22">
            <div class="relative h-[284px] shrink-0 overflow-hidden bg-canvas"><img src="{{ asset('assets/storefront/imgs/mcp/season-cushion.png') }}"
                alt="وسادة تطريز فلسطيني يدوي خيوط حرير" class="h-full w-full object-cover"><span
                class="absolute end-3 top-3 rounded-lg bg-olive px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">تطريز يدوي <bdi>100%</bdi></span><button
                type="button"
                class="remove-favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface text-copper shadow-sm"
                aria-label="إزالة وسادة التطريز من المفضلة"><i data-lucide="heart" class="h-[15px] w-[15px] fill-current"></i></button><button
                type="button"
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="220"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <a role="link" aria-disabled="true" data-deferred-navigation="vendor.html" class="flex justify-start pb-3"><span
                  class="flex items-center gap-1 rounded-lg bg-line/50 px-2 py-0.5 text-xs font-bold leading-4 text-olive">تعاونية
                  نساء نابلس • نابلس<img src="{{ asset('assets/storefront/imgs/mcp/arrival-shop.svg') }}" alt=""
                    class="h-[10.354px] w-[11.584px]"></span></a>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6">وسادة تطريز خيوط حرير طبيعي</h3></a>
              <div class="flex items-center justify-end gap-1 pb-3"><span
                  class="text-[11px] leading-4 text-[#918f83]">(<bdi>22</bdi>)</span><bdi
                  class="text-xs font-bold leading-4">5.0</bdi><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt=""
                  class="h-[11.083px] w-[11.667px]"></div>
              <div class="mt-auto flex items-center justify-between border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7">₪220</bdi><span
                  class="rounded-md bg-sage px-2 py-0.5 text-[11px] font-medium leading-[16.5px] text-surface">متوفر
                  <bdi>3</bdi> قطع</span></div>
            </div>
          </article>

          <article
            class="favorite-card product-card [&_img]:transition-transform [&_img]:duration-500 [&_img]:ease-out hover:[&_img]:scale-[1.045] group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-product-id="straw-basket-hebron" data-vendor="جمعية إحسان بيت لحم" data-date-added="2026-09-20">
            <div class="relative h-[284px] shrink-0 overflow-hidden bg-canvas"><img src="{{ asset('assets/storefront/imgs/mcp/season-basket.png') }}"
                alt="سلة قش فلسطينية من الخليل مجدولة يدوياً" class="h-full w-full object-cover"><span
                class="absolute end-3 top-3 rounded-lg bg-gold px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">إصدار
                محدود</span><button
                type="button"
                class="remove-favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface text-copper shadow-sm"
                aria-label="إزالة سلة القش من المفضلة"><i data-lucide="heart" class="h-[15px] w-[15px] fill-current"></i></button><button
                type="button"
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="95"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <a role="link" aria-disabled="true" data-deferred-navigation="vendor.html" class="flex justify-start pb-3"><span
                  class="flex items-center gap-1 rounded-lg bg-line/50 px-2 py-0.5 text-xs font-bold leading-4 text-olive">جمعية
                  إحسان بيت لحم • بيت لحم<img src="{{ asset('assets/storefront/imgs/mcp/arrival-shop.svg') }}" alt=""
                    class="h-[10.354px] w-[11.584px]"></span></a>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6">سلة قش فلسطينية من الخليل</h3></a>
              <div class="flex items-center justify-end gap-1 pb-3"><span
                  class="text-[11px] leading-4 text-[#918f83]">(<bdi>11</bdi>)</span><bdi
                  class="text-xs font-bold leading-4">4.6</bdi><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt=""
                  class="h-[11.083px] w-[11.667px]"></div>
              <div class="mt-auto flex items-center justify-between border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7">₪95</bdi><span
                  class="rounded-md bg-sage px-2 py-0.5 text-[11px] font-medium leading-[16.5px] text-surface">متوفر
                  <bdi>5</bdi> قطع</span></div>
            </div>
          </article>

          <article
            class="favorite-card product-card [&_img]:transition-transform [&_img]:duration-500 [&_img]:ease-out hover:[&_img]:scale-[1.045] group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-product-id="serving-plate-ornate" data-vendor="دار الكرمة للخزف" data-date-added="2026-09-15">
            <div class="relative h-[284px] shrink-0 overflow-hidden bg-canvas"><img src="{{ asset('assets/storefront/imgs/mcp/category-ceramics.png') }}"
                alt="طبق خزفي مزخرف من دار الكرمة" class="h-full w-full object-cover"><span
                class="absolute end-3 top-3 rounded-lg bg-olive px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-surface shadow-sm">مميز</span><button
                type="button"
                class="remove-favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface text-copper shadow-sm"
                aria-label="إزالة طبق التقديم من المفضلة"><i data-lucide="heart" class="h-[15px] w-[15px] fill-current"></i></button><button
                type="button"
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="120"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <a role="link" aria-disabled="true" data-deferred-navigation="vendor.html" class="flex justify-start pb-3"><span
                  class="flex items-center gap-1 rounded-lg bg-line/50 px-2 py-0.5 text-xs font-bold leading-4 text-olive">دار
                  الكرمة للخزف • الخليل<img src="{{ asset('assets/storefront/imgs/mcp/arrival-shop.svg') }}" alt=""
                    class="h-[10.354px] w-[11.584px]"></span></a>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6">طبق تقديم مزخرف</h3></a>
              <div class="flex items-center justify-end gap-1 pb-3"><span
                  class="text-[11px] leading-4 text-[#918f83]">(<bdi>9</bdi>)</span><bdi
                  class="text-xs font-bold leading-4">4.8</bdi><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt=""
                  class="h-[11.083px] w-[11.667px]"></div>
              <div class="mt-auto flex items-center justify-between border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7">₪120</bdi><span
                  class="rounded-md bg-sage px-2 py-0.5 text-[11px] font-medium leading-[16.5px] text-surface">متوفر
                  <bdi>6</bdi> قطع</span></div>
            </div>
          </article>

          <article
            class="favorite-card product-card [&_img]:transition-transform [&_img]:duration-500 [&_img]:ease-out hover:[&_img]:scale-[1.045] group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-product-id="olive-oil-candle" data-vendor="تعاونية نساء نابلس" data-date-added="2026-09-10">
            <div class="relative h-[284px] shrink-0 overflow-hidden bg-canvas"><img src="{{ asset('assets/storefront/imgs/mcp/popular-candle.png') }}"
                alt="شمعة زيت زيتون في فخار ريفي" class="h-full w-full object-cover"><span
                class="absolute end-3 top-3 rounded-lg bg-olive px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">طبيعي <bdi>100%</bdi></span><button
                type="button"
                class="remove-favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface text-copper shadow-sm"
                aria-label="إزالة الشمعة من المفضلة"><i data-lucide="heart" class="h-[15px] w-[15px] fill-current"></i></button><button
                type="button"
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="68"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <a role="link" aria-disabled="true" data-deferred-navigation="vendor.html" class="flex justify-start pb-3"><span
                  class="flex items-center gap-1 rounded-lg bg-line/50 px-2 py-0.5 text-xs font-bold leading-4 text-olive">تعاونية
                  نساء نابلس • نابلس<img src="{{ asset('assets/storefront/imgs/mcp/arrival-shop.svg') }}" alt=""
                    class="h-[10.354px] w-[11.584px]"></span></a>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6">شمعة زيت زيتون في فخار ريفي</h3></a>
              <div class="flex items-center justify-end gap-1 pb-3"><span
                  class="text-[11px] leading-4 text-[#918f83]">(<bdi>17</bdi>)</span><bdi
                  class="text-xs font-bold leading-4">4.7</bdi><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt=""
                  class="h-[11.083px] w-[11.667px]"></div>
              <div class="mt-auto flex items-center justify-between border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7">₪68</bdi><span
                  class="rounded-md bg-sage px-2 py-0.5 text-[11px] font-medium leading-[16.5px] text-surface">متوفر
                  <bdi>9</bdi> قطع</span></div>
            </div>
          </article>
        </div>
      </div>
    </section>

    {{-- Static demo page: cards and counts are approved reference content; this empty state stays hidden until a favorites backend exists. --}}
    <section id="favoritesEmptyState" class="hidden">
      <div class="flex flex-col items-center gap-4 text-center">
        <span class="flex h-20 w-20 items-center justify-center rounded-full bg-surface text-muted shadow-card">
          <i data-lucide="heart" class="h-9 w-9"></i>
        </span>
        <h2 class="text-xl font-bold leading-8 text-ink">لسا ما ضفت شي للمفضلة</h2>
        <p class="max-w-sm text-sm leading-6 text-muted">اضغط على أيقونة القلب فوق أي قطعة تعجبك وراح تظهر هون
          عشان ترجعلها بسهولة.</p>
        <div class="mt-2 flex flex-wrap items-center justify-center gap-3">
          <a href="{{ route('categories') }}"
            class="inline-flex h-11 items-center justify-center gap-2 rounded-[14px] bg-sage px-5 text-sm font-bold text-ink shadow-sm">
            <span>تصفح التصنيفات</span>
          </a>
          <a href="{{ route('vendors') }}"
            class="inline-flex h-11 items-center justify-center gap-2 rounded-[14px] border border-line bg-surface px-5 text-sm font-bold text-olive">
            <span>تصفح كل المتاجر</span>
          </a>
        </div>
      </div>
    </section>

      </div>
    </div>
  </section>
</main>
