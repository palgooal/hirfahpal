<main>
    <section class="border-b border-line/60 bg-canvas px-4 py-6 lg:px-8">
      <div class="mx-auto flex max-w-[1216px] flex-col gap-5">
        <nav class="flex flex-wrap items-center gap-2 text-xs font-medium leading-4 text-muted sm:text-sm"
          aria-label="مسار الصفحة">
          <a href="{{ route('home') }}" class="hover:text-olive">الرئيسية</a>
          <span class="text-line">/</span>
          <a href="{{ route('categories') }}" class="hover:text-olive">كل التصنيفات</a>
          <span class="text-line">/</span>
          <span id="breadcrumbCurrent" class="font-semibold text-olive">نتائج البحث</span>
        </nav>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div class="max-w-3xl text-start">
            <p class="flex items-center gap-1.5 text-xs font-bold uppercase leading-4 text-copper"><span
                class="h-1.5 w-1.5 rounded-full bg-copper"></span>نتائج مفلترة من السوق</p>
            <h1 class="pt-2 text-[30px] font-bold leading-9 text-ink sm:text-4xl sm:leading-[48px]">منتجات الحرفيين</h1>
            <p class="pt-2 text-sm leading-6 text-muted sm:text-base">صفّ النتائج حسب الفئة أو التاجر أو المدينة، ثم رتّبها بالطريقة التي تناسب قرار الشراء.</p>
          </div>
          <div class="flex items-center gap-2 rounded-[16px] border border-line bg-surface px-4 py-3 text-sm font-semibold text-olive shadow-sm">
            <i data-lucide="package-search" class="h-5 w-5 text-copper"></i>
            <span>النتائج المتاحة:</span>
            <bdi id="resultCount">0</bdi>
          </div>
        </div>
      </div>
    </section>

    <section id="resultsPanel" class="bg-canvas px-4 py-10 sm:py-12 lg:px-8">
      <div
        class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 mx-auto flex max-w-[1216px] flex-col gap-6">
        <button id="browseFilterToggle" type="button"
          class="inline-flex w-fit items-center justify-center gap-2 rounded-[16px] border border-line bg-surface px-4 py-2.5 text-sm font-bold text-olive shadow-sm lg:hidden"
          aria-controls="browseFilterPanel" aria-expanded="false">
          <i data-lucide="sliders-horizontal" class="h-4 w-4"></i>
          <span>الفلاتر</span>
        </button>

        <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
          <aside id="browseFilterPanel" class="hidden lg:sticky lg:top-6 lg:flex lg:w-[300px] lg:shrink-0 lg:flex-col lg:overflow-hidden lg:max-h-[calc(100vh-3rem)]">
            <form id="browseFilters"
              class="scroll-thin flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto rounded-[20px] border border-line bg-surface p-4 shadow-card sm:p-5"
              aria-label="فلاتر نتائج المنتجات">
              <div class="text-start">
                <h2 class="text-[22px] font-semibold leading-8 text-ink">تصفية النتائج</h2>
                <p class="text-sm leading-6 text-muted">اختر الفئة أو التاجر أو المدينة وحدد نطاق السعر.</p>
              </div>

              <fieldset class="min-w-0">
                <legend class="pb-2 text-start text-sm font-bold text-ink">الفئة</legend>
                <div class="grid gap-2 text-sm text-muted">
                  <label class="flex items-center gap-2"><input id="categoryPottery" name="category" value="pottery" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>الفخار والخزف</span></label>
                  <label class="flex items-center gap-2"><input id="categoryEmbroidery" name="category" value="embroidery" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>التطريز</span></label>
                  <label class="flex items-center gap-2"><input id="categoryBaskets" name="category" value="baskets" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>القش والسلال</span></label>
                  <label class="flex items-center gap-2"><input id="categoryCandles" name="category" value="candles-soaps" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>الشموع والصابون</span></label>
                  <label class="flex items-center gap-2"><input id="categoryTextiles" name="category" value="textiles" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>المنسوجات والوسائد</span></label>
                  <label class="flex items-center gap-2"><input id="categoryTableware" name="category" value="tableware" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>أواني التقديم</span></label>
                  <label class="flex items-center gap-2"><input id="categoryGifts" name="category" value="gifts" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>الهدايا التراثية</span></label>
                  <label class="flex items-center gap-2"><input id="categoryHomeDecor" name="category" value="home-decor" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>زينة البيت اليدوية</span></label>
                </div>
              </fieldset>

              <fieldset class="min-w-0">
                <legend class="pb-2 text-start text-sm font-bold text-ink">التاجر</legend>
                <div class="grid gap-2 text-sm text-muted">
                  <label class="flex items-center gap-2"><input id="vendorKarma" name="vendor" value="dar-al-karma" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>دار الكرمة للخزف</span></label>
                  <label class="flex items-center gap-2"><input id="vendorBethlehem" name="vendor" value="bethlehem-women" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>جمعية نساء بيت لحم</span></label>
                  <label class="flex items-center gap-2"><input id="vendorNoor" name="vendor" value="noor-alzaytouna" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>مشغل نور الزيتونة</span></label>
                  <label class="flex items-center gap-2"><input id="vendorJalil" name="vendor" value="jalil-weaving" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>نسج الجليل التراثي</span></label>
                  <label class="flex items-center gap-2"><input id="vendorOlivewood" name="vendor" value="jerusalem-olivewood" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>خشب الزيتون المقدسي</span></label>
                  <label class="flex items-center gap-2"><input id="vendorRawaq" name="vendor" value="rawaq-ramallah" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>منسوجات رواق رام الله</span></label>
                  <label class="flex items-center gap-2"><input id="vendorSoap" name="vendor" value="khan-nablus-soap" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>صابون خان نابلس</span></label>
                  <label class="flex items-center gap-2"><input id="vendorMarj" name="vendor" value="marj-embroidery" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>تطريز مرج ابن عامر</span></label>
                </div>
              </fieldset>

              <fieldset class="min-w-0">
                <legend class="pb-2 text-start text-sm font-bold text-ink">المدينة</legend>
                <div class="grid gap-2 text-sm text-muted">
                  <label class="flex items-center gap-2"><input id="cityJerusalem" name="city" value="jerusalem" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>القدس</span></label>
                  <label class="flex items-center gap-2"><input id="cityHebron" name="city" value="hebron" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>الخليل</span></label>
                  <label class="flex items-center gap-2"><input id="cityBethlehem" name="city" value="bethlehem" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>بيت لحم</span></label>
                  <label class="flex items-center gap-2"><input id="cityRamallah" name="city" value="ramallah" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>رام الله</span></label>
                  <label class="flex items-center gap-2"><input id="cityNablus" name="city" value="nablus" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>نابلس</span></label>
                  <label class="flex items-center gap-2"><input id="cityJenin" name="city" value="jenin" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>جنين</span></label>
                  <label class="flex items-center gap-2"><input id="cityGalilee" name="city" value="galilee" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>الجليل</span></label>
                </div>
              </fieldset>

              <fieldset class="min-w-0">
                <legend class="pb-2 text-start text-sm font-bold text-ink">السعر</legend>
                <div class="grid gap-2">
                  <label for="priceMin" class="text-start text-xs font-semibold text-muted">من</label>
                  <input id="priceMin" name="priceMin" type="number" min="0" inputmode="numeric" placeholder="أدنى"
                    class="h-10 rounded-[12px] border border-line bg-canvas px-3 text-start text-sm outline-none focus:border-sage">
                  <label for="priceMax" class="text-start text-xs font-semibold text-muted">إلى</label>
                  <input id="priceMax" name="priceMax" type="number" min="0" inputmode="numeric" placeholder="أعلى"
                    class="h-10 rounded-[12px] border border-line bg-canvas px-3 text-start text-sm outline-none focus:border-sage">
                  <button id="applyPriceFilter" type="button"
                    class="mt-1 inline-flex h-10 items-center justify-center gap-2 rounded-[14px] bg-sage px-4 text-xs font-bold text-ink hover:bg-[#a7af82]">
                    <i data-lucide="sliders-horizontal" class="h-4 w-4"></i>
                    <span>تطبيق السعر</span>
                  </button>
                </div>
              </fieldset>

              <button id="clearAllFilters" type="button"
                class="hidden w-full items-center justify-center gap-2 rounded-[14px] border border-line bg-canvas px-4 py-2.5 text-xs font-bold text-olive hover:border-sage">
                <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                <span>إعادة ضبط الكل</span>
              </button>
            </form>
          </aside>

          <div class="flex min-w-0 flex-1 flex-col gap-6">
            <div id="activeFilters" class="hidden flex-wrap items-center gap-2"></div>

            <div class="flex flex-col gap-3 rounded-[20px] border border-line bg-surface p-3 shadow-card sm:flex-row sm:items-center sm:justify-between sm:p-4">
              <div id="sortTabs" class="flex flex-wrap items-center gap-1.5" role="tablist" aria-label="ترتيب النتائج">
                <button type="button" data-sort="newest"
                  class="sort-tab rounded-[12px] px-3.5 py-2 text-xs font-bold text-muted hover:text-olive [&.sort-tab-active]:bg-olive [&.sort-tab-active]:text-surface">الأحدث</button>
                <button type="button" data-sort="price-asc"
                  class="sort-tab rounded-[12px] px-3.5 py-2 text-xs font-bold text-muted hover:text-olive [&.sort-tab-active]:bg-olive [&.sort-tab-active]:text-surface">السعر: الأقل للأعلى</button>
                <button type="button" data-sort="price-desc"
                  class="sort-tab rounded-[12px] px-3.5 py-2 text-xs font-bold text-muted hover:text-olive [&.sort-tab-active]:bg-olive [&.sort-tab-active]:text-surface">السعر: الأعلى للأقل</button>
                <button type="button" data-sort="rating-desc"
                  class="sort-tab rounded-[12px] px-3.5 py-2 text-xs font-bold text-muted hover:text-olive [&.sort-tab-active]:bg-olive [&.sort-tab-active]:text-surface">الأعلى تقييماً</button>
              </div>
              <p id="pageSummary" class="text-start text-sm font-semibold text-muted">عرض <bdi>1</bdi>-<bdi>9</bdi> من <bdi>16</bdi> نتيجة</p>
            </div>

        <div id="productGrid" class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6 xl:grid-cols-3">
          <article
            class="product-card group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-category="pottery" data-vendor="dar-al-karma" data-city="hebron" data-price="145" data-rating="4.9" data-date="116">
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-pitcher.png') }}"
                alt="إبريق فخار مقدسي مزخرف" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.045]"><span
                class="absolute end-3 top-3 rounded-lg bg-copper px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">جديد</span><button
                class="favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة إبريق الفخار إلى المفضلة"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><button
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold text-ink opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="145"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex items-center justify-between gap-2 pb-1 text-[11px] leading-4 text-muted"><span>دار الكرمة للخزف • الخليل</span><span
                  class="flex items-center gap-1"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11px] w-3"><bdi
                    class="text-xs font-bold text-ink">4.9</bdi><bdi class="text-[#918f83]">(14)</bdi></span></div>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6 text-ink">إبريق فخار مقدسي مزخرف</h3></a>
              <p class="pb-3 text-start text-xs leading-4 text-[#918f83]">خزف يدوي مسكوب ومزجج بزخارف زرقاء مقاومة للحرارة.</p>
              <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7 text-ink">₪145</bdi><span
                  class="rounded-md bg-sage px-2 py-0.5 text-[11px] font-medium leading-[16.5px] text-surface">متوفر <bdi>4</bdi> قطع</span></div>
            </div>
          </article>

          <article
            class="product-card group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-category="embroidery" data-vendor="bethlehem-women" data-city="bethlehem" data-price="220" data-rating="4.8" data-date="115">
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cushion.png') }}"
                alt="وسادة مطرزة قطبة فلاحية" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.045]"><span
                class="absolute end-3 top-3 rounded-lg bg-copper px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">جديد</span><button
                class="favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة الوسادة إلى المفضلة"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><button
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold text-ink opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="220"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex items-center justify-between gap-2 pb-1 text-[11px] leading-4 text-muted"><span>جمعية نساء بيت لحم • بيت لحم</span><span
                  class="flex items-center gap-1"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11px] w-3"><bdi
                    class="text-xs font-bold text-ink">4.8</bdi><bdi class="text-[#918f83]">(9)</bdi></span></div>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6 text-ink">وسادة مطرزة قطبة فلاحية</h3></a>
              <p class="pb-3 text-start text-xs leading-4 text-[#918f83]">كتان طبيعي مطرز بغرزة الفلاحي ونقشة بيتية دافئة.</p>
              <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7 text-ink">₪220</bdi><span
                  class="rounded-md bg-copper/10 px-2 py-0.5 text-[11px] font-medium leading-[16.5px] text-copper">قطعتان فقط</span></div>
            </div>
          </article>

          <article
            class="product-card group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-category="baskets" data-vendor="jalil-weaving" data-city="galilee" data-price="95" data-rating="4.7" data-date="114">
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-basket.png') }}"
                alt="سلة قش قمحية مجدولة" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.045]"><span
                class="absolute end-3 top-3 rounded-lg bg-gold px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">قطعة نادرة</span><button
                class="favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة سلة القش إلى المفضلة"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><button
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold text-ink opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="95"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex items-center justify-between gap-2 pb-1 text-[11px] leading-4 text-muted"><span>نسج الجليل التراثي • الجليل</span><span
                  class="flex items-center gap-1"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11px] w-3"><bdi
                    class="text-xs font-bold text-ink">4.7</bdi><bdi class="text-[#918f83]">(19)</bdi></span></div>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6 text-ink">سلة قش قمحية مجدولة</h3></a>
              <p class="pb-3 text-start text-xs leading-4 text-[#918f83]">جدل يدوي من سيقان القمح المجففة بألوان نباتية هادئة.</p>
              <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7 text-ink">₪95</bdi><span class="text-[11px] leading-[16.5px] text-muted">قطعة وحيدة</span></div>
            </div>
          </article>

          <article
            class="product-card group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-category="candles-soaps" data-vendor="noor-alzaytouna" data-city="nablus" data-price="68" data-rating="4.9" data-date="113">
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-candle.png') }}"
                alt="شمعة زيت الزيتون في فخار" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.045]"><span
                class="absolute end-3 top-3 rounded-lg bg-olive px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">طبيعي <bdi>100%</bdi></span><button
                class="favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة الشمعة إلى المفضلة"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><button
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold text-ink opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="68"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex items-center justify-between gap-2 pb-1 text-[11px] leading-4 text-muted"><span>مشغل نور الزيتونة • نابلس</span><span
                  class="flex items-center gap-1"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11px] w-3"><bdi
                    class="text-xs font-bold text-ink">4.9</bdi><bdi class="text-[#918f83]">(42)</bdi></span></div>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6 text-ink">شمعة زيت الزيتون في فخار</h3></a>
              <p class="pb-3 text-start text-xs leading-4 text-[#918f83]">شمع طبيعي ممزوج برائحة الميرمية والغار في وعاء فخاري.</p>
              <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7 text-ink">₪68</bdi><span
                  class="rounded-md bg-sage px-2 py-0.5 text-[11px] font-medium leading-[16.5px] text-surface">الأكثر طلباً</span></div>
            </div>
          </article>

          <article
            class="product-card group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-category="tableware" data-vendor="dar-al-karma" data-city="jerusalem" data-price="185" data-rating="4.95" data-date="112">
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ asset('assets/storefront/imgs/mcp/popular-pitcher.png') }}"
                alt="إبريق مقدسي كنعاني أزرق" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.045]"><span
                class="absolute end-3 top-3 rounded-lg bg-copper px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">الأعلى تقييماً</span><button
                class="favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة الإبريق الأزرق إلى المفضلة"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><button
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold text-ink opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="185"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex items-center justify-between gap-2 pb-1 text-[11px] leading-4 text-muted"><span>دار الكرمة للخزف • القدس</span><span
                  class="flex items-center gap-1"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11px] w-3"><bdi
                    class="text-xs font-bold text-ink">4.95</bdi><bdi class="text-[#918f83]">(142)</bdi></span></div>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6 text-ink">إبريق مقدسي كنعاني أزرق</h3></a>
              <p class="pb-3 text-start text-xs leading-4 text-[#918f83]">إبريق تقديم مرسوم يدوياً بزخارف كنعانية دقيقة.</p>
              <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7 text-ink">₪185</bdi><span class="text-[11px] leading-[16.5px] text-muted">صناعة تراثية</span></div>
            </div>
          </article>

          <article
            class="product-card group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-category="textiles" data-vendor="rawaq-ramallah" data-city="ramallah" data-price="155" data-rating="4.6" data-date="111">
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ asset('assets/storefront/imgs/mcp/popular-cushion.png') }}"
                alt="وسادة كنعانية محاكة يدوياً" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.045]"><span
                class="absolute end-3 top-3 rounded-lg bg-olive px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">منسوج يدوي</span><button
                class="favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة الوسادة الكنعانية إلى المفضلة"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><button
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold text-ink opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="155"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex items-center justify-between gap-2 pb-1 text-[11px] leading-4 text-muted"><span>منسوجات رواق رام الله • رام الله</span><span
                  class="flex items-center gap-1"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11px] w-3"><bdi
                    class="text-xs font-bold text-ink">4.6</bdi><bdi class="text-[#918f83]">(28)</bdi></span></div>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6 text-ink">وسادة كنعانية محاكة يدوياً</h3></a>
              <p class="pb-3 text-start text-xs leading-4 text-[#918f83]">قماش قطني محاك بخيوط هادئة ونقشة ريفية أصيلة.</p>
              <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7 text-ink">₪155</bdi><span class="text-[11px] leading-[16.5px] text-muted">متوفر <bdi>6</bdi></span></div>
            </div>
          </article>

          <article
            class="product-card group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-category="candles-soaps" data-vendor="khan-nablus-soap" data-city="nablus" data-price="35" data-rating="4.9" data-date="110">
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ asset('assets/storefront/imgs/mcp/popular-candle.png') }}"
                alt="صابون زيت الزيتون النابلسي" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.045]"><span
                class="absolute end-3 top-3 rounded-lg bg-olive px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">طبيعي <bdi>100%</bdi></span><button
                class="favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة الصابون إلى المفضلة"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><button
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold text-ink opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="35"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex items-center justify-between gap-2 pb-1 text-[11px] leading-4 text-muted"><span>صابون خان نابلس • نابلس</span><span
                  class="flex items-center gap-1"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11px] w-3"><bdi
                    class="text-xs font-bold text-ink">4.9</bdi><bdi class="text-[#918f83]">(57)</bdi></span></div>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6 text-ink">صابون زيت الزيتون النابلسي</h3></a>
              <p class="pb-3 text-start text-xs leading-4 text-[#918f83]">قوالب مقطعة ومختومة يدوياً بطريقة الخانات القديمة.</p>
              <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7 text-ink">₪35</bdi><span
                  class="rounded-md bg-copper/10 px-2 py-0.5 text-[11px] font-medium leading-[16.5px] text-copper">الأكثر مبيعاً</span></div>
            </div>
          </article>

          <article
            class="product-card group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-category="home-decor" data-vendor="jerusalem-olivewood" data-city="jerusalem" data-price="120" data-rating="4.8" data-date="109">
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ asset('assets/storefront/imgs/mcp/season-basket.png') }}"
                alt="زينة بيت يدوية من القش والمنسوجات" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.045]"><span
                class="absolute end-3 top-3 rounded-lg bg-gold px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">للبيت</span><button
                class="favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة زينة البيت إلى المفضلة"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><button
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold text-ink opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="120"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex items-center justify-between gap-2 pb-1 text-[11px] leading-4 text-muted"><span>خشب الزيتون المقدسي • القدس</span><span
                  class="flex items-center gap-1"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11px] w-3"><bdi
                    class="text-xs font-bold text-ink">4.8</bdi><bdi class="text-[#918f83]">(31)</bdi></span></div>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6 text-ink">تعليقة بيت بزخرفة زيتون</h3></a>
              <p class="pb-3 text-start text-xs leading-4 text-[#918f83]">قطعة ديكور صغيرة بخامة طبيعية ولمسة محفورة يدوياً.</p>
              <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7 text-ink">₪120</bdi><span class="text-[11px] leading-[16.5px] text-muted">هدية جاهزة</span></div>
            </div>
          </article>

          <article
            class="product-card group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-category="gifts" data-vendor="marj-embroidery" data-city="jenin" data-price="175" data-rating="4.7" data-date="108">
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ asset('assets/storefront/imgs/mcp/season-cushion.png') }}"
                alt="مجموعة هدية مطرزة" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.045]"><span
                class="absolute end-3 top-3 rounded-lg bg-copper px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">هدية</span><button
                class="favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة مجموعة الهدية إلى المفضلة"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><button
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold text-ink opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="175"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex items-center justify-between gap-2 pb-1 text-[11px] leading-4 text-muted"><span>تطريز مرج ابن عامر • جنين</span><span
                  class="flex items-center gap-1"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11px] w-3"><bdi
                    class="text-xs font-bold text-ink">4.7</bdi><bdi class="text-[#918f83]">(16)</bdi></span></div>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6 text-ink">مجموعة هدية مطرزة</h3></a>
              <p class="pb-3 text-start text-xs leading-4 text-[#918f83]">قطعة مطرزة مع بطاقة قصة وتغليف تراثي بسيط.</p>
              <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7 text-ink">₪175</bdi><span class="text-[11px] leading-[16.5px] text-muted">تغليف مشمول</span></div>
            </div>
          </article>

          <article
            class="product-card group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-category="pottery" data-vendor="dar-al-karma" data-city="hebron" data-price="110" data-rating="4.8" data-date="107">
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ asset('assets/storefront/imgs/mcp/season-candle.png') }}"
                alt="طقم أكواب فخارية مزخرفة" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.045]"><span
                class="absolute end-3 top-3 rounded-lg bg-olive px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">طقم من <bdi>4</bdi></span><button
                class="favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة طقم الأكواب إلى المفضلة"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><button
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold text-ink opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="110"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex items-center justify-between gap-2 pb-1 text-[11px] leading-4 text-muted"><span>دار الكرمة للخزف • الخليل</span><span
                  class="flex items-center gap-1"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11px] w-3"><bdi
                    class="text-xs font-bold text-ink">4.8</bdi><bdi class="text-[#918f83]">(31)</bdi></span></div>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6 text-ink">طقم أكواب فخارية مزخرفة</h3></a>
              <p class="pb-3 text-start text-xs leading-4 text-[#918f83]">أكواب فخار مشوية يدوياً برسومات نباتية تقليدية.</p>
              <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7 text-ink">₪110</bdi><span class="text-[11px] leading-[16.5px] text-muted">مقاوم للحرارة</span></div>
            </div>
          </article>

          <article
            class="product-card group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-category="baskets" data-vendor="jalil-weaving" data-city="hebron" data-price="85" data-rating="4.7" data-date="106">
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ asset('assets/storefront/imgs/mcp/season-pitcher.png') }}"
                alt="سلة قش دائرية بنقوش ملونة" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.045]"><span
                class="absolute end-3 top-3 rounded-lg bg-gold px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">إصدار محدود</span><button
                class="favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة السلة الدائرية إلى المفضلة"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><button
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold text-ink opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="85"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex items-center justify-between gap-2 pb-1 text-[11px] leading-4 text-muted"><span>نسج الجليل التراثي • الخليل</span><span
                  class="flex items-center gap-1"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11px] w-3"><bdi
                    class="text-xs font-bold text-ink">4.7</bdi><bdi class="text-[#918f83]">(22)</bdi></span></div>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6 text-ink">سلة قش دائرية بنقوش ملونة</h3></a>
              <p class="pb-3 text-start text-xs leading-4 text-[#918f83]">سعف طبيعي مصبوغ بألوان صديقة للبيئة وجدلة محكمة.</p>
              <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7 text-ink">₪85</bdi><span
                  class="rounded-md bg-sage px-2 py-0.5 text-[11px] font-medium leading-[16.5px] text-surface">متبقي <bdi>5</bdi></span></div>
            </div>
          </article>

          <article
            class="product-card group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-category="embroidery" data-vendor="marj-embroidery" data-city="jenin" data-price="140" data-rating="4.65" data-date="105">
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ asset('assets/storefront/imgs/mcp/season-basket.png') }}"
                alt="حقيبة مطرزة من مرج ابن عامر" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.045]"><span
                class="absolute end-3 top-3 rounded-lg bg-copper px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">تطريز يدوي</span><button
                class="favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة الحقيبة إلى المفضلة"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><button
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold text-ink opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="140"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex items-center justify-between gap-2 pb-1 text-[11px] leading-4 text-muted"><span>تطريز مرج ابن عامر • جنين</span><span
                  class="flex items-center gap-1"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11px] w-3"><bdi
                    class="text-xs font-bold text-ink">4.65</bdi><bdi class="text-[#918f83]">(12)</bdi></span></div>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6 text-ink">حقيبة مطرزة من مرج ابن عامر</h3></a>
              <p class="pb-3 text-start text-xs leading-4 text-[#918f83]">حقيبة قماشية بنقشة ريفية وخياطة خفيفة للاستخدام اليومي.</p>
              <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7 text-ink">₪140</bdi><span class="text-[11px] leading-[16.5px] text-muted">قماش كتان</span></div>
            </div>
          </article>

          <article
            class="product-card group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-category="textiles" data-vendor="bethlehem-women" data-city="bethlehem" data-price="210" data-rating="4.85" data-date="104">
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ asset('assets/storefront/imgs/mcp/category-embroidery.png') }}"
                alt="مفرش مطرز من بيت لحم" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.045]"><span
                class="absolute end-3 top-3 rounded-lg bg-olive px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">عمل <bdi>30</bdi> ساعة</span><button
                class="favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة المفرش إلى المفضلة"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><button
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold text-ink opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="210"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex items-center justify-between gap-2 pb-1 text-[11px] leading-4 text-muted"><span>جمعية نساء بيت لحم • بيت لحم</span><span
                  class="flex items-center gap-1"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11px] w-3"><bdi
                    class="text-xs font-bold text-ink">4.85</bdi><bdi class="text-[#918f83]">(24)</bdi></span></div>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6 text-ink">مفرش مطرز من بيت لحم</h3></a>
              <p class="pb-3 text-start text-xs leading-4 text-[#918f83]">مفرش قطن طبيعي بتطريز هادئ وحواف منتهية يدوياً.</p>
              <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7 text-ink">₪210</bdi><span class="text-[11px] leading-[16.5px] text-muted">قطعة بيتية</span></div>
            </div>
          </article>

          <article
            class="product-card group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-category="candles-soaps" data-vendor="noor-alzaytouna" data-city="jenin" data-price="60" data-rating="4.55" data-date="103">
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ asset('assets/storefront/imgs/mcp/category-candle.png') }}"
                alt="شموع زيت زيتون صغيرة" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.045]"><span
                class="absolute end-3 top-3 rounded-lg bg-gold px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">مجموعة</span><button
                class="favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة مجموعة الشموع إلى المفضلة"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><button
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold text-ink opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="60"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex items-center justify-between gap-2 pb-1 text-[11px] leading-4 text-muted"><span>مشغل نور الزيتونة • جنين</span><span
                  class="flex items-center gap-1"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11px] w-3"><bdi
                    class="text-xs font-bold text-ink">4.55</bdi><bdi class="text-[#918f83]">(18)</bdi></span></div>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6 text-ink">شموع زيت زيتون صغيرة</h3></a>
              <p class="pb-3 text-start text-xs leading-4 text-[#918f83]">ثلاث شمعات بروائح هادئة للحجرات الصغيرة والهدايا.</p>
              <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7 text-ink">₪60</bdi><span class="text-[11px] leading-[16.5px] text-muted">طقم من <bdi>3</bdi></span></div>
            </div>
          </article>

          <article
            class="product-card group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-category="tableware" data-vendor="dar-al-karma" data-city="hebron" data-price="135" data-rating="4.75" data-date="102">
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ asset('assets/storefront/imgs/mcp/category-ceramics.png') }}"
                alt="طبق تقديم خزفي أزرق" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.045]"><span
                class="absolute end-3 top-3 rounded-lg bg-copper px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">للمائدة</span><button
                class="favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة طبق التقديم إلى المفضلة"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><button
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold text-ink opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="135"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex items-center justify-between gap-2 pb-1 text-[11px] leading-4 text-muted"><span>دار الكرمة للخزف • الخليل</span><span
                  class="flex items-center gap-1"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11px] w-3"><bdi
                    class="text-xs font-bold text-ink">4.75</bdi><bdi class="text-[#918f83]">(37)</bdi></span></div>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6 text-ink">طبق تقديم خزفي أزرق</h3></a>
              <p class="pb-3 text-start text-xs leading-4 text-[#918f83]">طبق تقديم متوسط بطلاء أزرق وتفاصيل نباتية مرسومة.</p>
              <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7 text-ink">₪135</bdi><span class="text-[11px] leading-[16.5px] text-muted">مناسب للهدايا</span></div>
            </div>
          </article>

          <article
            class="product-card group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-category="home-decor" data-vendor="rawaq-ramallah" data-city="ramallah" data-price="75" data-rating="4.45" data-date="101">
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ asset('assets/storefront/imgs/mcp/popular-basket.png') }}"
                alt="سلة جدارية صغيرة" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.045]"><span
                class="absolute end-3 top-3 rounded-lg bg-olive px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">خفيفة</span><button
                class="favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة السلة الجدارية إلى المفضلة"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><button
                class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold text-ink opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="75"><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex items-center justify-between gap-2 pb-1 text-[11px] leading-4 text-muted"><span>منسوجات رواق رام الله • رام الله</span><span
                  class="flex items-center gap-1"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11px] w-3"><bdi
                    class="text-xs font-bold text-ink">4.45</bdi><bdi class="text-[#918f83]">(11)</bdi></span></div>
              <a role="link" aria-disabled="true" data-deferred-navigation="product.html"><h3 class="pb-2 text-start text-base font-bold leading-6 text-ink">سلة جدارية صغيرة</h3></a>
              <p class="pb-3 text-start text-xs leading-4 text-[#918f83]">قطعة زينة بسيطة من قش مصبوغ تناسب المداخل والرفوف.</p>
              <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7 text-ink">₪75</bdi><span class="text-[11px] leading-[16.5px] text-muted">متوفر <bdi>8</bdi></span></div>
            </div>
          </article>

        </div>

        <div id="browseEmptyState"
          class="hidden rounded-[20px] border border-line bg-surface p-8 text-center shadow-card sm:p-10">
          <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-copper/10 text-copper">
            <i data-lucide="search-x" class="h-7 w-7"></i>
          </span>
          <h2 class="mt-4 text-[22px] font-semibold leading-8 text-ink">لا توجد منتجات مطابقة</h2>
          <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-muted">جرّب إزالة فلتر واحد أو توسيع نطاق السعر للعثور على قطع قريبة من اختيارك.</p>
          <button id="emptyResetFilters" type="button"
            class="mt-5 inline-flex items-center justify-center gap-2 rounded-[16px] bg-sage px-5 py-3 text-sm font-bold text-ink shadow-sm transition-colors hover:bg-[#a7af82]">
            <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
            <span>إعادة ضبط الفلاتر</span>
          </button>
        </div>

        <div id="browsePagination" class="flex flex-wrap items-center justify-center gap-2 pt-2">
          <button type="button"
            class="pagination-control inline-flex h-10 items-center gap-1.5 rounded-full border border-line bg-surface px-4 text-sm font-bold text-olive disabled:cursor-not-allowed disabled:opacity-40"
            data-page="1" disabled>
            <span>السابق</span>
          </button>
          <button type="button"
            class="pagination-control flex h-10 min-w-10 items-center justify-center rounded-full border border-olive bg-olive px-3 text-sm font-bold text-surface"
            data-page="1" aria-current="page"><bdi>1</bdi></button>
          <button type="button"
            class="pagination-control flex h-10 min-w-10 items-center justify-center rounded-full border border-line bg-surface px-3 text-sm font-bold text-olive"
            data-page="2" aria-current="false"><bdi>2</bdi></button>
          <button type="button"
            class="pagination-control inline-flex h-10 items-center gap-1.5 rounded-full border border-line bg-surface px-4 text-sm font-bold text-olive disabled:cursor-not-allowed disabled:opacity-40"
            data-page="2">
            <span>التالي</span>
          </button>
        </div>
          </div>
        </div>
      </div>
    </section>
  </main>
