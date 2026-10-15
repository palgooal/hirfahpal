    <section id="season" class="bg-surface px-4 py-12 sm:py-16 lg:h-[844px] lg:px-8" data-node-id="223:583">
      <div
        class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 mx-auto flex max-w-[1216px] flex-col gap-10">
        <div class="flex flex-col gap-5 border-b border-line pb-[17px] sm:flex-row sm:items-end sm:justify-between"
          data-node-id="223:584">
          <div class="flex shrink-0 flex-col items-start gap-1 pt-[5.5px] text-right">
            <p class="text-xs font-bold uppercase leading-4 tracking-[.6px] text-copper">{{ $homeContent['season_text_1'] }}</p>
            <h2 class="pt-[2.5px] text-[30px] font-bold leading-9">{{ $homeContent['season_text_2'] }}</h2>
            <p class="text-sm leading-5 text-muted">{{ $homeContent['season_text_3'] }}</p>
          </div>
          <div
            class="hide-scrollbar [scrollbar-width:none] [&::-webkit-scrollbar]:hidden flex max-w-full flex-nowrap items-center gap-2 overflow-x-auto pb-1 sm:flex-wrap sm:overflow-visible"
            data-node-id="223:585">
            <button
              class="season-filter rounded-full border border-sage bg-sage px-4 py-1.5 text-xs font-bold leading-4 text-ink shadow-sm"
              data-filter="all">{{ $homeContent['season_text_4'] }}</button>
            @foreach ($filterCategories as $category)
<button
              class="season-filter rounded-full border border-line bg-surface px-[17px] py-[7px] text-xs font-medium leading-4 text-muted"
              data-filter="{{ $category->slug }}">{{ $category->name }}</button>
            @endforeach
          </div>
        </div>
        <div
          class="season-grid mobile-rail -mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto overscroll-x-contain px-4 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden [&>*]:w-[min(82vw,310px)] [&>*]:shrink-0 [&>*]:snap-start sm:mx-0 sm:grid sm:gap-6 sm:overflow-visible sm:px-0 sm:pb-0 sm:[&>*]:w-auto sm:[&>*]:shrink sm:grid-cols-2 lg:grid-cols-4"
          data-node-id="223:602">
          @forelse ($seasonProducts as $product)
          <article data-product-id="{{ $product->id }}"
            class="season-product product-card [&_img]:transition-transform [&_img]:duration-500 [&_img]:ease-out hover:[&_img]:scale-[1.045] group flex h-[473px] flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-px"
            data-category="{{ $product->category?->slug }}" data-node-id="223:738">
            <div class="relative h-[284px] shrink-0 overflow-hidden"><img src="{{ \App\Support\Storefront\HomepageMedia::url($product->primaryImage?->path ?? $product->images->first()?->path) }}"
                alt="{{ $product->primaryImage?->alt_text ?: $product->name }}" class="h-full w-full object-cover"><span
                class="absolute right-3 top-3 rounded-lg bg-copper px-2.5 py-0.5 text-[11px] font-bold leading-[16.5px] text-white shadow-sm">{{ $homeContent['season_text_5'] }}</span><button
                class="favorite absolute left-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-surface shadow-sm"
                aria-label="إضافة للمفضلة" aria-pressed="{{ isset($favoriteIds[$product->id]) ? 'true' : 'false' }}" data-favorite-id="{{ $favoriteIds[$product->id] ?? '' }}"><img src="{{ asset('assets/storefront/imgs/mcp/season-heart.svg') }}" alt=""
                  class="h-[13.323px] w-[14.625px]"></button><button
                class="add-cart absolute bottom-3 left-3 right-3 flex translate-y-0 items-center sm:translate-y-2 justify-center gap-2 rounded-[16px] bg-sage px-4 py-2.5 text-xs font-bold opacity-100 shadow-md transition-all sm:opacity-0 group-hover:translate-y-0 group-hover:opacity-100"
                data-price="{{ $product->price }}" @disabled(max(0, $product->stock_quantity - ($product->reserved_quantity ?? 0)) < 1)><span>{{ $homeContent['season_text_6'] }}</span><img src="{{ asset('assets/storefront/imgs/mcp/season-cart.svg') }}" alt=""
                  class="h-[13.103px] w-[12.918px]"></button></div>
            <div class="flex min-h-0 flex-1 flex-col p-4">
              <div class="flex justify-start pb-3"><span
                  class="flex items-center gap-1 rounded-lg bg-line/50 px-2 py-0.5 text-xs font-bold leading-4 text-olive">{{ $product->vendor->profile->store_name }}<img src="{{ asset('assets/storefront/imgs/mcp/season-shop.svg') }}" alt="" class="h-[10.354px] w-[11.584px]"></span>
              </div>
              <h3 class="pb-1.5 text-right text-base font-bold leading-6"><a href="{{ route('product', ['slug' => $product->slug]) }}">{{ $product->name }}</a></h3>
              <div class="flex items-center justify-end gap-1 pb-2"><span
                  class="text-[11px] leading-4 text-[#918f83]">({{ $product->reviews_count }})</span><bdi
                  class="text-xs font-bold leading-4">{{ number_format($product->reviews_avg_rating ?? 0, 1) }}</bdi><img src="{{ asset('assets/storefront/imgs/mcp/season-star.svg') }}" alt=""
                  class="h-[11.083px] w-[11.667px]"></div>
              <p class="truncate pb-3 text-right text-xs leading-4 text-[#918f83]">{{ $product->short_description }}</p>
              <div class="mt-auto flex items-center justify-between border-t border-line/60 pt-[13px]"><span
                  class="flex items-baseline gap-1"><small class="text-xs text-[#918f83]">{{ $homeContent['season_text_7'] }}</small><bdi
                    class="text-lg font-bold leading-7">{{ number_format($product->price, 2) }} {{ $currency }}</bdi></span><span
                  class="rounded-md bg-sage px-2 py-0.5 text-[11px] font-medium leading-[16.5px] text-surface">{{ max(0, $product->stock_quantity - ($product->reserved_quantity ?? 0)) }} قطعة متاحة</span></div>
            </div>
          </article>
          @empty
<p class="text-sm text-muted">{{ $homeContent['season_text_9'] }}</p>
          @endforelse
        </div>
        <div class="flex h-[52px] items-center justify-center sm:h-[58px]" data-node-id="223:783"><a href="#shop"
            class="inline-flex h-[52px] max-w-full items-center gap-2.5 rounded-[16px] border border-line bg-surface px-5 text-center text-sm font-bold leading-5 shadow-sm sm:h-[58px] sm:px-8"><span>{{ $homeContent['season_text_10'] }}</span><img src="{{ asset('assets/storefront/imgs/mcp/season-arrow.svg') }}" alt=""
              class="h-[11.625px] w-[11.625px]"></a></div>
      </div>
    </section>
