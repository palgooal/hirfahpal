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
            <bdi id="resultCount">{{ $products->count() }}</bdi>
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
                  @foreach ($filterCategories as $filter)
<label class="flex items-center gap-2"><input id="category-{{ $filter->id }}" name="category" value="{{ $filter->slug }}" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>{{ $filter->name }}</span></label>
@endforeach

                </div>
              </fieldset>

              <fieldset class="min-w-0">
                <legend class="pb-2 text-start text-sm font-bold text-ink">التاجر</legend>
                <div class="grid gap-2 text-sm text-muted">
                  @foreach ($filterVendors as $filter)
<label class="flex items-center gap-2"><input id="vendor-{{ $filter->id }}" name="vendor" value="{{ $filter->profile->slug }}" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>{{ $filter->profile->store_name }}</span></label>
@endforeach

                </div>
              </fieldset>

              <fieldset class="min-w-0">
                <legend class="pb-2 text-start text-sm font-bold text-ink">المدينة</legend>
                <div class="grid gap-2 text-sm text-muted">
                  @foreach ($filterCities as $filter)
<label class="flex items-center gap-2"><input id="city-{{ $filter->id }}" name="city" value="{{ $filter->slug }}" type="checkbox"
                      class="h-4 w-4 accent-olive"><span>{{ $filter->name }}</span></label>
@endforeach

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
          @foreach ($products as $product)
<article data-category="{{ $product->category?->slug }}" data-vendor="{{ $product->vendor->profile->slug }}" data-city="{{ $product->vendor->profile->city?->slug }}" data-price="{{ $product->price }}" data-rating="{{ $product->reviews_avg_rating ?? 0 }}" data-date="{{ $product->published_at?->timestamp ?? $product->created_at->timestamp }}"
            class="product-card group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
                 >
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ \App\Support\Storefront\HomepageMedia::url($product->primaryImage?->path ?? $product->images->first()?->path) }}"
                alt="{{ $product->name }}" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.045]"><span
                class="absolute end-3 top-3 rounded-lg bg-copper px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">جديد</span><button form="browse-favorite-{{ $product->id }}" type="submit"
                class="favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة إبريق الفخار إلى المفضلة"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><form hidden id="browse-favorite-{{ $product->id }}" method="POST" action="{{ route('customer.favorites.store') }}">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"></form><button form="browse-cart-{{ $product->id }}" type="submit"
                @disabled($product->stock_quantity <= ($product->reserved_quantity ?? 0)) class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold text-ink opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                ><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button><form hidden id="browse-cart-{{ $product->id }}" method="POST" action="{{ route('customer.cart.items.store') }}">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"><input type="hidden" name="quantity" value="1"></form></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex items-center justify-between gap-2 pb-1 text-[11px] leading-4 text-muted"><span>{{ $product->vendor->profile->store_name }} • {{ $product->vendor->profile->location_name }}</span><span
                  class="flex items-center gap-1"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11px] w-3"><bdi
                    class="text-xs font-bold text-ink">{{ number_format($product->reviews_avg_rating ?? 0, 1) }}</bdi><bdi class="text-[#918f83]">({{ $product->reviews_count }})</bdi></span></div>
              <a href="{{ route('product', ['slug' => $product->slug]) }}"><h3 class="pb-2 text-start text-base font-bold leading-6 text-ink">{{ $product->name }}</h3></a>
              <p class="pb-3 text-start text-xs leading-4 text-[#918f83]">{{ $product->short_description }}</p>
              <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7 text-ink">{{ number_format($product->price, 2) }} {{ $currency }}</bdi><span
                  class="rounded-md bg-sage px-2 py-0.5 text-[11px] font-medium leading-[16.5px] text-surface">متوفر <bdi>{{ max(0, $product->stock_quantity - ($product->reserved_quantity ?? 0)) }}</bdi> قطع</span></div>
            </div>
          </article>
@endforeach

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
