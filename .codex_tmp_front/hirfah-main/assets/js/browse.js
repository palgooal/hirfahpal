$(function () {
  const $browsePage = $('.browse-page');
  if (!$browsePage.length) return;

  const categoryLabels = {
    pottery: 'الفخار والخزف',
    embroidery: 'التطريز',
    baskets: 'القش والسلال',
    'candles-soaps': 'الشموع والصابون',
    textiles: 'المنسوجات والوسائد',
    tableware: 'أواني التقديم',
    gifts: 'الهدايا التراثية',
    'home-decor': 'زينة البيت اليدوية'
  };

  const vendorLabels = {
    'dar-al-karma': 'دار الكرمة للخزف',
    'bethlehem-women': 'جمعية نساء بيت لحم',
    'noor-alzaytouna': 'مشغل نور الزيتونة',
    'jalil-weaving': 'نسج الجليل التراثي',
    'jerusalem-olivewood': 'خشب الزيتون المقدسي',
    'rawaq-ramallah': 'منسوجات رواق رام الله',
    'khan-nablus-soap': 'صابون خان نابلس',
    'marj-embroidery': 'تطريز مرج ابن عامر'
  };

  const cityLabels = {
    jerusalem: 'القدس',
    hebron: 'الخليل',
    bethlehem: 'بيت لحم',
    ramallah: 'رام الله',
    nablus: 'نابلس',
    jenin: 'جنين',
    galilee: 'الجليل'
  };

  const pageSize = 8;
  const $cards = $('#productGrid .product-card');
  const $grid = $('#productGrid');
  const $activeFilters = $('#activeFilters');
  const $emptyState = $('#browseEmptyState');
  const $pagination = $('#browsePagination');
  const $resultCount = $('#resultCount');
  const $pageSummary = $('#pageSummary');
  const $breadcrumbCurrent = $('#breadcrumbCurrent');
  let currentPage = 1;
  let filteredCards = $cards.toArray();

  function selectedValues(name) {
    return $(`input[name="${name}"]:checked`).map(function () {
      return this.value;
    }).get();
  }

  function currentState() {
    const minPrice = Number($('#priceMin').val());
    const maxPrice = Number($('#priceMax').val());

    return {
      categories: selectedValues('category'),
      vendors: selectedValues('vendor'),
      cities: selectedValues('city'),
      minPrice: Number.isFinite(minPrice) && minPrice > 0 ? minPrice : null,
      maxPrice: Number.isFinite(maxPrice) && maxPrice > 0 ? maxPrice : null,
      sort: $('#sortProducts').val()
    };
  }

  function matchesCard(card, state) {
    const $card = $(card);
    const price = Number($card.data('price'));

    if (state.categories.length && !state.categories.includes(String($card.data('category')))) return false;
    if (state.vendors.length && !state.vendors.includes(String($card.data('vendor')))) return false;
    if (state.cities.length && !state.cities.includes(String($card.data('city')))) return false;
    if (state.minPrice !== null && price < state.minPrice) return false;
    if (state.maxPrice !== null && price > state.maxPrice) return false;

    return true;
  }

  function sortCards(cards, sortValue) {
    return cards.sort(function (firstCard, secondCard) {
      const $first = $(firstCard);
      const $second = $(secondCard);

      if (sortValue === 'price-asc') return Number($first.data('price')) - Number($second.data('price'));
      if (sortValue === 'price-desc') return Number($second.data('price')) - Number($first.data('price'));
      if (sortValue === 'rating-desc') return Number($second.data('rating')) - Number($first.data('rating'));

      return Number($second.data('date')) - Number($first.data('date'));
    });
  }

  function chipLabel(type, value) {
    if (type === 'category') return `الفئة: ${categoryLabels[value] || value}`;
    if (type === 'vendor') return `التاجر: ${vendorLabels[value] || value}`;
    if (type === 'city') return `المدينة: ${cityLabels[value] || value}`;
    return value;
  }

  function addChip(type, value, label) {
    const chip = $(`
      <button type="button"
        class="active-filter-chip inline-flex min-h-9 items-center gap-1.5 rounded-full border border-sage bg-sage/40 px-3 py-1.5 text-xs font-bold text-ink"
        data-filter-type="${type}" data-filter-value="${value}">
        <span>${label}</span>
        <i data-lucide="x" class="h-3.5 w-3.5"></i>
      </button>
    `);

    $activeFilters.append(chip);
  }

  function renderChips(state) {
    $activeFilters.empty();

    state.categories.forEach((category) => addChip('category', category, chipLabel('category', category)));
    state.vendors.forEach((vendor) => addChip('vendor', vendor, chipLabel('vendor', vendor)));
    state.cities.forEach((city) => addChip('city', city, chipLabel('city', city)));

    if (state.minPrice !== null || state.maxPrice !== null) {
      const minLabel = state.minPrice !== null ? state.minPrice : 0;
      const maxLabel = state.maxPrice !== null ? state.maxPrice : 'مفتوح';
      addChip('price', 'range', `السعر: ${minLabel} - ${maxLabel} ₪`);
    }

    $('#clearAllFilters').toggleClass('hidden', !$activeFilters.children().length);
    lucide.createIcons();
  }

  function syncSummary(state) {
    const primaryCategory = state.categories[0];
    const nextLabel = primaryCategory ? categoryLabels[primaryCategory] : 'نتائج البحث';

    $breadcrumbCurrent.text(nextLabel);
    $resultCount.text(filteredCards.length);
  }

  function syncUrl(state) {
    const params = new URLSearchParams();

    state.categories.forEach((category) => params.append('category', category));
    state.vendors.forEach((vendor) => params.append('vendor', vendor));
    state.cities.forEach((city) => params.append('city', city));
    if (state.minPrice !== null) params.set('min', state.minPrice);
    if (state.maxPrice !== null) params.set('max', state.maxPrice);
    if (state.sort && state.sort !== 'newest') params.set('sort', state.sort);

    const query = params.toString();
    history.replaceState(null, '', `${window.location.pathname}${query ? `?${query}` : ''}`);
  }

  function renderPagination() {
    const totalPages = Math.ceil(filteredCards.length / pageSize);
    $pagination.empty();

    if (totalPages <= 1) {
      $pagination.addClass('hidden');
      return;
    }

    $pagination.removeClass('hidden');

    const prevDisabled = currentPage === 1;
    const nextDisabled = currentPage === totalPages;

    $pagination.append(`
      <button type="button" class="pagination-control inline-flex h-10 items-center gap-1.5 rounded-full border border-line bg-surface px-4 text-sm font-bold text-olive disabled:cursor-not-allowed disabled:opacity-40" data-page="${currentPage - 1}" ${prevDisabled ? 'disabled' : ''}>
        <span>السابق</span>
      </button>
    `);

    for (let page = 1; page <= totalPages; page += 1) {
      const isCurrent = page === currentPage;
      $pagination.append(`
        <button type="button" class="pagination-control flex h-10 min-w-10 items-center justify-center rounded-full border px-3 text-sm font-bold ${isCurrent ? 'border-olive bg-olive text-surface' : 'border-line bg-surface text-olive'}" data-page="${page}" aria-current="${isCurrent ? 'page' : 'false'}">
          <bdi>${page}</bdi>
        </button>
      `);
    }

    $pagination.append(`
      <button type="button" class="pagination-control inline-flex h-10 items-center gap-1.5 rounded-full border border-line bg-surface px-4 text-sm font-bold text-olive disabled:cursor-not-allowed disabled:opacity-40" data-page="${currentPage + 1}" ${nextDisabled ? 'disabled' : ''}>
        <span>التالي</span>
      </button>
    `);

    lucide.createIcons();
  }

  function renderPage() {
    const pageStart = (currentPage - 1) * pageSize;
    const pageEnd = pageStart + pageSize;
    const pageCards = filteredCards.slice(pageStart, pageEnd);

    $cards.addClass('hidden');
    $(pageCards).removeClass('hidden');
    $grid.append(filteredCards);

    const firstItem = filteredCards.length ? pageStart + 1 : 0;
    const lastItem = Math.min(pageEnd, filteredCards.length);
    $pageSummary.html(filteredCards.length
      ? `عرض <bdi>${firstItem}</bdi>-<bdi>${lastItem}</bdi> من <bdi>${filteredCards.length}</bdi> نتيجة`
      : 'لا توجد نتائج للعرض');

    $emptyState.toggleClass('hidden', filteredCards.length !== 0);
    renderPagination();
  }

  function applyFilters(resetPage = true) {
    const state = currentState();
    if (resetPage) currentPage = 1;

    filteredCards = sortCards($cards.filter(function () {
      return matchesCard(this, state);
    }).toArray(), state.sort);

    renderChips(state);
    syncSummary(state);
    syncUrl(state);
    renderPage();
  }

  function resetFilters() {
    $('#browseFilters input[type="checkbox"]').prop('checked', false);
    $('#priceMin, #priceMax').val('');
    $('#sortProducts').val('newest');
    applyFilters();
  }

  function applyQueryParams() {
    const params = new URLSearchParams(window.location.search);
    const queryCategories = params.getAll('category');
    const queryVendors = params.getAll('vendor');
    const queryCities = params.getAll('city');

    if (queryCategories.length) $('input[name="category"]').prop('checked', false);
    if (queryVendors.length) $('input[name="vendor"]').prop('checked', false);
    if (queryCities.length) $('input[name="city"]').prop('checked', false);

    queryCategories.forEach((category) => $(`input[name="category"][value="${category}"]`).prop('checked', true));
    queryVendors.forEach((vendor) => $(`input[name="vendor"][value="${vendor}"]`).prop('checked', true));
    queryCities.forEach((city) => $(`input[name="city"][value="${city}"]`).prop('checked', true));

    if (params.has('min')) $('#priceMin').val(params.get('min'));
    if (params.has('max')) $('#priceMax').val(params.get('max'));
    if (params.has('sort')) $('#sortProducts').val(params.get('sort'));
  }

  $('#browseFilters').on('change', 'input, select', function () {
    applyFilters();
  });

  $('#applyPriceFilter').on('click', function () {
    applyFilters();
  });

  $activeFilters.on('click', '.active-filter-chip', function () {
    const $chip = $(this);
    const type = $chip.data('filter-type');
    const value = String($chip.data('filter-value'));

    if (type === 'price') {
      $('#priceMin, #priceMax').val('');
    } else {
      $(`input[name="${type}"][value="${value}"]`).prop('checked', false);
    }

    $chip.remove();
    applyFilters();
  });

  $('#clearAllFilters, #emptyResetFilters').on('click', resetFilters);

  $pagination.on('click', '.pagination-control', function () {
    const nextPage = Number($(this).data('page'));
    const totalPages = Math.ceil(filteredCards.length / pageSize);

    if (!nextPage || nextPage < 1 || nextPage > totalPages) return;
    currentPage = nextPage;
    renderPage();

    const target = $('#resultsPanel');
    if (target.length) $('html, body').animate({ scrollTop: target.offset().top - 20 }, 260);
  });

  applyQueryParams();
  applyFilters();
});
