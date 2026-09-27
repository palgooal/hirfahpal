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
                $('.mega-menu').removeClass('mega-menu-open').find('button').attr('aria-expanded', 'false');
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
            $('.mega-menu').not(this).removeClass('mega-menu-open').find('button').attr('aria-expanded', 'false');
            $(this).addClass('mega-menu-open').find('button').attr('aria-expanded', 'true');
        });

        $('.mega-menu').on('mouseleave', function () {
            const $menu = $(this);
            megaMenuTimer = setTimeout(() => {
                $menu.removeClass('mega-menu-open').find('button').attr('aria-expanded', 'false');
            }, 150);
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
