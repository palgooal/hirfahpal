<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>إعادة تعيين كلمة مرور الإدارة</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F8FAFC] text-slate-900 antialiased [font-family:Tajawal,Inter,sans-serif]">
  <main class="mx-auto max-w-3xl px-4 py-12">
    <section class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
      <h1 class="text-3xl font-extrabold">تعيين كلمة مرور جديدة</h1>
      <form class="mt-6 grid gap-5" action="{{ route('admin.password.update') }}" method="post">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}" />
        <div>
          <label for="email" class="text-sm font-extrabold">البريد الإلكتروني</label>
          <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required class="mt-2 h-14 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-left" dir="ltr" />
        </div>
        <div>
          <label for="password" class="text-sm font-extrabold">كلمة المرور الجديدة</label>
          <input id="password" name="password" type="password" required class="mt-2 h-14 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4" />
        </div>
        <div>
          <label for="password_confirmation" class="text-sm font-extrabold">تأكيد كلمة المرور</label>
          <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-2 h-14 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4" />
        </div>
        <button type="submit" class="inline-flex h-14 items-center justify-center rounded-2xl bg-[#2563EB] px-6 text-base font-extrabold text-white">حفظ كلمة المرور</button>
      </form>
    </section>
  </main>
</body>
</html>