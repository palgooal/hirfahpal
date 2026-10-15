@extends('layouts.storefront')

@section('title', 'كل التصنيفات | حِرفة')

@section('content')
<main>
    <section class="border-b border-line/60 bg-canvas px-4 py-5 lg:px-8">
      <div class="mx-auto flex max-w-[1216px] flex-col gap-6">
        <nav class="flex items-center gap-2 text-xs font-medium leading-4 text-muted sm:text-sm"
          aria-label="مسار الصفحة">
          <a href="{{ route('home') }}" class="hover:text-olive">الرئيسية</a>
          <span class="text-line">/</span>
          <span class="font-semibold text-olive">كل التصنيفات</span>
        </nav>
        <div class="flex max-w-3xl flex-col items-start gap-2 text-start">
          <p class="flex items-center gap-1.5 text-xs font-bold uppercase leading-4 tracking-[.6px] text-copper"><span
              class="h-1.5 w-1.5 rounded-full bg-copper"></span>بوابة اكتشاف الحرف</p>
          <h1 class="text-[30px] font-bold leading-9 text-ink sm:text-4xl sm:leading-[48px]">كل التصنيفات</h1>
          <p class="text-sm leading-6 text-muted sm:text-base">تصفح كل حِرف ومشاغل فلسطين حسب الفئة.</p>
        </div>
      </div>
    </section>

    <section class="bg-canvas px-4 py-10 sm:py-14 lg:px-8">
      <div
        class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 mx-auto flex max-w-[1216px] flex-col gap-7 sm:gap-10">
        <div class="flex flex-col items-start gap-1.5 text-start">
          <p class="text-xs font-bold uppercase leading-4 tracking-[.6px] text-copper">الفئات المتاحة على المنصة</p>
          <h2 class="text-[26px] font-bold leading-9 text-ink sm:text-[30px]">اختر الحرفة التي تريد استكشافها</h2>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6 lg:grid-cols-4">
          @forelse ($categories as $category)
<a href="{{ route('browse', ['category' => $category->slug]) }}"
            class="group relative h-[384px] overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            aria-label="تصفح {{ $category->name }}">
            <img src="{{ $category->imageMedia?->url ?: \App\Support\Storefront\HomepageMedia::url($category->image) }}" alt="{{ $category->name }}"
              class="absolute start-[-17.25%] top-0 h-full w-[134.51%] max-w-none transition-transform duration-500 group-hover:scale-[1.025]">
            <span class="absolute start-0 end-0 top-0 bottom-0 bg-gradient-to-t from-black/80 via-black/25 via-50% to-transparent"></span>
            <span
              class="absolute end-[14px] top-[14px] rounded-full border border-line/60 bg-surface px-[13px] py-[5px] text-xs font-bold leading-4 text-ink backdrop-blur-[2px]">{{ $category->name }}</span>
            <span class="absolute start-4 end-4 bottom-4 flex flex-col items-start gap-1 text-start">
              <small class="text-xs font-medium leading-4 text-[#fcd34d]"><bdi>{{ $category->products_count }}</bdi> قطعة</small>
              <strong class="text-xl font-bold leading-7 text-white">{{ $category->name }}</strong>
              <span class="block w-full truncate text-xs font-light leading-4 text-white/80">{{ $category->description }}</span>
              <span class="text-[11px] font-semibold leading-4 text-surface/80"><bdi>{{ $category->children_count }}</bdi> تصنيفات فرعية</span>
            </span>
          </a>
@empty
<p class="text-sm text-muted">لا توجد تصنيفات متاحة حالياً.</p>
@endforelse

        </div>
      </div>
    </section>
  </main>
@endsection
