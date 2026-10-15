<main data-vendor-storefront>
  <section class="border-b border-line/60 bg-canvas px-4 py-5 lg:px-8">
    <div class="mx-auto flex max-w-[1216px] flex-col gap-6">
      <nav class="flex flex-wrap items-center gap-2 text-xs font-medium leading-4 text-muted sm:text-sm"
        aria-label="مسار الصفحة">
        <a href="{{ route('home') }}" class="hover:text-olive">الرئيسية</a>
        <span class="text-line">/</span>
        <a href="{{ route('vendors') }}" class="hover:text-olive">كل المتاجر</a>
        <span class="text-line">/</span>
        <span class="font-semibold text-olive">{{ $vendor->profile->store_name }}</span>
      </nav>

      <section
        class="relative overflow-hidden rounded-[24px] border border-line bg-surface p-5 shadow-card sm:p-6 lg:p-8">
        <div class="absolute start-0 top-0 h-full w-2 bg-copper/70" aria-hidden="true"></div>
        <div class="grid gap-6 lg:grid-cols-[auto_1fr_auto] lg:items-center">
          <div class="flex items-start justify-start gap-4 sm:gap-5">
            <span
              class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full border-2 border-copper/20 bg-copper/10 text-2xl font-bold leading-8 text-copper shadow-sm sm:h-24 sm:w-24 sm:text-[30px]">{{ $vendor->profile->store_initials }}</span>
            <div class="min-w-0 flex-1 text-start">
              <p class="flex items-center gap-1.5 text-xs font-bold uppercase leading-4 tracking-[.6px] text-copper">
                <span class="h-1.5 w-1.5 rounded-full bg-copper"></span>{{ $vendor->profile->short_description ?: $vendor->profile->store_name }}
              </p>
              <h1 class="mt-2 text-[30px] font-bold leading-10 text-ink sm:text-4xl sm:leading-[48px]">{{ $vendor->profile->store_name }}</h1>
              <div class="mt-3 flex flex-wrap items-center gap-3 text-sm font-semibold text-ink">
                <span class="flex items-center gap-1.5"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt=""
                    class="h-[11.083px] w-[11.667px]"><bdi>{{ $vendor->published_reviews_count ? number_format($vendor->published_rating, 1) : '—' }}</bdi><span class="text-muted">تقييم المتجر</span></span>
                <span class="h-4 w-px bg-line" aria-hidden="true"></span>
                <span class="flex items-center gap-1.5 text-copper"><img src="{{ asset('assets/storefront/imgs/mcp/artisan-location.svg') }}"
                    alt="" class="h-[9.752px] w-[7.774px]"><span>{{ $vendor->profile->location_name }}</span></span>
              </div>
            </div>
          </div>
          <p class="text-start text-sm leading-7 text-muted lg:max-w-[540px]">{{ $vendor->profile->description ?: $vendor->profile->short_description }}</p>
          <div class="grid grid-cols-3 gap-2 rounded-[20px] border border-line bg-canvas p-3 text-center lg:w-[260px]">
            <div>
              <bdi class="block text-lg font-bold leading-7 text-olive">{{ $vendor->available_products_count }}</bdi>
              <span class="text-[11px] leading-4 text-muted">منتج معتمد</span>
            </div>
            <div class="border-s border-e border-line/70">
              <bdi class="block text-lg font-bold leading-7 text-olive">—</bdi>
              <span class="text-[11px] leading-4 text-muted">أيام تجهيز</span>
            </div>
            <div>
              <bdi class="block text-lg font-bold leading-7 text-olive">{{ $vendor->published_reviews_count }}</bdi>
              <span class="text-[11px] leading-4 text-muted">تقييم موثق</span>
            </div>
          </div>
        </div>
      </section>
    </div>
  </section>

  <section class="bg-canvas px-4 py-10 sm:py-14 lg:px-8">
    <div
      class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 mx-auto flex max-w-[1216px] flex-col gap-7 sm:gap-8">
      <div class="flex flex-col gap-5 rounded-[22px] border border-line bg-surface p-4 shadow-card sm:p-5">
        <div class="flex flex-col items-start gap-1.5 text-start">
          <p class="text-xs font-bold uppercase leading-4 tracking-[.6px] text-copper">تصنيفات {{ $vendor->profile->store_name }} فقط</p>
          <h2 class="text-[26px] font-bold leading-9 text-ink sm:text-[30px]">منتجات المتجر</h2>
          <p class="text-sm leading-6 text-muted">اختر تصنيفاً من داخل هذا المتجر لفرز منتجات {{ $vendor->profile->store_name }} في نفس
            الصفحة.</p>
        </div>
        <div
          class="hide-scrollbar flex max-w-full flex-nowrap gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          <button type="button" aria-pressed="true"
            class="vendor-category-chip shrink-0 rounded-full border border-sage bg-sage px-4 py-2 text-xs font-bold leading-4 text-ink shadow-sm"
            data-filter="all">الكل</button>
          @foreach ($vendorCategories as $category)
          <button type="button" aria-pressed="false"
            class="vendor-category-chip shrink-0 rounded-full border border-line bg-surface px-4 py-2 text-xs font-semibold leading-4 text-muted"
            data-filter="{{ $category->slug }}">{{ $category->name }}</button>
          @endforeach
        </div>
      </div>

      <div data-vendor-product-grid class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">
        @foreach ($products as $product)
        <article
          class="product-card [&_img]:transition-transform [&_img]:duration-500 [&_img]:ease-out hover:[&_img]:scale-[1.045] group flex h-[451px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
          data-category="{{ $product->category?->slug }}" data-name="{{ $product->name }}">
          <div class="relative h-[284px] shrink-0 overflow-hidden bg-canvas"><img src="{{ \App\Support\Storefront\HomepageMedia::url($product->primaryImage?->path ?? $product->images->first()?->path) }}"
              alt="{{ $product->primaryImage?->alt_text ?: $product->name }}"
              class="h-full w-full object-cover"><span
              class="absolute end-3 top-3 rounded-lg bg-copper px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">{{ $product->category?->name }}</span><button form="vendor-favorite-{{ $product->id }}" type="submit"
              class="favorite absolute start-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
              aria-label="إضافة {{ $product->name }} للمفضلة"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                class="h-[13.323px] w-[14.625px]"></button><form hidden id="vendor-favorite-{{ $product->id }}" method="POST" action="{{ route('customer.favorites.store') }}">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"></form><button form="vendor-cart-{{ $product->id }}" type="submit"
              class="add-cart absolute start-3 end-3 bottom-3 flex translate-y-0 items-center justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold opacity-100 shadow-md transition-all sm:translate-y-2 sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
              data-price="{{ $product->price }}" @disabled(max(0, $product->stock_quantity - ($product->reserved_quantity ?? 0)) < 1)><span>إضافة إلى السلة</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                class="h-[13.103px] w-[12.918px]"></button><form hidden id="vendor-cart-{{ $product->id }}" method="POST" action="{{ route('customer.cart.items.store') }}">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"><input type="hidden" name="quantity" value="1"></form></div>
          <div class="flex min-h-0 flex-1 flex-col p-4">
            <div class="flex justify-start pb-3"><span
                class="flex items-center gap-1 rounded-lg bg-line/50 px-2 py-0.5 text-xs font-bold leading-4 text-olive">{{ $vendor->profile->store_name }} • {{ $vendor->profile->location_name }}<img src="{{ asset('assets/storefront/imgs/mcp/arrival-shop.svg') }}" alt=""
                  class="h-[10.354px] w-[11.584px]"></span></div>
            <a href="{{ route('product', ['slug' => $product->slug]) }}"><h3 class="pb-2 text-start text-base font-bold leading-6">{{ $product->name }}</h3></a>
            <div class="flex items-center justify-end gap-1 pb-3"><span
                class="text-[11px] leading-4 text-[#918f83]">(<bdi>{{ $product->reviews_count }}</bdi>)</span><bdi
                class="text-xs font-bold leading-4">{{ $product->reviews_count ? number_format($product->reviews_avg_rating, 1) : '—' }}</bdi><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt=""
                class="h-[11.083px] w-[11.667px]"></div>
            <div class="mt-auto flex items-center justify-between border-t border-line/60 pt-[13px]"><bdi
                class="text-lg font-bold leading-7">{{ number_format($product->price, 2) }} {{ $currency }}</bdi><span
                class="rounded-md bg-sage px-2 py-0.5 text-[11px] font-medium leading-[16.5px] text-surface">متوفر
                <bdi>{{ max(0, $product->stock_quantity - ($product->reserved_quantity ?? 0)) }}</bdi> قطع</span></div>
          </div>
        </article>
        @endforeach
      </div>

      <div data-vendor-empty-state
        class="{{ $products->isEmpty() ? '' : 'hidden ' }}rounded-[22px] border border-line bg-surface p-8 text-center shadow-card sm:p-10">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-copper/10 text-copper">
          <i data-lucide="package-search" class="h-7 w-7"></i>
        </span>
        <h2 class="mt-4 text-[22px] font-semibold leading-8 text-ink">لا توجد منتجات ضمن هذا التصنيف حالياً لدى هذا
          المشغل</h2>
        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-muted">يمكنك العودة إلى كل منتجات {{ $vendor->profile->store_name }} أو متابعة
          التصنيفات المتاحة حالياً.</p>
      </div>
    </div>
  </section>
</main>
