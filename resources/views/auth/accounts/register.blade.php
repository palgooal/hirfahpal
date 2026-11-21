@php
    // The delivery driver registration page, in the vendor portal's design (auth/vendor/register). Direction comes from
    // the current Language row (is_rtl); a locale with no Language row falls back to the HTML default direction (ltr).
    $pageDirection = $currentLanguage?->is_rtl ? 'rtl' : 'ltr';
    // Driver strings: seeded by DeliveryDriverAuthTranslationSeeder, with the same text as the default until it runs.
    $driverText = fn (string $key): string => t($key, \Database\Seeders\DeliveryDriverAuthTranslationSeeder::fallback($key));

    $inputClass = 'h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-start text-sm text-ink transition-colors placeholder:text-muted/70 focus:border-olive focus:outline-none focus-visible:ring-4 focus-visible:ring-olive/20 aria-[invalid=true]:border-error';
    $passwordClass = 'h-12 w-full rounded-[14px] border border-line bg-canvas ps-4 pe-14 text-sm text-ink transition-colors placeholder:text-muted/70 focus:border-olive focus:outline-none focus-visible:ring-4 focus-visible:ring-olive/20 aria-[invalid=true]:border-error';
    $labelClass = 'mb-2 block text-sm font-semibold leading-5 text-ink';
    $errorClass = 'mt-2 text-start text-sm font-semibold leading-6 text-error';
    // Every field is required (CreateAccountUser); the mark is visual only, the inputs carry `required`.
    $requiredMark = '<span class="ms-0.5 text-error" aria-hidden="true">*</span>';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $pageDirection }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $driverText('driver.Register_Title') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet" />
  @vite(['resources/css/vendor-auth.css', 'resources/js/admin-auth.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-canvas font-cairo text-ink antialiased">
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

        <section class="rounded-[20px] border border-line bg-surface p-5 shadow-card sm:p-8" aria-labelledby="driver-register-title">
          <p class="mb-3 inline-flex items-center gap-2 rounded-full bg-sage/20 px-3 py-1 text-xs font-bold leading-5 text-olive">
            <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2" /><path d="M15 18H9" /><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14" /><circle cx="17" cy="18" r="2" /><circle cx="7" cy="18" r="2" /></svg>
            {{ $driverText('driver.Portal') }}
          </p>
          <h1 id="driver-register-title" class="text-2xl font-bold leading-8 text-ink">{{ $driverText('driver.Register_Heading') }}</h1>
          <p class="mt-2 text-sm leading-6 text-muted">{{ $driverText('driver.Register_Description') }}</p>

          {{-- A new driver account starts pending (CreateAccountUser): delivery services follow the review. --}}
          <div class="mt-5 flex items-start gap-3 rounded-[14px] border border-sage/40 bg-sage/10 px-4 py-3 text-start" data-review-notice>
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-olive" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="M12 16v-4" /><path d="M12 8h.01" /></svg>
            <p class="text-sm font-semibold leading-6 text-olive">{{ $driverText('driver.Review_Notice') }}</p>
          </div>

          <form method="POST" action="{{ route($account['route'].'.register.store') }}" class="mt-6 flex flex-col gap-5" novalidate data-submit-lock>
            @csrf

            <p class="text-start text-xs font-medium leading-5 text-ink/75">{!! $requiredMark !!} {{ $driverText('driver.Required_Fields') }}</p>

            <div>
              <label for="full_name" class="{{ $labelClass }}">{{ $driverText('driver.Full_Name') }}{!! $requiredMark !!}</label>
              <input
                id="full_name"
                name="full_name"
                type="text"
                value="{{ old('full_name') }}"
                required
                autofocus
                maxlength="255"
                autocomplete="name"
                @error('full_name') aria-invalid="true" aria-describedby="full_name-error" @enderror
                class="{{ $inputClass }}"
              />
              @error('full_name')
                <p id="full_name-error" role="alert" class="{{ $errorClass }}">{{ $message }}</p>
              @enderror
            </div>

            <div>
              <label for="phone" class="{{ $labelClass }}">{{ $driverText('driver.Phone') }}{!! $requiredMark !!}</label>
              <input
                id="phone"
                name="phone"
                type="tel"
                dir="ltr"
                value="{{ old('phone') }}"
                required
                maxlength="30"
                autocomplete="tel"
                placeholder="05xxxxxxxx"
                @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror
                class="{{ $inputClass }}"
              />
              @error('phone')
                <p id="phone-error" role="alert" class="{{ $errorClass }}">{{ $message }}</p>
              @enderror
            </div>

            <div>
              <label for="email" class="{{ $labelClass }}">{{ $driverText('driver.Email') }}{!! $requiredMark !!}</label>
              <input
                id="email"
                name="email"
                type="email"
                dir="ltr"
                value="{{ old('email') }}"
                required
                maxlength="255"
                autocomplete="email"
                placeholder="example@email.com"
                @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                class="{{ $inputClass }}"
              />
              @error('email')
                <p id="email-error" role="alert" class="{{ $errorClass }}">{{ $message }}</p>
              @enderror
            </div>

            {{-- E2-MVP batch 01: the service area, the active governorates and their active cities. The cities are grouped
                 under their governorate; the server checks that the city belongs to the governorate (LocationPair). --}}
            <div>
              <label for="governorate_id" class="{{ $labelClass }}">{{ $driverText('driver.Governorate') }}{!! $requiredMark !!}</label>
              <select
                id="governorate_id"
                name="governorate_id"
                required
                @error('governorate_id') aria-invalid="true" aria-describedby="governorate_id-error" @enderror
                class="{{ $inputClass }}"
              >
                <option value="">{{ $driverText('driver.Choose_Governorate') }}</option>
                @foreach ($governorates as $governorate)
                  <option value="{{ $governorate->id }}" @selected((string) old('governorate_id') === (string) $governorate->id)>{{ $governorate->name }}</option>
                @endforeach
              </select>
              @error('governorate_id')
                <p id="governorate_id-error" role="alert" class="{{ $errorClass }}">{{ $message }}</p>
              @enderror
            </div>

            <div>
              <label for="city_id" class="{{ $labelClass }}">{{ $driverText('driver.City') }}{!! $requiredMark !!}</label>
              <select
                id="city_id"
                name="city_id"
                required
                @error('city_id') aria-invalid="true" aria-describedby="city_id-error" @enderror
                class="{{ $inputClass }}"
              >
                <option value="">{{ $driverText('driver.Choose_City') }}</option>
                @foreach ($governorates as $governorate)
                  @if ($governorate->cities->isNotEmpty())
                    <optgroup label="{{ $governorate->name }}">
                      @foreach ($governorate->cities as $city)
                        <option value="{{ $city->id }}" @selected((string) old('city_id') === (string) $city->id)>{{ $city->name }}</option>
                      @endforeach
                    </optgroup>
                  @endif
                @endforeach
              </select>
              @error('city_id')
                <p id="city_id-error" role="alert" class="{{ $errorClass }}">{{ $message }}</p>
              @enderror
            </div>

            <div>
              <label for="vehicle_type" class="{{ $labelClass }}">{{ $driverText('driver.Vehicle_Type') }}{!! $requiredMark !!}</label>
              <input
                id="vehicle_type"
                name="vehicle_type"
                type="text"
                value="{{ old('vehicle_type') }}"
                required
                maxlength="255"
                placeholder="{{ $driverText('driver.Vehicle_Type_Placeholder') }}"
                @error('vehicle_type') aria-invalid="true" aria-describedby="vehicle_type-error" @enderror
                class="{{ $inputClass }}"
              />
              @error('vehicle_type')
                <p id="vehicle_type-error" role="alert" class="{{ $errorClass }}">{{ $message }}</p>
              @enderror
            </div>

            {{-- Passwords are never refilled after a failed submit; a mismatch is reported on password. --}}
            @foreach ([
                ['password', t('dashboard.Password', 'Password')],
                ['password_confirmation', $driverText('driver.Confirm_Password')],
            ] as [$field, $label])
              <div>
                <label for="{{ $field }}" class="{{ $labelClass }}">{{ $label }}{!! $requiredMark !!}</label>
                <div class="relative">
                  <input
                    id="{{ $field }}"
                    name="{{ $field }}"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="••••••••"
                    @if ($field === 'password')
                      aria-describedby="password-hint{{ $errors->has('password') ? ' password-error' : '' }}"
                      @error('password') aria-invalid="true" @enderror
                    @endif
                    class="{{ $passwordClass }}"
                  />
                  <button
                    type="button"
                    data-password-toggle
                    aria-controls="{{ $field }}"
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
                @if ($field === 'password')
                  <p id="password-hint" class="mt-2 text-start text-xs font-medium leading-5 text-ink/75">{{ $driverText('driver.Password_Hint') }}</p>
                  @error('password')
                    <p id="password-error" role="alert" class="{{ $errorClass }}">{{ $message }}</p>
                  @enderror
                @endif
              </div>
            @endforeach

            <div>
              <label for="terms" class="inline-flex min-h-11 cursor-pointer items-start gap-2 text-sm font-semibold leading-6 text-ink">
                <input
                  id="terms"
                  type="checkbox"
                  name="terms"
                  value="1"
                  required
                  @checked(old('terms'))
                  @error('terms') aria-invalid="true" aria-describedby="terms-error" @enderror
                  class="mt-1 h-4 w-4 shrink-0 cursor-pointer accent-olive focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-olive"
                />
                <span>{{ $driverText('driver.Terms_Agreement') }}{!! $requiredMark !!}</span>
              </label>
              @error('terms')
                <p id="terms-error" role="alert" class="{{ $errorClass }}">{{ $message }}</p>
              @enderror
            </div>

            <button type="submit" data-loading-label="{{ $driverText('driver.Creating_Account') }}" class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-[16px] bg-olive text-sm font-bold text-surface shadow-sm transition-colors hover:bg-olive-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-olive focus-visible:ring-offset-2 focus-visible:ring-offset-surface aria-disabled:cursor-wait aria-disabled:opacity-80">
              <span>{{ $driverText('driver.Create_Account') }}</span>
              {{-- The icon follows the reading direction, so it is mirrored in RTL. --}}
              <svg class="h-4 w-4 rtl:-scale-x-100" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><line x1="19" x2="19" y1="8" y2="14" /><line x1="22" x2="16" y1="11" y2="11" /></svg>
            </button>
            {{-- Filled by admin-auth.js while submitting; screen readers only, the button shows the same text. --}}
            <p data-submit-status role="status" aria-live="polite" class="sr-only"></p>
          </form>

          <p class="mt-6 flex flex-wrap items-center justify-center gap-x-1.5 border-t border-line pt-5 text-center text-sm leading-6 text-muted">
            <span>{{ $driverText('driver.Have_Account') }}</span>
            <a href="{{ route($account['route'].'.login') }}" class="inline-flex min-h-11 items-center rounded-md font-bold text-olive underline underline-offset-2 hover:text-olive-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-olive">{{ t('dashboard.Sign_In', 'Sign in') }}</a>
          </p>
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
        <p class="text-lg font-semibold leading-8 text-surface">{{ $driverText('driver.Register_Panel_Line') }}</p>
      </div>
    </aside>
  </div>
</body>
</html>
