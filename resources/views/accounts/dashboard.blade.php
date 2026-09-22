<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $account['label'] }} Dashboard</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
  <header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
      <div>
        <p class="text-sm font-bold uppercase tracking-wide text-blue-600">{{ $account['label'] }}</p>
        <h1 class="text-2xl font-extrabold">Dashboard</h1>
      </div>
      <form action="{{ route($account['route'].'.logout') }}" method="post">
        @csrf
        <button class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold">Logout</button>
      </form>
    </div>
  </header>

  <main class="mx-auto grid max-w-6xl gap-5 px-4 py-8 sm:grid-cols-3">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2">
      <h2 class="text-xl font-extrabold">Welcome</h2>
      <p class="mt-2 text-sm leading-6 text-slate-600">This area is ready for {{ strtolower($account['label']) }} features.</p>
    </section>
    <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <p class="text-sm font-bold text-slate-500">Guard</p>
      <p class="mt-1 font-extrabold">{{ $account['guard'] }}</p>
    </aside>
  </main>
</body>
</html>
