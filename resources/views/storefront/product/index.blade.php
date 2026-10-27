<main>
  <section class="border-b border-line/60 bg-canvas px-4 py-3 lg:px-8" data-node-id="223:2698">
    <div class="mx-auto flex max-w-[1216px] flex-wrap items-center justify-between gap-3 text-sm text-muted">
      <nav class="flex w-full min-w-0 items-center gap-2 overflow-hidden whitespace-nowrap text-xs font-medium sm:w-auto sm:text-sm"><a href="{{ route('home') }}" class="shrink-0 hover:text-olive">الرئيسية</a><img
          src="{{ asset('assets/storefront/imgs/product-page/breadcrumb-chevron.svg') }}" alt="" class="h-[7px] w-1"><a href="{{ route('home') }}#shop"
          class="shrink-0 hover:text-olive">{{ $product->category?->name }}</a><img src="{{ asset('assets/storefront/imgs/product-page/breadcrumb-chevron.svg') }}" alt=""
          class="h-[7px] w-1 shrink-0"><span class="min-w-0 truncate font-semibold text-olive">{{ $product->name }}</span></nav><span
        class="flex w-full items-center justify-center gap-1.5 rounded-full border border-copper/20 bg-copper/10 px-[13px] py-[5px] text-center text-[11px] leading-4 text-copper sm:w-auto sm:text-xs"><img
          src="{{ asset('assets/storefront/imgs/product-page/breadcrumb-verified.svg') }}" alt="" class="h-3 w-[13px]">قطعة أصلية موثقة - مشاغل {{ $product->vendor->profile->location_name }}</span>
    </div>
  </section>

  <section class="bg-canvas px-4 pb-16 pt-6 lg:min-h-[1055.5px] lg:px-8" data-node-id="223:2715">
    <div class="mx-auto flex w-full min-w-0 max-w-[1216px] flex-col gap-8 lg:flex-row lg:items-start lg:gap-[21px]">
      <div class="w-full min-w-0 shrink-0 lg:w-[509px]" data-product-gallery>
        <div
          class="group relative aspect-[4/5] overflow-hidden rounded-[24px] border border-line bg-surface shadow-sm">
          <img id="productMainImage" src="{{ \App\Support\Storefront\HomepageMedia::url($product->primaryImage?->path ?? $product->images->first()?->path) }}" alt="{{ $product->name }}" draggable="false"
            class="h-full w-full object-cover transition-[opacity,transform] duration-300 ease-out will-change-[opacity,transform]">
          <div class="absolute right-4 top-4 flex flex-col items-start gap-2">
            @if ($product->is_limited_edition)<span class="rounded-full bg-copper px-3 py-1 text-xs font-bold text-white shadow-sm">إصدار حرفي محدود</span>@endif
            @foreach ($product->highlight_tags ?? [] as $tag)<span class="rounded-full bg-[#2563eb] px-3 py-1 text-xs font-bold text-white shadow-sm">{{ $tag }}</span>@endforeach
            <span class="rounded-full bg-olive px-3 py-1 text-xs font-bold text-surface shadow-sm">{{ $product->vendor->profile->location_name }}</span>
          </div>
          <button form="main-favorite-{{ $product->id }}" type="submit"
            class="favorite absolute left-4 top-4 flex h-11 w-11 cursor-pointer items-center justify-center rounded-full border border-line bg-surface/95 shadow-sm backdrop-blur-sm"
            aria-label="أضف إلى المفضلة"><img src="{{ asset('assets/storefront/imgs/product-page/hero-heart.svg') }}" alt=""
              class="h-[18px] w-5"></button><form hidden id="main-favorite-{{ $product->id }}" method="POST" action="{{ route('customer.favorites.store') }}">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"></form>
          <div
            class="absolute inset-x-3 top-1/2 flex -translate-y-1/2 justify-between opacity-100 transition-opacity lg:inset-x-4 lg:opacity-0 lg:group-hover:opacity-100">
            <button class="gallery-prev flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border border-line bg-surface shadow"
              aria-label="الصورة السابقة"><img src="{{ asset('assets/storefront/imgs/product-page/hero-arrow-right.svg') }}" alt=""
                class="h-3 w-[7px]"></button><button
              class="gallery-next flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border border-line bg-surface shadow"
              aria-label="الصورة التالية"><img src="{{ asset('assets/storefront/imgs/product-page/hero-arrow-left.svg') }}" alt=""
                class="h-3 w-[7px]"></button>
          </div>
          <bdi id="galleryCounter"
            class="absolute bottom-4 left-4 rounded-full bg-black/60 px-3 py-1 text-xs text-white backdrop-blur-sm">1 / {{ $productImages->count() }}</bdi>
        </div>
        <div
          class="gallery-thumbnails mt-4 flex cursor-grab snap-x snap-mandatory gap-2 overflow-x-auto overscroll-x-contain touch-pan-y select-none active:cursor-grabbing [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          @foreach ($productImages as $productImage)
