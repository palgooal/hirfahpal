{{-- VQA-FIX-01: the regular price of a discounted product, struck through (storefront.partials.product-price). --}}
<span class="min-w-0 truncate text-xs text-muted line-through {{ $regularClass ?? '' }}" data-regular-price><bdi>{{ number_format((float) $product->price, 2) }} {{ $currency }}</bdi></span>
