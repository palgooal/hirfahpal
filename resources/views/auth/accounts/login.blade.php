<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $account['label'] }} Login</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
  <main class="mx-auto flex min-h-screen max-w-xl items-center px-4 py-10">
    <section class="w-full rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <p class="text-sm font-bold uppercase tracking-wide text-blue-600">{{ $account['label'] }}</p>
      <h1 class="mt-2 text-3xl font-extrabold">Login</h1>

      <form class="mt-6 grid gap-5" action="{{ route($account['route'].'.login.store') }}" method="post">
        @csrf

        @if ($errors->any())
          <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700">{{ $errors->first() }}</div>
        @endif

        @if (session('status'))
          <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-700">{{ session('status') }}</div>
        @endif

        <div>
          <label for="login" class="text-sm font-bold">Email or phone</label>
          <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username" class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4" />
        </div>

        <div>
          <div class="flex items-center justify-between gap-3">
            <label for="password" class="text-sm font-bold">Password</label>
            <a href="{{ route($account['route'].'.password.request') }}" class="text-sm font-bold text-blue-600">Forgot password?</a>
          </div>
          <input id="password" name="password" type="password" required autocomplete="current-password" class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4" />
        </div>

        <label class="flex items-center gap-3 text-sm font-bold text-slate-700">
          <input type="checkbox" name="remember" value="1" @checked(old('remember')) />
          Remember me
        </label>

        <button type="submit" class="h-12 rounded-xl bg-blue-600 px-5 font-extrabold text-white">Login</button>
      </form>

      <a href="{{ route($account['route'].'.register') }}" class="mt-5 inline-flex text-sm font-bold text-blue-600">Create {{ strtolower($account['label']) }} account</a>
    </section>
  </main>
</body>
</html>
