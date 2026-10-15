# Dynamic Blade pages

All current customer-facing Blade routes use marketplace records rather than example products, people, addresses or orders. The existing CSS/JavaScript source files remain unchanged. Blade bindings, hidden native forms and the existing controllers provide browser actions; JSON clients retain their existing responses.

| Area | Data and behavior |
| --- | --- |
| Home | Sellable products, categories, approved stores, stock, reviews, content settings and newsletter subscriptions |
| Categories | Active category records, media, child counts and sellable product counts |
| Browse | Database product cards and filter options; existing client filters/sorting/pagination; server search with `q` and offers with `sale=1` |
| Product | Slug lookup, actual gallery, description, stock, price, specifications, published reviews/distribution and related products |
| Stores | Approved active stores, ratings, available product counts and slug-specific store pages |
| Cart | Session/customer cart, vendor groups, current prices, native quantity updates/removal and stock checks |
| Checkout | Owned addresses, new address persistence, current cart totals, server delivery fees, transaction and stock reservations |
| Customer dashboard | Owned latest order, its actual vendor statuses and favorite count |
| Customer orders | HTML history/detail using the current dashboard template; JSON preserved for API requests; order item prices are the saved order snapshots |
| Favorites | Owned visible products, real counters/vendor options, native add/remove and cart actions |
| Addresses | Owned address list, province options, native creation/edit/delete, existing modal and ownership checks |
| Account details | Current customer fields, native profile/password updates and server validation |
| Shared storefront | Site identity, currency, logo, cart count/total, database navigation, locale switching and GET search |
| Admin/vendor authentication and dashboards | Existing connected controllers/forms and vendor overview/profile scripts are preserved and tested |
| Driver account landing page | Current account name/status/profile state and actual assignment count |

Unavailable catalog data produces an empty state; unknown/ineligible product/store slugs produce 404. No sample data was inserted to populate the pages. `/product` without a slug redirects to browse when no public product exists.

Cart actions use the existing customer routes with CSRF and method spoofing. Product quantity controls submit GET requests so the current quantity can reach native purchase forms without editing JavaScript. The existing product design's maximum of three pieces per add is retained; cart mutations additionally enforce actual available stock.

Delivery fees come from `config/marketplace.php` (`delivery_fee`, per vendor, initially zero). Client-supplied fees cannot change the order total. Online orders remain pending payments; a real payment-provider integration is required before money can be collected online. Newsletter subscriptions are stored; campaign delivery requires an email service. Product fields without a stored value display an unspecified value instead of fabricated materials or preparation times.

The unused `welcome` and old owner/auth scaffolding are not registered public routes; this change does not activate obsolete guards or expose those templates. Shared package/media-library templates remain backed by their existing package/controllers.

## Verification

- Built existing Vite entries with `npm run build`; no CSS or JavaScript source edits.
- Ran PHPUnit with GD loaded at process startup for image-upload tests.
- Checked the existing Node tests with both TAP and spec reporter summaries accepted.
- Checked local public routes after starting the existing MySQL instance; no `.env` or database connection changes.

Windows verification command used in this environment:

```powershell
$env:NODE_OPTIONS = '--preserve-symlinks --preserve-symlinks-main'
php -d extension=gd vendor/phpunit/phpunit/phpunit --colors=never
```

Ordinary environments with GD already enabled can run `php artisan test`.
