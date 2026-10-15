# Vendor directory backend

`GET /vendors` renders registered stores from `vendors` and `vendor_profiles` using the existing Blade layout. The existing CSS and JavaScript are unchanged.

- Only active vendor accounts with approved profiles appear publicly.
- Cards show the store name, initials, city (or governorate fallback), description, published vendor rating and available product count.
- Available products must satisfy `Product::sellable()` and have positive stock after outstanding reservations. Draft, suspended, archived and fully reserved products are excluded from the count.
- Ratings include only published reviews with `reviewable_type = vendor`. A store without reviews displays `—`.
- Stores sort by store name, then account ID. All public cards render together so the existing instant search can search the entire directory without JavaScript changes.
- No sample records are inserted. The existing empty state appears when there are no public stores.
- Vendor account email, phone and private profile/moderation fields are not rendered.

Each visit link targets `/vendors/{storeSlug}`. The existing store template now displays that store's profile, categories and sellable products. Unknown, inactive and unapproved stores return 404. `/vendors/dar-al-karma` remains available under the existing `vendors.show` route name and resolves actual database data. Preparation time displays `—` because there is no stored preparation-time field.

This work supplies the directory and the store data reached by its visit links. Existing product-page, cart and favorite button implementations are unchanged.

No additional migration or seeder is needed. Existing marketplace migrations must already be applied.

Verification:

```sh
php artisan test --filter='StorefrontVendorDirectoryTest|StorefrontHomePageTest|StorefrontAccountNavigationTest|StorefrontVendorSellabilityTest'
```
