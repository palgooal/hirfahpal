# HIRFAH — Site Availability (Open / Coming Soon / Maintenance)

| Field | Value |
|---|---|
| Document | SITE-AVAILABILITY-DESIGN-02, decisions finalized in SITE-AVAILABILITY-DESIGN-03, synchronized with the Phase 1 implementation in SITE-AVAILABILITY-DOC-SYNC-01; font decision and Phase 2A updated in SITE-AVAILABILITY-PHASE-02A |
| Status | Decisions approved (DEC-SA-01 to DEC-SA-09; DEC-SA-04 font part revised). **Phases 1, 2 (2A, 2B) and 3 (3A, 3B, 3C) implemented and tested locally** (not committed, not deployed); Phase 4 (rollout) pending |
| Supersedes | MAINTENANCE-MODE-01 (boolean maintenance design, chat report only) |
| Project | HIRFAH (Laravel 12, `F:\laragon\www\hirfahpal`) |
| Baseline | `main` at `f4f3c4f` |
| Date | 2026-10-20 |

> This document was written before implementation and has since been synchronized with the Phase 1 code and its verification (§0). Where an earlier statement differed from the implementation, the implemented and verified behaviour is described.

## 0. Implementation status

| Phase | Scope | Status |
|---|---|---|
| 1 — Core | Enum, resolution service, configuration, middleware (gate), blocked responses with a temporary page, migration; service, gate, vendor-allowlist and migration tests | **Implemented and tested locally** (SQLite and an isolated MySQL 8.4.3 database). Not committed, not deployed, not migrated on the development database. |
| 2 — Pages | **2A**: converted original Coming Soon page, Maintenance page, local CSS replacing the Tailwind CDN, Tajawal kept on Google Fonts (DEC-SA-04). **2B**: closed-state handling of unknown URLs (DEC-SA-09) | **2A and 2B implemented and tested locally** (SITE-AVAILABILITY-PHASE-02A, -02B-IMPLEMENTATION; not committed, not deployed) |
| 3 — Admin | **3A**: `maintenance.bypass` registration, labels, Super Admin-only grant/revoke. **3B**: settings card, update route, settings translation seeder. **3C**: admin header badge, storefront preview indicator | **3A, 3B and 3C implemented and tested locally** (SITE-AVAILABILITY-PHASE-03A, -03B, -03C; not committed, seeders not run) |
| 4 — Rollout | Backup, development migration and seeders, browser checks, cPanel sequence (§10) | Pending; production database engine and version still to be confirmed (T-3) |

Verification evidence (Phase 1): §11.3. Both temporary MySQL test databases used for it (`hirfah_site_availability_test`, `hirfah_fk_test_site_availability_fix01`) were dropped after verification; the development database and `hirfah_fk_test_ordbe001` were verified unchanged.

---

## 1. Approved decisions

| # | Decision |
|---|---|
| SA-01 | The site has exactly one availability state at a time: `open`, `coming_soon` or `maintenance`. |
| SA-02 | One centralized service (`SiteAvailability`) resolves the state and one middleware applies it. No per-route duplication. |
| SA-03 | Only a Super Admin (`Admin::isSuperAdmin()`) may change the state. |
| SA-04 | A Super Admin always bypasses `coming_soon` and `maintenance`. |
| SA-05 | Any other admin bypasses only with the explicit ability `maintenance.bypass`. |
| SA-06 | Bypass allows previewing the storefront; it never grants any other permission. Every route keeps its own guard and ability checks. |
| SA-07 | The admin login, logout and password-reset routes stay reachable in every state. |
| SA-08 | Admins keep using the dashboard according to their existing permissions in every state (the dashboard is not gated). |
| SA-09 | The Coming Soon design and branding come from the user-provided original `index.html` and `hirfah-logo.jpeg`, without redesign. |
| SA-10 | Admins previewing the storefront in a non-`open` state see a clear status indicator. |
| SA-11 | An environment override (`HIRFAH_SITE_AVAILABILITY`) exists for cPanel deployment and emergency recovery. |
| SA-12 | Laravel's `php artisan down` stays a separate, technical deploy switch; it is not used for the business state. |

HTTP contract (approved): `open` = normal responses; `coming_soon` = HTTP 200 with `X-Robots-Tag: noindex` and no-cache headers; `maintenance` = HTTP 503 with `Retry-After: 3600`, `X-Robots-Tag: noindex` and no-cache headers; JSON requests receive a structured JSON body, never HTML.

### 1.1 Decisions finalized in SITE-AVAILABILITY-DESIGN-03

| # | Decision | Where applied |
|---|---|---|
| DEC-SA-01 | **Vendor access during Coming Soon.** Active, authenticated vendors may use their own vendor dashboard during `coming_soon`, through an explicit route-name allowlist (not a `vendor/*` prefix exemption). Vendor authentication, `EnsureAccountIsActive`, `vendor.approved`, policies and ownership checks stay fully enforced. Vendors never bypass the public storefront closure. Vendor access is blocked during `maintenance`. Customers and delivery drivers stay blocked in both closed states. Vendor registration is never allowed in a closed state. | §2.1, §4, §5.1, §5.5, §11 |
| DEC-SA-02 | **HTTP behaviour.** Blocked `GET`/`HEAD` during `coming_soon`: 200 with `noindex` and no-store headers. Blocked `POST`/`PUT`/`PATCH`/`DELETE`: 503 in both closed states. Everything blocked during `maintenance`: 503. JSON requests receive structured JSON. No blocked mutation reaches its controller. (Closes former Q-2.) | §5.3 |
| DEC-SA-03 | **Coming Soon content.** No email subscription or registration form; the original Arabic content and design are preserved. (Closes former Q-3.) | §8.2 |
| DEC-SA-04 | **Assets and fonts** (font part revised in SITE-AVAILABILITY-PHASE-02A). Preserve the supplied `index.html` design and `hirfah-logo.jpeg`; replace the Tailwind CDN with production-ready local CSS; keep Tajawal exactly as the original references it — the same Google Fonts stylesheet and weights (400, 500, 700, 800, 900) — with `sans-serif` as the fallback; Tajawal is **not** downloaded, bundled or self-hosted, and no `.woff2` or `OFL.txt` is required. Google Fonts is an **approved optional external dependency**: the pages remain fully functional and readable when it is unavailable. No database access, no Tailwind CDN, no other external resource. (Closes former Q-4.) | §8.3, §8.4 |
| DEC-SA-05 | **Locale.** `change-locale/{locale}` stays reachable in every state; it only stores the language in the session and redirects, never renders storefront content and never bypasses authorization (the redirect target is gated as usual). (Closes former Q-5.) | §5.1, §5.4 |
| DEC-SA-06 | **Admin indicators.** The current availability state appears in the admin header; authorized admins previewing the storefront see a preview indicator. (Closes former Q-6.) | §7.4 |
| DEC-SA-07 | **Source assets.** The original Coming Soon HTML and JPEG logo were supplied by the user outside the repository and are not present locally yet. Destinations: `resources/site-availability/source/index.html` and `public/assets/site-availability/hirfah-logo.jpeg`. No file is invented or substituted. (Tracks former Q-7.) *Status (DOC-SYNC-01): both files are now present at these destinations — B-1 resolved.* | §8.1 |
| DEC-SA-08 | **First deployment.** The first cPanel deployment starts with `HIRFAH_SITE_AVAILABILITY=coming_soon`; the existing public Coming Soon page stays visible until Laravel's replacement is verified; the configuration-cache implications and emergency recovery are documented; the admin card states clearly when the environment override takes precedence over the database; the site is never switched to `open` automatically during deployment. Added in DOC-SYNC-01: `SESSION_DRIVER=file` until the migrations are complete, a valid `APP_KEY`, a writable `storage/framework/sessions`, and the production database engine and version verified before migrating (T-3). | §6.6, §7.1, §10 |
| DEC-SA-09 | **Unknown URLs (Phase 2B, implemented).** While the site is closed, an unknown public URL shows the availability page (same status and headers as §5.3) instead of a 404; while the site is open, normal 404 behaviour is unchanged. Admin authentication and dashboard security are unchanged. The closed-state fallback introduces no database dependency. Refined by D1–D4 (static resources, unsupported methods, OPTIONS → 503, no admin bypass on unmatched requests). | §5.6 |

---

## 2. Architecture

Effective order of a web request (Laravel 12, as implemented; the gate is in the `web` group and positioned by the middleware priority list):

```
request ─► EncryptCookies → AddQueuedCookies → StartSession → ShareErrors → ValidateCsrfToken
         → EnsureSiteIsAvailable (gate)
              1. path exempt (admin/*, dashboard/*, up, change-locale/*)?  → continue (no state read)
              2. SiteAvailability::current()  (memoized on the request attributes)
                    ├─ config override (env) when not `auto`
                    ├─ settings.site_availability_mode
                    └─ default: open
              3. open → continue
              4. active admin, Super Admin or `maintenance.bypass` → continue (preview)
              5. coming_soon and route name on the vendor allowlist → continue
              6. otherwise → SiteAvailabilityResponse (200/503, no Blade, no database)
         → route authentication (auth:*), throttling, vendor.approved, SubstituteBindings (model binding)
         → EnsureAccountIsActive, setLocale and the other route middleware → controller
```

### 2.1 Components

| Component | Responsibility |
|---|---|
| `App\Support\SiteAvailability\SiteAvailabilityState` (PHP backed enum) | The three allowed values `open`, `coming_soon`, `maintenance`; labels; HTTP status per state. Single source of truth for allowed values. |
| `App\Support\SiteAvailability\SiteAvailability` (resolved from the container with the current request) | `current(): SiteAvailabilityState` (precedence in §6.3); `source(): 'environment'|'database'|'default'`; `override()`; `canBypass(?Admin): bool` (the admin must be **active** — `status === 'active'` — and be a Super Admin or hold `maintenance.bypass`); `forget()`. The result is **memoized on the request attributes**, so one request reads the setting at most once and a later request never sees a stale value. Never throws: any failure resolves to the default and logs a warning without request data (§6.5). |
| `App\Http\Middleware\EnsureSiteIsAvailable` | Checks the exempt paths first (§5.1, no state read and no query for them), then lets the request through when the state is `open`, when an active admin may bypass, or — during `coming_soon` only — when the route is on the vendor allowlist (§5.5, DEC-SA-01); otherwise returns the state response (§5). When an admin bypasses it sets the request attribute `site_availability_preview` (the state) for the Phase 3 indicator. Matches the vendor allowlist by **route name** (resolved route), never by path prefix. Never signs anyone out and never writes to the session. |
| `App\Support\SiteAvailability\SiteAvailabilityResponse` | Builds the HTML or JSON response with the exact status and headers (§5.3), without Blade and without any database access. Since Phase 2A it serves the converted templates (§8) with their CSS inlined from a tracked file, keeping the Phase 1 minimal Arabic page (no external resource, no form) as the logged fallback if a template or the CSS is missing; status and headers unchanged. |
| `App\Http\Controllers\Dashboard\SiteAvailabilityController@update` | The single write path (Super Admin only, confirmation required, §7). |
| `config/site-availability.php` | Reads `HIRFAH_SITE_AVAILABILITY`, `Retry-After`, exempt path patterns, static-resource rules for unmatched requests (D1). The only place that reads `env()`. |
| `App\Support\SiteAvailability\UnmatchedRequestResponse` | Phase 2B: called by the exception handler for requests no route matched; returns the availability response while closed, otherwise null (normal 404/405) (§5.6). |

### 2.2 Middleware placement (as implemented)

`bootstrap/app.php` appends `EnsureSiteIsAvailable` to the `web` group and calls `prependToPriorityList(AuthenticatesRequests::class, EnsureSiteIsAvailable::class)`. Laravel sorts the route's middleware by that priority list, which gives the order of §2:

- **After** `StartSession` and `ValidateCsrfToken`: the admin's identity is needed for bypass, and all guards (`admin`, `customer`, `vendor`, `delivery_driver`, `web`) share one session cookie (`config/auth.php`), so `Auth::guard('admin')->user()` is available once the session has started. A gated POST with an invalid token still gets the existing 419.
- **Before** route authentication (`auth:*`), throttling, `vendor.approved`, `SubstituteBindings` (route-model binding) and `EnsureAccountIsActive`: a blocked request never reaches an authentication redirect, a binding 404, a rate limiter or a controller.
- Why the priority entry is required: in Laravel 12 the `web` group already contains `SubstituteBindings`, so simply appending the gate (the original design) would have run it **after** model binding and after route authentication. Tests confirm the implemented order: a guest on `/customer/dashboard` and an unknown product slug get the state page, not a login redirect or a 404 (§11.3).
- Because the gate now runs **before** `EnsureAccountIsActive`, it does not rely on that middleware to sign out a deactivated admin: `canBypass()` checks `status === 'active'` itself. `EnsureAccountIsActive` still runs on every request the gate lets through (for example it still signs out an inactive vendor on an allowlisted vendor route).
- Exempt paths are checked before the state is resolved, so they cost no settings read (the dashboard keeps its existing query budget).
- `/up` (Laravel health route, registered by `withRouting(health: '/up')`) is outside the `web` group and is never gated.

