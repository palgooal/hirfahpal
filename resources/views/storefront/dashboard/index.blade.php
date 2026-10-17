<main data-dashboard-page>
  @include('storefront.account.page-header', [
      'current' => ($orderPage ?? false) ? 'الطلبات' : 'لوحة التحكم',
      'eyebrow' => 'مرحباً بعودتك، '.$customer->name,
      'title' => ($orderPage ?? false) ? 'الطلبات' : 'لوحة التحكم',
      'subtitle' => 'ملخص سريع لآخر طلباتك والقطع المحفوظة وإعدادات حسابك.',
  ])
  <section class="bg-canvas px-4 py-10 sm:py-12 lg:px-8">
    <div class="mx-auto grid max-w-[1216px] gap-6 lg:grid-cols-[250px_minmax(0,1fr)] lg:items-start">
      @include('storefront.account.sidebar', ['active' => ($orderPage ?? false) ? 'orders' : 'dashboard'])
      <div class="min-w-0">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(260px,.75fr)]">
          @forelse ($displayOrders ?? collect([$latestOrder]) as $latestOrder)
          <section class="overflow-hidden rounded-[20px] border border-line bg-surface shadow-card" aria-labelledby="latestOrderTitle">
            <div class="flex flex-col gap-4 border-b border-line px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
              <div class="text-start">
                <p class="text-xs font-bold text-copper">آخر طلب</p>
                <h2 id="latestOrderTitle" class="mt-1 text-[22px] font-semibold leading-8 text-ink"><bdi>{{ $latestOrder?->number ?? 'لا توجد طلبات بعد' }}</bdi></h2>
                <p class="mt-1 text-xs leading-5 text-muted"><bdi>{{ $latestOrder?->created_at->format('Y/m/d') ?? '—' }}</bdi> — <bdi>{{ $latestOrder?->vendorOrders->count() ?? 0 }}</bdi> تجار، <bdi>{{ $latestOrder?->items->sum('quantity') ?? 0 }}</bdi> منتجات</p>
              </div>
              <span class="w-fit rounded-full border border-copper/30 bg-copper/10 px-3 py-1.5 text-xs font-bold text-copper">
                {{ $latestOrder ? config('marketplace.order_statuses.'.$latestOrder->status, $latestOrder->status) : '—' }}
              </span>
            </div>
            <div class="divide-y divide-line/70">
              @foreach ($latestOrder?->vendorOrders ?? [] as $vendorOrder)
<div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <span class="flex min-w-0 items-center gap-3 text-start">
                  <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border-2 border-copper/20 bg-copper/10 text-sm font-bold text-copper">{{ $vendorOrder->vendor->profile?->store_initials }}</span>
                  <strong class="truncate text-sm font-bold text-ink">{{ $vendorOrder->vendor->profile?->store_name ?? 'متجر غير متاح' }}</strong>
                </span>
                <span class="w-fit rounded-full border border-olive/20 bg-olive/10 px-3 py-1 text-xs font-bold text-olive">{{ config('marketplace.vendor_statuses.'.$vendorOrder->status, $vendorOrder->status) }}</span>
                @if ($orderPage ?? false)
                    @foreach ($vendorOrder->items as $orderItem)
                        <span class="text-sm leading-6 text-muted">{{ $orderItem->product_name }} — {{ $orderItem->quantity }} × {{ number_format($orderItem->unit_price, 2) }} {{ $currency }} — {{ number_format($orderItem->line_total, 2) }} {{ $currency }}</span>
                    @endforeach
                    @if ($vendorOrder->status === 'delivered')
                      <form method="POST" action="{{ route('customer.orders.vendor-orders.confirm-receipt', [$latestOrder, $vendorOrder]) }}" class="shrink-0">@csrf @method('PATCH')
                        <button type="submit" class="rounded-[12px] border border-olive bg-surface px-3 py-2 text-xs font-bold text-olive">تأكيد الاستلام</button>
                      </form>
                    @elseif ($vendorOrder->status === 'completed')
                      @php($vendorReview = $latestOrder->reviews->first(fn ($review) => $review->vendor_order_id === $vendorOrder->id && $review->reviewable_type === 'vendor'))
                      @if ($vendorReview)
                        <span class="text-xs font-bold text-olive">تم إرسال تقييمك: {{ $vendorReview->rating }}/5</span>
                      @else
                        <form method="POST" action="{{ route('customer.orders.reviews.store', $latestOrder) }}" class="flex items-center gap-2">@csrf
                          <input type="hidden" name="vendor_order_id" value="{{ $vendorOrder->id }}">
                          <label class="text-xs font-semibold text-muted">تقييم المتجر <select name="vendor_rating" required class="rounded-lg border border-line bg-canvas px-2 py-1 text-ink"><option value="">—</option>@foreach (range(1, 5) as $rating)<option value="{{ $rating }}">{{ $rating }}</option>@endforeach</select></label>
                          <button type="submit" class="rounded-[12px] border border-olive bg-surface px-3 py-2 text-xs font-bold text-olive">إرسال التقييم</button>
                        </form>
                      @endif
                    @endif
                @endif
              </div>
