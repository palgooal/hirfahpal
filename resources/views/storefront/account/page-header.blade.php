{{-- Shared account title section: $current (breadcrumb + page), $eyebrow, $title, $subtitle — all plain text, escaped. --}}
<section class="border-b border-line/60 bg-canvas px-4 py-6 lg:px-8">
  <div class="mx-auto flex max-w-[1216px] flex-col gap-5">
    <nav class="flex items-center gap-2 text-xs font-medium leading-4 text-muted sm:text-sm" aria-label="مسار الصفحة">
      <a href="{{ route('home') }}" class="hover:text-olive">الرئيسية</a>
      <span class="text-line">/</span>
      <a href="{{ route('customer.dashboard') }}" class="hover:text-olive">حسابي</a>
      <span class="text-line">/</span>
      <span class="font-semibold text-olive">{{ $current }}</span>
    </nav>
    <div class="flex max-w-3xl flex-col items-start gap-2 text-start">
      <p class="flex items-center gap-1.5 text-xs font-bold uppercase leading-4 tracking-[.6px] text-copper">
        <span class="h-1.5 w-1.5 rounded-full bg-copper"></span>{{ $eyebrow }}
      </p>
      <h1 class="text-[30px] font-bold leading-9 text-ink sm:text-4xl sm:leading-[48px]">{{ $title }}</h1>
      <p class="text-sm leading-6 text-muted sm:text-base">{{ $subtitle }}</p>
    </div>
  </div>
</section>
