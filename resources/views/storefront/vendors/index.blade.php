<main>
  <section class="border-b border-line/60 bg-canvas px-4 py-6 lg:px-8">
    <div class="mx-auto flex max-w-[1216px] flex-col gap-5">
      <nav class="flex items-center gap-2 text-xs font-medium leading-4 text-muted sm:text-sm"
        aria-label="مسار الصفحة">
        <a href="{{ route('home') }}" class="hover:text-olive">الرئيسية</a>
        <span class="text-line">/</span>
        <span class="font-semibold text-olive">كل المتاجر</span>
      </nav>
      <div class="flex max-w-3xl flex-col items-start gap-2 text-start">
        <p class="flex items-center gap-1.5 text-xs font-bold uppercase leading-4 tracking-[.6px] text-copper"><span
            class="h-1.5 w-1.5 rounded-full bg-copper"></span>دليل المشاغل المسجلة</p>
        <h1 class="text-[30px] font-bold leading-9 text-ink sm:text-4xl sm:leading-[48px]">كل المتاجر</h1>
        <p class="text-sm leading-6 text-muted sm:text-base">تعرّف على كل الحرفيين والمشاغل المسجلين في المنصة.</p>
      </div>
    </div>
  </section>

  <section class="bg-canvas px-4 py-10 sm:py-12 lg:px-8">
    <div
      class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 mx-auto flex max-w-[1216px] flex-col gap-7">
      <div
        class="flex flex-col gap-4 rounded-[20px] border border-line bg-surface p-4 shadow-card sm:flex-row sm:items-center sm:justify-between sm:p-5">
        <div class="text-start">
          <h2 class="text-[22px] font-semibold leading-8 text-ink">مشاغل وحرفيون من كل فلسطين</h2>
          <p class="text-sm leading-6 text-muted">ابحث بالاسم أو المدينة لتصل مباشرة إلى المتجر المناسب.</p>
        </div>
        <label class="relative block w-full sm:max-w-[384px]">
          <span class="sr-only">ابحث باسم المشغل أو المدينة</span>
          <i data-lucide="search"
            class="pointer-events-none absolute start-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted"></i>
          <input id="vendorSearch" type="search" placeholder="ابحث باسم المشغل أو المدينة..."
            class="h-12 w-full rounded-[16px] border border-line bg-surface ps-11 pe-4 text-start text-sm text-ink outline-none transition-colors placeholder:text-muted focus:border-sage">
        </label>
      </div>

      <div id="vendorsGrid" class="grid gap-4 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3">
        <article
          class="vendor-card flex min-h-[260px] flex-col rounded-[20px] border border-line bg-surface p-5 shadow-card transition-[border-color,box-shadow,transform] duration-300 hover:-translate-y-1 hover:border-copper/40 hover:shadow-soft sm:rounded-[24px] sm:p-[25px]"
          data-name="دار الكرمة للخزف" data-city="الخليل القديمة">
          <div class="flex items-center justify-start gap-4 pb-4">
            <span
              class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border-2 border-copper/20 bg-copper/10 p-0.5 text-xl font-bold leading-7 text-copper">د.ك</span>
            <div class="min-w-0 flex-1 text-start">
              <h3 class="truncate text-base font-bold leading-6">دار الكرمة للخزف</h3>
              <p class="flex items-center justify-start gap-1 text-xs font-medium leading-4 text-copper"><img
                  src="{{ asset('assets/storefront/imgs/mcp/artisan-location.svg') }}" alt="" class="h-[9.752px] w-[7.774px]"><span>الخليل
                  القديمة</span></p>
            </div>
          </div>
          <p class="pb-4 text-start text-xs leading-[19.5px] text-muted">مشغل عائلي يحفظ حكاية الخزف الأزرق والمزجج
            بلمسة ورثة الحرفة في البلدة القديمة.</p>
          <div class="flex items-center gap-1 pb-5 text-xs font-bold leading-4 text-ink" aria-label="تقييم 4.9 من 5">
            <bdi>4.9</bdi><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11.083px] w-[11.667px]">
          </div>
          <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[17px] text-xs">
            <span class="text-[#918f83]"><bdi>24</bdi> منتج متاح</span><a href="{{ route('vendors.show') }}"
              class="font-bold text-olive underline underline-offset-2 hover:text-copper">زيارة المتجر</a>
          </div>
        </article>

        <article
          class="vendor-card flex min-h-[260px] flex-col rounded-[20px] border border-line bg-surface p-5 shadow-card transition-[border-color,box-shadow,transform] duration-300 hover:-translate-y-1 hover:border-copper/40 hover:shadow-soft sm:rounded-[24px] sm:p-[25px]"
          data-name="جمعية نساء بيت لحم" data-city="بيت لحم">
          <div class="flex items-center justify-start gap-4 pb-4">
            <span
              class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border-2 border-sage/40 bg-sage/20 p-0.5 text-xl font-bold leading-7 text-olive">ن.ب</span>
            <div class="min-w-0 flex-1 text-start">
              <h3 class="truncate text-base font-bold leading-6">جمعية نساء بيت لحم</h3>
              <p class="flex items-center justify-start gap-1 text-xs font-medium leading-4 text-copper"><img
                  src="{{ asset('assets/storefront/imgs/mcp/artisan-location.svg') }}" alt="" class="h-[9.752px] w-[7.774px]"><span>بيت
                  لحم</span></p>
            </div>
          </div>
          <p class="pb-4 text-start text-xs leading-[19.5px] text-muted">تعاونية نسوية تجمع غرز التطريز الفلسطيني
            في قطع بيتية تحمل دفء اليد وذاكرة القرى.</p>
          <div class="flex items-center gap-1 pb-5 text-xs font-bold leading-4 text-ink" aria-label="تقييم 4.8 من 5">
            <bdi>4.8</bdi><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11.083px] w-[11.667px]">
          </div>
          <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[17px] text-xs">
            <span class="text-[#918f83]"><bdi>41</bdi> منتج متاح</span><a role="link" aria-disabled="true" data-deferred-navigation="vendor.html?id=bethlehem-women"
              class="font-bold text-olive underline underline-offset-2 hover:text-copper">زيارة المتجر</a>
          </div>
        </article>

        <article
          class="vendor-card flex min-h-[260px] flex-col rounded-[20px] border border-line bg-surface p-5 shadow-card transition-[border-color,box-shadow,transform] duration-300 hover:-translate-y-1 hover:border-copper/40 hover:shadow-soft sm:rounded-[24px] sm:p-[25px]"
          data-name="مشغل نور الزيتونة" data-city="نابلس">
          <div class="flex items-center justify-start gap-4 pb-4">
            <span
              class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border-2 border-copper/20 bg-copper/10 p-0.5 text-xl font-bold leading-7 text-copper">ن.ز</span>
            <div class="min-w-0 flex-1 text-start">
              <h3 class="truncate text-base font-bold leading-6">مشغل نور الزيتونة</h3>
              <p class="flex items-center justify-start gap-1 text-xs font-medium leading-4 text-copper"><img
                  src="{{ asset('assets/storefront/imgs/mcp/artisan-location.svg') }}" alt="" class="h-[9.752px] w-[7.774px]"><span>نابلس</span>
              </p>
            </div>
          </div>
          <p class="pb-4 text-start text-xs leading-[19.5px] text-muted">شموع وصابون بزيت الزيتون البكر، مصنوعة
            بروائح هادئة من سفوح نابلس.</p>
          <div class="flex items-center gap-1 pb-5 text-xs font-bold leading-4 text-ink" aria-label="تقييم 4.9 من 5">
            <bdi>4.9</bdi><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11.083px] w-[11.667px]">
          </div>
          <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[17px] text-xs">
            <span class="text-[#918f83]"><bdi>18</bdi> منتج متاح</span><a role="link" aria-disabled="true" data-deferred-navigation="vendor.html?id=noor-alzaytouna"
              class="font-bold text-olive underline underline-offset-2 hover:text-copper">زيارة المتجر</a>
          </div>
        </article>

        <article
          class="vendor-card flex min-h-[260px] flex-col rounded-[20px] border border-line bg-surface p-5 shadow-card transition-[border-color,box-shadow,transform] duration-300 hover:-translate-y-1 hover:border-copper/40 hover:shadow-soft sm:rounded-[24px] sm:p-[25px]"
          data-name="نسج الجليل التراثي" data-city="الجليل">
          <div class="flex items-center justify-start gap-4 pb-4">
            <span
              class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border-2 border-sage/40 bg-sage/20 p-0.5 text-xl font-bold leading-7 text-olive">ن.ج</span>
            <div class="min-w-0 flex-1 text-start">
              <h3 class="truncate text-base font-bold leading-6">نسج الجليل التراثي</h3>
              <p class="flex items-center justify-start gap-1 text-xs font-medium leading-4 text-copper"><img
                  src="{{ asset('assets/storefront/imgs/mcp/artisan-location.svg') }}" alt="" class="h-[9.752px] w-[7.774px]"><span>الجليل</span>
              </p>
            </div>
          </div>
          <p class="pb-4 text-start text-xs leading-[19.5px] text-muted">سلال وقش مصبوغ بنباتات طبيعية، كل جدلة
            فيها أثر موسم حصاد ويد صبورة.</p>
          <div class="flex items-center gap-1 pb-5 text-xs font-bold leading-4 text-ink" aria-label="تقييم 4.7 من 5">
            <bdi>4.7</bdi><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11.083px] w-[11.667px]">
          </div>
          <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[17px] text-xs">
            <span class="text-[#918f83]"><bdi>16</bdi> منتج متاح</span><a role="link" aria-disabled="true" data-deferred-navigation="vendor.html?id=jalil-weaving"
              class="font-bold text-olive underline underline-offset-2 hover:text-copper">زيارة المتجر</a>
          </div>
        </article>

        <article
          class="vendor-card flex min-h-[260px] flex-col rounded-[20px] border border-line bg-surface p-5 shadow-card transition-[border-color,box-shadow,transform] duration-300 hover:-translate-y-1 hover:border-copper/40 hover:shadow-soft sm:rounded-[24px] sm:p-[25px]"
          data-name="خشب الزيتون المقدسي" data-city="القدس">
          <div class="flex items-center justify-start gap-4 pb-4">
            <span
              class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border-2 border-copper/20 bg-copper/10 p-0.5 text-xl font-bold leading-7 text-copper">خ.ز</span>
            <div class="min-w-0 flex-1 text-start">
              <h3 class="truncate text-base font-bold leading-6">خشب الزيتون المقدسي</h3>
              <p class="flex items-center justify-start gap-1 text-xs font-medium leading-4 text-copper"><img
                  src="{{ asset('assets/storefront/imgs/mcp/artisan-location.svg') }}" alt="" class="h-[9.752px] w-[7.774px]"><span>القدس</span>
              </p>
            </div>
          </div>
          <p class="pb-4 text-start text-xs leading-[19.5px] text-muted">قطع خشب زيتون منحوتة بعناية، تترك عروق
            الشجرة تقود شكل الهدية وحكايتها.</p>
          <div class="flex items-center gap-1 pb-5 text-xs font-bold leading-4 text-ink" aria-label="تقييم 4.8 من 5">
            <bdi>4.8</bdi><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11.083px] w-[11.667px]">
          </div>
          <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[17px] text-xs">
            <span class="text-[#918f83]"><bdi>22</bdi> منتج متاح</span><a role="link" aria-disabled="true" data-deferred-navigation="vendor.html?id=jerusalem-olivewood"
              class="font-bold text-olive underline underline-offset-2 hover:text-copper">زيارة المتجر</a>
          </div>
        </article>

        <article
          class="vendor-card flex min-h-[260px] flex-col rounded-[20px] border border-line bg-surface p-5 shadow-card transition-[border-color,box-shadow,transform] duration-300 hover:-translate-y-1 hover:border-copper/40 hover:shadow-soft sm:rounded-[24px] sm:p-[25px]"
          data-name="منسوجات رواق رام الله" data-city="رام الله">
          <div class="flex items-center justify-start gap-4 pb-4">
            <span
              class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border-2 border-sage/40 bg-sage/20 p-0.5 text-xl font-bold leading-7 text-olive">ر.ر</span>
            <div class="min-w-0 flex-1 text-start">
              <h3 class="truncate text-base font-bold leading-6">منسوجات رواق رام الله</h3>
              <p class="flex items-center justify-start gap-1 text-xs font-medium leading-4 text-copper"><img
                  src="{{ asset('assets/storefront/imgs/mcp/artisan-location.svg') }}" alt="" class="h-[9.752px] w-[7.774px]"><span>رام
                  الله</span></p>
            </div>
          </div>
          <p class="pb-4 text-start text-xs leading-[19.5px] text-muted">أوشحة وبياضات بيتية منسوجة بخيوط قطنية
            هادئة، تحمل إيقاع النول لا ضجيج المصنع.</p>
          <div class="flex items-center gap-1 pb-5 text-xs font-bold leading-4 text-ink" aria-label="تقييم 4.6 من 5">
            <bdi>4.6</bdi><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11.083px] w-[11.667px]">
          </div>
          <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[17px] text-xs">
            <span class="text-[#918f83]"><bdi>19</bdi> منتج متاح</span><a role="link" aria-disabled="true" data-deferred-navigation="vendor.html?id=rawaq-ramallah"
              class="font-bold text-olive underline underline-offset-2 hover:text-copper">زيارة المتجر</a>
          </div>
        </article>

        <article
          class="vendor-card flex min-h-[260px] flex-col rounded-[20px] border border-line bg-surface p-5 shadow-card transition-[border-color,box-shadow,transform] duration-300 hover:-translate-y-1 hover:border-copper/40 hover:shadow-soft sm:rounded-[24px] sm:p-[25px]"
          data-name="صابون خان نابلس" data-city="نابلس">
          <div class="flex items-center justify-start gap-4 pb-4">
            <span
              class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border-2 border-copper/20 bg-copper/10 p-0.5 text-xl font-bold leading-7 text-copper">خ.ن</span>
            <div class="min-w-0 flex-1 text-start">
              <h3 class="truncate text-base font-bold leading-6">صابون خان نابلس</h3>
              <p class="flex items-center justify-start gap-1 text-xs font-medium leading-4 text-copper"><img
                  src="{{ asset('assets/storefront/imgs/mcp/artisan-location.svg') }}" alt="" class="h-[9.752px] w-[7.774px]"><span>نابلس</span>
              </p>
            </div>
          </div>
          <p class="pb-4 text-start text-xs leading-[19.5px] text-muted">قوالب صابون زيت زيتون بنَفَس نابلسي قديم،
            تقطع وتختم يدوياً كما كانت تفعل الخانات.</p>
          <div class="flex items-center gap-1 pb-5 text-xs font-bold leading-4 text-ink" aria-label="تقييم 4.9 من 5">
            <bdi>4.9</bdi><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11.083px] w-[11.667px]">
          </div>
          <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[17px] text-xs">
            <span class="text-[#918f83]"><bdi>27</bdi> منتج متاح</span><a role="link" aria-disabled="true" data-deferred-navigation="vendor.html?id=khan-nablus-soap"
              class="font-bold text-olive underline underline-offset-2 hover:text-copper">زيارة المتجر</a>
          </div>
        </article>

        <article
          class="vendor-card flex min-h-[260px] flex-col rounded-[20px] border border-line bg-surface p-5 shadow-card transition-[border-color,box-shadow,transform] duration-300 hover:-translate-y-1 hover:border-copper/40 hover:shadow-soft sm:rounded-[24px] sm:p-[25px]"
          data-name="تطريز مرج ابن عامر" data-city="جنين">
          <div class="flex items-center justify-start gap-4 pb-4">
            <span
              class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border-2 border-sage/40 bg-sage/20 p-0.5 text-xl font-bold leading-7 text-olive">م.ع</span>
            <div class="min-w-0 flex-1 text-start">
              <h3 class="truncate text-base font-bold leading-6">تطريز مرج ابن عامر</h3>
              <p class="flex items-center justify-start gap-1 text-xs font-medium leading-4 text-copper"><img
                  src="{{ asset('assets/storefront/imgs/mcp/artisan-location.svg') }}" alt="" class="h-[9.752px] w-[7.774px]"><span>جنين</span>
              </p>
            </div>
          </div>
          <p class="pb-4 text-start text-xs leading-[19.5px] text-muted">وسائد وحقائب مطرزة بغرز ريفية هادئة، تنسج
            ألوان المرج في قطعة تعيش طويلاً.</p>
          <div class="flex items-center gap-1 pb-5 text-xs font-bold leading-4 text-ink" aria-label="تقييم 4.7 من 5">
            <bdi>4.7</bdi><img src="{{ asset('assets/storefront/imgs/mcp/arrival-star.svg') }}" alt="" class="h-[11.083px] w-[11.667px]">
          </div>
          <div class="mt-auto flex items-center justify-between gap-3 border-t border-line/60 pt-[17px] text-xs">
            <span class="text-[#918f83]"><bdi>14</bdi> منتج متاح</span><a role="link" aria-disabled="true" data-deferred-navigation="vendor.html?id=marj-embroidery"
              class="font-bold text-olive underline underline-offset-2 hover:text-copper">زيارة المتجر</a>
          </div>
        </article>
      </div>

      <div id="vendorEmptyState"
        class="hidden rounded-[20px] border border-line bg-surface p-8 text-center shadow-card sm:p-10">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-copper/10 text-copper">
          <i data-lucide="search-x" class="h-7 w-7"></i>
        </span>
        <h2 class="mt-4 text-[22px] font-semibold leading-8 text-ink">لا يوجد تجار مطابقين</h2>
        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-muted">جرّب اسماً أقصر أو ابحث باسم مدينة مثل نابلس،
          الخليل، بيت لحم، أو القدس.</p>
        <button id="clearVendorSearch" type="button"
          class="mt-5 inline-flex items-center justify-center gap-2 rounded-[16px] bg-sage px-5 py-3 text-sm font-bold text-ink shadow-sm transition-colors hover:bg-[#a7af82]">
          <i data-lucide="x" class="h-4 w-4"></i>
          <span>مسح البحث</span>
        </button>
      </div>
    </div>
  </section>
</main>
