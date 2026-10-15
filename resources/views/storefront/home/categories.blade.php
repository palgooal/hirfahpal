    <section class="border-y border-line bg-surface px-4 py-12 sm:py-[65px] lg:h-[618px] lg:px-8"
      data-node-id="223:336">
      <div
        class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 mx-auto flex max-w-[1216px] flex-col gap-7 sm:gap-10">
        <div class="flex items-end justify-between" data-node-id="223:338">
          <div class="flex flex-col items-start gap-[6.5px] pt-[5.5px] text-right" data-node-id="223:344">
            <p class="text-xs font-bold uppercase leading-4 tracking-[.6px] text-copper">{{ $homeContent['categories_text_1'] }}</p>
            <h2 class="text-[30px] font-bold leading-9 text-ink">{{ $homeContent['categories_text_2'] }}</h2>
          </div>
          <a href="{{ route('categories') }}" class="hidden items-center gap-2.5 p-0.5 text-sm font-bold leading-5 text-olive sm:flex"
            data-node-id="223:339">
            <span>{{ $homeContent['categories_text_3'] }}</span>
            <img src="{{ asset('assets/storefront/imgs/mcp/category-arrow.svg') }}" alt="" class="h-[11.625px] w-[11.625px]">
          </a>
        </div>

        <div
          class="mobile-rail -mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto overscroll-x-contain px-4 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden [&>*]:w-[min(82vw,310px)] [&>*]:shrink-0 [&>*]:snap-start sm:mx-0 sm:grid sm:gap-6 sm:overflow-visible sm:px-0 sm:pb-0 sm:[&>*]:w-auto sm:[&>*]:shrink sm:grid-cols-2 lg:grid-cols-4"
          data-node-id="223:348">
          @forelse ($homeCategories as $category)
<a href="{{ route('browse', ['category' => $category->slug]) }}"
            class="group relative h-[384px] overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            data-node-id="223:394">
            <img src="{{ $category->imageMedia?->url ?: \App\Support\Storefront\HomepageMedia::url($category->image) }}" alt="{{ $category->name }}"
              class="absolute left-[-17.25%] top-0 h-full w-[134.51%] max-w-none transition-transform duration-500 group-hover:scale-[1.025]">
            <span class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/25 via-50% to-transparent"></span>
            <span
              class="absolute right-[14px] top-[14px] rounded-full border border-line/60 bg-surface px-[13px] py-[5px] text-xs font-bold leading-4 text-ink backdrop-blur-[2px]">{{ $category->name }}</span>
            <span class="absolute bottom-4 left-4 right-4 flex flex-col items-start gap-1 text-right">
              <small class="text-xs font-medium leading-4 text-[#fcd34d]">{{ $category->products_count }} قطعة</small>
              <strong class="text-xl font-bold leading-7 text-white">{{ $category->name }}</strong>
              <span class="block w-full truncate text-xs font-light leading-4 text-white/80">{{ $category->description }}</span>
            </span>
          </a>
          @empty
<p class="text-sm text-muted">{{ $homeContent['categories_text_4'] }}</p>
          @endforelse
        </div>
      </div>
    </section>
