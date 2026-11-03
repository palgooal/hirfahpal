{{--
    PRODUCT-FIX-01: a row of five stars for a published rating. $rating is the average of the published reviews, or
    null when there are none; the filled stars are that rating rounded to the nearest whole star and the others are
    dimmed, and the accessible name states the actual rating. $star is the star image, $starClass its size.
--}}
@php
    $filledStars = $rating === null ? 0 : max(0, min(5, (int) round((float) $rating)));
    $ratingLabel = $rating === null ? 'لا توجد تقييمات بعد' : 'التقييم '.number_format((float) $rating, 1).' من 5';
@endphp
<span class="flex gap-1" role="img" aria-label="{{ $ratingLabel }}" data-filled-stars="{{ $filledStars }}">@for ($position = 1; $position <= 5; $position++)<img
        src="{{ $star }}" alt="" class="{{ $starClass }}"@if ($position > $filledStars) style="opacity: .25"@endif>@endfor</span>
