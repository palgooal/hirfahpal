# HIRFAH Review Findings

Central log of findings from reviewing HIRFAH against the SRS, the architecture and the actual implementation.

> **Rule:** A UI PASS does not automatically close related Backend, Security, SRS, or Architecture findings.

## Review Scope & Implementation Ownership

This document is a review log intended for handoff to the **Palgoals Backend Team**.

- **A.** Backend findings in this document are review findings for the Palgoals Backend Team unless explicitly marked **IMPLEMENTED & VERIFIED**.
- **B.** Proposed Backend contracts describe recommended behavior derived from the SRS, the architecture, analysis of the current implementation, and review decisions.
- **C.** A proposed contract is **not** evidence that the Backend has been implemented.
- **D.** Backend implementation remains the responsibility of the Palgoals Backend Team, which reviews the findings and proposed contracts and decides on and implements Backend corrections.
- **E.** The current implementation scope of this review workstream is the **Vendor Dashboard frontend/UI**. The workstream audits the existing Backend against the SRS/architecture, documents findings, evidence, risks and proposed contracts, and reports them to the Palgoals Backend Team; it does not implement additional Backend changes.
- **F.** Frontend work may expose additional Backend dependencies. These are recorded and reported, not silently implemented by this workstream.
- **G.** No Backend finding is considered fixed merely because a proposed contract exists.

**Terminology.** In review-decision rounds, "approved" / "decided" means **approved as a review recommendation** by this review workstream. It does not mean implemented by, or accepted by, the Palgoals Backend Team unless a finding states so explicitly. Where a finding cites an "approved" VEN-BE-020 rule or lifecycle, read it as the proposed Phase 1 contract below.

**Historical exceptions.** Before this ownership boundary was clarified, some Backend changes were already made in the current working tree during earlier review work. They are marked **IMPLEMENTED & VERIFIED** on their findings (VEN-BE-001, VEN-BE-002, VEN-BE-019). VEN-BE-010 is a later, limited integration exception of the same kind: the vendor dashboard route split completed by the Vendor Auth/Frontend workstream (VUI-01C) to finish the vendor auth/approval flow. Other Backend-adjacent changes in the working tree only support the approved Vendor Auth UI screens (vendor view selection in `AccountGuard` and the account auth controllers, `VendorAuthTranslationSeeder`, the `vendor-auth.css` Vite entry); they are not fixes for any finding.

### Maturity labels

Maturity is separate from the finding status (`OPEN`, `PASS`, `DEFERRED`, …), which keeps its existing meaning.

| Label | Meaning |
|---|---|
| **IMPLEMENTED & VERIFIED** | Backend code was actually changed in the current working tree, and relevant tests/verification were performed. This is **not** the default classification. |
| **BACKEND FINDING — PALGOALS TEAM** | A defect, gap, inconsistency, risk or SRS mismatch found during review, reported to the Palgoals Backend Team for evaluation/correction. No implementation by the current frontend workstream is implied. **Default** for every Backend finding not marked otherwise (`VEN-BE-*`, `ADM-BE-*`, `SRS-*`). |
| **PROPOSED CONTRACT — TEAM REVIEW REQUIRED** | Detailed recommended Backend behavior documented to remove ambiguity and help implementation. It is review/design material, not implemented merely by being documented, and subject to Palgoals Backend Team technical review before implementation. |

The following unresolved findings are **BACKEND FINDING — PALGOALS TEAM** and are **not** frontend tasks of the current workstream: VEN-BE-020, VEN-BE-021, VEN-BE-023, VEN-BE-025, VEN-BE-029, VEN-BE-030, VEN-BE-031, ADM-BE-002, ADM-BE-003, ADM-BE-004, ADM-BE-005, ADM-BE-006, ADM-BE-007, SRS-001.

### Frontend dependency rule

When Vendor Dashboard frontend work reaches a feature whose required Backend contract is missing, unsafe, ambiguous, or still OPEN:

- do not silently implement Backend behavior;
- do not invent API or business rules;
- record or cross-reference the relevant Backend finding;
- implement only the frontend portion that can safely proceed;
- report the dependency to the Palgoals Backend Team.

This applies in particular to Orders, Finance, Reports, media upload, Returns, Disputes, and any other Backend-dependent Vendor Dashboard screen.

**Next implementation workstream:** Vendor Dashboard Frontend/UI, starting with the Vendor Dashboard Shell. UI design details are not documented in this Backend findings document.

## Conventions

**ID prefixes**

| Prefix | Area |
|---|---|
| `VEN-BE-###` | Vendor Backend |
| `VEN-UI-###` | Vendor UI |
| `ADM-BE-###` | Admin Backend |
| `ADM-UI-###` | Admin UI |
| `SEC-###` | Security |
| `SRS-###` | SRS/Implementation discrepancy |

**Statuses:** `OPEN` · `SENT TO TEAM` · `IN PROGRESS` · `READY FOR REVIEW` · `PASS` · `CLOSED` · `DEFERRED`

**Priorities:** `CRITICAL` · `HIGH` · `MEDIUM` · `LOW` · `REVIEW`

## Summary

