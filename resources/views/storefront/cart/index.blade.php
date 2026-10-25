<main data-cart-page>
  <section class="border-b border-line/60 bg-canvas px-4 py-6 lg:px-8">
    <div class="mx-auto flex max-w-[1216px] flex-col gap-5">
      <nav class="flex items-center gap-2 text-xs font-medium leading-4 text-muted sm:text-sm"
        aria-label="مسار الصفحة">
        <a href="{{ route('home') }}" class="hover:text-olive">الرئيسية</a>
        <span class="text-line">/</span>
        <span class="font-semibold text-olive">سلة المشتريات</span>
      </nav>
      <div class="flex max-w-3xl flex-col items-start gap-2 text-start">
        <p class="flex items-center gap-1.5 text-xs font-bold uppercase leading-4 tracking-[.6px] text-copper"><span
            class="h-1.5 w-1.5 rounded-full bg-copper"></span>مراجعة الطلب قبل الدفع</p>
        <h1 class="text-[30px] font-bold leading-9 text-ink sm:text-4xl sm:leading-[48px]">سلة المشتريات</h1>
        <p class="text-sm leading-6 text-muted sm:text-base">المنتجات هنا مرتبة حسب التاجر حتى تراجع كل مجموعة
          فرعية بوضوح قبل إتمام الطلب.</p>
      </div>
    </div>
  </section>

  <section class="bg-canvas px-4 py-10 sm:py-12 lg:px-8">
    <div
      class="reveal opacity-100 translate-y-0 transition-[opacity,transform] duration-[650ms] ease-out sm:translate-y-[22px] sm:opacity-0 motion-reduce:sm:translate-y-0 motion-reduce:sm:opacity-100 mx-auto flex max-w-[1216px] flex-col gap-7">
      <div id="cartPopulatedState" class="{{ $cartItems->isEmpty() ? 'hidden' : 'grid' }} gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
        <div class="flex min-w-0 flex-col gap-5" data-cart-vendor-groups>
          @foreach ($cartGroups as $items)
@php($groupVendor = $items->first()->product->vendor)
<article class="cart-vendor-group overflow-hidden rounded-[20px] border border-line bg-surface shadow-card"
            data-vendor-slug="{{ $groupVendor->profile->slug }}">
            <header class="flex flex-col gap-4 border-b border-line/70 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
              <a href="{{ route('vendors.profile', ['storeSlug' => $groupVendor->profile->slug]) }}" class="flex min-w-0 items-center justify-start gap-3 text-start">
                <span
                  class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border-2 border-copper/20 bg-copper/10 text-lg font-bold leading-7 text-copper">{{ $groupVendor->profile->store_initials }}</span>
                <span class="min-w-0">
                  <strong class="block truncate text-base font-bold leading-6 text-ink">{{ $groupVendor->profile->store_name }}</strong>
                  <span class="block text-xs leading-4 text-muted">كل قطعة من هذا المشغل تُجهّز يدوياً</span>
                </span>
              </a>
              <span class="w-fit rounded-full border border-copper/20 bg-copper/10 px-3 py-1 text-xs font-bold text-copper">مجموعة
                تاجر مستقلة</span>
            </header>

            <div class="divide-y divide-line/70">
              @foreach ($items as $item)
<article class="cart-line-item grid gap-4 px-4 py-5 sm:grid-cols-[96px_minmax(0,1fr)] sm:px-5 lg:grid-cols-[104px_minmax(0,1fr)_150px]"
                data-unit-price="{{ $item->product->sellingPrice() }}" data-max-quantity="{{ $item->product->availableStockQuantity() }}">
                <a href="{{ route('product', ['slug' => $item->product->slug]) }}" class="block h-24 w-24 overflow-hidden rounded-[16px] border border-line bg-canvas sm:h-24 sm:w-24 lg:h-[104px] lg:w-[104px]">
                  <img src="{{ \App\Support\Storefront\HomepageMedia::url($item->product->primaryImage?->path) }}" alt="{{ $item->product->name }}"
                    class="h-full w-full object-cover">
                </a>
                <div class="min-w-0 text-start">
                  <h2 class="text-base font-bold leading-6 text-ink">{{ $item->product->name }}</h2>
                  <p class="mt-1 text-xs leading-5 text-muted">{{ $item->product->short_description }}</p>
                  <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:max-w-[420px]">
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">سعر القطعة</span>
                      @if ($item->product->hasDiscount())<span class="block text-xs text-muted line-through"><bdi>{{ number_format($item->product->price, 2) }} {{ $currency }}</bdi></span>@endif<strong class="block text-sm font-bold leading-6 text-ink"><bdi>{{ number_format($item->product->sellingPrice(), 2) }} {{ $currency }}</bdi></strong>
                    </div>
                    <div>
                      <span class="block text-[11px] font-semibold leading-4 text-muted">الكمية</span>
                      <div class="mt-1 flex h-[42px] w-fit items-center overflow-hidden rounded-xl border border-line bg-surface shadow-sm">
                        <button form="cart-minus-{{ $item->id }}" type="submit"
                          @disabled($item->quantity <= 1) class="cart-qty-decrease flex h-full w-10 items-center justify-center text-muted transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40"
                          aria-label="إنقاص الكمية"><i data-lucide="minus" class="h-4 w-4"></i></button><form hidden id="cart-minus-{{ $item->id }}" method="POST" action="{{ route('customer.cart.items.update', $item) }}">@csrf
