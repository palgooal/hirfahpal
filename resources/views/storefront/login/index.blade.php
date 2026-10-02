<main data-auth-page>
  <section class="bg-canvas px-4 py-10 sm:py-16 lg:px-8">
    <div
      class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 mx-auto flex w-full max-w-[480px] flex-col gap-6">
      <div class="flex flex-col items-center gap-2 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-[16px] bg-surface p-1 shadow-sm">
          <img src="{{ asset('assets/storefront/imgs/mcp/header-logo.png') }}" alt="شعار حرفة" class="h-11 w-11 object-cover">
        </span>
        <h1 class="mt-2 text-2xl font-bold leading-8 text-ink">أهلاً بك في حرفة</h1>
        <p class="text-sm leading-6 text-muted">سجّل دخولك أو أنشئ حساباً جديداً لمتابعة طلباتك ودعم الحرفيين
          مباشرة.</p>
      </div>

      <div class="rounded-[20px] border border-line bg-surface p-5 shadow-card sm:p-6">
        <!-- Tabs -->
        <div class="grid grid-cols-2 gap-2 rounded-[14px] border border-line bg-canvas p-1" role="tablist"
          aria-label="تبديل بين تسجيل الدخول وإنشاء حساب">
          <button type="button" id="loginTabButton" role="tab" aria-selected="true" aria-controls="loginPanel"
            class="auth-tab-button rounded-[10px] px-4 py-2.5 text-sm font-bold transition-colors bg-olive text-surface shadow-sm">تسجيل
            الدخول</button>
          <button type="button" id="registerTabButton" role="tab" aria-selected="false" aria-controls="registerPanel"
            class="auth-tab-button rounded-[10px] px-4 py-2.5 text-sm font-bold transition-colors text-muted">إنشاء
            حساب</button>
        </div>

        <!-- Login panel -->
        <form id="loginPanel" role="tabpanel" aria-labelledby="loginTabButton" class="mt-6 flex flex-col gap-4"
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
              <button type="button" data-open-forgot-password class="text-xs font-bold text-olive underline underline-offset-2">نسيت
                كلمة المرور؟</button>
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
          @php($loginError = $errors->first('login') ?: $errors->first('password'))
          <p data-form-error class="{{ $loginError ? '' : 'hidden ' }}rounded-[12px] border border-[#A13D2B]/30 bg-[#A13D2B]/10 px-4 py-3 text-start text-sm font-semibold text-[#A13D2B]"
            role="alert">{{ $loginError }}</p>
          <button type="submit"
            class="mt-2 inline-flex h-12 w-full items-center justify-center gap-2 rounded-[16px] bg-olive text-sm font-bold text-surface shadow-sm transition-colors hover:bg-[#313923]">
            <span>تسجيل الدخول</span>
            <i data-lucide="log-in" class="h-4 w-4"></i>
          </button>
        </form>

        <!-- Register panel -->
        <form id="registerPanel" role="tabpanel" aria-labelledby="registerTabButton" class="mt-6 hidden flex-col gap-4"
          novalidate>
          <label class="text-start">
            <span class="mb-2 block text-sm font-semibold leading-5 text-ink">الاسم الكامل</span>
            <input type="text" name="registerName" required autocomplete="name"
              class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive"
              placeholder="الاسم الكامل">
          </label>
          <label class="text-start">
            <span class="mb-2 block text-sm font-semibold leading-5 text-ink">البريد الإلكتروني أو رقم الجوال</span>
            <input type="text" name="registerIdentifier" required autocomplete="username"
              class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive"
              placeholder="example@email.com أو 05xxxxxxxx">
          </label>
          <label class="text-start">
            <span class="mb-2 block text-sm font-semibold leading-5 text-ink">كلمة المرور</span>
            <span class="relative flex items-center">
              <input type="password" name="registerPassword" required autocomplete="new-password"
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
            <span class="mb-2 block text-sm font-semibold leading-5 text-ink">عنوان التوصيل <span class="font-normal text-muted">(اختياري الآن)</span></span>
            <input type="text" name="registerAddress" autocomplete="street-address"
              class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive"
              placeholder="المدينة، الحي، الشارع">
          </label>
          <p data-form-error class="hidden rounded-[12px] border border-[#A13D2B]/30 bg-[#A13D2B]/10 px-4 py-3 text-start text-sm font-semibold text-[#A13D2B]"
            role="alert"></p>
          <button type="button"
            class="mt-2 inline-flex h-12 w-full items-center justify-center gap-2 rounded-[16px] bg-olive text-sm font-bold text-surface shadow-sm transition-colors hover:bg-[#313923]">
            <span>إنشاء الحساب</span>
            <i data-lucide="user-plus" class="h-4 w-4"></i>
          </button>
        </form>
      </div>

      <p class="text-center text-xs leading-5 text-muted">بالمتابعة أنت توافق على
        <a role="link" aria-disabled="true" data-deferred-navigation="#" class="font-semibold text-olive underline underline-offset-2">شروط الاستخدام</a> و
        <a role="link" aria-disabled="true" data-deferred-navigation="#" class="font-semibold text-olive underline underline-offset-2">سياسة الخصوصية</a>.</p>
    </div>
  </section>
</main>

<!-- Forgot password modal -->
<div id="forgotPasswordModal" class="fixed start-0 end-0 top-0 bottom-0 z-[90] hidden bg-ink/50 p-4 backdrop-blur-sm"
  role="dialog" aria-modal="true" aria-labelledby="forgotPasswordTitle">
  <div class="mx-auto mt-24 max-w-md rounded-[20px] border border-line bg-surface p-5 shadow-soft sm:p-6">
    <div class="flex items-start justify-between gap-4 border-b border-line pb-4">
      <div class="text-start">
        <h2 id="forgotPasswordTitle" class="text-lg font-bold leading-7 text-ink">استرجاع كلمة المرور</h2>
        <p class="mt-1 text-sm leading-6 text-muted">أدخل بريدك أو رقم جوالك وسنرسل لك رابط إعادة التعيين.</p>
      </div>
      <button type="button" data-close-forgot-password
        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-line bg-canvas text-muted hover:text-ink"
        aria-label="إغلاق"><i data-lucide="x" class="h-4 w-4"></i></button>
    </div>
    <form id="forgotPasswordForm" class="mt-5 flex flex-col gap-4" novalidate>
      <label class="text-start">
        <span class="mb-2 block text-sm font-semibold leading-5 text-ink">البريد الإلكتروني أو رقم الجوال</span>
        <input type="text" name="forgotIdentifier" required
          class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive"
          placeholder="example@email.com أو 05xxxxxxxx">
      </label>
      <button type="button"
        class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-[16px] bg-olive text-sm font-bold text-surface shadow-sm transition-colors hover:bg-[#313923]">
        <span>إرسال رابط الاسترجاع</span>
      </button>
    </form>
    <div data-forgot-success class="mt-5 hidden items-center gap-3 rounded-[14px] border border-sage/40 bg-sage/10 px-4 py-3 text-start">
      <i data-lucide="check-circle-2" class="h-5 w-5 shrink-0 text-olive"></i>
      <p class="text-sm font-semibold leading-6 text-olive">تم إرسال رابط الاسترجاع إن كان الحساب موجوداً.</p>
    </div>
  </div>
</div>
