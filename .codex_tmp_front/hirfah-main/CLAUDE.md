# HIRFAH — Project Reference for Claude

HIRFAH (حِرفة) is a static-HTML front-end prototype for a **multi-vendor marketplace** selling Palestinian handmade/artisan goods (pottery, textiles, baskets, candles). This is the **customer storefront only** — no vendor dashboard, admin dashboard, or delivery-driver interface belongs in this repo.

Read `docs/01-pages-analysis.md`, `docs/02-remaining-pages-plan.md`, and `docs/design-system.md` for full requirements/rationale. This file is the condensed, code-facing version of those — when they conflict with what's actually in the code, trust the code and flag the mismatch.

## Stack

- Plain HTML pages (no framework, no bundler beyond Tailwind CLI).
- Tailwind CSS v4, compiled from `assets/css/input.css` → `assets/css/output.css`.
  - `npm run build` — one-off minified build.
  - `npm run dev` — watch mode. **Run this (or `build`) after any class/token change** — `output.css` is generated, editing it directly will be overwritten.
  - New HTML files must be added to the `@source` list at the top of `input.css` or Tailwind won't scan them for classes.
- jQuery 3.7 (`assets/js/vendor/jquery.min.js`) for all interactivity. No other JS framework.
- Lucide icons (`assets/js/vendor/lucide.min.js`), invoked as `lucide.createIcons()`.
- No backend, no build-time data — all product/vendor/order data is **hardcoded directly in the HTML**, and simple state (cart count/total) lives in module-scoped JS variables in `assets/js/app.js`.

## Pages (13, all exist)

`index.html` (Home), `categories.html`, `browse.html` (filtered results), `vendors.html` (directory), `vendor.html` (single storefront), `product.html`, `cart.html`, `checkout.html`, `orders.html` (list), `order-detail.html`, `login.html`, `profile.html`, `rate-order.html`.

Each page has (or should reuse) the same header/footer/mobile-menu/search-panel markup block — when editing shared chrome (nav links, header, footer), **it is duplicated per file, not templated** — changes must be applied to every page individually. Recent commits (`Fix header nav and footer links across all pages`, `Make product cards clickable`) are exactly this pattern: a fix in one shared block has to be repeated across all 13 files. Check consistency across pages after any header/footer/nav change.

## Navigation structure (important — supersedes older docs)

There is **no single "Browse" landing page**. The real structure, per `docs/02-remaining-pages-plan.md`:

- **كل التصنيفات** (`categories.html`) — pure category directory, no products, links out to filtered results.
- **كل المتاجر** (`vendors.html`) — vendor directory grid → **متجر واحد** (`vendor.html`, single vendor storefront: vendor identity + that vendor's own categories + products).
- **نتائج مفلترة** (`browse.html`) — the actual filter/sort/product-grid results page. Reached via category click, header search, or manual filtering — **not** a page with its own permanent nav link. Reads filters from query params.

Don't reintroduce a generic top-level "Browse" nav item — that model was explicitly replaced.

## Non-negotiable business rules (apply to any page touching orders/cart/products)

- **Multi-vendor grouping is the core mental model.** A "Parent Order" = multiple independent "Vendor Sub-orders". Anywhere products/sub-orders appear (cart, checkout, order list, order detail), group by vendor visually — always show vendor name/logo next to the item, never a flat undifferentiated list.
- **Order lifecycle states (sub-order level) — this exact list, never invent extra states:**
  `Pending → Accepted → Preparing → Ready for Delivery → Assigned → Out for Delivery → Delivered → Completed`, plus exception states `Rejected` and `Cancelled`.
- **Sub-order rejection is a normal state, not an error.** UI must read as "this vendor's part was cancelled, the rest of the order continues" — never as a failure of the whole order.
- **No order cancellation after checkout, ever.** Checkout must have an explicit, hard-to-miss confirmation step (modal or dedicated screen) before finalizing — for both payment methods.
- **Exactly two payment methods:** Online Payment and Cash on Delivery. No default pre-selected — the user must actively choose.
- **Ratings are always a 3-part set** (product + vendor + delivery driver) submitted together, never as separate flows.
- **No WhatsApp integration, no live GPS map tracking** — explicitly out of scope, don't design touchpoints for either.
- **Every empty state needs real design** (icon + short message + CTA) — cart, search results, orders, notifications. Never a blank page.
- **Numerals are always Western (1, 2, 3...)** even in Arabic UI — never Arabic-Indic digits (٠١٢٣), including prices, quantities, ratings.
- No geographic gating on Browse — city/location is a filter, never a hard restriction on what's visible.

## Language / RTL state — read this before touching anything language-related

Despite the design docs mandating full bilingual AR/EN with real layout mirroring, **the actual current implementation is Arabic-only content, `dir="rtl"` hardcoded per-page in the `<html>` tag.** The `.language-toggle` button (`assets/js/app.js`) only flips the `lang`/`dir` attributes and swaps an "EN"/"AR" label — it does **not** translate or swap any real text content. Treat true bilingual/mirrored-layout support as **not implemented yet**, not as a regression to fix quietly — if a task implies adding it, that's a real feature, call it out.

If/when real bilingual support is built: use Tailwind logical properties only (`ms-*`, `me-*`, `ps-*`, `pe-*`, `text-start`, `text-end`, `start-*`, `end-*`) — never `ml-`, `mr-`, `pl-`, `pr-`, `left-`, `right-` — so mirroring works without a rewrite.

## Design tokens (actually implemented, in `assets/css/input.css` `@theme`)

Colors: `ink`, `olive`, `sage`, `copper`, `canvas` (warm cream page background — never pure white for page bg), `surface` (card/modal bg), `line` (borders), `muted` (secondary text), `gold`.
Fonts: `cairo` (default body/UI), `amiri` (display/headline serif accent), `noto` (Noto Sans Arabic fallback).

Note: `docs/design-system.md` describes a richer palette (explicit warning/danger hex values, etc.) that isn't fully wired into the Tailwind theme yet — the doc is the design intent, this list is what's actually usable as a class today. If a task needs a color not in this list, check whether it should be added to `@theme` in `input.css` rather than hardcoded as an arbitrary Tailwind value.

## Working conventions

- Don't hand-edit `assets/css/output.css` — it's generated. Edit `input.css` (tokens) or the HTML (utility classes), then rebuild.
- jQuery event handlers are attached per-page in `assets/js/app.js` (shared) or a page-specific file (`browse.js`, `cart.js`, `checkout.js`, `order-detail.js`, `profile.js`, `rate-order.js`, `auth.js`). Check which script a page actually includes before assuming a handler exists.
- No test suite, no linter configured — verify changes by opening the page in a browser.
- Never commit or push without being explicitly asked — this repo's history shows small, focused commits (one concern per commit); match that granularity when asked to commit.
