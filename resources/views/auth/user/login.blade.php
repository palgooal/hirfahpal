<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>تسجيل الدخول | اسأل قلقيلية</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet" />
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F8FAFC] text-slate-900 antialiased [font-family:Tajawal,Inter,sans-serif]">
  <main class="mx-auto grid max-w-7xl gap-6 px-4 py-10 sm:px-6 lg:grid-cols-[0.95fr_1.05fr] lg:px-8 lg:py-16">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
      <p class="inline-flex rounded-2xl bg-blue-50 px-3 py-1.5 text-sm font-extrabold text-[#2563EB]">حساب المستخدم</p>
      <h1 class="mt-4 text-3xl font-extrabold text-slate-950">تسجيل الدخول</h1>
      <p class="mt-3 text-base leading-7 text-slate-600">ادخل بالبريد الإلكتروني أو رقم الهاتف للوصول إلى حسابك.</p>
      <form class="mt-7 grid gap-5" action="{{ route('login.store') }}" method="post">
        @csrf
        @if ($errors->any())
          <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700">{{ $errors->first() }}</div>
        @endif
        @if (session('status'))
          <div class="rounded-2xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-[#16A34A]">{{ session('status') }}</div>
        @endif
        <div>
          <label for="login" class="text-sm font-extrabold text-slate-950">البريد الإلكتروني أو رقم الهاتف</label>
          <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username" class="mt-2 h-14 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-base font-bold text-slate-900 outline-none focus:border-[#2563EB] focus:bg-white focus:ring-4 focus:ring-blue-100" />
        </div>
        <div>
          <div class="flex items-center justify-between gap-3">
            <label for="password" class="text-sm font-extrabold text-slate-950">كلمة المرور</label>
            <a href="{{ route('password.request') }}" class="text-sm font-extrabold text-[#2563EB]">نسيت كلمة المرور؟</a>
          </div>
          <input id="password" name="password" type="password" required autocomplete="current-password" class="mt-2 h-14 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-base font-bold text-slate-900 outline-none focus:border-[#2563EB] focus:bg-white focus:ring-4 focus:ring-blue-100" />
        </div>
        <label class="flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3 text-sm font-bold text-slate-700">
          <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-4 accent-[#2563EB]" />
          تذكرني على هذا الجهاز
        </label>
        <button type="submit" class="inline-flex h-14 items-center justify-center rounded-2xl bg-[#2563EB] px-6 text-base font-extrabold text-white">تسجيل الدخول</button>
      </form>
      <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <a href="{{ route('owner.login') }}" class="inline-flex h-12 items-center justify-center rounded-2xl border border-blue-100 bg-blue-50 px-4 text-sm font-extrabold text-[#2563EB]">دخول صاحب نشاط</a>
        <a href="{{ route('admin.login') }}" class="inline-flex h-12 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-extrabold text-slate-800">دخول الإدارة</a>
      </div>
      <div class="mt-6 text-center">
        <p class="text-sm font-bold text-slate-600">لا تملك حسابًا؟</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
          <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-extrabold text-slate-800 shadow-sm">حساب مستخدم</a>
          <a href="{{ route('owner.register') }}" class="inline-flex items-center justify-center rounded-2xl bg-[#2563EB] px-5 py-3 text-sm font-extrabold text-white shadow-sm">حساب صاحب نشاط</a>
        </div>
      </div>
    </section>
    <aside class="rounded-2xl border border-slate-200 bg-slate-950 p-6 text-white shadow-sm sm:p-8">
      <p class="text-sm font-extrabold text-blue-200">كل أدواتك في مكان واحد</p>
      <h2 class="mt-3 text-3xl font-extrabold leading-tight">ادخل إلى حسابك لإدارة تجربتك</h2>
    </aside>
  </main>
</body>
</html>