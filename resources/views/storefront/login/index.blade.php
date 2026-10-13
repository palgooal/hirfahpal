@php
  $showRegister = request()->routeIs('customer.register') || old('_auth_panel') === 'register';
  $registerErrors = collect(['full_name', 'phone', 'email', 'password', 'terms'])
      ->map(fn ($field) => $errors->first($field))
      ->first();
  $loginError = $errors->first('login') ?: $errors->first('password');
@endphp

<main data-auth-page data-initial-auth-tab="{{ $showRegister ? 'register' : 'login' }}">
  <section class="bg-canvas px-4 py-10 sm:py-16 lg:px-8">
    <div
      class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 mx-auto flex w-full max-w-[480px] flex-col gap-6">
      <div class="flex flex-col items-center gap-2 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-[16px] bg-surface p-1 shadow-sm">
          <img src="{{ asset('assets/storefront/imgs/mcp/header-logo.png') }}" alt="شعار حرفة" class="h-11 w-11 object-cover">
        </span>
        <h1 class="mt-2 text-2xl font-bold leading-8 text-ink">أهلاً بك في حرفة</h1>
        <p class="text-sm leading-6 text-muted">سجّل دخولك أو أنشئ حساباً جديداً لمتابعة طلباتك ودعم الحرفيين مباشرة.</p>
      </div>

      <div class="rounded-[20px] border border-line bg-surface p-5 shadow-card sm:p-6">
        <div class="grid grid-cols-2 gap-2 rounded-[14px] border border-line bg-canvas p-1" role="tablist"
          aria-label="تبديل بين تسجيل الدخول وإنشاء حساب">
          <button type="button" id="loginTabButton" role="tab" aria-selected="{{ $showRegister ? 'false' : 'true' }}" aria-controls="loginPanel"
            class="auth-tab-button rounded-[10px] px-4 py-2.5 text-sm font-bold transition-colors {{ $showRegister ? 'text-muted' : 'bg-olive text-surface shadow-sm' }}">تسجيل الدخول</button>
          <button type="button" id="registerTabButton" role="tab" aria-selected="{{ $showRegister ? 'true' : 'false' }}" aria-controls="registerPanel"
            class="auth-tab-button rounded-[10px] px-4 py-2.5 text-sm font-bold transition-colors {{ $showRegister ? 'bg-olive text-surface shadow-sm' : 'text-muted' }}">إنشاء حساب</button>
        </div>

        <form id="loginPanel" role="tabpanel" aria-labelledby="loginTabButton" class="mt-6 {{ $showRegister ? 'hidden' : 'flex' }} flex-col gap-4"
          method="POST" action="{{ route('customer.login.store') }}" novalidate>
          @csrf
          <label class="text-start">
            <span class="mb-2 block text-sm font-semibold leading-5 text-ink">البريد الإلكتروني أو رقم الجوال</span>
            <input type="text" name="login" value="{{ old('login') }}" required autocomplete="username"
              class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive"
              placeholder="example@email.com أو 05xxxxxxxx">
          </label>
          <label class="text-start">
            <span class="mb-2 flex items-center justify-between text-sm font-semibold leading-5 text-ink">
              <span>كلمة المرور</span>
              <a href="{{ route('customer.password.request') }}" class="text-xs font-bold text-olive underline underline-offset-2">نسيت كلمة المرور؟</a>
            </span>
            <span class="relative flex items-center">
              <input type="password" name="password" required autocomplete="current-password"
                class="password-input h-12 w-full rounded-[14px] border border-line bg-canvas px-4 pe-12 text-sm outline-none transition-colors focus:border-olive"
                placeholder="••••••••">
              <button type="button" data-toggle-password
                class="absolute end-3 flex h-8 w-8 items-center justify-center rounded-full text-muted hover:text-ink"
                aria-label="إظهار كلمة المرور">
                <i data-lucide="eye" class="h-4 w-4"></i>
              </button>
            </span>
          </label>
          @if (session('status'))
            <div class="flex items-center gap-3 rounded-[14px] border border-sage/40 bg-sage/10 px-4 py-3 text-start"
              role="status" data-login-status>
              <i data-lucide="check-circle-2" class="h-5 w-5 shrink-0 text-olive"></i>
              <p class="text-sm font-semibold leading-6 text-olive">{{ session('status') }}</p>
            </div>
          @endif
          <p data-form-error class="{{ $loginError ? '' : 'hidden ' }}rounded-[12px] border border-[#A13D2B]/30 bg-[#A13D2B]/10 px-4 py-3 text-start text-sm font-semibold text-[#A13D2B]"
            role="alert">{{ $loginError }}</p>
          <button type="submit"
            class="mt-2 inline-flex h-12 w-full items-center justify-center gap-2 rounded-[16px] bg-olive text-sm font-bold text-surface shadow-sm transition-colors hover:bg-[#313923]">
            <span>تسجيل الدخول</span>
            <i data-lucide="log-in" class="h-4 w-4"></i>
          </button>
        </form>

        <form id="registerPanel" role="tabpanel" aria-labelledby="registerTabButton" class="mt-6 {{ $showRegister ? 'flex' : 'hidden' }} flex-col gap-4"
          method="POST" action="{{ route('customer.register.store') }}" novalidate>
          @csrf
          <input type="hidden" name="_auth_panel" value="register">
          <label class="text-start">
            <span class="mb-2 block text-sm font-semibold leading-5 text-ink">الاسم الكامل</span>
            <input type="text" name="full_name" value="{{ old('full_name') }}" required autocomplete="name"
              class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive"
              placeholder="الاسم الكامل">
          </label>
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-start">
              <span class="mb-2 block text-sm font-semibold leading-5 text-ink">رقم الجوال</span>
              <input type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel"
                class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive"
                placeholder="05xxxxxxxx">
            </label>
            <label class="text-start">
              <span class="mb-2 block text-sm font-semibold leading-5 text-ink">البريد الإلكتروني</span>
              <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive"
                placeholder="example@email.com">
            </label>
          </div>
          <label class="text-start">
            <span class="mb-2 block text-sm font-semibold leading-5 text-ink">كلمة المرور</span>
            <span class="relative flex items-center">
              <input type="password" name="password" required autocomplete="new-password"
                class="password-input h-12 w-full rounded-[14px] border border-line bg-canvas px-4 pe-12 text-sm outline-none transition-colors focus:border-olive"
                placeholder="8 أحرف على الأقل">
              <button type="button" data-toggle-password
                class="absolute end-3 flex h-8 w-8 items-center justify-center rounded-full text-muted hover:text-ink"
                aria-label="إظهار كلمة المرور">
                <i data-lucide="eye" class="h-4 w-4"></i>
              </button>
            </span>
          </label>
          <label class="text-start">
            <span class="mb-2 block text-sm font-semibold leading-5 text-ink">تأكيد كلمة المرور</span>
            <span class="relative flex items-center">
              <input type="password" name="password_confirmation" required autocomplete="new-password"
                class="password-input h-12 w-full rounded-[14px] border border-line bg-canvas px-4 pe-12 text-sm outline-none transition-colors focus:border-olive"
                placeholder="أعد كتابة كلمة المرور">
              <button type="button" data-toggle-password
                class="absolute end-3 flex h-8 w-8 items-center justify-center rounded-full text-muted hover:text-ink"
                aria-label="إظهار كلمة المرور">
                <i data-lucide="eye" class="h-4 w-4"></i>
              </button>
            </span>
          </label>
          <label class="flex items-start gap-3 text-start text-xs font-semibold leading-5 text-muted">
            <input type="checkbox" name="terms" value="1" @checked(old('terms')) required class="mt-1 h-4 w-4 rounded border-line text-olive focus:ring-olive">
            <span>أوافق على شروط الاستخدام وسياسة الخصوصية.</span>
          </label>
          <p data-form-error class="{{ $registerErrors ? '' : 'hidden ' }}rounded-[12px] border border-[#A13D2B]/30 bg-[#A13D2B]/10 px-4 py-3 text-start text-sm font-semibold text-[#A13D2B]"
            role="alert">{{ $registerErrors }}</p>
          <button type="submit"
            class="mt-2 inline-flex h-12 w-full items-center justify-center gap-2 rounded-[16px] bg-olive text-sm font-bold text-surface shadow-sm transition-colors hover:bg-[#313923]">
            <span>إنشاء الحساب</span>
            <i data-lucide="user-plus" class="h-4 w-4"></i>
          </button>
        </form>
      </div>

      <p class="text-center text-xs leading-5 text-muted">بالمتابعة أنت توافق على
        <a role="link" aria-disabled="true" data-deferred-navigation="#" class="font-semibold text-olive underline underline-offset-2">شروط الاستخدام</a>
        و
        <a role="link" aria-disabled="true" data-deferred-navigation="#" class="font-semibold text-olive underline underline-offset-2">سياسة الخصوصية</a>.
      </p>
    </div>
  </section>
</main>
