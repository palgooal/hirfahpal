@php
    // Direction comes from the current Language row (is_rtl). If the locale has
    // no Language row at all, fall back to the HTML default direction (ltr).
    $pageDirection = $currentLanguage?->is_rtl ? 'rtl' : 'ltr';
    $isRejected = $state === 'rejected';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $pageDirection }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ t('vendor.Status_Title', 'Application status | Hirfah Vendor') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,700;1,400&family=Cairo:wght@300;400;500;600;700&family=Noto+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet" />
  @vite(['resources/css/vendor-auth.css'])
</head>
<body class="min-h-screen overflow-x-hidden bg-canvas font-cairo text-ink antialiased">
  {{-- The card is the first grid column, so it sits on the start side: right in RTL, left in LTR. --}}
  <div class="grid min-h-screen lg:grid-cols-2">
    <main class="flex min-w-0 items-center justify-center px-4 py-10 sm:px-8 sm:py-16 lg:px-12">
      <div class="w-full max-w-[480px] lg:max-w-[520px]">
        <x-lang.language-switcher-admin-auth class="mb-6 flex justify-end lg:mb-4" />

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

        <section class="rounded-[20px] border border-line bg-surface p-5 shadow-card sm:p-8" aria-labelledby="vendor-status-title">
          <p class="mb-5 inline-flex items-center gap-2 rounded-full bg-sage/20 px-3 py-1 text-xs font-bold leading-5 text-olive">
            <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="8" height="4" x="8" y="2" rx="1" ry="1" /><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" /><path d="m9 14 2 2 4-4" /></svg>
            {{ t('vendor.Application_Status', 'Application status') }}
          </p>

          {{-- Status indicator: decorative, the state is also given in text below. --}}
          @if ($isRejected)
            <span data-status-indicator="rejected" class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-error/10 text-error" aria-hidden="true">
              <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><path d="m15 9-6 6" /><path d="m9 9 6 6" /></svg>
            </span>
          @else
            <span data-status-indicator="pending" class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-gold/15 text-gold" aria-hidden="true">
              <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14" /><path d="M5 2h14" /><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22" /><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2" /></svg>
            </span>
          @endif

          <h1 id="vendor-status-title" class="text-2xl font-bold leading-8 text-ink">
            {{ $isRejected ? t('vendor.Rejected_Heading', 'Your application was not approved') : t('vendor.Pending_Heading', 'Your application is under review') }}
          </h1>
          <p class="mt-2 text-sm leading-6 text-muted">
            {{ $isRejected
                ? t('vendor.Rejected_Description', 'We are sorry to let you know that your request to join Hirfah as a vendor was not approved.')
                : t('vendor.Pending_Description', 'We have received your request to join Hirfah as a vendor, and our team is reviewing it.') }}
          </p>

          <dl class="mt-6 divide-y divide-line rounded-[14px] border border-line bg-canvas px-4">
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-3">
              <dt class="text-sm font-semibold text-muted">{{ t('vendor.Status_Label', 'Status') }}</dt>
              <dd>
                @if ($isRejected)
                  <span class="inline-flex items-center gap-1.5 rounded-full bg-error/10 px-3 py-1 text-xs font-bold leading-5 text-error">
                    <span class="h-1.5 w-1.5 rounded-full bg-error" aria-hidden="true"></span>
                    {{ t('vendor.Rejected_Label', 'Not approved') }}
                  </span>
                @else
                  <span class="inline-flex items-center gap-1.5 rounded-full bg-gold/15 px-3 py-1 text-xs font-bold leading-5 text-ink">
                    <span class="h-1.5 w-1.5 rounded-full bg-gold" aria-hidden="true"></span>
                    {{ t('vendor.Pending_Label', 'Awaiting review') }}
                  </span>
                @endif
              </dd>
            </div>
            @if (filled($storeName))
              <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-3">
                <dt class="text-sm font-semibold text-muted">{{ t('vendor.Store_Name', 'Store name') }}</dt>
                <dd dir="auto" class="min-w-0 break-words text-sm font-bold text-ink">{{ $storeName }}</dd>
              </div>
            @endif
          </dl>

          @if ($isRejected && $rejectionReason !== null)
            <div id="rejection-reason" class="mt-4 rounded-[14px] border border-error/30 bg-error/10 px-4 py-3 text-start">
              <p class="text-sm font-bold leading-6 text-error">{{ t('vendor.Rejection_Reason', 'Reason') }}</p>
              <p class="mt-1 whitespace-pre-line break-words text-sm leading-6 text-ink">{{ $rejectionReason }}</p>
            </div>
          @endif

          <form method="POST" action="{{ route('vendor.logout') }}" class="mt-6 border-t border-line pt-5">
            @csrf
            <button type="submit" class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-[16px] border border-line/70 bg-surface text-sm font-bold text-olive transition-colors hover:border-olive hover:bg-canvas focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-olive focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
              <span>{{ t('vendor.Sign_Out', 'Sign out') }}</span>
              {{-- The arrow points toward the reading direction, so it is mirrored in RTL. --}}
              <svg class="h-4 w-4 rtl:-scale-x-100" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><polyline points="16 17 21 12 16 7" /><line x1="21" x2="9" y1="12" y2="12" /></svg>
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
        <p class="text-lg font-semibold leading-8 text-surface">{{ t('vendor.Login_Panel_Line', 'Manage your store and start your journey with Hirfah') }}</p>
      </div>
    </aside>
  </div>
</body>
</html>
