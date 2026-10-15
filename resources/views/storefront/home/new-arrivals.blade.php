    <section class="bg-canvas px-4 py-12 sm:py-16 lg:h-[689.5px] lg:px-8" data-node-id="223:409">
      <div
        class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 mx-auto flex max-w-[1216px] flex-col gap-8">
        <div class="flex items-end justify-between border-b border-line pb-[17px]" data-node-id="223:410">
          <div class="flex flex-col items-start gap-1 pt-[5.5px] text-right">
            <p class="flex items-center gap-1.5 text-xs font-bold uppercase leading-4 tracking-[.6px] text-copper"><span
                class="h-1.5 w-1.5 rounded-full bg-copper"></span>{{ $homeContent['new-arrivals_text_1'] }}</p>
            <h2 class="text-[30px] font-bold leading-9">{{ $homeContent['new-arrivals_text_2'] }}</h2>
          </div>
          <a href="#shop"
            class="hidden items-center gap-2.5 py-0.5 text-xs font-bold leading-4 text-olive sm:flex"><span>{{ $homeContent['new-arrivals_text_3'] }}</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-arrow.svg') }}" alt="" class="h-[11.625px] w-[11.625px]"></a>
        </div>
        <div
          class="mobile-rail -mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto overscroll-x-contain px-4 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden [&>*]:w-[min(82vw,310px)] [&>*]:shrink-0 [&>*]:snap-start sm:mx-0 sm:grid sm:gap-6 sm:overflow-visible sm:px-0 sm:pb-0 sm:[&>*]:w-auto sm:[&>*]:shrink sm:grid-cols-2 lg:grid-cols-4"
          data-node-id="223:422">
          @forelse ($newProducts as $product)
          <article data-product-id="{{ $product->id }}"
            class="product-card [&_img]:transition-transform [&_img]:duration-500 [&_img]:ease-out hover:[&_img]:scale-[1.045] group flex h-[451px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px"
            data-node-id="223:543">
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ \App\Support\Storefront\HomepageMedia::url($product->primaryImage?->path ?? $product->images->first()?->path) }}"
                alt="{{ $product->primaryImage?->alt_text ?: $product->name }}" class="h-full w-full object-cover"><span
                class="absolute right-3 top-3 rounded-lg bg-copper px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">{{ $homeContent['new-arrivals_text_4'] }}</span><button
                class="favorite absolute left-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة للمفضلة" aria-pressed="{{ isset($favoriteIds[$product->id]) ? 'true' : 'false' }}" data-favorite-id="{{ $favoriteIds[$product->id] ?? '' }}"><img src="{{ asset('assets/storefront/imgs/mcp/arrival-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><button
                class="add-cart absolute bottom-3 left-3 right-3 flex translate-y-0 items-center sm:translate-y-2 justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold opacity-100 shadow-md transition-all sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="{{ $product->price }}" @disabled(max(0, $product->stock_quantity - ($product->reserved_quantity ?? 0)) < 1)><span>{{ $homeContent['new-arrivals_text_5'] }}</span><img src="{{ asset('assets/storefront/imgs/mcp/arrival-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex justify-start pb-3"><span
                  class="flex items-center gap-1 rounded-lg bg-line/50 px-2 py-0.5 text-xs font-bold leading-4 text-olive">{{ $product->vendor->profile->store_name }}<img src="{{ asset('assets/storefront/imgs/mcp/arrival-shop.svg') }}" alt=""
                    class="h-[10.354px] w-[11.584px]"></span></div>
              <a href="{{ route('product', ['slug' => $product->slug]) }}"><h3 class="pb-2 text-right text-base font-bold leading-6">{{ $product->name }}</h3></a>
              <div class="flex items-center justify-end gap-1 pb-3"><span
                  class="text-[11px] leading-4 text-[#918f83]">({{ $product->reviews_count }})</span><bdi
                  class="text-xs font-bold leading-4">{{ number_format($product->reviews_avg_rating ?? 0, 1) }}</bdi><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt=""
                  class="h-[11.083px] w-[11.667px]"></div>
              <div class="mt-auto flex items-center justify-between border-t border-line/60 pt-[13px]"><bdi
                  class="text-lg font-bold leading-7">{{ number_format($product->price, 2) }} {{ $currency }}</bdi><span
                  class="rounded-md bg-sage px-2 py-0.5 text-[11px] font-medium leading-[16.5px] text-surface">{{ $homeContent['new-arrivals_text_6'] }}</span></div>
            </div>
          </article>
          @empty
<p class="text-sm text-muted">{{ $homeContent['new-arrivals_text_7'] }}</p>
          @endforelse
        </div>
      </div>
    </section>
