<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>دخول الإدارة</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet" />
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F8FAFC] text-slate-900 antialiased [font-family:Tajawal,Inter,sans-serif]">
  <main class="mx-auto max-w-3xl px-4 py-12">
    <section class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
      <p class="text-sm font-extrabold text-[#2563EB]">الإدارة</p>
      <h1 class="mt-3 text-3xl font-extrabold">تسجيل دخول الإدارة</h1>

      @if (session('status'))
        <div class="mt-6 rounded-2xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-700">
          {{ session('status') }}
        </div>
      @endif

      <form class="mt-6 grid gap-5" action="{{ route('admin.login.store') }}" method="post">
        @csrf
        @if ($errors->any())
          <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700">
            {{ $errors->first() }}
          </div>
        @endif
        <div>
          <label for="login" class="text-sm font-extrabold">البريد الإلكتروني أو الهاتف</label>
          <input id="login" name="login" type="text" value="{{ old('login') }}" required autocomplete="username" class="mt-2 h-14 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4" />
        </div>
        <div>
          <label for="password" class="text-sm font-extrabold">كلمة المرور</label>
          <input id="password" name="password" type="password" required autocomplete="current-password" class="mt-2 h-14 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4" />
        </div>
        <label class="flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3 text-sm font-bold text-slate-700">
          <input type="checkbox" name="remember" value="1" class="size-4 accent-[#2563EB]" />
          تذكرني
        </label>
        <button type="submit" class="inline-flex h-14 items-center justify-center rounded-2xl bg-[#2563EB] px-6 text-base font-extrabold text-white">دخول الإدارة</button>
      </form>
      <div class="mt-4 text-sm font-bold">
        <a href="{{ route('admin.password.request') }}" class="text-[#2563EB]">نسيت كلمة المرور؟</a>
      </div>
    </section>
  </main>
</body>
</html>
