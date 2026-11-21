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

        <section class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- E2-MVP batch 05: the cash collected on delivery and not yet handed to the platform. --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-cash-balance><p class="text-sm text-slate-500">النقد في عهدتك</p><strong class="mt-1 block text-3xl" dir="ltr">{{ $cashBalance }}</strong></div>
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
                    // E2-MVP batch 03: a task the driver declined is cancelled, shown as declined.
                    if ($assignment->declined_at !== null) {
                        $statusLabels['cancelled'] = 'رفضتَ هذه المهمة';
                    }
                @endphp
                @php
                    // E2-MVP batch 02: the card shows only what DeliveryDriverAssignmentCard gives (no model fields).
                    $card = $cards[$assignment->id];
                    $linkClass = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900';
                @endphp
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-assignment-card="{{ $assignment->id }}">
                    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                        <div class="min-w-0">
                            <p class="text-sm text-slate-500" dir="ltr">{{ $card['number'] }}</p>
                            <h2 class="mt-1 break-words text-xl font-extrabold">{{ $card['store_name'] }}</h2>
                            @if ($card['payment_method'])
                                <p class="mt-2 text-sm text-slate-600" data-payment-method>طريقة الدفع: <span class="font-bold text-slate-800">{{ $card['payment_method'] }}</span></p>
                            @endif
                            {{-- E2-MVP batch 05: what to collect on an active cash-on-delivery task, and what was collected. --}}
                            @if ($card['cod_amount'])
                                <p class="mt-1 text-sm text-slate-600" data-cod-amount>المبلغ المطلوب تحصيله: <span class="font-bold text-slate-900" dir="ltr">{{ $card['cod_amount'] }}</span></p>
                            @elseif ($card['cod_collected'])
                                <p class="mt-1 text-sm text-slate-600" data-cod-collected>تم تحصيل: <span class="font-bold text-slate-900" dir="ltr">{{ $card['cod_collected'] }}</span></p>
                            @endif
                        </div>
                        <span class="w-fit rounded-full bg-amber-50 px-3 py-1 text-sm font-bold text-amber-800">{{ $statusLabels[$assignment->status] ?? $assignment->status }}</span>
                    </div>

                    <div class="mt-4">
                        <h3 class="text-sm font-bold text-slate-500">المنتجات</h3>
                        <ul class="mt-2 space-y-1" data-card-items>
                            @foreach ($card['items'] as $item)
                                <li class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="min-w-0 break-words">{{ $item['name'] }}</span>
                                    <span class="shrink-0 font-bold" dir="ltr">× {{ $item['quantity'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    @if ($card['customer'] || $card['vendor'])
                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            @if ($card['vendor'])
                                <section class="rounded-xl bg-slate-50 p-4" data-card-vendor>
                                    <h3 class="text-sm font-bold text-slate-500">الاستلام من المتجر</h3>
                                    <p class="mt-1 break-words font-bold">{{ $card['vendor']['name'] }}</p>
                                    <p class="mt-1 break-words text-sm text-slate-700">{{ $card['vendor']['address'] ?? 'لا يوجد عنوان مسجل للمتجر' }}</p>
                                    @if ($card['vendor']['phone'])
                                        <p class="mt-1 text-sm text-slate-700" dir="ltr">{{ $card['vendor']['phone'] }}</p>
                                    @endif
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @if ($card['vendor']['tel'])
                                            <a href="{{ $card['vendor']['tel'] }}" class="{{ $linkClass }}" data-call="vendor">اتصال بالمتجر</a>
                                        @endif
                                        @if ($card['vendor']['maps_url'])
                                            <a href="{{ $card['vendor']['maps_url'] }}" target="_blank" rel="noopener noreferrer" class="{{ $linkClass }}" data-directions="vendor">الاتجاهات إلى المتجر</a>
                                        @endif
                                    </div>
                                </section>
                            @endif
                            @if ($card['customer'])
                                <section class="rounded-xl bg-slate-50 p-4" data-card-customer>
                                    <h3 class="text-sm font-bold text-slate-500">التسليم إلى</h3>
                                    <p class="mt-1 break-words font-bold">{{ $card['customer']['name'] ?? '—' }}</p>
                                    <p class="mt-1 break-words text-sm text-slate-700">{{ $card['customer']['address'] ?? 'لا يوجد عنوان توصيل مسجل' }}</p>
                                    @if ($card['customer']['notes'])
                                        <p class="mt-1 whitespace-pre-line break-words text-sm text-slate-600">ملاحظات: {{ $card['customer']['notes'] }}</p>
                                    @endif
                                    @if ($card['customer']['phone'])
                                        <p class="mt-1 text-sm text-slate-700" dir="ltr">{{ $card['customer']['phone'] }}</p>
                                    @endif
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @if ($card['customer']['tel'])
                                            <a href="{{ $card['customer']['tel'] }}" class="{{ $linkClass }}" data-call="customer">اتصال بالمستلم</a>
                                        @endif
                                        @if ($card['customer']['maps_url'])
                                            <a href="{{ $card['customer']['maps_url'] }}" target="_blank" rel="noopener noreferrer" class="{{ $linkClass }}" data-directions="customer">الاتجاهات إلى المستلم</a>
                                        @endif
                                    </div>
                                </section>
                            @endif
                        </div>
                    @endif

                    @if ($card['reason'])
                        {{-- E2-MVP batch 03: the reason the driver gave (decline or failed delivery). --}}
                        <p class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-700" data-card-reason>
                            <span class="font-bold">{{ $card['declined'] ? 'سبب الرفض' : 'سبب التعذر' }}:</span>
                            <span class="whitespace-pre-line break-words">{{ $card['reason'] }}</span>
                        </p>
                    @endif

                    @php
                        $fieldClass = 'min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm';
                    @endphp
                    <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-100 pt-4">
                        @if ($assignment->status === 'assigned')
                            <form method="POST" action="{{ route('delivery-driver.dashboard.assignments.accept', $assignment) }}">@csrf @method('PATCH')<button class="min-h-11 rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">قبول المهمة</button></form>
                            {{-- E2-MVP batch 03: decline before accepting; "أخرى" needs the text (checked on the server). --}}
                            <form method="POST" action="{{ route('delivery-driver.dashboard.assignments.decline', $assignment) }}" class="grid w-full gap-2 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)_auto]" data-decline-form>
                                @csrf @method('PATCH')
                                <label class="sr-only" for="decline_code_{{ $assignment->id }}">سبب رفض المهمة</label>
                                <select id="decline_code_{{ $assignment->id }}" name="decline_code" required class="{{ $fieldClass }}">
                                    <option value="">اختر سبب الرفض</option>
                                    @foreach (\App\Support\Orders\DeliveryReasons::DECLINE as $code => $label)
                                        <option value="{{ $code }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <label class="sr-only" for="decline_note_{{ $assignment->id }}">تفاصيل السبب</label>
                                <input id="decline_note_{{ $assignment->id }}" name="decline_note" maxlength="1000" class="{{ $fieldClass }}" placeholder="التفاصيل (إلزامية عند اختيار «أخرى»)">
                                <button class="min-h-11 rounded-xl border border-red-200 px-4 py-2 text-sm font-bold text-red-700">رفض المهمة</button>
                            </form>
                        @elseif ($assignment->status === 'accepted')
                            <form method="POST" action="{{ route('delivery-driver.dashboard.assignments.pick-up', $assignment) }}">@csrf @method('PATCH')<button class="min-h-11 rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">تم استلام الطلب</button></form>
                        @elseif (in_array($assignment->status, ['picked_up', 'out_for_delivery'], true))
                            <form method="POST" action="{{ route('delivery-driver.dashboard.assignments.delivered', $assignment) }}" class="flex flex-wrap items-end gap-2" data-delivered-form>@csrf @method('PATCH')
                                @if ($card['cod_amount'])
                                    {{-- E2-MVP batch 05: the driver confirms the amount collected; the server requires the full amount. --}}
                                    <label class="grid gap-1 text-sm" for="collected_amount_{{ $assignment->id }}">
                                        <span>المبلغ المحصّل</span>
                                        <input id="collected_amount_{{ $assignment->id }}" name="collected_amount" required inputmode="decimal" dir="ltr" class="min-h-11 w-36 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                                    </label>
                                @endif
                                <button class="min-h-11 rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white">تم التسليم</button>
                            </form>
                            {{-- E2-MVP batch 03: a reason from the list, with an optional note ("أخرى" needs it). --}}
                            <form method="POST" action="{{ route('delivery-driver.dashboard.assignments.failed', $assignment) }}" class="grid w-full gap-2 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)_auto]" data-failure-form>
                                @csrf @method('PATCH')
                                <label class="sr-only" for="failure_code_{{ $assignment->id }}">سبب تعذر التوصيل</label>
                                <select id="failure_code_{{ $assignment->id }}" name="failure_code" required class="{{ $fieldClass }}">
                                    <option value="">اختر سبب التعذر</option>
                                    @foreach (\App\Support\Orders\DeliveryReasons::FAILURE as $code => $label)
                                        <option value="{{ $code }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <label class="sr-only" for="failure_reason_{{ $assignment->id }}">ملاحظة</label>
                                <input id="failure_reason_{{ $assignment->id }}" name="failure_reason" maxlength="1000" class="{{ $fieldClass }}" placeholder="ملاحظة (اختيارية، إلزامية عند اختيار «أخرى»)">
                                <button class="min-h-11 rounded-xl border border-red-200 px-4 py-2 text-sm font-bold text-red-700">تعذر التوصيل</button>
                            </form>
                        @elseif ($assignment->status === 'failed' && $vendorOrder->status === 'out_for_delivery')
                            {{-- E2-MVP custody fix: the driver declares the return; the platform confirms it and closes the custody. --}}
                            @if ($card['return_declared'])
                                <div class="w-full rounded-xl bg-amber-50 p-3 text-sm text-amber-900" data-return-declared>أقررتَ بإعادة الشحنة إلى البائع. تبقى الشحنة في عهدتك حتى تؤكد الإدارة استلام البائع لها.</div>
                            @else
                                <div class="w-full rounded-xl bg-amber-50 p-3 text-sm text-amber-900">يجب إعادة الشحنة إلى البائع. تبقى في عهدتك حتى تؤكد الإدارة استلام البائع لها.</div>
                                <form method="POST" action="{{ route('delivery-driver.dashboard.assignments.return-to-vendor', $assignment) }}">@csrf @method('PATCH')<button class="min-h-11 rounded-xl border border-amber-300 px-4 py-2 text-sm font-bold text-amber-900">تمت إعادة الشحنة إلى البائع</button></form>
                            @endif
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
