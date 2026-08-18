<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="إنشاء حساب جديد في اسأل قلقيلية للمستخدمين وأصحاب الأعمال داخل قلقيلية." />
  <title>إنشاء حساب جديد | اسأل قلقيلية</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet" />
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F8FAFC] text-slate-900 antialiased [font-family:Tajawal,Inter,sans-serif]">
  <header class="border-b border-slate-200 bg-white">
    <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8" aria-label="تنقل التسجيل">
      <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="اسأل قلقيلية">
        <span class="flex size-11 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <img src="{{ asset('images/ask-qalqilya-logo.svg') }}" alt="" class="h-full w-full object-contain p-1.5" />
        </span>
        <span>
          <span class="block text-lg font-extrabold leading-5 text-slate-950">اسأل قلقيلية</span>
          <span class="block text-xs font-bold text-slate-500">دليل محلي موثوق</span>
        </span>
      </a>
      <a href="{{ route('login') }}" class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-extrabold text-slate-800 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-blue-100">لدي حساب بالفعل</a>
    </nav>
  </header>

  <main class="mx-auto grid max-w-7xl gap-6 px-4 py-10 sm:px-6 lg:grid-cols-[0.95fr_1.05fr] lg:px-8 lg:py-16">
    <section class="order-1 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8" aria-labelledby="register-title">
      <p class="inline-flex rounded-2xl bg-green-50 px-3 py-1.5 text-sm font-extrabold text-[#16A34A]">ابدأ الآن</p>
      <h1 id="register-title" class="mt-4 text-3xl font-extrabold text-slate-950">إنشاء حساب جديد</h1>
      <p class="mt-3 text-base leading-7 text-slate-600">أنشئ حسابك للوصول إلى خدمات اسأل قلقيلية، أو ابدأ بإضافة نشاطك التجاري.</p>

      <form class="mt-7 grid gap-5" action="{{ route('register.store') }}" method="post">
        @csrf

        @if ($errors->any())
          <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700" role="alert">
            <p>يرجى مراجعة الحقول المطلوبة قبل إنشاء الحساب.</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <fieldset>
          <legend class="text-sm font-extrabold text-slate-950">نوع الحساب</legend>
          <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="rounded-2xl border border-blue-200 bg-blue-50 p-4">
              <span class="flex items-center gap-3">
                <input type="radio" name="account_type" value="user" @checked(old('account_type', 'user') === 'user') class="size-4 accent-[#2563EB]" />
                <span class="font-extrabold text-slate-950">مستخدم</span>
              </span>
              <span class="mt-2 block text-sm leading-6 text-slate-600">حفظ الأماكن، التقديم للوظائف، كتابة مراجعات</span>
            </label>
            <label class="rounded-2xl border border-orange-200 bg-orange-50 p-4 shadow-sm">
              <span class="mb-3 inline-flex rounded-full bg-white px-3 py-1 text-xs font-extrabold text-[#EA580C]">الأكثر استخداماً لأصحاب الأعمال</span>
              <span class="flex items-center gap-3">
                <input type="radio" name="account_type" value="business" @checked(old('account_type') === 'business') class="size-4 accent-[#2563EB]" />
                <span class="font-extrabold text-slate-950">صاحب نشاط</span>
              </span>
              <span class="mt-2 block text-sm leading-6 text-slate-600">إدارة بيانات النشاط، إضافة الصور، نشر الوظائف</span>
              <span class="mt-2 block text-xs font-bold leading-5 text-slate-500">أنشئ صفحة نشاطك، أضف الصور، وانشر الوظائف.</span>
            </label>
          </div>
        </fieldset>

        <div class="grid gap-5 sm:grid-cols-2">
          <div>
            <label for="full-name" class="text-sm font-extrabold text-slate-950">الاسم الكامل</label>
            <input id="full-name" name="full_name" type="text" value="{{ old('full_name') }}" required autocomplete="name" class="mt-2 h-14 w-full rounded-2xl border bg-slate-50 px-4 text-base font-bold text-slate-900 outline-none transition focus:border-[#2563EB] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('full_name') ? 'border-red-300' : 'border-slate-200' }}" />
          </div>
          <div>
            <label for="phone" class="text-sm font-extrabold text-slate-950">رقم الهاتف</label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required autocomplete="tel" dir="ltr" class="mt-2 h-14 w-full rounded-2xl border bg-slate-50 px-4 text-left text-base font-bold text-slate-900 outline-none transition focus:border-[#2563EB] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('phone') ? 'border-red-300' : 'border-slate-200' }}" />
          </div>
        </div>
        <div>
          <label for="email" class="text-sm font-extrabold text-slate-950">البريد الإلكتروني</label>
          <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" dir="ltr" class="mt-2 h-14 w-full rounded-2xl border bg-slate-50 px-4 text-left text-base font-bold text-slate-900 outline-none transition focus:border-[#2563EB] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('email') ? 'border-red-300' : 'border-slate-200' }}" />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
          <div>
            <label for="password" class="text-sm font-extrabold text-slate-950">كلمة المرور</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-2 h-14 w-full rounded-2xl border bg-slate-50 px-4 text-base font-bold text-slate-900 outline-none transition focus:border-[#2563EB] focus:bg-white focus:ring-4 focus:ring-blue-100 {{ $errors->has('password') ? 'border-red-300' : 'border-slate-200' }}" />
            <p class="mt-2 text-xs font-bold text-slate-500">يجب أن تحتوي كلمة المرور على 8 أحرف على الأقل.</p>
          </div>
          <div>
            <label for="password-confirmation" class="text-sm font-extrabold text-slate-950">تأكيد كلمة المرور</label>
            <input id="password-confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 h-14 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-base font-bold text-slate-900 outline-none transition focus:border-[#2563EB] focus:bg-white focus:ring-4 focus:ring-blue-100" />
            <p class="mt-2 text-xs font-bold text-slate-500">يجب أن تحتوي كلمة المرور على 8 أحرف على الأقل.</p>
          </div>
        </div>
        <label class="flex items-start gap-3 rounded-2xl bg-slate-50 px-4 py-3 text-sm font-bold text-slate-700">
          <input type="checkbox" name="terms" value="1" @checked(old('terms')) required class="mt-1 size-4 accent-[#2563EB]" />
          <span>أوافق على شروط الاستخدام وسياسة الخصوصية في منصة اسأل قلقيلية.</span>
        </label>
        <button type="submit" class="inline-flex h-14 items-center justify-center rounded-2xl bg-[#2563EB] px-6 text-base font-extrabold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-200">إنشاء الحساب</button>
      </form>

      <div class="my-6 flex items-center gap-4" aria-hidden="true">
        <span class="h-px flex-1 bg-slate-200"></span><span class="text-sm font-extrabold text-slate-400">أو</span><span class="h-px flex-1 bg-slate-200"></span>
      </div>

      <div class="grid gap-3 sm:grid-cols-2" aria-label="خيارات إنشاء حساب مستقبلية">
        <button type="button" disabled class="inline-flex h-12 cursor-not-allowed items-center justify-center rounded-2xl border border-slate-200 bg-slate-100 px-4 text-sm font-extrabold text-slate-400">Google (قريباً)</button>
        <button type="button" disabled class="inline-flex h-12 cursor-not-allowed items-center justify-center rounded-2xl border border-slate-200 bg-slate-100 px-4 text-sm font-extrabold text-slate-400">Facebook (قريباً)</button>
      </div>

      <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4"><p class="text-sm leading-6 text-slate-700">يتم تشفير بيانات الدخول وحمايتها وفق أفضل الممارسات الأمنية.</p></div>
      <div class="mt-4 rounded-2xl border border-blue-100 bg-blue-50 p-4"><p class="text-sm leading-6 text-slate-700">بياناتك تستخدم فقط لإدارة حسابك داخل منصة اسأل قلقيلية.</p></div>

      <div class="mt-6 text-center">
        <p class="text-sm font-bold text-slate-600">لديك حساب؟</p>
        <a href="{{ route('login') }}" class="mt-3 inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-extrabold text-slate-800 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-blue-100">لدي حساب بالفعل</a>
      </div>
    </section>

    <aside class="order-2 rounded-2xl border border-slate-200 bg-slate-950 p-6 text-white shadow-sm sm:p-8">
      <p class="text-sm font-extrabold text-blue-200">حساب واحد لخدمات المدينة</p>
      <h2 class="mt-3 text-3xl font-extrabold leading-tight">استفد من اسأل قلقيلية كمستخدم أو صاحب نشاط</h2>
      <div class="mt-6 grid gap-4">
        <div class="rounded-2xl bg-white/10 p-4"><h3 class="font-extrabold">للمستخدمين</h3><p class="mt-2 text-sm leading-6 text-slate-300">احفظ الأماكن، قيّم الخدمات، وتابع الوظائف المناسبة لك.</p></div>
        <div class="rounded-2xl bg-white/10 p-4"><h3 class="font-extrabold">لأصحاب الأعمال</h3><p class="mt-2 text-sm leading-6 text-slate-300">أدر بيانات نشاطك، أضف الصور، وانشر فرص العمل المحلية.</p></div>
        <div class="rounded-2xl bg-white/10 p-4"><h3 class="font-extrabold">ثقة محلية</h3><p class="mt-2 text-sm leading-6 text-slate-300">حسابك يساعد المنصة على بناء دليل أكثر دقة لسكان قلقيلية.</p></div>
      </div>
    </aside>
  </main>

  <footer class="border-t border-slate-200 bg-white">
    <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-6 text-sm font-bold text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
      <p>© اسأل قلقيلية</p>
      <div class="flex flex-wrap gap-3">
        <a href="{{ route('register') }}" class="hover:text-[#2563EB] focus:outline-none focus:ring-4 focus:ring-blue-100">الخصوصية</a>
        <a href="{{ route('register') }}" class="hover:text-[#2563EB] focus:outline-none focus:ring-4 focus:ring-blue-100">الشروط</a>
        <a href="{{ route('home') }}" class="hover:text-[#2563EB] focus:outline-none focus:ring-4 focus:ring-blue-100">الرئيسية</a>
      </div>
    </div>
  </footer>
</body>
</html>
