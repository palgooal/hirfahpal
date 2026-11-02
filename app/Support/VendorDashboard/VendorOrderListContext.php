<?php

namespace App\Support\VendorDashboard;

/**
 * ORD-02A (step 4): the orders list's context (search, status, needs-response filter and page) carried from the list to
 * an order's details page and back. Only these four parameters are kept, each only when valid, read the way the list
 * script reads its own page address (vendor-resource-list.js: filtersFromQuery, pageNumber); anything else is dropped.
 * The way back is always the orders list's own route with that context: no address is taken from the request, so there
 * is no open redirect. Display only: the list endpoint validates its own parameters.
 */
final class VendorOrderListContext
{
    /** The parameters kept, in this order. */
    public const KEYS = ['search', 'status', 'needs_response', 'page'];

    /** The needs_response values that mean "on", as the list script and the orders endpoint read them. */
    private const ON = ['1', 'true', 'on', 'yes'];

    /**
     * The valid context in a query: a non-blank search (trimmed), a known status, needs_response=1, and a page of 2 to
     * 999999 (page 1 is the default and is left out). A value that is not a plain string is dropped.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    public static function fromQuery(array $query): array
    {
        $context = [];

        $search = $query['search'] ?? null;
        if (is_string($search) && trim($search) !== '') {
            $context['search'] = trim($search);
        }

        $status = $query['status'] ?? null;
        if (is_string($status) && array_key_exists($status, VendorOrderTexts::statuses())) {
            $context['status'] = $status;
        }

        $needsResponse = $query['needs_response'] ?? null;
        if (is_string($needsResponse) && in_array($needsResponse, self::ON, true)) {
            $context['needs_response'] = '1';
        }

        $page = $query['page'] ?? null;
        if (is_string($page) && preg_match('/^[1-9][0-9]{0,5}$/', $page) === 1 && (int) $page > 1) {
            $context['page'] = $page;
        }

        return $context;
    }

    /**
     * The orders list with the valid context of a query (the list without any when there is none).
     *
     * @param  array<string, mixed>  $query
     */
    public static function listUrl(array $query): string
    {
        return route('vendor.dashboard.orders.page', self::fromQuery($query));
    }
}
