    <section id="shop" class="bg-canvas px-4 py-10 lg:h-[888.5px] lg:px-8" data-node-id="223:158">
      <div
        class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 mx-auto flex max-w-[1216px] flex-col gap-6 overflow-hidden rounded-[20px] bg-surface p-4 shadow-[0_4px_6px_-1px_rgba(0,0,0,.1),0_2px_4px_-2px_rgba(0,0,0,.1)] sm:gap-8 sm:rounded-[24px] sm:p-5 lg:h-[808.5px] lg:p-10">
        <div
          class="flex flex-col gap-5 border-b border-line/60 pb-[25px] lg:h-[87px] lg:flex-row lg:items-start lg:justify-between"
          data-node-id="223:161">
          <div class="shrink-0 pt-[5.5px] text-right">
            <p class="flex items-center gap-1.5 text-xs font-bold leading-4 tracking-[.6px] text-copper"><span
                class="h-1.5 w-1.5 rounded-full bg-copper"></span>{{ $homeContent['popular-products_text_1'] }}</p>
            <h2 class="mt-[4.5px] text-[30px] font-bold leading-9">{{ $homeContent['popular-products_text_2'] }}</h2>
          </div>
          <div
            class="hide-scrollbar [scrollbar-width:none] [&::-webkit-scrollbar]:hidden flex gap-2 overflow-x-auto pb-1 pt-[13px] font-noto">
            <button
              class="filter-chip shrink-0 rounded-full border border-sage bg-sage px-4 py-2 text-xs font-bold leading-4 text-ink"
              data-filter="all">{{ $homeContent['popular-products_text_3'] }}</button>
            @foreach ($filterCategories as $category)
<button
              class="filter-chip shrink-0 rounded-full border border-transparent px-4 py-2 text-xs font-semibold leading-4 text-muted"
              data-filter="{{ $category->slug }}">{{ $category->name }}</button>
            @endforeach

          </div>
        </div>
        <div
          class="product-grid mobile-rail popular-rail -mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto overscroll-x-contain px-4 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden [&>*]:w-[min(82vw,310px)] [&>*]:shrink-0 [&>*]:snap-start sm:mx-0 sm:grid sm:gap-6 sm:overflow-visible sm:px-0 sm:pb-0 sm:[&>*]:w-auto sm:[&>*]:shrink sm:grid-cols-2 lg:h-[512.5px] lg:grid-cols-4"
          data-node-id="223:177">
          @forelse ($popularProducts as $product)
          <article data-product-id="{{ $product->id }}"
            class="product-card [&_img]:transition-transform [&_img]:duration-500 [&_img]:ease-out hover:[&_img]:scale-[1.045] flex h-[486px] flex-col justify-between sm:h-[512.5px] overflow-hidden rounded-[16px] bg-surface font-noto shadow-sm"
            data-category="{{ $product->category?->slug }}" data-name="{{ $product->name }}" data-node-id="223:288">
            <div>
              <div class="relative h-[240px] overflow-hidden bg-white sm:h-auto sm:aspect-square"><img
                  src="{{ \App\Support\Storefront\HomepageMedia::url($product->primaryImage?->path ?? $product->images->first()?->path) }}" alt="{{ $product->primaryImage?->alt_text ?: $product->name }}"
                  class="{{ $loop->first ? 'absolute left-[6.02%] top-[26.03%] h-[47.94%] w-[87.97%] max-w-none' : 'absolute left-[-41.76%] top-0 h-full w-[183.51%] max-w-none' }}"><span
                  class="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-full {{ $loop->first ? 'bg-copper text-white' : 'bg-olive text-surface' }} text-xs font-bold ring-2 ring-[#fdfbf6] shadow-md">#{{ $loop->iteration }}</span><span
                  class="absolute left-3 top-3 rounded bg-ink px-2 py-1 text-[10px] font-medium leading-[15px] text-surface">{{ $product->vendor->profile->store_name }}</span></div>
              <div class="flex h-[173.5px] flex-col items-start gap-1 p-5"><span
                  class="flex h-[24.5px] items-center gap-1.5 rounded-md bg-copper/10 px-2.5 text-[11px] font-semibold text-copper"><img
                    src="{{ asset('assets/storefront/imgs/mcp/pitcher-flame.svg') }}" alt="" class="h-[10.292px] w-[8.667px]">{{ $product->category?->name }}</span>
                <a href="{{ route('product', ['slug' => $product->slug]) }}"><h3 class="w-full pt-1.5 text-right text-base font-bold leading-6">{{ $product->name }}</h3></a>
                <p class="w-full text-right text-xs leading-[19.5px] text-muted">{{ $product->short_description }}</p>
                <div class="flex w-full items-center justify-end gap-1.5 pt-3 text-xs"><span class="text-muted">({{ $product->reviews_count }})</span><bdi class="font-bold">{{ number_format($product->reviews_avg_rating ?? 0, 1) }}</bdi><img src="{{ asset('assets/storefront/imgs/mcp/pitcher-star.svg') }}" alt=""
                    class="h-[11.083px] w-[11.667px]"></div>
              </div>
            </div>
            <div class="flex h-[73px] items-start justify-between border-t border-line/60 px-5 pb-5 pt-[9px]">
              <div class="text-right"><span class="block text-xs leading-4 text-muted">{{ $homeContent['popular-products_text_4'] }}</span><bdi
                  class="block text-lg font-bold leading-7 text-copper">{{ number_format($product->price, 2) }} {{ $currency }}</bdi></div><button
                class="add-cart flex items-center gap-1.5 rounded-lg bg-sage px-3 py-2 text-xs font-semibold"
                data-price="{{ $product->price }}" @disabled(max(0, $product->stock_quantity - ($product->reserved_quantity ?? 0)) < 1)><img src="{{ asset('assets/storefront/imgs/mcp/pitcher-cart.svg') }}" alt=""
                  class="h-[13.333px] w-[13.321px]"><span>{{ $homeContent['popular-products_text_5'] }}</span></button>
            </div>
          </article>
          @empty
<p class="text-sm text-muted">{{ $homeContent['popular-products_text_6'] }}</p>
          @endforelse
        </div>
        <div
          class="flex min-h-[65px] flex-col items-start gap-3 border-t border-line/60 pt-5 text-xs text-muted sm:flex-row sm:justify-between sm:pt-[25px]"
          data-node-id="223:324">
          <p class="flex items-start gap-2 pt-1 leading-5 sm:items-center sm:pt-3"><span
              class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-[#7a8f5e] sm:mt-0"></span>{{ $homeContent['popular-products_text_7'] }}</p>
          <div class="hidden gap-2 sm:flex"><button
              class="flex h-10 w-10 items-center justify-center rounded-full bg-surface" aria-label="التالي"><img
                src="{{ asset('assets/storefront/imgs/mcp/carousel-next.svg') }}" alt="" class="h-[9px] w-[5.55px]"></button><button
              class="flex h-10 w-10 items-center justify-center rounded-full bg-surface" aria-label="السابق"><img
                src="{{ asset('assets/storefront/imgs/mcp/carousel-prev.svg') }}" alt="" class="h-[9px] w-[5.55px]"></button></div>
        </div>
      </div>
    </section>
