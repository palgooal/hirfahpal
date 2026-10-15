# Homepage backend

GET `/` renders the existing Blade layout with database products, categories, approved active stores, published product reviews, current prices, stock availability and the visitor's cart/favorites. Popular products rank by quantities in delivered/completed vendor orders, then published review count. New arrivals sort by publication date; seasonal picks use `products.is_featured`.

The homepage does not insert sample catalog records or create a cart on a read request. Empty sections show their empty message. Existing catalog, product, vendor and cart destination pages remain outside this change.

## Editable copy and images

Defaults are in `config/homepage.php`; `settings.home_content` stores partial overrides. Existing settings supply the site title, description and currency. Text is escaped by Blade. Editorial images accept public disk paths, `/assets/...` paths or HTTP(S) URLs.

Authenticated administrators with settings permissions can use:

- `GET /dashboard/settings/home-content`: returns all current keys and values.
- `PATCH /dashboard/settings/home-content`: merges submitted keys into stored overrides; requires edit permission and a CSRF token for session requests.

Example JSON body:

```json
{
    "home_content": {
        "hero_text_1": "سوق الحرف اليدوية الفلسطينية",
        "hero_image_hero-main": "homepage/hero.jpg",
        "newsletter_text_3": "اشترك لتصلك أحدث أخبار المشاغل"
    }
}
```

Unknown keys and non-string values are rejected. No administration UI or CSS changes are required. Use the GET response to discover the exact editable keys.

## Actions

Homepage cart and favorite buttons call the existing customer endpoints with CSRF protection. Guests can add to their session cart; favorites require customer login. Cart counts/totals update from successful server responses, and stock reservations are considered when disabling unavailable products.

POST `/newsletter` validates and normalizes email, stores one subscription per email and limits requests to five per minute. It supports JSON and standard form submission. This stores subscriptions; outbound campaigns and promotional discounts require a separate integration.

## Migration and verification

Run `php artisan migrate` before serving the page. The application also requires its existing product moderation, category media and favorites migrations.

```sh
php artisan test --filter='StorefrontHomePageTest|StorefrontAccountNavigationTest|StorefrontVendorSellabilityTest'
```