### 2.3 Why static templates instead of Blade

The application registers a catch-all view composer (`AppServiceProvider`, `view()->composer('*', …)`) that queries `languages` for every Blade view, and the storefront composer queries settings, cart and categories. The availability pages must render when the database is slow, empty, unmigrated or unreachable, so they are plain HTML files read from disk with two placeholder substitutions (CSS URL, logo URL) — Phase 2; Phase 1 serves a minimal built-in page with the same guarantees. No database query, no view composer, no session write beyond the framework's normal cookie handling (verified: zero queries for a blocked request with a forced state).

---

## 3. State-transition rules

| From → To | Allowed | Extra warning shown in the confirmation |
|---|---|---|
| `open` → `coming_soon` | Yes | The public storefront is replaced by the Coming Soon page; existing customer, vendor and driver sessions are kept but blocked. |
| `open` → `maintenance` | Yes | Maintenance warning (§7.3): the whole storefront and all customer, vendor and driver areas answer 503. |
| `coming_soon` → `open` | Yes | Launch warning: the store becomes public to everyone. |
| `coming_soon` → `maintenance` | Yes | Maintenance warning. |
| `maintenance` → `open` | Yes | — |
| `maintenance` → `coming_soon` | Yes | — |
| same → same | No change | Rejected as a no-op with an informational message; nothing is written. |

Rules:

1. Every accepted change writes `site_availability_mode`, `site_availability_changed_at = now()` and `site_availability_changed_by = <admin id>` in one statement.
2. A change requires the explicit confirmation (§7.2), validated on the server.
3. Changes take effect on the next request (no cache to invalidate, §6.4).
4. A change is accepted while an environment override is active, but the card states that the override currently wins (§7.1).
5. No automatic transitions (no schedule, no countdown that flips the state).

---

## 4. Authorization matrix

Legend: **Page** = the state page (Coming Soon 200 or Maintenance 503); **Allowed** = normal application behaviour with the actor's existing permissions.

### 4.1 Storefront and account areas

