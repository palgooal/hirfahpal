const $ = window.jQuery;

if ($) {
    $(function () {
        if (document.documentElement.dataset.storefrontHeaderInitialized === 'true') return;
        document.documentElement.dataset.storefrontHeaderInitialized = 'true';

        window.lucide?.createIcons();

        // The markup-only phase disabled this control until its behavior was available.
        if ($('#searchPanel').length) {
            $('#searchButton').prop('disabled', false).removeAttr('aria-disabled');
        }

        // VP-FIX-02: the mobile menu is a disclosure. Its button reports the state (aria-expanded) and controls
        // #mobileMenu (aria-controls); opening moves focus to the menu's first item, Escape closes it (below).
        const $menuButton = $('#menuButton');
        const $mobileMenu = $('#mobileMenu');
        const setMobileMenu = (open, moveFocus = false) => {
            $menuButton.attr('aria-expanded', String(open));
            if (open) {
                $mobileMenu.stop(true, true).slideDown(180, () => {
                    if (moveFocus) $mobileMenu.find('a, button').first().trigger('focus');
                });
            } else {
                $mobileMenu.stop(true, true).slideUp(150);
            }
        };

        $menuButton.on('click', function () {
            setMobileMenu($menuButton.attr('aria-expanded') !== 'true', true);
        });

        $('#mobileMenu a').on('click', function () {
            setMobileMenu(false);
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

        // VP-FIX-02: one place sets a mega menu's state, so aria-expanded always matches what is shown. A menu
        // opened by click or keyboard is "pinned": moving the mouse away does not close it.
        const setMegaMenu = ($menu, open) => {
            $menu.toggleClass('mega-menu-open', open).children('button').attr('aria-expanded', String(open));
            if (!open) $menu.removeData('megaMenuPinned');
        };

        $(document).on('keydown', function (event) {
            if (event.key === 'Escape') {
                $('.nav-dropdown').removeClass('nav-dropdown-open').find('button').attr('aria-expanded', 'false');
                // VP-FIX-02: closing a menu that holds the focus gives the focus back to the button that opened it.
                $('.mega-menu').each(function () {
                    const hadFocus = this.contains(document.activeElement);
                    if ($(this).hasClass('mega-menu-open')) setMegaMenu($(this), false);
                    if (hadFocus) $(this).children('button').trigger('focus');
                });
                if ($menuButton.attr('aria-expanded') === 'true') {
                    const hadFocus = $mobileMenu[0]?.contains(document.activeElement);
                    setMobileMenu(false);
                    if (hadFocus) $menuButton.trigger('focus');
                }
            }
        });

        let megaMenuTimer;

        function setActiveMegaCategory($menu, category) {
            $menu.find('.mega-category-trigger').removeClass('mega-category-active');
            $menu.find(`[data-mega-category="${category}"]`).addClass('mega-category-active');
            $menu.find('.mega-panel').addClass('hidden').removeClass('grid');
            $menu.find(`[data-mega-panel="${category}"]`).removeClass('hidden').addClass('grid');
        }

        $('.mega-menu').each(function () {
            const $menu = $(this);
            setActiveMegaCategory($menu, $menu.find('.mega-category-trigger').first().data('mega-category'));
        });

        $('.mega-menu').on('mouseenter', function () {
            clearTimeout(megaMenuTimer);
            $('.mega-menu').not(this).each(function () { setMegaMenu($(this), false); });
            setMegaMenu($(this), true);
        });

        $('.mega-menu').on('mouseleave', function () {
            const $menu = $(this);
            if ($menu.data('megaMenuPinned')) return;
            megaMenuTimer = setTimeout(() => setMegaMenu($menu, false), 150);
        });

        // VP-FIX-02: click, Enter or Space opens the menu (and pins it); a second activation closes it. Tab moves into
        // the panel (its links follow the button); leaving the menu with the keyboard, or clicking outside, closes it.
        $('.mega-menu > button').on('click', function (event) {
            event.stopPropagation();
            const $menu = $(this).closest('.mega-menu');
            clearTimeout(megaMenuTimer);
            if ($menu.data('megaMenuPinned')) {
                setMegaMenu($menu, false);
            } else {
                $('.mega-menu').not($menu).each(function () { setMegaMenu($(this), false); });
                setMegaMenu($menu, true);
                $menu.data('megaMenuPinned', true);
            }
        });

        $('.mega-menu').on('focusout', function (event) {
            if (!this.contains(event.relatedTarget)) setMegaMenu($(this), false);
        });

        $(document).on('click', function (event) {
            $('.mega-menu.mega-menu-open').each(function () {
                if (!this.contains(event.target)) setMegaMenu($(this), false);
            });
        });

        $('.mega-category-trigger').on('mouseenter focus', function () {
            setActiveMegaCategory($(this).closest('.mega-menu'), $(this).data('mega-category'));
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
            }
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
}