| ID | Area | Title | Priority | Status |
|---|---|---|---|---|
| [VEN-BE-001](#ven-be-001--vendor-approval-is-not-enforced) | Vendor Backend | Vendor approval is not enforced | CRITICAL | PASS |
| [VEN-BE-002](#ven-be-002--vendor-password-reset-email-uses-the-general-reset-route) | Vendor Backend | Vendor password reset email uses the general reset route | HIGH | PASS |
| [VEN-BE-003](#ven-be-003--forgot-password-account-enumeration) | Vendor Backend | Forgot Password account enumeration | HIGH | OPEN |
| [VEN-BE-004](#ven-be-004--forgot-password-rate-limit-uses-generic-429-page) | Vendor Backend | Forgot Password rate limit uses generic 429 page | MEDIUM | OPEN |
| [VEN-BE-005](#ven-be-005--store_name-is-consumed-without-server-side-validation) | Vendor Backend | `store_name` is consumed without server-side validation | HIGH | OPEN |
| [VEN-BE-006](#ven-be-006--vendor-registration-profile-fields-require-srs-review) | Vendor Backend | Vendor registration profile fields require SRS review | REVIEW | OPEN |
| [VEN-BE-007](#ven-be-007--termsprivacy-destinations-are-not-defined) | Vendor Backend | Terms/Privacy destinations are not defined | REVIEW | OPEN |
| [VEN-BE-008](#ven-be-008--vendor-auth-translations-require-manual-seeding) | Vendor Backend | Vendor Auth translations require manual seeding | MEDIUM | OPEN |
| [VEN-BE-009](#ven-be-009--english-validation-localization-incomplete) | Vendor Backend | English validation localization incomplete | LOW | DEFERRED |
| [VEN-BE-010](#ven-be-010--vendor-dashboard-web-route-currently-returns-json) | Vendor Backend | Vendor dashboard web route currently returns JSON | HIGH | PASS |
| [VEN-BE-011](#ven-be-011--password-reset-email-expiry-text-uses-default-broker-configuration) | Vendor Backend | Password-reset email expiry text uses default broker configuration | MEDIUM | OPEN |
| [VEN-BE-012](#ven-be-012--password-reset-broker-errors-are-attached-to-the-email-field) | Vendor Backend | Password reset broker errors are attached to the email field | LOW | OPEN |
| [VEN-BE-013](#ven-be-013--registration-and-password-reset-password-policies-can-diverge) | Vendor Backend | Registration and password-reset password policies can diverge | MEDIUM | OPEN |
| [VEN-BE-014](#ven-be-014--storefront-does-not-enforce-vendor-approval) | Vendor Backend | Storefront does not enforce Vendor approval | CRITICAL | OPEN |
| [VEN-BE-015](#ven-be-015--storefront-product-access-does-not-consistently-enforce-vendor-account-status) | Vendor Backend | Storefront product access does not consistently enforce Vendor account status | HIGH | OPEN |
| [VEN-BE-016](#ven-be-016--vendor-profile-update-validation-is-inconsistent-with-registration-identity-rules) | Vendor Backend | Vendor profile update validation is inconsistent with registration identity rules | HIGH | OPEN |
| [VEN-BE-017](#ven-be-017--vendor-approval-endpoints-lack-dedicated-regressionsecurity-tests) | Vendor Backend | Vendor approval endpoints lack dedicated regression/security tests | MEDIUM | OPEN |
| [VEN-BE-018](#ven-be-018--commission-and-financial-lifecycle-is-not-implemented) | Vendor Backend | Commission and financial lifecycle is not implemented | HIGH | OPEN |
| [VEN-BE-019](#ven-be-019--inventory-commitment-lifecycle-is-not-implemented) | Vendor Backend | Inventory commitment lifecycle is not implemented | HIGH | PASS |
| [VEN-BE-020](#ven-be-020--vendor-order-lifecycle-cannot-complete-through-the-normal-flow) | Vendor Backend | Vendor order lifecycle cannot complete through the normal flow | HIGH | OPEN |
| [VEN-BE-021](#ven-be-021--dashboard-order-buckets-omit-delivered-vendor-orders) | Vendor Backend | Dashboard order buckets omit `delivered` vendor orders | LOW | OPEN |
| [VEN-BE-022](#ven-be-022--sales_total-has-no-approved-financial-definition) | Vendor Backend | `sales_total` has no approved financial definition | MEDIUM | OPEN |
| [VEN-BE-023](#ven-be-023--vendor-api-responses-overexpose-data) | Vendor Backend | Vendor API responses overexpose data | MEDIUM | OPEN |
| [VEN-BE-024](#ven-be-024--vendor-profile-city-is-not-validated-against-its-governorate) | Vendor Backend | Vendor profile city is not validated against its governorate | LOW | OPEN |
| [VEN-BE-025](#ven-be-025--vendor-media-upload-contract-is-missing) | Vendor Backend | Vendor media upload contract is missing | HIGH | OPEN |
| [VEN-BE-026](#ven-be-026--vendor-review-visibility-needs-a-contract-decision) | Vendor Backend | Vendor review visibility needs a contract decision | REVIEW | OPEN |
| [VEN-BE-027](#ven-be-027--vendor-response-deadline-is-not-enforced) | Vendor Backend | Vendor response deadline is not enforced | REVIEW | OPEN |
| [VEN-BE-028](#ven-be-028--vendor-list-endpoints-accept-an-unbounded-per_page) | Vendor Backend | Vendor list endpoints accept an unbounded `per_page` | LOW | OPEN |
| [VEN-BE-029](#ven-be-029--stock-reservation-and-checkout-lifecycle-has-no-automated-tests) | Vendor Backend | Stock, reservation and checkout lifecycle has no automated tests | HIGH | OPEN |
| [VEN-BE-030](#ven-be-030--checkout-uses-the-carts-stale-unit-price) | Vendor Backend | Checkout uses the cart's stale unit price | MEDIUM | OPEN |
| [VEN-BE-031](#ven-be-031--review-eligibility-incorrectly-depends-on-parent-order-completion) | Vendor Backend | Review eligibility incorrectly depends on Parent Order completion | MEDIUM | OPEN |
| [ADM-BE-001](#adm-be-001--non-vendor-password-reset-emails-use-the-general-fortify-reset-route) | Admin Backend | Non-vendor password-reset emails use the general Fortify reset route | HIGH | OPEN |
| [ADM-BE-002](#adm-be-002--admin-can-create-inconsistent-vendor-accountapproval-states) | Admin Backend | Admin can create inconsistent Vendor account/approval states | HIGH | OPEN |
| [ADM-BE-003](#adm-be-003--vendor-approvalrejection-has-no-transition-contract-or-decision-history) | Admin Backend | Vendor approval/rejection has no transition contract or decision history | MEDIUM | OPEN |
| [ADM-BE-004](#adm-be-004--admin-driver-assignment-does-not-enforce-full-driver-eligibility) | Admin Backend | Admin driver assignment does not enforce full driver eligibility | HIGH | OPEN |
| [ADM-BE-005](#adm-be-005--admin-can-set-the-parent-order-lifecycle-and-payment-status-independently-of-its-vendor-orders) | Admin Backend | Admin can set the parent order lifecycle and payment status independently of its vendor orders | HIGH | OPEN |
| [ADM-BE-006](#adm-be-006--admin-can-attach-a-header-only-vendor-order-to-an-existing-customer-order) | Admin Backend | Admin can attach a header-only vendor order to an existing customer order | MEDIUM | OPEN |
| [ADM-BE-007](#adm-be-007--admin-generic-vendororder-creation-can-bypass-the-approved-lifecycle) | Admin Backend | Admin generic VendorOrder creation can bypass the approved lifecycle | HIGH | OPEN |
| [SRS-001](#srs-001--vendor-reports-backend-is-missing) | SRS/Implementation discrepancy | Vendor reports backend is missing | HIGH | OPEN |

**Totals: 39 findings**

| Priority | Count | | Status | Count |
|---|---|---|---|---|
| CRITICAL | 2 | | OPEN | 34 |
| HIGH | 17 | | PASS | 4 |
| MEDIUM | 11 | | DEFERRED | 1 |
| LOW | 5 | | | |
| REVIEW | 4 | | | |

## Approved Vendor UI

PASS here means **visual/UI approval only**. It does not close any Backend, Security, SRS or Architecture finding, including those listed against the same screen.

| Screen | Route | View | Status | Related open findings |
|---|---|---|---|---|
| Vendor Login UI | `vendor.login` | `resources/views/auth/vendor/login.blade.php` | PASS | VEN-BE-001, VEN-BE-008, VEN-BE-010 |
| Vendor Registration UI | `vendor.register` | `resources/views/auth/vendor/register.blade.php` | PASS | VEN-BE-001, VEN-BE-005, VEN-BE-006, VEN-BE-007, VEN-BE-008, VEN-BE-009 |
| Vendor Forgot Password Request UI | `vendor.password.request` | `resources/views/auth/vendor/forgot-password.blade.php` | PASS | VEN-BE-002, VEN-BE-003, VEN-BE-004, VEN-BE-008 |
| Vendor Reset Password UI | `vendor.password.reset` | `resources/views/auth/vendor/reset-password.blade.php` | PASS | VEN-BE-003, VEN-BE-008, VEN-BE-012, VEN-BE-013 |

**Vendor Reset Password UI review scope:** Arabic Desktop, English Desktop, Arabic Mobile, RTL/LTR, and responsive layout.

**Mobile vertical centering (not a finding):** on short vendor auth pages the content block is vertically centered on mobile (`min-h-screen` + `items-center` on `<main>`), which leaves extra space above the language switcher on tall screens. This was reviewed across Login, Registration, Forgot Password and Reset Password: it is shared, intentional layout behavior, there is no horizontal overflow, and the decision is to keep it.

---

## Approved Vendor Approval Contract

Approved product decisions from the Vendor Post-Login & Approval Flow Audit. This section is a contract, not a finding; implementation is tracked by VEN-BE-001, VEN-BE-014, VEN-BE-015, ADM-BE-002, ADM-BE-003 and VEN-BE-017.

States are taken from the schema: `vendors.status` ∈ `active`, `pending`, `blocked`; `vendor_profiles.approval_status` ∈ `pending`, `approved`, `rejected`.

**Access by state**

| Account status | Approval status | Login | After login | Commercial activity |
|---|---|---|---|---|
| `active` | `pending` | Allowed | Approval Status page only | None |
| `active` | `approved` | Allowed | Vendor Dashboard and operational functions | Allowed |
| `active` | `rejected` | Allowed | Rejected Status page, with the rejection reason if present | None |
| `pending` | any | Refused — "account pending activation" message | — | None |
| `blocked` | any | Refused — "account blocked" message | — | None |

**Rules**
- Account status takes precedence over approval status.
- Registration keeps `status=active` + `approval_status=pending` with automatic login, but the redirect must end in the Status experience, not the operational dashboard.
- While pending, the vendor may currently use only: the Status page, Logout, and language switching.
- No Profile/Store editing while pending under this contract.
- No draft products while pending.
- Rejection does not automatically mean `vendors.status=blocked`.
- No reapply in the current Phase 1.
- No new `under_review` state.
- Storefront sellability must require an eligible and approved Vendor, enforced independently of Dashboard authorization.
- Transitions such as `rejected → approved` or `approved → rejected` are not approved yet; they need a separate lifecycle decision (see ADM-BE-003).

**Deferred audit notes (not findings)** — to revisit when reviewing the Product and Admin modules:
- Vendors can set `is_featured` on their own products; whether this is allowed is a product decision.
- Vendor JSON responses expose `approved_by` (admin id) and `commission_rate`, and `recent_orders` includes full customer objects; review for data minimisation. → Now tracked as a finding: VEN-BE-023.
- There is no admin route to block or reactivate a vendor independently of approval.

---

## Findings

### VEN-BE-001 — Vendor approval is not enforced

- **ID:** VEN-BE-001
- **Area:** Vendor Backend
- **Priority:** CRITICAL
- **Status:** PASS
- **Maturity:** IMPLEMENTED & VERIFIED — historical exception: the Backend change was made in the current working tree during earlier review work, before the ownership boundary was clarified (see Review Scope & Implementation Ownership). It is still subject to Palgoals Backend Team review.
- **Discovered During:** Initial Admin/Vendor architecture audit; confirmed during Vendor Registration UI.

**Current Behavior**
Self-registration creates a `Vendor` with `status=active` and a `VendorProfile` with `approval_status=pending`, then logs the vendor in immediately and redirects to `vendor.dashboard`. Login (`GuardLoginService`) and `EnsureAccountIsActive` check only `vendors.status`, never `approval_status`. A pending vendor can therefore reach every `vendor/dashboard/*` endpoint, including product create/update/delete, order actions and profile updates.

**Expected Behavior**
Approval is enforced centrally. A vendor whose profile is not `approved` can see the status of their application (pending / rejected with reason) but cannot manage the store (products, orders, profile operations) until an admin approves.

**SRS / Architecture Reference**
SRS: vendor submits a registration request that requires admin approval. Exact section to be linked.

**Related Routes / Files**
- Routes: `vendor.register.store`, `vendor.login.store`, `vendor.dashboard`, `vendor.dashboard.*`
- `app/Actions/Auth/CreateAccountUser.php`
- `app/Http/Controllers/Auth/AccountRegisteredUserController.php`
- `app/Support/Auth/GuardLoginService.php`
- `app/Http/Middleware/EnsureAccountIsActive.php`
- `app/Http/Controllers/Dashboard/VendorManagementController.php` (approve/reject)
- `app/Http/Controllers/VendorDashboard/*`
- `routes/web.php` (vendor account group)

**Backend Guidance**
- Enforce in one place (e.g. a dedicated middleware on the operational `vendor.dashboard.*` routes), not per controller.
- Keep a small allow-list reachable while pending: application status view and logout.
- Decide explicitly how `vendors.status` and `vendor_profiles.approval_status` relate, so the two cannot disagree (e.g. admin "create vendor" currently allows `status=active` with `approval_status=pending`).
- Do not weaken the existing ownership checks in `VendorDashboard` controllers.

**Acceptance Criteria**
- A pending vendor gets a non-operational response (redirect to status page, or 403 for JSON) from every operational vendor route.
- A rejected vendor sees the rejection reason and cannot operate the store.
- An approved vendor keeps full current access.
- Admin approve/reject immediately changes access without requiring re-login.

**Required Tests**
- Pending vendor: each operational route denied (web + JSON).
- Pending vendor: status page and logout allowed.
- Rejected vendor: denied and sees reason.
- Approved vendor: existing `VendorDashboardBackendTest` scenarios still pass.
- Approve/reject mid-session takes effect on the next request.

**Resolution Notes**
Implemented per the Approved Vendor Approval Contract. **PASS** after review (not CLOSED).

*Verification (manual review completed successfully):*
- `active + pending`: login allowed; lands on Approval Status; opening `/vendor/dashboard` redirects back to Approval Status.
- `active + rejected`: reaches Approval Status and sees `rejection_reason`; opening `/vendor/dashboard` redirects back to Approval Status.
- `active + approved`: reaches the operational Vendor Dashboard successfully.
- Automated enforcement suite (`VendorApprovalEnforcementTest`): 21/21 PASS.
- Full suite: still the 12 baseline failures; none is a new regression. Five of them (`CustomerAccountDetailsPageTest`, `CustomerAddressesPageTest`, `CustomerDashboardPageTest`, `CustomerFavoritesPageTest`, `StorefrontCartPageTest`) changed failure signature because of the Approval Gate: their profile-less vendor now gets a 302 to Approval Status instead of a non-view response from `vendor.dashboard` (see VEN-BE-010).
- `approved_at` / `approved_by` being `null` in the manual test is **not a finding**: the approved state was produced by editing local test data directly, not through the Admin Approve workflow (which sets both).

*Implementation:*
- **Gate:** new middleware `EnsureVendorIsApproved` (alias `vendor.approved`) on the whole `vendor/dashboard` group (all 23 operational `vendor.dashboard*` routes). Unapproved vendors get a redirect to `vendor.approval-status` (web) or `403` JSON with `approval_status` and `status_url`. `Vendor::isApproved()` reads `profile.approval_status`; a vendor without a profile is not approved.
- **Ordering:** the gate is prioritised before `SubstituteBindings`, so an unapproved vendor never reaches route-model lookups (non-existent ids also redirect). Inactive accounts pass through the gate and are signed out by `EnsureAccountIsActive`, so account status keeps precedence.
- **Status contract:** `GET vendor/approval-status` (`vendor.approval-status`, `auth:vendor` + `setLocale`, outside the gate) returns JSON `approval_status`, `rejection_reason` (only when rejected), `store_name`, `dashboard_url` (only when approved). No final UI yet.
- **Redirects:** registration and login still target `vendor.dashboard`; the gate sends pending/rejected vendors on to the status route, approved vendors stay on the dashboard.
- **Admin rejection:** no longer sets `vendors.status=blocked`; it keeps the account status and stores the reason.
- **Tests:** `tests/Feature/VendorApprovalEnforcementTest.php` (21 tests) covers registration state and flow, pending/rejected login, every operational route by web and JSON including direct mutations and non-existent ids, rejection reason, admin rejection not blocking, approved access, account-status precedence, logout, language switching on the status route, mid-session approve/reject, and a structural check that only `vendor.logout` and `vendor.approval-status` are outside the gate. `VendorDashboardBackendTest` and `AccountStatusEnforcementTest` now use approved vendor profiles for their operational scenarios.
- **Not in scope:** storefront enforcement (VEN-BE-014, VEN-BE-015), admin create combinations (ADM-BE-002), transition rules/history (ADM-BE-003), status page UI.

---

### VEN-BE-002 — Vendor password reset email uses the general reset route

- **ID:** VEN-BE-002
- **Area:** Vendor Backend
- **Priority:** HIGH
- **Status:** PASS
- **Maturity:** IMPLEMENTED & VERIFIED — historical exception: the Backend change was made in the current working tree during earlier review work, before the ownership boundary was clarified (see Review Scope & Implementation Ownership). It is still subject to Palgoals Backend Team review.
- **Discovered During:** Vendor Forgot Password Request UI.

**Current Behavior**
`vendor.password.email` sends the reset email through the `vendors` broker, but the link in the email is Laravel's default `ResetPassword` URL, which resolves to the general Fortify route `/reset-password/{token}` instead of `vendor/reset-password/{token}` (`vendor.password.reset`). The vendor password-reset journey cannot be completed from the email. The same applies to the other custom account types. The codebase already tracks this as **ARCH-03** (see `tests/Feature/PasswordResetTokenIsolationTest.php`, `test_emailed_reset_link_is_still_the_known_arch_03_route`).

**Expected Behavior**
A vendor's reset email links to `vendor.password.reset` with the token and email, and submitting that form resets the password through the `vendors` broker only.

**SRS / Architecture Reference**
ARCH-03 (existing technical debt). SRS reference to be linked.

**Related Routes / Files**
- Routes: `vendor.password.email`, `vendor.password.reset`, `vendor.password.update`, Fortify `password.reset`
- `app/Http/Controllers/Auth/AccountPasswordResetLinkController.php`
- `app/Http/Controllers/Auth/AccountNewPasswordController.php`
- `app/Models/Vendor.php`
- `tests/Feature/PasswordResetTokenIsolationTest.php`

**Backend Guidance**
- Generate the link per account type (e.g. override `sendPasswordResetNotification` on the account models, or a per-guard URL generator) so each type uses its own reset route.
- Keep broker/token-table isolation exactly as is.
- Updating the ARCH-03 test is expected when this is fixed.

**Acceptance Criteria**
- The emailed link path for a vendor is `/vendor/reset-password/{token}` and carries the email.
- Following it and submitting a new password resets the vendor's password.
- A vendor token still cannot reset any other account type (existing isolation tests stay green).

**Required Tests**
- Notification `actionUrl` for a vendor points at `vendor.password.reset`.
- End-to-end: request link → open link → reset → login with new password.
- Existing cross-broker isolation tests.

**Resolution Notes**
**PASS (not CLOSED).** Verified by review: the root-cause fix, the automated full vendor reset journey, and the vendor-specific reset UI. The finding stays open as PASS rather than CLOSED only because manual end-to-end email delivery verification is still deferred until outbound mail is configured (see the last note).
- **Root cause:** Laravel's `ResetPassword` notification builds its link from the fixed route `password.reset` (Fortify, web `users` broker); nothing in the project overrode it.
- **Fix:** `AppServiceProvider::configurePasswordResetLinks()` registers `ResetPassword::createUrlUsing()`. A `Vendor` notifiable gets the named route `vendor.password.reset` with `token` and `email`; every other notifiable gets exactly the framework default, so their behavior is unchanged (tracked separately in ADM-BE-001). The notification class, broker and token tables are unchanged.
- **Tests:** `tests/Feature/VendorPasswordResetFlowTest.php` (8 tests): full journey request → link → form → reset → old password rejected → new password signs in on `vendor.login`; token single-use; invalid token; valid token with another email; vendor token rejected on admin/customer/delivery-driver endpoints and on Fortify's general route; customer token rejected on the vendor endpoint; other account types keep their current link. The full-journey test fails without the fix and passes with it.
- The existing ARCH-03 test (`test_emailed_reset_link_is_still_the_known_arch_03_route`) checks the customer link and still passes unchanged.
- Vendor-specific Reset Password UI has been implemented using `auth.vendor.reset-password` and passed visual review (see Approved Vendor UI).
- Manual end-to-end email delivery verification is deferred until outbound mail is configured. Automated tests currently verify the Vendor reset-link generation and complete reset flow.

---

### VEN-BE-003 — Forgot Password account enumeration

- **ID:** VEN-BE-003
- **Area:** Vendor Backend
- **Priority:** HIGH
- **Status:** OPEN
- **Discovered During:** Vendor Forgot Password Request UI.

**Current Behavior**
For an email with no vendor account, `AccountPasswordResetLinkController@store` returns the broker error `passwords.user` («لا يوجد مستخدم بهذا البريد الإلكتروني.») on the `email` field, while an existing account gets `passwords.sent`. This reveals whether a vendor account exists for any email.

*Additional observation (Vendor Reset Password UI review):* the reset POST (`vendor.password.update`, `AccountNewPasswordController@store`) can also return `passwords.user` in some cases (e.g. a submitted email with no vendor account). Any future account-enumeration review must cover the whole vendor password-recovery flow, not only the Forgot Password request. This note does not extend or fix this finding.

**Expected Behavior**
The same generic response is returned whether or not the account exists, while the reset link is still sent only to a real vendor account.

**SRS / Architecture Reference**
Security best practice (OWASP account-enumeration guidance). SRS reference to be linked.

**Related Routes / Files**
- Route: `vendor.password.email`
- `app/Http/Controllers/Auth/AccountPasswordResetLinkController.php`
- `lang/ar/passwords.php`
- `tests/Feature/VendorForgotPasswordPageTest.php` (`test_unknown_email_keeps_the_current_broker_error` documents the current behavior)

**Backend Guidance**
- Map `INVALID_USER` (and preferably `RESET_THROTTLED`) to the same neutral status message as `RESET_LINK_SENT`.
- Keep validation errors for a malformed email.
- The controller is shared with customer and delivery driver; decide whether the fix applies to all account types.

**Acceptance Criteria**
- Unknown email and existing email produce identical visible responses.
- Only the existing account receives a notification.
- Rate limiting is unchanged.

**Required Tests**
- Unknown email → generic status, no error on `email`, no notification sent.
- Existing email → same generic status, notification sent.
- Update the test that currently asserts `passwords.user`.

**Resolution Notes**
—

---

### VEN-BE-004 — Forgot Password rate limit uses generic 429 page

- **ID:** VEN-BE-004
- **Area:** Vendor Backend
- **Priority:** MEDIUM
- **Status:** OPEN
- **Discovered During:** Vendor Forgot Password Request UI.

**Current Behavior**
`vendor.password.email` is protected by `throttle:account-password-email` (5/min per IP, 3 per 15 min per account type + email). When exceeded, the framework renders the generic 429 error page instead of feedback inside the Vendor Auth experience.

**Expected Behavior**
When the limit is hit, the vendor stays in the Vendor Auth design and sees a clear, localized "try again later" message. The limits themselves are unchanged.

**SRS / Architecture Reference**
To be linked.

**Related Routes / Files**
- Route: `vendor.password.email`
- `app/Providers/AppServiceProvider.php` (`account-password-email` limiter)
- `bootstrap/app.php` (exception handling)
- `resources/views/auth/vendor/forgot-password.blade.php`

**Backend Guidance**
- Do **not** lower or remove the rate limits.
- Only change how a `ThrottleRequestsException` on this route is presented (e.g. redirect back with a localized error, or a branded 429 view).
- Keep the message generic so it does not reintroduce enumeration (see VEN-BE-003).

**Acceptance Criteria**
- Exceeding the limit shows a localized message in the vendor page design.
- Limits and their keys are identical to today.

**Required Tests**
- Exceeding the per-email limit returns the branded feedback, not the default 429 page.
- Existing `AuthRateLimitingTest` and `PasswordResetTokenIsolationTest` limits remain green.

**Resolution Notes**
—

---

### VEN-BE-005 — `store_name` is consumed without server-side validation

- **ID:** VEN-BE-005
- **Area:** Vendor Backend
- **Priority:** HIGH
- **Status:** OPEN
- **Discovered During:** Vendor Registration UI.

**Current Behavior**
`CreateAccountUser::create()` reads `$input['store_name'] ?? $input['full_name']` and uses it for `vendor_profiles.store_name` and the slug, but `store_name` is not in the validated rules. A direct request with an oversized value (> 255, the column length) or a malformed value (e.g. an array) can cause a 500 instead of a validation error. The field is currently hidden from the Vendor Registration UI.

**Expected Behavior**
If `store_name` is accepted at registration, it is validated server-side (e.g. `nullable|string|max:255`) and malformed input returns a validation error on `store_name`.

**SRS / Architecture Reference**
To be linked (see also VEN-BE-006).

**Related Routes / Files**
- Route: `vendor.register.store`
- `app/Actions/Auth/CreateAccountUser.php`
- `database/migrations/2026_09_24_130002_create_vendor_profiles_table.php`
- `resources/views/auth/vendor/register.blade.php`

**Backend Guidance**
- Add the rule in `CreateAccountUser` (the validation is shared with other account types; the rule should only matter for vendors).
- Decide whether `store_name` should be required for vendors (SRS).
- Only show the field in the UI after the rule and tests exist.

**Acceptance Criteria**
- Oversized or non-string `store_name` returns a 302 with an error on `store_name`, never a 500.
- Valid `store_name` is stored and used for the slug.
- Missing `store_name` behaves per the agreed SRS decision.

**Required Tests**
- `store_name` > 255 characters → validation error.
- `store_name[]` array → validation error.
- Valid `store_name` → stored on the profile with a unique slug.

**Resolution Notes**
—

---

### VEN-BE-006 — Vendor registration profile fields require SRS review

- **ID:** VEN-BE-006
- **Area:** Vendor Backend
- **Priority:** REVIEW
- **Status:** OPEN
- **Discovered During:** Vendor Registration UI.

**Current Behavior**
The registration contract collects only: `full_name`, `phone`, `email`, `password`, `password_confirmation`, `terms`. `vendor_profiles` also holds `governorate_id`, `city_id`, `address_line`, `short_description`, `description`, `logo`, `cover_image`, `commission_rate` and others, none of which are collected at registration.

**Expected Behavior**
A documented decision, based on the SRS/Architecture, on which vendor data is required at registration and which is completed later in store settings. It should not be assumed that all profile columns belong in registration.

**SRS / Architecture Reference**
SRS vendor registration requirements. Section to be linked.

**Related Routes / Files**
- Route: `vendor.register.store`
- `app/Actions/Auth/CreateAccountUser.php`
- `app/Models/VendorProfile.php`
- `resources/views/auth/vendor/register.blade.php`
- `app/Http/Controllers/VendorDashboard/ProfileController.php`

**Backend Guidance**
- Produce a field-by-field mapping: SRS requirement → registration / store settings / admin-only.
- Any field added to registration must come with server-side validation (see VEN-BE-005).

**Acceptance Criteria**
- Signed-off field mapping recorded in this finding's Resolution Notes.
- Follow-up findings opened for any gap that requires implementation.

**Required Tests**
None until the decision is made; implementation follow-ups define their own tests.

**Resolution Notes**
—

---

### VEN-BE-007 — Terms/Privacy destinations are not defined

- **ID:** VEN-BE-007
- **Area:** Vendor Backend
- **Priority:** REVIEW
- **Status:** OPEN
- **Discovered During:** Vendor Registration UI.

**Current Behavior**
Vendor registration requires accepting the terms and privacy policy (`terms` → `accepted`), but there are no defined legal destinations to link to, so the checkbox text is not linked.

**Expected Behavior**
Approved legal destinations (pages or documents) exist for the vendor terms and privacy policy, and the registration checkbox links to them.

**SRS / Architecture Reference**
To be linked.

**Related Routes / Files**
- Route: `vendor.register.store`
- `app/Actions/Auth/CreateAccountUser.php` (`terms` rule)
- `resources/views/auth/vendor/register.blade.php`

**Backend Guidance**
- Out of scope for engineering until the legal content and destinations are provided.
- Do not create pages or legal content under this finding.
- Consider whether acceptance should be recorded (timestamp/version) once destinations exist.

**Acceptance Criteria**
- Legal destinations are provided and approved.
- The registration checkbox links to them in both Arabic and English.

**Required Tests**
- Registration page renders the approved links.
- `terms` is still required.

**Resolution Notes**
—

---

### VEN-BE-008 — Vendor Auth translations require manual seeding

- **ID:** VEN-BE-008
- **Area:** Vendor Backend
- **Priority:** MEDIUM
- **Status:** OPEN
- **Discovered During:** Vendor Login UI.

**Current Behavior**
Vendor Auth pages read their strings via `t()` from `translation_values`, and their guest routes use `disableTranslationAutoCreate`, so missing keys fall back to the English defaults in the Blade views. The keys are added by `VendorAuthTranslationSeeder` (and shared ones by `AdminAuthTranslationSeeder`), which must currently be run manually on each environment; neither is called from `DatabaseSeeder`.

**Expected Behavior**
A reliable deployment/seeding strategy ensures every environment has the Vendor Auth keys, so new environments never show fallback English or untranslated keys on Arabic pages.

**SRS / Architecture Reference**
To be linked.

**Related Routes / Files**
- Routes: `vendor.login`, `vendor.register`, `vendor.password.request`
- `database/seeders/VendorAuthTranslationSeeder.php`
- `database/seeders/AdminAuthTranslationSeeder.php`
- `database/seeders/DatabaseSeeder.php`
- `app/helpers_locale.php` (`t()`)

**Backend Guidance**
- Options: call the seeders from `DatabaseSeeder`, run them from a migration, or add them to the deploy script.
- The seeders use `insertOrIgnore`, so re-running is safe and never overwrites admin-edited values.

**Acceptance Criteria**
- A fresh environment set up with the documented steps shows the Arabic Vendor Auth strings without any manual seeding command.
- Admin-edited translation values are never overwritten.

**Required Tests**
- Running the chosen setup path on an empty database produces all `VendorAuthTranslationSeeder::TRANSLATIONS` rows.
- Re-running it leaves existing edited values unchanged.

**Resolution Notes**
—

---

### VEN-BE-009 — English validation localization incomplete

- **ID:** VEN-BE-009
- **Area:** Vendor Backend
- **Priority:** LOW
- **Status:** DEFERRED
- **Discovered During:** Vendor Registration UI.

**Current Behavior**
The project has `lang/ar/validation.php` with attribute names (`full_name`, `phone`, …), but no project-level `lang/en/validation.php`. English validation messages therefore use Laravel's generic attribute names (e.g. "full name").

**Expected Behavior**
English validation messages use the same human-readable field names as the English UI labels.

**SRS / Architecture Reference**
To be linked.

**Related Routes / Files**
- Routes: `vendor.register.store`, `vendor.password.email`, `vendor.login.store`
- `lang/ar/validation.php`
- `lang/en/` (missing `validation.php`)

**Backend Guidance**
Deferred to Localization QA. Add `lang/en/validation.php` attributes matching the English UI labels.

**Acceptance Criteria**
- English validation errors on vendor auth forms use the agreed English field names.

**Required Tests**
- Registration validation in English shows the agreed attribute names.

**Resolution Notes**
Deferred to Localization QA.

---

### VEN-BE-010 — Vendor dashboard web route currently returns JSON

- **ID:** VEN-BE-010
- **Area:** Vendor Backend
- **Priority:** HIGH
- **Status:** PASS
- **Maturity:** IMPLEMENTED & VERIFIED — limited historical integration exception: the route split was completed by the Vendor Auth/Frontend workstream (VUI-01C) before Backend handoff, to finish the vendor authentication/approval flow. No Overview business logic was changed. It is still subject to Palgoals Backend Team review.
- **Discovered During:** Initial Admin/Vendor architecture audit; confirmed during Vendor Login UI.

**Current Behavior**
*(Before VUI-01C; see Resolution Notes.)* After a successful vendor login or registration the vendor is redirected to `vendor.dashboard` (`GET /vendor/dashboard`), which is handled by `VendorDashboard\OverviewController` and returns JSON, not a web dashboard view. All other `vendor.dashboard.*` routes are JSON endpoints as well.

**Expected Behavior**
`vendor.dashboard` renders the Vendor web dashboard. Existing JSON endpoints keep working and their backend/API contracts are not broken.

**SRS / Architecture Reference**
To be linked.

**Related Routes / Files**
- Routes: `vendor.dashboard`, `vendor.dashboard.*`
- `routes/web.php` (vendor account group)
- `app/Http/Controllers/VendorDashboard/OverviewController.php` and the other `VendorDashboard` controllers
- `tests/Feature/VendorDashboardBackendTest.php`
- Baseline failing tests that expect a view: `CustomerAccountDetailsPageTest`, `CustomerAddressesPageTest`, `CustomerDashboardPageTest`, `CustomerFavoritesPageTest`, `StorefrontCartPageTest`

**Backend Guidance**
- Handle when Vendor Dashboard work starts; do not change now.
- Decide the route split (web pages vs JSON endpoints) without changing existing JSON route behavior or names unless agreed.
- Coordinate with VEN-BE-001 (approval enforcement) for the same route group.

**Acceptance Criteria**
- After login, the vendor lands on an HTML dashboard page.
- Existing JSON endpoints return the same payloads as before.

**Required Tests**
- `GET vendor.dashboard` returns a view for an authenticated vendor.
- Existing `VendorDashboardBackendTest` stays green.
- Baseline tests expecting a vendor dashboard view are reconciled.

**Resolution Notes**
Implemented and verified in VUI-01C (Vendor Auth → Dashboard Integration), routing/integration only.
- **Canonical browser dashboard:** `GET /vendor/dashboard` (`vendor.dashboard`) renders the vendor dashboard shell (`resources/views/vendor-dashboard/home.blade.php`) as HTML. Middleware: `auth:vendor` + `vendor.approved` (group) + `setLocale` + `disableTranslationAutoCreate` (route).
- **Overview JSON moved, unchanged:** `GET /vendor/dashboard/overview` (`vendor.dashboard.overview`), same gate, still handled by `VendorDashboard\OverviewController`. The controller, its queries, calculations and payload keys were not modified (`vendor`, `stats` with its nine keys, `recent_orders`, `low_stock_products`).
- **Auth flow:** approved vendor login redirects to `vendor.dashboard` and lands on the HTML dashboard. Pending and rejected vendors (login or registration, which creates `active` + `pending`) are still gated: browser requests go to `vendor.approval-status`, JSON requests get the existing `403`. Account-status behavior is unchanged. No application redirect code changed; the existing `dashboard_route` destination now resolves to the HTML page.
- **Temporary route removed:** the VUI-01B browser route `/vendor/panel` (`vendor.panel.home`) no longer exists; the shell navigation points to `vendor.dashboard`.
- **Tests:** `VendorDashboardShellTest` (29) covers the route split, the HTML response, the unchanged overview payload, approved/pending/rejected login, registration, the status-page redirect, Arabic/English switching on `/vendor/dashboard`, and the removed `/vendor/panel`. JSON consumers in `VendorApprovalEnforcementTest` and `StockReservationLifecycleTest` now call `vendor.dashboard.overview`; the gated-route structure test and the `disableTranslationAutoCreate` route list were updated for the new route set. Full suite: 492 passed with the same 12 baseline failures.
- **Not reconciled here:** the five baseline tests that expect the generic `accounts.dashboard` view from `vendor.dashboard` with a profile-less vendor still fail as before (approval gate). They are baseline failures and were not changed.

*Final review — **PASS** (not CLOSED):* VEN-BE-010 is implemented and verified by VUI-01C.
- `/vendor/dashboard` is the canonical vendor browser dashboard UI.
- `/vendor/dashboard/overview` preserves the existing Overview JSON contract.
- Approved vendor login reaches the dashboard UI; pending and rejected vendors remain protected by `vendor.approved`.
- The temporary `/vendor/panel` route was removed.
- Focused and relevant regression coverage passed, with no new regression.
- **Baseline test-suite reconciliation debt (separate from this finding):** `CustomerAccountDetailsPageTest`, `CustomerAddressesPageTest`, `CustomerDashboardPageTest`, `CustomerFavoritesPageTest` and `StorefrontCartPageTest` carry pre-existing, stale expectations that `vendor.dashboard` returns the generic `accounts.dashboard` view for a profile-less vendor. These expectations predate the approval gate (VEN-BE-001) and the vendor dashboard UI, so they do not reflect the approved contract. They are tracked as baseline test-suite debt to reconcile separately and do not keep VEN-BE-010 open. The "Baseline tests expecting a vendor dashboard view are reconciled" item under Required Tests is therefore carried as that separate debt, not as an implementation defect.

---

### VEN-BE-011 — Password-reset email expiry text uses default broker configuration

- **ID:** VEN-BE-011
- **Area:** Vendor Backend
- **Priority:** MEDIUM
- **Status:** OPEN
- **Discovered During:** VEN-BE-002 fix.

**Current Behavior**
The `ResetPassword` notification's mail text ("This password reset link will expire in :count minutes") reads the expiry from `auth.passwords.{auth.defaults.passwords}.expire`, i.e. the default `users` broker, not the broker that issued the token. A vendor token is issued by the `vendors` broker. Both are currently 60 minutes, so the text is correct in practice, but it becomes wrong as soon as the vendor expiry differs from the default.

**Expected Behavior**
The expiry shown in the email always matches the expiry of the broker that issued the token.

**SRS / Architecture Reference**
To be linked.

**Related Routes / Files**
- Route: `vendor.password.email`
- `vendor/laravel/framework/src/Illuminate/Auth/Notifications/ResetPassword.php` (`buildMailMessage`)
- `config/auth.php` (`passwords.vendors.expire`, `defaults.passwords`)
- `app/Providers/AppServiceProvider.php` (`configurePasswordResetLinks`)

**Backend Guidance**
- Do not change the expiry policy itself in this finding.
- Resolve the displayed value from the notifiable's broker (e.g. through `ResetPassword::toMailUsing` or a per-account notification), keeping the link behavior from VEN-BE-002.
- Consider fixing this together with ADM-BE-001, since both touch how reset emails are built per account type.

**Acceptance Criteria**
- With `passwords.vendors.expire` set to a value different from the default broker, the vendor email shows the vendor value.
- The vendor reset link and token isolation are unchanged.

**Required Tests**
- Vendor email expiry text follows `passwords.vendors.expire` when it differs from the default broker.
- Other account types show their own broker's expiry (or keep current behavior if out of scope).
- `VendorPasswordResetFlowTest` stays green.

**Resolution Notes**
—

---

### VEN-BE-012 — Password reset broker errors are attached to the email field

- **ID:** VEN-BE-012
- **Area:** Vendor Backend
- **Priority:** LOW
- **Status:** OPEN
- **Discovered During:** Vendor Reset Password UI.

**Current Behavior**
When a reset fails in the broker (e.g. invalid or expired token), `AccountNewPasswordController@store` returns the broker status as an error on `email` (`back()->withErrors(['email' => __($status)])`). The reset page therefore shows a message such as «رمز إعادة تعيين كلمة المرور غير صالح» as an error of the email field, even when the email itself is correct.

**Expected Behavior**
The error mapping lets the UI distinguish token/reset-session errors from email validation errors, without revealing sensitive information and without changing token validation or security behavior.

**SRS / Architecture Reference**
To be linked.

**Related Routes / Files**
- Routes: `vendor.password.reset`, `vendor.password.update`
- `app/Http/Controllers/Auth/AccountNewPasswordController.php` (`store`)
- `resources/views/auth/vendor/reset-password.blade.php`
- `lang/ar/passwords.php`

**Backend Guidance**
- Review later together with VEN-BE-003, so that any new error key does not reveal whether an account exists.
- Do not change token validation, expiry or broker behavior.
- The controller is shared with customer and delivery driver; decide the scope before changing it.

**Acceptance Criteria**
- An invalid or expired token produces an error the UI can show as a reset-link problem, not as an email-field error.
- Email validation errors remain on `email`.
- No new information about account existence is exposed.

**Required Tests**
- Invalid token → error is not attached to `email`; the reset page renders it as a link/session error.
- Malformed email → error stays on `email`.
- `VendorPasswordResetFlowTest` and `VendorResetPasswordPageTest` stay green (updated for the new mapping).

**Resolution Notes**
—

---

### VEN-BE-013 — Registration and password-reset password policies can diverge

- **ID:** VEN-BE-013
- **Area:** Vendor Backend
- **Priority:** MEDIUM
- **Status:** OPEN
- **Discovered During:** Vendor Reset Password UI.

**Current Behavior**
Vendor registration validates the password with `Password::default()` (via `PasswordValidationRules`), while the vendor password reset validates it with `min:8`. Today both resolve to the same practical rule (at least 8 characters), but if the global/default password policy changes, reset could accept a password that registration rejects, or the reverse.

**Expected Behavior**
One canonical password policy for accounts, applied consistently at registration, password reset and any other password change.

**SRS / Architecture Reference**
To be linked.

**Related Routes / Files**
- Routes: `vendor.register.store`, `vendor.password.update`
- `app/Actions/Auth/CreateAccountUser.php`
- `app/Actions/Fortify/PasswordValidationRules.php`
- `app/Http/Controllers/Auth/AccountNewPasswordController.php`
- `resources/views/auth/vendor/register.blade.php`, `resources/views/auth/vendor/reset-password.blade.php` (password hint text)

**Backend Guidance**
- Do not change the policy itself under this finding; first agree on the canonical policy.
- Then use the same rule source everywhere (e.g. `PasswordValidationRules`/`Password::default()` in the reset controller too).
- Keep the UI hint text in sync with the agreed policy.

**Acceptance Criteria**
- Registration and reset reject and accept exactly the same passwords.
- Changing the default policy in one place changes both flows.

**Required Tests**
- A password that fails the canonical policy is rejected by both registration and reset.
- A password that passes it is accepted by both.

**Resolution Notes**
—

---

### VEN-BE-014 — Storefront does not enforce Vendor approval

- **ID:** VEN-BE-014
- **Area:** Vendor Backend
- **Priority:** CRITICAL
- **Status:** OPEN
- **Discovered During:** Vendor Post-Login & Approval Flow Audit.

**Current Behavior**
The storefront does not consistently require `vendor_profiles.approval_status=approved`. A pending or rejected vendor's products can therefore reach customers: the audit showed that such a product can be listed, added to the cart, pass through checkout and produce a `VendorOrder` for that vendor.
- `ProductCatalogController::index` filters on `vendor.status=active` only, not approval.
- `ProductCatalogController::show` checks only `product.status`.
- `CartController@store` checks only `product.status=active`.
- `CheckoutController` groups cart items by `vendor_id` and creates vendor orders and stock reservations without a vendor eligibility check.

Only `ProductCatalogController::vendors` (the public vendors list) filters on approval.

*Expanded (VEN-BE-019 Inventory Lifecycle Contract Audit):* checkout also does not revalidate the product's own sellable state. A product already in a cart can still be purchased after it becomes `inactive` (or otherwise non-sellable), because `CheckoutController` rechecks stock availability only. The canonical sellability rule must therefore cover product state as well as vendor eligibility. Stale cart prices are tracked separately in VEN-BE-030.

**Expected Behavior**
Sellability requires at least a valid vendor account and `approval_status=approved`, and a product in a sellable state, enforced independently in Catalog, Product Detail, Cart and Checkout, not by relying on Dashboard middleware.

**SRS / Architecture Reference**
Backend Scenario Review (`docs/New Microsoft Word Document (2).docx`, section 2): the admin reviews and approves/rejects the vendor before activity starts. Approved Vendor Approval Contract: storefront sellability must require an eligible, approved vendor. Related: VEN-BE-001 (tracked separately because this is a customer-facing security/business exposure).

**Related Routes / Files**
- Routes: `shop.products.index`, `shop.products.show`, `customer.cart.items.store`, `customer.cart.items.update`, `customer.checkout.store`
- `app/Http/Controllers/Store/ProductCatalogController.php`
- `app/Http/Controllers/Store/CartController.php`
- `app/Http/Controllers/Store/CheckoutController.php`
- `app/Models/Product.php`, `app/Models/Vendor.php`, `app/Models/VendorProfile.php`

**Backend Guidance**
- Define one canonical sellability rule (e.g. a query scope) and apply it in every storefront path, including re-validation at checkout for items already in a cart.
- Do not rely on the vendor dashboard gate (VEN-BE-001) to protect the storefront.
- Decide how existing cart items from ineligible vendors are handled at checkout.
- Coordinate with VEN-BE-015 so account status and approval are enforced by the same rule.

**Acceptance Criteria**
- Products of pending or rejected vendors are not listed, not viewable on product detail, cannot be added to a cart and cannot be checked out.
- A cart item whose vendor becomes ineligible is rejected at checkout and no `VendorOrder` or stock reservation is created for it.
- A cart item whose product is no longer sellable (e.g. `inactive`) is rejected at checkout.
- Approved, active vendors behave exactly as today.

**Required Tests**
- Pending vendor product: absent from catalog, 404 on detail, rejected by cart add, rejected at checkout.
- Rejected vendor product: same as pending.
- Vendor becomes ineligible after the item is in the cart: checkout refuses it.
- Product becomes `inactive` after it is in the cart: checkout refuses it.
- Approved, active vendor: existing storefront and checkout tests stay green.

**Resolution Notes**
—

---

### VEN-BE-015 — Storefront product access does not consistently enforce Vendor account status

- **ID:** VEN-BE-015
- **Area:** Vendor Backend
- **Priority:** HIGH
- **Status:** OPEN
- **Discovered During:** Vendor Post-Login & Approval Flow Audit.

**Current Behavior**
`ProductCatalogController::index` checks `vendor.status=active`, but `ProductCatalogController::show` and the cart paths do not check `vendor.status` at all. An active product of a blocked vendor can therefore remain viewable and purchasable.

**Expected Behavior**
A canonical sellability rule is used across all storefront purchase paths, so a product of a vendor whose account is not active is never viewable or purchasable.

**SRS / Architecture Reference**
Approved Vendor Approval Contract (account status takes precedence over approval status). To be linked to the SRS.

**Related Routes / Files**
- Routes: `shop.products.show`, `customer.cart.items.store`, `customer.cart.items.update`, `customer.checkout.store`
- `app/Http/Controllers/Store/ProductCatalogController.php`
- `app/Http/Controllers/Store/CartController.php`
- `app/Http/Controllers/Store/CheckoutController.php`

**Backend Guidance**
- Use the same canonical sellability rule as VEN-BE-014 (account status + approval) rather than separate checks per controller.

**Acceptance Criteria**
- A blocked or pending-account vendor's products are not viewable on detail, cannot be added to a cart and cannot be checked out.
- Catalog listing behavior stays consistent with detail, cart and checkout.

**Required Tests**
- Blocked vendor active product: 404 on detail, rejected by cart add, rejected at checkout.
- Pending-account vendor: same.

**Resolution Notes**
—

---

### VEN-BE-016 — Vendor profile update validation is inconsistent with registration identity rules

- **ID:** VEN-BE-016
- **Area:** Vendor Backend
- **Priority:** HIGH
- **Status:** OPEN
- **Discovered During:** Vendor Post-Login & Approval Flow Audit; expanded during the Vendor Dashboard & Navigation Audit.

**Current Behavior**
Registration (`CreateAccountUser`) checks `email` and `phone` uniqueness across all account types (users, admins, customers, vendors, delivery drivers) and limits `phone` to 30 characters. Vendor profile update (`UpdateProfileRequest`) checks uniqueness within `vendors` only and allows `phone` up to 255 characters. Profile update can therefore store identity data that registration would reject, e.g. an email already used by an admin or customer account.

*Expanded (Vendor Dashboard & Navigation Audit):* the same profile-update validation also diverges from the stored data and the recovery contract:
- **`short_description` length mismatch:** `vendor_profiles.short_description` is a `string` (255) column, but `UpdateProfileRequest` allows up to 1000 characters. A value between 256 and 1000 characters passes validation and can fail at the database (500 on MySQL strict mode) instead of returning a validation error.
- **Recovery email can be removed:** registration requires `email`, but profile update accepts `email = null` and `ProfileController` stores `null`. The implemented vendor password recovery is email-only (VEN-BE-002), so a vendor can leave the account without the identifier the recovery flow needs.

**Expected Behavior**
One canonical identity validation contract applies to every create and update flow for account email and phone. Profile-update rules never accept values the database cannot store, and never let the account lose the identifier required by the implemented recovery flow (unless an approved contract changes recovery).

**SRS / Architecture Reference**
UC-VEN-003 — إدارة الملف الشخصي وبيانات المتجر (FR-VEN-003 personal profile data, FR-VEN-004 store data), HIRFAH SRS v0.5 (reviewed outside the repository). Exact validation rules to be linked.

**Related Routes / Files**
- Routes: `vendor.register.store`, `vendor.dashboard.profile.update`
- `app/Actions/Auth/CreateAccountUser.php`
- `app/Http/Requests/VendorDashboard/UpdateProfileRequest.php`
- `app/Http/Controllers/VendorDashboard/ProfileController.php`
- `app/Http/Controllers/Dashboard/VendorManagementController.php` (admin create uses its own cross-table rule)

**Backend Guidance**
- Extract the identity rules (cross-account uniqueness, formats, lengths) into one reusable source and use it in registration, admin create and profile update.
- Note: under the Approved Vendor Approval Contract, profile editing is not available while pending; this finding applies to the update flow wherever it remains available.

**Acceptance Criteria**
- Profile update rejects an email or phone already used by any account type (except the vendor's own).
- Phone length and format rules are identical in registration and update.
- `short_description` validation matches the column length; oversized input returns a validation error, never a 500.
- Profile update cannot remove the email while recovery is email-only.

**Required Tests**
- Update with an email belonging to an admin/customer/driver → validation error.
- Update with a phone longer than the registration limit → validation error.
- Update keeping the vendor's own email/phone → succeeds.
- Update with a `short_description` longer than the column → validation error, no database exception.
- Update with an empty/`null` email → validation error.

**Resolution Notes**
—

---

### VEN-BE-017 — Vendor approval endpoints lack dedicated regression/security tests

- **ID:** VEN-BE-017
- **Area:** Vendor Backend
- **Priority:** MEDIUM
- **Status:** OPEN
- **Discovered During:** Vendor Post-Login & Approval Flow Audit.

**Current Behavior**
There are no direct tests for the admin `approve` / `reject` endpoints or for the effect of the decision on vendor access and operations. `tests/Feature/Dashboard/VendorManagementTest.php` covers vendor creation only.

**Expected Behavior**
After approval enforcement is implemented, a dedicated test suite covers the approval lifecycle end to end.

**SRS / Architecture Reference**
Approved Vendor Approval Contract.

**Related Routes / Files**
- Routes: `dashboard.vendors.approve`, `dashboard.vendors.reject`, `vendor.dashboard*`, storefront purchase routes
- `app/Http/Controllers/Dashboard/VendorManagementController.php`
- `tests/Feature/Dashboard/VendorManagementTest.php`
- `tests/Feature/VendorDashboardBackendTest.php`

**Backend Guidance**
Write the tests together with the VEN-BE-001 / VEN-BE-014 / ADM-BE-003 implementation, against the Approved Vendor Approval Contract.

**Acceptance Criteria**
Tests exist and pass for state transitions, the access gate, cross-state behavior and storefront sellability.

**Required Tests**
- Approve / reject authorization (ability `vendors.edit`, super admin, forbidden otherwise).
- State transitions allowed and refused per the agreed lifecycle.
- Access gate for every `vendors.status` × `approval_status` combination in the contract.
- Storefront sellability for pending, approved and rejected vendors.

**Resolution Notes**
—

---

### VEN-BE-018 — Commission and financial lifecycle is not implemented

- **ID:** VEN-BE-018
- **Area:** Vendor Backend
- **Priority:** HIGH
- **Status:** OPEN
- **Discovered During:** Vendor Dashboard & Navigation Audit.

**Current Behavior**
No `Commission` records are created by the normal marketplace flow: nothing in the application creates them. `CommissionRule` exists but is not used by any active calculation. Vendor orders created at checkout keep `commission_amount = 0` (only the Admin manual "create vendor order" form sets it). As a result, `vendor.dashboard.commissions.index` has no meaningful operational data and the dashboard `commission_pending` is always `0`.

**Expected Behavior**
The marketplace flow produces the commission data the vendor's financial performance depends on, according to an approved commission formula and lifecycle.

**SRS / Architecture Reference**
UC-VEN-007 — متابعة الأداء المالي للمتجر (FR-VEN-016, FR-VEN-017, FR-VEN-018), HIRFAH SRS v0.5 (reviewed outside the repository). Admin commission requirements/business rules to be cross-referenced once linked; none are recorded in this document yet.

**Related Routes / Files**
- Routes: `vendor.dashboard.commissions.index`, `vendor.dashboard`, `customer.checkout.store`, `dashboard.vendor-orders.store`
- `app/Models/Commission.php`, `app/Models/CommissionRule.php`, `app/Models/VendorOrder.php` (`commission_amount`)
- `app/Http/Controllers/Store/CheckoutController.php`
- `app/Http/Controllers/VendorDashboard/CommissionController.php`, `OverviewController.php`
- `app/Http/Controllers/Dashboard/VendorOrderManagementController.php`
- `vendor_profiles.commission_rate`, `commission_rules`, `commissions` migrations

**Backend Guidance**
- Record the gap only; do not invent the commission formula or lifecycle.
- First agree on the source of truth (`commission_rules`, `vendor_profiles.commission_rate`, `vendor_orders.commission_amount`, `commissions`), the base amount, and when a commission is created and changes status (`pending`, `earned`, `waived`, `paid`).
- Coordinate with VEN-BE-020 (order completion) and VEN-BE-022 (sales definition).

**Acceptance Criteria**
- The normal order flow produces commission data according to the approved contract.
- `commissions.index` and the dashboard commission figures reflect that data.

**Required Tests**
- An order going through the approved lifecycle produces the expected commission record/amount.
- Commission status transitions follow the approved contract.
- Vendor commission endpoints are scoped to the vendor.

**Resolution Notes**
—

---

### VEN-BE-019 — Inventory commitment lifecycle is not implemented

- **ID:** VEN-BE-019
- **Area:** Vendor Backend
- **Priority:** HIGH
- **Status:** PASS
- **Maturity:** IMPLEMENTED & VERIFIED — historical exception: the Backend change was made in the current working tree during earlier review work, before the ownership boundary was clarified (see Review Scope & Implementation Ownership). It is still subject to Palgoals Backend Team review.
- **Discovered During:** Vendor Dashboard & Navigation Audit.

**Current Behavior**
Checkout creates `StockReservation` rows (`reserved`) inside a transaction that locks each product and rechecks availability, and vendor rejection releases them (`released`). Confirmed related defects (expanded by the VEN-BE-019 Inventory Lifecycle Contract Audit):
- **Reservations never become `committed`:** no production path sets `committed` / `committed_at`; the state exists only in the schema and model.
- **`stock_quantity` never decrements:** no code path deducts physical stock. In practice an outstanding `reserved` reservation acts as a permanent deduction through the available-stock formula, while `stock_quantity` keeps the value the vendor entered (so a vendor who corrects `stock_quantity` to the real count double-counts sold units).
- **Stock can be set below the reserved quantity:** vendor product create/update validates `stock_quantity` as `integer|min:0` only; lowering it below the outstanding reserved quantity is accepted and available stock is silently clamped to 0.
- **Dashboard low stock uses raw stock:** `products_low_stock` and `low_stock_products` compare `stock_quantity` with `low_stock_threshold` and ignore reservations, although `Product::availableStockQuantity()` exists.
- **No release on Admin cancellation or payment failure:** setting an order to `cancelled` / `partially_cancelled` or its payment to `failed` does not release its reservations. The exact remediation depends on the later order/payment lifecycle contract (see VEN-BE-020); not every cancellation/payment-failure behavior is decided yet.

**Expected Behavior**
Stock follows the approved stock contract below: checkout reserves, vendor reject releases, vendor accept commits and decrements physical stock exactly once, and vendor-facing stock indicators use available stock.

**Approved stock contract (decision recorded in the VEN-BE-019 Stock Contract Decision round)**

Stock reservation is committed at **Vendor Accept**.

Canonical lifecycle:
- Checkout → `reserved`
- Vendor Reject while `pending` → `released`
- Vendor Accept → `committed` + decrement `products.stock_quantity`

Rules:
- The checkout reservation prevents overselling but is not a final stock deduction.
- `stock_quantity` represents physical/current stock.
- While a reservation is `reserved`, available stock is `max(0, stock_quantity − reserved quantity)`.
- At a successful Vendor Accept, inside the same transactional operation:
  1. lock the relevant product row(s);
  2. validate that the reservation is still `reserved`;
  3. decrement `stock_quantity` exactly once by the reserved quantity;
  4. change the reservation `reserved → committed`;
  5. set `committed_at`.
- This operation must be atomic; a repeated Accept must not deduct stock twice.
- Vendor Reject before Accept: `reserved → released`, set `released_at`, and **do not** increment `stock_quantity` (it was never decremented).
- A vendor must not be able to reduce `stock_quantity` below the quantity currently reserved for that product.
- Vendor Dashboard low stock is based on available stock, not raw physical stock: `available_stock <= low_stock_threshold`.
- Where one operation needs several product locks, acquire them deterministically, ordered by `product_id`.

**Explicitly deferred (not in VEN-BE-019 implementation scope yet)**
- Reservation expiry.
- Automatic action after the 24-hour vendor deadline (see VEN-BE-027).
- Restoration after a cancellation that happens after commitment.
- Restoration after failed delivery.
- Return/restock behavior (no return-policy finding or contract exists yet; the Backend Scenario Review, section 10, defers the return policy).
- Automatic `out_of_stock` status.
- Final handling of committed reservations at order completion.

These depend on VEN-BE-020 (order lifecycle) and VEN-BE-027 (vendor response deadline).

**SRS / Architecture Reference**
UC-VEN-004 — إدارة المنتجات والمخزون, FR-VEN-008 (vendor manages inventory levels), HIRFAH SRS v0.5 (reviewed outside the repository). Backend Scenario Review (`docs/New Microsoft Word Document (2).docx`, section 5): checkout is not a final deduction; reservations are released on vendor rejection/cancellation; the exact point where a reservation becomes a final deduction must be consistent with the vendor order lifecycle. The commit point (Vendor Accept) is the approved project decision recorded above.

**Related Routes / Files**
- Routes: `customer.checkout.store`, `vendor.dashboard.orders.accept`, `vendor.dashboard.orders.reject`, `vendor.dashboard`, `vendor.dashboard.products.*`, `dashboard.orders.update-status`, `dashboard.orders.update-payment-status`
- `app/Models/StockReservation.php`, `app/Models/Product.php` (`reservedStockQuantity`, `availableStockQuantity`)
- `app/Http/Controllers/Store/CheckoutController.php`
- `app/Http/Controllers/VendorDashboard/OrderController.php` (`accept`, `reject`)
- `app/Http/Controllers/VendorDashboard/ProductController.php`, `app/Http/Requests/VendorDashboard/StoreProductRequest.php`, `UpdateProductRequest.php`
- `app/Http/Controllers/VendorDashboard/OverviewController.php` (low-stock queries)
- `app/Http/Controllers/Dashboard/OrderManagementController.php`

**Backend Guidance**
- Implement the approved contract above; do not implement the deferred items.
- Keep accept/commit in one transaction with deterministic product locking.
- For Admin cancellation / payment failure, only release what the agreed order/payment lifecycle defines; record any undecided case rather than inventing it.

**Acceptance Criteria**
The future implementation must prove that:
- Checkout creates `reserved` stock without decrementing physical stock.
- Available stock subtracts reserved quantities.
- Two successful checkouts cannot reserve more than the available stock.
- Vendor Reject releases the reservation without changing physical stock.
- Vendor Accept commits the reservation and decrements physical stock exactly once.
- A repeated or invalid Accept cannot double-deduct.
- A vendor cannot lower physical stock below the outstanding reserved quantity.
- Vendor Dashboard low-stock calculations use available stock.
- `committed` and `released` reservations are not counted as outstanding reservations.
- All operations preserve vendor/product ownership boundaries.

**Required Tests**
- Checkout → `reserved`, `stock_quantity` unchanged, available stock reduced.
- Concurrent/successive checkouts cannot over-reserve the last units.
- Reject → `released` + `released_at`, `stock_quantity` unchanged.
- Accept → `committed` + `committed_at`, `stock_quantity` decremented once; repeated/invalid Accept leaves stock unchanged.
- Vendor stock update below the outstanding reserved quantity → validation error.
- Low-stock metrics follow `available_stock <= low_stock_threshold`.
- `committed`/`released` reservations are excluded from available-stock subtraction.
- Cross-vendor access to products/orders stays denied.

**Resolution Notes**
Implemented per the approved stock contract; **READY FOR REVIEW** (not PASS).
- **Service:** `App\Support\Inventory\StockReservationLifecycle` (narrow stock responsibility) with `commitForVendorOrder()` and `releaseForVendorOrder()`; failures throw `StockCommitmentException`. Both run inside the caller's transaction.
- **Accept** (`VendorDashboard\OrderController@accept`): one `DB::transaction` → lock the vendor order row and re-check `pending` → collect the order's reservation product ids → lock those products in ascending `product_id` → lock the reservations → validate all are `reserved`, every product belongs to the order's vendor, and physical stock covers the reserved quantity per product → decrement each product's `stock_quantity` once → `reserved → committed` + `committed_at` (affected-row count verified) → order `pending → accepted`. Any failure throws and the whole transaction rolls back (`422`). A repeated Accept sees the order is no longer `pending` and stops before any stock change. Vendor orders without reservations (manual Admin orders) are accepted without stock changes.
- **Reject** (`@reject`): one transaction → lock the vendor order and re-check `pending` → order `rejected` → `reserved → released` + `released_at`; `stock_quantity` is never incremented.
- **Stock edit:** `UpdateProductRequest` refuses a `stock_quantity` below the product's outstanding reserved quantity with a normal validation error (no clamping). Only checked for the vendor's own product, so another vendor's reservations are never revealed (the controller still answers 404). Product creation keeps `integer|min:0`.
- **Low stock:** `Product::scopeLowAvailableStock()` / `scopeOrderByAvailableStock()` implement `available_stock <= low_stock_threshold` with `available = max(0, stock − reserved)`, written as `stock <= threshold + reserved` so the unsigned column is never subtracted from on MySQL. Used by the vendor dashboard `products_low_stock` and `low_stock_products`; the Admin dashboard is unchanged.
- **Checkout:** locks all cart products up front in ascending `product_id` order (removing the inconsistent lock ordering); the existing transaction and per-item availability recheck are unchanged.
- **Schema:** no migration; the existing `reserved` / `released` / `committed` statuses and timestamps were sufficient.
- **Tests:** `tests/Feature/StockReservationLifecycleTest.php` (22 tests) covers checkout reservation and linkage, over-reservation refusal, cart add/update limits, reject release without restoration, repeated/invalid reject, accept commit with a single deduction, multi-product atomic commit, repeated accept, rollback on a non-reserved reservation and on insufficient physical stock, cross-vendor protection (route and service level), the stock-edit rule (below / equal / above / released / committed / foreign product), available-stock calculation and its floor, and dashboard low-stock count, list, ordering and boundary.
- **Verification:** focused suite 22/22; full suite 457 passed with the same 12 baseline failures, no new failure. The low-stock scopes were also executed read-only against the local MySQL connection without SQL errors (no local products, so data-dependent cases were exercised on SQLite only).
- **Limitation:** tests run on SQLite, where `lockForUpdate` is a no-op; they prove the logical invariants, not real row-lock concurrency (tracked in VEN-BE-029).
- **Still deferred (unchanged):** the items listed under "Explicitly deferred" above, plus release on Admin cancellation / payment failure.

*Final correctness fix (after code verification):* two defects inside the approved contract were fixed; status stays **READY FOR REVIEW**.
- **Stock-update race:** the FormRequest check ran before the write, so a reservation committed in between could leave stock below the reserved quantity. `ProductController@update` now writes inside `DB::transaction`: lock the product (`lockForUpdate`, product first, as in checkout and accept), re-check vendor ownership (404), re-read the outstanding `reserved` quantity under the lock, throw a `ValidationException` on `stock_quantity` if the new value is lower, otherwise update. The FormRequest rule stays for early feedback; both share one message.
- **Accept completeness:** Accept now proves a one-to-one match between the vendor order's stock-backed items (non-null `product_id`) and its reservations: each item has exactly one reservation of the same vendor order with that `order_item_id` and `product_id`, in `reserved` status, and no reservation is left unmatched. Otherwise `StockCommitmentException` → full rollback, `422`. Products of both items and reservations are locked.
- **Header-only orders** (no stock-backed items and no reservations, as the Admin form creates) are still accepted without stock changes, for compatibility with existing behavior only; this is not an approved business contract. Items with `product_id = null` are excluded from completeness; this does not define their wider commercial meaning.
- **Tests:** `StockReservationLifecycleTest` now has 28 tests: write-boundary recheck (a reservation is created after FormRequest validation passed, the update is refused with `422` on `stock_quantity` and stock is unchanged; on SQLite this proves the recheck logic, not MySQL row locking), the four completeness failures (no reservation; one of two missing; wrong `order_item_id`; wrong `product_id`) with full database state checks, the header-only order with database checks, the ownership check with a complete item/reservation pair, and a rollback after mutation (a SQLite test-only trigger fails the reservation commit after the decrements; order, stock and reservations are all unchanged). Removing either fix makes its tests fail.
- **Verification:** focused 28/28; related 127 passed; full suite 463 passed with the same 12 baseline failures, no new failure.

*Final review — **PASS** (not CLOSED):* the approved inventory commitment contract is implemented and verified.
- Checkout creates `reserved` reservations without decrementing physical stock; available stock subtracts only outstanding `reserved` quantities.
- Vendor Reject performs `reserved → released` without restoring physical stock.
- Vendor Accept is the stock commitment point and atomically performs: vendor order locking and state validation, deterministic product locking, reservation locking, order item/reservation completeness validation, vendor/product ownership validation, physical-stock validation, a single stock decrement, `reserved → committed` with `committed_at`, and `pending → accepted`. Missing or incorrect reservations cause a complete rollback; a repeated Accept cannot double-deduct.
- Header-only vendor orders are still accepted without stock changes only for compatibility with the existing Admin behavior; this is not a newly approved business contract.
- Vendor stock update rechecks the outstanding reserved quantity at the write boundary, inside a transaction, after locking the product; a vendor cannot reduce physical stock below the outstanding reserved quantity.
- The vendor dashboard low-stock figures use available stock; checkout product locks use deterministic `product_id` ordering; no schema migration was required.
- **Final verification:** `StockReservationLifecycleTest` 28 passed / 144 assertions; related regression 127 passed / 0 failed; full suite 463 passed with the 12 known baseline failures; no new regression; `git diff --check` clean. The final correctness review also verified that the product-update concurrency invariant is protected at the write boundary, that stock-backed order items require exactly one matching reservation, and that rollback was tested after stock mutation had already begun.
- **Not covered by this PASS:** real MySQL/InnoDB concurrent row-lock behavior (VEN-BE-029 stays OPEN), and every item under "Explicitly deferred" above, plus release on Admin cancellation / payment failure (see VEN-BE-020, VEN-BE-027, VEN-BE-014, VEN-BE-030).

*Related audit candidates (not findings):*
- Reservation expiry/cleanup is not implemented (no `expires_at`, no scheduler); tied to VEN-BE-027 and deferred above.
- Checkout locks products in cart order, so two checkouts could deadlock; expected to be addressed by the deterministic `product_id` lock ordering above.
- `stock_reservations` has no `(product_id, status)` index for the availability sum and no uniqueness on `order_item_id`; implementation-quality consideration.
- The storefront catalog does not reflect availability and `out_of_stock` is not linked to stock; needs a storefront contract review.
- Guest-cart merge does not check availability; checkout revalidates availability later, so assess separately before treating it as a defect.

---

### VEN-BE-020 — Vendor order lifecycle cannot complete through the normal flow

- **ID:** VEN-BE-020
- **Area:** Vendor Backend
- **Priority:** HIGH
- **Status:** OPEN
- **Maturity:** BACKEND FINDING — PALGOALS TEAM; Parts A–E below are a **PROPOSED CONTRACT — TEAM REVIEW REQUIRED**. Nothing in Parts A–E has been implemented by the review workstream.
- **Discovered During:** Vendor Dashboard & Navigation Audit.

**Current Behavior**
Vendor actions support `pending → accepted`, `pending → rejected` (with reason), `accepted → preparing` and `accepted/preparing → ready_for_delivery`. Admin can then assign a driver, which moves the vendor order to `assigned`. No normal actor or system path completes `assigned → out_for_delivery → delivered → completed` (only the Admin manual "create vendor order" form can set those statuses directly). Consequences:
- the vendor cannot follow an order through delivery on a complete operational lifecycle;
- completed-order metrics are unreachable through the normal flow;
- sales metrics that depend on `delivered`/`completed` (VEN-BE-022) are affected, and customer reviews (which require `completed`) cannot be created through the normal flow.

*Expanded (VEN-BE-020 Vendor Order Lifecycle Contract Audit):*
- The delivery driver area has authentication and a placeholder dashboard only; there are no driver routes to see assignments, confirm pickup or confirm delivery. `DeliveryAssignment` never leaves `assigned` (`picked_up_at` / `delivered_at` are never set).
- `vendor_orders.customer_receipt_confirmed_at` exists but is never set; there is no customer receipt-confirmation route.
- **Assignment from invalid states:** `dashboard.vendor-orders.assign-driver` accepts any vendor order except `rejected` / `cancelled` / `completed` (including `pending`, `accepted`, `preparing`, `assigned`, `out_for_delivery`, `delivered`). It only moves `ready_for_delivery → assigned`; from other states it creates or overwrites the assignment without changing the order status. This contradicts the approved Part A rule that assignment is allowed only from `ready_for_delivery`.

**Expected Behavior**
The vendor order can progress from preparation through hand-over to delivery and completion through an approved, actor-driven lifecycle, and the vendor can follow its delivery status.

> **Contract maturity:** Parts A–E were approved **as review recommendations** in the VEN-BE-020 contract decision rounds of this review workstream. They are a **PROPOSED CONTRACT — TEAM REVIEW REQUIRED**: not implemented, and not accepted by the Palgoals Backend Team. Their code-level design (states, lock order, transactions, audit fields, services) is subject to the team's technical review before implementation.

**Proposed Phase 1 Contract — For Palgoals Backend Team Review — Part A (Vendor → Delivery → Receipt → Completion)**

Approved normal flow:

```
pending → accepted → preparing → ready_for_delivery → assigned → out_for_delivery → delivered → completed
pending → rejected
```

| Transition | Actor |
|---|---|
| `pending → accepted` | Vendor |
| `pending → rejected` | Vendor (reason required) |
| `accepted → preparing` | Vendor |
| `preparing → ready_for_delivery` | Vendor |
| `ready_for_delivery → assigned` | Admin (manual driver assignment) |
| `assigned → out_for_delivery` | Driver pickup confirmation |
| `out_for_delivery → delivered` | Driver delivery confirmation |
| `delivered → completed` | Customer receipt confirmation triggers system completion |

No cancellation edges are defined in Part A.

Rules:
1. **`ready_for_delivery`** means the vendor has finished preparing the vendor order and handed it into the delivery workflow for pickup/assignment. It does not mean a driver picked it up, that it is out for delivery, or that it was delivered. This is the Phase 1 interpretation of the vendor handoff required by FR-VEN-013.
2. **Driver assignment** stays manual by Admin and is allowed only when `VendorOrder.status = ready_for_delivery`; not from `pending`, `accepted`, `preparing`, `assigned`, `out_for_delivery`, `delivered`, `completed`, `rejected` or `cancelled`. Automatic assignment remains deferred.
3. **Driver eligibility:** a driver can be assigned only when account `status = active`, `approval_status = approved` and `is_available = true`, enforced server-side (not only by filtering the Admin UI). Tracked separately in ADM-BE-004.
4. **`assigned`:** a successful assignment moves the vendor order `ready_for_delivery → assigned` and creates/sets the `DeliveryAssignment` to `assigned`, linked to that vendor order and driver. Reassignment and assignment history are not decided in Part A; the current `updateOrCreate` overwrite behavior is not approved by this decision.
5. **No driver acceptance step:** Phase 1 does not require a separate driver acceptance; no `assigned → accepted` transition is introduced.
6. **Physical pickup:** the driver confirms pickup from the vendor → `DeliveryAssignment: assigned → picked_up` and `VendorOrder: assigned → out_for_delivery`, recording the pickup timestamp the schema already supports (`delivery_assignments.picked_up_at`). `picked_up` is a delivery-assignment state; `out_for_delivery` is the corresponding vendor-order state. The vendor does not perform this transition.
7. **Delivery confirmation:** the assigned driver confirms actual delivery to the customer → `DeliveryAssignment → delivered` and `VendorOrder: out_for_delivery → delivered`, setting the delivered timestamps (`delivery_assignments.delivered_at`, `vendor_orders.delivered_at`). The implementation must keep the delivery-assignment and vendor-order states separate even when one action updates both atomically.
8. **Delivered is not Completed:** the driver's delivery confirmation creates `delivered` only; it must never create `completed` on its own.
9. **Customer receipt confirmation:** after the vendor order is `delivered`, the customer who owns the parent order confirms receipt of that specific vendor order. Allowed only when `VendorOrder.status = delivered`; records `customer_receipt_confirmed_at`. Neither the driver nor the vendor can perform it.
10. **Automatic completion:** in the normal flow, once the delivery is confirmed (`delivered`) and the customer's receipt is confirmed, the customer's valid receipt confirmation triggers the system transition `delivered → completed` and sets `completed_at`. Payment status is **not** a prerequisite for completion in Part A (the project keeps the payment and order/delivery cycles separate; `paid ≠ completed`); COD collection remains a separate, unresolved payment-domain contract.
11. **Missing customer confirmation:** no timeout, no automatic completion after a period, and no automatic cancellation are defined. Admin intervention in approved cases is allowed by the project documentation, but its timeout, evidence, override action and conflict/dispute handling remain unresolved.
12. **Vendor authority after handoff** ends at `ready_for_delivery`. In `assigned`, `out_for_delivery`, `delivered` and `completed` the vendor may view/follow the status (FR-VEN-014, FR-VEN-015) but cannot perform those transitions.
13. **`DeliveryAssignment.accepted`:** the existing `accepted` enum value is not part of the approved Phase 1 normal flow. It is not removed or redefined here; its future use can be reviewed separately.

**Proposed Phase 1 Contract — For Palgoals Backend Team Review — Part B (Parent Order Aggregation)**

1. **Derived aggregate:** `Order.status` is derived from its vendor orders. For normal marketplace orders it is not an independently editable lifecycle; it is recalculated after every vendor-order lifecycle transition, and no business logic may move the parent to a state that contradicts its children.
2. **Aggregation groups** (for parent aggregation only):

   | Group | Vendor-order statuses |
   |---|---|
   | WAITING | `pending` |
   | IN_PROGRESS | `accepted`, `preparing`, `ready_for_delivery`, `assigned`, `out_for_delivery`, `delivered` |
   | SUCCESS_TERMINAL | `completed` |
   | CANCEL_TERMINAL | `rejected`, `cancelled` |

   `delivered` stays IN_PROGRESS because Delivered ≠ Completed (Part A). `rejected` and `cancelled` are equivalent only for parent aggregation; they are not merged or redefined at vendor-order level.
3. **Parent statuses:** intermediate `pending`, `processing`; terminal `completed`, `partially_cancelled`, `cancelled`.
4. **`pending`:** every vendor order is in {`pending`, `rejected`, `cancelled`} and at least one is still `pending` (no surviving branch has started processing). Examples: `pending` → pending; `pending + pending` → pending; `pending + rejected` → pending; `pending + cancelled` → pending; `pending + rejected + cancelled` → pending.
5. **`processing`:** at least one vendor order is still non-terminal and at least one surviving branch has progressed beyond `pending` (any of `accepted` … `delivered`), including when another branch is already `completed`. Examples: `accepted` → processing; `rejected + preparing` → processing; `completed + pending` → processing; `completed + delivered` → processing; `completed + rejected + preparing` → processing. A rejected/cancelled branch does not make the parent `partially_cancelled` while another branch is still active.
6. **`completed`:** only when **every** vendor order is `completed` (`completed + completed` → completed; `completed + rejected`, `completed + cancelled` and `completed + delivered` are not completed). It means the whole marketplace order succeeded.
7. **`partially_cancelled`** is a **terminal mixed-outcome** state: no vendor order is still non-terminal, at least one is `completed`, and at least one is `rejected` or `cancelled`. Examples: `completed + rejected`, `completed + cancelled`, `completed + completed + rejected`, `completed + rejected + cancelled` → partially_cancelled. It is not used merely because one branch was rejected while another is still running.
8. **`cancelled`:** every vendor order is in {`rejected`, `cancelled`} (`rejected`, `cancelled`, `rejected + rejected`, `rejected + cancelled`, `cancelled + cancelled` → cancelled).
9. **Deterministic precedence:**

   ```
   X = {rejected, cancelled}
   non-terminal = {pending, accepted, preparing, ready_for_delivery, assigned, out_for_delivery, delivered}

   if no vendor orders:
       not defined by this contract (not a normal storefront order)
   elseif all vendor orders are in X:
       cancelled
   elseif any vendor order is non-terminal:
       if every vendor order is in {pending} ∪ X:
           pending
       else:
           processing
   else:
       # all branches terminal and at least one completed
       if any vendor order is in X:
           partially_cancelled
       else:
           completed
   ```
10. **`orders.completed_at`:** in Phase 1 it records when the parent lifecycle reaches its terminal outcome. It is set when the derived parent first reaches `completed`, `partially_cancelled` or `cancelled`, and is not set for `pending` or `processing`. Despite its name, it is the parent's final-outcome timestamp; no new column or migration is added. No reopening/terminal-exit behavior is approved in Part B.
11. **Architecture:** one central order-status aggregation service (conceptually `OrderStatusAggregator`), invoked explicitly by every vendor-order lifecycle transition and deriving the parent from the authoritative vendor-order states. No model observers, and no duplicated controller-specific aggregation logic.
12. **Transactions:** aggregation runs inside the same database transaction as the vendor-order transition that caused it (e.g. Vendor Reject: vendor order `rejected` + reservations released + parent aggregation; customer receipt confirmation: `customer_receipt_confirmed_at` + vendor order `completed` + `completed_at` + parent aggregation), so a failure never leaves parent and child states inconsistent.
13. **Lock order** for vendor-order lifecycle transitions: **Order → VendorOrder(s) → Products (ascending `product_id`) → StockReservations.** The parent order is locked before one of its vendor orders is modified or recalculated, and sibling vendor orders are read under that parent serialization boundary, so concurrent updates of sibling vendor orders cannot produce a lost or stale parent status. VEN-BE-019 stays PASS: extending Accept/Reject to lock the parent first does not reopen the inventory commitment point or inventory semantics.
14. **Trigger points:** after every vendor-order lifecycle transition: normal checkout creation/initialization as appropriate, Vendor accept, Vendor reject, preparing, ready_for_delivery, Admin assignment, Driver pickup, Driver delivery, customer receipt confirmation/completion, and future approved cancellation (not implemented in Part B).
15. **Admin generic parent status editing:** because `Order.status` is derived, Admin must not keep an unrestricted `any → any` lifecycle editor for the parent. The current `dashboard.orders.update-status` conflicts with this contract and is tracked in ADM-BE-005. No Admin override mechanism is decided; a future explicit override would need approved cases, authorization, transition rules, side effects and audit/history.
16. **Admin header-only vendor orders:** Admin can attach a header-only vendor order to an existing customer order, which can interfere with derived aggregation. The aggregator must not exclude them through hidden special logic, and attaching them to customer storefront orders is not approved. Tracked in ADM-BE-006; its resolution is not decided here.

**Proposed Phase 1 Contract — For Palgoals Backend Team Review — Part C (Cancellation + Committed Inventory Restoration)**

1. **Meaning of `cancelled`:** `rejected` = the vendor refuses a vendor order while it is still `pending`; `cancelled` = an Admin cancellation of a vendor order through the approved cancellation workflow. They stay distinct vendor-order states and are equivalent only for parent aggregation (Part B). The vendor cannot use `cancelled` as a substitute for rejection.
2. **Actor:** Phase 1 cancellation is **Admin only**. No customer self-service cancellation and no vendor post-Accept cancellation are approved; the vendor keeps only `pending → rejected`. If a vendor cannot fulfil an accepted order, the Phase 1 path is Admin intervention/cancellation, not a new vendor action. Customer cancellation stays out of scope unless a future contract adds it.
3. **Cancellable window:** Admin may cancel a vendor order only from `pending`, `accepted`, `preparing`, `ready_for_delivery` or `assigned`; never from `out_for_delivery`, `delivered`, `completed`, `rejected` or `cancelled`. Before pickup the goods are still with the vendor; after pickup physical possession has changed, so a logical cancellation cannot imply immediate vendor stock restoration (post-pickup belongs to Part D).
4. **Cancelling a `pending` vendor order** atomically: lock the parent order, lock the vendor order, release all matching `reserved` reservations (`reserved → released`, set `released_at`), make **no** change to `products.stock_quantity`, set the vendor order `cancelled`, record the cancellation audit fields, and recalculate the parent (Part B). Vendor rejection (`pending → rejected`) and Admin cancellation (`pending → cancelled`) both release reserved inventory but stay separate actions.
5. **Cancelling after commitment** (`accepted`, `preparing`, `ready_for_delivery`, `assigned`; inventory already committed under VEN-BE-019) atomically:
   1. lock the parent order;
   2. lock the vendor order and siblings as required by Part B;
   3. lock all relevant products in ascending `product_id`;
   4. lock the vendor order's committed reservations;
   5. validate the restoration completeness invariant (rule 15);
   6. increment each product's `stock_quantity` by its committed quantity exactly once;
   7. move each matching reservation `committed → released`;
   8. set `released_at`;
   9. cancel the delivery assignment when the vendor order is `assigned`;
   10. set the vendor order `cancelled`;
   11. record the cancellation audit fields;
   12. recalculate the parent order;
   13. commit everything together.

   Any failure rolls back all effects.
6. **Reservation reversal:** approved `committed → released`; no new `restored` status in Phase 1. `reserved → released` means released before commitment; `reserved → committed → released` means committed stock was later restored by a valid pre-pickup cancellation. `committed_at` and `released_at` distinguish the two histories. Only `reserved` still reduces available stock; the VEN-BE-019 available-stock formula is unchanged.
7. **Successful completion:** for a successfully fulfilled vendor order the reservation stays `committed` permanently; no `consumed`/`finalized`/`completed` reservation state is needed. `committed` is terminal for consumed stock; returns/restocking may define a later reversal separately.
8. **Physical pickup boundary:** driver pickup is the physical inventory boundary. `assigned` → Admin cancellation is allowed and restores stock; `out_for_delivery` → normal cancellation is forbidden. Vendor stock is never incremented merely because an out-for-delivery shipment fails or is cancelled logically; physical return/restocking after pickup belongs to Part D.
9. **`delivered` / `completed`:** normal cancellation is forbidden. `delivered` but not yet `completed` does not mean the goods can be restored to vendor inventory; issues after delivery belong to Admin intervention, disputes, returns and refunds as separately approved later. `completed` stays terminal for normal cancellation.
10. **Delivery assignment:** cancelling an `assigned` vendor order also sets its active delivery assignment `cancelled` in the same transaction; the goods were not picked up, so stock is restored per rule 5. Reassignment/history is not decided here.
11. **Whole parent order cancellation:** Admin must never set `Order.status = cancelled` directly (Part B; current generic editing tracked in ADM-BE-005). Cancelling a whole order means cancelling **every** vendor order of that order through the vendor-order cancellation primitive, **all-or-nothing**: if any vendor order is not currently cancellable, the whole operation is refused and nothing changes. Examples: `pending + preparing` → allowed, both cancelled atomically; `pending + assigned` → allowed; `pending + out_for_delivery` → refused, neither changes; `completed + pending` → refused, neither changes. Admin may still cancel an individual eligible vendor order separately.
12. **Parent aggregation** (Part B unchanged, same transaction): `cancelled + pending` → pending; `cancelled + preparing` → processing; `cancelled + completed` → partially_cancelled; all rejected/cancelled → cancelled; `completed + cancelled + delivered` → processing.
13. **Payment boundary:** cancellation does **not** modify `orders.payment_status`, `payments.status` or `refunded_amount`; the order, delivery and payment lifecycles stay separate. A valid cancellation may proceed even if payment is recorded as paid; refund handling is a separate pending workflow. No automatic refund marking, no payment-status change, and no blocking of a valid cancellation solely because payment is paid. The payment-status source of truth and the refund workflow remain for the later payment review.
14. **Idempotency (exactly once for inventory):** the source vendor-order status is validated under lock; `cancelled`, `rejected`, `completed` and every non-cancellable state are refused; pre-commit release affects only `reserved` reservations; post-commit restoration affects only `committed` reservations; a restored reservation becomes `released`; a repeated cancellation can neither release nor restore again, and stock can never be incremented twice for the same commitment.
15. **Multi-product completeness:** restoration reuses the VEN-BE-019 completeness invariant: every stock-backed order item has exactly one matching `committed` reservation with the same vendor order, `order_item_id`, `product_id` and expected quantity. Products are locked in ascending `product_id`; restoration is all-or-nothing. Any missing or inconsistent reservation/product relationship fails the cancellation: no stock restored, no reservation changed, vendor order and parent order unchanged.
16. **Null-product order items** (`product_id = null`) stay outside inventory restoration, consistent with VEN-BE-019; no stock behavior is defined for them.
17. **Header-only vendor orders** (no stock-backed items, no reservations) have no inventory effect on cancellation; cancelling one changes lifecycle/audit data and triggers aggregation only. ADM-BE-006 stays OPEN; no hidden aggregation special-casing.
18. **Cancellation audit:** Phase 1 cancellation requires explicit audit fields on the vendor order: `cancelled_at`, `cancellation_reason` (**required**) and `cancelled_by_admin_id` (the Admin who cancelled). `rejected_at` and `rejection_reason` must not be reused. These fields do not exist yet, so implementation requires a migration. Preferred: `cancelled_by_admin_id` as a nullable FK to `admins.id` (nullable at schema level for historical/deletion safety, but always populated by a valid Phase 1 cancellation). No polymorphic actor system in Phase 1.
19. **Lock order** stays **Order → VendorOrder(s) → Products (ascending `product_id`) → StockReservations**. Delivery-assignment updates happen in the same transaction without introducing a conflicting lock-order cycle. Whole-order cancellation uses deterministic ordering for its vendor orders and products.
20. **Failed delivery stays in Part D:** Part C stops at the physical pickup boundary and does not define failed delivery, a driver failure action, return-to-vendor, restocking after physical return, retry/re-delivery, or reassignment after pickup.
21. **Returns/refunds stay separate:** `delivered` and `completed` are not cancellable merely because return/refund workflows are not implemented; returns, disputes, refunds and restocking after customer receipt are separate contracts.

**Proposed Phase 1 Contract — For Palgoals Backend Team Review — Part D (Failed Delivery + Physical Return + Retry / Reassignment)**

1. **Responsibility split:** the vendor order is the commercial fulfilment lifecycle; a delivery assignment is one driver's physical delivery custody/mission. They are related, not duplicate independent sources of truth. The vendor order owns the commercial states; the delivery assignment owns which driver has the mission, assignment, physical pickup, mission failure, physical delivery, assignment cancellation and the physical-return audit.
2. **Assignment states used in Phase 1:** normal path `assigned → picked_up → delivered`, plus terminal outcomes `failed` and `cancelled`. `accepted` and assignment-level `out_for_delivery` are not used in the approved path (not removed in this round). No driver acceptance step, and no lifecycle event between physical pickup and the vendor order becoming `out_for_delivery`.
3. **Synchronization:**

   | Operation | Delivery assignment | Vendor order |
   |---|---|---|
   | Admin assignment | new row `assigned` | `assigned` |
   | Driver pickup | `assigned → picked_up`, `picked_up_at` | `assigned → out_for_delivery` |
   | Successful delivery | `picked_up → delivered`, `delivered_at` | `out_for_delivery → delivered`, `delivered_at` |
   | Terminal failure | `picked_up → failed` | stays `out_for_delivery` |
   | Resellable return confirmed | return data recorded on the failed row | `out_for_delivery → ready_for_delivery`; reservation stays `committed` |
   | Pre-pickup reassignment | previous `assigned → cancelled`; new row `assigned` | stays `assigned`; driver mirror updated |

4. **Assignment history:** every Admin assignment creates a **new** delivery-assignment row; `updateOrCreate` overwriting is not allowed. A vendor order may have many historical assignments but **exactly one active** assignment at a time. Active: `assigned`, `picked_up`. Inactive/terminal: `delivered`, `failed`, `cancelled`. `accepted` and assignment-level `out_for_delivery` must not be used to bypass this invariant. Enforced at service level under locks in Phase 1; no partial unique DB index is required.
5. **Reassignment before pickup:** allowed only while the active assignment is `assigned` and pickup has not occurred. It must: lock the order; lock the vendor order; lock the active assignment; validate the new driver per ADM-BE-004 (active, approved, `is_available = true`); cancel the old assignment; create a new row; update the vendor order's driver mirror; keep the vendor order `assigned`. History is preserved; the old row is never reused or overwritten.
6. **No reassignment after pickup:** once the assignment is `picked_up` and the vendor order `out_for_delivery`, the goods are physically with that driver and Admin cannot replace the driver in the database. No direct driver-to-driver handoff is approved in Phase 1; to assign another driver, the goods must first return physically to the vendor and the return be confirmed under this Part D.
7. **Attempt vs failure:** not every unsuccessful contact/attempt is a terminal failure, and Phase 1 introduces **no** `DeliveryAttempt` model. A temporarily unavailable customer, an unanswered call, or a later retry by the **same** driver who still has custody may be noted operationally but does not end the assignment: it stays `picked_up` and the vendor order stays `out_for_delivery`. This avoids reopening a terminal row, faking a new assignment starting at `picked_up`, or inventing an attempt subsystem.
8. **`failed` is terminal** for that assignment and is used only when that driver's mission has ended unsuccessfully and the goods require physical return to the vendor or exceptional Admin intervention for loss/damage. A failed assignment is never reopened; a temporary same-driver retry does not set `failed`.
9. **Failure reason:** a terminal failure requires `failed_at`, `failure_reason_code` and nullable `failure_notes`. Approved reason codes: `customer_refused`, `invalid_address`, `unable_to_contact`, `cod_not_collected`, `operational_issue`, `vehicle_issue`, `package_issue`, `other`. `unable_to_contact` is terminal only when the mission is being ended, not for every failed call. `package_issue` does not imply stock restoration. No vendor-order failure states are added.
10. **Same-driver retry:** allowed while the assignment stays `picked_up`, the vendor order stays `out_for_delivery`, custody has not changed, and the assignment is not `failed`/`delivered`/`cancelled`. No new assignment row, no stock or reservation change, no parent change beyond staying `processing`, no payment change. Per-attempt history is deferred unless a `DeliveryAttempt` concept is approved later.
11. **Physical return after terminal failure:** after an assignment is `failed`, the goods are not vendor inventory merely because the driver reported failure. In the normal resellable path the **vendor** confirms physical receipt of the goods. The vendor may do this even though Part A ended its authority over delivery transitions, because this is a physical inventory custody confirmation, not a driver delivery transition.
12. **Return audit** on the delivery assignment (minimum semantic fields): `failed_at`, `failure_reason_code`, `failure_notes` (nullable), `returned_at` (the physical return event), `return_confirmed_by_vendor_at` (vendor confirmation), `return_condition` (`resellable` or `damaged`; not a boolean). The vendor identity is derivable from the vendor order's ownership; no polymorphic actor field unless implementation proves it necessary. Migration details may be refined during implementation, but these semantic fields are required.
13. **Resellable return:** when the assignment is `failed`, the goods physically return, the vendor confirms receipt and `return_condition = resellable`, atomically: record the return audit; vendor order `out_for_delivery → ready_for_delivery`; clear/update the vendor order's driver mirror so it no longer represents an active driver; the reservation stays `committed`; `products.stock_quantity` does **not** increase; the parent stays `processing` (Part B). The product is still allocated to the same vendor order; physical return does not release the customer's allocation.
14. **After a resellable return** (`ready_for_delivery`, reservation `committed`), Admin has two approved choices: **A. retry** — normal Part A assignment creating a new assignment row, then pickup/delivery; **B. end** — normal Part C Admin cancellation from `ready_for_delivery` (restore stock exactly once, `committed → released`, vendor order `cancelled`, parent aggregation). No special post-failure cancellation primitive.
15. **No automatic restoration on return:** confirming a return never increments `products.stock_quantity`, even when `resellable`; stock stays committed because the item is still allocated to the same vendor order. Restoration happens only if Admin later cancels through Part C. This prevents double restoration and exposing allocated stock to other buyers before the order ends.
16. **Damaged returns** (`return_condition = damaged`): not restored to sellable inventory; the vendor order does **not** move to `ready_for_delivery`; Part C restoration does not run. Admin intervention is required; the commercial ending/compensation/dispute workflow remains unresolved.
17. **Lost goods** (never returned): no restoration; the reservation stays `committed`; the vendor order stays `out_for_delivery`; the failed assignment records the failure; Admin intervention is required. The closing/compensation lifecycle remains unresolved. A physical return is never faked and inventory is never marked available.
18. **COD refusal/non-collection:** physically, may be a terminal failure with `failure_reason_code = cod_not_collected` when the mission is ending and the goods are returning. The payment lifecycle stays separate: no automatic change to payment status, no refunded/paid marking, no `refunded_amount` change.
19. **Delivery proof (no OTP):** pickup = assignment driver identity + `picked_up_at`; successful delivery = assignment driver identity + `delivered_at`; terminal failure = `failed_at` + `failure_reason_code` + optional `failure_notes`; physical return = `returned_at` + vendor confirmation timestamp + `return_condition`. Photo, signature, GPS, recipient name and OTP are not required in Phase 1 (future enhancements).
20. **Driver availability:** `is_available` stays an Admin-controlled eligibility flag; it is not toggled automatically on assignment, pickup, delivery, failure or return. No one-active-order-per-driver rule: a driver may have several assignments unless a capacity contract is approved later. ADM-BE-004 eligibility applies to every assignment and reassignment.
21. **`vendor_orders.delivery_driver_id`** is kept in Phase 1 as a **mirror** of the current active assignment; the authoritative history is the delivery assignments. Assignment/reassignment → mirror = active driver; successful delivery → may keep the driver who delivered; terminal failure while the goods are still with the driver → keep the driver until custody is resolved; confirmed resellable return → clear the mirror; cancellation before pickup → clear the mirror as part of the assignment cancellation. The column is not dropped in Part D.
22. **Parent aggregation** (Part B unchanged): while the vendor order is `out_for_delivery`, a same-driver retry is pending, a failed assignment awaits physical return, or resellable goods have returned (`ready_for_delivery`), the parent stays `processing` unless sibling vendor orders produce another Part B result. After a Part C cancellation following a return, aggregate normally. No vendor-order `failed` state.
23. **Payment boundary:** delivery failure does not change payment; COD non-collection is recorded as failure context; no automatic refund; the payment-status source of truth stays unresolved.
24. **Transactions** (each atomic): A. assignment — order/vendor-order locks, validate driver, create assignment, update vendor order and mirror, aggregate; B. pre-pickup reassignment — cancel old, create new, update mirror, vendor order stays `assigned`; C. pickup — assignment `picked_up` + `picked_up_at`, vendor order `out_for_delivery`; D. terminal failure — assignment `failed` + `failed_at` + reason/notes, vendor order stays `out_for_delivery`, stock/reservation/payment unchanged; E. resellable return — return audit, vendor order `ready_for_delivery`, mirror cleared, reservation stays `committed`, stock unchanged, aggregate; F. successful delivery — assignment `delivered` + `delivered_at`, vendor order `delivered` + `delivered_at`, aggregate; G. cancellation after a resellable return — Part C unchanged.
25. **Global lock order** (extended): **Order → VendorOrder(s) → DeliveryAssignment(s) → Products (ascending `product_id`) → StockReservations.** Every Part D operation follows it; no code path may lock a product/reservation first and later acquire the order, vendor order or assignment.
26. **Idempotency:** pickup only once from `assigned`; delivery only once from `picked_up`; terminal failure only once from `picked_up`; `delivered` and `failed` are mutually exclusive; a failed assignment is never reopened; return confirmation only once per failed assignment; no second active assignment; reassignment preserves history; a return never restores stock; Part C stays the only pre-delivery stock-restoration primitive; no double restoration.
27. **Concurrency:** serialized under Order → VendorOrder → DeliveryAssignment, covering at least: Admin reassignment vs driver pickup; Admin cancellation vs driver pickup; driver delivery vs terminal failure; two Admin assignments; return confirmation vs another lifecycle action; reassignment after a return; sibling vendor-order changes through the parent order lock. The first valid locked transition wins; an incompatible second transition is refused. VEN-BE-029 stays OPEN for real MySQL/InnoDB concurrency proof.
28. **Assignment cancellation audit:** delivery assignments record `cancelled_at` when cancelled before pickup (Admin reassignment, or Part C cancellation while `assigned`). No separate assignment cancellation reason: the surrounding operation (reassignment or vendor-order cancellation) explains it.

Kept inside VEN-BE-020 (no separate findings): the overwritten assignment history, the missing driver lifecycle, the missing failed-delivery handling, the missing physical-return audit, the one-active-assignment service invariant, and the delivery-assignment/vendor-order synchronization requirements. ADM-BE-004 applies to every assignment and reassignment.

**Proposed Phase 1 Contract — For Palgoals Backend Team Review — Part E (Customer Receipt Confirmation + Missing Confirmation + Admin Intervention)**

1. **Confirmation subject:** customer receipt confirmation is performed on **one specific vendor order**, never directly on the parent order. Vendor orders are fulfilled and delivered independently; the parent `Order.status` stays derived exclusively through Part B aggregation.
2. **Authorized actor:** only the customer who owns the parent order (`VendorOrder → Order → customer_id` = the authenticated customer) may confirm receipt of that vendor order. Vendor, driver and Admin cannot use the customer confirmation endpoint; Admin intervention is a separate Admin workflow (rule 11).
3. **Source state:** normal customer confirmation is allowed only when `VendorOrder.status = delivered` **and** the corresponding successful delivery assignment is `delivered` with `delivered_at` recorded. For new Phase 1 data the vendor-order/delivery-assignment lifecycle consistency must be validated. Confirmation is refused from `pending`, `accepted`, `preparing`, `ready_for_delivery`, `assigned`, `out_for_delivery`, `rejected` and `cancelled`. An already `completed` vendor order is handled only by the idempotency rule (rule 6).
4. **Normal customer confirmation transaction** (one transaction):
   1. lock the parent order;
   2. lock the vendor order;
   3. lock the relevant successful delivery assignment;
   4. revalidate customer ownership;
   5. revalidate lifecycle consistency;
   6. ensure the vendor order was not completed through Admin intervention;
   7. ensure no blocking open dispute applies, once the dispute workflow exists (rule 10);
   8. set `customer_receipt_confirmed_at`;
   9. vendor order `delivered → completed`;
   10. set `vendor_orders.completed_at`;
   11. run Part B parent aggregation;
   12. commit.

   No product stock mutation, no stock-reservation mutation, no payment mutation and no delivery-assignment state mutation: the reservation stays `committed` and the delivery assignment stays `delivered`.
5. **Customer confirmation audit semantics:** `customer_receipt_confirmed_at` means exactly that the owning customer explicitly confirmed receipt. It must **not** be populated when Admin completes the vendor order, when Admin creates historical data, when a seeder creates data, or when any other actor intervenes. No `confirmed_by_customer_id` is required in Phase 1: the customer identity is derivable from parent order ownership.
6. **Idempotency:** if the **same** owning customer repeats confirmation after a successful customer confirmation, the response is success with no write: `customer_receipt_confirmed_at` and `completed_at` are unchanged, no side effect is rerun, and the current completed state is returned. This is an idempotent success, not a lifecycle error. If the vendor order was completed by **Admin** rather than by the customer, a customer confirmation is **not** idempotent: it is refused with a clear conflict/validation response and never populates `customer_receipt_confirmed_at`.
7. **Missing customer confirmation:** **no automatic completion** in Phase 1 and no approved timeout. No 24-hour value, N-day value or scheduled auto-completion is invented. If the customer does not confirm, the vendor order stays `delivered` until an approved Admin intervention (rule 11) or a future dispute/intervention workflow resolves it.
8. **Customer unavailable / blocked:** if the customer cannot access the account or does not respond, the vendor order is not auto-completed and stays `delivered`; Admin intervention is the Phase 1 fallback.
9. **Non-receipt conflict:** if the driver recorded delivery but the customer claims non-receipt, the vendor order is not completed automatically and `customer_receipt_confirmed_at` is not mutated; the case requires Admin intervention / dispute handling. Part E does **not** define an Admin `delivered → failed` transition; correcting an incorrectly recorded delivery remains an explicit unresolved exception workflow. Part D is not reopened.
10. **Open-dispute blocking (future-facing invariant):** an **open** dispute concerning the vendor order blocks both customer receipt confirmation and Admin completion. The dispute creation/status workflow is not implemented or approved yet; this is recorded as a lifecycle invariant/dependency only, with no dispute implementation invented in Part E.
11. **Admin completion:** a separate, explicit Admin intervention action `delivered → completed`; it is **not** customer confirmation. Requirements: the vendor order is `delivered`; the delivery lifecycle is consistent; no blocking open dispute; an Admin completion reason is mandatory; the Admin identity is recorded; `vendor_orders.completed_at` is recorded; Part B aggregation runs in the same transaction. Admin completion must **not** set `customer_receipt_confirmed_at`. No stock, reservation or payment mutation.
12. **Admin completion audit:** explicit fields on `vendor_orders`:
    - `completed_by_admin_id`: nullable FK to `admins.id` at schema level; it **must** be populated by every valid Admin completion;
    - `admin_completion_reason`: required for Admin completion.

    No `admin_completed_at` in Phase 1; `completed_at` already records when completion occurred. These fields do not exist yet, so implementation requires a migration. The completion source is derivable:

    | Source | `customer_receipt_confirmed_at` | `completed_by_admin_id` |
    |---|---|---|
    | Customer completion | not null | null |
    | Admin completion | null | not null |

    For new valid Phase 1 completed data the two sources are mutually exclusive.
13. **Completion invariants** (new Phase 1 data):
    - `status = completed` ⇒ `delivered_at` not null, `completed_at` not null, and **exactly one** completion source exists (`customer_receipt_confirmed_at` **or** `completed_by_admin_id`), never both.
    - `customer_receipt_confirmed_at` not null ⇒ `status = completed` and `customer_receipt_confirmed_at >= delivered_at`.
    - `completed_by_admin_id` not null ⇒ `status = completed`, `customer_receipt_confirmed_at` null, and `admin_completion_reason` present.

    Legacy/inconsistent data may require migration/cleanup handling during implementation; confirmation sources must never be silently fabricated.
14. **Parent aggregation** (Part B unchanged): `completed + delivered` → processing; `completed + completed` → completed; `completed + cancelled` → partially_cancelled; `completed + ready_for_delivery` → processing; all cancelled/rejected → cancelled. When a customer/Admin completion of the final active vendor order makes the parent terminal, Part B controls `Order.status` and `orders.completed_at`; the parent `completed_at` stays exact-once under the Part B contract.
15. **Timestamp ordering:**
    - Customer completion: `delivery_assignments.delivered_at` ≤ `vendor_orders.delivered_at` ≤ `customer_receipt_confirmed_at` ≤ `vendor_orders.completed_at` (`customer_receipt_confirmed_at` and `completed_at` may be produced in the same transaction/time).
    - Admin completion: `delivery_assignments.delivered_at` ≤ `vendor_orders.delivered_at` ≤ `vendor_orders.completed_at`.
    - `orders.completed_at` must not precede the child transition that caused the parent to reach its first terminal outcome.
16. **Payment boundary:** customer confirmation and Admin completion do not automatically set payment paid, collect COD, refund, or mutate `payment_status`, payments or `refunded_amount`. Under the approved Part A rule a vendor order may become `completed` while payment stays pending. The payment/COD source of truth stays unresolved for the later Finance review.
17. **Inventory boundary:** completion causes no inventory mutation: `products.stock_quantity` is unchanged and the reservation stays `committed`, consistent with VEN-BE-019 and Part C.
18. **Review eligibility:** eligibility for a vendor experience is based on the specific `VendorOrder.status = completed` plus customer ownership and the review's other valid requirements. It must **not** require `Order.status = completed`, because a valid completed vendor order can belong to a `partially_cancelled` parent under Part B; such a vendor order represents a completed customer/vendor experience and stays review-eligible. The rest of Reviews is not redesigned in Part E. The current code conflicts with this rule; tracked in VEN-BE-031.
19. **Returns boundary:** Part E does not decide whether a future return window begins at `delivered_at`, `customer_receipt_confirmed_at` or `completed_at`; that belongs to the Returns contract, and no return-window assumption may be hard-coded.
20. **Commission boundary:** `VendorOrder.completed` is a clean lifecycle event available to later Finance/Commission logic. Part E does **not** decide that completion alone creates commission entitlement; the later Finance contract decides whether commission depends on vendor-order completion, payment, the parent order, settlement, or a combination (VEN-BE-018).
21. **Notifications:** no notification workflow is introduced in Part E; no confirmation reminders or scheduled alerts are invented. Notification requirements may be designed separately later.
22. **Generic Admin lifecycle creation/editing:** generic Admin creation/editing must not bypass the approved lifecycle. Creating a vendor order directly as `delivered` or `completed` with fabricated lifecycle timestamps bypasses the delivery-assignment lifecycle, customer confirmation, the Admin completion audit, parent aggregation and inventory/order invariants, and is **not approved**. The implementation must prevent generic Admin CRUD from manufacturing these lifecycle states outside the explicit lifecycle services. Whether generic vendor-order creation is removed entirely or restricted to a safe initial state is not decided here; it is the acceptance requirement of ADM-BE-007.
23. **Lock order:** receipt confirmation and Admin completion use **Order → VendorOrder → DeliveryAssignment**, a valid prefix of the global order **Order → VendorOrder(s) → DeliveryAssignment(s) → Products (ascending `product_id`) → StockReservations**. No product or stock-reservation lock is required for completion.
24. **Concurrency:** customer confirmation and Admin completion serialize under Order → VendorOrder → DeliveryAssignment, and only one valid completion source may win:
    - customer confirmation first → the customer source is recorded; a later Admin completion is refused;
    - Admin completion first → the Admin source is recorded; a later customer confirmation is refused;
    - two customer confirmation requests → the first writes; the second receives an idempotent success.

    VEN-BE-029 stays OPEN for real MySQL/InnoDB lock proof.

Kept inside VEN-BE-020 (no separate findings): no current customer confirmation route; no current `delivered → completed` transition; no current Admin completion service/audit implementation. Tracked separately: VEN-BE-031 (review eligibility depends on parent order completion) and ADM-BE-007 (Admin generic vendor-order creation can bypass the lifecycle).

**Still unresolved after Part E**
- Correction of an incorrectly recorded `delivered`.
- Damaged-goods commercial resolution.
- Lost-goods commercial resolution.
- Compensation/dispute workflow for delivery loss/damage.
- Direct driver-to-driver physical handoff.
- Detailed `DeliveryAttempt` history.
- Full dispute workflow / non-receipt resolution.
- COD collection/payment accounting.
- Payment-status source of truth (`orders.payment_status` vs `payments.status`).
- Refund workflow.
- Admin lifecycle overrides outside the specifically approved interventions.
- Returns/restocking after successful delivery.
- Commission trigger (VEN-BE-018).

**Decided (as review recommendations; proposed contract, not implemented or accepted by the Palgoals Backend Team):** parent order aggregation (Part B); cancellation after Vendor Accept, committed-stock restoration on cancellation, and terminal handling of committed reservations for normal successful completion (Part C); ordinary pre-pickup reassignment, assignment history, normal delivery failure, same-driver retry while custody is unchanged, resellable physical return, and retry after a resellable return (Part D); normal customer receipt confirmation, Phase 1 missing-confirmation behavior, normal Admin completion intervention, completion-source audit, and the review eligibility rule (Part E).

**SRS / Architecture Reference**
UC-VEN-005 — معالجة الطلب الفرعي (FR-VEN-011, FR-VEN-012, FR-VEN-013, FR-VEN-020, FR-VEN-022 as applicable: independent sub-order, preparation updates, hand-over to delivery); UC-VEN-006 — متابعة الطلبات والتوصيل (FR-VEN-014, FR-VEN-015: follow the order until delivery, follow delivery status); HIRFAH SRS v0.5 (reviewed outside the repository). Backend Scenario Review (docx, sections 6 and 10): vendor lifecycle `Pending → Accepted → Preparing → Ready for Delivery` with `Rejected`; Order, Vendor Order, Delivery and Payment statuses stay independent. Delivery requirements to be cross-referenced once recorded.

**Related Routes / Files**
- Routes: `vendor.dashboard.orders.*`, `dashboard.vendor-orders.assign-driver`, `dashboard.vendor-orders.store`
- `app/Http/Controllers/VendorDashboard/OrderController.php`
- `app/Http/Controllers/Dashboard/VendorOrderManagementController.php`
- `app/Models/VendorOrder.php`, `app/Models/DeliveryAssignment.php`
- Delivery driver area (currently a placeholder dashboard)

**Backend Guidance**
- After Palgoals Backend Team review, implement only the proposed Parts A–E contract as accepted by the team; do not invent actors or transitions for the items still unresolved.
- Keep the vendor order, delivery assignment, payment and parent order statuses independent; when one action updates several, do it atomically.

**Acceptance Criteria**
- Through the approved Part A lifecycle, a vendor order reaches `delivered` and `completed` without manual status editing.
- Admin can assign a driver only from `ready_for_delivery`, and only an eligible driver (ADM-BE-004).
- Only the assigned driver can confirm pickup (`assigned → out_for_delivery`, assignment `picked_up`) and delivery (`out_for_delivery → delivered`, assignment `delivered`).
- Driver delivery confirmation never sets `completed`.
- Only the parent order's customer can confirm receipt, and only from `delivered`; this sets `customer_receipt_confirmed_at`, then the system sets `completed` and `completed_at`.
- The vendor cannot perform any transition after `ready_for_delivery`, but can see the delivery status of its orders.
- After every vendor-order transition, the parent `Order.status` equals the Part B derived value, in the same transaction; `orders.completed_at` is set when the parent first reaches a terminal outcome.
- Concurrent transitions on sibling vendor orders cannot leave a stale parent status (parent locked first).

**Required Tests**
- End-to-end vendor order through the approved Part A lifecycle to completion.
- Each transition allowed only for its approved actor and source state; every other actor/state refused without changes.
- Assignment from every non-`ready_for_delivery` state refused.
- Driver delivery confirmation leaves the order `delivered`, not `completed`.
- Customer receipt confirmation by another customer, or before `delivered`, refused.
- Vendor order endpoints expose the delivery status to the vendor.
- Parent aggregation: one test per Part B rule and example (pending, processing, completed, partially_cancelled, cancelled, including mixed 3+ branch cases), and `completed_at` set only on the first terminal outcome.
- A failed transition leaves both child and parent unchanged.
- Part C cancellation:
  - A. `pending` Admin cancellation: `reserved → released`, no stock increment, `cancelled`, audit fields, aggregation.
  - B. `accepted` cancellation: `committed → released`, stock restored once.
  - C. `preparing` cancellation; D. `ready_for_delivery` cancellation.
  - E. `assigned` cancellation: stock restored, delivery assignment `cancelled`.
  - F–J. Refused from `out_for_delivery`, `delivered`, `completed`, `rejected`, and already `cancelled`.
  - K. Double cancellation: no second stock increment.
  - L. Multi-product: complete restoration, deterministic product locking, all-or-nothing.
  - M. Missing/wrong committed reservation: full refusal/rollback.
  - N. Rollback after a stock mutation has begun.
  - O. Whole-order cancellation: all eligible → all cancelled atomically; one ineligible → entire operation refused, no changes.
  - P. Part B parent aggregation after cancellation.
  - Q. Payment fields unchanged.
  - R. Actor/authorization: Admin required; vendor/customer cannot call Admin cancellation.
  - S. `cancellation_reason` required.
  - T. `cancelled_by_admin_id` recorded.
  - U. Header-only vendor order: lifecycle/audit/aggregation only, no stock mutation.
  - V. Concurrency: cancellation vs sibling transition under parent order serialization (logical SQLite test where possible; real MySQL/InnoDB locking remains subject to VEN-BE-029).
- Part D delivery failure / return / reassignment:
  - Every assignment creates a new history row; exactly one active assignment per vendor order.
  - An eligible driver (ADM-BE-004) is required on assignment and reassignment.
  - Pre-pickup reassignment cancels the old row (`cancelled_at`) and preserves history; reassignment after pickup is refused.
  - Pickup syncs assignment and vendor order atomically; successful delivery syncs both atomically.
  - A temporary same-driver retry creates no new assignment and changes no state.
  - A terminal failure requires a reason code, leaves the vendor order `out_for_delivery`, and changes no stock, reservation or payment; a failed row cannot be reopened.
  - A resellable return is vendor-confirmed, moves the vendor order to `ready_for_delivery`, and leaves stock and the reservation `committed`; a duplicate return is refused.
  - A retry after a return creates a new assignment; a Part C cancellation after a return restores stock exactly once.
  - A damaged return neither restocks nor moves to `ready_for_delivery`; lost goods are never restocked.
  - A COD failure does not change payment.
  - Races: delivery vs failure, reassignment vs pickup, cancellation vs pickup.
  - Driver mirror synchronization; Part B aggregation; actor authorization.
  - Logical concurrency tests; real DB lock proof stays under VEN-BE-029.
- Part E receipt confirmation / missing confirmation / Admin completion:
  - A. The owning customer confirms a `delivered` vendor order: `customer_receipt_confirmed_at` set, `completed`, `completed_at` set, parent aggregated.
  - B. A foreign customer is refused without changes.
  - C. Vendor, driver and Admin cannot use the customer confirmation endpoint.
  - D. Only `delivered` can be confirmed normally; every other source state is refused.
  - E. The delivery assignment must be lifecycle-consistent (`delivered`, `delivered_at` recorded).
  - F. Repeated customer confirmation: success/no-op, timestamps unchanged.
  - G. Admin-completed vendor order: a later customer confirmation is refused and `customer_receipt_confirmed_at` stays null.
  - H. Admin completion: `delivered → completed`, reason required, `completed_by_admin_id` recorded, `customer_receipt_confirmed_at` null, parent aggregated.
  - I. Customer vs Admin completion race: only one completion source is recorded.
  - J. No stock mutation.
  - K. The reservation stays `committed`.
  - L. No payment mutation.
  - M. Parent aggregation: final all-completed → `completed`; completed + cancelled → `partially_cancelled`.
  - N. `orders.completed_at` set exactly once.
  - O. Timestamp ordering (rule 15).
  - P. Review allowed for a completed vendor order inside a `partially_cancelled` parent (VEN-BE-031).
  - Q. Generic Admin creation cannot create `delivered`/`completed` outside the lifecycle (ADM-BE-007).
  - R. An open dispute blocks confirmation and Admin completion, once the dispute workflow exists.
  - S. Rollback if aggregation fails: vendor order, confirmation/audit fields and parent unchanged.

**Backend Handoff Note — Implementation Readiness Review (BACKEND HANDOFF NOTE / SUGGESTED IMPLEMENTATION PLAN)**
After Parts A–E, a read-only implementation readiness review was performed:
- It concluded that Parts A–E form a coherent proposed Phase 1 lifecycle, with no contradiction found and no unresolved item blocking the normal A–E lifecycle.
- It produced suggested migrations, service boundaries, routes, lock ordering, transaction boundaries, suggested implementation phases and test suites.
- Points the team will want to verify first:
  - the current Vendor Accept/Reject lock the vendor order without locking the parent order first;
  - `markPreparing` / `markReady` run without a transaction or lock, and `markReady` also accepts `accepted` as a source state;
  - `VendorOrder::deliveryAssignment()` is an unordered `hasOne` that cannot represent assignment history;
  - there are no driver lifecycle routes;
  - no `OrderStatusAggregator` exists.
- This is handoff material for the Palgoals Backend Team. The suggested implementation phases are **not** the current workstream's implementation plan, and no Backend implementation resulted from the readiness review.

**Backend Handoff Note — Local MySQL schema drift (BACKEND HANDOFF NOTE — PALGOALS TEAM)**
The read-only readiness inspection detected local MySQL schema drift:
- The migration files and the inspected local MySQL schema are not fully aligned.
- `vendor_orders.customer_receipt_confirmed_at` exists in the current migration definition but was absent from the inspected local MySQL schema.
- The local `orders` schema contained historical status/schema differences (an extra `confirmed_at` column and extra `status` values such as `confirmed` / `partially_delivered`).
- The inspection associated this with create migrations having been edited after they had already run locally (commit `d11972e`).
- SQLite tests rebuild the schema from the migration files and therefore do not expose this drift.
- The inspected lifecycle tables (`orders`, `vendor_orders`, `delivery_assignments`, `stock_reservations`) were empty locally at the time of the review.

Recommendation: the Backend team should verify the schema history of each environment before implementing the proposed VEN-BE-020 lifecycle. Future schema changes should use additive migrations rather than editing previously executed create migrations. This note only reports the issue: no `migrate:fresh`, reconciliation migration, database change or migration edit was made.

**Resolution Notes**
Parts A (Vendor → Delivery → Receipt → Completion), B (Parent Order Aggregation), C (Cancellation + Committed Inventory Restoration), D (Failed Delivery + Physical Return + Retry / Reassignment) and E (Customer Receipt Confirmation + Missing Confirmation + Admin Intervention) were approved as review recommendations and form a proposed contract for Palgoals Backend Team review. They are not implemented and not accepted by the Palgoals Backend Team. Status remains OPEN. Remaining open items are listed under "Still unresolved after Part E".

---

### VEN-BE-021 — Dashboard order buckets omit `delivered` vendor orders

- **ID:** VEN-BE-021
- **Area:** Vendor Backend
- **Priority:** LOW
- **Status:** OPEN
- **Discovered During:** Vendor Dashboard & Navigation Audit.

**Current Behavior**
In `OverviewController`, `orders_in_progress` counts `accepted`, `preparing`, `ready_for_delivery`, `assigned`, `out_for_delivery`, and `orders_completed` counts `completed`. Vendor orders in `delivered` fall into neither bucket. This is related to VEN-BE-020 (today `delivered` is not reached through the normal flow), but it remains a metric-definition gap once that lifecycle exists.

**Expected Behavior**
Every vendor order status maps to an intended dashboard bucket (or is explicitly excluded by an agreed definition).

**SRS / Architecture Reference**
UC-VEN-006 (FR-VEN-014, FR-VEN-015) as applicable. Related: VEN-BE-020.

**Related Routes / Files**
- Route: `vendor.dashboard`
- `app/Http/Controllers/VendorDashboard/OverviewController.php`

**Backend Guidance**
Fix together with the dashboard metric definitions after VEN-BE-020 is agreed.

**Acceptance Criteria**
- `delivered` vendor orders are counted in the agreed bucket.

**Required Tests**
- A `delivered` vendor order is counted in the agreed bucket and in no other.

**Resolution Notes**
—

---

### VEN-BE-022 — `sales_total` has no approved financial definition

- **ID:** VEN-BE-022
- **Area:** Vendor Backend
- **Priority:** MEDIUM
- **Status:** OPEN
- **Discovered During:** Vendor Dashboard & Navigation Audit.

**Current Behavior**
The dashboard `sales_total` sums `vendor_orders.total` for `delivered` and `completed` vendor orders. `total` includes `delivery_fee`; the figure does not account for commission, refunds or returns, and ignores `payment_status`. The business meaning of "sales" is therefore ambiguous.

**Expected Behavior**
`sales_total` (and any other vendor financial figure) follows an approved definition.

**SRS / Architecture Reference**
UC-VEN-007 — متابعة الأداء المالي للمتجر (FR-VEN-016, FR-VEN-017, FR-VEN-018 as applicable), HIRFAH SRS v0.5 (reviewed outside the repository). Related: VEN-BE-018, VEN-BE-020.

**Related Routes / Files**
- Route: `vendor.dashboard`
- `app/Http/Controllers/VendorDashboard/OverviewController.php`
- `app/Models/VendorOrder.php`, `app/Models/Payment.php`, `app/Models/ReturnRequest.php`

**Backend Guidance**
Do not define gross/net revenue in this finding. Agree the definition (statuses counted, delivery fee, commission, refunds/returns, payment status) first, then implement it in one place reused by the dashboard and reports (SRS-001).

**Acceptance Criteria**
- `sales_total` matches the approved definition for representative order/payment/return scenarios.

**Required Tests**
- One test per rule of the approved definition (included/excluded statuses, delivery fee, commission, refunds/returns, payment status).

**Resolution Notes**
—

---

### VEN-BE-023 — Vendor API responses overexpose data

- **ID:** VEN-BE-023
- **Area:** Vendor Backend
- **Priority:** MEDIUM
- **Status:** OPEN
- **Discovered During:** Vendor Post-Login & Approval Flow Audit (deferred audit note); confirmed and expanded during the Vendor Dashboard & Navigation Audit.

**Current Behavior**
Vendor endpoints serialize whole models and relations, returning fields that are potentially unnecessary for the vendor:
- the full parent `Order` (other vendors' totals via `subtotal`/`grand_total`, `payment_method`, `payment_status`, `notes`);
- customer account fields (email, phone, status, last login, etc.);
- the full delivery address, including coordinates and the recipient's phone;
- delivery driver profile fields (e.g. vehicle plate, `approved_by`);
- internal/admin fields such as `approved_by`, `reviewed_by`, `resolved_by`, `admin_note`;
- profile internals such as `commission_rate` where the screen does not need them.

Affected endpoints include `vendor.dashboard`, `profile.show/update`, `orders.index/show`, `reviews.index`, `returns.index/show`, `disputes.index/show` and `commissions.index`.

**Expected Behavior**
Each vendor endpoint returns only the fields the vendor needs for its function, through an explicit response contract.

**SRS / Architecture Reference**
No explicit FR/BR establishes a data-minimisation requirement for these fields. Classification: **Security / Privacy / API-contract finding discovered by backend audit** (not an SRS discrepancy). Previously recorded as a deferred audit note in the Approved Vendor Approval Contract section.

**Related Routes / Files**
- Routes: `vendor.dashboard`, `vendor.dashboard.*`
- `app/Http/Controllers/VendorDashboard/*`
- `app/Models/Order.php`, `Customer.php`, `CustomerAddress.php`, `DeliveryDriverProfile.php`, `VendorProfile.php`, `ReturnRequest.php`, `Dispute.php`

**Backend Guidance**
- Do not remove fields until the per-screen data needs are agreed (e.g. whether the vendor needs the customer address/phone when delivery is assigned by Admin).
- Then define explicit response shapes (e.g. API resources) instead of serializing full models.

**Acceptance Criteria**
- Each vendor endpoint returns only the agreed fields.
- No admin-internal identifiers or other vendors' financial data are exposed.

**Required Tests**
- Response-shape tests per vendor endpoint asserting the absence of the excluded fields.

**Resolution Notes**
—

---

### VEN-BE-024 — Vendor profile city is not validated against its governorate

- **ID:** VEN-BE-024
- **Area:** Vendor Backend
- **Priority:** LOW
- **Status:** OPEN
- **Discovered During:** Vendor Dashboard & Navigation Audit.

**Current Behavior**
`UpdateProfileRequest` validates `governorate_id` and `city_id` independently (`exists`), so a city that does not belong to the selected governorate is accepted.

**Expected Behavior**
The selected city must belong to the selected governorate.

**SRS / Architecture Reference**
UC-VEN-003 (FR-VEN-004 store data), HIRFAH SRS v0.5 (reviewed outside the repository).

**Related Routes / Files**
- Route: `vendor.dashboard.profile.update`
- `app/Http/Requests/VendorDashboard/UpdateProfileRequest.php`
- `app/Models/City.php` (`governorate_id`)

**Backend Guidance**
Validate `city_id` against `governorate_id` (and decide whether a city without a governorate is allowed).

**Acceptance Criteria**
- A city from another governorate is rejected with a validation error.

**Required Tests**
- Mismatched city/governorate → validation error; matching pair → accepted.

**Resolution Notes**
—

---

### VEN-BE-025 — Vendor media upload contract is missing

- **ID:** VEN-BE-025
- **Area:** Vendor Backend
- **Priority:** HIGH
- **Status:** OPEN
- **Discovered During:** Vendor Dashboard & Navigation Audit.

**Current Behavior**
`avatar`, `logo`, `cover_image` and product image `path` values are accepted as plain strings. Product image CRUD manages path records, but there is no vendor-scoped file upload endpoint. The existing Media Library is Admin-oriented (`auth:admin`, and its configuration notes that media is not scoped to the uploader), so it is not a proven vendor-scoped upload contract. This blocks a proper Vendor Store and Product UI.

**Expected Behavior**
The backend provides a usable, vendor-scoped upload flow for the media the current store and product data contract requires.

**SRS / Architecture Reference**
FR-VEN-004 (store data) and FR-VEN-005/FR-VEN-006 (add/edit product) as applicable to practical store/product management, HIRFAH SRS v0.5 (reviewed outside the repository). The SRS is not claimed to mandate a specific upload endpoint or Media Library architecture; the gap is that the current backend has no usable vendor upload flow for the current data contract/UI.

**Related Routes / Files**
- Routes: `vendor.dashboard.profile.update`, `vendor.dashboard.products.store/update`, `vendor.dashboard.products.images.*`, `media-library.*`
- `app/Http/Requests/VendorDashboard/UpdateProfileRequest.php`, `StoreProductRequest.php`, `UpdateProductRequest.php`, `StoreProductImageRequest.php`, `UpdateProductImageRequest.php`
- `config/media-library.php`

**Backend Guidance**
- Decide the upload architecture (dedicated vendor endpoint, or a media library with per-owner scoping) before building store/product forms.
- Any solution must scope files to the owning vendor and validate type and size server-side.
- Do not open the current Admin Media Library to vendors as-is.

**Acceptance Criteria**
- An approved vendor can upload store and product media through a vendor-scoped flow and reference the results in profile and product data.
- A vendor cannot read, replace or delete another vendor's or Admin media.

**Required Tests**
- Upload by an approved vendor succeeds with valid files and rejects invalid type/size.
- Cross-vendor access to uploaded media is denied.
- Unapproved vendors cannot upload (VEN-BE-001 gate).

**Resolution Notes**
—

---

### VEN-BE-026 — Vendor review visibility needs a contract decision

- **ID:** VEN-BE-026
- **Area:** Vendor Backend
- **Priority:** REVIEW
- **Status:** OPEN
- **Discovered During:** Vendor Dashboard & Navigation Audit.

**Current Behavior**
`vendor.dashboard.reviews.index` returns the vendor's and its products' reviews in every status by default, including `pending` and `hidden` reviews, unless a `status` filter is passed.

**Expected Behavior**
A decided visibility rule for which review statuses (and which fields) a vendor may see.

**SRS / Architecture Reference**
To be linked.

**Related Routes / Files**
- Route: `vendor.dashboard.reviews.index`
- `app/Http/Controllers/VendorDashboard/ReviewController.php`
- `app/Http/Controllers/Store/ReviewController.php`, `app/Http/Controllers/Dashboard/ReviewManagementController.php`

**Backend Guidance**
Do not decide visibility in this finding; record the decision, then enforce it server-side.

**Acceptance Criteria**
- The vendor review listing follows the decided visibility rule.

**Required Tests**
- Reviews in each status are shown or hidden per the decided rule.

**Resolution Notes**
—

---

### VEN-BE-027 — Vendor response deadline is not enforced

- **ID:** VEN-BE-027
- **Area:** Vendor Backend
- **Priority:** REVIEW
- **Status:** OPEN
- **Discovered During:** Vendor Dashboard & Navigation Audit.

**Current Behavior**
Checkout sets `vendor_orders.vendor_response_due_at` (now + 1 day), and the Admin dashboard highlights orders close to it. However, the vendor `accept` (and `reject`) action does not check it: a vendor can still accept after the timestamp has passed, and nothing happens automatically at expiry.

**Expected Behavior**
A decided rule for what happens when the vendor response deadline passes, and enforcement of that rule.

**SRS / Architecture Reference**
Backend Scenario Review (docx, section 6 and conclusion): the approved vendor order lifecycle includes a 24-hour window with an Admin alert before it expires, and Admin may intervene when confirmation is missing. The available documentation does not specify that a vendor action must be refused after the deadline, so enforcement behavior is a decision, not a confirmed defect. SRS rule to be linked.

**Related Routes / Files**
- Routes: `vendor.dashboard.orders.accept`, `vendor.dashboard.orders.reject`, `customer.checkout.store`
- `app/Http/Controllers/VendorDashboard/OrderController.php`
- `app/Http/Controllers/Store/CheckoutController.php`
- `app/Http/Controllers/Dashboard/AdminDashboardController.php` ("needs attention")

**Backend Guidance**
Agree the expiry behavior (block late vendor action, auto-transition, Admin-only resolution, or alert only) before implementing anything.

**Acceptance Criteria**
- Vendor actions and order state after the deadline follow the decided rule.

**Required Tests**
- Vendor action before and after the deadline behaves per the decided rule.

**Resolution Notes**
—

---

### VEN-BE-028 — Vendor list endpoints accept an unbounded `per_page`

- **ID:** VEN-BE-028
- **Area:** Vendor Backend
- **Priority:** LOW
- **Status:** OPEN
- **Discovered During:** Vendor Dashboard & Navigation Audit.

**Current Behavior**
Vendor list endpoints read `per_page` from the request without an upper bound (`$request->integer('per_page', …)`), so a client can request arbitrarily large pages.

**Expected Behavior**
Page size is limited to an agreed maximum.

**SRS / Architecture Reference**
API hardening. To be linked.

**Related Routes / Files**
- Routes: `vendor.dashboard.products.index`, `orders.index`, `reviews.index`, `returns.index`, `disputes.index`, `commissions.index`
- `app/Http/Controllers/VendorDashboard/*`

**Backend Guidance**
Clamp `per_page` to an agreed range in one shared place.

**Acceptance Criteria**
- Requests above the maximum return at most the maximum page size.

**Required Tests**
- `per_page` above the maximum is clamped on each vendor list endpoint.

**Resolution Notes**
—

---

### VEN-BE-029 — Stock, reservation and checkout lifecycle has no automated tests

- **ID:** VEN-BE-029
- **Area:** Vendor Backend
- **Priority:** HIGH
- **Status:** OPEN
- **Discovered During:** VEN-BE-019 Inventory Lifecycle Contract Audit.

**Current Behavior**
No automated test covers stock availability, reservation creation, the checkout endpoint's order/reservation behavior, vendor rejection releasing reservations, stock commitment, concurrency, or low-stock metrics. Existing checkout-related tests only check redirects and the static checkout page; `VendorApprovalEnforcementTest` only checks the approval gate on these routes. The tests run on SQLite, where `lockForUpdate` has no effect, so the checkout locking behavior is also unproven.

**Expected Behavior**
The stock/reservation/checkout lifecycle is covered by automated tests that protect the approved stock contract (VEN-BE-019) and the checkout behavior.

**SRS / Architecture Reference**
UC-VEN-004 (FR-VEN-008), HIRFAH SRS v0.5 (reviewed outside the repository). Approved stock contract recorded in VEN-BE-019.

**Related Routes / Files**
- Routes: `customer.cart.items.store`, `customer.cart.items.update`, `customer.checkout.store`, `vendor.dashboard.orders.accept`, `vendor.dashboard.orders.reject`, `vendor.dashboard`, `vendor.dashboard.products.*`
- `app/Http/Controllers/Store/CartController.php`, `CheckoutController.php`
- `app/Http/Controllers/VendorDashboard/OrderController.php`, `ProductController.php`, `OverviewController.php`
- `tests/Feature/StorefrontCheckoutPageTest.php`, `tests/Feature/StorefrontCartPageTest.php`, `tests/Feature/VendorDashboardBackendTest.php`

**Backend Guidance**
- Write the tests together with the VEN-BE-019 implementation, against its acceptance criteria.
- Decide how locking/concurrency is verified given the SQLite test database (e.g. logical over-reservation tests plus a database-specific check where needed).

**Acceptance Criteria**
- Tests exist and pass for every VEN-BE-019 acceptance criterion and for checkout's order/reservation creation.

**Required Tests**
- Cart add/update and checkout refuse quantities above available stock.
- Checkout creates the expected order, vendor orders, items and `reserved` reservations.
- Reservations cannot exceed available stock across successive checkouts.
- Reject releases; Accept commits and decrements once; repeated Accept does not double-deduct.
- Low-stock metrics use available stock.

**Resolution Notes**
Partially addressed by the VEN-BE-019 implementation; stays **OPEN**.
- Added `tests/Feature/StockReservationLifecycleTest.php` (now 28 tests after the VEN-BE-019 final correctness fix), covering every listed Required Test and each VEN-BE-019 acceptance criterion at the logical level.
- Remaining: the Backend Guidance decision on verifying real row-lock concurrency is still open. The suite runs on SQLite, where `lockForUpdate` is a no-op, so "two checkouts cannot over-reserve" is proven only for successive requests, not for truly concurrent ones on MySQL.

---

### VEN-BE-030 — Checkout uses the cart's stale unit price

- **ID:** VEN-BE-030
- **Area:** Vendor Backend
- **Priority:** MEDIUM
- **Status:** OPEN
- **Discovered During:** VEN-BE-019 Inventory Lifecycle Contract Audit.

**Current Behavior**
`CartItem.unit_price` is captured when the item is added (or merged from a guest cart). Checkout uses that stored `unit_price` for order items, vendor order subtotals and the order total, and does not revalidate it against the product's current `price`. If the vendor changes the price after the item is added, the order is created at the old price.

**Expected Behavior**
Checkout applies an agreed price rule for cart items whose product price has changed. Which rule applies is a contract decision, not decided in this finding:
- update the cart price automatically;
- require the customer to reconfirm;
- preserve the historical cart price;
- reject the checkout.

**SRS / Architecture Reference**
FR-VEN-009 (vendor manages product prices), HIRFAH SRS v0.5 (reviewed outside the repository), as applicable. The checkout price rule itself is a contract decision. Product sellability at checkout is tracked in VEN-BE-014.

**Related Routes / Files**
- Routes: `customer.cart.items.store`, `customer.checkout.store`, `vendor.dashboard.products.update`
- `app/Http/Controllers/Store/CartController.php` (`store`, guest-cart merge)
- `app/Http/Controllers/Store/CheckoutController.php`
- `app/Models/CartItem.php`

**Backend Guidance**
- Agree the price rule first; then apply it at checkout inside the existing transaction.
- Keep the order item price immutable once the order is created.

**Acceptance Criteria**
- A price change between add-to-cart and checkout is handled according to the agreed rule.

**Required Tests**
- Product price changes after add-to-cart → checkout behaves per the agreed rule.
- Unchanged price → checkout unaffected.

**Resolution Notes**
—

---

### VEN-BE-031 — Review eligibility incorrectly depends on Parent Order completion

- **ID:** VEN-BE-031
- **Area:** Vendor Backend
- **Priority:** MEDIUM
- **Status:** OPEN
- **Discovered During:** VEN-BE-020 Contract Decision Analysis — Part E (Customer Receipt Confirmation + Missing Confirmation + Admin Intervention).

**Current Behavior**
`Store\ReviewController@store` requires **both** the parent `Order.status = completed` and the selected `VendorOrder.status = completed`. Under the proposed VEN-BE-020 Part B aggregation, a vendor order may validly be `completed` while its parent order is `partially_cancelled` because another vendor order was rejected or cancelled. The customer therefore cannot review a vendor whose individual fulfilment was successfully completed.

**Expected Behavior**
Review eligibility for a vendor experience is based on the specific vendor order being `completed` plus customer ownership and the review's other valid requirements (VEN-BE-020 Part E rule 18). A `partially_cancelled` parent does not block review of a completed vendor order.

**SRS / Architecture Reference**
Review eligibility is tied to a genuinely completed vendor experience, not to all unrelated vendor branches succeeding (Backend Scenario Review, docx section 13). VEN-BE-020 proposed Phase 1 contract (for Palgoals Backend Team review), Parts B and E.

**Related Routes / Files**
- `app/Http/Controllers/Store/ReviewController.php` (`store`)

**Backend Guidance**
- Base eligibility on the specific vendor order's completion and ownership; leave the other review constraints unchanged.
- Do not fix the separate duplicate-review / missing unique-index issue under this finding.

**Acceptance Criteria**
- Review eligibility uses the specific vendor order's completion plus ownership.
- A `partially_cancelled` parent does not block review of a completed vendor order.
- Other review constraints remain unchanged.

**Required Tests**
- Review allowed for a completed vendor order inside a `partially_cancelled` parent.
- Review still refused for a non-completed vendor order and for a foreign customer.
- Regression tests for the existing review constraints.

**Resolution Notes**
—

---

### ADM-BE-001 — Non-vendor password-reset emails use the general Fortify reset route

- **ID:** ADM-BE-001
- **Area:** Admin Backend
- **Priority:** HIGH
- **Status:** OPEN
- **Discovered During:** VEN-BE-002 fix.

**Current Behavior**
Admin password-reset emails, and according to the current review also customer and delivery-driver emails, still use Laravel's default `ResetPassword` link: Fortify's general `/reset-password/{token}` (`password.reset`, web `users` broker), instead of each account type's own reset route (`admin.password.reset`, `customer.password.reset`, `delivery-driver.password.reset`). Their tokens are issued by their own brokers, so the emailed link cannot complete the reset. This is an extension of **ARCH-03**. It was intentionally not fixed in VEN-BE-002, which was scoped to vendors only; `AppServiceProvider::configurePasswordResetLinks()` deliberately keeps the framework default for every non-vendor account.

**Expected Behavior**
Each account type's reset email links to that type's own reset flow, and the token can only be used against that type's broker.

**SRS / Architecture Reference**
ARCH-03 (existing technical debt). SRS reference to be linked.

**Related Routes / Files**
- Routes: `admin.password.email`, `admin.password.reset`, `admin.password.update`; `customer.password.*`; `delivery-driver.password.*`; Fortify `password.reset`
- `app/Http/Controllers/Auth/AdminPasswordResetLinkController.php`
- `app/Http/Controllers/Auth/AdminNewPasswordController.php`
- `app/Http/Controllers/Auth/AccountPasswordResetLinkController.php`
- `app/Http/Controllers/Auth/AccountNewPasswordController.php`
- `app/Providers/AppServiceProvider.php` (`configurePasswordResetLinks`)
- `app/Providers/FortifyServiceProvider.php`
- `tests/Feature/PasswordResetTokenIsolationTest.php` (`test_emailed_reset_link_is_still_the_known_arch_03_route`)

**Backend Guidance**
- Review the routes, brokers, token tables and reset controllers of each account type first; do not assume the final solution before that review.
- Confirm whether the customer flow is meant to stay on Fortify (customer login is the canonical public login) before changing it.
- Whatever mechanism is chosen must keep the vendor link from VEN-BE-002 and the existing per-broker token isolation.

**Acceptance Criteria**
- For each account type in scope, the emailed link opens that type's own reset route with token and email.
- A reset through that link updates only the intended account; cross-type tokens are rejected.
- The ARCH-03 test is updated to the agreed behavior.

**Required Tests**
- Per account type in scope: notification `actionUrl` points at its own reset route.
- Per account type in scope: end-to-end request → link → reset → login with the new password.
- Existing `PasswordResetTokenIsolationTest` and `VendorPasswordResetFlowTest` stay green.

**Resolution Notes**
—

---

### ADM-BE-002 — Admin can create inconsistent Vendor account/approval states

- **ID:** ADM-BE-002
- **Area:** Admin Backend
- **Priority:** HIGH
- **Status:** OPEN
- **Discovered During:** Vendor Post-Login & Approval Flow Audit.

**Current Behavior**
Admin "Create Vendor" validates `status` (`active`, `pending`, `blocked`) and `approval_status` (`pending`, `approved`, `rejected`) independently, so it accepts combinations such as `active + rejected` (which currently gives the vendor full access) or `blocked + approved`. The create form also preselects `approval_status=approved`.

**Expected Behavior**
The admin vendor lifecycle only allows combinations defined by the canonical state contract (see Approved Vendor Approval Contract), with a default that matches the agreed lifecycle.

**SRS / Architecture Reference**
Approved Vendor Approval Contract. To be linked to the SRS.

**Related Routes / Files**
- Routes: `dashboard.vendors.create`, `dashboard.vendors.store`
- `app/Http/Controllers/Dashboard/VendorManagementController.php` (`store`)
- `resources/views/dashboard/vendors/create.blade.php`

**Backend Guidance**
- Review the admin vendor lifecycle first and define which combinations are valid at creation.
- Enforce them server-side in `store`, not only in the form.
- Do not change the Admin UI under this finding until the lifecycle is agreed.

**Acceptance Criteria**
- Invalid status/approval combinations are rejected by the server with a validation error.
- The create form default matches the agreed lifecycle.

**Required Tests**
- Each invalid combination → validation error, no vendor created.
- Each valid combination → vendor created with the expected state.

**Resolution Notes**
—

---

### ADM-BE-003 — Vendor approval/rejection has no transition contract or decision history

- **ID:** ADM-BE-003
- **Area:** Admin Backend
- **Priority:** MEDIUM
- **Status:** OPEN
- **Discovered During:** Vendor Post-Login & Approval Flow Audit.

**Current Behavior**
`dashboard.vendors.approve` and `dashboard.vendors.reject` are always available, with no transition rules: an approved vendor can be approved again, a rejected vendor can be approved, and an approved vendor can be rejected. There is no `rejected_at` and no decision history; `approved_by` is reused to store the admin who rejected. *(Updated after VEN-BE-001:)* rejection originally also set `vendors.status=blocked`; since the VEN-BE-001 fix it sets `approval_status=rejected` with the rejection reason and leaves the account status unchanged, in line with the Approved Vendor Approval Contract. The missing transition contract and decision history described above are unchanged.

**Expected Behavior**
An agreed lifecycle defines which transitions are allowed and what decision information (who, when, reason) must be kept.

**SRS / Architecture Reference**
Approved Vendor Approval Contract (transitions such as `rejected → approved` and `approved → rejected` are not approved yet; rejection does not automatically mean `blocked`). To be linked to the SRS.

**Related Routes / Files**
- Routes: `dashboard.vendors.approve`, `dashboard.vendors.reject`, `dashboard.vendors.show`
- `app/Http/Controllers/Dashboard/VendorManagementController.php` (`approve`, `reject`)
- `resources/views/dashboard/vendors/show.blade.php`
- `database/migrations/2026_09_24_130002_create_vendor_profiles_table.php`

**Backend Guidance**
- First fix the lifecycle and history requirements; do not assume a schema solution now.
- Then enforce allowed transitions server-side. Rejection already no longer blocks the account (done in VEN-BE-001); keep that behavior.

**Acceptance Criteria**
- Only agreed transitions are accepted; others are refused with a clear error.
- Decision information required by the agreed lifecycle is recorded for both approval and rejection.

**Required Tests**
- Each allowed transition succeeds; each refused transition fails without changing state.
- Decision data (actor, time, reason) is stored as agreed.

**Resolution Notes**
—

---

### ADM-BE-004 — Admin driver assignment does not enforce full driver eligibility

- **ID:** ADM-BE-004
- **Area:** Admin Backend
- **Priority:** HIGH
- **Status:** OPEN
- **Discovered During:** VEN-BE-020 Vendor Order Lifecycle Contract Audit.

**Current Behavior**
`VendorOrderManagementController@assignDriver` validates `delivery_driver_id` with `exists:delivery_drivers,id` where `status = active` only. It does not check the driver profile's `approval_status = approved` or `is_available = true`. A self-registered driver is created with `status = active` and `approval_status = pending`, so an unapproved or unavailable driver can be assigned. The Admin vendor-order page filters the driver list correctly, but the server does not enforce it.

**Expected Behavior**
A driver can be assigned only when account `status = active`, profile `approval_status = approved` and `is_available = true`, enforced server-side regardless of the Admin UI.

**SRS / Architecture Reference**
VEN-BE-020 proposed Phase 1 contract (for Palgoals Backend Team review), Part A (driver eligibility rule). Backend Scenario Review (docx, section 7): manual driver assignment by Admin in Phase 1.

**Related Routes / Files**
- Route: `dashboard.vendor-orders.assign-driver`
- `app/Http/Controllers/Dashboard/VendorOrderManagementController.php` (`assignDriver`, `show` driver list)
- `app/Models/DeliveryDriver.php`, `app/Models/DeliveryDriverProfile.php`
- `app/Actions/Auth/CreateAccountUser.php` (driver self-registration state)

**Backend Guidance**
- Apply the eligibility rule in the assignment validation (one shared rule/scope reused by the driver list and the server check).
- Implement together with the VEN-BE-020 assignment rule (`ready_for_delivery` only); reassignment/history is not decided yet.

**Acceptance Criteria**
- Assigning a driver who is not active, not approved, or not available is refused with a validation error and changes nothing.
- Assigning an eligible driver to a `ready_for_delivery` vendor order succeeds per VEN-BE-020 Part A.

**Required Tests**
- Ineligible driver: `status` not active → refused.
- Ineligible driver: `approval_status` pending or rejected → refused.
- Ineligible driver: `is_available = false` → refused.
- Driver without a profile → refused.
- Eligible driver → assignment created and vendor order `assigned`.

**Resolution Notes**
—

---

### ADM-BE-005 — Admin can set the parent order lifecycle and payment status independently of its vendor orders

- **ID:** ADM-BE-005
- **Area:** Admin Backend
- **Priority:** HIGH
- **Status:** OPEN
- **Discovered During:** VEN-BE-020 Contract Decisions — Part B (Parent Order Aggregation).

**Current Behavior**
`dashboard.orders.update-status` lets an Admin set `Order.status` to any of `pending`, `processing`, `completed`, `partially_cancelled`, `cancelled` from any current status (`any → any`). It does not synchronize the vendor orders, has no inventory side effects (reservations are not released), and only sets `completed_at` when the new value is `completed`. `dashboard.orders.update-payment-status` similarly sets `orders.payment_status` to any value. An Admin can therefore mark an order `completed` while its vendor orders are still `pending`, or `cancelled` while they are still active, and once derived aggregation exists the next vendor-order transition would silently overwrite the manual value.

**Expected Behavior**
`Order.status` is a derived aggregate of its vendor orders (VEN-BE-020 Part B) and is not editable through an unrestricted generic `any → any` editor. Any future Admin override is an explicit, approved mechanism with defined cases, authorization, transition rules, side effects and audit/history.

**SRS / Architecture Reference**
VEN-BE-020 proposed Phase 1 contract (for Palgoals Backend Team review), Part B (parent order is a derived aggregate). Backend Scenario Review (docx, section 10): order, vendor-order, delivery and payment statuses stay independent, and any linked transition needs a clear business rule. The payment-status part depends on the still-unresolved payment-status source of truth (`orders.payment_status` vs `payments.status`), to be reviewed later.

**Related Routes / Files**
- Routes: `dashboard.orders.update-status`, `dashboard.orders.update-payment-status`
- `app/Http/Controllers/Dashboard/OrderManagementController.php` (`updateStatus`, `updatePaymentStatus`)
- `resources/views/dashboard/orders/show.blade.php`
- `app/Models/Order.php`, `app/Models/Payment.php`

**Backend Guidance**
- Remove or restrict the generic parent lifecycle editor when Part B aggregation is implemented; do not decide an override mechanism here.
- Do not resolve the payment-status source of truth under this finding; keep `update-payment-status` for the later payment review.

**Acceptance Criteria**
- An Admin cannot set `Order.status` to a value that contradicts the derived aggregate of its vendor orders.
- Any retained Admin action on the parent follows an approved, audited contract.
- The payment-status behavior follows the decision of the later payment review.

**Required Tests**
- An Admin attempt to set a parent status contradicting its vendor orders is refused (or the route no longer exists).
- Parent status always equals the derived aggregate after Admin actions.

**Resolution Notes**
—

---

### ADM-BE-006 — Admin can attach a header-only vendor order to an existing customer order

- **ID:** ADM-BE-006
- **Area:** Admin Backend
- **Priority:** MEDIUM
- **Status:** OPEN
- **Discovered During:** VEN-BE-020 Contract Decisions — Part B (Parent Order Aggregation).

**Current Behavior**
`dashboard.vendor-orders.store` creates a vendor order header (amounts, status, timestamps) for any existing `order_id`, including a real customer storefront order, without order items or stock reservations, and with any initial status. Such a vendor order joins the parent order's children and can interfere with the derived parent aggregation of VEN-BE-020 Part B (e.g. a header-only `pending` vendor order keeps a customer's order `pending`/`processing`).

**Expected Behavior**
Header-only vendor orders cannot distort a customer order's derived lifecycle; any manual vendor-order creation follows an explicitly supported workflow.

**SRS / Architecture Reference**
VEN-BE-020 proposed Phase 1 contract (for Palgoals Backend Team review), Part B (no hidden special-casing of header-only vendor orders in the aggregator; attaching them to customer storefront orders is not approved).

**Related Routes / Files**
- Routes: `dashboard.vendor-orders.create`, `dashboard.vendor-orders.store`
- `app/Http/Controllers/Dashboard/VendorOrderManagementController.php` (`store`, `timestampsForStatus`)
- `resources/views/dashboard/vendor-orders/create.blade.php`

**Backend Guidance**
The future resolution must decide whether Admin manual vendor-order creation is removed, restricted to a legitimate non-storefront context, or represented through an explicit supported workflow. Do not add special exclusions to the aggregator.

**Acceptance Criteria**
- Admin cannot attach a header-only vendor order to a customer storefront order unless an approved workflow allows it.
- Parent aggregation is never affected by an unsupported manual vendor order.

**Required Tests**
- Attaching a header-only vendor order to a storefront order is refused (or follows the approved workflow).
- The parent status of a storefront order is unaffected by refused attempts.

**Resolution Notes**
—

---

### ADM-BE-007 — Admin generic VendorOrder creation can bypass the approved lifecycle

- **ID:** ADM-BE-007
- **Area:** Admin Backend
- **Priority:** HIGH
- **Status:** OPEN
- **Discovered During:** VEN-BE-020 Contract Decision Analysis — Part E (Customer Receipt Confirmation + Missing Confirmation + Admin Intervention).

**Current Behavior**
`dashboard.vendor-orders.store` can create vendor orders directly in `delivered` or `completed` states and synthesizes the lifecycle timestamps (`timestampsForStatus`: `accepted_at`, `ready_at`, `delivered_at`, `completed_at`). This bypasses the delivery-assignment lifecycle, customer receipt confirmation, the Admin completion audit, parent aggregation, and the lifecycle/inventory invariants of VEN-BE-020.

**Expected Behavior**
Generic Admin CRUD cannot manufacture advanced lifecycle states; `delivered` and `completed` are reached only through the approved lifecycle services (VEN-BE-020 Part E rule 22).

**SRS / Architecture Reference**
VEN-BE-020 proposed Phase 1 contract (for Palgoals Backend Team review) (Parts A, B and E). Parent order lifecycle editing is tracked in ADM-BE-005; header-only vendor orders attached to customer orders in ADM-BE-006.

**Related Routes / Files**
- Routes: `dashboard.vendor-orders.create`, `dashboard.vendor-orders.store`
- `app/Http/Controllers/Dashboard/VendorOrderManagementController.php` (`store`, `timestampsForStatus`)
- `resources/views/dashboard/vendor-orders/create.blade.php`

**Backend Guidance**
- Whether generic vendor-order creation is removed entirely or restricted to a safe initial state is not decided here; the chosen behavior must be explicitly constrained.
- Never fabricate completion sources (`customer_receipt_confirmed_at`, `completed_by_admin_id`) or lifecycle timestamps.

**Acceptance Criteria**
- Generic Admin CRUD cannot manufacture advanced lifecycle states.
- `delivered`/`completed` are reached only through approved lifecycle services.
- Safe initial/manual creation behavior is explicitly constrained.
- Regression tests prove the lifecycle bypass is impossible.

**Required Tests**
- Admin generic creation in `delivered` or `completed` is refused (or the path no longer exists).
- No vendor order reaches `delivered`/`completed` without the corresponding lifecycle service and audit data.

**Resolution Notes**
—

---

### SRS-001 — Vendor reports backend is missing

- **ID:** SRS-001
- **Area:** SRS/Implementation discrepancy
- **Priority:** HIGH
- **Status:** OPEN
- **Discovered During:** Vendor Dashboard & Navigation Audit.

**Current Behavior**
There is no vendor reports backend: no reports route, no reports controller or service, no report date-range contract, and no report, export or time-series data. No vendor endpoint supports date filtering.

**Expected Behavior**
The vendor can view its store reports as defined by the SRS.

**SRS / Architecture Reference**
UC-VEN-008 — الاطلاع على تقارير المتجر, FR-VEN-019, HIRFAH SRS v0.5 (reviewed outside the repository). This is a confirmed SRS gap: Reports are confirmed Vendor scope, not an optional UI idea. The implementation contract must later be defined from FR-VEN-019 / AC-VEN-008.

**Related Routes / Files**
- Routes: none (missing)
- `app/Http/Controllers/VendorDashboard/*` (no reports controller)
- Related data sources: `vendor_orders`, `order_items`, `commissions`, `reviews`, `return_requests`

**Backend Guidance**
- Do not invent report contents; define the contract from FR-VEN-019 / AC-VEN-008 first.
- Reports depend on the order, stock and financial definitions (VEN-BE-018, VEN-BE-019, VEN-BE-020, VEN-BE-022); build on those, not on the current approximations.

**Acceptance Criteria**
- Vendor reports exist per the approved FR-VEN-019 / AC-VEN-008 contract, scoped to the vendor and behind the approval gate.

**Required Tests**
- Report data per the approved contract, scoped to the vendor.
- Unapproved vendors cannot access reports.

**Resolution Notes**
—