| Actor | `open` | `coming_soon`: storefront, customer and driver areas, Fortify routes | `coming_soon`: vendor allowlist (§5.5) | `coming_soon`: other `vendor/*` (registration) | `maintenance`: everything gated, vendor area included |
|---|---|---|---|---|---|
| Guest | Allowed | Page | Vendor login and password-recovery pages reachable; dashboard routes redirect to `vendor.login` (existing `auth:vendor`) | Page | Page |
| Customer (signed in) | Allowed | Page (session kept) | As guest for the vendor guard | Page | Page (session kept) |
| Vendor, active (signed in) | Allowed | Page (session kept) — no storefront bypass | **Allowed** with all existing checks (`auth:vendor`, `vendor.approved` → `vendor.approval-status` for unapproved stores, ownership) | Page | Page (session kept) |
| Vendor, inactive or blocked | Signed out by `EnsureAccountIsActive` | Page (the gate answers first; no sign-out on gated paths) | Passes the gate, then signed out by `EnsureAccountIsActive` and redirected to `vendor.login` | Page | Page |
| Delivery driver (signed in) | Allowed | Page (session kept) | As guest for the vendor guard | Page | Page (session kept) |
| Admin without `maintenance.bypass` | Allowed | Page | As guest for the vendor guard | Page | Page |
| Admin with `maintenance.bypass` | Allowed | Allowed + preview indicator; no added permission | Normal (bypass) | Normal (bypass) | Allowed + preview indicator |
| Super Admin | Allowed | Allowed + preview indicator | Normal (bypass) | Normal (bypass) | Allowed + preview indicator |
| Pending or blocked admin (any abilities, Super Admin included) | — | Page (bypass refused by the gate's own `status === 'active'` check) | As guest for the vendor guard | Page | Page |

### 4.2 Admin areas and the state switch (every state)

| Actor | `admin/*` (login, forgot/reset password, logout) | `dashboard/*` | Change the state |
|---|---|---|---|
| Guest, customer, vendor, driver | Allowed (login pages are `guest:admin`) | Redirect to admin login (existing `auth:admin`) | No |
| Admin without `maintenance.bypass` | Allowed | Allowed by existing abilities; header shows the state | 403 |
| Admin with `maintenance.bypass` | Allowed | Allowed by existing abilities; header shows the state | 403 |
| Super Admin | Allowed | Allowed; header shows the state | Allowed |

Notes:

- Bypass is evaluated only for the `admin` guard. Being signed in as a customer, vendor or driver never bypasses the storefront closure, even in the same browser as an admin.
- The vendor allowlist (DEC-SA-01) is not a bypass: it opens only named vendor routes, only in `coming_soon`, and every one of them keeps its own guard and middleware.
- Bypass requires an **active** admin account (`status === 'active'`), checked by the gate itself because it runs before `EnsureAccountIsActive` (§2.2). A pending or blocked admin never bypasses, whatever their abilities.
- An admin who bypasses and is *also* signed in as a customer/vendor/driver in the same browser acts with that account's own permissions on its routes (see risk R-1).
- The new ability is the only authorization change; no roles are added and no existing ability changes meaning.
- **Granting `maintenance.bypass` (Phase 3A, implemented).** Registered in the existing catalogue (`data/abilities.php`, group `maintenance`, labels «حالة الموقع» / «معاينة المتجر أثناء إغلاقه», English "Site Availability" / "Preview the storefront while closed") and assigned through the existing admin create/edit form (`role_user`, `ability = allow`). **Only a Super Admin** (`admins.super_admin`, `Admin::isSuperAdmin()`) may grant or revoke it: `AdminController::SUPER_ADMIN_ONLY_ABILITIES` removes it from a non-Super Admin's grantable scope even when that admin holds it, so the existing `resolveAbilities()` rejects adding it with 403 (store and update, before anything is written) and keeps it on the target when such an admin saves without it; the form does not list it for them. Every other ability keeps the existing rule (a non-Super Admin grants only what they hold). No account receives it automatically.

---

## 5. Route and middleware behaviour

### 5.1 Exempt paths (never gated, in every state)

| Pattern | Reason |
|---|---|
| `admin`, `admin/*` | Admin login, forgot/reset password, logout (SA-07). |
| `dashboard`, `dashboard/*` | Admin area, protected by `auth:admin` and abilities (SA-08); unauthenticated requests keep their existing redirect to the admin login. Includes the media library routes under `dashboard/media-library`. |
| `up` | Health check (outside the `web` group anyway; listed for clarity). |
| `change-locale/*` | DEC-SA-05: stores the chosen language in the session and redirects (to a validated same-site target or the previous page). It renders no content; the redirect target goes through the gate again, so nothing is exposed and no authorization is bypassed. |

Static files under `public/` (`/build/*`, `/assets/*`, `/storage/*`, `/images/*`, `favicon.ico`, `robots.txt`) are served directly by the web server and never reach Laravel, so they need no exemption and no database.

### 5.2 Gated route groups (when the state is not `open` and the actor may not bypass)

`/`, `browse`, `product`, `categories`, `vendors*` (the public store directory and store pages — gated for vendors too), `cart`, `contact` (GET and POST), `pages/*`, `newsletter` (POST), `shop/*`, `customer/*` (including customer login, registration and password reset), `vendor/*` **except** the `coming_soon` allowlist of §5.5 (vendor registration always gated; the whole `vendor/*` area gated in `maintenance`), `delivery-driver/*` (including driver login), `translate-json/*`, and the Fortify routes of the legacy `web` user (`login`, `register`, `forgot-password`, `reset-password`, `two-factor-challenge`, `passkeys/*`, `user/*`). There are no API routes, webhooks or payment callbacks today; any added later must be classified explicitly (exempt or gated) in `config/site-availability.php`.

### 5.3 Responses by method and format

| Request | `coming_soon` | `maintenance` |
|---|---|---|
| `GET` / `HEAD`, HTML | **200**, Coming Soon page | **503**, Maintenance page |
| `GET` / `HEAD`, JSON (`expectsJson()`) | **200**, `{"state":"coming_soon","message":"…"}` | **503**, `{"state":"maintenance","message":"…","retry_after":3600}` |
| `POST` / `PUT` / `PATCH` / `DELETE`, HTML | **503**, Coming Soon page | **503**, Maintenance page |
| `POST` / `PUT` / `PATCH` / `DELETE`, JSON | **503**, `{"state":"coming_soon","message":"…"}` | **503**, `{"state":"maintenance","message":"…","retry_after":3600}` |

Rationale for 503 on state-changing methods during `coming_soon` (approved, DEC-SA-02): a 200 to an AJAX "add to cart" or a form POST would be read as success by the client although nothing was saved. The gate returns before any route middleware or controller runs, so no blocked mutation executes. Requests on the vendor allowlist (§5.5) during `coming_soon`, and bypassing admins, are not blocked and behave normally (including their mutations).

Summary by state (DEC-SA-02):

| State | Blocked `GET`/`HEAD` | Blocked `POST`/`PUT`/`PATCH`/`DELETE` | Headers | Body |
|---|---|---|---|---|
| `open` | — (nothing blocked) | — | Application defaults | Application |
| `coming_soon` | 200 | 503 | `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`, `Pragma: no-cache`, `X-Robots-Tag: noindex, nofollow` | Coming Soon page, or JSON |
| `maintenance` | 503 | 503 | as above plus `Retry-After: 3600` | Maintenance page, or JSON |

Headers on every gated response:

| Header | `coming_soon` | `maintenance` |
|---|---|---|
| Status | 200 (safe methods) / 503 (others) | 503 |
| `Cache-Control` | `no-store, no-cache, must-revalidate, max-age=0` | same |
| `Pragma` | `no-cache` | same |
| `X-Robots-Tag` | `noindex, nofollow` | same |
| `Retry-After` | — (on 503 for state-changing methods: not sent) | `3600` (configurable) |
| `Content-Type` | `text/html; charset=UTF-8` or `application/json` | same |

The JSON `message` is a fixed Arabic sentence with an English fallback in the config file (no database, no `t()`).

### 5.4 Interaction with existing middleware and flows

| Concern | Behaviour |
|---|---|
| CSRF | `ValidateCsrfToken` runs before the gate. A gated POST with a valid token gets the state response; with an invalid or expired token it gets the existing 419. Nothing is executed in either case. |
| Authentication redirects | The gate runs before `auth:*`, so a guest asking for `/customer/dashboard` sees the state page, not a login redirect. `dashboard/*` keeps its redirect to `admin.login`. |
| Sessions | The gate never logs anyone out, never invalidates or regenerates the session, and never writes to it. Customers, vendors and drivers find their sessions intact when the state returns to `open` (subject to normal session lifetime). |
| `EnsureAccountIsActive` | Unchanged, but it now runs **after** the gate (§2.2). On gated paths the gate answers first and nobody is signed out; on paths the gate lets through (exempt paths, bypassing admins, the vendor allowlist) it runs as before. The gate checks the admin's active status itself for bypass. |
| Throttling and model binding | Run after the gate: a blocked request consumes no rate-limit attempt and triggers no route-model lookup (an unknown slug shows the state page, not a 404). |
| Locale | The gate runs before the route-level `setLocale`; the static pages are Arabic RTL (as the source design) and do not depend on the locale. |
| Admin preview | The bypassing request continues normally; the gate shares the current state so `layouts/storefront` can show the indicator (§7.4). Responses keep Laravel's default `Cache-Control: no-cache, private`. |
| Laravel `artisan down` | `PreventRequestsDuringMaintenance` runs globally before everything and has its own 503 page and bypass secret. It is independent and unchanged (SA-12). |
| Queue workers, scheduler, mail | Not HTTP; unaffected. Mails sent during a non-`open` state (for example a customer password-reset link) may point to gated pages until the state is `open` again. Vendor password-reset links work in `coming_soon` (the reset routes are on the allowlist) and are gated in `maintenance`. |
| Vendor dashboard links to public pages | The vendor dashboard does not link to storefront routes today; any public page a vendor opens (for example a store page under `vendors/*`) shows the state page, because vendors never bypass the storefront closure. |

### 5.5 Vendor allowlist during `coming_soon` (DEC-SA-01)

Matched by route name on the resolved route, only when the state is `coming_soon`. Every route keeps its own middleware; the allowlist only stops the gate from answering first. Nothing here applies in `maintenance` (the whole vendor area is gated) or in `open` (nothing is gated).

| Purpose | Route names (method, path) | Existing protection kept |
|---|---|---|
| Vendor area entry point | `vendor.entry` (GET `vendor`): a plain redirect to `vendor/dashboard`, with no access decision of its own; where the visitor finally lands (sign-in, approval status, dashboard, or signed out) is decided by the dashboard row's protection below | `EnsureAccountIsActive` on the request itself; then everything listed for `vendor.dashboard` |
| Sign in | `vendor.login` (GET `vendor/login`), `vendor.login.store` (POST `vendor/login`) | `setLocale`, `guest:vendor`, `throttle:account-login-ip`, vendor account-status check at login |
| Sign out | `vendor.logout` (POST `vendor/logout`) | `auth:vendor`, CSRF |
| Password recovery for existing vendors | `vendor.password.request` (GET `vendor/forgot-password`), `vendor.password.email` (POST `vendor/forgot-password`), `vendor.password.reset` (GET `vendor/reset-password/{token}`), `vendor.password.update` (POST `vendor/reset-password`) | `guest:vendor`, throttles `account-password-email` / `account-password-reset`, vendor password broker |
| Approval status (where `vendor.approved` sends unapproved stores) | `vendor.approval-status` (GET `vendor/approval-status`) | `auth:vendor` |
| Vendor dashboard | `vendor.dashboard` and every route named `vendor.dashboard.*` (overview, my-store, products and images, profile and media, orders and their actions, returns, reviews, commissions, disputes) | `auth:vendor`, `vendor.approved`, `EnsureAccountIsActive`, controller ownership and policy checks |

Never on the allowlist: `vendor.register` and `vendor.register.store` (GET/POST `vendor/register`), and any future `vendor/*` route until it is added deliberately. The allowlist lives in `config/site-availability.php` as exact route names plus the single prefix `vendor.dashboard.`; a test asserts the full set so a new vendor route is gated until reviewed.

Customers, delivery drivers, guests and the Fortify `web` user get nothing from this list beyond the vendor login and recovery pages themselves (which require valid vendor credentials or a valid vendor reset token to do anything).

### 5.6 Unknown URLs and unsupported methods (DEC-SA-09, D1–D4; implemented in Phase 2B)

**Why the gate cannot see them.** A request no route matches fails in the router (`AbstractRouteCollection::handleMatchedRoute()`) **before** any `web` middleware runs: no session, no CSRF, no authentication, no `EnsureSiteIsAvailable`. The router throws `NotFoundHttpException` (unknown URL) or `MethodNotAllowedHttpException` (known path, unsupported method). Before Phase 2B such requests got Laravel's 404/405 in every state. Correction to the earlier note: with no usable database the Blade error view fails (the catch-all `composer('*')` queries `languages`); with `APP_DEBUG=false` Laravel logs that and falls back to Symfony's generic error page **with the original status (404/405)**; only with `APP_DEBUG=true` does it become a 500.

**Mechanism (approved in SITE-AVAILABILITY-PHASE-02B-DISCOVERY): an exception render callback, not a fallback route.** `bootstrap/app.php` registers `$exceptions->render(fn (NotFoundHttpException|MethodNotAllowedHttpException $e, Request $request) => UnmatchedRequestResponse::make($request))`. `App\Support\SiteAvailability\UnmatchedRequestResponse::make()` returns `null` (Laravel's normal rendering) unless **all** of these hold, in this order:

1. `$request->route() === null` — the router matched nothing. Exceptions thrown inside a matched route (`abort(404)`, model binding, a 405 thrown by code) are never intercepted;
2. the path is not exempt from the gate (`site-availability.exempt`: `admin`, `admin/*`, `dashboard`, `dashboard/*`, `up`, `change-locale/*`);
3. the path is not a static resource (D1, below);
4. the effective state is not `open` (`SiteAvailability::current()`: forced state without a query, or the single settings read in `auto`; a read failure resolves to `open`, so the normal 404/405 is kept).

Otherwise it returns `SiteAvailabilityResponse::make($state, $request)` — the same page, JSON body and headers as a blocked request (§5.3) — with D3's status rule, prepared for the request (empty body for `HEAD`). Any internal error returns `null`, so the normal rendering is never broken. A fallback route was rejected: it changed `open` (unknown POST/DELETE 404 → 405, unknown OPTIONS 404 → 200 with `Allow`, a session cookie on every 404) and could not answer unknown mutations with 503 (the router answers 405 first).

**Approved decisions (SITE-AVAILABILITY-PHASE-02B-IMPLEMENTATION):**

| # | Decision |
|---|---|
| D1 | **Missing static resources keep the normal 404** in every state. Path rules in `config/site-availability.php` → `unmatched_static`: prefixes `build`, `assets`, `assets-dashboard`, `images`, `storage`, `vendor`; exact paths `favicon.ico`, `robots.txt`. Matched by **whole path segments** (`build` and `build/…`, never `builder` or `x/build/…`). Applies only to unmatched requests; it never exempts a route from the gate. The admin, dashboard, health-check and locale exemptions are unchanged. |
| D2 | **Unsupported methods on known paths** (routing-level `MethodNotAllowedHttpException`, no route matched): while closed, the availability response; while open, the original 405 with its `Allow` header. |
| D3 | **Only `GET` and `HEAD` may get 200** (during `coming_soon`). Unmatched `OPTIONS` (and every other method) gets **503**: `coming_soon` → 503 without `Retry-After`; `maintenance` → 503 with `Retry-After`; `open` → the existing 404. `no-store` and `noindex` always. `OPTIONS` on a **matched** path is unchanged (the router's automatic 200 with `Allow`, no exception involved). |
| D4 | **No admin bypass on unmatched requests.** They have no initialized session, so the bypass is not evaluated: a Super Admin or `maintenance.bypass` admin previewing a closed site sees the availability page (not a 404) on an unknown URL. The callback never starts a session or authenticates anyone. Accepted. |

Consequence of D1 to note: the `vendor` prefix is shared with the vendor area, so an **unknown** `/vendor/…` URL keeps the normal 404 while closed; matched vendor routes are still gated by `EnsureSiteIsAvailable` (and the DEC-SA-01 allowlist). Covered by a test.

Behaviour (verified by `SiteAvailabilityUnknownUrlTest`, §11.4):

| Request (no route matched) | `open` | `coming_soon` | `maintenance` |
|---|---|---|---|
| Unknown `GET` / `HEAD` | 404 | **200** page / empty body | **503** + `Retry-After` |
| Unknown `POST` / `PUT` / `PATCH` / `DELETE` | 404 | **503** | **503** + `Retry-After` |
| Unknown `OPTIONS` (D3) | 404 | **503** | **503** + `Retry-After` |
| Unknown, JSON expected | 404 JSON | 200 / 503 JSON `{state, message}` | 503 JSON with `retry_after` |
| Known path, unsupported method (D2) | 405 + `Allow` | 200 (GET) / 503 | 503 + `Retry-After` |
| Missing static resource (D1) | 404 | 404 | 404 |
| Unknown under `admin/*`, `dashboard/*`, `change-locale/*`; `up` with another method | 404 / 405 | 404 / 405 | 404 / 405 |

Every availability response here carries `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`, `Pragma: no-cache`, `X-Robots-Tag: noindex, nofollow`, no `Allow` header and **no cookie**; nothing runs, so there is no CSRF check (no 419) and nothing is written. Database: a forced state makes no query (works with no tables); `auto` makes only the settings read; a failed read keeps the normal 404/405.

---

## 6. Database and configuration design

### 6.1 Migration (one, additive) — `settings`

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
Migration `2026_10_20_000000_add_site_availability_to_settings_table` (implemented and verified, §11.3):

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `site_availability_mode` | `VARCHAR(20)` | No | `'open'` | Exactly `open`, `coming_soon` or `maintenance`, enforced by the named CHECK below. |
| `site_availability_changed_at` | `TIMESTAMP` | Yes | NULL | Set on every accepted change (Phase 3). |
| `site_availability_changed_by` | `BIGINT UNSIGNED` → `admins.id` | Yes | NULL | Foreign key `settings_site_availability_changed_by_foreign`, `ON DELETE SET NULL`; the UI shows «مشرف محذوف» when NULL after a change. |

**Exact values.** The table collation (`utf8mb4_unicode_ci`) is case-insensitive and ignores trailing spaces, so a plain `IN` would accept `OPEN` and `maintenance ` on MySQL (found in the first MySQL verification). The CHECK therefore compares bytes:

- MySQL/MariaDB: `CONSTRAINT settings_site_availability_mode_check CHECK (CAST(site_availability_mode AS BINARY) IN ('open', 'coming_soon', 'maintenance'))` — stored by MySQL 8.4.3 as ``(cast(`site_availability_mode` as char charset binary) in (_utf8mb4'open',_utf8mb4'coming_soon',_utf8mb4'maintenance'))``. It does not depend on a version-specific column collation; the column keeps the table collation.
- SQLite: `CHECK (site_availability_mode IN ('open', 'coming_soon', 'maintenance'))` — SQLite compares text exactly by default (a binary cast would turn the text into a number there).
- Invalid values are rejected, never normalized: `OPEN`, `Open`, `MAINTENANCE`, `maintenance ` (space or tab), ` maintenance`, `coming soon`, `coming-soon`, the empty string and unknown values are refused (MySQL error 3819) and the stored value stays unchanged. The service itself also matches exactly (`SiteAvailabilityState::tryFrom`) and never trims or lowercases a stored value.

**Atomic on MySQL/MariaDB.** `up()` adds the three columns, the foreign key and the named CHECK in **one `ALTER TABLE`** (it completes or changes nothing). On SQLite, adding the foreign key rebuilds the table, so the mode column and its column CHECK are added after it (a rebuild would drop a CHECK added before).

**Fully applied schema.** If all three columns exist, `up()` verifies the target state (types, nullability, the `open` default, the foreign key's name, target and `SET NULL` rule, and the CHECK) and issues no statement; any difference raises an error ending in "Nothing was changed."

**Partial schema.** If only some owned objects exist (any of the columns, the CHECK or the foreign key, but not the complete verified set), `up()` refuses with a message listing what it found ("partly migrated for site availability … Nothing was changed. Roll this migration back …") and changes nothing.

**Rollback.** `down()` removes only objects this migration owns and only those that exist: on MySQL/MariaDB one `ALTER TABLE` with `DROP CONSTRAINT` (the CHECK), `DROP FOREIGN KEY`, an orphaned `DROP INDEX` of the key if its column is already gone, and `DROP COLUMN` for each present column; on SQLite the mode column first, then the foreign key, then the remaining columns. On a clean schema it issues nothing, so it is safe to repeat; it also clears every partial state. Unrelated columns, constraints and data are never touched.

- Safe default `open` keeps an existing installation's behaviour unchanged when the migration runs. A pre-launch deployment uses the environment override (§10) so it is never public, even before the migration.
- The columns are **not** added to `Setting::$fillable`: the existing settings form (`UpdateSettingRequest`) cannot change them; only the dedicated controller writes them (`forceFill`). The model casts (`site_availability_mode` → the enum, `site_availability_changed_at` → datetime) are added in Phase 3 with the write path; Phase 1 reads the column through the query builder.
- Server requirements implied by the SQL: MySQL ≥ 8.0.19 (`DROP CONSTRAINT`; enforced CHECK from 8.0.16) or MariaDB ≥ 10.2.1. Only MySQL 8.4.3 has been tested; MariaDB has **not** been tested (§11.3, T-3).

### 6.2 Configuration (`config/site-availability.php`)

| Key | Env | Default | Purpose |
|---|---|---|---|
| `override` | `HIRFAH_SITE_AVAILABILITY` | `auto` | `auto`, `open`, `coming_soon`, `maintenance`. Any other value is treated as `auto` and logged once. |
| `retry_after` | `HIRFAH_SITE_AVAILABILITY_RETRY_AFTER` | `3600` | Seconds, maintenance only. |
| `exempt` | — | §5.1 list | Path patterns never gated. |
| `coming_soon_allowed_routes` | — | §5.5 list | Exact vendor route names, plus the `vendor.dashboard.` name prefix, allowed during `coming_soon` only (DEC-SA-01). |

The repository has no `.env.example`; both variables are documented in `config/site-availability.php` (and in the deployment notes, §10). The config file also holds the fixed JSON messages (§5.3).

### 6.3 Precedence

1. Environment override, when it is not `auto`.
2. `settings.site_availability_mode` of the singleton row (`singleton_key = 1`).
3. Default `open`.

`SiteAvailability::source()` reports which of the three decided, for the admin card and logs.

### 6.4 Reads and caching

- At most one read of the singleton row per request, memoized on the request attributes, and only for non-exempt paths; a forced override reads nothing. Storefront pages already read this row (`Setting::current()` via `StorefrontContext`), so the gate adds no new table to the request path (it does add one small query per storefront request).
- No application cache (the project's cache store is the database, which would not remove the query and would add an invalidation step).

### 6.5 Database failure behaviour

| Situation | Result | Why |
|---|---|---|
| `settings` table missing (fresh install) | Default `open` unless the override is set | Nothing to read yet; the override covers pre-launch (§10). |
| Column missing (code deployed before the migration) | Default `open` unless the override is set | Same; detected by catching the query exception, not by a per-request schema query. With `auto`, a warning is logged on every storefront request until the migration runs (a forced override reads nothing and logs nothing). |
| Row missing | Default `open` | — |
| Unknown stored value | Default `open`, warning logged | Cannot happen through the controller and the CHECK constraint; defensive only. |
| Connection or query error | Default `open`, warning logged (no request data) | Fail-open is harmless here: the storefront itself cannot render without the database, so nothing extra is exposed. A deployment that must stay closed uses the override, which needs no database. |

### 6.6 Config caching and emergency recovery (cPanel)

- `env()` is read only in `config/site-availability.php`. If the configuration is cached (`php artisan config:cache`, `bootstrap/cache/config.php`), an `.env` edit has no effect until the cache is cleared.
- Recovery without a terminal: in cPanel File Manager, set `HIRFAH_SITE_AVAILABILITY=open` (or the needed state) in `.env`, then delete `bootstrap/cache/config.php` if it exists (equivalent to `php artisan config:clear`). With a terminal: edit `.env`, then `php artisan config:clear` (and `config:cache` again if the deployment uses it).
- Recovery from a lost Super Admin session: the admin login and password reset are never gated (SA-07); a Super Admin always bypasses (SA-04).
- Configuration-cache implications (DEC-SA-08): with a cached configuration, the override in force is the one cached at the last `config:cache`, whatever `.env` says now. Every override change is therefore "edit `.env`, then clear the cache" (and re-cache if the deployment caches). The admin card (§7.1) shows the override actually in force, read from the loaded configuration, so a stale cache is visible from the dashboard.
- Emergency recovery summary: (1) site wrongly closed or wrongly open → set `HIRFAH_SITE_AVAILABILITY` to the wanted state in `.env`, clear the config cache; (2) Laravel page broken during the first deployment → restore the previous static Coming Soon page as described in §10 step 9; (3) admin cannot sign in → admin password reset (never gated), or a Super Admin account.

---

## 7. Admin UI behaviour

### 7.1 Placement and visibility

- One card, `Site availability` / «حالة الموقع», inside the existing `resources/views/dashboard/settings/index.blade.php`, as an included partial. No new menu item, page or module.
- Rendered only for a Super Admin. Admins with `settings.view` but not Super Admin see the settings page as today, without the card. The update route returns 403 to them regardless.
- Contents:
  - Current effective state as a badge, with its source: "set in the dashboard", or "forced by the server environment (`HIRFAH_SITE_AVAILABILITY=…`)". When an override is active (DEC-SA-08) the card shows a prominent warning at the top: «حالة الموقع مفروضة من إعدادات الخادم (HIRFAH_SITE_AVAILABILITY=…) وتتقدّم على الإعداد المحفوظ هنا. التغييرات تُحفظ لكنها لا تُطبَّق حتى تُعاد القيمة إلى auto.» The form stays usable so the stored state can be prepared before the override is removed.
  - Stored state, always shown, even when it matches the effective state; an environment override can make the two differ.
  - Last change: date/time (`Y-m-d H:i`, LTR) and the administrator's name, or "—" if never changed, or «مشرف محذوف» if the admin was deleted. SA-FIX-1: the time is stored in UTC (the application timezone) and displayed in Palestine time (`Asia/Hebron`, daylight saving included) followed by the translated label «بتوقيت فلسطين» / "Palestine time"; the conversion works on a copy, so the stored value is never changed. Without a change time the card shows "—" alone, with no timezone label. This is a display conversion in the card only, not a change of Laravel's timezone.
  - A radio group with the three mutually exclusive states, each with a one-line description; the current stored state is pre-selected. SA-FIX-2: the one exception is the request right after a save rejected for a missing confirmation, where the state the admin submitted stays selected (§7.2).

### 7.2 Server-validated confirmation

- A required checkbox «أؤكد تغيير حالة الموقع» (`confirm=1`), validated on the server with the same pattern as the contact-message deletion: without it nothing is written and the admin returns to the settings page with an error message (session flash, not a validation bag, to avoid the layout's generic error list). SA-FIX-2: on this path only, the submitted state — already validated against the enum — is flashed as old input (`state` alone; never `confirm`, the CSRF token or anything else), so on the next request it stays selected while the saved and effective states are unchanged and the confirmation checkbox is not ticked again. The card accepts an old `state` only if it is one of the three enum values; any other value is ignored. Other rejections (invalid state, pending migration) keep nothing, and a successful save or any later visit returns to the saved state being pre-selected.
- The submitted state must be one of the enum values (otherwise 422-style error message, nothing written).
- Same state as stored → informational message, nothing written.
- `PATCH dashboard/settings/site-availability`, CSRF protected, Super Admin checked before reading the settings row.

### 7.3 Warnings

- Selecting **Maintenance** shows, next to the option and in the card hint: «سيظهر للزوار والعملاء والبائعين ومندوبي التوصيل أن الموقع تحت الصيانة (رمز 503)، ولن يستطيعوا تسجيل الدخول أو إتمام أي عملية حتى تعيد فتح الموقع.» The warning is static text (no JavaScript required).
- Selecting **Open** while the stored state is `coming_soon` or `maintenance` shows the launch warning: «سيصبح المتجر متاحًا للجميع فور الحفظ.» (SA-FIX-4B: reopening from `maintenance` included.)
- **Coming Soon** (SA-FIX-4B): while the stored state is not `coming_soon`, a static warning beneath the option, in the launch warning's dark amber: «عند حفظ وضع «قريبًا»، سيتوقف وصول العملاء إلى المتجر، ولن يتمكنوا من تسجيل الدخول أو إتمام الطلبات حتى إعادة فتحه. كما سيتعذر على مندوبي التوصيل الوصول إلى لوحاتهم.»
- The warnings follow the **stored** state, which is what a save changes; with an environment override in force the effective state may differ (§7.1).
- Option descriptions (SA-FIX-4B): Coming Soon «تظهر صفحة «قريبًا» للزوار والعملاء ومندوبي التوصيل، بينما يستطيع البائعون المعتمدون الوصول إلى لوحاتهم، وتبقى لوحة الإدارة متاحة.»; Maintenance «تظهر صفحة الصيانة للزوار والعملاء والبائعين ومندوبي التوصيل (رمز 503)، بينما تبقى لوحة الإدارة متاحة للمسؤولين المصرّح لهم.» `AdminSiteAvailabilityTranslationSeeder` replaces the first shipped descriptions only while a row still holds them exactly (guarded corrections; edited values are kept).

### 7.3a Implementation (Phase 3B, implemented and tested locally)

- **Files.** Card: `resources/views/dashboard/settings/partials/site-availability.blade.php`, included at the top of `dashboard/settings/index.blade.php` only when `SettingController@index` passes `$siteAvailability` — which it does **for a Super Admin only** (`isSuperAdmin()`); for anyone else no card data, schema check or availability read happens. Data, schema check and write: `App\Support\SiteAvailability\SiteAvailabilitySettings` (`schemaReady()`, `card()`, `store()`). Save: `PATCH dashboard/settings/site-availability` → `SiteAvailabilityController@update` (`dashboard.setting.site-availability.update`). Texts: `t()` without auto-creating rows, seeded by `AdminSiteAvailabilityTranslationSeeder` (31 keys, Arabic and English: 62 translation rows; the approved Arabic texts above used as written; run in the local development environment, **not run in production**). `Setting` gains only a `datetime` cast for `site_availability_changed_at`; the three columns stay out of `$fillable`, and the general settings form cannot write them (tested).
- **Card.** Effective state (dashboard badge, see U1 below: open olive outline, coming soon copper outline, maintenance solid red) with its source — «محفوظة من لوحة التحكم», «مفروضة من إعدادات الخادم» or «الافتراضية (لم تُحفظ حالة بعد)» (SA-FIX-4B, display only: also when the saved state is `open` with no change date — the migration's default, since only `SiteAvailabilitySettings::store()` writes the state and always with its date; any other state without a date keeps «محفوظة من لوحة التحكم»; the resolved source is unchanged); saved state; last change (`Y-m-d H:i` LTR in Palestine time with «بتوقيت فلسطين», SA-FIX-1 — admin name, «مشرف محذوف» when that admin no longer exists, «—» if never changed); three radios with one-line descriptions, the saved state pre-selected (except right after an unconfirmed save, SA-FIX-2, §7.2); the static Maintenance warning; the Coming Soon warning while the saved state is not `coming_soon`; the launch warning beside Open when the saved state is `coming_soon` or `maintenance` (§7.3); the required confirmation checkbox; outcome messages in the card (session flash `site_availability`, not the validation bag).
- **Outcome message roles (SA-FIX-3).** The message element takes its ARIA role from the flash level, so errors and warnings are announced assertively and confirmations politely. A level outside the four below falls back to `info` and `status`; the role is one of two fixed values and never comes from input. The visual classes (`alert alert-{level}`) and the message texts are unchanged. This is an HTML accessibility improvement verified on the rendered markup; it has not been tested with a real screen reader.

  | Flash level | ARIA role |
  |---|---|
  | `danger` | `alert` |
  | `warning` | `alert` |
  | `success` | `status` |
  | `info` | `status` |
- **Save, in this order.** (1) `abort_unless(isSuperAdmin(), 403)` before any read — no `settings` query and no schema check for anyone else (tested with the query log); pending or blocked admins are signed out earlier by `EnsureAccountIsActive`. (2) Schema not ready → error message, nothing written. (3) `state` must be a string equal to `open`, `coming_soon` or `maintenance` (`SiteAvailabilityState::tryFrom`; case variants, arrays, empty and unknown values rejected). (4) `confirm` must be truthy. (5) `store()`: the singleton row is ensured, then one transaction reads `site_availability_mode` with `lockForUpdate()`; the same state returns «no change» (date and author untouched); otherwise mode, `changed_at` and `changed_by` are written with the query builder. Database errors are not caught: a failed write is a 500 with no success message and nothing changed (tested with a failing trigger). After a save the request's memoized state is dropped.
- **Pending migration (readiness, corrected in SITE-AVAILABILITY-PHASE-03B-CORRECTIONS).** `schemaReady()` requires **both**: (1) the exact migration `2026_10_20_000000_add_site_availability_to_settings_table` recorded in Laravel's migrations table (`config('database.migrations.table')`), and (2) the three columns. Laravel records the migration only after `up()` completes, and `up()` verifies the whole schema (types, default, CHECK, foreign key) before returning, so columns alone — a partial or manual state that `migrate` would refuse — never count. A missing migrations table, a missing or differently named record, or missing columns all mean «not ready». Order: migrations-table existence, record, columns (three schema/database reads per page or save, once each; database errors are not caught). When not ready the card shows a red notice («لم يُطبَّق ترحيل قاعدة البيانات الخاص بحالة الموقع بعد…»), no saved state, a disabled `<fieldset>` and **no Save button**; a direct `PATCH` is refused with a message and writes nothing. No migration runs and the schema is never changed by the application.
- **UI corrections (PHASE-03B-UI-REVIEW → CORRECTIONS).** U1: the effective-state badge uses the dashboard's existing `admin-products-badge` inside its `.admin-products` scope wrapper (which has no style of its own) — open `is-active` (white, olive border and text), coming soon `is-low` (white, copper border, `#8a4520` text), maintenance `is-suspended` (solid red, white text); the non-existent `bg-light-*` classes are gone. U2: no Save button while not ready (Bootstrap's blue disabled-button colour no longer appears). U3: each radio and the confirmation checkbox sit with their label in one `d-flex gap-2` row that follows the page direction (no `.form-check` float), only inside the card. U4: the launch warning uses `#8a4520` instead of `text-warning`. No CSS file changed.
- **Environment override.** The card shows the forced state as the effective state (source «مفروضة من إعدادات الخادم»), the saved state separately, and the §7.1 warning with the forced value. The form stays usable when the schema exists; a save is stored and answered «حُفظت الحالة، لكن إعدادات الخادم ما زالت تحدد حالة الموقع حتى تُعاد إلى auto.»; the effective state does not change until the override is `auto` (tested through the gate).

### 7.4 Status indicator

- Storefront: a small, non-dismissable bar at the top of `layouts/storefront` (partial `storefront/partials/site-availability-indicator.blade.php`), rendered only when the gate let an admin through in a non-`open` state: «الموقع في وضع «قريبًا» — تشاهده كمشرف» or «الموقع في وضع الصيانة — تشاهده كمشرف». Arabic text in the view, like the other storefront views.
- Dashboard (DEC-SA-06): a compact badge in the admin header (`layouts/partials/dashboard/header.blade.php`) for every signed-in admin, showing the effective state («مفتوح» / «قريبًا» / «صيانة»), visually prominent when not `open`, with "(server override)" when an environment override is in force. For a Super Admin it links to the settings card. The badge reads the state through `SiteAvailability` (no extra query beyond the memoized read) and its texts are read without auto-creating translation rows.

### 7.4a Implementation (Phase 3C, implemented and tested locally)

- **Header badge** (`layouts/partials/dashboard/header.blade.php`, first item of the header's end group, before the language switcher). Every signed-in admin sees the effective state with the dashboard's existing `admin-products-badge` (same tones as the card: open olive outline, coming soon copper outline, maintenance solid red), texts «مفتوح» / «قريبًا» / «صيانة» (the card's keys). When `HIRFAH_SITE_AVAILABILITY` forces the state: a lock icon, «· مفروضة من الخادم» from the `md` breakpoint up (a visually hidden equivalent below it), and the title/label «حالة الموقع: … (مفروضة من الخادم)». **Super Admin**: the badge is a link to `dashboard/settings#site-availability`. **Every other admin**: a plain `<span>` — no link, no settings route, no card. One new key, `dashboard.Site_Availability_Badge_Override` (`AdminSiteAvailabilityTranslationSeeder`, now 29 keys; not run).
- **Queries.** The badge uses the request-memoized `SiteAvailability::current()`: one read of `site_availability_mode` per dashboard page in `auto`, none with a forced state. On the settings page the card shares that read and adds only its own read of the saved row (2 in total, as before Phase 3C); for non-Super Admins there is no card read, migration-record read or schema check. Without the migration or with an unreadable `settings` table the badge shows «مفتوح» (the gate's fail-open result) and the dashboard keeps working; as for the gate, each such request logs the existing warning.
- **Storefront preview bar** (`storefront/partials/site-availability-indicator.blade.php`, included as the first element of `<body>` in `layouts/storefront.blade.php`). Rendered only when the request attribute `EnsureSiteIsAvailable::PREVIEW_ATTRIBUTE` is `coming_soon` or `maintenance` — the gate sets it only when it lets an active Super Admin or `maintenance.bypass` admin through a closed state; customers, vendors on the DEC-SA-01 allowlist, admins without bypass, blocked admins and an open site never get it. No query of its own. Text: «الموقع في وضع «قريبًا» ومغلق أمام الزوار — تشاهده لأنك مشرف في وضع المعاينة.» / «الموقع في وضع الصيانة ومغلق أمام الزوار — تشاهده لأنك مشرف في وضع المعاينة.» (the §7.4 wording, made explicit about the preview). `role="note"`, `dir="rtl"`, `lang="ar"`; in the page flow (not fixed or sticky), so it pushes the page down instead of covering the navigation, buttons or forms. Classes already present in the storefront build (`bg-ink`, `text-white`, `text-sm`, …).
- **Scope.** Only pages using `layouts/storefront` show the bar; other closed pages an admin previews (for example account areas with their own layout) do not. The gate, `SiteAvailability` and `SiteAvailabilitySettings` are unchanged; ordering, checkout and other workflows are untouched (risk R-1 still applies to an admin also signed in as a customer).

---

## 8. Coming Soon and Maintenance page specifications

### 8.1 Source assets (user-provided; not in the repository)

DEC-SA-07: the user supplied the original Coming Soon `index.html` and `hirfah-logo.jpeg`; they are now **present at the destinations below** (untracked, unmodified; B-1 resolved). The source uses Tajawal only (weights 400, 500, 700, 800, 900), the Tailwind CDN with an inline `tailwind.config`, the logo at the relative path `assets/hirfah-logo.jpeg`, and no form. (`.codex_tmp_front/hirfah-main/index.html` is the storefront homepage prototype, not the Coming Soon page; `public/images/brand/hirfah-logo.png` is the admin brand logo and is **not** a substitute.) Destinations:

| File | Destination | Tracked |
|---|---|---|
| Original page, unmodified (design reference) | `resources/site-availability/source/index.html` | Yes |
| Original logo, unmodified | `public/assets/site-availability/hirfah-logo.jpeg` | Yes |
| Any other image, icon or font file the original page references locally | `public/assets/site-availability/` (same file names) | Yes |

No replacement asset will be created or substituted.

### 8.2 Production templates

| File | Content |
|---|---|
| `resources/site-availability/coming-soon.html` | The original page with only these technical changes: the Tailwind CDN `<script>` and its inline `tailwind.config` removed; a `{{STYLESHEET}}` placeholder added after the font links (replaced by the inline `<style>` with the tracked CSS, §8.3); the Google Fonts `preconnect` and Tajawal stylesheet links **kept unchanged** (§8.4); `class="font-tajawal"` and `data-site-availability="coming_soon"` on `<body>`; the logo `src` via `{{LOGO_URL}}` (→ `/assets/site-availability/hirfah-logo.jpeg`); `<meta name="robots" content="noindex, nofollow">` added. The source has no form or script besides the CDN (DEC-SA-03). Markup, classes, Arabic text, logo, colours, RTL layout and responsive behaviour otherwise unchanged. |
| `resources/site-availability/maintenance.html` | The same template with the minimal content differences below. |

Maintenance page — minimal differences from Coming Soon, text only (markup, every `class` attribute, logo, layout, RTL, colours and font identical). Copy **approved in SITE-AVAILABILITY-PHASE-02A-FINAL**:

| Element | Maintenance copy |
|---|---|
| `<title>` | «الموقع تحت الصيانة \| حِرفة» |
| `<meta name="description">` | «متجر حِرفة يخضع حاليًا لصيانة مؤقتة. سنعود لخدمتكم قريبًا.» |
| Badge | «نعمل على تحسين تجربتكم» |
| Heading | «الموقع تحت الصيانة» — the coloured second line of the `h1`; the first line, the brand «متجر حِرفة», is kept as in the original layout |
| Main description | «نجري حاليًا بعض أعمال الصيانة والتحسين على متجر حِرفة. سنعود إليكم قريبًا. شكرًا لتفهمكم.» |
| `<body>` | `data-site-availability="maintenance"` |

- No launch date or countdown exists in the source, so nothing launch-specific is removed. Header, feature list and footer texts are shared with Coming Soon.
- `<meta name="robots" content="noindex, nofollow">` on both pages (in addition to the header).
- The built-in fallback page (below) uses the same approved main description for maintenance (aligned in SITE-AVAILABILITY-PHASE-02A-CSS-HARDENING).

The two placeholders are replaced with `str_replace` at response time (the logo URL HTML-escaped); the result is never passed through Blade. If the template **or the CSS** is missing, unreadable or empty (or the CSS contains `</style`), the failure is **logged** (`error`: "Site availability page: the template|stylesheet could not be read; serving the built-in fallback page.", with the state) and a built-in plain page is served instead: `lang="ar" dir="rtl"`, static Arabic heading and text, inline `style` attributes with `sans-serif`, no utility classes, no external resource, no form, `noindex` — readable without any stylesheet, with the same status and headers. A logging failure never prevents the response.

### 8.3 Local CSS replacing the Tailwind CDN

- `resources/css/site-availability.css`: `@import "tailwindcss" source(none);` with `@source` for the two production templates only (not the CDN-based source), and `@theme { --font-tajawal: "Tajawal", sans-serif; }` reproducing the original inline `tailwind.config` (its only customization: `fontFamily.tajawal: ['Tajawal', 'sans-serif']`; colours are arbitrary values in the markup). The file contains no `@font-face` and no `url()`.
- **Tailwind v3 → v4 fidelity fix**: the original runs Tailwind v3 (CDN), where a responsive `text-*` utility also sets its line-height and overrides an explicit `leading-*` on the same element; in v4 the explicit leading wins. The two affected elements (the `h1` at `sm`/`lg`, the lead paragraph at `sm`) get v3's line-height back through three small unlayered rules in the same file.
- **Delivery (SITE-AVAILABILITY-PHASE-02A-CSS-HARDENING): inline, from a tracked artifact.** The compiled, minified CSS is committed as `resources/site-availability/site-availability.min.css` (13,082 bytes) and inserted into the page as `<style>…</style>` in place of `{{STYLESHEET}}` — one file read, like the templates. The pages do **not** use Vite at response time: no `public/build`, no `manifest.json`, no `public/hot`, no second HTTP request, no configuration and no database. `resources/css/site-availability.css` is **not** an input of `vite.config.js` (which is back to its committed content) and is not part of `npm run build`.
- **Regeneration** (only when the classes used by the templates or `resources/css/site-availability.css` change), from the project root:

  ```
  npx vite build --config vite.site-availability.config.js
  ```

  Source: `resources/css/site-availability.css` (scanning only `coming-soon.html` and `maintenance.html`). Config: `vite.site-availability.config.js` (`@tailwindcss/vite` only — no Laravel plugin, no hot file, no manifest, no hash, nothing written to `public/`). Output: `resources/site-availability/site-availability.min.css`, overwritten in place. Uses only the installed dev dependencies (`vite`, `@tailwindcss/vite`, `tailwindcss` 4.3.1); no new package. The generated file was verified **byte-identical** to the Vite build used for the Phase 2A fidelity measurements. After regenerating, run `php artisan test --filter=SiteAvailabilityPagesTest`: its drift guard fails if any template class is missing from the artifact.
- Visual fidelity is verified side by side against the original (CDN) at 390, 768 and 1280 px (§11.2, §11.4).

#### 8.3.1 CSS delivery reliability — T-4 resolved

Assessment (SITE-AVAILABILITY-PHASE-02A-FINAL) before the change, and the result after it:

| Condition | Before (Vite link) | After (inline artifact) |
|---|---|---|
| Database unavailable | Styled | Styled (no database read; with the database down and no override the gate fails open, §6.5, so the page appears only with a forced state) |
| Configuration cached | Styled | Styled (no configuration involved) |
| `public/build/manifest.json` missing | **Unstyled**, nothing logged | Styled — tested with an empty public directory |
| Compiled CSS in `public/build` missing or stale | **Unstyled** (404 on the `<link>`) | Styled — `public/build` is not used |
| `public/hot` present | **Unstyled** (dev-server URL) | Styled — tested with a hot file present; no dev-server URL in the page |
| CSS artifact itself missing, unreadable or empty | — | **Logged** (`error`) and the readable built-in fallback page is served (§8.2); never an unstyled converted page |

The only remaining condition for complete styling is that `resources/site-availability/` is deployed with the PHP code — the same condition under which the templates themselves exist. Cost: about 13 kB inline per blocked response (already `no-store`).

### 8.4 Fonts and external dependencies (DEC-SA-04, revised in SITE-AVAILABILITY-PHASE-02A)

- Tajawal is used **exactly as the original references it**: the same `preconnect` links to `fonts.googleapis.com` / `fonts.gstatic.com` and the same stylesheet `https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap`, kept unchanged in both templates. The font stack is `"Tajawal", sans-serif`, as in the original.
- Tajawal is **not** downloaded, bundled or self-hosted; no `.woff2` file and no `OFL.txt` are added to the repository.
- **Google Fonts is an approved optional external dependency** — the only external resource the pages reference. No Tailwind CDN, no third-party script, no external image.
- The pages remain **fully functional when Google Fonts is unavailable** (blocked network, outage, privacy blocker): the text renders in the `sans-serif` fallback, layout and RTL direction are unchanged and nothing overflows (verified with Google Fonts blocked at 390 and 1280 px, §11.4). Everything else — HTML with the inline CSS, logo — is served by the site itself without the database.

---

## 9. Security and failure scenarios

| # | Scenario | Handling |
|---|---|---|
| S-1 | Non-Super Admin tries to change the state | 403 before any read; card not rendered. |
| S-2 | Forged or replayed state change | CSRF, `PATCH` only, Super Admin, enum validation, required confirmation. |
| S-3 | Mass assignment through the general settings form | Columns not fillable; only `forceFill` in the dedicated controller. |
| S-4 | Admin locked out after enabling a state | Admin login and password reset never gated; Super Admin always bypasses; `.env` override plus config-cache removal (§6.6). |
| S-5 | Pending or blocked admin with `maintenance.bypass` (or a Super Admin) | The gate runs before `EnsureAccountIsActive`, so `canBypass()` checks `status === 'active'` itself: no bypass (tested). |
| S-6 | Customer/vendor/driver session during a non-`open` state | Kept intact; every request answered by the state page; nothing executed. |
| S-7 | Shared cache or CDN caching the state page as the real page, or vice versa | `Cache-Control: no-store`, `Pragma: no-cache`; bypass responses keep `private`. |
| S-8 | Search engines indexing Coming Soon or Maintenance | `X-Robots-Tag: noindex, nofollow` and the `robots` meta; 503 + `Retry-After` for maintenance. |
| S-9 | Database down or unmigrated | Default `open` (harmless, §6.5); override for deployments that must stay closed. |
| S-10 | Invalid override value in `.env` | Treated as `auto`, logged once. |
| S-11 | Config cached with an old override | Documented recovery (§6.6). |
| S-12 | Future webhook or payment callback | Must be added to the exempt list deliberately; otherwise gated (fail-safe). |
| S-13 | A new `vendor/*` route added later (for example a public vendor page) | Not on the allowlist unless named deliberately; gated by default; the allowlist test (§11.1) fails until the route is reviewed. |
| S-14 | Vendor registration during `coming_soon` | `vendor.register` / `vendor.register.store` never on the allowlist → state page / 503. |
| S-15 | Vendor credential stuffing through the allowlisted login during `coming_soon` | Same protection as today: `throttle:account-login-ip`, the per-account login limiter, and the account-status check at login. |
| S-16 | A customer or driver uses the vendor login page | Only valid vendor credentials authenticate, and only for the `vendor` guard; customer and driver areas stay gated. |
| S-17 | Vendor opens public store pages while previewing | Gated (state page); vendors never bypass the storefront (DEC-SA-01). |
| S-18 | Migration partly applied (a failed or manual change) | `up()` refuses the partial schema without changing anything; `down()` removes only the owned objects that exist, then `up()` applies cleanly. On MySQL/MariaDB `up()` is a single `ALTER TABLE`, so it does not create a partial state itself (§6.1, verified). |
| S-19 | Database-backed sessions before the migrations | `StartSession` runs before the gate, so `SESSION_DRIVER=database` would fail every request (Coming Soon page included) before the `sessions` table exists. First deployment uses `SESSION_DRIVER=file` (§10). |
| S-20 | Unknown URL while closed | Phase 2B: availability page through the exception handler, no session, no database dependency beyond the state read (§5.6, DEC-SA-09); missing static resources and exempt paths keep the normal 404. |
| S-21 | Hand-written invalid state in the database (`OPEN`, `maintenance `) | Rejected by the byte-exact CHECK (MySQL error 3819); never normalized (§6.1). |

### 9.1 Risk: admins testing transactions in a non-`open` state

| # | Risk | Mitigation |
|---|---|---|
| R-1 | An admin who bypasses and is also signed in as a customer can place **real** orders, reserve **real** stock, submit contact messages and newsletter subscriptions. | Use dedicated test customer accounts; cancel test orders before reopening; never test with real vendors' live products during `maintenance`. Documented in the deploy checklist; no technical block in this phase. |
| R-2 | Vendors and drivers cannot act during `coming_soon`/`maintenance`, so orders created in that period wait until `open`. | Announce maintenance windows; keep them short. |
| R-3 | Pre-launch vendor onboarding. | Resolved by DEC-SA-01: existing approved vendors prepare stores and products during `coming_soon`; new vendors cannot register until `open` (accounts can still be created by an admin through `dashboard/vendors/create`). |
| R-5 | Vendors act on real data during `coming_soon` (products, images, profile, and any orders that existed before closing). | Intended (DEC-SA-01); all existing ownership and approval checks apply. Changes become public at launch. |
| R-4 | Emails sent during a non-`open` state may link to gated pages. | Expected; links work again after reopening. |
| R-6 | Production database engine differs from the tested MySQL 8.4.3 (MariaDB, or MySQL older than 8.0.19). | T-3: confirm the engine and version before migrating; ideally repeat the isolated MySQL migration test on a matching server. Local verification is not proof of production compatibility. |

---

## 10. Initial cPanel deployment sequence (pre-launch site, DEC-SA-08)

Starting point: the domain currently serves the existing static Coming Soon page from the cPanel document root (`public_html`). It stays the public page until Laravel's replacement is verified.

1. **Keep the live page untouched.** Copy the current `public_html` contents (static `index.html` and its assets) to a backup folder outside the web root (for example `~/backup/coming-soon-static/`) for rollback.
2. **Prepare the application outside the web root** (for example `~/hirfah/`): upload the code, `vendor/` (or run `composer install --no-dev` if a terminal exists), and the locally built assets (`public/build/`, needed by the storefront and dashboards, **not** by the availability pages, whose CSS is inlined from `resources/site-availability/`, §8.3) plus `public/assets/site-availability/` (logo).
3. **Environment first.** Create `~/hirfah/.env` with:
   - `APP_ENV=production`, `APP_DEBUG=false`, a valid **`APP_KEY`** (cookie encryption and sessions run before the gate; a missing key fails every request), the database credentials;
   - **`HIRFAH_SITE_AVAILABILITY=coming_soon`**: with this value the gate reads nothing from the database (verified: zero queries), so Coming Soon is served with an empty, unmigrated or unreachable database (§6.5);
   - **`SESSION_DRIVER=file`** until the migrations are complete (the session middleware runs before the gate; `database` sessions would fail every request before the `sessions` table exists — S-19), with **`storage/framework/sessions` writable** (as `storage/logs`). Switch to another driver only after the migrations, if wanted;
   - do not run `config:cache` yet.
4. **Verify Laravel on a non-public address.** Point a temporary subdomain (for example `preview.<domain>`), protected with cPanel *Directory Privacy* (password), at `~/hirfah/public`. Check: `/` shows the Coming Soon page (HTTP 200, `noindex`, `no-store`); a POST returns 503; `/admin/login` works; `/vendor/login` works and `/vendor/register` shows the page; `/up` answers; the page makes no external request other than the approved Google Fonts stylesheet and font files (§8.4); the design matches the original (§11.2).
5. **Database.** First confirm the production database engine and version (T-3): the site-availability migration needs MySQL ≥ 8.0.19 or MariaDB ≥ 10.2.1, and only MySQL 8.4.3 has been tested — local verification is not proof of production compatibility. Then create the database, run the migrations, then the approved seeders (translations, abilities), and create the Super Admin. Each step is separately approved; back up first if the database already holds data. If the site-availability migration is ever interrupted, run its rollback (removes only what exists) and migrate again (§6.1).
6. **Verify the admin side on the preview address.** Sign in as Super Admin; the settings card shows the override warning (§7.1) and the header badge shows «قريبًا (server override)». Set the stored state to `coming_soon` with confirmation, so the database agrees with the override.
7. **Cutover.** Either point the main domain's document root at `~/hirfah/public`, or replace the `public_html` contents with Laravel's `public/` contents (adjusting `index.php` paths to `~/hirfah`). **Remove or rename the old `index.html`** from the document root: cPanel's `DirectoryIndex` usually prefers `index.html` over `index.php`, so a leftover file would keep serving the old page for `/` while other paths reach Laravel.
8. **Verify publicly** from a private window: `/` (Laravel Coming Soon, headers), a storefront path such as `/product`, `/admin/login`, `/vendor/login`, `/up`. Optionally run `php artisan config:cache` now; from then on every `.env` change — `HIRFAH_SITE_AVAILABILITY`, `SESSION_DRIVER` included — needs `config:clear` (or deleting `bootstrap/cache/config.php`) to take effect, because a cached configuration keeps the values read at caching time (§6.6).
9. **Rollback (any time before launch).** Restore the previous document root, or put the backed-up static `index.html` and assets back into `public_html`; the old page is live again immediately and Laravel can be fixed on the preview address.
10. **Stay closed.** Keep `HIRFAH_SITE_AVAILABILITY=coming_soon` until launch day; vendors prepare their stores (DEC-SA-01). Remove the preview subdomain.
11. **Launch (manual, never automatic).** Super Admin confirms the stored state is ready; set `.env` to `HIRFAH_SITE_AVAILABILITY=auto` and clear the config cache — the site stays closed because the stored state is `coming_soon`. Then the Super Admin selects `open` in the settings card and confirms. Verify the storefront from a private window.

Recovery at any point: §6.6. No step switches the site to `open` by itself.

---

## 11. Test plan

### 11.1 Automated (PHPUnit; in-memory SQLite, plus an opt-in isolated MySQL test)

- **Service**: precedence (override beats database beats default); every override value including invalid; missing table, missing column, missing row, unknown value, query exception → default; `source()` values; memoization per request.
- **Gate matrix**: for each state × actor (guest, customer, vendor, driver, admin without/with `maintenance.bypass`, Super Admin, deactivated admin) × route group (§5.1/§5.2 samples: `/`, `product`, `vendors`, `contact` GET/POST, `newsletter`, `customer/login`, `vendor/login`, `vendor/register`, `vendor/dashboard`, `delivery-driver/login`, Fortify `login`, `admin/login`, `admin/forgot-password`, `dashboard`, `change-locale`, `/up`).
- **Vendor allowlist (DEC-SA-01)**:
  - in `coming_soon`: an active approved vendor reaches `vendor.dashboard` and a `vendor.dashboard.*` GET and mutation (for example a product update), with ownership still enforced (another vendor's product → 403/404 as today); an unapproved vendor is sent to `vendor.approval-status`; an inactive vendor is signed out; a guest on a dashboard route is redirected to `vendor.login`; `vendor` (`vendor.entry`) redirects to `vendor/dashboard` instead of returning the state page; `vendor/login`, logout and the four password-recovery routes work; `vendor/register` GET and POST return the state responses (200 / 503);
  - the same vendor on `/`, `product` and `vendors/{slug}` gets the state page (no storefront bypass);
  - in `maintenance`: every vendor route, login included, returns 503;
  - customers and drivers: their areas gated in both closed states, including their login pages;
  - the configured allowlist equals exactly the documented set, and every current `vendor.*` route name is either on it or deliberately gated (a new vendor route fails the test until classified).
- **Locale (DEC-SA-05)**: `change-locale/{locale}` in a closed state sets the session language and redirects; following the redirect to a storefront page returns the state page.
- **Responses**: exact status per method and format (§5.3); every header (§5.3); JSON body shape; the response makes **no database query** (assert with the query log) and creates no translation row; no `Set-Cookie` session change beyond the framework's; HEAD has no body.
- **Sessions and CSRF**: gated requests leave customer/vendor/driver sessions authenticated; POST with an invalid token → 419; with a valid token → state response and nothing persisted (cart, contact message, newsletter).
- **Gate order (§2.2)**: a guest on `/customer/dashboard` and `/delivery-driver/dashboard` gets the state page, not the login redirect; an unknown product slug gets the state page, not the binding 404; a pending or blocked admin (even a Super Admin) never bypasses; exempt paths make no settings read; a blocked request reads only the settings row, and nothing with a forced state.
- **Bypass**: indicator shown only to bypassing admins and only in non-`open` states; bypass grants no route the admin could not reach otherwise (for example `dashboard/*` abilities still enforced).
- **Toggle**: Super Admin only (403 for others, checked before reading); confirmation required; enum validation; same-state no-op; `changed_at`/`changed_by` recorded; the general settings form cannot change the columns; override warning shown whenever the override is not `auto`, with the forced value, and a saved change does not alter the effective state while the override is active.
- **Indicators (DEC-SA-06)**: the admin header badge shows the effective state for every signed-in admin (with "server override" when applicable) and creates no translation row; the storefront preview bar appears only for bypassing admins in closed states.
- **Static pages (DEC-SA-03, DEC-SA-04; `SiteAvailabilityPagesTest`)**: the served HTML contains no `<form>`, no `<script>`, no `cdn.tailwindcss.com`, no `.woff2` and no unreplaced placeholder; it keeps the exact Google Fonts Tajawal link (the only allowed external URL besides same-site assets, and the only `rel="stylesheet"`), exactly one inline `<style>` equal to the committed artifact, the logo URL, `lang="ar" dir="rtl"` and the `noindex` meta; the approved maintenance copy; no database query in either state; Vite bound to a throwing fake (any use fails the test); styled with an empty public directory (no manifest) and a `public/hot` file present; the committed CSS covers every template class (drift guard), with no `url()`, `@import` or `</style`; a missing or empty CSS file and a missing template each logged and served as the readable fallback; statuses, `Retry-After`, cache/noindex headers and JSON bodies unchanged for the page and the fallback; the original source page and logo SHA-1 unchanged.
- **Migration (SQLite)**: columns, default `open`, the CHECK (exact values: case variants, leading/trailing spaces, other spellings, empty and unknown values rejected), FK `nullOnDelete`, `up()` on the applied schema is a no-op, every partial state refused by `up()` without change, `down()` removing only existing owned objects and safe to repeat, `down()`/`up()` round trip keeping other settings data.
- **Migration (MySQL, `SiteAvailabilityMigrationMySqlTest`)**: runs only when `CATEGORY_FK_MYSQL_DATABASE` names an isolated `hirfah_fk_test_*` database (protected names refused; skipped otherwise). Exact column types, the byte-comparing CHECK (enforced), the foreign key name/target/rule, error 3819 for invalid values with no normalization, error 1452 for an unknown admin, admin deletion clearing the reference, `up()` and `down()` as one `ALTER` each, `up()` on the applied schema issuing nothing, and five partial states (single column, column with key, column with CHECK, all columns without CHECK, orphaned key index) refused by `up()` with no `ALTER` and cleared by `down()` without touching unrelated constraints or data.
- **Unknown URLs (Phase 2B, DEC-SA-09, D1–D4; `SiteAvailabilityUnknownUrlTest`)**: unknown `GET`/`HEAD` (empty `HEAD` body), `POST`/`PUT`/`PATCH`/`DELETE` and `OPTIONS` in all three states; `OPTIONS` on a matched path unchanged; wrong method on known routes (`POST /`, `GET newsletter`) — 405 + `Allow` when open; JSON bodies and statuses; exact headers (`no-store`, `Pragma`, `noindex`, `Retry-After` only in maintenance, no `Allow`, no cookie); D1 static paths 404 in every state and whole-segment matching (`builder`, `x/build/…`, `robots.txt.bak` are not static); the `vendor` prefix overlap; exempt `admin`, `dashboard`, `change-locale`, `up`; no cookie, no 419, no query and no row written with a forced state; forced state with the `settings` and `languages` tables dropped; `auto` → exactly one settings query; `auto` with the table missing → normal 404/405; stored state used in `auto`; `abort(404)`, `ModelNotFoundException` and a thrown 405 inside a matched route not intercepted; gated routes still answered by the gate.
- **Seeders**: new keys in Arabic and English, idempotent, existing rows kept; ability labels added through the existing `AdminAbilityTranslationSeeder` (its exact counts updated).
- **Regression**: full suite green with the state `open` (default) — no behaviour change.

### 11.2 Browser checks

- Coming Soon and Maintenance pages vs the original `index.html` (CDN) at 390, 768 and 1280 px, RTL; no console errors; the logo and CSS load from the site itself and Tajawal from Google Fonts; the network panel shows **no external request other than Google Fonts**; with Google Fonts blocked the page renders in `sans-serif` with the same layout and no horizontal overflow.
- Private window in `coming_soon`: storefront, customer and driver login pages show the Coming Soon page; `vendor/register` shows it too; `vendor/login` works and an existing vendor reaches their dashboard, edits a product, and still sees Coming Soon on the public store page; admin login works.
- Private window in `maintenance`: everything except admin login, dashboard, `change-locale` and `/up` shows the Maintenance page with 503, vendor login included.
- Admin header badge in each state, including the "server override" variant; settings card override warning.
- Super Admin and bypass admin: storefront preview with the indicator; checkout path not used with real accounts (R-1).
- Settings card: options, warnings, confirmation, last change, override notice; mobile layout.
- Response headers checked in the browser network panel (status, `Cache-Control`, `X-Robots-Tag`, `Retry-After`).

### 11.3 Verification results (Phase 1, local)

| Run | Result |
|---|---|
| SQLite — `SiteAvailabilityMigrationTest` | 9 passed, 45 assertions |
| SQLite — `SiteAvailabilityServiceTest` | 8 passed |
| SQLite — `SiteAvailabilityGateTest` | 18 passed |
| SQLite — `SiteAvailabilityVendorAllowlistTest` | 9 passed |
| MySQL 8.4.3 (Laragon) — `SiteAvailabilityMigrationMySqlTest`, isolated database `hirfah_fk_test_site_availability_fix01` | **5 passed, 94 assertions** |
| Full suite (SQLite) | **1461 tests: 1422 passed, 39 skipped, 0 failed** (the skips are the opt-in MySQL tests, including the 5 above) |

History of the MySQL verification:

1. A first run on `hirfah_site_availability_test` found that the original CHECK (plain `IN`) accepted `OPEN` and `maintenance ` under `utf8mb4_unicode_ci`, and that a partially applied migration could not be repaired by the migration itself (`up()` failed with error 1060, `down()` with 3821). The database was kept as evidence.
2. The migration was changed (byte-comparing CHECK, single `ALTER TABLE`, applied/partial detection, idempotent owned-object rollback, §6.1) and verified on the new isolated database `hirfah_fk_test_site_availability_fix01` (5 passed, 94 assertions).
3. Both temporary databases were then dropped by exact name; read-only fingerprints confirmed `multi_vendor_commerce` and `hirfah_fk_test_ordbe001` unchanged before and after every step.

Not verified: MariaDB (no server available) and the production database server (T-3). The development database has **not** been migrated.

### 11.4 Verification results (Phase 2A, local)

| Check | Result |
|---|---|
| SQLite — `SiteAvailabilityPagesTest` (new) | 6 passed |
| SQLite — all `SiteAvailability*` tests | 50 passed, 5 skipped (opt-in MySQL), 720 assertions |
| Full suite (SQLite) | **1467 tests: 1428 passed, 39 skipped, 0 failed** |
| Class coverage | All 122 classes used by the two templates are present in the built CSS (`site-availability-*.css`, 13.08 kB); no `url()` in it |
| Fidelity, Tajawal loaded (headless Edge, original with CDN vs converted, 14 elements, 390 / 768 / 1280 px) | **0 px geometric difference** for every element at every width after the v3 line-height fix (§8.3); before the fix the `h1` and lead differed by up to 22 px at ≥ 640 px. Remaining computed-style differences are notation only (`oklab` vs `rgba` for `bg-white/45`, `calc(infinity * 1px)` vs `9999px` for `rounded-full`) |
| Fidelity, Google Fonts blocked (390 / 1280 px) | Tajawal not loaded in both; `sans-serif` fallback; geometry identical to the original under the same condition; `scrollWidth` equals the viewport (no horizontal overflow) |
| Source integrity | `resources/site-availability/source/index.html` and `hirfah-logo.jpeg` SHA-1 unchanged |
| Approved maintenance copy (PHASE-02A-FINAL) | Text-only change in `maintenance.html` (meta description, badge, description); every `class` attribute identical to `coming-soon.html`; `coming-soon.html` SHA-1 unchanged; `SiteAvailabilityPagesTest` asserts the new copy and the absence of the old; all `SiteAvailability*` tests: 50 passed, 5 skipped, 723 assertions |
| Database | No migration, seeder or database operation in this phase |

CSS hardening (SITE-AVAILABILITY-PHASE-02A-CSS-HARDENING, inline CSS, T-4):

| Check | Result |
|---|---|
| Artifact | `resources/site-availability/site-availability.min.css`, 13,082 bytes, regenerated with the documented command; byte-identical to the previously verified Vite build; all 122 template classes present; no `url()`, `@import` or `</style` |
| SQLite — `SiteAvailabilityPagesTest` | 10 passed, 260 assertions (inline CSS on both pages, no Vite use, no manifest and a hot file present, drift guard, logged fallbacks for missing/empty CSS and missing template, statuses/headers/JSON, source and logo SHA-1) |
| SQLite — all `SiteAvailability*` tests | 54 passed, 5 skipped (opt-in MySQL), 909 assertions |
| Full suite (SQLite) | **1471 tests: 1432 passed, 39 skipped, 0 failed** |

### 11.5 Verification results (Phase 2B, local — unknown URLs, D1–D4)

| Check | Result |
|---|---|
| SQLite — `SiteAvailabilityUnknownUrlTest` (new) | 17 passed, 641 assertions |
| SQLite — all `SiteAvailability*` tests | 71 passed, 5 skipped (opt-in MySQL), 1550 assertions |
| Full suite (SQLite) | **1488 tests: 1449 passed, 39 skipped, 0 failed** |
| Runtime probe of the real implementation (`APP_DEBUG=false`, in-memory SQLite **without tables**, forced state, array session/cache) | Matches the §5.6 matrix in all three states: open 404/405 unchanged (no cookie); closed GET 200/503, HEAD empty body, POST/DELETE/OPTIONS 503, JSON bodies, `Retry-After` only in maintenance, `no-store` everywhere, no cookie; `admin/*`, `dashboard/*` and `build/…` keep 404 |
| Pint (`--test`), `git diff --check` | Clean |
| Unchanged | `EnsureSiteIsAvailable.php`, routes, templates, CSS, admin UI, database, `.env` |

### 11.6 Verification results (Phase 3A, local — `maintenance.bypass`)

| Check | Result |
|---|---|
| SQLite — `MaintenanceBypassAbilityTest` (new) | 12 passed: registered in the catalogue; Super Admin grants (update and create) and revokes; a holder cannot grant it (update and create, 403, nothing written), cannot revoke it (saving without it keeps it; re-submitting what the target holds is allowed), cannot change it on their own account; crafted requests (extra fields, duplicates, `super_admin`, case/wildcard variants → validation error, padded name → trimmed → 403) change nothing; the form lists it for a Super Admin only; unrelated abilities keep the existing scope rules; no automatic grant; `SiteAvailability::canBypass()` reads the granted row (active admins only) |
| SQLite — `AdminAbilityTranslationSeederTest` | Passed with the new counts (53 keys, 36 corrections, 106 rows); English labels equal to the `data/abilities.php` defaults, Arabic labels present |
| SQLite — `AdminManagementTest`, `SuperAdminResolutionTest`, `AccountStatusEnforcementTest`, all `SiteAvailability*` | Passed (with the above: 176 tests, 5 skipped) |
| Full suite (SQLite) | **1500 tests: 1461 passed, 39 skipped, 0 failed** |
| Pint (`--test`), `git diff --check` | Clean |
| Not done | `AdminAbilityTranslationSeeder` not run on any database; no migration; no `.env` change; gate unchanged |

### 11.7 Verification results (Phase 3B, local — settings card and save)

| Check | Result |
|---|---|
| SQLite — `SiteAvailabilitySettingsTest` (new) | 19 passed: card for a Super Admin only (not for `settings.view`/`settings.edit`/`maintenance.bypass` admins, with no availability or schema query for them); effective/saved state, source, last change, deleted admin, warnings; 403 for every non-Super Admin with nothing written and no `settings` or schema read before the check; guest redirected, blocked Super Admin signed out; strict values (`OPEN`, `Maintenance`, `closed`, empty, array, missing…) and required confirmation, nothing written; each state saved with date and author, one settings row; saved state applied by the gate; same state = no-op (date/author kept); read and write inside the save transaction; a failing write (SQLite trigger) → 500, no success message, nothing changed; the general settings form cannot write the three columns; override shown apart from the saved state, form usable, saved but not effective until `auto`; migration rolled back → warning, disabled form, direct save refused, schema and row unchanged; general settings page and form unchanged |
| SQLite — `AdminSiteAvailabilityTranslationSeederTest` (new) | 8 passed: exactly the 28 keys the card and the controller read, English = code defaults, approved Arabic texts verbatim, idempotent, edits kept, cache cleared, the card creates no translation row, not in `DatabaseSeeder` |
| Related suites (`SettingTest`, `HomeHeroTest`, admin dashboard/header/title, account status, admin management, `MaintenanceBypassAbilityTest`, all `SiteAvailability*`, storefront home) | 287 tests passed, 5 skipped |
| Full suite (SQLite) | **1527 tests: 1488 passed, 39 skipped, 0 failed** |
| Pint (`--test`), `git diff --check` | Clean |
| Not done | Seeders not run; no migration; no `.env` change; no browser check of the card yet; header badge and storefront indicator not started |

### 11.8 Verification results (Phase 3B corrections — UI and readiness)

| Check | Result |
|---|---|
| SQLite — `SiteAvailabilitySettingsTest` | 29 passed (10 new): badges for all three states; no Save button and disabled form when not ready; radio/checkbox and label in one row, no `.form-check` in the card; launch warning `#8a4520`, no `text-warning`; columns without the migration record → not ready, `PATCH` refused, nothing written; record without columns → refused; migrations table missing → not ready; a differently named record does not count; record + columns → ready and saves; the migration record read once per save; the general settings form markup unchanged. The existing authorization, validation, no-op, transaction, failure and override tests still pass |
| SQLite — `AdminSiteAvailabilityTranslationSeederTest` | 8 passed (texts unchanged) |
| Full suite (SQLite) | **1537 tests: 1498 passed, 39 skipped, 0 failed** |
| Browser (headless Edge 1280 px, real page rendered against a scratch SQLite file, Arabic RTL, seeded texts) | Computed styles: open badge white / olive `rgb(61,71,43)` border and text; coming soon white / `#8a4520` text, copper border; maintenance solid `rgb(161,61,43)` / white; pill radius, 22 px high. Every radio and the checkbox sit 8 px to the right of their label on the same line. Launch warning `rgb(138,69,32)`. Pending and no-record scenarios render the warning, a disabled form and no Save button; a non-Super Admin's page is byte-identical in size to before (no card). Limitation: 390 px could not be judged — the whole dashboard (with or without the card) overflows when opened from `file://` because its JavaScript does not run there; a real-browser mobile check is still needed |
| Pint (`--test`), `git diff --check` | Clean |
| Rendering without the Vite manifest | The real response rendered with `public/build/manifest.json` temporarily moved aside: one inline `<style>`, one stylesheet link (Google Fonts) on both pages |
| Fidelity, Coming Soon (inline CSS vs original with CDN, 390 / 768 / 1280 px) | **0 px** geometric difference for all 14 elements at every width; notation-only style differences as above |
| Fidelity, Maintenance (inline CSS vs the original with CDN and the maintenance texts swapped in, 390 / 768 / 1280 px) | **0 px** geometric difference for all 14 elements at every width |
| `vite.config.js` | Identical to the committed version (no diff) |


### 11.9 Verification results (Phase 3C, local — header badge and preview bar)

| Check | Result |
|---|---|
| SQLite — `SiteAvailabilityIndicatorTest` (new) | 13 passed: every admin sees the effective state with the right badge and name in all three states; only a Super Admin gets the link to `dashboard/settings#site-availability` (admins with no abilities, `settings.view`, `settings.edit` or `maintenance.bypass` get a plain badge); server override shown (lock, label, title) to both; without the migration and with the `settings` table dropped the badge shows open and the dashboard works; one state read per dashboard page, two on the settings page (card row included), none when forced; no translation row created; bar for a previewing Super Admin (coming soon and maintenance, home and contact) and a `maintenance.bypass` admin; first in `<body>`, `role="note"`, not fixed/sticky/absolute; no bar for an admin without bypass, a blocked Super Admin, a customer, an approved vendor (dashboard and storefront) or an open store; the bar adds no query (only the gate's read) |
| Existing tests adjusted (intent unchanged) | `SiteAvailabilityGateTest`: the "not the availability page" checks now look for the page marker `data-site-availability="` instead of any `data-site-availability…` attribute, since the badge and bar add `data-site-availability-badge` / `-preview` (8 assertions). `SiteAvailabilitySettingsTest`: non-Super Admins now have the header's single state read, still no card read, migration-record read or schema check; the general form test checks the absence of the card instead of any badge. `AdminSiteAvailabilityTranslationSeederTest`: 29 keys, the header as a source, one default per key everywhere |
| Related suites (admin dashboard/header/products/customers/contact messages, media library, storefront, settings, account status, all `SiteAvailability*`) | Passed |
| Full suite (SQLite) | **1550 tests: 1511 passed, 39 skipped, 0 failed** |
| Browser (headless Edge, real pages rendered against a scratch SQLite file, Arabic RTL) | 1280 px: badge at the start of the header's end group («قريبًا» copper outline; «صيانة · مفروضة من الخادم» solid red with lock); bar 40 px, one line, above the storefront top bar. True 390 px (390 px iframes): no horizontal overflow on the storefront or the dashboard (document width = viewport); the bar wraps to two lines (64 px); the badge fits beside the language switcher; with the override badge the header page title is shortened with an ellipsis («الرئ…»), the existing header behaviour. Correction to §11.8: headless Edge never gives a viewport below about 474 px for a 390 px window, which — not JavaScript — cut off the earlier 390 px screenshots; the iframe method avoids it |
| Pint (`--test`), `git diff --check` | Clean |
| Not done | Seeders not run; no migration; no `.env` change; real-device check not done |

---

## 12. Implementation file inventory

### Create

| Path | Purpose |
|---|---|
| `config/site-availability.php` | Override, `Retry-After`, exempt paths, `unmatched_static` (D1, §5.6, Phase 2B), `coming_soon` vendor route allowlist (§5.5). |
| `app/Support/SiteAvailability/SiteAvailabilityState.php` | Backed enum. |
| `app/Support/SiteAvailability/SiteAvailability.php` | Resolution service. |
| `app/Support/SiteAvailability/SiteAvailabilityResponse.php` | Static HTML/JSON responses and headers. |
| `app/Http/Middleware/EnsureSiteIsAvailable.php` | The gate (unchanged by Phase 2B). |
| `app/Support/SiteAvailability/UnmatchedRequestResponse.php` | Availability response for requests no route matched (§5.6, D1–D4). Done in Phase 2B. |
| `app/Http/Controllers/Dashboard/SiteAvailabilityController.php` | `update` (Super Admin). Done in Phase 3B. |
| `app/Support/SiteAvailability/SiteAvailabilitySettings.php` | Schema check, card data, locked transactional write (§7.3a). Done in Phase 3B. |
| `database/migrations/2026_10_20_000000_add_site_availability_to_settings_table.php` | Three columns, CHECK, FK. |
| `database/seeders/AdminSiteAvailabilityTranslationSeeder.php` | Card, header badge and controller texts (29 keys: 28 in Phase 3B, `Site_Availability_Badge_Override` in Phase 3C; not run). |
| `resources/site-availability/source/index.html` | User-provided original (unmodified). |
| `resources/site-availability/coming-soon.html` | Production template (§8.2). |
| `resources/site-availability/maintenance.html` | Production template (§8.2). |
| `public/assets/site-availability/hirfah-logo.jpeg` | User-provided original logo (DEC-SA-07). |
| `resources/css/site-availability.css` | Tailwind source of the page CSS replacing the CDN (`font-tajawal` theme, v3 line-height fix; no `@font-face`, Tajawal stays on Google Fonts, §8.4); compiled into the tracked artifact, not a Vite input. Done in Phase 2A. |
| `resources/views/dashboard/settings/partials/site-availability.blade.php` | Settings card. Done in Phase 3B. |
| `resources/views/storefront/partials/site-availability-indicator.blade.php` | Admin preview bar (§7.4a). Done in Phase 3C. |
| `tests/Feature/SiteAvailabilityIndicatorTest.php` | Header badge and storefront preview bar. Done in Phase 3C. |
| `tests/Feature/SiteAvailabilityServiceTest.php` | Precedence and failure behaviour. |
| `tests/Feature/SiteAvailabilityGateTest.php` | Matrix, responses, headers, sessions, CSRF, locale. |
| `tests/Feature/SiteAvailabilityVendorAllowlistTest.php` | DEC-SA-01 vendor access in `coming_soon`, blocked in `maintenance`, allowlist completeness. |
| `tests/Feature/SiteAvailabilityPagesTest.php` | Static pages: inline CSS, no Vite/manifest/hot dependency, CSS drift guard, no form, no CDN, Google Fonts as the only external URL, no database access, logged fallbacks, statuses/headers/JSON. Done in Phase 2A. |
| `tests/Feature/SiteAvailabilityUnknownUrlTest.php` | Unknown URLs and unsupported methods in every state, D1–D4, headers, JSON, no session/CSRF/database side effects, matched-route exceptions not intercepted. Done in Phase 2B. |
| `resources/site-availability/site-availability.min.css` | Generated, tracked CSS inlined into the pages (§8.3). Done in Phase 2A (CSS hardening). |
| `vite.site-availability.config.js` | Regeneration config for that CSS (§8.3); not used by `npm run build`. Done in Phase 2A (CSS hardening). |
| `tests/Feature/Dashboard/SiteAvailabilitySettingsTest.php` | Settings card and update route (planned as `SiteAvailabilityToggleTest`). Done in Phase 3B. |
| `tests/Feature/SiteAvailabilityMigrationTest.php` | Schema, exact values, partial states and rollback (SQLite). |
| `tests/Feature/SiteAvailabilityMigrationMySqlTest.php` | MySQL path on an isolated `hirfah_fk_test_*` database (opt-in, §11.1). |
| `tests/Feature/AdminSiteAvailabilityTranslationSeederTest.php` | Seeder. Done in Phase 3B. |

### Modify

| Path | Change |
|---|---|
| `bootstrap/app.php` | Append `EnsureSiteIsAvailable` to the `web` group **and** `prependToPriorityList(AuthenticatesRequests::class, EnsureSiteIsAvailable::class)` (order of §2.2). Done in Phase 1. Phase 2B: the `withExceptions()` render callback for `NotFoundHttpException|MethodNotAllowedHttpException` delegating to `UnmatchedRequestResponse` (§5.6). |
| `routes/dashboard.php` | `PATCH settings/site-availability` → `dashboard.setting.site-availability.update`. Done in Phase 3B. |
| `app/Models/Setting.php` | `datetime` cast for `site_availability_changed_at` (columns not `fillable`). Done in Phase 3B. |
| `app/Http/Controllers/Dashboard/SettingController.php` | `index` passes the card data for a Super Admin only. Done in Phase 3B. |
| `data/abilities.php` | `maintenance` group with `bypass` (label keys `dashboard.Maintenance`, `dashboard.Maintenance_Bypass`). Done in Phase 3A. |
| `database/seeders/AdminAbilityTranslationSeeder.php` + its test | The two labels (as CORRECTIONS entries, like the contact-messages labels); counts 51 → 53 keys, 34 → 36 corrections, 102 → 106 rows. Done in Phase 3A (not run). |
| `app/Http/Controllers/Dashboard/AdminController.php` | `SUPER_ADMIN_ONLY_ABILITIES = ['maintenance.bypass']`, excluded from a non-Super Admin's grantable scope (§4.2). Done in Phase 3A. |
| `tests/Feature/Dashboard/MaintenanceBypassAbilityTest.php` (new) | Grant/revoke by Super Admin only, crafted requests, form listing, unrelated abilities unchanged, no automatic grant, gate integration. Done in Phase 3A. |
| `resources/views/dashboard/settings/index.blade.php` | Include the card for Super Admins. Done in Phase 3B. |
| `resources/views/layouts/storefront.blade.php` | Include the preview indicator. Done in Phase 3C. |
| `resources/views/layouts/partials/dashboard/header.blade.php` | State badge (DEC-SA-06, §7.4a). Done in Phase 3C. |
| ~~`vite.config.js`~~ | **Not changed**: the `site-availability.css` input added in Phase 2A was removed again in the CSS hardening; the file equals the committed version. |
| `app/Support/SiteAvailability/SiteAvailabilityResponse.php` | Phase 2A: serve the templates (placeholders filled, the tracked CSS inlined as `<style>`, no Vite); the Phase 1 page kept as the logged built-in fallback for a missing template or CSS. |
| ~~`.env.example`~~ | Not applicable: the repository has no `.env.example`; the variables are documented in `config/site-availability.php` and §10. |

Not changed: storefront controllers and views (other than the layout include), account guards, `EnsureAccountIsActive`, existing abilities, `artisan down` behaviour.

### Implementation phases

1. **Core** — **implemented and tested locally** (§0, §11.3): enum, service, config, migration, middleware, response builder with a temporary plain page; service, gate, vendor-allowlist and migration tests (SQLite and isolated MySQL). Files: `config/site-availability.php`, `app/Support/SiteAvailability/{SiteAvailabilityState,SiteAvailability,SiteAvailabilityResponse}.php`, `app/Http/Middleware/EnsureSiteIsAvailable.php`, the migration, `bootstrap/app.php`, and the five `SiteAvailability*Test.php` files. Not committed.
2. **Pages** — **2A implemented and tested locally** (§0, §11.4): templates, local CSS inlined from a tracked artifact (no Vite at response time, T-4 resolved), response builder with logged fallback, Tajawal kept on Google Fonts (no self-hosting), fidelity and fonts-blocked checks, `SiteAvailabilityPagesTest`. Not committed. **2B implemented and tested locally** (§5.6, §11.4): exception render callback, `UnmatchedRequestResponse`, `unmatched_static` config (D1–D4), `SiteAvailabilityUnknownUrlTest`. Not committed. Review.
3. **Admin** — **3A implemented and tested locally** (§4.2, §11.6): `maintenance.bypass` in `data/abilities.php` with its labels (`AdminAbilityTranslationSeeder`, not run), Super Admin-only grant/revoke in `AdminController`, `MaintenanceBypassAbilityTest`. Not committed. **3B implemented and tested locally** (§7.3a, §11.7): settings card, `SiteAvailabilityController`, route, `SiteAvailabilitySettings`, `Setting` cast, `AdminSiteAvailabilityTranslationSeeder` (not run), `SiteAvailabilitySettingsTest`. Not committed. **3C implemented and tested locally** (§7.4a, §11.9): header badge, storefront preview bar, `SiteAvailabilityIndicatorTest`. Not committed. Phase 3 complete. Review.
4. **Rollout** (pending): confirm the production database engine and version (T-3), backup, development migration and seeders (each approved), browser checks, cPanel sequence (§10).

---

## 13. Questions

### 13.1 Closed (SITE-AVAILABILITY-DESIGN-03)

| # | Question | Resolution |
|---|---|---|
| Q-1 | Vendor (and driver) dashboards during `coming_soon`? | **Closed — DEC-SA-01**: vendors only, through a route-name allowlist in `coming_soon`; drivers and customers stay gated; everything gated in `maintenance`. |
| Q-2 | 503 for state-changing methods during `coming_soon`? | **Closed — DEC-SA-02**: yes. |
| Q-3 | Sign-up or contact form on the page? | **Closed — DEC-SA-03**: none. |
| Q-4 | Fonts? | **Closed — DEC-SA-04** (revised in SITE-AVAILABILITY-PHASE-02A): Tajawal kept on Google Fonts exactly as in the original, approved optional external dependency, `sans-serif` fallback; no self-hosting; no Tailwind CDN. |
| Q-5 | `change-locale/*` exempt? | **Closed — DEC-SA-05**: yes, without exposing content or bypassing authorization. |
| Q-6 | Admin header badge? | **Closed — DEC-SA-06**: yes, plus the storefront preview indicator. |
| Q-7 | Source assets? | **Closed — DEC-SA-07**: destinations fixed, and the files are now present (B-1 resolved). |
| T-1 | Which font families and weights does the source page use? | **Closed** (read from the source `index.html`): **Tajawal only, weights 400, 500, 700, 800 and 900**; the page has no form. |
| — | Unknown URLs while closed? | **Closed — DEC-SA-09** (§5.6), implemented in Phase 2B with D1–D4. |

### 13.2 Blocking items

| # | Item | Status |
|---|---|---|
| B-1 | The user-supplied original `index.html` and `hirfah-logo.jpeg` at `resources/site-availability/source/index.html` and `public/assets/site-availability/hirfah-logo.jpeg`. | **Resolved**: both files are present (untracked, not modified). The page references no other local asset. |
| B-2 | ~~Tajawal `woff2` files and `OFL.txt`~~ | **Withdrawn** (SITE-AVAILABILITY-PHASE-02A): Tajawal stays on Google Fonts (DEC-SA-04, §8.4); no font files are required. |

### 13.3 Open technical questions and deployment prerequisites

| # | Question | Why it is open |
|---|---|---|
| T-2 | Does the cPanel account allow a password-protected preview subdomain pointed at `~/hirfah/public`, and is the main domain's document root changeable? | Selects step 4 and step 7 variants of §10; depends on the hosting plan. |
| T-3 | **Confirm the cPanel database engine and version** (MySQL ≥ 8.0.19 or MariaDB ≥ 10.2.1 required, §6.1). | **Deployment prerequisite.** Only MySQL 8.4.3 (local Laragon) has been tested; MariaDB has not. Local verification is not proof of production compatibility; ideally repeat the isolated MySQL migration test on a matching server before migrating production. |
| T-4 | **CSS delivery for the availability pages** (§8.3.1). | **Resolved** (SITE-AVAILABILITY-PHASE-02A-CSS-HARDENING): the CSS is inlined from the tracked `resources/site-availability/site-availability.min.css`; tests confirm no use of Vite, styling with no manifest and with `public/hot` present, and a logged readable fallback if the artifact is missing (§8.3.1, §11.4). Residual: the artifact must be regenerated when template classes change (drift guard test fails otherwise). |