<button
            type="button" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
            class="gallery-thumb relative aspect-square w-[31%] min-w-[31%] shrink-0 snap-center cursor-pointer overflow-hidden rounded-[16px] {{ $loop->first ? 'border-2 border-olive p-0.5 opacity-100 shadow-[0_0_0_2px_rgba(74,93,58,.2)]' : 'border border-line opacity-70' }} transition-[opacity,border-color,box-shadow] duration-300 after:absolute after:inset-0 after:z-10 after:content-[''] lg:w-[159.5px] lg:min-w-[159.5px]"
            data-image="{{ \App\Support\Storefront\HomepageMedia::url($productImage->path) }}"><img
              src="{{ \App\Support\Storefront\HomepageMedia::url($productImage->path) }}" alt="{{ $productImage->alt_text ?: $product->name }}" draggable="false"
              class="pointer-events-none h-full w-full select-none object-cover"></button>
@endforeach

        </div>
      </div>

      <div class="w-full min-w-0 flex-1 lg:max-w-[686px]" data-node-id="223:2748">
        <div class="flex flex-col items-start gap-3"><span
            class="flex max-w-full items-center gap-1.5 rounded-lg border border-surface bg-[#ffd0ba] px-[13px] py-[5px] text-[11px] font-bold leading-4 text-olive sm:text-xs"><img
              src="{{ asset('assets/storefront/imgs/product-page/hero-store.svg') }}" alt="" class="h-[11px] w-3">{{ $product->vendor->profile->store_name }}</span>
          <h1 class="text-[26px] font-bold leading-tight sm:text-4xl">{{ $product->name }}</h1>
        </div>
        <div class="mt-5 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm"><span class="flex gap-1">
            <img src="{{ asset('assets/storefront/imgs/product-page/hero-star.svg') }}" alt="" class="h-[14px] w-[15px]"><img
              src="{{ asset('assets/storefront/imgs/product-page/hero-star.svg') }}" alt="" class="h-[14px] w-[15px]"><img
              src="{{ asset('assets/storefront/imgs/product-page/hero-star.svg') }}" alt="" class="h-[14px] w-[15px]"><img
              src="{{ asset('assets/storefront/imgs/product-page/hero-star.svg') }}" alt="" class="h-[14px] w-[15px]"><img
              src="{{ asset('assets/storefront/imgs/product-page/hero-star.svg') }}" alt="" class="h-[14px] w-[15px]"></span><strong>{{ number_format($product->reviews_avg_rating ?? 0, 1) }}</strong><span
            class="text-muted">({{ $product->reviews_count }} تقييماً موثقاً من مشترين فعليين)</span><span class="flex items-center gap-1 text-olive"><img
              src="{{ asset('assets/storefront/imgs/product-page/hero-authentic.svg') }}" alt="" class="h-[15px] w-[15px]">100% صناعة يدوية أصيلة</span></div>
        <div class="mt-6 rounded-[24px] border border-line bg-surface p-5 shadow-sm">@if ($product->hasDiscount())<span class="mb-1 block text-sm text-muted line-through"><bdi>{{ number_format($product->price, 2) }} {{ $currency }}</bdi></span>@endif<strong
            class="block text-4xl leading-10 text-olive"><bdi>{{ number_format($product->sellingPrice(), 2) }} {{ $currency }}</bdi></strong><span
            class="text-xs text-muted">شامل الضريبة والشحن من المشغل</span>
          <div class="mt-3 flex flex-wrap gap-2"><span class="rounded-full bg-olive px-3 py-1 text-xs text-white"><i
                class="ms-1 inline-block h-2 w-2 rounded-full bg-sage"></i>{{ $product->availableStockQuantity() > 0 ? 'متوفر وجاهز للطلب' : 'غير متوفر حالياً' }}</span><span
              class="rounded-full bg-copper/10 px-3 py-1 text-xs font-semibold text-copper">{{ $product->availableStockQuantity() }} قطعة متاحة</span></div>
        </div>
        <p class="mt-6 text-sm leading-7 text-ink sm:text-[15px]">{{ $product->short_description ?: $product->description }}</p>
        @if (filled($product->craft_tags))<div class="mt-4 flex flex-wrap gap-2">@foreach ($product->craft_tags as $tag)<span class="rounded-full border border-copper/30 bg-copper/10 px-3 py-1 text-xs font-semibold text-copper">{{ $tag }}</span>@endforeach</div>@endif
        <div class="mt-6 grid grid-cols-[auto_1fr] items-center gap-3 text-xs sm:flex sm:flex-wrap sm:gap-4"><strong>الكمية المطلوبة:</strong>
          <div class="flex h-[42px] items-center overflow-hidden rounded-xl border border-line bg-surface shadow-sm" data-max-quantity="{{ $product->availableStockQuantity() }}">
            <button type="button" id="quantityIncrease" @disabled($selectedQuantity >= $product->availableStockQuantity()) class="flex h-10 w-10 cursor-pointer items-center justify-center transition-colors hover:bg-canvas active:bg-sage/40"
              aria-label="زيادة الكمية"><i data-lucide="plus" class="h-4 w-4"></i></button><span id="productQuantity" aria-live="polite"
              class="flex h-10 w-9 items-center justify-center font-bold">{{ $selectedQuantity }}</span><button type="button" id="quantityDecrease" @disabled($selectedQuantity <= 1)
              class="flex h-10 w-10 cursor-pointer items-center justify-center transition-colors hover:bg-canvas active:bg-sage/40" aria-label="تقليل الكمية"><i data-lucide="minus"
                class="h-4 w-4"></i></button>
          </div><span class="col-span-2 text-muted sm:col-auto">الحد الأقصى للطلب: {{ $product->availableStockQuantity() }} قطع</span>
        </div>
        <div class="mt-4 flex gap-3"><button form="main-cart-{{ $product->id }}" type="submit"
            class="add-cart flex h-12 flex-1 cursor-pointer items-center justify-center gap-2 rounded-2xl bg-sage font-bold shadow-md transition-[background-color,transform,box-shadow] hover:bg-[#a7af82] hover:shadow-lg active:scale-[0.99]"
            data-price="{{ $product->price }}" @disabled($product->availableStockQuantity() < 1)><i data-lucide="shopping-bag" class="h-4 w-4"></i>أضف إلى السلة</button><form hidden id="main-cart-{{ $product->id }}" method="POST" action="{{ route('customer.cart.items.store') }}">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"><input class="product-cart-quantity" type="hidden" name="quantity" value="{{ $selectedQuantity }}"></form><button form="buy-now-{{ $product->id }}" @disabled($product->availableStockQuantity() < 1) type="submit"
            class="h-12 shrink-0 cursor-pointer rounded-2xl border border-olive bg-surface px-4 font-bold text-olive transition-[background-color,color,transform] hover:bg-olive hover:text-white active:scale-[0.98] sm:px-7">اشتري الآن</button><form hidden id="buy-now-{{ $product->id }}" method="POST" action="{{ route('customer.cart.items.store') }}">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"><input class="product-cart-quantity" type="hidden" name="quantity" value="{{ $selectedQuantity }}"><input type="hidden" name="checkout" value="1"></form>
        </div>
        <div class="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-4">
          <div class="flex items-center gap-2 rounded-2xl border border-line bg-surface p-3"><span
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-olive text-sage"><i
                data-lucide="wallet-cards" class="h-4 w-4"></i></span>
            <p class="text-xs"><strong class="block">دفع مرن وآمن</strong><span
                class="text-[10px] text-muted">إلكتروني أو عند الاستلام</span></p>
          </div>
          <div class="flex items-center gap-2 rounded-2xl border border-line bg-surface p-3"><span
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-olive text-sage"><i
                data-lucide="truck" class="h-4 w-4"></i></span>
            <p class="text-xs"><strong class="block">شحن مباشر</strong><span class="text-[10px] text-muted">{{ $product->vendor->profile->location_name }}</span></p>
          </div>
          <div class="flex items-center gap-2 rounded-2xl border border-line bg-surface p-3"><span
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-olive text-sage"><i
                data-lucide="badge-check" class="h-4 w-4"></i></span>
            <p class="text-xs"><strong class="block">قطعة أصلية 100%</strong><span class="text-[10px] text-muted">برقم
                تسلسلي من المشغل</span></p>
          </div>
          <div class="flex items-center gap-2 rounded-2xl border border-line bg-surface p-3"><span
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-olive text-sage"><i
                data-lucide="shield" class="h-4 w-4"></i></span>
            <p class="text-xs"><strong class="block">تغليف آمن ضد الكسر</strong><span
                class="text-[10px] text-muted">للشحن الداخلي والخارجي</span></p>
          </div>
        </div>
        <article class="mt-6 rounded-[24px] border border-line bg-surface p-5 shadow-sm">
          <div class="flex items-center gap-4"><span
              class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-olive text-xl font-bold text-surface">{{ $product->vendor->profile->store_initials }}</span>
            <div>
              <h2 class="font-bold"><i data-lucide="badge-check" class="ms-1 inline h-4 w-4 text-olive"></i>{{ $product->vendor->profile->store_name }}</h2>
              <p class="text-xs text-copper">{{ $product->vendor->profile->location_name }}</p>
              <p class="mt-1 text-xs text-muted">{{ $product->vendor->profile->short_description }}</p>
            </div>
          </div>
          <div class="mt-4 grid grid-cols-3 gap-2 border-t border-line/60 pt-3 text-center text-xs">
            <div><span class="block text-muted">التقييم</span><strong>{{ $product->vendor->published_reviews_count ? number_format($product->vendor->published_rating, 1) : '—' }} من 5</strong></div>
            <div><span class="block text-muted">الأعمال</span><strong>{{ $product->vendor->available_products_count }} منتجاً</strong></div>
            <div><span class="block text-muted">التجهيز</span><strong>غير محدد</strong></div>
          </div><a href="{{ route('vendors.profile', ['storeSlug' => $product->vendor->profile->slug]) }}"
            class="mt-4 flex h-10 cursor-pointer items-center justify-center rounded-2xl border border-line text-xs font-bold text-olive transition-colors hover:border-olive hover:bg-canvas">تصفح
            كافة أعمال {{ $product->vendor->profile->store_name }} <i data-lucide="chevron-left" class="me-1 h-3 w-3"></i></a>
        </article>
      </div>
    </div>
  </section>

  <section class="relative overflow-hidden bg-olive px-4 py-16 text-white lg:px-8 lg:pb-16 lg:pt-[104px]"
    data-node-id="223:2894">
    <div class="mx-auto grid max-w-[1216px] gap-10 lg:grid-cols-2 lg:items-center">
      <div class="relative"><img src="{{ \App\Support\Storefront\HomepageMedia::url($product->vendor->profile->cover_image) }}"
          alt="{{ $product->vendor->profile->store_name }}"
          class="h-[340px] w-full rounded-[24px] border border-white/10 object-cover shadow-2xl sm:h-[402px]"><span
          class="absolute right-4 top-4 rounded-full border border-white/20 bg-black/60 px-4 py-2 text-xs text-[#fde68a] backdrop-blur-md"><i
            data-lucide="map-pin" class="ms-1 inline h-3 w-3"></i>حارة الفواخير، {{ $product->vendor->profile->location_name }}</span>
        <div
          class="absolute -bottom-5 right-3 flex max-w-[320px] items-center gap-3 rounded-2xl border border-line bg-surface p-4 text-ink shadow-xl sm:-right-5">
          <span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-copper/10 text-lg font-bold text-copper">—</span>
          <p class="text-xs"><strong class="block">{{ $product->vendor->profile->store_name }}</strong><span class="text-[11px] text-muted">في
              {{ $product->category?->name }}</span></p>
        </div>
      </div>
      <div class="lg:pr-6">
        <p class="text-xs font-medium text-[#fcd34d]">حكاية صانع وميراث وطن <i data-lucide="badge-help"
            class="inline h-3 w-3"></i></p>
        <blockquote class="mt-5 text-[24px] font-bold leading-9 sm:mt-6 sm:text-[30px]">{{ $product->vendor->profile->short_description ?: $product->vendor->profile->store_name }}</blockquote>
        <p class="mt-5 text-[13px] font-light leading-6 text-surface sm:mt-6 sm:text-sm">{{ $product->vendor->profile->description }}</p>
        <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3">
          <div class="rounded-2xl border border-white/15 bg-white/10 p-4"><span
              class="text-[11px] text-[#fde68a]/80">نوع الحرفة</span><strong class="mt-1 block text-sm">{{ $product->category?->name }}</strong></div>
          <div class="rounded-2xl border border-white/15 bg-white/10 p-4"><span
              class="text-[11px] text-[#fde68a]/80">المادة الأساسية</span><strong class="mt-1 block text-sm">غير محدد</strong></div>
          <div class="rounded-2xl border border-white/15 bg-white/10 p-4"><span
              class="text-[11px] text-[#fde68a]/80">مكان الصنع</span><strong class="mt-1 block text-sm">{{ $product->vendor->profile->location_name }}</strong></div>
          <div class="rounded-2xl border border-white/15 bg-white/10 p-4"><span
              class="text-[11px] text-[#fde68a]/80">زمن الإنجاز</span><strong class="mt-1 block text-sm">{{ $product->vendor->profile->preparation_days !== null ? $product->vendor->profile->preparation_days.' يوم' : 'غير محدد' }}</strong></div>
          <div class="col-span-2 rounded-2xl border border-white/15 bg-white/10 p-4"><span
              class="text-[11px] text-[#fde68a]/80">طريقة العناية الموصى بها</span><strong
              class="mt-1 block text-sm">يرجى الرجوع لتعليمات المتجر</strong></div>
        </div>
      </div>
    </div>
  </section>

  <section class="bg-canvas px-4 py-12 lg:px-8" data-node-id="223:2947">
    <div class="mx-auto max-w-[768px]">
      <div class="pb-4 text-center">
        <p class="text-xs font-bold leading-4 tracking-[0.6px] text-copper">تفاصيل الصنعة</p>
        <h2 class="mt-2 text-[26px] font-bold leading-9 text-ink sm:text-[30px]">المواصفات والتفاصيل الدقيقة</h2>
        <p class="mt-1 text-sm leading-5 text-muted">كل ما يلزمك معرفته عن {{ $product->name }}</p>
      </div>
      <div class="space-y-4" id="productAccordion">
        <article class="accordion-item overflow-hidden rounded-[24px] border border-line bg-surface shadow-sm"><button
            type="button" aria-expanded="true" aria-controls="spec-description"
            class="accordion-toggle flex w-full cursor-pointer items-center justify-between gap-3 px-4 py-4 text-right transition-colors duration-200 hover:bg-canvas/40 focus-visible:outline-2 focus-visible:outline-offset-[-3px] focus-visible:outline-olive sm:px-6"><span
              class="flex items-center gap-3 text-sm font-bold leading-6 sm:text-base"><img src="{{ asset('assets/storefront/imgs/product-page/spec-description.svg') }}"
                alt="" class="h-[17px] w-[13px] shrink-0">الوصف والاستخدامات</span><img
              src="{{ asset('assets/storefront/imgs/product-page/chevron-down.svg') }}" alt=""
              class="accordion-chevron h-[7px] w-3 shrink-0 rotate-180 transform-gpu transition-transform duration-300 ease-in-out motion-reduce:transition-none"></button>
          <div id="spec-description" aria-hidden="false"
            class="accordion-content grid grid-rows-[1fr] opacity-100 transition-[grid-template-rows,opacity] duration-300 ease-out">
            <div class="min-h-0 overflow-hidden">
              <p class="px-4 pb-5 text-sm leading-6 text-muted sm:px-6">{{ $product->description }}</p>
            </div>
          </div>
        </article>
        <article class="accordion-item overflow-hidden rounded-[24px] border border-line bg-surface shadow-sm"><button
            type="button" aria-expanded="false" aria-controls="spec-measurements"
            class="accordion-toggle flex w-full cursor-pointer items-center justify-between gap-3 px-4 py-4 text-right transition-colors duration-200 hover:bg-canvas/40 focus-visible:outline-2 focus-visible:outline-offset-[-3px] focus-visible:outline-olive sm:px-6"><span
              class="flex items-center gap-3 text-sm font-bold leading-6 sm:text-base"><img src="{{ asset('assets/storefront/imgs/product-page/spec-ruler.svg') }}" alt=""
                class="h-[10px] w-[17px] shrink-0">المواد والأبعاد والمقاييس</span><img src="{{ asset('assets/storefront/imgs/product-page/chevron-down.svg') }}"
              alt="" class="accordion-chevron h-[7px] w-3 shrink-0 transform-gpu transition-transform duration-300 ease-in-out motion-reduce:transition-none"></button>
          <div id="spec-measurements" aria-hidden="true"
            class="accordion-content grid grid-rows-[0fr] opacity-0 transition-[grid-template-rows,opacity] duration-300 ease-out">
            <div class="min-h-0 overflow-hidden">
              <p class="px-4 pb-5 text-sm leading-6 text-muted sm:px-6">الوزن: {{ $product->weight ?? 'غير محدد' }} — الأبعاد: {{ $product->dimensions ? json_encode($product->dimensions, JSON_UNESCAPED_UNICODE) : 'غير محدد' }}</p>
            </div>
          </div>
        </article>
        <article class="accordion-item overflow-hidden rounded-[24px] border border-line bg-surface shadow-sm"><button
            type="button" aria-expanded="false" aria-controls="spec-care"
            class="accordion-toggle flex w-full cursor-pointer items-center justify-between gap-3 px-4 py-4 text-right transition-colors duration-200 hover:bg-canvas/40 focus-visible:outline-2 focus-visible:outline-offset-[-3px] focus-visible:outline-olive sm:px-6"><span
              class="flex items-center gap-3 text-sm font-bold leading-6 sm:text-base"><img src="{{ asset('assets/storefront/imgs/product-page/spec-care.svg') }}" alt=""
                class="h-[18px] w-[15px] shrink-0">تعليمات العناية والحفظ</span><img src="{{ asset('assets/storefront/imgs/product-page/chevron-down.svg') }}"
              alt="" class="accordion-chevron h-[7px] w-3 shrink-0 transform-gpu transition-transform duration-300 ease-in-out motion-reduce:transition-none"></button>
          <div id="spec-care" aria-hidden="true"
            class="accordion-content grid grid-rows-[0fr] opacity-0 transition-[grid-template-rows,opacity] duration-300 ease-out">
            <div class="min-h-0 overflow-hidden">
              <p class="px-4 pb-5 text-sm leading-6 text-muted sm:px-6">يرجى الرجوع إلى المتجر لمعرفة تعليمات العناية الخاصة بهذا المنتج.</p>
            </div>
          </div>
        </article>
        <article class="accordion-item overflow-hidden rounded-[24px] border border-line bg-surface shadow-sm"><button
            type="button" aria-expanded="false" aria-controls="spec-shipping"
            class="accordion-toggle flex w-full cursor-pointer items-center justify-between gap-3 px-4 py-4 text-right transition-colors duration-200 hover:bg-canvas/40 focus-visible:outline-2 focus-visible:outline-offset-[-3px] focus-visible:outline-olive sm:px-6"><span
              class="flex items-center gap-3 text-sm font-bold leading-6 sm:text-base"><img src="{{ asset('assets/storefront/imgs/product-page/spec-package.svg') }}" alt=""
                class="h-[17px] w-[15px] shrink-0">تفاصيل التغليف الحرفي الآمن ومسار الشحن</span><img
              src="{{ asset('assets/storefront/imgs/product-page/chevron-down.svg') }}" alt=""
              class="accordion-chevron h-[7px] w-3 shrink-0 transform-gpu transition-transform duration-300 ease-in-out motion-reduce:transition-none"></button>
          <div id="spec-shipping" aria-hidden="true"
            class="accordion-content grid grid-rows-[0fr] opacity-0 transition-[grid-template-rows,opacity] duration-300 ease-out">
            <div class="min-h-0 overflow-hidden">
              <p class="px-4 pb-5 text-sm leading-6 text-muted sm:px-6">{{ $product->vendor->profile->short_description }}</p>
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>

  <section class="bg-canvas px-4 pb-16 lg:px-8" data-node-id="223:2992">
    <div
      class="mx-auto max-w-[1216px] rounded-[24px] border border-line bg-surface px-4 pb-8 pt-8 shadow-sm sm:px-5 sm:pb-10 sm:pt-10 lg:px-[41px] lg:pb-[41px] lg:pt-[81px]">
      <div class="grid gap-8 border-b border-line/60 pb-8 lg:grid-cols-12 lg:items-center lg:pb-[33px]">
        <div class="lg:col-span-4"><strong class="block text-5xl font-bold leading-[48px] text-olive">{{ number_format($product->reviews_avg_rating ?? 0, 1) }}</strong>
          <div class="mt-2 flex gap-1" aria-label="5 من 5 نجوم">
            <img src="{{ asset('assets/storefront/imgs/product-page/review-star-large.svg') }}" alt="" class="h-4 w-[17px]">
            <img src="{{ asset('assets/storefront/imgs/product-page/review-star-large.svg') }}" alt="" class="h-4 w-[17px]">
            <img src="{{ asset('assets/storefront/imgs/product-page/review-star-large.svg') }}" alt="" class="h-4 w-[17px]">
            <img src="{{ asset('assets/storefront/imgs/product-page/review-star-large.svg') }}" alt="" class="h-4 w-[17px]">
            <img src="{{ asset('assets/storefront/imgs/product-page/review-star-large.svg') }}" alt="" class="h-4 w-[17px]">
          </div>
          <p class="mt-2 text-xs font-semibold leading-4 text-muted">مبني على {{ $product->reviews_count }} تقييماً موثقاً من مشترين فعليين</p>
        </div>
        <div class="space-y-2 text-xs font-medium lg:col-span-5">
          @foreach (range(5, 1) as $rating)<div class="flex items-center gap-3"><span class="w-12">{{ $rating }} نجوم</span><span
              class="h-2 flex-1 overflow-hidden rounded-full bg-canvas"><i
                class="block h-full rounded-full bg-copper" style="width: {{ $reviewDistribution[$rating] }}%"></i></span><span class="w-8">{{ $reviewDistribution[$rating] }}%</span></div>@endforeach

        </div>
        <div class="rounded-2xl border border-line/70 p-[17px] lg:col-span-3"><strong
            class="flex items-center gap-1.5 text-xs leading-5 text-olive"><img
              src="{{ asset('assets/storefront/imgs/product-page/review-shield.svg') }}" alt="" class="h-[13px] w-[11px]">شفافية حرفة</strong>
          <p class="mt-2 text-xs leading-5 text-muted">تقتصر التقييمات حصرياً على العملاء الذين استلموا طلبياتهم
            المؤكدة لضمان مصداقية التجربة ودعم الحرفي النزيه.</p>
        </div>
      </div>
      <div class="mt-7 space-y-5 lg:mt-0 lg:pt-7">
        @forelse ($productReviews as $review)
