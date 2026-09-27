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
          <a href="{{ route('browse', ['category' => 'pottery']) }}"
            class="group relative h-[384px] overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            aria-label="تصفح الفخار والخزف">
            <img src="{{ asset('assets/storefront/imgs/mcp/category-ceramics.png') }}" alt="الفخار والخزف اليدوي الفلسطيني"
              class="absolute start-[-17.25%] top-0 h-full w-[134.51%] max-w-none transition-transform duration-500 group-hover:scale-[1.025]">
            <span class="absolute start-0 end-0 top-0 bottom-0 bg-gradient-to-t from-black/80 via-black/25 via-50% to-transparent"></span>
            <span
              class="absolute end-[14px] top-[14px] rounded-full border border-line/60 bg-surface px-[13px] py-[5px] text-xs font-bold leading-4 text-ink backdrop-blur-[2px]">الخليل
              والقدس</span>
            <span class="absolute start-4 end-4 bottom-4 flex flex-col items-start gap-1 text-start">
              <small class="text-xs font-medium leading-4 text-[#fcd34d]"><bdi>34</bdi> قطعة فريدة</small>
              <strong class="text-xl font-bold leading-7 text-white">الفخار والخزف</strong>
              <span class="block w-full truncate text-xs font-light leading-4 text-white/80">أوانٍ مقدسية وأباريق
                مرسومة يدوياً بالزخارف الزرقاء</span>
              <span class="text-[11px] font-semibold leading-4 text-surface/80"><bdi>2</bdi> تصنيفات فرعية</span>
            </span>
          </a>

          <a href="{{ route('browse', ['category' => 'embroidery']) }}"
            class="group relative h-[384px] overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            aria-label="تصفح التطريز الفلسطيني">
            <img src="{{ asset('assets/storefront/imgs/mcp/category-embroidery.png') }}" alt="التطريز الفلسطيني التراثي"
              class="absolute start-[-17.25%] top-0 h-full w-[134.51%] max-w-none transition-transform duration-500 group-hover:scale-[1.025]">
            <span class="absolute start-0 end-0 top-0 bottom-0 bg-gradient-to-t from-black/80 via-black/25 via-50% to-transparent"></span>
            <span
              class="absolute end-[14px] top-[14px] rounded-full border border-line/60 bg-surface px-[13px] py-[5px] text-xs font-bold leading-4 text-ink backdrop-blur-[2px]">بيت
              لحم ورام الله</span>
            <span class="absolute start-4 end-4 bottom-4 flex flex-col items-start gap-1 text-start">
              <small class="text-xs font-medium leading-4 text-[#fcd34d]"><bdi>52</bdi> قطعة مطرزة</small>
              <strong class="text-xl font-bold leading-7 text-white">التطريز</strong>
              <span class="block w-full truncate text-xs font-light leading-4 text-white/80">وسائد، شالات وإكسسوارات
                بقطبة الفلاحي ونقشات كنعانية</span>
              <span class="text-[11px] font-semibold leading-4 text-surface/80"><bdi>2</bdi> تصنيفات فرعية</span>
            </span>
          </a>

          <a href="{{ route('browse', ['category' => 'baskets']) }}"
            class="group relative h-[384px] overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            aria-label="تصفح القش والسلال">
            <img src="{{ asset('assets/storefront/imgs/mcp/category-basket.png') }}" alt="السلال والقش والنسيج الريفي"
              class="absolute start-[-17.25%] top-0 h-full w-[134.51%] max-w-none transition-transform duration-500 group-hover:scale-[1.025]">
            <span class="absolute start-0 end-0 top-0 bottom-0 bg-gradient-to-t from-black/80 via-black/25 via-50% to-transparent"></span>
            <span
              class="absolute end-[14px] top-[14px] rounded-full border border-line/60 bg-surface px-[13px] py-[5px] text-xs font-bold leading-4 text-ink backdrop-blur-[2px]">قرى
              الجليل والخليل</span>
            <span class="absolute start-4 end-4 bottom-4 flex flex-col items-start gap-1 text-start">
              <small class="text-xs font-medium leading-4 text-[#fcd34d]"><bdi>18</bdi> عملاً يدوياً</small>
              <strong class="text-xl font-bold leading-7 text-white">القش والسلال</strong>
              <span class="block w-full truncate text-xs font-light leading-4 text-white/80">سلال قش طبيعية من سيقان
                القمح ونبات الدوم الفلسطيني</span>
            </span>
          </a>

          <a href="{{ route('browse', ['category' => 'candles-soaps']) }}"
            class="group relative h-[384px] overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            aria-label="تصفح الشموع والصابون">
            <img src="{{ asset('assets/storefront/imgs/mcp/category-candle.png') }}" alt="شموع وزيوت طبيعية وصابون نابلسي"
              class="absolute start-[-17.25%] top-0 h-full w-[134.51%] max-w-none transition-transform duration-500 group-hover:scale-[1.025]">
            <span class="absolute start-0 end-0 top-0 bottom-0 bg-gradient-to-t from-black/80 via-black/25 via-50% to-transparent"></span>
            <span
              class="absolute end-[14px] top-[14px] rounded-full border border-line/60 bg-surface px-[13px] py-[5px] text-xs font-bold leading-4 text-ink backdrop-blur-[2px]">نابلس
              وجنين</span>
            <span class="absolute start-4 end-4 bottom-4 flex flex-col items-start gap-1 text-start">
              <small class="text-xs font-medium leading-4 text-[#fcd34d]"><bdi>27</bdi> صنفاً طبيعياً</small>
              <strong class="text-xl font-bold leading-7 text-white">الشموع والصابون</strong>
              <span class="block w-full truncate text-xs font-light leading-4 text-white/80">شموع بزيت الزيتون
                وصابون بلدي بروائح طبيعية هادئة</span>
            </span>
          </a>

          <a href="{{ route('browse', ['category' => 'textiles']) }}"
            class="group relative h-[384px] overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            aria-label="تصفح المنسوجات والوسائد">
            <img src="{{ asset('assets/storefront/imgs/mcp/arrival-cushion.png') }}" alt="وسائد ومنسوجات فلسطينية مطرزة"
              class="absolute start-[-17.25%] top-0 h-full w-[134.51%] max-w-none transition-transform duration-500 group-hover:scale-[1.025]">
            <span class="absolute start-0 end-0 top-0 bottom-0 bg-gradient-to-t from-black/80 via-black/25 via-50% to-transparent"></span>
            <span
              class="absolute end-[14px] top-[14px] rounded-full border border-line/60 bg-surface px-[13px] py-[5px] text-xs font-bold leading-4 text-ink backdrop-blur-[2px]">بيت
              لحم</span>
            <span class="absolute start-4 end-4 bottom-4 flex flex-col items-start gap-1 text-start">
              <small class="text-xs font-medium leading-4 text-[#fcd34d]"><bdi>31</bdi> قطعة قماشية</small>
              <strong class="text-xl font-bold leading-7 text-white">المنسوجات والوسائد</strong>
              <span class="block w-full truncate text-xs font-light leading-4 text-white/80">أقمشة كتان ووسائد بيتية
                تحمل نقشات ريفية أصيلة</span>
            </span>
          </a>

          <a href="{{ route('browse', ['category' => 'tableware']) }}"
            class="group relative h-[384px] overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            aria-label="تصفح أواني التقديم">
            <img src="{{ asset('assets/storefront/imgs/mcp/arrival-pitcher.png') }}" alt="أواني تقديم خزفية مرسومة يدوياً"
              class="absolute start-[-17.25%] top-0 h-full w-[134.51%] max-w-none transition-transform duration-500 group-hover:scale-[1.025]">
            <span class="absolute start-0 end-0 top-0 bottom-0 bg-gradient-to-t from-black/80 via-black/25 via-50% to-transparent"></span>
            <span
              class="absolute end-[14px] top-[14px] rounded-full border border-line/60 bg-surface px-[13px] py-[5px] text-xs font-bold leading-4 text-ink backdrop-blur-[2px]">القدس
              والخليل</span>
            <span class="absolute start-4 end-4 bottom-4 flex flex-col items-start gap-1 text-start">
              <small class="text-xs font-medium leading-4 text-[#fcd34d]"><bdi>22</bdi> طقماً وقطعة</small>
              <strong class="text-xl font-bold leading-7 text-white">أواني التقديم</strong>
              <span class="block w-full truncate text-xs font-light leading-4 text-white/80">أباريق وأطباق تقديم
                للمائدة اليومية والهدايا</span>
            </span>
          </a>

          <a href="{{ route('browse', ['category' => 'gifts']) }}"
            class="group relative h-[384px] overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            aria-label="تصفح الهدايا التراثية">
            <img src="{{ asset('assets/storefront/imgs/mcp/hero-main.png') }}" alt="مجموعة هدايا تراثية من مشغولات فلسطينية"
              class="absolute start-[-17.25%] top-0 h-full w-[134.51%] max-w-none transition-transform duration-500 group-hover:scale-[1.025]">
            <span class="absolute start-0 end-0 top-0 bottom-0 bg-gradient-to-t from-black/80 via-black/25 via-50% to-transparent"></span>
            <span
              class="absolute end-[14px] top-[14px] rounded-full border border-line/60 bg-surface px-[13px] py-[5px] text-xs font-bold leading-4 text-ink backdrop-blur-[2px]">فلسطين</span>
            <span class="absolute start-4 end-4 bottom-4 flex flex-col items-start gap-1 text-start">
              <small class="text-xs font-medium leading-4 text-[#fcd34d]"><bdi>16</bdi> مجموعة جاهزة</small>
              <strong class="text-xl font-bold leading-7 text-white">الهدايا التراثية</strong>
              <span class="block w-full truncate text-xs font-light leading-4 text-white/80">اختيارات منسقة للتغليف
                والإهداء مع بطاقة قصة القطعة</span>
            </span>
          </a>

          <a href="{{ route('browse', ['category' => 'home-decor']) }}"
            class="group relative h-[384px] overflow-hidden rounded-[24px] border border-line bg-surface p-px shadow-sm"
            aria-label="تصفح زينة البيت اليدوية">
            <img src="{{ asset('assets/storefront/imgs/mcp/season-basket.png') }}" alt="زينة بيت يدوية من القش والمنسوجات"
              class="absolute start-[-17.25%] top-0 h-full w-[134.51%] max-w-none transition-transform duration-500 group-hover:scale-[1.025]">
            <span class="absolute start-0 end-0 top-0 bottom-0 bg-gradient-to-t from-black/80 via-black/25 via-50% to-transparent"></span>
            <span
              class="absolute end-[14px] top-[14px] rounded-full border border-line/60 bg-surface px-[13px] py-[5px] text-xs font-bold leading-4 text-ink backdrop-blur-[2px]">رام
              الله والجليل</span>
            <span class="absolute start-4 end-4 bottom-4 flex flex-col items-start gap-1 text-start">
              <small class="text-xs font-medium leading-4 text-[#fcd34d]"><bdi>24</bdi> قطعة للبيت</small>
              <strong class="text-xl font-bold leading-7 text-white">زينة البيت اليدوية</strong>
              <span class="block w-full truncate text-xs font-light leading-4 text-white/80">تفاصيل صغيرة للبيوت
                الدافئة من خامات طبيعية ومشاغل محلية</span>
            </span>
          </a>
        </div>
      </div>
    </section>
  </main>
@endsection