@endforeach

            </div>
            <div class="flex flex-col gap-3 border-t border-line bg-canvas/50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
              <strong class="text-lg font-bold text-olive"><bdi>{{ number_format($latestOrder?->grand_total ?? 0, 2) }} {{ $currency }}</bdi></strong>
              <a href="{{ $latestOrder ? route('customer.orders.show', $latestOrder) : route('browse') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-[12px] bg-olive px-4 text-xs font-bold text-surface">
                <span>عرض تفاصيل الطلب</span><i data-lucide="chevron-left" class="h-4 w-4"></i>
              </a>
            </div>
          </section>
          @empty
              <p class="text-sm text-muted">لا توجد طلبات بعد.</p>
          @endforelse
          @if (isset($displayOrders) && $displayOrders instanceof \Illuminate\Contracts\Pagination\Paginator)
              {{ $displayOrders->links() }}
          @endif

          <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <a href="{{ route('customer.favorites') }}" class="flex min-h-32 items-center justify-between gap-4 rounded-[20px] border border-line bg-surface p-5 text-start shadow-card transition-colors hover:border-olive/40">
              <span>
                <span class="block text-sm font-semibold text-muted">المفضلة</span>
                <strong class="mt-2 block text-3xl font-bold text-ink"><bdi>{{ $favoriteCount }}</bdi></strong>
                <span class="mt-1 block text-xs text-muted">قطع محفوظة</span>
              </span>
              <span class="flex h-12 w-12 items-center justify-center rounded-full bg-copper/10 text-copper"><i data-lucide="heart" class="h-5 w-5"></i></span>
            </a>
            <a href="{{ route('customer.account-details') }}" class="flex min-h-28 items-center justify-between gap-4 rounded-[20px] border border-line bg-surface p-5 text-start shadow-card transition-colors hover:border-olive/40">
              <span><strong class="block text-base font-bold text-ink">تفاصيل الحساب</strong><span class="mt-1 block text-xs leading-5 text-muted">تحديث المعلومات وكلمة المرور</span></span>
              <i data-lucide="chevron-left" class="h-4 w-4 text-muted"></i>
            </a>
            <a href="{{ route('customer.addresses') }}" class="flex min-h-28 items-center justify-between gap-4 rounded-[20px] border border-line bg-surface p-5 text-start shadow-card transition-colors hover:border-olive/40">
              <span><strong class="block text-base font-bold text-ink">العنوان</strong><span class="mt-1 block text-xs leading-5 text-muted">إدارة عناوين التوصيل</span></span>
              <i data-lucide="chevron-left" class="h-4 w-4 text-muted"></i>
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>
