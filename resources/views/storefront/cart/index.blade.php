<main data-cart-page>
  <section class="border-b border-line/60 bg-canvas px-4 py-6 lg:px-8">
    <div class="mx-auto flex max-w-[1216px] flex-col gap-5">
      <nav class="flex items-center gap-2 text-xs font-medium leading-4 text-muted sm:text-sm"
        aria-label="مسار الصفحة">
        <a href="{{ route('home') }}" class="hover:text-olive">الرئيسية</a>
        <span class="text-line">/</span>
        <span class="font-semibold text-olive">سلة المشتريات</span>
      </nav>
      <div class="flex max-w-3xl flex-col items-start gap-2 text-start">
        <p class="flex items-center gap-1.5 text-xs font-bold uppercase leading-4 tracking-[.6px] text-copper"><span
            class="h-1.5 w-1.5 rounded-full bg-copper"></span>مراجعة الطلب قبل الدفع</p>
        <h1 class="text-[30px] font-bold leading-9 text-ink sm:text-4xl sm:leading-[48px]">سلة المشتريات</h1>
        <p class="text-sm leading-6 text-muted sm:text-base">المنتجات هنا مرتبة حسب التاجر حتى تراجع كل مجموعة
          فرعية بوضوح قبل إتمام الطلب.</p>
      </div>
    </div>
  </section>

  <section class="bg-canvas px-4 py-10 sm:py-12 lg:px-8">
    <div
      class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 mx-auto flex max-w-[1216px] flex-col gap-7">
      <div id="cartPopulatedState" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
        <div class="flex min-w-0 flex-col gap-5" data-cart-vendor-groups>
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
              <article class="cart-line-item grid gap-4 px-4 py-5 sm:grid-cols-[96px_minmax(0,1fr)] sm:px-5 lg:grid-cols-[104px_minmax(0,1fr)_150px]"
                data-unit-price="145" data-max-quantity="3">
                <a role="link" aria-disabled="true" data-deferred-navigation="product.html" class="block h-24 w-24 overflow-hidden rounded-[16px] border border-line bg-canvas sm:h-24 sm:w-24 lg:h-[104px] lg:w-[104px]">
                  <img src="{{ asset('assets/storefront/imgs/mcp/arrival-pitcher.png') }}" alt="إبريق فخار مقدسي مزخرف"
                    class="h-full w-full object-cover">
                </a>
                <div class="min-w-0 text-start">
                  <h2 class="text-base font-bold leading-6 text-ink">إبريق فخار مقدسي مزخرف</h2>
                  <p class="mt-1 text-xs leading-5 text-muted">قطعة تقديم مرسومة يدوياً من خزف الخليل.</p>
                  <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:max-w-[420px]">
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">سعر القطعة</span>
                      <strong class="block text-sm font-bold leading-6 text-ink"><bdi>₪145</bdi></strong>
                    </div>
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">الكمية</span>
                      <div class="mt-1 flex h-[42px] w-fit items-center overflow-hidden rounded-xl border border-line bg-surface shadow-sm">
                        <button type="button" disabled
                          class="cart-qty-decrease flex h-full w-10 items-center justify-center text-muted transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40"
                          aria-label="إنقاص الكمية"><i data-lucide="minus" class="h-4 w-4"></i></button>
                        <bdi class="cart-qty flex h-full min-w-10 items-center justify-center border-s border-e border-line px-3 text-sm font-bold text-ink">1</bdi>
                        <button type="button"
                          class="cart-qty-increase flex h-full w-10 items-center justify-center text-olive transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40"
                          aria-label="زيادة الكمية"><i data-lucide="plus" class="h-4 w-4"></i></button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-line/60 pt-4 sm:col-start-2 lg:col-start-auto lg:flex-col lg:items-end lg:border-t-0 lg:pt-0">
                  <div class="text-start lg:text-end">
                    <span class="block text-[11px] font-semibold leading-4 text-muted">إجمالي السطر</span>
                    <strong class="block text-lg font-bold leading-7 text-olive" data-line-total><bdi>₪145</bdi></strong>
                  </div>
                  <button type="button"
                    class="cart-remove-item inline-flex h-10 w-10 items-center justify-center rounded-full border border-line bg-canvas text-muted transition-colors hover:border-copper/40 hover:text-copper"
                    aria-label="إزالة إبريق فخار مقدسي مزخرف"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                </div>
              </article>

              <article class="cart-line-item grid gap-4 px-4 py-5 sm:grid-cols-[96px_minmax(0,1fr)] sm:px-5 lg:grid-cols-[104px_minmax(0,1fr)_150px]"
                data-unit-price="135" data-max-quantity="5">
                <a role="link" aria-disabled="true" data-deferred-navigation="product.html" class="block h-24 w-24 overflow-hidden rounded-[16px] border border-line bg-canvas sm:h-24 sm:w-24 lg:h-[104px] lg:w-[104px]">
                  <img src="{{ asset('assets/storefront/imgs/mcp/category-ceramics.png') }}" alt="طبق تقديم خزفي أزرق"
                    class="h-full w-full object-cover">
                </a>
                <div class="min-w-0 text-start">
                  <h2 class="text-base font-bold leading-6 text-ink">طبق تقديم خزفي أزرق</h2>
                  <p class="mt-1 text-xs leading-5 text-muted">طبق متوسط بزخرفة نباتية هادئة للمائدة اليومية.</p>
                  <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:max-w-[420px]">
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">سعر القطعة</span>
                      <strong class="block text-sm font-bold leading-6 text-ink"><bdi>₪135</bdi></strong>
                    </div>
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">الكمية</span>
                      <div class="mt-1 flex h-[42px] w-fit items-center overflow-hidden rounded-xl border border-line bg-surface shadow-sm">
                        <button type="button"
                          class="cart-qty-decrease flex h-full w-10 items-center justify-center text-muted transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40"
                          aria-label="إنقاص الكمية"><i data-lucide="minus" class="h-4 w-4"></i></button>
                        <bdi class="cart-qty flex h-full min-w-10 items-center justify-center border-s border-e border-line px-3 text-sm font-bold text-ink">2</bdi>
                        <button type="button"
                          class="cart-qty-increase flex h-full w-10 items-center justify-center text-olive transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40"
                          aria-label="زيادة الكمية"><i data-lucide="plus" class="h-4 w-4"></i></button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-line/60 pt-4 sm:col-start-2 lg:col-start-auto lg:flex-col lg:items-end lg:border-t-0 lg:pt-0">
                  <div class="text-start lg:text-end">
                    <span class="block text-[11px] font-semibold leading-4 text-muted">إجمالي السطر</span>
                    <strong class="block text-lg font-bold leading-7 text-olive" data-line-total><bdi>₪270</bdi></strong>
                  </div>
                  <button type="button"
                    class="cart-remove-item inline-flex h-10 w-10 items-center justify-center rounded-full border border-line bg-canvas text-muted transition-colors hover:border-copper/40 hover:text-copper"
                    aria-label="إزالة طبق تقديم خزفي أزرق"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                </div>
              </article>
            </div>

            <footer class="flex items-center justify-between gap-4 border-t border-line bg-canvas px-4 py-4 text-start sm:px-5">
              <span class="text-sm font-semibold leading-6 text-muted">المجموع الفرعي</span>
              <strong class="text-xl font-bold leading-8 text-ink" data-vendor-subtotal><bdi>₪415</bdi></strong>
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
              <span class="w-fit rounded-full border border-sage/40 bg-sage/20 px-3 py-1 text-xs font-bold text-olive">مجموعة
                تاجر مستقلة</span>
            </header>

            <div class="divide-y divide-line/70">
              <article class="cart-line-item grid gap-4 px-4 py-5 sm:grid-cols-[96px_minmax(0,1fr)] sm:px-5 lg:grid-cols-[104px_minmax(0,1fr)_150px]"
                data-unit-price="220" data-max-quantity="4">
                <a role="link" aria-disabled="true" data-deferred-navigation="product.html" class="block h-24 w-24 overflow-hidden rounded-[16px] border border-line bg-canvas sm:h-24 sm:w-24 lg:h-[104px] lg:w-[104px]">
                  <img src="{{ asset('assets/storefront/imgs/mcp/arrival-cushion.png') }}" alt="وسادة تطريز فلاحي كنعاني"
                    class="h-full w-full object-cover">
                </a>
                <div class="min-w-0 text-start">
                  <h2 class="text-base font-bold leading-6 text-ink">وسادة تطريز فلاحي كنعاني</h2>
                  <p class="mt-1 text-xs leading-5 text-muted">قماش كتان مطرز بغرزة الفلاحي ونقوش النجمة.</p>
                  <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:max-w-[420px]">
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">سعر القطعة</span>
                      <strong class="block text-sm font-bold leading-6 text-ink"><bdi>₪220</bdi></strong>
                    </div>
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">الكمية</span>
                      <div class="mt-1 flex h-[42px] w-fit items-center overflow-hidden rounded-xl border border-line bg-surface shadow-sm">
                        <button type="button" disabled
                          class="cart-qty-decrease flex h-full w-10 items-center justify-center text-muted transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40"
                          aria-label="إنقاص الكمية"><i data-lucide="minus" class="h-4 w-4"></i></button>
                        <bdi class="cart-qty flex h-full min-w-10 items-center justify-center border-s border-e border-line px-3 text-sm font-bold text-ink">1</bdi>
                        <button type="button"
                          class="cart-qty-increase flex h-full w-10 items-center justify-center text-olive transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40"
                          aria-label="زيادة الكمية"><i data-lucide="plus" class="h-4 w-4"></i></button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-line/60 pt-4 sm:col-start-2 lg:col-start-auto lg:flex-col lg:items-end lg:border-t-0 lg:pt-0">
                  <div class="text-start lg:text-end">
                    <span class="block text-[11px] font-semibold leading-4 text-muted">إجمالي السطر</span>
                    <strong class="block text-lg font-bold leading-7 text-olive" data-line-total><bdi>₪220</bdi></strong>
                  </div>
                  <button type="button"
                    class="cart-remove-item inline-flex h-10 w-10 items-center justify-center rounded-full border border-line bg-canvas text-muted transition-colors hover:border-copper/40 hover:text-copper"
                    aria-label="إزالة وسادة تطريز فلاحي كنعاني"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                </div>
              </article>

              <article class="cart-line-item grid gap-4 px-4 py-5 sm:grid-cols-[96px_minmax(0,1fr)] sm:px-5 lg:grid-cols-[104px_minmax(0,1fr)_150px]"
                data-unit-price="180" data-max-quantity="3">
                <a role="link" aria-disabled="true" data-deferred-navigation="product.html" class="block h-24 w-24 overflow-hidden rounded-[16px] border border-line bg-canvas sm:h-24 sm:w-24 lg:h-[104px] lg:w-[104px]">
                  <img src="{{ asset('assets/storefront/imgs/mcp/category-embroidery.png') }}" alt="شال مطرز بخيوط قطنية"
                    class="h-full w-full object-cover">
                </a>
                <div class="min-w-0 text-start">
                  <h2 class="text-base font-bold leading-6 text-ink">شال مطرز بخيوط قطنية</h2>
                  <p class="mt-1 text-xs leading-5 text-muted">شال خفيف بنقشة ريفية مناسبة للهدايا اليومية.</p>
                  <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:max-w-[420px]">
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">سعر القطعة</span>
                      <strong class="block text-sm font-bold leading-6 text-ink"><bdi>₪180</bdi></strong>
                    </div>
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">الكمية</span>
                      <div class="mt-1 flex h-[42px] w-fit items-center overflow-hidden rounded-xl border border-line bg-surface shadow-sm">
                        <button type="button" disabled
                          class="cart-qty-decrease flex h-full w-10 items-center justify-center text-muted transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40"
                          aria-label="إنقاص الكمية"><i data-lucide="minus" class="h-4 w-4"></i></button>
                        <bdi class="cart-qty flex h-full min-w-10 items-center justify-center border-s border-e border-line px-3 text-sm font-bold text-ink">1</bdi>
                        <button type="button"
                          class="cart-qty-increase flex h-full w-10 items-center justify-center text-olive transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40"
                          aria-label="زيادة الكمية"><i data-lucide="plus" class="h-4 w-4"></i></button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-line/60 pt-4 sm:col-start-2 lg:col-start-auto lg:flex-col lg:items-end lg:border-t-0 lg:pt-0">
                  <div class="text-start lg:text-end">
                    <span class="block text-[11px] font-semibold leading-4 text-muted">إجمالي السطر</span>
                    <strong class="block text-lg font-bold leading-7 text-olive" data-line-total><bdi>₪180</bdi></strong>
                  </div>
                  <button type="button"
                    class="cart-remove-item inline-flex h-10 w-10 items-center justify-center rounded-full border border-line bg-canvas text-muted transition-colors hover:border-copper/40 hover:text-copper"
                    aria-label="إزالة شال مطرز بخيوط قطنية"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                </div>
              </article>
            </div>

            <footer class="flex items-center justify-between gap-4 border-t border-line bg-canvas px-4 py-4 text-start sm:px-5">
              <span class="text-sm font-semibold leading-6 text-muted">المجموع الفرعي</span>
              <strong class="text-xl font-bold leading-8 text-ink" data-vendor-subtotal><bdi>₪400</bdi></strong>
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
              <article class="cart-line-item grid gap-4 px-4 py-5 sm:grid-cols-[96px_minmax(0,1fr)] sm:px-5 lg:grid-cols-[104px_minmax(0,1fr)_150px]"
                data-unit-price="68" data-max-quantity="5">
                <a role="link" aria-disabled="true" data-deferred-navigation="product.html" class="block h-24 w-24 overflow-hidden rounded-[16px] border border-line bg-canvas sm:h-24 sm:w-24 lg:h-[104px] lg:w-[104px]">
                  <img src="{{ asset('assets/storefront/imgs/mcp/arrival-candle.png') }}" alt="شمعة صويا وزيت زيتون"
                    class="h-full w-full object-cover">
                </a>
                <div class="min-w-0 text-start">
                  <h2 class="text-base font-bold leading-6 text-ink">شمعة صويا وزيت زيتون</h2>
                  <p class="mt-1 text-xs leading-5 text-muted">شمعة في وعاء فخاري صغير بخيط خشبي آمن.</p>
                  <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:max-w-[420px]">
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">سعر القطعة</span>
                      <strong class="block text-sm font-bold leading-6 text-ink"><bdi>₪68</bdi></strong>
                    </div>
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">الكمية</span>
                      <div class="mt-1 flex h-[42px] w-fit items-center overflow-hidden rounded-xl border border-line bg-surface shadow-sm">
                        <button type="button"
                          class="cart-qty-decrease flex h-full w-10 items-center justify-center text-muted transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40"
                          aria-label="إنقاص الكمية"><i data-lucide="minus" class="h-4 w-4"></i></button>
                        <bdi class="cart-qty flex h-full min-w-10 items-center justify-center border-s border-e border-line px-3 text-sm font-bold text-ink">2</bdi>
                        <button type="button"
                          class="cart-qty-increase flex h-full w-10 items-center justify-center text-olive transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40"
                          aria-label="زيادة الكمية"><i data-lucide="plus" class="h-4 w-4"></i></button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-line/60 pt-4 sm:col-start-2 lg:col-start-auto lg:flex-col lg:items-end lg:border-t-0 lg:pt-0">
                  <div class="text-start lg:text-end">
                    <span class="block text-[11px] font-semibold leading-4 text-muted">إجمالي السطر</span>
                    <strong class="block text-lg font-bold leading-7 text-olive" data-line-total><bdi>₪136</bdi></strong>
                  </div>
                  <button type="button"
                    class="cart-remove-item inline-flex h-10 w-10 items-center justify-center rounded-full border border-line bg-canvas text-muted transition-colors hover:border-copper/40 hover:text-copper"
                    aria-label="إزالة شمعة صويا وزيت زيتون"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                </div>
              </article>

              <article class="cart-line-item grid gap-4 px-4 py-5 sm:grid-cols-[96px_minmax(0,1fr)] sm:px-5 lg:grid-cols-[104px_minmax(0,1fr)_150px]"
                data-unit-price="60" data-max-quantity="5">
                <a role="link" aria-disabled="true" data-deferred-navigation="product.html" class="block h-24 w-24 overflow-hidden rounded-[16px] border border-line bg-canvas sm:h-24 sm:w-24 lg:h-[104px] lg:w-[104px]">
                  <img src="{{ asset('assets/storefront/imgs/mcp/category-candle.png') }}" alt="مجموعة شموع زيت زيتون صغيرة"
                    class="h-full w-full object-cover">
                </a>
                <div class="min-w-0 text-start">
                  <h2 class="text-base font-bold leading-6 text-ink">مجموعة شموع زيت زيتون صغيرة</h2>
                  <p class="mt-1 text-xs leading-5 text-muted">ثلاث شموع بروائح هادئة للحجرات الصغيرة والهدايا.</p>
                  <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:max-w-[420px]">
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">سعر المجموعة</span>
                      <strong class="block text-sm font-bold leading-6 text-ink"><bdi>₪60</bdi></strong>
                    </div>
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">الكمية</span>
                      <div class="mt-1 flex h-[42px] w-fit items-center overflow-hidden rounded-xl border border-line bg-surface shadow-sm">
                        <button type="button" disabled
                          class="cart-qty-decrease flex h-full w-10 items-center justify-center text-muted transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40"
                          aria-label="إنقاص الكمية"><i data-lucide="minus" class="h-4 w-4"></i></button>
                        <bdi class="cart-qty flex h-full min-w-10 items-center justify-center border-s border-e border-line px-3 text-sm font-bold text-ink">1</bdi>
                        <button type="button"
                          class="cart-qty-increase flex h-full w-10 items-center justify-center text-olive transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40"
                          aria-label="زيادة الكمية"><i data-lucide="plus" class="h-4 w-4"></i></button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-line/60 pt-4 sm:col-start-2 lg:col-start-auto lg:flex-col lg:items-end lg:border-t-0 lg:pt-0">
                  <div class="text-start lg:text-end">
                    <span class="block text-[11px] font-semibold leading-4 text-muted">إجمالي السطر</span>
                    <strong class="block text-lg font-bold leading-7 text-olive" data-line-total><bdi>₪60</bdi></strong>
                  </div>
                  <button type="button"
                    class="cart-remove-item inline-flex h-10 w-10 items-center justify-center rounded-full border border-line bg-canvas text-muted transition-colors hover:border-copper/40 hover:text-copper"
                    aria-label="إزالة مجموعة شموع زيت زيتون صغيرة"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                </div>
              </article>
            </div>

            <footer class="flex items-center justify-between gap-4 border-t border-line bg-canvas px-4 py-4 text-start sm:px-5">
              <span class="text-sm font-semibold leading-6 text-muted">المجموع الفرعي</span>
              <strong class="text-xl font-bold leading-8 text-ink" data-vendor-subtotal><bdi>₪196</bdi></strong>
            </footer>
          </article>
        </div>

        <aside class="rounded-[20px] border border-line bg-surface p-5 shadow-card lg:sticky lg:top-24">
          <div class="flex items-center justify-between gap-4 border-b border-line pb-4">
            <div class="text-start">
              <h2 class="text-[22px] font-semibold leading-8 text-ink">ملخص السلة</h2>
              <p class="text-xs leading-5 text-muted"><span data-cart-line-count><bdi>6</bdi></span> منتجات من
                <span data-cart-vendor-count><bdi>3</bdi></span> متاجر</p>
            </div>
            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-copper/10 text-copper">
              <i data-lucide="shopping-cart" class="h-5 w-5"></i>
            </span>
          </div>

          <div class="grid gap-3 py-5 text-sm">
            <div class="flex items-center justify-between gap-4 text-muted">
              <span>مجموع المنتجات</span>
              <strong class="font-bold text-ink" data-grand-total><bdi>₪1,011</bdi></strong>
            </div>
            <div class="rounded-[16px] border border-line bg-canvas p-4 text-start">
              <p class="text-xs font-semibold leading-5 text-muted">قبل تكلفة التوصيل</p>
              <p class="mt-1 text-sm leading-6 text-ink">التوصيل يُحسب لاحقاً لكل تاجر عند إتمام الطلب.</p>
            </div>
          </div>

          <div class="border-t border-line pt-5">
            <div class="flex items-end justify-between gap-4">
              <span class="text-base font-bold leading-7 text-ink">المجموع الكلي</span>
              <strong class="text-2xl font-bold leading-9 text-olive" data-grand-total-display><bdi>₪1,011</bdi></strong>
            </div>
            <a href="{{ route('customer.checkout.show') }}"
              class="mt-5 inline-flex h-12 w-full items-center justify-center gap-2 rounded-[16px] bg-olive px-5 text-sm font-bold text-surface shadow-sm transition-colors hover:bg-[#313923]">
              <span>متابعة إلى الدفع</span>
              <i data-lucide="credit-card" class="h-4 w-4"></i>
            </a>
          </div>
        </aside>
      </div>

      <div id="cartEmptyState"
        class="hidden rounded-[20px] border border-line bg-surface p-8 text-center shadow-card sm:p-10">
        <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-sage/20 text-olive">
          <i data-lucide="shopping-bag" class="h-8 w-8"></i>
        </span>
        <h2 class="mt-4 text-[22px] font-semibold leading-8 text-ink">سلتك فارغة حالياً</h2>
        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-muted">ابدأ من التصنيفات لاختيار قطع يدوية من مشاغل
          فلسطينية موثقة.</p>
        <a href="{{ route('categories') }}"
          class="mt-6 inline-flex items-center justify-center gap-2 rounded-[16px] bg-olive px-5 py-3 text-sm font-bold text-surface shadow-sm transition-colors hover:bg-[#313923]">
          <i data-lucide="grid-3x3" class="h-4 w-4"></i>
          <span>تصفح التصنيفات</span>
        </a>
      </div>
    </div>
  </section>
</main>
