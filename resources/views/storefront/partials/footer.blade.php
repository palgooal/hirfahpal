{{-- FOOTER-I18N-01B: the footer texts and the site name in the page language (English defaults where English has none).
     The design's heights are minimums: the longer English description, with the social icons, would otherwise cross
     the bottom bar's line (the Arabic footer keeps its 355px). --}}
@php($footer = \App\Models\Setting::footerFor($siteSetting ?? null, app()->getLocale()))
@php($siteName = \App\Models\Setting::siteNameFor($siteSetting ?? null, app()->getLocale()))
{{-- FOOTER-FIX-01: alignment follows the page direction (text-start); each text the merchant wrote is isolated with
     dir="auto", so its own direction (and punctuation) is kept inside an Arabic or English page; the link columns are
     navigation landmarks named in the page language; links are at least 24px high (same rhythm as before) with a
     visible focus ring in the storefront's sage. Interface texts are read without auto-creating rows. --}}
@php($footerText = function (string $key, string $default): string {
    $autoCreate = config('palgoals-locale.auto_create');
    config(['palgoals-locale.auto_create' => false]);

    try {
        return t('storefront.Footer_'.$key, $default);
    } finally {
        config(['palgoals-locale.auto_create' => $autoCreate]);
    }
})
<footer class="border-t border-sage bg-olive text-white lg:min-h-[355px]">
    <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-9 px-4 pb-8 pt-10 sm:gap-12 sm:pb-10 sm:pt-14 lg:px-8">
        <div class="grid w-full grid-cols-2 gap-x-6 gap-y-9 sm:gap-10 lg:min-h-[159px] lg:grid-cols-5 lg:gap-12">
            <div class="col-span-2 flex flex-col items-start gap-4 text-start lg:col-span-2">
                <div class="flex w-full items-center justify-start gap-3">
                    {{-- The site name is written right beside it: the logo adds nothing for assistive technology. --}}
                    <span class="flex h-[58px] w-[58px] shrink-0 items-center justify-center rounded-[16px] bg-surface p-1 shadow-sm"><img src="{{ \App\Support\Storefront\HomepageMedia::url($siteSetting?->logoUrl(), 'assets/storefront/imgs/mcp/footer-logo.png') }}" alt="" class="h-[50px] w-[50px] object-cover"></span>
                    <div class="flex flex-col items-start"><div class="flex items-center justify-start gap-1.5"><i class="h-1.5 w-1.5 rounded-full bg-copper"></i><strong class="text-2xl font-bold leading-8 text-[#fef3c7]" dir="auto">{{ $siteName }}</strong></div><p class="text-[10px] font-medium uppercase leading-[15px] tracking-[.5px] text-white/70"><span dir="auto">{{ $footer['tagline'] }}</span></p></div>
                </div>
                <p class="w-full max-w-[384px] text-start text-sm font-light leading-5 text-white/75"><span dir="auto">{{ $footer['description'] }}</span></p>
                {{-- FOOTER-SOCIAL-02A: only enabled links of the approved platforms with an allowed address, in their saved
                     order (App\Support\Storefront\SocialPlatforms); no row at all without one. Each opens in a new tab and is
                     named in the page language (AdminSettingsTranslationSeeder), read without auto-creating rows. --}}
                @php($socialLinks = \App\Support\Storefront\SocialPlatforms::linksFrom($siteSetting?->footer_content ?? null))
                @if ($socialLinks !== [])
                    @php($socialText = function (string $key, string $default): string {
                        $autoCreate = config('palgoals-locale.auto_create');
                        config(['palgoals-locale.auto_create' => false]);

                        try {
                            return t('storefront.Footer_Social_'.$key, $default);
                        } finally {
                            config(['palgoals-locale.auto_create' => $autoCreate]);
                        }
                    })
                    @php($newTab = $socialText('New_Tab', 'opens in a new tab'))
                    <div class="flex w-full items-center justify-start gap-2.5 pt-2">
                        @foreach ($socialLinks as $link)
                            @php([$labelKey, $labelDefault] = \App\Support\Storefront\SocialPlatforms::PLATFORMS[$link['platform']])
                            <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" class="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/10 text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sage" aria-label="{{ $socialText($labelKey, $labelDefault) }} ({{ $newTab }})" data-footer-social="{{ $link['platform'] }}"><x-storefront.social-icon :platform="$link['platform']" class="h-4 w-4" /></a>
                        @endforeach
                    </div>
                @endif
            </div>
            @php($groups = [
                ['about_title', [['about_story_label', \App\Models\StorefrontContentPage::publicUrl('about')], ['about_authenticity_label', \App\Models\StorefrontContentPage::publicUrl('authenticity')], ['about_contact_label', route('contact.create')]]],
                ['links_title', [['orders_label', route('customer.orders.index')], ['cart_label', route('cart')], ['login_label', route('customer.login')], ['account_label', route('customer.dashboard')]]],
                ['customer_service_title', [['shipping_label', \App\Models\StorefrontContentPage::publicUrl('shipping')], ['returns_label', \App\Models\StorefrontContentPage::publicUrl('returns')], ['faq_label', \App\Models\StorefrontContentPage::publicUrl('faq')]]],
            ])
            {{-- Each column's landmark name in the page language (its heading is the merchant's text). --}}
            @php($navNames = [
                'about_title' => $footerText('Nav_About', 'About'),
                'links_title' => $footerText('Nav_Links', 'Useful links'),
                'customer_service_title' => $footerText('Nav_Service', 'Customer service'),
            ])
            @foreach ($groups as [$title, $links])
                <nav class="flex flex-col items-start gap-[14px] text-start" aria-label="{{ $navNames[$title] }}" data-footer-nav="{{ $title }}">
                    {{-- h2: the footer's columns, not subsections of the page's last section. --}}
                    <h2 class="w-full text-start text-sm font-bold leading-5 tracking-[.35px] text-[#fde68a]"><span dir="auto">{{ $footer[$title] }}</span></h2>
                    <ul class="flex w-full flex-col gap-[1.5px] text-xs leading-4 text-white/75">
                        @foreach ($links as [$label, $url])
                            <li><a href="{{ $url }}" class="flex min-h-6 items-center justify-start gap-1.5 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sage"><i class="h-1 w-1 rounded-full bg-[#fde68a]/50"></i><span dir="auto">{{ $footer[$label] }}</span></a></li>
                        @endforeach
                    </ul>
                </nav>
            @endforeach
        </div>
        <div class="flex w-full flex-col gap-5 border-t border-white/10 pt-6 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-start text-xs leading-4 text-white/70"><bdi>© {{ now()->year }}</bdi> <span dir="auto">{{ $siteName }}.</span> <span dir="auto">{{ $footer['copyright'] }}</span></p>
            {{-- The heading at 70% white (about 5.7:1 on olive, WCAG AA for small text); it was 50% (about 3.8:1). --}}
            <div class="flex flex-col items-start gap-3 text-[11px] leading-4 sm:flex-row sm:flex-wrap sm:items-center"><span class="text-white/70" dir="auto">{{ $footer['payment_methods_title'] }}</span><div class="flex flex-wrap items-center gap-2">
                @foreach ([['footer-card.svg', 'card_payment_label'], ['footer-apple.svg', 'apple_pay_label'], ['footer-cod.svg', 'cash_on_delivery_label']] as [$icon, $label])
                    <span class="flex h-[26px] items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-[11px] font-medium text-white/90"><img src="{{ asset('assets/storefront/imgs/mcp/'.$icon) }}" alt="" class="h-3 w-3"><span dir="auto">{{ $footer[$label] }}</span></span>
                @endforeach
            </div></div>
        </div>
    </div>
</footer>
