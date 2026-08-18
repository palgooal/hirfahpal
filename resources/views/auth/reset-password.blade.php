<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="إنشاء كلمة مرور جديدة لحسابك في اسأل قلقيلية." />
  <title>تعيين كلمة مرور جديدة | اسأل قلقيلية</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet" />
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F8FAFC] text-slate-900 antialiased [font-family:Tajawal,Inter,sans-serif]">
  <header class="border-b border-slate-200 bg-white">
    <nav class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-3 sm:px-6" aria-label="تنقل تعيين كلمة المرور">
      <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="اسأل قلقيلية">
        <span class="flex size-11 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><img src="{{ asset('images/ask-qalqilya-logo.svg') }}" alt="" class="h-full w-full object-contain p-1.5" /></span>
        <span><span class="block text-lg font-extrabold leading-5 text-slate-950">اسأل قلقيلية</span><span class="block text-xs font-bold text-slate-500">دليل محلي موثوق</span></span>
      </a>
      <a href="{{ route('login') }}" class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-extrabold text-slate-800 shadow-sm hover:bg-slate-50">تسجيل الدخول</a>
    </nav>
  </header>

  <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:py-16">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8" aria-labelledby="reset-password-title">
      <p class="inline-flex rounded-2xl bg-blue-50 px-3 py-1.5 text-sm font-extrabold text-[#2563EB]">حماية الحساب</p>
      <h1 id="reset-password-title" class="mt-4 text-3xl font-extrabold text-slate-950">تعيين كلمة مرور جديدة</h1>
      <p class="mt-3 text-base leading-7 text-slate-600">اختر كلمة مرور قوية لا تقل عن 8 أحرف.</p>

      <form class="mt-7 grid gap-5" action="{{ route('password.update') }}" method="post">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}" />

        @if ($errors->any())
          <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700" role="alert">{{ $errors->first() }}</div>
        @endif

        <div>
          <label for="email" class="text-sm font-extrabold text-slate-950">البريد الإلكتروني</label>
          <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email" dir="ltr" class="mt-2 h-14 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-left text-base font-bold text-slate-900 outline-none focus:border-[#2563EB] focus:bg-white focus:ring-4 focus:ring-blue-100" />
        </div>
        <div>
          <label for="password" class="text-sm font-extrabold text-slate-950">كلمة المرور الجديدة</label>
          <input id="password" name="password" type="password" required autofocus autocomplete="new-password" class="mt-2 h-14 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-base font-bold text-slate-900 outline-none focus:border-[#2563EB] focus:bg-white focus:ring-4 focus:ring-blue-100" />
        </div>
        <div>
          <label for="password_confirmation" class="text-sm font-extrabold text-slate-950">تأكيد كلمة المرور</label>
          <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 h-14 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-base font-bold text-slate-900 outline-none focus:border-[#2563EB] focus:bg-white focus:ring-4 focus:ring-blue-100" />
        </div>
        <button type="submit" class="inline-flex h-14 items-center justify-center rounded-2xl bg-[#2563EB] px-6 text-base font-extrabold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-200">حفظ كلمة المرور الجديدة</button>
      </form>
    </section>
  </main>
</body>
</html>

