<main data-dashboard-page>
  @include('storefront.account.page-header', [
      'current' => 'لوحة التحكم',
      'eyebrow' => 'مرحباً بعودتك، '.$customer->name,
      'title' => 'لوحة التحكم',
      'subtitle' => 'ملخص سريع لآخر طلباتك والقطع المحفوظة وإعدادات حسابك.',
  ])
  <section class="bg-canvas px-4 py-10 sm:py-12 lg:px-8">
    <div class="mx-auto grid max-w-[1216px] gap-6 lg:grid-cols-[250px_minmax(0,1fr)] lg:items-start">
      @include('storefront.account.sidebar', ['active' => 'dashboard'])
      <div class="min-w-0">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(260px,.75fr)]">
          <section class="overflow-hidden rounded-[20px] border border-line bg-surface shadow-card" aria-labelledby="latestOrderTitle">
            <div class="flex flex-col gap-4 border-b border-line px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
              <div class="text-start">
                <p class="text-xs font-bold text-copper">آخر طلب</p>
                <h2 id="latestOrderTitle" class="mt-1 text-[22px] font-semibold leading-8 text-ink"><bdi>HF-2026-0814</bdi></h2>
                <p class="mt-1 text-xs leading-5 text-muted"><bdi>2026/08/14</bdi> — <bdi>3</bdi> تجار، <bdi>6</bdi> منتجات</p>
              </div>
              <span class="w-fit rounded-full border border-copper/30 bg-copper/10 px-3 py-1.5 text-xs font-bold text-copper">
                <bdi>1</bdi> خارج للتوصيل، <bdi>1</bdi> تم التسليم، <bdi>1</bdi> مرفوض
              </span>
            </div>
            <div class="divide-y divide-line/70">
              <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <span class="flex min-w-0 items-center gap-3 text-start">
                  <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border-2 border-copper/20 bg-copper/10 text-sm font-bold text-copper">د.ك</span>
                  <strong class="truncate text-sm font-bold text-ink">دار الكرمة للخزف</strong>
                </span>
                <span class="w-fit rounded-full border border-olive/20 bg-olive/10 px-3 py-1 text-xs font-bold text-olive">خارج للتوصيل</span>
              </div>
              <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <span class="flex min-w-0 items-center gap-3 text-start">
                  <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border-2 border-sage/40 bg-sage/20 text-sm font-bold text-olive">ن.ب</span>
                  <strong class="truncate text-sm font-bold text-ink">جمعية نساء بيت لحم</strong>
                </span>
                <span class="w-fit rounded-full border border-sage/40 bg-sage/20 px-3 py-1 text-xs font-bold text-olive">تم التسليم</span>
              </div>
              <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <span class="flex min-w-0 items-center gap-3 text-start">
                  <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border-2 border-copper/20 bg-copper/10 text-sm font-bold text-copper">ن.ز</span>
                  <strong class="truncate text-sm font-bold text-ink">مشغل نور الزيتونة</strong>
                </span>
                <span class="w-fit rounded-full border border-[#A13D2B]/30 bg-[#A13D2B]/10 px-3 py-1 text-xs font-bold text-[#A13D2B]">مرفوض</span>
              </div>
            </div>
            <div class="flex flex-col gap-3 border-t border-line bg-canvas/50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
              <strong class="text-lg font-bold text-olive"><bdi>₪1,066</bdi></strong>
              <a role="link" aria-disabled="true" data-deferred-navigation="order-detail.html?id=HF-2026-0814" class="inline-flex h-10 items-center justify-center gap-2 rounded-[12px] bg-olive px-4 text-xs font-bold text-surface">
                <span>عرض تفاصيل الطلب</span><i data-lucide="chevron-left" class="h-4 w-4"></i>
              </a>
            </div>
          </section>

          <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <a href="{{ route('customer.favorites') }}" class="flex min-h-32 items-center justify-between gap-4 rounded-[20px] border border-line bg-surface p-5 text-start shadow-card transition-colors hover:border-olive/40">
              <span>
                <span class="block text-sm font-semibold text-muted">المفضلة</span>
                <strong class="mt-2 block text-3xl font-bold text-ink"><bdi>5</bdi></strong>
                <span class="mt-1 block text-xs text-muted">قطع محفوظة</span>
              </span>
              <span class="flex h-12 w-12 items-center justify-center rounded-full bg-copper/10 text-copper"><i data-lucide="heart" class="h-5 w-5"></i></span>
            </a>
            <a href="{{ route('customer.account-details') }}" class="flex min-h-28 items-center justify-between gap-4 rounded-[20px] border border-line bg-surface p-5 text-start shadow-card transition-colors hover:border-olive/40">
              <span><strong class="block text-base font-bold text-ink">تفاصيل الحساب</strong><span class="mt-1 block text-xs leading-5 text-muted">تحديث المعلومات وكلمة المرور</span></span>
              <i data-lucide="chevron-left" class="h-4 w-4 text-muted"></i>
            </a>
            <a href="{{ route('customer.addresses') }}" class="flex min-h-28 items-center justify-between gap-4 rounded-[20px] border border-line bg-surface p-5 text-start shadow-card transition-colors hover:border-olive/40">
              <span><strong class="block text-base font-bold text-ink">العنوان</strong><span class="mt-1 block text-xs leading-5 text-muted">إدارة عناوين التوصيل</span></span>
              <i data-lucide="chevron-left" class="h-4 w-4 text-muted"></i>
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>
