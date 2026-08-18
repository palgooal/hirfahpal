<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="تسجيل الدخول إلى اسأل قلقيلية لإدارة النشاطات، حفظ الأماكن، ومتابعة الوظائف." />
  <title>تسجيل الدخول | اسأل قلقيلية</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet" />
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F8FAFC] text-slate-900 antialiased [font-family:Tajawal,Inter,sans-serif]">
  <header class="border-b border-slate-200 bg-white">
    <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8" aria-label="تنقل الدخول">
      <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="اسأل قلقيلية">
        <span class="flex size-11 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <img src="{{ asset('images/ask-qalqilya-logo.svg') }}" alt="" class="h-full w-full object-contain p-1.5" />
        </span>
        <span>
          <span class="block text-lg font-extrabold leading-5 text-slate-950">اسأل قلقيلية</span>
          <span class="block text-xs font-bold text-slate-500">دليل محلي موثوق</span>
        </span>
      </a>
      <a href="{{ route('home') }}" class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-extrabold text-slate-800 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-blue-100">العودة للرئيسية</a>
    </nav>
  </header>

  <main class="mx-auto grid max-w-7xl gap-6 px-4 py-10 sm:px-6 lg:grid-cols-[0.95fr_1.05fr] lg:px-8 lg:py-16">
    <section class="order-1 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8" aria-labelledby="login-title">
      <p class="inline-flex rounded-2xl bg-blue-50 px-3 py-1.5 text-sm font-extrabold text-[#2563EB]">مرحباً بعودتك</p>
      <h1 id="login-title" class="mt-4 text-3xl font-extrabold text-slate-950">تسجيل الدخول</h1>
      <p class="mt-3 text-base leading-7 text-slate-600">ادخل إلى حسابك لإدارة نشاطك، متابعة الوظائف، أو حفظ الأماكن المفضلة.</p>

      <form class="mt-7 grid gap-5" action="{{ route('login.store') }}" method="post">
        @csrf

        @if ($errors->any())
          <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700" role="alert">{{ $errors->first() }}</div>
        @endif

        @if (session('status'))
          <div class="rounded-2xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-[#16A34A]" role="status">{{ session('status') }}</div>
        @endif

        <div>
          <label for="login-identity" class="text-sm font-extrabold text-slate-950">البريد الإلكتروني أو رقم الهاتف</label>
          <input id="login-identity" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username" class="mt-2 h-14 w-full rounded-2xl border bg-slate-50 px-4 text-base font-bold text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#2563EB] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('login') ? 'border-red-300' : 'border-slate-200' }}" />
        </div>
        <div>
          <div class="flex items-center justify-between gap-3">
            <label for="login-password" class="text-sm font-extrabold text-slate-950">كلمة المرور</label>
            <a href="{{ route('password.request') }}" class="text-sm font-extrabold text-[#2563EB] hover:text-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100">نسيت كلمة المرور؟</a>
          </div>
          <input id="login-password" name="password" type="password" required autocomplete="current-password" class="mt-2 h-14 w-full rounded-2xl border bg-slate-50 px-4 text-base font-bold text-slate-900 outline-none transition focus:border-[#2563EB] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('password') ? 'border-red-300' : 'border-slate-200' }}" />
          <p class="mt-2 text-xs font-bold text-slate-500">يجب أن تحتوي كلمة المرور على 8 أحرف على الأقل.</p>
        </div>
        <label class="flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3 text-sm font-bold text-slate-700">
          <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-4 accent-[#2563EB]" />
          تذكرني على هذا الجهاز
        </label>
        <button type="submit" class="inline-flex h-14 items-center justify-center rounded-2xl bg-[#2563EB] px-6 text-base font-extrabold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-200">تسجيل الدخول</button>
      </form>

      <div class="mt-4 grid gap-3 sm:grid-cols-2" aria-label="روابط دخول سريعة">
        <a href="{{ route('dashboard.home') }}" class="inline-flex h-12 items-center justify-center rounded-2xl border border-blue-100 bg-blue-50 px-4 text-sm font-extrabold text-[#2563EB] hover:bg-blue-100 focus:outline-none focus:ring-4 focus:ring-blue-100">دخول صاحب نشاط</a>
        <a href="{{ route('dashboard.home') }}" class="inline-flex h-12 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-extrabold text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-blue-100">دخول الإدارة</a>
      </div>

      <div class="mt-4 grid gap-2 rounded-2xl bg-slate-50 p-4 text-sm font-extrabold text-slate-700 sm:grid-cols-3">
        <span>✓ إدارة نشاطك</span><span>✓ متابعة الوظائف</span><span>✓ حفظ الأماكن المفضلة</span>
      </div>

      <div class="my-6 flex items-center gap-4" aria-hidden="true">
        <span class="h-px flex-1 bg-slate-200"></span><span class="text-sm font-extrabold text-slate-400">أو</span><span class="h-px flex-1 bg-slate-200"></span>
      </div>

      <div class="grid gap-3 sm:grid-cols-2" aria-label="خيارات تسجيل دخول مستقبلية">
        <button type="button" disabled class="inline-flex h-12 cursor-not-allowed items-center justify-center rounded-2xl border border-slate-200 bg-slate-100 px-4 text-sm font-extrabold text-slate-400">Google (قريباً)</button>
        <button type="button" disabled class="inline-flex h-12 cursor-not-allowed items-center justify-center rounded-2xl border border-slate-200 bg-slate-100 px-4 text-sm font-extrabold text-slate-400">Facebook (قريباً)</button>
      </div>

      <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4"><p class="text-sm leading-6 text-slate-700">يتم تشفير بيانات الدخول وحمايتها وفق أفضل الممارسات الأمنية.</p></div>
      <div class="mt-4 rounded-2xl border border-blue-100 bg-blue-50 p-4"><p class="text-sm leading-6 text-slate-700">بياناتك تستخدم فقط لإدارة حسابك داخل منصة اسأل قلقيلية.</p></div>

      <div class="mt-6 text-center">
        <p class="text-sm font-bold text-slate-600">لا تملك حساباً؟</p>
        <a href="{{ route('register') }}" class="mt-3 inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-extrabold text-slate-800 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-blue-100">إنشاء حساب جديد</a>
      </div>
    </section>

    <aside class="order-2 rounded-2xl border border-slate-200 bg-slate-950 p-6 text-white shadow-sm sm:p-8">
      <p class="text-sm font-extrabold text-blue-200">لماذا تدخل إلى حسابك؟</p>
      <h2 class="mt-3 text-3xl font-extrabold leading-tight">كل أدواتك المحلية في مكان واحد</h2>
      <div class="mt-6 grid gap-4">
        <div class="rounded-2xl bg-white/10 p-4"><h3 class="font-extrabold">إدارة النشاطات</h3><p class="mt-2 text-sm leading-6 text-slate-300">حدّث أوقات العمل، معلومات التواصل، الصور، والعروض.</p></div>
        <div class="rounded-2xl bg-white/10 p-4"><h3 class="font-extrabold">متابعة الوظائف</h3><p class="mt-2 text-sm leading-6 text-slate-300">احفظ الوظائف وتابع فرص العمل المناسبة داخل قلقيلية.</p></div>
        <div class="rounded-2xl bg-white/10 p-4"><h3 class="font-extrabold">حفظ الأماكن</h3><p class="mt-2 text-sm leading-6 text-slate-300">احتفظ بالمطاعم والخدمات المفضلة للوصول إليها بسرعة.</p></div>
      </div>
    </aside>
  </main>

  <footer class="border-t border-slate-200 bg-white">
    <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-6 text-sm font-bold text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
      <p>© اسأل قلقيلية</p>
      <div class="flex flex-wrap gap-3">
        <a href="{{ route('login') }}" class="hover:text-[#2563EB] focus:outline-none focus:ring-4 focus:ring-blue-100">الخصوصية</a>
        <a href="{{ route('login') }}" class="hover:text-[#2563EB] focus:outline-none focus:ring-4 focus:ring-blue-100">الشروط</a>
        <a href="{{ route('home') }}" class="hover:text-[#2563EB] focus:outline-none focus:ring-4 focus:ring-blue-100">الرئيسية</a>
      </div>
    </div>
  </footer>
</body>
</html>
