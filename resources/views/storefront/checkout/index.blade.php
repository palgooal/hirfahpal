<main data-checkout-page>
  <section class="border-b border-line/60 bg-canvas px-4 py-6 lg:px-8">
    <div class="mx-auto flex max-w-[1216px] flex-col gap-5">
      <nav class="flex items-center gap-2 text-xs font-medium leading-4 text-muted sm:text-sm"
        aria-label="مسار الصفحة">
        <a href="{{ route('home') }}" class="hover:text-olive">الرئيسية</a>
        <span class="text-line">/</span>
        <a href="{{ route('cart') }}" class="hover:text-olive">سلة المشتريات</a>
        <span class="text-line">/</span>
        <span class="font-semibold text-olive">إتمام الطلب</span>
      </nav>
      <div class="flex max-w-3xl flex-col items-start gap-2 text-start">
        <p class="flex items-center gap-1.5 text-xs font-bold uppercase leading-4 tracking-[.6px] text-copper"><span
            class="h-1.5 w-1.5 rounded-full bg-copper"></span>مراجعة نهائية قبل إنشاء الطلب</p>
        <h1 class="text-[30px] font-bold leading-9 text-ink sm:text-4xl sm:leading-[48px]">إتمام الطلب</h1>
        <p class="text-sm leading-6 text-muted sm:text-base">راجع العنوان، الطلبات الفرعية حسب كل تاجر، وطريقة الدفع قبل التأكيد النهائي.</p>
      </div>
    </div>
  </section>

  <section class="bg-canvas px-4 py-10 sm:py-12 lg:px-8">
    <div
      class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 mx-auto grid max-w-[1216px] gap-6 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start">
      <div class="flex min-w-0 flex-col gap-6">
        <section class="rounded-[20px] border border-line bg-surface p-5 shadow-card sm:p-6" aria-labelledby="addressTitle">
          <div class="flex flex-col gap-2 border-b border-line pb-5 text-start sm:flex-row sm:items-start sm:justify-between">
            <div>
              <h2 id="addressTitle" class="text-[22px] font-semibold leading-8 text-ink">عنوان التوصيل</h2>
              <p class="mt-1 text-sm leading-6 text-muted">اختر عنواناً محفوظاً أو أدخل عنواناً جديداً لهذا الطلب.</p>
            </div>
            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-sage/20 text-olive">
              <i data-lucide="map-pin" class="h-5 w-5"></i>
            </span>
          </div>

          <div class="mt-5 grid gap-3 md:grid-cols-3">
            <label tabindex="0"
              class="address-card cursor-pointer rounded-[16px] border border-olive bg-olive/5 p-4 text-start shadow-card transition-colors">
              <input type="radio" name="addressChoice" value="home" class="sr-only" checked>
              <span class="flex items-center justify-between gap-3">
                <strong class="text-base font-bold leading-6 text-ink">المنزل</strong>
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-olive text-surface"><i
                    data-lucide="check" class="h-4 w-4"></i></span>
              </span>
              <span class="mt-3 block text-sm leading-6 text-muted">ليان خليل</span>
              <span class="block text-sm leading-6 text-muted">رام الله، حي الطيرة، قرب دوار الساعة</span>
              <span class="mt-2 block text-sm font-semibold leading-6 text-ink"><bdi>0599123456</bdi></span>
            </label>

            <label tabindex="0"
              class="address-card cursor-pointer rounded-[16px] border border-line bg-surface p-4 text-start transition-colors hover:border-olive/50">
              <input type="radio" name="addressChoice" value="work" class="sr-only">
              <span class="flex items-center justify-between gap-3">
                <strong class="text-base font-bold leading-6 text-ink">العمل</strong>
                <span class="flex h-7 w-7 items-center justify-center rounded-full border border-line text-muted"><i
                    data-lucide="building-2" class="h-4 w-4"></i></span>
              </span>
              <span class="mt-3 block text-sm leading-6 text-muted">ليان خليل</span>
              <span class="block text-sm leading-6 text-muted">القدس، شارع صلاح الدين، الطابق <bdi>2</bdi></span>
              <span class="mt-2 block text-sm font-semibold leading-6 text-ink"><bdi>0599123456</bdi></span>
            </label>

            <label tabindex="0"
              class="address-card cursor-pointer rounded-[16px] border border-line bg-surface p-4 text-start transition-colors hover:border-olive/50">
              <input type="radio" name="addressChoice" value="new" class="sr-only">
              <span class="flex items-center justify-between gap-3">
                <strong class="text-base font-bold leading-6 text-ink">إضافة عنوان جديد</strong>
                <span class="flex h-7 w-7 items-center justify-center rounded-full border border-line text-muted"><i
                    data-lucide="plus" class="h-4 w-4"></i></span>
              </span>
              <span class="mt-3 block text-sm leading-6 text-muted">استخدم هذا الخيار لإدخال اسم مستلم ومدينة ووصف واضح للعنوان.</span>
            </label>
          </div>

          <div id="newAddressForm" class="mt-5 hidden rounded-[16px] border border-line bg-canvas p-4">
            <div class="grid gap-4 sm:grid-cols-2">
              <label class="text-start">
                <span class="mb-2 block text-sm font-semibold leading-5 text-ink">اسم المستلم</span>
                <input type="text"
                  class="h-12 w-full rounded-[14px] border border-line bg-surface px-4 text-sm outline-none transition-colors focus:border-olive"
                  placeholder="الاسم الكامل">
              </label>
              <label class="text-start">
                <span class="mb-2 block text-sm font-semibold leading-5 text-ink">رقم الهاتف</span>
                <input type="tel"
                  class="h-12 w-full rounded-[14px] border border-line bg-surface px-4 text-sm outline-none transition-colors focus:border-olive"
                  placeholder="05xxxxxxxx">
              </label>
              <label class="text-start">
                <span class="mb-2 block text-sm font-semibold leading-5 text-ink">المحافظة</span>
                <select
                  class="h-12 w-full rounded-[14px] border border-line bg-surface px-4 text-sm outline-none transition-colors focus:border-olive">
                  <option>القدس</option>
                  <option>الخليل</option>
                  <option>بيت لحم</option>
                  <option>رام الله</option>
                  <option>نابلس</option>
                  <option>جنين</option>
                  <option>الجليل</option>
                </select>
              </label>
              <label class="text-start sm:col-span-2">
                <span class="mb-2 block text-sm font-semibold leading-5 text-ink">الشارع ووصف العنوان</span>
                <textarea rows="3"
                  class="w-full rounded-[14px] border border-line bg-surface px-4 py-3 text-sm leading-6 outline-none transition-colors focus:border-olive"
                  placeholder="الحي، الشارع، أقرب علامة واضحة، وأي تفاصيل تساعد التوصيل"></textarea>
              </label>
            </div>
          </div>
        </section>

        <section class="flex flex-col gap-5" aria-labelledby="orderSummaryTitle">
          <div class="text-start">
            <h2 id="orderSummaryTitle" class="text-[22px] font-semibold leading-8 text-ink">ملخص الطلب حسب التاجر</h2>
            <p class="mt-1 text-sm leading-6 text-muted">كل تاجر سيُنشئ طلباً فرعياً مستقلاً بتكلفة توصيله الخاصة.</p>
          </div>

          <article class="cart-vendor-group overflow-hidden rounded-[20px] border border-line bg-surface shadow-card"
            data-vendor-slug="dar-al-karma">
            <header class="flex flex-col gap-4 border-b border-line/70 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
              <a role="link" aria-disabled="true" data-deferred-navigation="vendor.html?id=dar-al-karma" class="flex min-w-0 items-center justify-start gap-3 text-start">
                <span
                  class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border-2 border-copper/20 bg-copper/10 text-lg font-bold leading-7 text-copper">د.ك</span>
                <span class="min-w-0">
                  <strong class="block truncate text-base font-bold leading-6 text-ink">دار الكرمة للخزف</strong>
                  <span class="block text-xs leading-4 text-muted">كل قطعة من هذا المشغل تُجهّز يدوياً</span>
                </span>
              </a>
              <span class="w-fit rounded-full border border-copper/20 bg-copper/10 px-3 py-1 text-xs font-bold text-copper">مجموعة
                تاجر مستقلة</span>
            </header>

            <div class="divide-y divide-line/70">
              <article class="cart-line-item grid gap-4 px-4 py-5 sm:grid-cols-[96px_minmax(0,1fr)] sm:px-5 lg:grid-cols-[104px_minmax(0,1fr)_150px]">
                <a role="link" aria-disabled="true" data-deferred-navigation="product.html" class="block h-24 w-24 overflow-hidden rounded-[16px] border border-line bg-canvas sm:h-24 sm:w-24 lg:h-[104px] lg:w-[104px]">
                  <img src="{{ asset('assets/storefront/imgs/mcp/arrival-pitcher.png') }}" alt="إبريق فخار مقدسي مزخرف"
                    class="h-full w-full object-cover">
                </a>
                <div class="min-w-0 text-start">
                  <h3 class="text-base font-bold leading-6 text-ink">إبريق فخار مقدسي مزخرف</h3>
                  <p class="mt-1 text-xs leading-5 text-muted">قطعة تقديم مرسومة يدوياً من خزف الخليل.</p>
                  <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:max-w-[420px]">
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">سعر القطعة</span>
                      <strong class="block text-sm font-bold leading-6 text-ink"><bdi>₪145</bdi></strong>
                    </div>
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">الكمية</span>
                      <strong class="mt-1 inline-flex h-[42px] items-center rounded-xl border border-line bg-canvas px-4 text-sm font-bold text-ink"><bdi>1</bdi></strong>
                    </div>
                  </div>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-line/60 pt-4 sm:col-start-2 lg:col-start-auto lg:flex-col lg:items-end lg:border-t-0 lg:pt-0">
                  <div class="text-start lg:text-end">
                    <span class="block text-[11px] font-semibold leading-4 text-muted">إجمالي السطر</span>
                    <strong class="block text-lg font-bold leading-7 text-olive"><bdi>₪145</bdi></strong>
                  </div>
                </div>
              </article>

              <article class="cart-line-item grid gap-4 px-4 py-5 sm:grid-cols-[96px_minmax(0,1fr)] sm:px-5 lg:grid-cols-[104px_minmax(0,1fr)_150px]">
                <a role="link" aria-disabled="true" data-deferred-navigation="product.html" class="block h-24 w-24 overflow-hidden rounded-[16px] border border-line bg-canvas sm:h-24 sm:w-24 lg:h-[104px] lg:w-[104px]">
                  <img src="{{ asset('assets/storefront/imgs/mcp/category-ceramics.png') }}" alt="طبق تقديم خزفي أزرق"
                    class="h-full w-full object-cover">
                </a>
                <div class="min-w-0 text-start">
                  <h3 class="text-base font-bold leading-6 text-ink">طبق تقديم خزفي أزرق</h3>
                  <p class="mt-1 text-xs leading-5 text-muted">طبق متوسط بزخرفة نباتية هادئة للمائدة اليومية.</p>
                  <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:max-w-[420px]">
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">سعر القطعة</span>
                      <strong class="block text-sm font-bold leading-6 text-ink"><bdi>₪135</bdi></strong>
                    </div>
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">الكمية</span>
                      <strong class="mt-1 inline-flex h-[42px] items-center rounded-xl border border-line bg-canvas px-4 text-sm font-bold text-ink"><bdi>2</bdi></strong>
                    </div>
                  </div>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-line/60 pt-4 sm:col-start-2 lg:col-start-auto lg:flex-col lg:items-end lg:border-t-0 lg:pt-0">
                  <div class="text-start lg:text-end">
                    <span class="block text-[11px] font-semibold leading-4 text-muted">إجمالي السطر</span>
                    <strong class="block text-lg font-bold leading-7 text-olive"><bdi>₪270</bdi></strong>
                  </div>
                </div>
              </article>
            </div>

            <footer class="grid gap-3 border-t border-line bg-canvas px-4 py-4 text-start sm:px-5">
              <div class="flex items-center justify-between gap-4">
                <span class="text-sm font-semibold leading-6 text-muted">المجموع الفرعي</span>
                <strong class="text-xl font-bold leading-8 text-ink"><bdi>₪415</bdi></strong>
              </div>
              <div class="flex items-center justify-between gap-4">
                <span class="text-sm font-semibold leading-6 text-muted">توصيل هذا التاجر</span>
                <strong class="text-base font-bold leading-7 text-ink"><bdi>₪18</bdi></strong>
              </div>
              <div class="flex items-center justify-between gap-4 border-t border-line/70 pt-3">
                <span class="text-sm font-bold leading-6 text-ink">المجموع مع التوصيل</span>
                <strong class="text-xl font-bold leading-8 text-olive"><bdi>₪433</bdi></strong>
              </div>
            </footer>
          </article>

          <article class="cart-vendor-group overflow-hidden rounded-[20px] border border-line bg-surface shadow-card"
            data-vendor-slug="bethlehem-women">
            <header class="flex flex-col gap-4 border-b border-line/70 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
              <a role="link" aria-disabled="true" data-deferred-navigation="vendor.html?id=bethlehem-women" class="flex min-w-0 items-center justify-start gap-3 text-start">
                <span
                  class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border-2 border-sage/40 bg-sage/20 text-lg font-bold leading-7 text-olive">ن.ب</span>
                <span class="min-w-0">
                  <strong class="block truncate text-base font-bold leading-6 text-ink">جمعية نساء بيت لحم</strong>
                  <span class="block text-xs leading-4 text-muted">تعاونية نسوية تجمع التطريز والبيت الدافئ</span>
                </span>
              </a>
              <span class="w-fit rounded-full border border-copper/20 bg-copper/10 px-3 py-1 text-xs font-bold text-copper">مجموعة
                تاجر مستقلة</span>
            </header>

            <div class="divide-y divide-line/70">
              <article class="cart-line-item grid gap-4 px-4 py-5 sm:grid-cols-[96px_minmax(0,1fr)] sm:px-5 lg:grid-cols-[104px_minmax(0,1fr)_150px]">
                <a role="link" aria-disabled="true" data-deferred-navigation="product.html" class="block h-24 w-24 overflow-hidden rounded-[16px] border border-line bg-canvas sm:h-24 sm:w-24 lg:h-[104px] lg:w-[104px]">
                  <img src="{{ asset('assets/storefront/imgs/mcp/arrival-cushion.png') }}" alt="وسادة تطريز فلاحي كنعاني"
                    class="h-full w-full object-cover">
                </a>
                <div class="min-w-0 text-start">
                  <h3 class="text-base font-bold leading-6 text-ink">وسادة تطريز فلاحي كنعاني</h3>
                  <p class="mt-1 text-xs leading-5 text-muted">قماش كتان مطرز بغرزة الفلاحي ونقوش النجمة.</p>
                  <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:max-w-[420px]">
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">سعر القطعة</span>
                      <strong class="block text-sm font-bold leading-6 text-ink"><bdi>₪220</bdi></strong>
                    </div>
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">الكمية</span>
                      <strong class="mt-1 inline-flex h-[42px] items-center rounded-xl border border-line bg-canvas px-4 text-sm font-bold text-ink"><bdi>1</bdi></strong>
                    </div>
                  </div>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-line/60 pt-4 sm:col-start-2 lg:col-start-auto lg:flex-col lg:items-end lg:border-t-0 lg:pt-0">
                  <div class="text-start lg:text-end">
                    <span class="block text-[11px] font-semibold leading-4 text-muted">إجمالي السطر</span>
                    <strong class="block text-lg font-bold leading-7 text-olive"><bdi>₪220</bdi></strong>
                  </div>
                </div>
              </article>

              <article class="cart-line-item grid gap-4 px-4 py-5 sm:grid-cols-[96px_minmax(0,1fr)] sm:px-5 lg:grid-cols-[104px_minmax(0,1fr)_150px]">
                <a role="link" aria-disabled="true" data-deferred-navigation="product.html" class="block h-24 w-24 overflow-hidden rounded-[16px] border border-line bg-canvas sm:h-24 sm:w-24 lg:h-[104px] lg:w-[104px]">
                  <img src="{{ asset('assets/storefront/imgs/mcp/category-embroidery.png') }}" alt="شال مطرز بخيوط قطنية"
                    class="h-full w-full object-cover">
                </a>
                <div class="min-w-0 text-start">
                  <h3 class="text-base font-bold leading-6 text-ink">شال مطرز بخيوط قطنية</h3>
                  <p class="mt-1 text-xs leading-5 text-muted">شال خفيف بنقشة ريفية مناسبة للهدايا اليومية.</p>
                  <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:max-w-[420px]">
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">سعر القطعة</span>
                      <strong class="block text-sm font-bold leading-6 text-ink"><bdi>₪180</bdi></strong>
                    </div>
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">الكمية</span>
                      <strong class="mt-1 inline-flex h-[42px] items-center rounded-xl border border-line bg-canvas px-4 text-sm font-bold text-ink"><bdi>1</bdi></strong>
                    </div>
                  </div>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-line/60 pt-4 sm:col-start-2 lg:col-start-auto lg:flex-col lg:items-end lg:border-t-0 lg:pt-0">
                  <div class="text-start lg:text-end">
                    <span class="block text-[11px] font-semibold leading-4 text-muted">إجمالي السطر</span>
                    <strong class="block text-lg font-bold leading-7 text-olive"><bdi>₪180</bdi></strong>
                  </div>
                </div>
              </article>
            </div>

            <footer class="grid gap-3 border-t border-line bg-canvas px-4 py-4 text-start sm:px-5">
              <div class="flex items-center justify-between gap-4">
                <span class="text-sm font-semibold leading-6 text-muted">المجموع الفرعي</span>
                <strong class="text-xl font-bold leading-8 text-ink"><bdi>₪400</bdi></strong>
              </div>
              <div class="flex items-center justify-between gap-4">
                <span class="text-sm font-semibold leading-6 text-muted">توصيل هذا التاجر</span>
                <strong class="text-base font-bold leading-7 text-ink"><bdi>₪22</bdi></strong>
              </div>
              <div class="flex items-center justify-between gap-4 border-t border-line/70 pt-3">
                <span class="text-sm font-bold leading-6 text-ink">المجموع مع التوصيل</span>
                <strong class="text-xl font-bold leading-8 text-olive"><bdi>₪422</bdi></strong>
              </div>
            </footer>
          </article>

          <article class="cart-vendor-group overflow-hidden rounded-[20px] border border-line bg-surface shadow-card"
            data-vendor-slug="noor-alzaytouna">
            <header class="flex flex-col gap-4 border-b border-line/70 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
              <a role="link" aria-disabled="true" data-deferred-navigation="vendor.html?id=noor-alzaytouna" class="flex min-w-0 items-center justify-start gap-3 text-start">
                <span
                  class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border-2 border-copper/20 bg-copper/10 text-lg font-bold leading-7 text-copper">ن.ز</span>
                <span class="min-w-0">
                  <strong class="block truncate text-base font-bold leading-6 text-ink">مشغل نور الزيتونة</strong>
                  <span class="block text-xs leading-4 text-muted">شموع وصابون بروائح طبيعية هادئة</span>
                </span>
              </a>
              <span class="w-fit rounded-full border border-copper/20 bg-copper/10 px-3 py-1 text-xs font-bold text-copper">مجموعة
                تاجر مستقلة</span>
            </header>

            <div class="divide-y divide-line/70">
              <article class="cart-line-item grid gap-4 px-4 py-5 sm:grid-cols-[96px_minmax(0,1fr)] sm:px-5 lg:grid-cols-[104px_minmax(0,1fr)_150px]">
                <a role="link" aria-disabled="true" data-deferred-navigation="product.html" class="block h-24 w-24 overflow-hidden rounded-[16px] border border-line bg-canvas sm:h-24 sm:w-24 lg:h-[104px] lg:w-[104px]">
                  <img src="{{ asset('assets/storefront/imgs/mcp/arrival-candle.png') }}" alt="شمعة صويا وزيت زيتون"
                    class="h-full w-full object-cover">
                </a>
                <div class="min-w-0 text-start">
                  <h3 class="text-base font-bold leading-6 text-ink">شمعة صويا وزيت زيتون</h3>
                  <p class="mt-1 text-xs leading-5 text-muted">شمعة في وعاء فخاري صغير بخيط خشبي آمن.</p>
                  <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:max-w-[420px]">
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">سعر القطعة</span>
                      <strong class="block text-sm font-bold leading-6 text-ink"><bdi>₪68</bdi></strong>
                    </div>
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">الكمية</span>
                      <strong class="mt-1 inline-flex h-[42px] items-center rounded-xl border border-line bg-canvas px-4 text-sm font-bold text-ink"><bdi>2</bdi></strong>
                    </div>
                  </div>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-line/60 pt-4 sm:col-start-2 lg:col-start-auto lg:flex-col lg:items-end lg:border-t-0 lg:pt-0">
                  <div class="text-start lg:text-end">
                    <span class="block text-[11px] font-semibold leading-4 text-muted">إجمالي السطر</span>
                    <strong class="block text-lg font-bold leading-7 text-olive"><bdi>₪136</bdi></strong>
                  </div>
                </div>
              </article>

              <article class="cart-line-item grid gap-4 px-4 py-5 sm:grid-cols-[96px_minmax(0,1fr)] sm:px-5 lg:grid-cols-[104px_minmax(0,1fr)_150px]">
                <a role="link" aria-disabled="true" data-deferred-navigation="product.html" class="block h-24 w-24 overflow-hidden rounded-[16px] border border-line bg-canvas sm:h-24 sm:w-24 lg:h-[104px] lg:w-[104px]">
                  <img src="{{ asset('assets/storefront/imgs/mcp/category-candle.png') }}" alt="مجموعة شموع زيت زيتون صغيرة"
                    class="h-full w-full object-cover">
                </a>
                <div class="min-w-0 text-start">
                  <h3 class="text-base font-bold leading-6 text-ink">مجموعة شموع زيت زيتون صغيرة</h3>
                  <p class="mt-1 text-xs leading-5 text-muted">ثلاث شموع بروائح هادئة للحجرات الصغيرة والهدايا.</p>
                  <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:max-w-[420px]">
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">سعر المجموعة</span>
                      <strong class="block text-sm font-bold leading-6 text-ink"><bdi>₪60</bdi></strong>
                    </div>
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">الكمية</span>
                      <strong class="mt-1 inline-flex h-[42px] items-center rounded-xl border border-line bg-canvas px-4 text-sm font-bold text-ink"><bdi>1</bdi></strong>
                    </div>
                  </div>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-line/60 pt-4 sm:col-start-2 lg:col-start-auto lg:flex-col lg:items-end lg:border-t-0 lg:pt-0">
                  <div class="text-start lg:text-end">
                    <span class="block text-[11px] font-semibold leading-4 text-muted">إجمالي السطر</span>
                    <strong class="block text-lg font-bold leading-7 text-olive"><bdi>₪60</bdi></strong>
                  </div>
                </div>
              </article>
            </div>

            <footer class="grid gap-3 border-t border-line bg-canvas px-4 py-4 text-start sm:px-5">
              <div class="flex items-center justify-between gap-4">
                <span class="text-sm font-semibold leading-6 text-muted">المجموع الفرعي</span>
                <strong class="text-xl font-bold leading-8 text-ink"><bdi>₪196</bdi></strong>
              </div>
              <div class="flex items-center justify-between gap-4">
                <span class="text-sm font-semibold leading-6 text-muted">توصيل هذا التاجر</span>
                <strong class="text-base font-bold leading-7 text-ink"><bdi>₪15</bdi></strong>
              </div>
              <div class="flex items-center justify-between gap-4 border-t border-line/70 pt-3">
                <span class="text-sm font-bold leading-6 text-ink">المجموع مع التوصيل</span>
                <strong class="text-xl font-bold leading-8 text-olive"><bdi>₪211</bdi></strong>
              </div>
            </footer>
          </article>
        </section>

        <section data-payment-selector class="rounded-[20px] border border-line bg-surface p-5 shadow-card sm:p-6" aria-labelledby="paymentTitle">
          <div class="flex flex-col gap-2 border-b border-line pb-5 text-start sm:flex-row sm:items-start sm:justify-between">
            <div>
              <h2 id="paymentTitle" class="text-[22px] font-semibold leading-8 text-ink">طريقة الدفع</h2>
              <p class="mt-1 text-sm leading-6 text-muted">اختر طريقة دفع واحدة بوضوح. لا توجد طريقة محددة مسبقاً.</p>
            </div>
            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-copper/10 text-copper">
              <i data-lucide="credit-card" class="h-5 w-5"></i>
            </span>
          </div>

          <div class="mt-5 grid gap-4 md:grid-cols-2" role="radiogroup" aria-describedby="paymentError">
            <label for="paymentOnline" tabindex="0" data-payment-value="online"
              class="payment-card relative cursor-pointer rounded-[18px] border border-line bg-surface p-5 text-start transition-all hover:border-olive/50"
              role="radio" aria-checked="false">
              <input id="paymentOnline" type="radio" name="paymentMethod" value="online" class="sr-only">
              <span class="flex items-start justify-between gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-olive/10 text-olive">
                  <i data-lucide="landmark" class="h-5 w-5"></i>
                </span>
                <span
                  class="payment-check flex h-7 w-7 scale-75 items-center justify-center rounded-full bg-olive text-surface opacity-0 transition-all"><i
                    data-lucide="check" class="h-4 w-4"></i></span>
              </span>
              <strong class="mt-4 block text-lg font-bold leading-7 text-ink">الدفع الإلكتروني</strong>
              <span class="mt-1 block text-sm leading-6 text-muted">إتمام الدفع عبر بوابة دفع آمنة قبل إرسال الطلب للتجار.</span>
            </label>

            <label for="paymentCod" tabindex="0" data-payment-value="cod"
              class="payment-card relative cursor-pointer rounded-[18px] border border-line bg-surface p-5 text-start transition-all hover:border-olive/50"
              role="radio" aria-checked="false">
              <input id="paymentCod" type="radio" name="paymentMethod" value="cod" class="sr-only">
              <span class="flex items-start justify-between gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-copper/10 text-copper">
                  <i data-lucide="wallet-cards" class="h-5 w-5"></i>
                </span>
                <span
                  class="payment-check flex h-7 w-7 scale-75 items-center justify-center rounded-full bg-olive text-surface opacity-0 transition-all"><i
                    data-lucide="check" class="h-4 w-4"></i></span>
              </span>
              <strong class="mt-4 block text-lg font-bold leading-7 text-ink">الدفع عند الاستلام</strong>
              <span class="mt-1 block text-sm leading-6 text-muted">تدفع المبلغ الكامل عند تسليم الطلب حسب تنسيق التوصيل.</span>
            </label>
          </div>
          <p id="paymentError" class="mt-4 hidden rounded-[14px] border border-[#A13D2B]/30 bg-[#A13D2B]/10 px-4 py-3 text-start text-sm font-semibold leading-6 text-[#A13D2B]"
            role="alert"></p>
        </section>
      </div>

      <aside class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 rounded-[20px] border border-line bg-surface p-5 shadow-card lg:sticky lg:top-24">
        <div class="flex items-center justify-between gap-4 border-b border-line pb-4">
          <div class="text-start">
            <h2 class="text-[22px] font-semibold leading-8 text-ink">الإجمالي النهائي</h2>
            <p class="text-xs leading-5 text-muted"><bdi>6</bdi> منتجات من <bdi>3</bdi> متاجر</p>
          </div>
          <span class="flex h-11 w-11 items-center justify-center rounded-full bg-copper/10 text-copper">
            <i data-lucide="receipt-text" class="h-5 w-5"></i>
          </span>
        </div>

        <div class="grid gap-3 py-5 text-sm">
          <div class="flex items-center justify-between gap-4 text-muted">
            <span>مجموع المنتجات</span>
            <strong class="font-bold text-ink"><bdi>₪1,011</bdi></strong>
          </div>
          <div class="rounded-[16px] border border-copper/30 bg-copper/10 p-4 text-start">
            <div class="flex items-center justify-between gap-4">
              <span class="text-sm font-bold leading-6 text-copper">إجمالي تكلفة التوصيل</span>
              <strong class="text-lg font-bold leading-7 text-copper"><bdi>₪55</bdi></strong>
            </div>
            <p class="mt-1 text-xs leading-5 text-muted">مجموع توصيل الطلبات الفرعية الثلاثة.</p>
          </div>
          <div class="flex items-center justify-between gap-4 text-muted">
            <span>إجمالي التوصيل</span>
            <strong class="font-bold text-ink"><bdi>₪55</bdi></strong>
          </div>
        </div>

        <div class="border-t border-line pt-5">
          <div class="flex items-end justify-between gap-4">
            <span class="text-base font-bold leading-7 text-ink">المجموع الكلي</span>
            <strong class="text-3xl font-bold leading-10 text-olive"><bdi>₪1,066</bdi></strong>
          </div>
          <div class="mt-4 rounded-[16px] border border-line bg-canvas p-4 text-start">
            <span class="block text-xs font-semibold leading-5 text-muted">طريقة الدفع المختارة</span>
            <strong data-selected-payment class="mt-1 block text-sm font-bold leading-6 text-ink">لم يتم الاختيار بعد</strong>
          </div>
          <button id="placeOrderButton" type="button" aria-disabled="true"
            class="mt-5 inline-flex h-12 w-full items-center justify-center gap-2 rounded-[16px] bg-olive px-5 text-sm font-bold text-surface opacity-70 shadow-sm transition-colors">
            <span>تأكيد الطلب</span>
            <i data-lucide="shield-check" class="h-4 w-4"></i>
          </button>
          <p class="mt-3 text-start text-xs leading-5 text-muted">سيظهر تنبيه نهائي قبل إنشاء الطلب لأن الإلغاء غير متاح بعد التأكيد.</p>
        </div>
      </aside>
    </div>
  </section>
