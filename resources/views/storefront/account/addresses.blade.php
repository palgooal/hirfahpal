<main data-profile-page>
  @include('storefront.account.page-header', [
      'current' => 'العنوان',
      'eyebrow' => 'عناوين التوصيل المحفوظة',
      'title' => 'العنوان',
      'subtitle' => 'أضف عناوين التوصيل أو عدّلها لتظهر لك عند إتمام الطلب.',
  ])
  <section class="bg-canvas px-4 py-10 sm:py-12 lg:px-8">
    <div class="mx-auto grid max-w-[1216px] gap-6 lg:grid-cols-[250px_minmax(0,1fr)] lg:items-start">
      @include('storefront.account.sidebar', ['active' => 'addresses'])
      <div class="min-w-0 flex flex-col gap-6">
        <!-- Saved addresses -->
        <section class="rounded-[20px] border border-line bg-surface p-5 shadow-card sm:p-6" aria-labelledby="addressesTitle">
          <div class="flex items-center justify-between gap-4 border-b border-line pb-5">
            <div class="text-start">
              <h2 id="addressesTitle" class="text-[22px] font-semibold leading-8 text-ink">عناوين التوصيل</h2>
              <p class="mt-1 text-sm leading-6 text-muted">إدارة العناوين المحفوظة التي تظهر عند إتمام الطلب.</p>
            </div>
            <button type="button" data-open-address-modal
              class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-[12px] border border-line bg-canvas px-4 text-xs font-bold text-olive transition-colors hover:border-olive/50">
              <i data-lucide="plus" class="h-4 w-4"></i>
              <span>إضافة عنوان</span>
            </button>
          </div>

          <div id="addressList" class="mt-5 grid gap-4 sm:grid-cols-2">
            <article class="address-list-card flex flex-col gap-3 rounded-[16px] border border-line bg-canvas p-4 text-start"
              data-address-id="home">
              <div class="flex items-start justify-between gap-3">
                <span class="flex items-center gap-2">
                  <span class="flex h-9 w-9 items-center justify-center rounded-full bg-olive/10 text-olive"><i
                      data-lucide="home" class="h-4 w-4"></i></span>
                  <strong class="text-sm font-bold leading-6 text-ink">المنزل</strong>
                </span>
                <span class="rounded-full border border-sage/40 bg-sage/20 px-2.5 py-0.5 text-[11px] font-bold text-olive">افتراضي</span>
              </div>
              <p class="text-sm leading-6 text-muted">ليان خليل<br>رام الله، حي الطيرة، قرب دوار الساعة<br><bdi>0599123456</bdi></p>
              <div class="mt-1 flex items-center gap-3 border-t border-line/70 pt-3 text-xs font-bold">
                <button type="button" data-edit-address class="text-olive underline underline-offset-2">تعديل</button>
                <button type="button" data-remove-address class="text-[#A13D2B] underline underline-offset-2">حذف</button>
              </div>
            </article>

            <article class="address-list-card flex flex-col gap-3 rounded-[16px] border border-line bg-canvas p-4 text-start"
              data-address-id="work">
              <div class="flex items-start justify-between gap-3">
                <span class="flex items-center gap-2">
                  <span class="flex h-9 w-9 items-center justify-center rounded-full bg-copper/10 text-copper"><i
                      data-lucide="building-2" class="h-4 w-4"></i></span>
                  <strong class="text-sm font-bold leading-6 text-ink">العمل</strong>
                </span>
              </div>
              <p class="text-sm leading-6 text-muted">ليان خليل<br>القدس، شارع صلاح الدين، الطابق <bdi>2</bdi><br><bdi>0599123456</bdi></p>
              <div class="mt-1 flex items-center gap-3 border-t border-line/70 pt-3 text-xs font-bold">
                <button type="button" data-edit-address class="text-olive underline underline-offset-2">تعديل</button>
                <button type="button" data-remove-address class="text-[#A13D2B] underline underline-offset-2">حذف</button>
              </div>
            </article>
          </div>

          <div data-address-empty-state class="mt-5 hidden flex-col items-center gap-3 rounded-[16px] border border-dashed border-line bg-canvas px-4 py-10 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-surface text-muted shadow-card">
              <i data-lucide="map-pin-off" class="h-6 w-6"></i>
            </span>
            <h3 class="text-base font-bold leading-6 text-ink">لا يوجد عناوين محفوظة</h3>
            <p class="max-w-xs text-sm leading-6 text-muted">أضف عنوان توصيل ليسهل عليك إتمام طلباتك القادمة.</p>
            <button type="button" data-open-address-modal
              class="mt-1 inline-flex h-10 items-center justify-center gap-2 rounded-[12px] bg-sage px-4 text-xs font-bold text-ink shadow-sm">
              <span>إضافة عنوان جديد</span>
            </button>
          </div>
        </section>
      </div>
    </div>
  </section>
</main>

{{-- Address add/edit modal: open/close only (storefront-addresses.js); saving is deferred. --}}
<div id="addressModal" class="fixed start-0 end-0 top-0 bottom-0 z-[90] hidden bg-ink/50 p-4 backdrop-blur-sm"
  role="dialog" aria-modal="true" aria-labelledby="addressModalTitle">
  <div class="mx-auto mt-16 max-w-lg rounded-[20px] border border-line bg-surface p-5 shadow-soft sm:p-6">
    <div class="flex items-start justify-between gap-4 border-b border-line pb-4">
      <h2 id="addressModalTitle" class="text-lg font-bold leading-7 text-ink">إضافة عنوان جديد</h2>
      <button type="button" data-close-address-modal
        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-line bg-canvas text-muted hover:text-ink"
        aria-label="إغلاق"><i data-lucide="x" class="h-4 w-4"></i></button>
    </div>
    <form id="addressForm" class="mt-5 grid gap-4 sm:grid-cols-2" novalidate>
      <label class="text-start">
        <span class="mb-2 block text-sm font-semibold leading-5 text-ink">اسم العنوان</span>
        <input type="text" name="addressLabel" required placeholder="المنزل، العمل..."
          class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive">
      </label>
      <label class="text-start">
        <span class="mb-2 block text-sm font-semibold leading-5 text-ink">رقم الهاتف</span>
        <input type="tel" name="addressPhone" required placeholder="05xxxxxxxx"
          class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive">
      </label>
      <label class="text-start sm:col-span-2">
        <span class="mb-2 block text-sm font-semibold leading-5 text-ink">المحافظة</span>
        <select name="addressCity"
          class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive">
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
        <textarea name="addressDetails" rows="3" required
          class="w-full rounded-[14px] border border-line bg-canvas px-4 py-3 text-sm leading-6 outline-none transition-colors focus:border-olive"
          placeholder="الحي، الشارع، أقرب علامة واضحة"></textarea>
      </label>
      <div class="flex items-center justify-end gap-3 sm:col-span-2">
        <button type="button" data-close-address-modal
          class="inline-flex h-11 items-center justify-center rounded-[14px] border border-line bg-canvas px-5 text-sm font-bold text-ink">تراجع</button>
        <button type="button"
          class="inline-flex h-11 items-center justify-center gap-2 rounded-[14px] bg-olive px-5 text-sm font-bold text-surface shadow-sm hover:bg-[#313923]">
          <span>حفظ العنوان</span>
        </button>
      </div>
    </form>
  </div>
</div>
