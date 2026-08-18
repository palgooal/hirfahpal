<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>تسجيل صاحب النشاط | اسأل قلقيلية</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet" />
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F8FAFC] text-slate-900 antialiased [font-family:Tajawal,Inter,sans-serif]">
  <main class="mx-auto grid max-w-7xl gap-6 px-4 py-10 sm:px-6 lg:grid-cols-[0.95fr_1.05fr] lg:px-8 lg:py-16">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
      <p class="inline-flex rounded-2xl bg-green-50 px-3 py-1.5 text-sm font-extrabold text-[#16A34A]">صاحب نشاط جديد</p>
      <h1 class="mt-4 text-3xl font-extrabold text-slate-950">إنشاء حساب صاحب نشاط</h1>
      <form class="mt-7 grid gap-5" action="{{ route('owner.register.store') }}" method="post">
        @csrf
        @if ($errors->any())
          <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700">{{ $errors->first() }}</div>
        @endif
        <div>
          <label for="full_name" class="text-sm font-extrabold text-slate-950">الاسم الكامل</label>
          <input id="full_name" name="full_name" type="text" value="{{ old('full_name') }}" required class="mt-2 h-14 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4" />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
          <div>
            <label for="phone" class="text-sm font-extrabold text-slate-950">رقم الهاتف</label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required class="mt-2 h-14 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-left" dir="ltr" />
          </div>
          <div>
            <label for="email" class="text-sm font-extrabold text-slate-950">البريد الإلكتروني</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-2 h-14 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-left" dir="ltr" />
          </div>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
          <div>
            <label for="password" class="text-sm font-extrabold text-slate-950">كلمة المرور</label>
            <input id="password" name="password" type="password" required class="mt-2 h-14 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4" />
          </div>
          <div>
            <label for="password_confirmation" class="text-sm font-extrabold text-slate-950">تأكيد كلمة المرور</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-2 h-14 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4" />
          </div>
        </div>
        <label class="flex items-start gap-3 rounded-2xl bg-slate-50 px-4 py-3 text-sm font-bold text-slate-700">
          <input type="checkbox" name="terms" value="1" @checked(old('terms')) required class="mt-1 size-4 accent-[#2563EB]" />
          <span>أوافق على شروط الاستخدام وسياسة الخصوصية.</span>
        </label>
        <button type="submit" class="inline-flex h-14 items-center justify-center rounded-2xl bg-[#2563EB] px-6 text-base font-extrabold text-white">إنشاء حساب النشاط</button>
      </form>
      <div class="mt-6 text-center text-sm font-bold text-slate-600">
        <a href="{{ route('login') }}" class="text-[#2563EB]">لديك حساب صاحب نشاط؟ سجل الدخول</a>
      </div>
    </section>
    <aside class="rounded-2xl border border-slate-200 bg-slate-950 p-6 text-white shadow-sm sm:p-8">
      <h2 class="text-3xl font-extrabold leading-tight">ابدأ بإدارة نشاطك من مكان واحد</h2>
    </aside>
  </main>
</body>
</html>