</main>

<div id="confirmOrderModal" class="fixed start-0 end-0 top-0 bottom-0 z-[90] hidden bg-ink/50 p-4 backdrop-blur-sm" role="dialog"
  aria-modal="true" aria-labelledby="confirmOrderTitle">
  <div id="confirmOrderPanel" class="mx-auto mt-20 max-w-xl rounded-[20px] border border-line bg-surface p-5 shadow-soft sm:p-6">
    <div class="flex items-start justify-between gap-4 border-b border-line pb-4">
      <div class="text-start">
        <span class="mb-3 inline-flex items-center gap-2 rounded-full border border-gold/30 bg-gold/10 px-3 py-1 text-xs font-bold text-gold">
          <i data-lucide="triangle-alert" class="h-4 w-4"></i>
          تأكيد نهائي مطلوب
        </span>
        <h2 id="confirmOrderTitle" class="text-[24px] font-bold leading-9 text-ink">تنويه: بعد تأكيد الطلب لا يمكن إلغاؤه</h2>
        <p class="mt-2 text-sm leading-6 text-muted">راجع المبلغ وطريقة الدفع. عند الضغط على التأكيد النهائي سيتم إنشاء الطلبات الفرعية للتجار.</p>
      </div>
      <button type="button" data-close-confirm-modal
        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-line bg-canvas text-muted hover:text-ink"
        aria-label="إغلاق"><i data-lucide="x" class="h-4 w-4"></i></button>
    </div>

    <div class="grid gap-3 py-5 text-sm">
      <div class="flex items-center justify-between gap-4 rounded-[14px] border border-line bg-canvas px-4 py-3">
        <span class="font-semibold text-muted">طريقة الدفع</span>
        <strong data-confirm-payment class="text-ink"></strong>
      </div>
      <div class="flex items-center justify-between gap-4 rounded-[14px] border border-line bg-canvas px-4 py-3">
        <span class="font-semibold text-muted">المجموع الكلي</span>
        <strong class="text-lg font-bold text-olive"><bdi>₪1,066</bdi></strong>
      </div>
    </div>

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
      <button type="button" data-close-confirm-modal
        class="inline-flex h-12 items-center justify-center rounded-[16px] border border-line bg-canvas px-5 text-sm font-bold text-ink transition-colors hover:border-olive/40">
        رجوع للمراجعة
      </button>
      <button id="finalConfirmButton" type="button"
        class="inline-flex h-12 items-center justify-center gap-2 rounded-[16px] bg-olive px-5 text-sm font-bold text-surface shadow-sm transition-colors hover:bg-[#313923]">
        <span>تأكيد الطلب نهائياً</span>
        <i data-lucide="check-circle-2" class="h-4 w-4"></i>
      </button>
    </div>
  </div>
</div>
