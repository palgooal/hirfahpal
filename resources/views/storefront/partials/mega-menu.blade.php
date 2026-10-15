{{-- Page navigation is deferred. Original menu/category hooks await Storefront JavaScript. --}}
            <div
              class="mega-menu-panel invisible absolute start-0 top-full z-40 mt-2 flex w-[720px] origin-top-start overflow-hidden rounded-2xl border border-line bg-surface opacity-0 shadow-soft transition-[opacity,transform] duration-150 [transform:scale(.97)] group-[.mega-menu-open]:visible group-[.mega-menu-open]:opacity-100 group-[.mega-menu-open]:[transform:scale(1)]">

              <ul class="w-56 shrink-0 border-e border-line bg-canvas/60 p-2">
                @foreach ($navigationCategories as $navigationCategory)
<li>
                  <a href="{{ route('browse', ['category' => $navigationCategory->slug]) }}" data-mega-category="{{ $navigationCategory->slug }}"
                    class="mega-category-trigger group/item flex items-center justify-between rounded-xl px-3 py-3 text-sm font-semibold text-ink [&.mega-category-active]:bg-surface [&.mega-category-active]:text-copper">
                    <span class="flex items-center gap-2.5">
                      <img src="{{ $navigationCategory->imageMedia?->url ?: \App\Support\Storefront\HomepageMedia::url($navigationCategory->image) }}" alt="" class="h-8 w-8 shrink-0 rounded-lg object-cover">
                      {{ $navigationCategory->name }}
                    </span>
                    <i data-lucide="chevron-left" class="h-4 w-4 shrink-0 text-muted"></i>
                  </a>
                </li>
@endforeach

                <li class="mt-1 border-t border-line pt-1">
                  <a href="{{ route('categories') }}" class="block rounded-xl px-3 py-2.5 text-xs font-bold text-olive hover:bg-surface">استعراض جميع الفئات</a>
                </li>
              </ul>

              <div class="relative min-h-[280px] flex-1 p-5">
                @foreach ($navigationCategories as $navigationCategory)
<div data-mega-panel="{{ $navigationCategory->slug }}" class="mega-panel grid grid-cols-3 gap-3">
                  @foreach ($navigationCategory->products->concat($navigationCategory->children->flatMap->products)->unique('id')->take(6) as $navigationProduct)
<a href="{{ route('product', ['slug' => $navigationProduct->slug]) }}" class="group/card flex flex-col items-center gap-2.5 rounded-xl p-2.5 text-center hover:bg-canvas">
                    <img src="{{ \App\Support\Storefront\HomepageMedia::url($navigationProduct->primaryImage?->path) }}" alt="{{ $navigationProduct->name }}" class="h-20 w-20 shrink-0 rounded-lg bg-white object-contain p-1.5 shadow-sm transition-transform duration-200 group-hover/card:scale-105">
                    <span>
                      <strong class="block text-xs font-bold leading-4 text-ink">{{ $navigationProduct->name }}</strong>
                      <small class="mt-1 block text-[11px] text-muted">{{ $navigationProduct->vendor->profile->store_name }}</small>
                    </span>
                  </a>
@endforeach

                </div>
@endforeach

              </div>
            </div>
