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
        {{-- Latest-order and favorites cards are deferred (10C / favorites backend); the two remaining shortcuts share one row. --}}
        <div class="grid gap-4 sm:grid-cols-2">
          <a role="link" aria-disabled="true" data-deferred-navigation="dashboard/account-details.html" class="flex min-h-28 items-center justify-between gap-4 rounded-[20px] border border-line bg-surface p-5 text-start shadow-card transition-colors hover:border-olive/40">
            <span><strong class="block text-base font-bold text-ink">تفاصيل الحساب</strong><span class="mt-1 block text-xs leading-5 text-muted">تحديث المعلومات وكلمة المرور</span></span>
            <i data-lucide="chevron-left" class="h-4 w-4 text-muted"></i>
          </a>
          <a role="link" aria-disabled="true" data-deferred-navigation="dashboard/addresses.html" class="flex min-h-28 items-center justify-between gap-4 rounded-[20px] border border-line bg-surface p-5 text-start shadow-card transition-colors hover:border-olive/40">
            <span><strong class="block text-base font-bold text-ink">العنوان</strong><span class="mt-1 block text-xs leading-5 text-muted">إدارة عناوين التوصيل</span></span>
            <i data-lucide="chevron-left" class="h-4 w-4 text-muted"></i>
          </a>
        </div>
      </div>
    </div>
  </section>
</main>
