const $ = window.jQuery;

if ($) {
    $(function () {
        // Product behavior binds only when the Product gallery markup is present.
        const $productGallery = $('[data-product-gallery]');
        if (!$productGallery.length) return;
        if ($productGallery.data('storefrontProductInitialized')) return;
        $productGallery.data('storefrontProductInitialized', true);

        let galleryIndex = 0;
        const galleryThumbs = $productGallery.find('.gallery-thumb');
        const galleryImage = $('#productMainImage');
        const galleryStrip = $productGallery.find('.gallery-thumbnails').get(0);
        let gallerySwapTimer;
        let galleryIsDragging = false;
        let galleryDidDrag = false;
        let galleryDragStart = 0;
        let galleryScrollStart = 0;
        let galleryLastX = 0;
        let galleryLastTime = 0;
        let galleryVelocity = 0;
        let galleryGlideFrame;

        function selectGalleryImage(index) {
            if (!galleryThumbs.length) return;
            galleryIndex = (index + galleryThumbs.length) % galleryThumbs.length;
            const activeThumb = galleryThumbs.eq(galleryIndex);

            clearTimeout(gallerySwapTimer);
            galleryImage.addClass('opacity-0 scale-[1.015]');
            gallerySwapTimer = setTimeout(function () {
                galleryImage
                    .attr({
                        src: activeThumb.data('image'),
                        alt: activeThumb.find('img').attr('alt')
                    })
                    .removeClass('opacity-0 scale-[1.015]');
            }, 140);

            $('#galleryCounter').text((galleryIndex + 1) + ' / ' + galleryThumbs.length);
            galleryThumbs
                .attr('aria-pressed', 'false')
                .removeClass('border-2 border-olive p-0.5 opacity-100 shadow-[0_0_0_2px_rgba(74,93,58,.2)]')
                .addClass('border border-line opacity-70');
            activeThumb
                .attr('aria-pressed', 'true')
                .addClass('border-2 border-olive p-0.5 opacity-100 shadow-[0_0_0_2px_rgba(74,93,58,.2)]')
                .removeClass('border border-line opacity-70');

            activeThumb.get(0).scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }

        galleryThumbs.on('click', function (event) {
            if (galleryDidDrag) {
                event.preventDefault();
                galleryDidDrag = false;
                return;
            }
            selectGalleryImage(galleryThumbs.index(this));
        });
        $productGallery.find('.gallery-next').on('click', () => selectGalleryImage(galleryIndex + 1));
        $productGallery.find('.gallery-prev').on('click', () => selectGalleryImage(galleryIndex - 1));

        if (galleryStrip) {
            galleryStrip.addEventListener('pointerdown', function (event) {
                if (event.pointerType === 'mouse' && event.button !== 0) return;
                cancelAnimationFrame(galleryGlideFrame);
                galleryIsDragging = true;
                galleryDidDrag = false;
                galleryDragStart = event.clientX;
                galleryScrollStart = galleryStrip.scrollLeft;
                galleryLastX = event.clientX;
                galleryLastTime = performance.now();
                galleryVelocity = 0;
                galleryStrip.classList.add('snap-none');
            });

            galleryStrip.addEventListener('pointermove', function (event) {
                if (!galleryIsDragging) return;
                const distance = event.clientX - galleryDragStart;
                if (!galleryDidDrag && Math.abs(distance) > 5) {
                    galleryDidDrag = true;
                    galleryStrip.setPointerCapture(event.pointerId);
                }
                if (!galleryDidDrag) return;

                event.preventDefault();
                galleryStrip.scrollLeft = galleryScrollStart - distance;
                const now = performance.now();
                const elapsed = Math.max(now - galleryLastTime, 1);
                galleryVelocity = (event.clientX - galleryLastX) / elapsed;
                galleryLastX = event.clientX;
                galleryLastTime = now;
            });

            galleryStrip.addEventListener('pointerup', function (event) {
                galleryIsDragging = false;
                if (galleryStrip.hasPointerCapture(event.pointerId)) galleryStrip.releasePointerCapture(event.pointerId);

                if (!galleryDidDrag) {
                    galleryStrip.classList.remove('snap-none');
                    return;
                }

                let momentum = galleryVelocity * 18;
                const glide = function () {
                    momentum *= 0.9;
                    galleryStrip.scrollLeft -= momentum;
                    if (Math.abs(momentum) > 0.35) {
                        galleryGlideFrame = requestAnimationFrame(glide);
                    } else {
                        galleryStrip.classList.remove('snap-none');
                    }
                };
                galleryGlideFrame = requestAnimationFrame(glide);
                setTimeout(function () {
                    galleryDidDrag = false;
                }, 0);
            });

            galleryStrip.addEventListener('pointercancel', function () {
                galleryIsDragging = false;
                galleryDidDrag = false;
                galleryStrip.classList.remove('snap-none');
            });

            galleryStrip.addEventListener('dragstart', function (event) {
                event.preventDefault();
            });
        }

        // Quantity is UI-only: it never feeds price, cart or related cards.
        $('#quantityIncrease').on('click', function () {
            const quantity = Math.min(3, Number($('#productQuantity').text()) + 1);
            $('#productQuantity').text(quantity);
        });
        $('#quantityDecrease').on('click', function () {
            const quantity = Math.max(1, Number($('#productQuantity').text()) - 1);
            $('#productQuantity').text(quantity);
        });

        const $productAccordion = $('#productAccordion');
        $productAccordion.on('click', '.accordion-toggle', function () {
            const button = $(this);
            const item = button.closest('.accordion-item');
            const isOpen = button.attr('aria-expanded') === 'true';

            $productAccordion.find('.accordion-item').each(function () {
                const currentItem = $(this);
                const currentButton = currentItem.find('.accordion-toggle');
                const currentContent = currentItem.find('.accordion-content');
                const shouldOpen = currentItem.is(item) && !isOpen;

                currentButton.attr('aria-expanded', String(shouldOpen));
                currentContent.attr('aria-hidden', !shouldOpen);
                currentContent.toggleClass('grid-rows-[1fr] opacity-100', shouldOpen);
                currentContent.toggleClass('grid-rows-[0fr] opacity-0', !shouldOpen);
                currentButton.find('.accordion-chevron').toggleClass('rotate-180', shouldOpen);
            });
        });
    });
}
