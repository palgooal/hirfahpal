{{-- CUSTOMER-AUTH-B2-02: the customer forgot-password page, on the customer login page's design (storefront/login/index). --}}
<main data-auth-page>
  <section class="bg-canvas px-4 py-10 sm:py-16 lg:px-8">
    <div
      class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 mx-auto flex w-full max-w-[480px] flex-col gap-6">
      <div class="flex flex-col items-center gap-2 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-[16px] bg-surface p-1 shadow-sm">
          <img src="{{ asset('assets/storefront/imgs/mcp/header-logo.png') }}" alt="شعار حرفة" class="h-11 w-11 object-cover">
        </span>
        <h1 class="mt-2 text-2xl font-bold leading-8 text-ink">نسيت كلمة المرور؟</h1>
        <p class="text-sm leading-6 text-muted">أدخل بريدك الإلكتروني المسجّل، وسنرسل إليك رابطًا لإعادة تعيين كلمة المرور.</p>
      </div>

      <div class="rounded-[20px] border border-line bg-surface p-5 shadow-card sm:p-6">
        <form id="forgotPasswordPanel" class="flex flex-col gap-4" method="POST" action="{{ route($account['route'].'.password.email') }}" novalidate>
          @csrf
          @if (session('status'))
            <div class="flex items-center gap-3 rounded-[14px] border border-sage/40 bg-sage/10 px-4 py-3 text-start" role="status">
              <i data-lucide="check-circle-2" class="h-5 w-5 shrink-0 text-olive"></i>
              <p class="text-sm font-semibold leading-6 text-olive">{{ session('status') }}</p>
            </div>
          @endif
          <label class="text-start">
            <span class="mb-2 block text-sm font-semibold leading-5 text-ink">البريد الإلكتروني</span>
            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
              class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none transition-colors focus:border-olive"
              placeholder="example@email.com">
          </label>
          @error('email')
            <p class="rounded-[12px] border border-[#A13D2B]/30 bg-[#A13D2B]/10 px-4 py-3 text-start text-sm font-semibold text-[#A13D2B]"
              role="alert">{{ $message }}</p>
          @enderror
          <button type="submit"
            class="mt-2 inline-flex h-12 w-full items-center justify-center gap-2 rounded-[16px] bg-olive text-sm font-bold text-surface shadow-sm transition-colors hover:bg-[#313923]">
            <span>إرسال رابط الاستعادة</span>
            <i data-lucide="mail" class="h-4 w-4"></i>
          </button>
        </form>
      </div>

      <p class="text-center text-sm leading-6 text-muted">
        <a href="{{ route($account['route'].'.login') }}" class="font-bold text-olive underline underline-offset-2">العودة إلى تسجيل الدخول</a>
      </p>
    </div>
  </section>
</main>
