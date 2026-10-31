<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>لوحة مندوب التوصيل</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
            <div>
                <p class="text-sm font-bold text-amber-700">HIRFAH</p>
                <h1 class="text-2xl font-extrabold">مهام التوصيل</h1>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm font-semibold">{{ $driver->name }}</span>
                <form action="{{ route('delivery-driver.logout') }}" method="POST">
                    @csrf
                    <button class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold">تسجيل الخروج</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
        @if (session('success'))
            <p class="mb-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <p class="mb-5 rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errors->first() }}</p>
        @endif

        <section class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">مهام جديدة</p><strong class="mt-1 block text-3xl">{{ $assignedCount }}</strong></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">قيد التوصيل</p><strong class="mt-1 block text-3xl">{{ $activeCount }}</strong></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">تم التسليم</p><strong class="mt-1 block text-3xl">{{ $deliveredCount }}</strong></div>
        </section>

        <section class="space-y-4">
            @forelse ($assignments as $assignment)
                @php
                    $vendorOrder = $assignment->vendorOrder;
                    $statusLabels = [
                        'assigned' => 'بانتظار قبولك',
                        'accepted' => 'مقبول — بانتظار الاستلام',
                        'picked_up' => 'تم الاستلام — قيد التوصيل',
                        'out_for_delivery' => 'قيد التوصيل',
                        'delivered' => 'تم التسليم',
                        'failed' => 'تعذر التوصيل',
                        'cancelled' => 'ملغي',
                    ];
                @endphp
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                        <div>
                            <p class="text-sm text-slate-500">{{ $vendorOrder->number }}</p>
                            <h2 class="mt-1 text-xl font-extrabold">{{ $vendorOrder->vendor?->profile?->store_name ?? $vendorOrder->vendor?->name }}</h2>
                            <p class="mt-2 text-sm text-slate-600">العميل: {{ $vendorOrder->order?->customer?->name ?? '—' }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $vendorOrder->items->pluck('product_name')->join('، ') }}</p>
                        </div>
                        <span class="w-fit rounded-full bg-amber-50 px-3 py-1 text-sm font-bold text-amber-800">{{ $statusLabels[$assignment->status] ?? $assignment->status }}</span>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-100 pt-4">
                        @if ($assignment->status === 'assigned')
                            <form method="POST" action="{{ route('delivery-driver.dashboard.assignments.accept', $assignment) }}">@csrf @method('PATCH')<button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">قبول المهمة</button></form>
                        @elseif ($assignment->status === 'accepted')
                            <form method="POST" action="{{ route('delivery-driver.dashboard.assignments.pick-up', $assignment) }}">@csrf @method('PATCH')<button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">تم استلام الطلب</button></form>
                        @elseif (in_array($assignment->status, ['picked_up', 'out_for_delivery'], true))
                            <form method="POST" action="{{ route('delivery-driver.dashboard.assignments.delivered', $assignment) }}">@csrf @method('PATCH')<button class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white">تم التسليم</button></form>
                            <form method="POST" action="{{ route('delivery-driver.dashboard.assignments.failed', $assignment) }}" class="flex flex-wrap gap-2">@csrf @method('PATCH')<input name="failure_reason" required maxlength="1000" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="سبب تعذر التوصيل"><button class="rounded-xl border border-red-200 px-4 py-2 text-sm font-bold text-red-700">تعذر التوصيل</button></form>
                        @endif
                    </div>
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500">لا توجد مهام توصيل حالياً.</p>
            @endforelse
        </section>
    </main>
</body>
</html>
