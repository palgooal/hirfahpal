{{--
    VQA-FIX-01: a product card's price. The selling price is Product::sellingPrice(), the price the cart and checkout
    charge; with a genuine discount (Product::hasDiscount()) the regular price is shown struck through, as on the
    product page. One line that never wraps, so the fixed-height cards keep their layout; a long struck price shrinks
    (truncates) instead of pushing the row. $priceClass keeps each card's own style for the selling price. $regular
    places the struck price: 'after' the selling price (default), 'before' it (where it takes the place of the card's
    "price" label), or 'none' (the card shows it on a line of its own).
--}}
@php($regular = $product->hasDiscount() ? ($regular ?? 'after') : 'none')
<span class="flex min-w-0 items-baseline gap-1.5 whitespace-nowrap" data-product-price>@if ($regular === 'before')@include('storefront.partials.product-regular-price')@endif<bdi
        class="shrink-0 {{ $priceClass }}" data-selling-price>{{ number_format($product->sellingPrice(), 2) }} {{ $currency }}</bdi>@if ($regular === 'after')@include('storefront.partials.product-regular-price')@endif</span>
