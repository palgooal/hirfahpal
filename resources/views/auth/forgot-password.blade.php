<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="استعادة كلمة المرور لحسابك في اسأل قلقيلية." />
  <title>نسيت كلمة المرور؟ | اسأل قلقيلية</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet" />
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F8FAFC] text-slate-900 antialiased [font-family:Tajawal,Inter,sans-serif]">
  <header class="border-b border-slate-200 bg-white">
    <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8" aria-label="تنقل استعادة كلمة المرور">
      <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="اسأل قلقيلية">
        <span class="flex size-11 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <img src="{{ asset('images/ask-qalqilya-logo.svg') }}" alt="" class="h-full w-full object-contain p-1.5" />
        </span>
        <span>
          <span class="block text-lg font-extrabold leading-5 text-slate-950">اسأل قلقيلية</span>
          <span class="block text-xs font-bold text-slate-500">دليل محلي موثوق</span>
        </span>
      </a>
      <a href="{{ route('login') }}" class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-extrabold text-slate-800 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-blue-100">العودة لتسجيل الدخول</a>
    </nav>
  </header>

  <main class="mx-auto grid max-w-7xl gap-6 px-4 py-10 sm:px-6 lg:grid-cols-[0.95fr_1.05fr] lg:px-8 lg:py-16">
    <section class="order-1 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8" aria-labelledby="forgot-password-title">
      <p class="inline-flex rounded-2xl bg-blue-50 px-3 py-1.5 text-sm font-extrabold text-[#2563EB]">استعادة الحساب</p>
      <h1 id="forgot-password-title" class="mt-4 text-3xl font-extrabold text-slate-950">نسيت كلمة المرور؟</h1>
      <p class="mt-3 text-base leading-7 text-slate-600">أدخل بريدك الإلكتروني وسنرسل إليك رابطاً آمناً لإنشاء كلمة مرور جديدة.</p>

      <form class="mt-7 grid gap-5" action="{{ route('password.email') }}" method="post">
        @csrf

        @if (session('status'))
          <div class="rounded-2xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-[#16A34A]" role="status">{{ session('status') }}</div>
        @endif

        @error('email')
          <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700" role="alert">{{ $message }}</div>
        @enderror

        <div>
          <label for="email" class="text-sm font-extrabold text-slate-950">البريد الإلكتروني</label>
          <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="name@example.com" class="mt-2 h-14 w-full rounded-2xl border bg-slate-50 px-4 text-left text-base font-bold text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#2563EB] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('email') ? 'border-red-300' : 'border-slate-200' }}" dir="ltr" />
        </div>

        <button type="submit" class="inline-flex h-14 items-center justify-center rounded-2xl bg-[#2563EB] px-6 text-base font-extrabold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-200">إرسال رابط الاستعادة</button>
      </form>

      <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <p class="text-sm leading-6 text-slate-700">لأمان حسابك، تنتهي صلاحية رابط الاستعادة بعد مدة محددة ولا يمكن استخدامه أكثر من مرة.</p>
      </div>

      <a href="{{ route('login') }}" class="mt-6 inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-extrabold text-slate-800 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-blue-100">العودة إلى تسجيل الدخول</a>
    </section>

    <aside class="order-2 rounded-2xl border border-slate-200 bg-slate-950 p-6 text-white shadow-sm sm:p-8">
      <p class="text-sm font-extrabold text-blue-200">خطوات بسيطة وآمنة</p>
      <h2 class="mt-3 text-3xl font-extrabold leading-tight">استعد الوصول إلى حسابك</h2>
      <div class="mt-6 grid gap-4">
        <div class="rounded-2xl bg-white/10 p-4"><h3 class="font-extrabold">1. أدخل بريدك</h3><p class="mt-2 text-sm leading-6 text-slate-300">استخدم البريد الإلكتروني المرتبط بحسابك.</p></div>
        <div class="rounded-2xl bg-white/10 p-4"><h3 class="font-extrabold">2. افتح الرابط</h3><p class="mt-2 text-sm leading-6 text-slate-300">ستصلك رسالة تحتوي على رابط آمن لإعادة التعيين.</p></div>
        <div class="rounded-2xl bg-white/10 p-4"><h3 class="font-extrabold">3. اختر كلمة جديدة</h3><p class="mt-2 text-sm leading-6 text-slate-300">أنشئ كلمة مرور قوية ثم عد إلى حسابك.</p></div>
      </div>
    </aside>
  </main>

  <footer class="border-t border-slate-200 bg-white">
    <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-6 text-sm font-bold text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
      <p>© اسأل قلقيلية</p>
      <a href="{{ route('home') }}" class="hover:text-[#2563EB] focus:outline-none focus:ring-4 focus:ring-blue-100">الرئيسية</a>
    </div>
  </footer>
</body>
</html>
