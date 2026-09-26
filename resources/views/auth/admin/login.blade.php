@php
    // Direction comes from the current Language row (is_rtl). If the locale has
    // no Language row at all, fall back to the HTML default direction (ltr).
    $pageDirection = $currentLanguage?->is_rtl ? 'rtl' : 'ltr';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $pageDirection }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ t('dashboard.Admin_Login_Title', 'Sign in | Hirfah Admin') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,700;1,400&family=Cairo:wght@300;400;500;600;700&family=Noto+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet" />
  @vite(['resources/css/admin-auth.css', 'resources/js/admin-auth.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-canvas font-cairo text-ink antialiased">
  @php($errorMessage = $errors->first())

  {{-- The form is the first grid column, so it sits on the start side: right in RTL, left in LTR. --}}
  <div class="grid min-h-screen lg:grid-cols-2">
    <main class="flex min-w-0 items-center justify-center px-4 py-10 sm:px-8 sm:py-16 lg:px-12">
      <div class="w-full max-w-[480px] lg:max-w-[520px]">
        <x-lang.language-switcher-admin-auth class="mb-6 flex justify-end" />

        {{-- Compact brand lockup for mobile and tablet --}}
        <div class="mb-8 flex items-center justify-center gap-3 lg:hidden">
          <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-[16px] bg-surface p-1 shadow-sm sm:h-[58px] sm:w-[58px]">
            <img src="{{ asset('images/brand/hirfah-logo.png') }}" alt="{{ t('dashboard.Hirfah_Logo', 'Hirfah logo') }}" width="50" height="50" class="h-12 w-12 object-contain sm:h-[50px] sm:w-[50px]" />
          </span>
          <div class="min-w-0 text-start">
            <div class="flex items-center gap-1.5">
              <span class="h-1.5 w-1.5 rounded-full bg-copper" aria-hidden="true"></span>
              <span class="text-2xl font-bold leading-8 tracking-[-.6px] text-olive">{{ t('dashboard.Brand_Name', 'Hirfah') }}</span>
            </div>
            <p class="text-[10px] font-medium leading-[15px] tracking-[.5px] text-olive sm:text-xs">{{ t('dashboard.Brand_Tagline', 'Palestinian craft marketplace') }}</p>
          </div>
        </div>

        <section class="rounded-[20px] border border-line bg-surface p-5 shadow-card sm:p-8" aria-labelledby="admin-login-title">
          <h1 id="admin-login-title" class="text-2xl font-bold leading-8 text-ink">{{ t('dashboard.Welcome_Back', 'Welcome back') }}</h1>
          <p class="mt-2 text-sm leading-6 text-muted">{{ t('dashboard.Admin_Login_Description', 'Sign in to access the Hirfah admin panel.') }}</p>

          @if (session('status'))
            <div role="status" class="mt-6 flex items-start gap-3 rounded-[14px] border border-sage/40 bg-sage/10 px-4 py-3 text-start">
              <svg class="mt-0.5 h-5 w-5 shrink-0 text-olive" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="m9 12 2 2 4-4" /></svg>
              <p class="text-sm font-semibold leading-6 text-olive">{{ session('status') }}</p>
            </div>
          @endif

          @if ($errorMessage)
            <p id="admin-login-error" role="alert" class="mt-6 rounded-[12px] border border-error/30 bg-error/10 px-4 py-3 text-start text-sm font-semibold leading-6 text-error">
              {{ $errorMessage }}
            </p>
          @endif

          <form method="POST" action="{{ route('admin.login.store') }}" class="mt-6 flex flex-col gap-5">
            @csrf

            <div>
              <label for="login" class="mb-2 block text-sm font-semibold leading-5 text-ink">{{ t('dashboard.Email_Or_Phone', 'Email or phone number') }}</label>
              <input
                id="login"
                name="login"
                type="text"
                dir="ltr"
                value="{{ old('login') }}"
                required
                autofocus
                autocomplete="username"
                placeholder="example@email.com"
                @if ($errors->has('login')) aria-invalid="true" aria-describedby="admin-login-error" @endif
                class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-start text-sm text-ink transition-colors placeholder:text-muted/70 focus:border-olive focus:outline-none focus-visible:ring-4 focus-visible:ring-olive/20 aria-[invalid=true]:border-error"
              />
            </div>

            <div>
              <label for="password" class="mb-2 block text-sm font-semibold leading-5 text-ink">{{ t('dashboard.Password', 'Password') }}</label>
              <div class="relative">
                <input
                  id="password"
                  name="password"
                  type="password"
                  required
                  autocomplete="current-password"
                  placeholder="••••••••"
                  @if ($errors->has('password')) aria-invalid="true" aria-describedby="admin-login-error" @endif
                  class="h-12 w-full rounded-[14px] border border-line bg-canvas ps-4 pe-14 text-sm text-ink transition-colors placeholder:text-muted/70 focus:border-olive focus:outline-none focus-visible:ring-4 focus-visible:ring-olive/20 aria-[invalid=true]:border-error"
                />
                <button
                  type="button"
                  data-password-toggle
                  aria-controls="password"
                  aria-pressed="false"
                  aria-label="{{ t('dashboard.Show_Password', 'Show password') }}"
                  data-label-show="{{ t('dashboard.Show_Password', 'Show password') }}"
                  data-label-hide="{{ t('dashboard.Hide_Password', 'Hide password') }}"
                  class="absolute end-0.5 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full text-muted transition-colors hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-olive"
                >
                  <svg data-icon-show class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" /><circle cx="12" cy="12" r="3" /></svg>
                  <svg data-icon-hide class="hidden h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49" /><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242" /><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143" /><path d="m2 2 20 20" /></svg>
                </button>
              </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
              <label for="remember" class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm font-semibold text-ink">
                <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember')) class="h-4 w-4 cursor-pointer accent-olive focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-olive" />
                {{ t('dashboard.Remember_Me', 'Remember me') }}
              </label>
              <a href="{{ route('admin.password.request') }}" class="inline-flex min-h-11 items-center rounded-md text-sm font-bold text-olive underline underline-offset-2 hover:text-olive-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-olive">{{ t('dashboard.Forgot_Password', 'Forgot your password?') }}</a>
            </div>

            <button type="submit" class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-[16px] bg-olive text-sm font-bold text-surface shadow-sm transition-colors hover:bg-olive-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-olive focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
              <span>{{ t('dashboard.Sign_In', 'Sign in') }}</span>
              <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" /><polyline points="10 17 15 12 10 7" /><line x1="15" x2="3" y1="12" y2="12" /></svg>
            </button>
          </form>
        </section>
      </div>
    </main>

    {{-- Brand panel: desktop only, colors and CSS shapes, no photos --}}
    <aside class="relative hidden min-w-0 overflow-hidden bg-olive lg:flex lg:items-center lg:justify-center lg:px-12" aria-label="{{ t('dashboard.Hirfah', 'Hirfah') }}">
      <div class="pointer-events-none absolute -top-28 -end-28 h-80 w-80 rounded-full border border-sage/25" aria-hidden="true"></div>
      <div class="pointer-events-none absolute -top-12 -end-12 h-48 w-48 rounded-full border border-sage/15" aria-hidden="true"></div>
      <div class="pointer-events-none absolute -bottom-40 -start-24 h-[26rem] w-[26rem] rounded-full bg-sage/10" aria-hidden="true"></div>
      <div class="pointer-events-none absolute bottom-24 end-20 h-3 w-3 rounded-full bg-copper" aria-hidden="true"></div>
      <div class="pointer-events-none absolute top-28 start-16 h-2 w-2 rounded-full bg-gold" aria-hidden="true"></div>

      <div class="relative flex max-w-md flex-col items-center text-center">
        <span class="flex h-[112px] w-[112px] items-center justify-center rounded-[24px] bg-surface p-2 shadow-soft">
          <img src="{{ asset('images/brand/hirfah-logo.png') }}" alt="{{ t('dashboard.Hirfah_Logo', 'Hirfah logo') }}" width="96" height="96" class="h-24 w-24 object-contain" />
        </span>
        <div class="mt-6 flex items-center gap-2">
          <span class="h-2 w-2 rounded-full bg-copper" aria-hidden="true"></span>
          <span class="text-4xl font-bold leading-[3rem] tracking-[-.6px] text-surface">{{ t('dashboard.Brand_Name', 'Hirfah') }}</span>
        </div>
        <p class="mt-1 text-sm font-medium tracking-[.5px] text-sage">{{ t('dashboard.Brand_Tagline', 'Palestinian craft marketplace') }}</p>
        <span class="my-8 h-px w-16 bg-sage/40" aria-hidden="true"></span>
        <p class="text-lg font-semibold leading-8 text-surface">{{ t('dashboard.Admin_Panel_Line', 'Hirfah platform administration') }}</p>
      </div>
    </aside>
  </div>
</body>
</html>
