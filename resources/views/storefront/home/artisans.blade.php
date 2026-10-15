<section id="artisans" class="bg-[#f2ebe0]/60 px-4 py-12 sm:py-16 lg:h-[490px] lg:px-8" data-node-id="223:835">
      <div
        class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 mx-auto flex w-full max-w-[1216px] flex-col items-center gap-8 sm:gap-12"
        data-node-id="223:836">
        <div class="flex w-full max-w-[672px] flex-col items-center gap-[6.5px] pt-[5.5px] text-center"
          data-node-id="223:837">
          <p class="text-xs font-bold uppercase leading-4 tracking-[.6px] text-copper">{{ $homeContent['artisans_text_1'] }}</p>
          <h2 class="text-[30px] font-bold leading-9">{{ $homeContent['artisans_text_2'] }}</h2>
          <p class="pt-[1.5px] text-sm leading-5 text-muted">نحتفي بـ {{ $artisanCount }} مبدعاً ومبدعة يكرسون أوقاتهم لحفظ الذاكرة
            اليدوية</p>
        </div>
        <div class="grid w-full gap-4 sm:gap-8 md:grid-cols-3" data-node-id="223:843">
          @forelse ($artisans as $artisan)
          <article
            class="flex min-h-[222px] flex-col rounded-[20px] border border-line bg-surface p-5 sm:h-[222px] sm:rounded-[24px] sm:p-[25px]"
            data-node-id="223:886">
            <div class="flex items-center justify-start gap-4 pb-4"><span
                class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border-2 border-copper/20 bg-copper/10 p-0.5 text-xl font-bold leading-7 text-copper">{{ mb_substr($artisan->profile->store_name, 0, 2) }}</span>
              <div class="text-right">
                <h3 class="text-base font-bold leading-6">{{ $artisan->profile->store_name }}</h3>
                <p class="flex items-center justify-start gap-1 text-xs font-medium leading-4 text-copper"><img
                    src="{{ asset('assets/storefront/imgs/mcp/artisan-location.svg') }}" alt="" class="h-[9.752px] w-[7.774px]"><span>{{ $artisan->profile->city?->name ?? $artisan->profile->governorate?->name }}</span></p>
              </div>
            </div>
            <p class="pb-6 text-right text-xs leading-[19.5px] text-muted">{{ $artisan->profile->short_description }}</p>
            <div class="mt-auto flex items-center justify-between border-t border-line/60 pt-[17px] text-xs leading-4">
              <span class="text-[#918f83]">{{ $artisan->products_count }} منتج متاح</span><a href="{{ route('browse', ['vendor' => $artisan->profile->slug]) }}"
                class="font-bold text-olive underline underline-offset-2">{{ $homeContent['artisans_text_3'] }}</a></div>
          </article>
          @empty
<p class="text-sm text-muted">{{ $homeContent['artisans_text_4'] }}</p>
          @endforelse
        </div>
      </div>
    </section>
