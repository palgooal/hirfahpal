$(function () {
  lucide.createIcons();

  let cartCount = 2;
  let cartTotal = 365;

  function applyLanguage(language, persist = true) {
    const isEnglish = language === 'en';
    const nextLanguage = isEnglish ? 'ar' : 'en';

    $('html').attr({
      lang: language,
      dir: isEnglish ? 'ltr' : 'rtl'
    });
    $('.language-label')
      .text(isEnglish ? 'AR' : 'EN')
      .attr('lang', nextLanguage);
    $('.language-toggle').attr('aria-label', isEnglish ? 'التبديل إلى العربية' : 'Switch to English');

    if (persist) localStorage.setItem('hirfah-language', language);
  }

  const savedLanguage = localStorage.getItem('hirfah-language');
  applyLanguage(savedLanguage === 'en' ? 'en' : 'ar', false);

  $('.language-toggle').on('click', function () {
    applyLanguage($('html').attr('lang') === 'ar' ? 'en' : 'ar');
  });

  function showToast(message) {
    $('#toast').text(message).removeClass('-translate-y-24');
    clearTimeout(window.toastTimer);
    window.toastTimer = setTimeout(() => $('#toast').addClass('-translate-y-24'), 2200);
  }

  $('#menuButton').on('click', function () {
    $('#mobileMenu').stop(true, true).slideToggle(180);
  });

  $('#mobileMenu a').on('click', function () {
    $('#mobileMenu').slideUp(150);
  });

  $('.nav-dropdown').on('click', function (event) {
    event.stopPropagation();
    const $dropdown = $(this);
    const isOpen = $dropdown.hasClass('nav-dropdown-open');

    $('.nav-dropdown').removeClass('nav-dropdown-open').find('button').attr('aria-expanded', 'false');
    if (!isOpen) {
      $dropdown.addClass('nav-dropdown-open').find('button').attr('aria-expanded', 'true');
    }
  });

  $(document).on('click', function () {
    $('.nav-dropdown').removeClass('nav-dropdown-open').find('button').attr('aria-expanded', 'false');
  });

  $(document).on('keydown', function (event) {
    if (event.key === 'Escape') {
      $('.nav-dropdown').removeClass('nav-dropdown-open').find('button').attr('aria-expanded', 'false');
    }
  });

  $('.mobile-nav-dropdown-toggle').on('click', function (event) {
    event.stopPropagation();
    const $toggle = $(this);
    const $panel = $toggle.siblings('.mobile-nav-dropdown-panel');
    const isOpen = $toggle.attr('aria-expanded') === 'true';

    $toggle.attr('aria-expanded', String(!isOpen));
    $toggle.find('[data-lucide="chevron-down"]').toggleClass('rotate-180', !isOpen);
    $panel.slideToggle(150);
  });

  $('#searchButton, #mobileSearchButton').on('click', function () {
    $('#searchPanel').fadeIn(160).css('display', 'block');
    setTimeout(() => $('#globalSearch').trigger('focus'), 50);
  });

  $('#closeSearch, #searchPanel').on('click', function (event) {
    if (event.target === this || this.id === 'closeSearch') $('#searchPanel').fadeOut(140);
  });

  $(document).on('keydown', function (event) {
    if (event.key === 'Escape') $('#searchPanel').fadeOut(140);
  });

  $('.filter-chip').on('click', function () {
    const filter = $(this).data('filter');
    $('.filter-chip')
      .removeClass('bg-sage text-ink border-sage font-bold')
      .addClass('border-transparent font-semibold text-muted');
    $(this)
      .addClass('bg-sage text-ink border-sage font-bold')
      .removeClass('border-transparent font-semibold text-muted');
    $('.product-grid .product-card').each(function () {
      $(this).toggle(filter === 'all' || $(this).data('category') === filter);
    });
  });

  $('.season-filter').on('click', function () {
    const filter = $(this).data('filter');
    $('.season-filter')
      .removeClass('border-sage bg-sage font-bold text-ink shadow-sm')
      .addClass('border-line bg-surface font-medium text-muted');
    $(this)
      .addClass('border-sage bg-sage font-bold text-ink shadow-sm')
      .removeClass('border-line bg-surface font-medium text-muted');
    $('.season-product').each(function () {
      $(this).toggle(filter === 'all' || $(this).data('category') === filter);
    });
  });

  $('#globalSearch').on('input', function () {
    const query = $(this).val().trim();
    if (!query) return;

    const matches = $('.product-card').filter(function () {
      return $(this).text().includes(query);
    }).length;

    if (matches) {
      $('#searchPanel').fadeOut(120);
      const target = $('#shop').length ? $('#shop') : $('.product-card').first();
      $('html, body').animate({ scrollTop: target.offset().top - 30 }, 450);
      showToast('وجدنا قطعاً تطابق بحثك');
    }
  });

  $('.favorite').on('click', function () {
    const isActive = !$(this).hasClass('text-copper');
    $(this).toggleClass('text-copper bg-[#fff3ea]', isActive);
    $(this).find('svg').toggleClass('fill-current', isActive);
    showToast(isActive ? 'أضيفت القطعة إلى المفضلة' : 'أزيلت القطعة من المفضلة');
  });

  $('.add-cart').on('click', function () {
    const quantity = $('.product-page').length ? Number($('#productQuantity').text()) : 1;
    cartCount += quantity;
    cartTotal += Number($(this).data('price')) * quantity;
    $('#cartCount').text(cartCount);
    $('#cartTotal').text('₪' + cartTotal);
    $(this).text('تمت الإضافة').addClass('bg-olive text-white').prop('disabled', true);
    showToast('تمت إضافة القطعة إلى السلة');
  });

  $('#cartButton').on('click', function () {
    showToast('في السلة ' + cartCount + ' قطع بقيمة ₪' + cartTotal);
  });

  $('#newsletter').on('submit', function (event) {
    event.preventDefault();
    const email = $('#email').val();
    $(this).html('<div class="flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-sage/40 text-sm font-bold"><span>تم الاشتراك بنجاح</span><i data-lucide="check" class="h-4 w-4"></i></div>');
    lucide.createIcons();
    showToast('أهلاً بك في مجتمع حِرفة، ' + email);
  });

  let galleryIndex = 0;
  const galleryThumbs = $('.gallery-thumb');
  const galleryImage = $('#productMainImage');
  const galleryStrip = $('.gallery-thumbnails').get(0);
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
  $('.gallery-next').on('click', () => selectGalleryImage(galleryIndex + 1));
  $('.gallery-prev').on('click', () => selectGalleryImage(galleryIndex - 1));

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

  $('#quantityIncrease').on('click', function () {
    const quantity = Math.min(3, Number($('#productQuantity').text()) + 1);
    $('#productQuantity').text(quantity);
  });
  $('#quantityDecrease').on('click', function () {
    const quantity = Math.max(1, Number($('#productQuantity').text()) - 1);
    $('#productQuantity').text(quantity);
  });

  $('#productAccordion').on('click', '.accordion-toggle', function () {
    const button = $(this);
    const item = button.closest('.accordion-item');
    const isOpen = button.attr('aria-expanded') === 'true';

    $('.accordion-item').each(function () {
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

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      entry.target.classList.remove('sm:opacity-0', 'sm:translate-y-[22px]');
      entry.target.classList.add('opacity-100', 'translate-y-0');
      observer.unobserve(entry.target);
    });
  }, { threshold: 0.08 });

  document.querySelectorAll('.reveal').forEach((element) => observer.observe(element));
});

