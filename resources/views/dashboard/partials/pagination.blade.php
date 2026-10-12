{{--
    Admin pagination (AD-UI-01 5.4, DEC-AD-UI-08): result count, previous / next and page numbers, translated
    through t() without auto-creating keys. Used as $paginator->links('dashboard.partials.pagination'); the
    paginator keeps the query string (withQueryString), so filters survive paging.
--}}
@php
    $pageText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };
    $pageNumber = fn ($value) => (string) (int) $value;
@endphp

@if ($paginator->total() > 0)
    <nav class="admin-pagination" aria-label="{{ $pageText('Pagination_Label', 'Pagination') }}">
        <p class="admin-pagination-summary">
            {{ strtr($pageText('Pagination_Summary', 'Showing :from to :to of :total'), [':from' => $pageNumber($paginator->firstItem()), ':to' => $pageNumber($paginator->lastItem()), ':total' => $pageNumber($paginator->total())]) }}
        </p>

        @if ($paginator->hasPages())
            <ul class="admin-pagination-list">
                <li>
                    @if ($paginator->onFirstPage())
                        <span class="admin-page is-disabled" aria-disabled="true">
                            <i class="ph-duotone ph-caret-left admin-page-arrow" aria-hidden="true"></i>
                            <span>{{ $pageText('Pagination_Previous', 'Previous') }}</span>
                        </span>
                    @else
                        <a class="admin-page" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                            <i class="ph-duotone ph-caret-left admin-page-arrow" aria-hidden="true"></i>
                            <span>{{ $pageText('Pagination_Previous', 'Previous') }}</span>
                        </a>
                    @endif
                </li>

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li><span class="admin-page is-gap" aria-hidden="true">…</span></li>
                    @else
                        @foreach ($element as $page => $url)
                            <li>
                                @if ($page == $paginator->currentPage())
                                    <span class="admin-page is-current" aria-current="page">{{ $pageNumber($page) }}</span>
                                @else
                                    <a class="admin-page" href="{{ $url }}" aria-label="{{ str_replace(':page', $pageNumber($page), $pageText('Pagination_Page', 'Page :page')) }}">{{ $pageNumber($page) }}</a>
                                @endif
                            </li>
                        @endforeach
                    @endif
                @endforeach

                <li>
                    @if ($paginator->hasMorePages())
                        <a class="admin-page" href="{{ $paginator->nextPageUrl() }}" rel="next">
                            <span>{{ $pageText('Pagination_Next', 'Next') }}</span>
                            <i class="ph-duotone ph-caret-right admin-page-arrow" aria-hidden="true"></i>
                        </a>
                    @else
                        <span class="admin-page is-disabled" aria-disabled="true">
                            <span>{{ $pageText('Pagination_Next', 'Next') }}</span>
                            <i class="ph-duotone ph-caret-right admin-page-arrow" aria-hidden="true"></i>
                        </span>
                    @endif
                </li>
            </ul>
        @endif
    </nav>
@endif