<article class="rounded-2xl border border-line/60 p-4 sm:p-[21px]">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-center gap-3"><span
                class="flex h-10 w-10 items-center justify-center rounded-full bg-olive font-bold text-surface">{{ mb_substr($review->customer?->name ?? 'عميل', 0, 1) }}</span>
              <div>
                <h3 class="text-sm font-bold">{{ $review->customer?->name ?? 'عميل' }}</h3><time
                  class="text-[11px] text-muted">{{ $review->created_at->format('Y/m/d') }}</time>
              </div>
            </div><div class="flex self-start" aria-label="{{ $review->rating }} من 5 نجوم">@for ($star = 0; $star < $review->rating; $star++)<img src="{{ asset('assets/storefront/imgs/product-page/review-star-small.svg') }}" alt="" class="h-[11px] w-3">@endfor</div>
          </div>
          <p class="mt-4 text-sm leading-6 text-muted">{{ $review->comment }}</p>
        </article>
@empty
<p class="text-sm text-muted">لا توجد تقييمات منشورة لهذا المنتج بعد.</p>
@endforelse

      </div>
    </div>
  </section>

  <section class="bg-canvas px-4 py-12 lg:px-8" data-node-id="223:3136">
    <div class="mx-auto max-w-[1216px]">
      <div class="flex items-center justify-between border-b border-line/60 pb-[13px]">
        <div>
          <p class="text-xs font-bold leading-4 tracking-[0.6px] text-copper">مختارات أصيلة</p>
          <h2 class="mt-1 text-[28px] font-bold leading-9 text-ink sm:text-[30px]">قد يعجبك أيضاً</h2>
          <p class="text-sm leading-5 text-muted">مختارات منتقاة بعناية من مشاغل الحرف اليدوية في فلسطين</p>
        </div><a href="{{ route('home') }}#season"
          class="hidden cursor-pointer items-center gap-1 text-sm font-semibold text-olive transition-colors hover:text-copper sm:flex">تصفح المزيد<img
            src="{{ asset('assets/storefront/imgs/product-page/related-arrow.svg') }}" alt="" class="h-[7px] w-1"></a>
      </div>
      <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($relatedProducts as $related)
