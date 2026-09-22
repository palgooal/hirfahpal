<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $account['label'] }} Password Reset</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
  <main class="mx-auto flex min-h-screen max-w-xl items-center px-4 py-10">
    <section class="w-full rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <h1 class="text-3xl font-extrabold">Reset Password</h1>
      <form class="mt-6 grid gap-5" action="{{ route($account['route'].'.password.email') }}" method="post">
        @csrf
        @if (session('status'))
          <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-700">{{ session('status') }}</div>
        @endif
        @error('email')
          <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700">{{ $message }}</div>
        @enderror
        <div>
          <label for="email" class="text-sm font-bold">Email</label>
          <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4" />
        </div>
        <button type="submit" class="h-12 rounded-xl bg-blue-600 px-5 font-extrabold text-white">Send reset link</button>
      </form>
    </section>
  </main>
</body>
</html>
