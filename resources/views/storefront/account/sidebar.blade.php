{{-- Shared customer account sidebar. $active: dashboard|orders|addresses|account|favorites. Items without a route stay deferred. --}}
@php
  $accountSections = [
      ['key' => 'dashboard', 'label' => 'لوحة التحكم', 'icon' => 'layout-dashboard', 'href' => route('customer.dashboard'), 'deferred' => null],
      ['key' => 'orders', 'label' => 'الطلبات', 'icon' => 'package-search', 'href' => null, 'deferred' => 'dashboard/orders.html'],
      ['key' => 'addresses', 'label' => 'العنوان', 'icon' => 'map-pin', 'href' => route('customer.addresses'), 'deferred' => null],
      ['key' => 'account', 'label' => 'تفاصيل الحساب', 'icon' => 'user-round-cog', 'href' => route('customer.account-details'), 'deferred' => null],
      ['key' => 'favorites', 'label' => 'المفضلة', 'icon' => 'heart', 'href' => null, 'deferred' => 'dashboard/favorites.html'],
  ];
@endphp
<aside class="h-fit rounded-[20px] border border-line bg-surface p-3 shadow-card lg:sticky lg:top-6" aria-label="أقسام حسابي">
  <nav class="flex flex-col gap-1.5">
    @foreach ($accountSections as $section)
      @php($isActive = $section['key'] === $active)
      <a @if ($section['href']) href="{{ $section['href'] }}" @else role="link" aria-disabled="true" data-deferred-navigation="{{ $section['deferred'] }}" @endif @if ($isActive) aria-current="page" @endif
        class="flex min-h-11 items-center gap-3 rounded-[14px] border px-4 py-2.5 text-sm font-bold transition-colors {{ $isActive ? 'border-olive bg-olive text-surface shadow-sm' : 'border-transparent text-ink hover:border-line hover:bg-canvas' }}">
        <i data-lucide="{{ $section['icon'] }}" class="h-4 w-4 shrink-0"></i>
        <span>{{ $section['label'] }}</span>
      </a>
    @endforeach
    <form method="POST" action="{{ route('customer.logout') }}">
      @csrf
      <button type="submit"
        class="flex min-h-11 w-full items-center gap-3 rounded-[14px] border border-transparent px-4 py-2.5 text-sm font-bold text-[#A13D2B] transition-colors hover:border-[#A13D2B]/20 hover:bg-[#A13D2B]/10">
        <i data-lucide="log-out" class="h-4 w-4 shrink-0"></i>
        <span>تسجيل الخروج</span>
      </button>
    </form>
  </nav>
</aside>
