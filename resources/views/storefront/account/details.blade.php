<main data-profile-page>
  @include('storefront.account.page-header', [
      'current' => 'تفاصيل الحساب',
      'eyebrow' => 'معلوماتك وأمان حسابك',
      'title' => 'تفاصيل الحساب',
      'subtitle' => 'حدّث معلوماتك الشخصية أو غيّر كلمة المرور من مكان واحد.',
  ])
  <section class="bg-canvas px-4 py-10 sm:py-12 lg:px-8">
    <div class="mx-auto grid max-w-[1216px] gap-6 lg:grid-cols-[250px_minmax(0,1fr)] lg:items-start">
      @include('storefront.account.sidebar', ['active' => 'account'])
      <div class="min-w-0 flex flex-col gap-6">
        <!-- Personal information -->
        <section class="rounded-[20px] border border-line bg-surface p-5 shadow-card sm:p-6" aria-labelledby="personalInfoTitle">
          <div class="flex items-center justify-between gap-4 border-b border-line pb-5">
            <div class="text-start">
              <h2 id="personalInfoTitle" class="text-[22px] font-semibold leading-8 text-ink">المعلومات الشخصية</h2>
              <p class="mt-1 text-sm leading-6 text-muted">اسمك وبريدك ورقم جوالك كما تظهر في طلباتك.</p>
            </div>
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-olive/10 text-olive">
              <i data-lucide="user" class="h-5 w-5"></i>
            </span>
          </div>
          <form id="personalInfoForm" class="mt-5 grid gap-4 sm:grid-cols-2" novalidate>
            <label class="text-start">
              <span class="mb-2 block text-sm font-semibold leading-5 text-ink">الاسم الكامل</span>
              <input type="text" name="fullName" value="{{ $customer->name }}" required
                class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive">
            </label>
            <label class="text-start">
              <span class="mb-2 block text-sm font-semibold leading-5 text-ink">البريد الإلكتروني</span>
              <input type="email" name="email" value="{{ $customer->email ?? '' }}" required
                class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive">
            </label>
            <label class="text-start sm:col-span-2">
              <span class="mb-2 block text-sm font-semibold leading-5 text-ink">رقم الجوال</span>
              <input type="tel" name="phone" value="{{ $customer->phone }}" required
                class="h-12 w-full max-w-sm rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive">
            </label>
            <div class="sm:col-span-2">
              <button type="button"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-[14px] bg-olive px-5 text-sm font-bold text-surface shadow-sm transition-colors hover:bg-[#313923]">
                <i data-lucide="check" class="h-4 w-4"></i>
                <span>حفظ التغييرات</span>
              </button>
            </div>
          </form>
        </section>

        <!-- Change password -->
        <section class="rounded-[20px] border border-line bg-surface p-5 shadow-card sm:p-6" aria-labelledby="passwordTitle">
          <div class="flex items-center justify-between gap-4 border-b border-line pb-5">
            <div class="text-start">
              <h2 id="passwordTitle" class="text-[22px] font-semibold leading-8 text-ink">تغيير كلمة المرور</h2>
              <p class="mt-1 text-sm leading-6 text-muted">استخدم كلمة مرور قوية لا تشاركها مع أحد.</p>
            </div>
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-copper/10 text-copper">
              <i data-lucide="lock" class="h-5 w-5"></i>
            </span>
          </div>
          <form id="passwordForm" class="mt-5 grid gap-4 sm:grid-cols-2" novalidate>
            <label class="text-start sm:col-span-2">
              <span class="mb-2 block text-sm font-semibold leading-5 text-ink">كلمة المرور الحالية</span>
              <input type="password" name="currentPassword" required autocomplete="current-password"
                class="h-12 w-full max-w-sm rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive">
            </label>
            <label class="text-start">
              <span class="mb-2 block text-sm font-semibold leading-5 text-ink">كلمة المرور الجديدة</span>
              <input type="password" name="newPassword" required autocomplete="new-password"
                class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive">
            </label>
            <label class="text-start">
              <span class="mb-2 block text-sm font-semibold leading-5 text-ink">تأكيد كلمة المرور الجديدة</span>
              <input type="password" name="confirmPassword" required autocomplete="new-password"
                class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive">
            </label>
            <p data-password-error class="hidden sm:col-span-2 rounded-[12px] border border-[#A13D2B]/30 bg-[#A13D2B]/10 px-4 py-3 text-start text-sm font-semibold text-[#A13D2B]"
              role="alert"></p>
            <div class="sm:col-span-2">
              <button type="button"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-[14px] bg-olive px-5 text-sm font-bold text-surface shadow-sm transition-colors hover:bg-[#313923]">
                <i data-lucide="shield-check" class="h-4 w-4"></i>
                <span>تحديث كلمة المرور</span>
              </button>
            </div>
          </form>
        </section>
      </div>
    </div>
  </section>
</main>
