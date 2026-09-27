{{-- Page navigation is deferred. Original menu/category hooks await Storefront JavaScript. --}}
            <div
              class="mega-menu-panel invisible absolute start-0 top-full z-40 mt-2 flex w-[720px] origin-top-start overflow-hidden rounded-2xl border border-line bg-surface opacity-0 shadow-soft transition-[opacity,transform] duration-150 [transform:scale(.97)] group-[.mega-menu-open]:visible group-[.mega-menu-open]:opacity-100 group-[.mega-menu-open]:[transform:scale(1)]">

              <ul class="w-56 shrink-0 border-e border-line bg-canvas/60 p-2">
                <li>
                  <a href="{{ route('browse', ['category' => 'pottery']) }}" data-mega-category="pottery"
                    class="mega-category-trigger group/item flex items-center justify-between rounded-xl px-3 py-3 text-sm font-semibold text-ink [&.mega-category-active]:bg-surface [&.mega-category-active]:text-copper">
                    <span class="flex items-center gap-2.5">
                      <img src="{{ asset('assets/storefront/imgs/mcp/category-ceramics.png') }}" alt="" class="h-8 w-8 shrink-0 rounded-lg object-cover">
                      الفخار والخزف
                    </span>
                    <i data-lucide="chevron-left" class="h-4 w-4 shrink-0 text-muted"></i>
                  </a>
                </li>
                <li>
                  <a href="{{ route('browse', ['category' => 'embroidery']) }}" data-mega-category="embroidery"
                    class="mega-category-trigger group/item flex items-center justify-between rounded-xl px-3 py-3 text-sm font-semibold text-ink [&.mega-category-active]:bg-surface [&.mega-category-active]:text-copper">
                    <span class="flex items-center gap-2.5">
                      <img src="{{ asset('assets/storefront/imgs/mcp/category-embroidery.png') }}" alt="" class="h-8 w-8 shrink-0 rounded-lg object-cover">
                      التطريز الفلسطيني
                    </span>
                    <i data-lucide="chevron-left" class="h-4 w-4 shrink-0 text-muted"></i>
                  </a>
                </li>
                <li>
                  <a href="{{ route('browse', ['category' => 'baskets']) }}" data-mega-category="baskets"
                    class="mega-category-trigger group/item flex items-center justify-between rounded-xl px-3 py-3 text-sm font-semibold text-ink [&.mega-category-active]:bg-surface [&.mega-category-active]:text-copper">
                    <span class="flex items-center gap-2.5">
                      <img src="{{ asset('assets/storefront/imgs/mcp/category-basket.png') }}" alt="" class="h-8 w-8 shrink-0 rounded-lg object-cover">
                      السلال والقش
                    </span>
                    <i data-lucide="chevron-left" class="h-4 w-4 shrink-0 text-muted"></i>
                  </a>
                </li>
                <li>
                  <a href="{{ route('browse', ['category' => 'candles-soaps']) }}" data-mega-category="candles-soaps"
                    class="mega-category-trigger group/item flex items-center justify-between rounded-xl px-3 py-3 text-sm font-semibold text-ink [&.mega-category-active]:bg-surface [&.mega-category-active]:text-copper">
                    <span class="flex items-center gap-2.5">
                      <img src="{{ asset('assets/storefront/imgs/mcp/category-candle.png') }}" alt="" class="h-8 w-8 shrink-0 rounded-lg object-cover">
                      الشموع والصابون
                    </span>
                    <i data-lucide="chevron-left" class="h-4 w-4 shrink-0 text-muted"></i>
                  </a>
                </li>
                <li class="mt-1 border-t border-line pt-1">
                  <a href="{{ route('categories') }}" class="block rounded-xl px-3 py-2.5 text-xs font-bold text-olive hover:bg-surface">استعراض جميع الفئات</a>
                </li>
              </ul>

              <div class="relative min-h-[280px] flex-1 p-5">
                <div data-mega-panel="pottery" class="mega-panel grid grid-cols-3 gap-3">
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/popular-pitcher.png') }}" alt="إبريق مقدسي كنعاني أزرق" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">إبريق مقدسي كنعاني أزرق</strong>
                      <small class="mt-1 block text-[11px] text-muted">مشغل دار الكرمة</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/season-candle.png') }}" alt="شمعة زيت الزيتون في وعاء فخاري يدوي" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">شمعة زيت الزيتون في وعاء فخاري</strong>
                      <small class="mt-1 block text-[11px] text-muted">مشغل الطين الأصيل • الخليل</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/popular-pitcher.png') }}" alt="إبريق مقدسي كنعاني أزرق" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">إبريق مقدسي كنعاني أزرق</strong>
                      <small class="mt-1 block text-[11px] text-muted">مشغل دار الكرمة</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/season-candle.png') }}" alt="شمعة زيت الزيتون في وعاء فخاري يدوي" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">شمعة زيت الزيتون في وعاء فخاري</strong>
                      <small class="mt-1 block text-[11px] text-muted">مشغل الطين الأصيل • الخليل</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/popular-pitcher.png') }}" alt="إبريق مقدسي كنعاني أزرق" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">إبريق مقدسي كنعاني أزرق</strong>
                      <small class="mt-1 block text-[11px] text-muted">مشغل دار الكرمة</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/season-candle.png') }}" alt="شمعة زيت الزيتون في وعاء فخاري يدوي" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">شمعة زيت الزيتون في وعاء فخاري</strong>
                      <small class="mt-1 block text-[11px] text-muted">مشغل الطين الأصيل • الخليل</small>
                    </span>
                  </a>
                </div>
                <div data-mega-panel="embroidery" class="mega-panel hidden grid-cols-3 gap-3">
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/popular-cushion.png') }}" alt="وسادة كنعانية محاكة يدوياً" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">وسادة كنعانية محاكة يدوياً</strong>
                      <small class="mt-1 block text-[11px] text-muted">سيدات رام الله</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/season-basket.png') }}" alt="سلة قش فلسطينية من الخليل مجدولة يدوياً" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">سلة قش فلسطينية من الخليل</strong>
                      <small class="mt-1 block text-[11px] text-muted">جمعية إحسان بيت لحم</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/popular-cushion.png') }}" alt="وسادة كنعانية محاكة يدوياً" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">وسادة كنعانية محاكة يدوياً</strong>
                      <small class="mt-1 block text-[11px] text-muted">سيدات رام الله</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/season-basket.png') }}" alt="سلة قش فلسطينية من الخليل مجدولة يدوياً" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">سلة قش فلسطينية من الخليل</strong>
                      <small class="mt-1 block text-[11px] text-muted">جمعية إحسان بيت لحم</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/popular-cushion.png') }}" alt="وسادة كنعانية محاكة يدوياً" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">وسادة كنعانية محاكة يدوياً</strong>
                      <small class="mt-1 block text-[11px] text-muted">سيدات رام الله</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/season-basket.png') }}" alt="سلة قش فلسطينية من الخليل مجدولة يدوياً" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">سلة قش فلسطينية من الخليل</strong>
                      <small class="mt-1 block text-[11px] text-muted">جمعية إحسان بيت لحم</small>
                    </span>
                  </a>
                </div>
                <div data-mega-panel="baskets" class="mega-panel hidden grid-cols-3 gap-3">
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/popular-basket.png') }}" alt="سلة طعام قشية بزخارف ملونة" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">سلة طعام قشية بزخارف ملونة</strong>
                      <small class="mt-1 block text-[11px] text-muted">تعاونية الأغوار</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/season-pitcher.png') }}" alt="إبريق فخار مقدسي مزخرف يدوياً" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">إبريق فخار مقدسي مزخرف يدوياً</strong>
                      <small class="mt-1 block text-[11px] text-muted">تعاونية سيدات أريحا</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/popular-basket.png') }}" alt="سلة طعام قشية بزخارف ملونة" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">سلة طعام قشية بزخارف ملونة</strong>
                      <small class="mt-1 block text-[11px] text-muted">تعاونية الأغوار</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/season-pitcher.png') }}" alt="إبريق فخار مقدسي مزخرف يدوياً" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">إبريق فخار مقدسي مزخرف يدوياً</strong>
                      <small class="mt-1 block text-[11px] text-muted">تعاونية سيدات أريحا</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/popular-basket.png') }}" alt="سلة طعام قشية بزخارف ملونة" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">سلة طعام قشية بزخارف ملونة</strong>
                      <small class="mt-1 block text-[11px] text-muted">تعاونية الأغوار</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/season-pitcher.png') }}" alt="إبريق فخار مقدسي مزخرف يدوياً" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">إبريق فخار مقدسي مزخرف يدوياً</strong>
                      <small class="mt-1 block text-[11px] text-muted">تعاونية سيدات أريحا</small>
                    </span>
                  </a>
                </div>
                <div data-mega-panel="candles-soaps" class="mega-panel hidden grid-cols-3 gap-3">
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/popular-candle.png') }}" alt="شمعة زيت زيتون في فخار ريفي" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">شمعة زيت زيتون في فخار ريفي</strong>
                      <small class="mt-1 block text-[11px] text-muted">معصرة برقين</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/season-cushion.png') }}" alt="وسادة تطريز فلسطيني يدوي خيوط حرير" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">وسادة تطريز خيوط حرير طبيعي</strong>
                      <small class="mt-1 block text-[11px] text-muted">تعاونية نساء نابلس</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/popular-candle.png') }}" alt="شمعة زيت زيتون في فخار ريفي" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">شمعة زيت زيتون في فخار ريفي</strong>
                      <small class="mt-1 block text-[11px] text-muted">معصرة برقين</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/season-cushion.png') }}" alt="وسادة تطريز فلسطيني يدوي خيوط حرير" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">وسادة تطريز خيوط حرير طبيعي</strong>
                      <small class="mt-1 block text-[11px] text-muted">تعاونية نساء نابلس</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/popular-candle.png') }}" alt="شمعة زيت زيتون في فخار ريفي" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">شمعة زيت زيتون في فخار ريفي</strong>
                      <small class="mt-1 block text-[11px] text-muted">معصرة برقين</small>
                    </span>
                  </a>
                  <a role="link" tabindex="0" aria-disabled="true" data-deferred-navigation="product.html" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ asset('assets/storefront/imgs/mcp/season-cushion.png') }}" alt="وسادة تطريز فلسطيني يدوي خيوط حرير" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">وسادة تطريز خيوط حرير طبيعي</strong>
                      <small class="mt-1 block text-[11px] text-muted">تعاونية نساء نابلس</small>
                    </span>
                  </a>
                </div>
              </div>
            </div>
