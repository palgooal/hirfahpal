{{-- CUSTOMER-AUTH-B2-02: the customer reset-password page, on the customer login page's design (storefront/login/index).
     The broker reports a bad or expired token on the email field (passwords.token). --}}
@php
  $tokenInvalid = $errors->first('email') === __('passwords.token');
@endphp

<main data-auth-page>
  <section class="bg-canvas px-4 py-10 sm:py-16 lg:px-8">
    <div
      class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 mx-auto flex w-full max-w-[480px] flex-col gap-6">
      <div class="flex flex-col items-center gap-2 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-[16px] bg-surface p-1 shadow-sm">
          <img src="{{ asset('assets/storefront/imgs/mcp/header-logo.png') }}" alt="شعار حرفة" class="h-11 w-11 object-cover">
        </span>
        <h1 class="mt-2 text-2xl font-bold leading-8 text-ink">تعيين كلمة مرور جديدة</h1>
        <p class="text-sm leading-6 text-muted">اختر كلمة مرور جديدة لحسابك في حرفة.</p>
      </div>

      <div class="rounded-[20px] border border-line bg-surface p-5 shadow-card sm:p-6">
        <form id="resetPasswordPanel" class="flex flex-col gap-4" method="POST" action="{{ route($account['route'].'.password.update') }}" novalidate>
          @csrf
          <input type="hidden" name="token" value="{{ $token }}">
          <label class="text-start">
            <span class="mb-2 block text-sm font-semibold leading-5 text-ink">البريد الإلكتروني</span>
            <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"
              class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive"
              placeholder="example@email.com">
          </label>
          @error('email')
            <div class="rounded-[12px] border border-[#A13D2B]/30 bg-[#A13D2B]/10 px-4 py-3 text-start text-sm font-semibold text-[#A13D2B]" role="alert">
              <p>{{ $message }}</p>
              @if ($tokenInvalid)
                <a href="{{ route($account['route'].'.password.request') }}" class="mt-1 inline-block font-bold underline underline-offset-2">طلب رابط جديد</a>
              @endif
            </div>
          @enderror
          <label class="text-start">
            <span class="mb-2 block text-sm font-semibold leading-5 text-ink">كلمة المرور الجديدة</span>
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
            <span class="mb-2 block text-sm font-semibold leading-5 text-ink">تأكيد كلمة المرور الجديدة</span>
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
          @error('password')
            <p class="rounded-[12px] border border-[#A13D2B]/30 bg-[#A13D2B]/10 px-4 py-3 text-start text-sm font-semibold text-[#A13D2B]"
              role="alert">{{ $message }}</p>
          @enderror
          <button type="submit"
            class="mt-2 inline-flex h-12 w-full items-center justify-center gap-2 rounded-[16px] bg-olive text-sm font-bold text-surface shadow-sm transition-colors hover:bg-[#313923]">
            <span>حفظ كلمة المرور</span>
            <i data-lucide="key-round" class="h-4 w-4"></i>
          </button>
        </form>
      </div>

      <p class="text-center text-sm leading-6 text-muted">
        <a href="{{ route($account['route'].'.login') }}" class="font-bold text-olive underline underline-offset-2">العودة إلى تسجيل الدخول</a>
      </p>
    </div>
  </section>
</main>
