@php($footer = \App\Models\Setting::footerFor($siteSetting ?? null))
<footer class="border-t border-sage bg-olive text-white lg:h-[355px]">
    <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-9 px-4 pb-8 pt-10 sm:gap-12 sm:pb-10 sm:pt-14 lg:px-8">
        <div class="grid w-full grid-cols-2 gap-x-6 gap-y-9 sm:gap-10 lg:h-[159px] lg:grid-cols-5 lg:gap-12">
            <div class="col-span-2 flex flex-col items-start gap-4 text-right lg:col-span-2">
                <div class="flex w-full items-center justify-start gap-3">
                    <span class="flex h-[58px] w-[58px] shrink-0 items-center justify-center rounded-[16px] bg-surface p-1 shadow-sm"><img src="{{ \App\Support\Storefront\HomepageMedia::url($siteSetting?->logo, 'assets/storefront/imgs/mcp/footer-logo.png') }}" alt="شعار الموقع" class="h-[50px] w-[50px] object-cover"></span>
                    <div class="flex flex-col items-start"><div class="flex items-center justify-start gap-1.5"><i class="h-1.5 w-1.5 rounded-full bg-copper"></i><strong class="text-2xl font-bold leading-8 text-[#fef3c7]">{{ $siteSetting?->site_name ?? 'حِرفة' }}</strong></div><p class="text-[10px] font-medium uppercase leading-[15px] tracking-[.5px] text-white/70">{{ $footer['tagline'] }}</p></div>
                </div>
                <p class="w-full max-w-[384px] text-right text-sm font-light leading-5 text-white/75">{{ $footer['description'] }}</p>
                <div class="flex w-full items-center justify-start gap-2.5 pt-2">
                    {{-- SETTINGS-FIX-02 (SET-08): each link's accessible name in the page language (AdminSettingsTranslationSeeder),
                         read without auto-creating rows. --}}
                    @php($socialText = function (string $key, string $default): string {
                        $autoCreate = config('palgoals-locale.auto_create');
                        config(['palgoals-locale.auto_create' => false]);

                        try {
                            return t('storefront.Footer_Social_'.$key, $default);
                        } finally {
                            config(['palgoals-locale.auto_create' => $autoCreate]);
                        }
                    })
                    @php($socialNames = ['instagram' => $socialText('Instagram', 'Instagram'), 'facebook' => $socialText('Facebook', 'Facebook'), 'x' => $socialText('X', 'X')])
                    @foreach (['instagram' => 'footer-instagram.svg', 'facebook' => 'footer-facebook.svg', 'x' => 'footer-x.svg'] as $network => $icon)
                        @if ($footer[$network.'_url'])
                            <a href="{{ $footer[$network.'_url'] }}" class="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/10" aria-label="{{ $socialNames[$network] }}"><img src="{{ asset('assets/storefront/imgs/mcp/'.$icon) }}" alt="" class="h-4 w-4"></a>
                        @else
                            {{-- SETTINGS-FIX-01 (SET-02): no address, no link (the same icon, not a link to "#"). --}}
                            <span class="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/10" aria-hidden="true"><img src="{{ asset('assets/storefront/imgs/mcp/'.$icon) }}" alt="" class="h-4 w-4"></span>
                        @endif
                    @endforeach
                </div>
            </div>
            @php($groups = [
                ['about_title', [['about_story_label', route('content.show', 'about')], ['about_authenticity_label', route('content.show', 'authenticity')], ['about_contact_label', route('contact.create')]]],
                ['links_title', [['orders_label', route('customer.orders.index')], ['cart_label', route('cart')], ['login_label', route('customer.login')], ['account_label', route('customer.dashboard')]]],
                ['customer_service_title', [['shipping_label', route('content.show', 'shipping')], ['returns_label', route('content.show', 'returns')], ['faq_label', route('content.show', 'faq')]]],
            ])
            @foreach ($groups as [$title, $links])
                <div class="flex flex-col items-start gap-[14px] text-right">
                    <h3 class="w-full text-right text-sm font-bold leading-5 tracking-[.35px] text-[#fde68a]">{{ $footer[$title] }}</h3>
                    <ul class="flex w-full flex-col gap-[9.5px] text-xs leading-4 text-white/75">
                        @foreach ($links as [$label, $url])
                            <li><a href="{{ $url }}" class="flex items-center justify-start gap-1.5"><i class="h-1 w-1 rounded-full bg-[#fde68a]/50"></i><span>{{ $footer[$label] }}</span></a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
        <div class="flex w-full flex-col gap-5 border-t border-white/10 pt-6 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-right text-xs leading-4 text-white/70"><bdi>© {{ now()->year }}</bdi> {{ $siteSetting?->site_name ?? 'حِرفة' }}. {{ $footer['copyright'] }}</p>
            <div class="flex flex-col items-start gap-3 text-[11px] leading-4 sm:flex-row sm:flex-wrap sm:items-center"><span class="text-white/50">{{ $footer['payment_methods_title'] }}</span><div class="flex flex-wrap items-center gap-2">
                @foreach ([['footer-card.svg', 'card_payment_label'], ['footer-apple.svg', 'apple_pay_label'], ['footer-cod.svg', 'cash_on_delivery_label']] as [$icon, $label])
                    <span class="flex h-[26px] items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-[11px] font-medium text-white/90"><img src="{{ asset('assets/storefront/imgs/mcp/'.$icon) }}" alt="" class="h-3 w-3"><span>{{ $footer[$label] }}</span></span>
                @endforeach
            </div></div>
        </div>
    </div>
</footer>
