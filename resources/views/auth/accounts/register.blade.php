<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $account['label'] }} Register</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
  <main class="mx-auto flex min-h-screen max-w-2xl items-center px-4 py-10">
    <section class="w-full rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <p class="text-sm font-bold uppercase tracking-wide text-blue-600">{{ $account['label'] }}</p>
      <h1 class="mt-2 text-3xl font-extrabold">Create Account</h1>

      <form class="mt-6 grid gap-5" action="{{ route($account['route'].'.register.store') }}" method="post">
        @csrf

        @if ($errors->any())
          <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700">{{ $errors->first() }}</div>
        @endif

        <div>
          <label for="full_name" class="text-sm font-bold">Full name</label>
          <input id="full_name" name="full_name" type="text" value="{{ old('full_name') }}" required class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
          <div>
            <label for="phone" class="text-sm font-bold">Phone</label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4" />
          </div>
          <div>
            <label for="email" class="text-sm font-bold">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4" />
          </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
          <div>
            <label for="password" class="text-sm font-bold">Password</label>
            <input id="password" name="password" type="password" required class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4" />
          </div>
          <div>
            <label for="password_confirmation" class="text-sm font-bold">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4" />
          </div>
        </div>

        <label class="flex items-start gap-3 text-sm font-bold text-slate-700">
          <input type="checkbox" name="terms" value="1" @checked(old('terms')) required class="mt-1" />
          <span>I agree to the terms and privacy policy.</span>
        </label>

        <button type="submit" class="h-12 rounded-xl bg-blue-600 px-5 font-extrabold text-white">Create account</button>
      </form>

      <a href="{{ route($account['route'].'.login') }}" class="mt-5 inline-flex text-sm font-bold text-blue-600">Already have an account?</a>
    </section>
  </main>
</body>
</html>