@method('PATCH')<input type="hidden" name="quantity" value="{{ max(1, $item->quantity - 1) }}"></form>
                        <bdi class="cart-qty flex h-full min-w-10 items-center justify-center border-s border-e border-line px-3 text-sm font-bold text-ink">{{ $item->quantity }}</bdi>
                        <button form="cart-plus-{{ $item->id }}" type="submit"
                          @disabled(!$item->product->isSellable() || $item->quantity >= $item->product->availableStockQuantity()) class="cart-qty-increase flex h-full w-10 items-center justify-center text-olive transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40"
                          aria-label="زيادة الكمية"><i data-lucide="plus" class="h-4 w-4"></i></button><form hidden id="cart-plus-{{ $item->id }}" method="POST" action="{{ route('customer.cart.items.update', $item) }}">@csrf
@method('PATCH')<input type="hidden" name="quantity" value="{{ $item->quantity + 1 }}"></form>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-line/60 pt-4 sm:col-start-2 lg:col-start-auto lg:flex-col lg:items-end lg:border-t-0 lg:pt-0">
                  <div class="text-start lg:text-end">
                    <span class="block text-[11px] font-semibold leading-4 text-muted">إجمالي السطر</span>
                    <strong class="block text-lg font-bold leading-7 text-olive" data-line-total><bdi>{{ number_format($item->quantity * $item->product->sellingPrice(), 2) }} {{ $currency }}</bdi></strong>
                  </div>
                  <button form="cart-remove-{{ $item->id }}" type="submit"
                    class="cart-remove-item inline-flex h-10 w-10 items-center justify-center rounded-full border border-line bg-canvas text-muted transition-colors hover:border-copper/40 hover:text-copper"
                    aria-label="إزالة إبريق فخار مقدسي مزخرف"><i data-lucide="trash-2" class="h-4 w-4"></i></button><form hidden id="cart-remove-{{ $item->id }}" method="POST" action="{{ route('customer.cart.items.destroy', $item) }}">@csrf
@method('DELETE')</form>
                </div>
              </article>
@endforeach

            </div>

            <footer class="flex items-center justify-between gap-4 border-t border-line bg-canvas px-4 py-4 text-start sm:px-5">
              <span class="text-sm font-semibold leading-6 text-muted">المجموع الفرعي</span>
              <strong class="text-xl font-bold leading-8 text-ink" data-vendor-subtotal><bdi>{{ number_format($items->sum(fn ($item) => $item->quantity * $item->product->sellingPrice()), 2) }} {{ $currency }}</bdi></strong>
            </footer>
          </article>
@endforeach

        </div>

        <aside class="rounded-[20px] border border-line bg-surface p-5 shadow-card lg:sticky lg:top-24">
          <div class="flex items-center justify-between gap-4 border-b border-line pb-4">
            <div class="text-start">
              <h2 class="text-[22px] font-semibold leading-8 text-ink">ملخص السلة</h2>
              <p class="text-xs leading-5 text-muted"><span data-cart-line-count><bdi>{{ $cartItems->count() }}</bdi></span> منتجات من
                <span data-cart-vendor-count><bdi>{{ $cartGroups->count() }}</bdi></span> متاجر</p>
            </div>
            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-copper/10 text-copper">
              <i data-lucide="shopping-cart" class="h-5 w-5"></i>
            </span>
          </div>

          <div class="grid gap-3 py-5 text-sm">
            <div class="flex items-center justify-between gap-4 text-muted">
              <span>مجموع المنتجات</span>
              <strong class="font-bold text-ink" data-grand-total><bdi>{{ number_format($subtotal, 2) }} {{ $currency }}</bdi></strong>
            </div>
            <div class="rounded-[16px] border border-line bg-canvas p-4 text-start">
              <p class="text-xs font-semibold leading-5 text-muted">قبل تكلفة التوصيل</p>
              <p class="mt-1 text-sm leading-6 text-ink">التوصيل يُحسب لاحقاً لكل تاجر عند إتمام الطلب.</p>
            </div>
          </div>

          <div class="border-t border-line pt-5">
            <div class="flex items-end justify-between gap-4">
              <span class="text-base font-bold leading-7 text-ink">المجموع الكلي</span>
              <strong class="text-2xl font-bold leading-9 text-olive" data-grand-total-display><bdi>{{ number_format($subtotal, 2) }} {{ $currency }}</bdi></strong>
            </div>
            <a href="{{ route('customer.checkout.show') }}"
              class="mt-5 inline-flex h-12 w-full items-center justify-center gap-2 rounded-[16px] bg-olive px-5 text-sm font-bold text-surface shadow-sm transition-colors hover:bg-[#313923]">
              <span>متابعة إلى الدفع</span>
              <i data-lucide="credit-card" class="h-4 w-4"></i>
            </a>
          </div>
        </aside>
      </div>

      <div id="cartEmptyState"
        class="{{ $cartItems->isNotEmpty() ? 'hidden ' : '' }}rounded-[20px] border border-line bg-surface p-8 text-center shadow-card sm:p-10">
        <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-sage/20 text-olive">
          <i data-lucide="shopping-bag" class="h-8 w-8"></i>
        </span>
        <h2 class="mt-4 text-[22px] font-semibold leading-8 text-ink">سلتك فارغة حالياً</h2>
        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-muted">ابدأ من التصنيفات لاختيار قطع يدوية من مشاغل
          فلسطينية موثقة.</p>
        <a href="{{ route('categories') }}"
          class="mt-6 inline-flex items-center justify-center gap-2 rounded-[16px] bg-olive px-5 py-3 text-sm font-bold text-surface shadow-sm transition-colors hover:bg-[#313923]">
          <i data-lucide="grid-3x3" class="h-4 w-4"></i>
          <span>تصفح التصنيفات</span>
        </a>
      </div>
    </div>
  </section>
</main>