$(function () {
  const $vendorDirectorySearch = $('#vendorSearch');
  if (!$vendorDirectorySearch.length) return;

  const $vendorDirectoryCards = $('.vendor-card');
  const $vendorDirectoryEmptyState = $('#vendorEmptyState');

  function runVendorDirectoryFilter() {
    const vendorDirectoryQuery = $vendorDirectorySearch.val().trim().toLowerCase();
    let vendorDirectoryVisibleCount = 0;

    $vendorDirectoryCards.each(function () {
      const $vendorDirectoryCard = $(this);
      const vendorDirectoryHaystack = [
        $vendorDirectoryCard.data('name'),
        $vendorDirectoryCard.data('city'),
        $vendorDirectoryCard.text()
      ].join(' ').toLowerCase();
      const vendorDirectoryMatches = !vendorDirectoryQuery || vendorDirectoryHaystack.includes(vendorDirectoryQuery);

      $vendorDirectoryCard.toggle(vendorDirectoryMatches);
      if (vendorDirectoryMatches) vendorDirectoryVisibleCount += 1;
    });

    $vendorDirectoryEmptyState.toggle(vendorDirectoryVisibleCount === 0);
  }

  $vendorDirectorySearch.on('input', runVendorDirectoryFilter);
  $('#clearVendorSearch').on('click', function () {
    $vendorDirectorySearch.val('').trigger('input').trigger('focus');
  });

  runVendorDirectoryFilter();
});

$(function () {
  const $vendorStorefrontRoot = $('[data-vendor-storefront]');
  if (!$vendorStorefrontRoot.length) return;

  const $vendorStorefrontChips = $vendorStorefrontRoot.find('.vendor-category-chip');
  const $vendorStorefrontGrid = $vendorStorefrontRoot.find('[data-vendor-product-grid]');
  const $vendorStorefrontCards = $vendorStorefrontGrid.find('.product-card');
  const $vendorStorefrontEmptyState = $vendorStorefrontRoot.find('[data-vendor-empty-state]');

  if (!$vendorStorefrontChips.length || !$vendorStorefrontGrid.length || !$vendorStorefrontCards.length) return;

  function runVendorStorefrontFilter(vendorStorefrontFilter) {
    let vendorStorefrontVisibleCount = 0;

    $vendorStorefrontCards.each(function () {
      const $vendorStorefrontCard = $(this);
      const vendorStorefrontMatches = vendorStorefrontFilter === 'all' || $vendorStorefrontCard.data('category') === vendorStorefrontFilter;

      $vendorStorefrontCard.toggle(vendorStorefrontMatches);
      if (vendorStorefrontMatches) vendorStorefrontVisibleCount += 1;
    });

    $vendorStorefrontEmptyState.toggleClass('hidden', vendorStorefrontVisibleCount !== 0);
  }

  $vendorStorefrontChips.on('click', function () {
    const $vendorStorefrontChip = $(this);
    const vendorStorefrontFilter = $vendorStorefrontChip.data('filter') || 'all';

    $vendorStorefrontChips
      .attr('aria-pressed', 'false')
      .removeClass('border-sage bg-sage font-bold text-ink shadow-sm')
      .addClass('border-line bg-surface font-semibold text-muted');
    $vendorStorefrontChip
      .attr('aria-pressed', 'true')
      .addClass('border-sage bg-sage font-bold text-ink shadow-sm')
      .removeClass('border-line bg-surface font-semibold text-muted');

    runVendorStorefrontFilter(vendorStorefrontFilter);
  });

  const vendorStorefrontInitialFilter = $vendorStorefrontChips.filter('[aria-pressed="true"]').first().data('filter') || 'all';
  runVendorStorefrontFilter(vendorStorefrontInitialFilter);
});
