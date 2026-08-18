<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="لوحة إدارة منصة اسأل قلقيلية لإدارة المتاجر، التصنيفات، الوظائف، الاشتراكات، المراجعات، والبلاغات." />
  <title>لوحة الإدارة | اسأل قلقيلية</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet" />
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F8FAFC] text-slate-900 antialiased [font-family:Tajawal,Inter,sans-serif]">
  <div class="min-h-screen lg:grid lg:grid-cols-[280px_1fr]">
    <aside class="hidden border-l border-slate-200 bg-white lg:sticky lg:top-0 lg:block lg:h-screen lg:overflow-y-auto">
      <div class="flex h-full flex-col p-5">
        <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-2xl focus:outline-none focus:ring-4 focus:ring-blue-100" aria-label="اسأل قلقيلية">
          <span class="flex size-12 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <img src="{{ asset('images/ask-qalqilya-logo.svg') }}" alt="" class="h-full w-full object-contain p-1.5" />
          </span>
          <span><span class="block text-lg font-extrabold leading-5 text-slate-950">اسأل قلقيلية</span><span class="block text-xs font-bold text-slate-500">لوحة إدارة المنصة</span></span>
        </a>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-sm font-extrabold text-slate-950">{{ auth()->user()->name }}</p>
          <p class="mt-1 text-xs font-bold text-slate-500">المتاجر · الوظائف · الاشتراكات</p>
          <span class="mt-3 inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-extrabold text-[#2563EB]">مدير المنصة</span>
        </div>

        <nav class="mt-6 grid gap-1" aria-label="تنقل لوحة الإدارة">
          <a href="{{ route('admin.dashboard') }}" aria-current="page" class="rounded-2xl bg-blue-50 px-4 py-3 text-sm font-extrabold text-[#2563EB] focus:outline-none focus:ring-4 focus:ring-blue-100">نظرة عامة</a>
          @foreach (['طلبات المتاجر', 'المتاجر المعتمدة', 'التصنيفات', 'الوظائف', 'الاشتراكات', 'المراجعات', 'المستخدمون', 'البلاغات', 'إعدادات المنصة'] as $item)
            <a href="{{ route('admin.dashboard') }}" class="rounded-2xl px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-blue-100">{{ $item }}</a>
          @endforeach
        </nav>

        <form action="{{ route('logout') }}" method="post" class="mt-auto">
          @csrf
          <button type="submit" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-center text-sm font-extrabold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-blue-100">تسجيل الخروج</button>
        </form>
      </div>
    </aside>

    <div class="min-w-0">
      <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
          <div class="flex min-w-0 items-center gap-3">
            <a href="{{ route('home') }}" class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm focus:outline-none focus:ring-4 focus:ring-blue-100" aria-label="اسأل قلقيلية">
              <img src="{{ asset('images/ask-qalqilya-logo.svg') }}" alt="" class="h-full w-full object-contain p-1.5" />
            </a>
            <div class="min-w-0"><p class="text-xs font-bold text-slate-500">إدارة اسأل قلقيلية</p><h1 class="truncate text-xl font-extrabold text-slate-950 sm:text-2xl">لوحة الإدارة</h1></div>
          </div>
          <div class="flex items-center gap-2">
            <span class="hidden rounded-full bg-blue-50 px-3 py-1.5 text-xs font-extrabold text-[#2563EB] sm:inline-flex">مدير المنصة</span>
            <a href="{{ route('home') }}" class="inline-flex items-center justify-center rounded-2xl bg-[#2563EB] px-4 py-2.5 text-sm font-extrabold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-200">عرض الموقع</a>
          </div>
        </div>
        <div class="mx-auto max-w-7xl px-4 pb-3 sm:px-6 lg:hidden">
          <a href="#admin-navigation" class="inline-flex h-11 w-full items-center justify-center rounded-2xl border border-blue-100 bg-blue-50 px-4 text-sm font-extrabold text-[#2563EB] shadow-sm focus:outline-none focus:ring-4 focus:ring-blue-100">إدارة الأقسام</a>
        </div>
      </header>

      <main class="mx-auto max-w-7xl px-4 py-6 pb-10 sm:px-6 lg:px-8 lg:py-8">
        <nav id="admin-navigation" class="mb-6 flex gap-2 overflow-x-auto pb-1 lg:hidden" aria-label="تنقل لوحة الإدارة المختصر">
          <a href="{{ route('admin.dashboard') }}" aria-current="page" class="shrink-0 rounded-full bg-blue-50 px-4 py-2 text-sm font-extrabold text-[#2563EB]">نظرة عامة</a>
          @foreach (['الطلبات', 'المتاجر', 'الوظائف', 'الاشتراكات', 'البلاغات'] as $item)
            <a href="{{ route('admin.dashboard') }}" class="shrink-0 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">{{ $item }}</a>
          @endforeach
        </nav>

        <div class="grid gap-6">
          @php($reviewTotal = $stats['pending_businesses'] + $stats['pending_jobs'] + $stats['pending_reports'])
          <section class="rounded-2xl border border-orange-200 bg-orange-50 p-5 shadow-sm sm:flex sm:items-center sm:justify-between sm:gap-5 sm:p-6" aria-labelledby="admin-alert-title">
            <div>
              <p class="text-xs font-extrabold text-[#EA580C]">تنبيه اليوم</p>
              <h2 id="admin-alert-title" class="mt-2 text-2xl font-extrabold text-slate-950">{{ $reviewTotal ? 'يوجد عناصر تحتاج مراجعة اليوم' : 'لا توجد عناصر معلقة حالياً' }}</h2>
              <div class="mt-3 flex flex-wrap gap-2 text-sm font-extrabold text-slate-700">
                <span class="rounded-full bg-white px-3 py-1.5">{{ $stats['pending_businesses'] }} طلبات متاجر</span>
                <span class="rounded-full bg-white px-3 py-1.5">{{ $stats['pending_jobs'] }} وظائف</span>
                <span class="rounded-full bg-white px-3 py-1.5">{{ $stats['pending_reports'] }} بلاغات</span>
              </div>
            </div>
            <a href="#attention" class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-2xl bg-[#EA580C] px-5 text-sm font-extrabold text-white hover:bg-orange-700 focus:outline-none focus:ring-4 focus:ring-orange-100 sm:mt-0 sm:w-auto">عرض المهام</a>
          </section>

          <section aria-labelledby="platform-stats-title">
            <div class="mb-4 flex items-center justify-between gap-3"><h2 id="platform-stats-title" class="text-xl font-extrabold text-slate-950">ملخص المنصة</h2><p class="text-sm font-bold text-slate-500">آخر تحديث: الآن</p></div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
              @foreach ([
                ['إجمالي المتاجر', $stats['businesses'], 'default'],
                ['بانتظار المراجعة', $stats['pending_businesses'], 'orange'],
                ['وظائف نشطة', $stats['active_jobs'], 'default'],
                ['مستخدمون', $stats['users'], 'default'],
                ['اشتراكات فعالة', $stats['active_subscriptions'], 'default'],
                ['مراجعات هذا الأسبوع', $stats['weekly_reviews'], 'default'],
              ] as [$label, $value, $tone])
                <article class="rounded-2xl border p-5 shadow-sm {{ $tone === 'orange' ? 'border-orange-200 bg-orange-50' : 'border-slate-200 bg-white' }}">
                  <p class="text-sm font-bold text-slate-500">{{ $label }}</p>
                  <p class="mt-2 text-3xl font-extrabold {{ $tone === 'orange' ? 'text-[#EA580C]' : 'text-slate-950' }}">{{ number_format($value) }}</p>
                </article>
              @endforeach
            </div>
          </section>

          <section id="attention" class="rounded-2xl border border-orange-200 bg-orange-50 p-5 shadow-sm sm:p-6" aria-labelledby="attention-title">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-xs font-extrabold text-[#EA580C]">أولوية الإدارة</p><h2 id="attention-title" class="mt-2 text-2xl font-extrabold text-slate-950">يحتاج إلى مراجعة</h2></div></div>
            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
              @foreach ([
                [$stats['pending_businesses'].' متاجر بانتظار الاعتماد', '#business-requests'],
                [$stats['pending_reports'].' بلاغات معلقة', '#reports'],
                [$stats['pending_jobs'].' وظائف تحتاج تدقيق', '#jobs-moderation'],
                [$stats['expiring_subscriptions'].' اشتراكات قاربت على الانتهاء', '#subscriptions'],
              ] as [$label, $target])
                <article class="rounded-2xl bg-white p-4 shadow-sm">
                  <h3 class="font-extrabold text-slate-950">{{ $label }}</h3>
                  <div class="mt-4"><a href="{{ $target }}" class="rounded-2xl bg-[#2563EB] px-3 py-2 text-sm font-extrabold text-white focus:outline-none focus:ring-4 focus:ring-blue-100">مراجعة الآن</a></div>
                </article>
              @endforeach
            </div>
          </section>

          <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <div class="grid gap-6">
              <section id="business-requests" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="approval-title">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div><h2 id="approval-title" class="text-xl font-extrabold text-slate-950">طلبات المتاجر</h2><p class="mt-1 text-sm font-bold text-slate-500">طلبات جديدة تحتاج قبولاً أو رفضاً</p><p class="mt-2 text-sm leading-6 text-slate-600">راجع بيانات النشاط قبل اعتماد الطلب.</p></div>
                  <a href="{{ route('businesses.index') }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 px-4 text-sm font-extrabold text-slate-800 hover:bg-slate-50">عرض كل المتاجر</a>
                </div>
                <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
                  <div class="hidden grid-cols-[1.2fr_1fr_0.8fr_1fr_0.8fr_0.8fr] gap-4 bg-slate-50 px-4 py-3 text-sm font-extrabold text-slate-600 lg:grid">
                    <span>اسم النشاط</span><span>التصنيف</span><span>المنطقة</span><span>صاحب النشاط</span><span>تاريخ الطلب</span><span>الحالة</span>
                  </div>
                  <div class="divide-y divide-slate-200">
                    @forelse ($pendingBusinesses as $business)
                      <article class="grid gap-4 p-4 lg:grid-cols-[1.2fr_1fr_0.8fr_1fr_0.8fr_0.8fr] lg:items-center">
                        <div><p class="text-xs font-bold text-slate-500 lg:hidden">اسم النشاط</p><p class="font-extrabold text-slate-950">{{ $business->name }}</p></div>
                        <div class="text-sm font-bold text-slate-600">{{ $business->category?->name ?? 'غير مصنف' }}</div>
                        <div class="text-sm font-bold text-slate-600">{{ $business->area?->name ?? 'غير محددة' }}</div>
                        <div class="text-sm font-bold text-slate-600">{{ $business->owner?->name ?? 'غير معروف' }}</div>
                        <div class="text-sm font-bold text-slate-600">{{ $business->created_at?->format('Y/m/d') }}</div>
                        <div><span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-extrabold text-[#EA580C]">قيد المراجعة</span></div>
                      </article>
                    @empty
                      <p class="p-6 text-center text-sm font-bold text-slate-500">لا توجد طلبات متاجر معلقة.</p>
                    @endforelse
                  </div>
                </div>
              </section>

              <section id="jobs-moderation" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="jobs-moderation-title">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div><h2 id="jobs-moderation-title" class="text-xl font-extrabold text-slate-950">مراجعة الوظائف</h2><p class="mt-1 text-sm font-bold text-slate-500">{{ $stats['active_jobs'] }} وظيفة نشطة · {{ $stats['pending_jobs'] }} بانتظار المراجعة</p></div>
                  <a href="{{ route('jobs.index') }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 px-4 text-sm font-extrabold text-slate-800 hover:bg-slate-50">عرض الوظائف</a>
                </div>
                <div class="mt-5 grid gap-3">
                  @forelse ($pendingJobs as $job)
                    <article class="rounded-2xl bg-slate-50 p-4"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="font-extrabold text-slate-950">{{ $job->title }}</h3><p class="mt-1 text-sm font-bold text-slate-500">{{ $job->business?->name ?? 'نشاط غير معروف' }} · {{ $job->area?->name ?? 'المنطقة غير محددة' }}</p></div><span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-extrabold text-[#EA580C]">قيد المراجعة</span></div></article>
                  @empty
                    <p class="rounded-2xl bg-slate-50 p-4 text-center text-sm font-bold text-slate-500">لا توجد وظائف بانتظار المراجعة.</p>
                  @endforelse
                </div>
              </section>

              <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="categories-title">
                <h2 id="categories-title" class="text-xl font-extrabold text-slate-950">إدارة التصنيفات</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                  @forelse ($categories as $category)
                    <article class="rounded-2xl border border-slate-200 p-4"><h3 class="font-extrabold text-slate-950">{{ $category->name }}</h3><p class="mt-1 text-sm font-bold text-slate-500">{{ $category->businesses_count }} نشاط · {{ $category->status === 'active' ? 'ظاهر' : 'مخفي' }}</p><a href="{{ route('categories.show', $category->slug) }}" class="mt-4 inline-flex text-sm font-extrabold text-[#2563EB]">عرض</a></article>
                  @empty
                    <p class="text-sm font-bold text-slate-500">لا توجد تصنيفات.</p>
                  @endforelse
                </div>
              </section>
            </div>

            <aside class="grid content-start gap-6">
              <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="platform-health-title">
                <img src="{{ asset('images/dashboard/analytics.jpg') }}" alt="لوحة مؤشرات وتحليلات" class="mb-4 h-28 w-full rounded-2xl object-cover" />
                <p class="inline-flex rounded-full bg-green-50 px-3 py-1 text-xs font-extrabold text-[#16A34A]">مستقرة</p>
                <h2 id="platform-health-title" class="mt-3 text-xl font-extrabold text-slate-950">حالة المنصة</h2>
                <p class="mt-3 text-sm font-extrabold text-[#16A34A]">✓ تعمل بشكل طبيعي</p><p class="mt-2 text-sm leading-6 text-slate-600">آخر مزامنة: الآن</p>
              </section>

              <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="quick-actions-title">
                <h2 id="quick-actions-title" class="text-xl font-extrabold text-slate-950">إجراءات إدارية سريعة</h2>
                <div class="mt-5 grid gap-3">
                  @foreach (['إضافة تصنيف', 'اعتماد متجر', 'إنشاء باقة', 'مراجعة البلاغات'] as $action)
                    <a href="{{ route('admin.dashboard') }}" class="inline-flex h-12 items-center justify-center rounded-2xl border border-slate-200 px-5 text-sm font-extrabold text-slate-800 hover:bg-slate-50">{{ $action }}</a>
                  @endforeach
                </div>
              </section>

              <section id="subscriptions" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="subscription-overview-title">
                <h2 id="subscription-overview-title" class="text-xl font-extrabold text-slate-950">ملخص الاشتراكات</h2>
                <dl class="mt-5 grid gap-3 text-sm">
                  <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">اشتراكات فعالة</dt><dd class="font-extrabold text-slate-950">{{ $stats['active_subscriptions'] }}</dd></div>
                  <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">قاربت على الانتهاء</dt><dd class="font-extrabold text-[#EA580C]">{{ $stats['expiring_subscriptions'] }}</dd></div>
                </dl>
              </section>

              <section id="reports" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="reviews-reports-title">
                <h2 id="reviews-reports-title" class="text-xl font-extrabold text-slate-950">المراجعات والبلاغات</h2>
                <p class="mt-1 text-sm font-bold text-slate-500">{{ $stats['pending_reports'] }} بلاغات معلقة</p>
                <div class="mt-5 grid gap-3">
                  @forelse ($pendingReports as $report)
                    <article class="rounded-2xl bg-slate-50 p-4"><p class="font-extrabold text-slate-950">{{ $report->reason }}</p><p class="mt-1 text-sm leading-6 text-slate-600">{{ $report->description ?: 'لا توجد تفاصيل إضافية.' }}</p></article>
                  @empty
                    <p class="rounded-2xl bg-slate-50 p-4 text-sm font-bold text-slate-500">لا توجد بلاغات معلقة.</p>
                  @endforelse
                </div>
              </section>

              <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="data-quality-title">
                <p class="inline-flex rounded-full bg-orange-50 px-3 py-1 text-xs font-extrabold text-[#EA580C]">تحسينات مطلوبة</p>
                <h2 id="data-quality-title" class="mt-3 text-xl font-extrabold text-slate-950">جودة بيانات المنصة</h2>
                <div class="mt-5 grid gap-3 text-sm font-bold text-slate-700">
                  <p>متاجر بدون صور: {{ $dataQuality['without_images'] }}</p>
                  <p>متاجر بدون أوقات عمل: {{ $dataQuality['without_hours'] }}</p>
                  <p>متاجر بدون موقع على الخريطة: {{ $dataQuality['without_location'] }}</p>
                  <p>وظائف بدون تاريخ انتهاء: {{ $dataQuality['jobs_without_expiry'] }}</p>
                </div>
              </section>
            </aside>
          </div>
        </div>
      </main>
    </div>
  </div>
</body>
</html>
