<section class="bg-canvas px-4 py-12 sm:py-16 lg:h-[459.75px] lg:px-8" data-node-id="223:945">
      <div
        class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 relative mx-auto flex w-full max-w-[1216px] items-start justify-center overflow-hidden rounded-[20px] border border-line bg-[#ebe4d5] px-4 py-10 sm:rounded-[24px] sm:px-5 sm:py-12 lg:h-[331.75px] lg:px-[272px] lg:py-[49px]"
        data-node-id="223:946">
        <div class="absolute -right-16 -top-16 h-64 w-64 rounded-full bg-copper/10 blur-[20px]" aria-hidden="true">
        </div>
        <div class="relative flex w-full max-w-[672px] flex-col items-center gap-4 pt-[5.5px] text-center"
          data-node-id="223:948">
          <p class="text-xs font-bold uppercase leading-4 tracking-[.6px] text-copper">{{ $homeContent['newsletter_text_1'] }}</p>
          <h2
            class="pt-[2.5px] text-[22px] font-bold leading-8 sm:text-2xl sm:leading-9 lg:whitespace-nowrap lg:text-[30px]">{{ $homeContent['newsletter_text_2'] }}</h2>
          <p class="text-sm leading-[22.75px] text-muted">{{ $homeContent['newsletter_text_3'] }}<bdi
              class="font-bold text-copper">10%</bdi>{{ $homeContent['newsletter_text_4'] }}</p>
          <form id="newsletter" method="POST" action="{{ route('newsletter.store') }}" class="flex w-full max-w-[448px] flex-col gap-3 pt-4 sm:flex-row"
            data-node-id="223:956">
            @csrf
            <input id="email" name="email" value="{{ old('email') }}" type="email" required maxlength="255" placeholder="أدخل بريدك الإلكتروني..."
              class="h-[46px] min-w-0 flex-1 rounded-[16px] border border-line bg-surface px-[17px] text-right text-sm text-ink outline-none placeholder:text-[#6b7280] focus:border-sage">
            <button type="submit"
              class="h-[46px] shrink-0 rounded-[16px] bg-sage px-6 text-sm font-bold leading-5 text-ink shadow-sm">{{ $homeContent['newsletter_text_5'] }}</button>
          </form>
          <p id="newsletterStatus" role="status" @if (!session('newsletter_status') && !$errors->has('email')) hidden @endif class="text-xs text-copper">{{ session('newsletter_status') ?: $errors->first('email') }}</p>
          <p class="pt-2 text-[11px] leading-[16.5px] text-[#918f83]">{{ $homeContent['newsletter_text_6'] }}</p>
        </div>
      </div>
    </section>