<article class="product-card group flex overflow-hidden rounded-[24px] border border-line bg-surface">
          <div class="flex w-full flex-col">
            <div class="relative h-[240px] overflow-hidden sm:h-[284px]"><img src="{{ \App\Support\Storefront\HomepageMedia::url($related->primaryImage?->path ?? $related->images->first()?->path) }}"
                alt="{{ $related->name }}" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.04]">
              <div class="absolute right-3 top-3 flex flex-col items-start gap-1.5"><span
                  class="rounded-lg bg-gold px-2.5 py-0.5 text-[11px] font-bold leading-[17px] text-white shadow-sm">قطعة نادرة</span><span
                  class="rounded-md border border-line bg-surface/95 px-2.5 py-0.5 text-[10px] font-bold leading-[15px] text-olive backdrop-blur-sm">{{ $related->vendor->profile->store_name }}</span></div>
              <button form="related-favorite-{{ $related->id }}" type="submit" aria-label="أضف سلة القش إلى المفضلة"
                class="favorite absolute left-3 top-3 flex h-8 w-8 cursor-pointer items-center justify-center rounded-full bg-surface shadow-sm"><img
                  src="{{ asset('assets/storefront/imgs/product-page/related-heart.svg') }}" alt="" class="h-[14px] w-[15px]"></button><form hidden id="related-favorite-{{ $related->id }}" method="POST" action="{{ route('customer.favorites.store') }}">@csrf<input type="hidden" name="product_id" value="{{ $related->id }}"></form>
              <button form="related-cart-{{ $related->id }}" type="submit" data-price="{{ $related->price }}"
                class="add-cart absolute inset-x-3 bottom-3 flex translate-y-0 cursor-pointer items-center justify-center gap-2 rounded-2xl bg-sage px-4 py-2.5 text-xs font-bold text-ink opacity-100 shadow-md transition-[opacity,transform] duration-300 sm:translate-y-2 sm:opacity-0 sm:group-hover:translate-y-0 sm:group-hover:opacity-100 sm:group-focus-within:translate-y-0 sm:group-focus-within:opacity-100"><span>إضافة إلى السلة</span><img
                  src="{{ asset('assets/storefront/imgs/product-page/related-cart.svg') }}" alt="" class="h-[14px] w-[13px]"></button><form hidden id="related-cart-{{ $related->id }}" method="POST" action="{{ route('customer.cart.items.store') }}">@csrf<input type="hidden" name="product_id" value="{{ $related->id }}"><input type="hidden" name="quantity" value="1"></form>
            </div>
            <div class="flex flex-1 flex-col p-4">
              <div class="flex items-center justify-between pb-1 text-[11px] leading-4 text-muted"><span>{{ $related->vendor->profile->store_name }}</span><span
                  class="flex items-center gap-1"><img src="{{ asset('assets/storefront/imgs/product-page/related-star.svg') }}" alt="" class="h-[11px] w-3"><bdi
                    class="text-xs font-bold text-ink">{{ number_format($related->reviews_avg_rating ?? 0, 1) }}</bdi><bdi class="text-[#918f83]">({{ $related->reviews_count }})</bdi></span></div>
              <a href="{{ route('product', ['slug' => $related->slug]) }}"><h3 class="pb-2 text-base font-bold leading-6 text-ink">{{ $related->name }}</h3></a>
              <p class="pb-3 text-xs leading-4 text-[#918f83]">{{ $related->short_description }}</p>
              <div class="mt-auto flex items-center justify-between border-t border-line/60 pt-[13px]"><span
                  class="flex items-baseline gap-1"><span class="text-xs text-[#918f83]">السعر:</span><bdi class="text-lg font-bold leading-7 text-ink">{{ number_format($related->price, 2) }} {{ $currency }}</bdi></span><span
                  class="text-[11px] text-muted">قطعة وحيدة</span></div>
            </div>
          </div>
        </article>
@endforeach

      </div>
    </div>
  </section>
</main>
