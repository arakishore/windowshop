# Prompt Outcome Log

## Purpose

This document records important user prompts and the final implementation outcome.

Use it as a running project memory so we can quickly see:

- what the previous prompt/request was
- what was implemented
- what was intentionally not implemented
- what tests or checks were run

Add new entries at the top, newest first, with local time.

## 2026-10-04 — WS-018 Phase 3 storefront Razorpay test-mode checkout

### Goal and frozen rules
- Enable merchant-direct Razorpay for the existing single-shop storefront checkout using only a valid shop-mapped Test PaymentAccount.
- Keep payment success separate from merchant acceptance: verified gateway payment sets Payment=Paid while Order remains Pending.

### Decision and outcome
- Installed the official `razorpay/razorpay` SDK and isolated it behind a provider boundary so initiation and verification can be tested without network calls and reused by a future webhook.
- Online Payment availability fails closed unless the selected shop has an enabled, mapped `razorpay/test` account with a public key and decryptable encrypted secret. Live accounts never fall back into the Test flow.
- The existing authoritative order grand total is converted to integer paise server-side. Every launch/retry creates a distinct PaymentAttempt, then creates and records a Razorpay Order using the merchant's own credentials.
- Browser success is not authoritative. The server loads the customer-owned attempt, uses its attached account/secret, verifies the signature, fetches the payment, and requires matching provider order, amount, currency, and `captured` status.
- Verification locks the attempt and order and is idempotent. It records the provider payment, marks the attempt/order payment paid, preserves Order=Pending, and never repeats stock deduction or order placement.
- Unpaid Online Payment orders cannot be accepted; paid ones continue through the existing merchant workflow. Direct Merchant UPI gating remains intact.
- Dismissal/failure records only attempt state; retry reuses the same WindowShop order and creates a new attempt. Webhooks, refunds, Live payment execution, and settlement handling remain deferred.

### Key areas and verification
- Payment provider/services, storefront checkout controller/routes/views, payment-method resolver, merchant acceptance gating, Composer manifests, and focused checkout/order tests.
- Focused coverage verifies fail-closed availability, Test-only resolution, authoritative amount/minor units, multiple attempts, signature/provider-field rejection, captured success, idempotency, Pending order state, and merchant acceptance gating.

## 2026-10-03 18:43 IST - Safe bulk permanent deletion of product variants

### Goal
Add one checkbox-driven `Delete Selected` action to the shared Admin/Merchant Variants & Inventory table without adding per-row delete actions or risking transaction/history data.

### Decisions and Outcome
- The existing row/select-all checkboxes drive one disabled-until-selected bulk action beside `Apply to Selected` and `Apply to All`. Bootbox confirmation includes the selected count, permanence warning, protected-history rule, and `Cancel` / `Delete Variants` actions.
- Each selected ID is validated as belonging to the current product. Variants are processed independently inside a transaction and locked before reference checks, allowing safe rows to be deleted while protected rows remain unchanged.
- Order items, refund items, exchange/return items, and product reviews are protected history. Active cart items and variant promotion targets are protected operational references. No referenced row is deleted or nulled by this workflow.
- Only unused variants are force-deleted; their variant-owned attribute rows may cascade. If the default is deleted, the existing default-selection service promotes the next active variant.
- Generation intentionally sees a hard-deleted combination as missing and can recreate it later with a new variant identity and the established zero-stock generation defaults.
- Admin and Merchant use the same request, service logic, shared view, and result wording. Mixed results report deleted and protected counts; an all-protected result uses a warning flash.

### Verification
- Full `AdminProductVariantGenerationTest` and `MerchantProductManagementTest`: 53 tests, 299 assertions passed.
- Regression coverage verifies the single bulk UI, disabled initial state, safe deletion, history preservation, partial results, default reassignment, regeneration, cart protection, foreign-ID rejection, and Merchant endpoint.
- Pint, Blade compilation, inline JavaScript syntax, route registration, PHP syntax, and `git diff --check` passed.
- Browser verification remained pending because no browser surface was available in the session.

## 2026-10-03 17:55 IST - Storefront Color x Size variant synchronization

### Goal
Continue the storefront Color/gallery fix so Size choices and selected variant metadata follow the actual active, sellable Color x Size combinations.

### Decision and Outcome
- The product-detail presenter now exposes a selection matrix built only from the same active, sellable variants already loaded for the storefront. No variant status, stock, pricing, or cart business rule changed.
- Selecting a Color disables Size values without a matching active variant, updates each available Size with that exact combination's variant/price/availability metadata, and preserves the selected Size when valid. If it is invalid, selection moves to the first valid Size.
- The main Size picker and sticky Size selector are synchronized. Inactive combinations are not serialized into the storefront contract and cannot be selected.
- Each serialized variant carries the canonical availability guard's `allowed` result as `can_add_to_cart`; the browser does not recalculate purchase eligibility.
- Main and sticky Add-to-Cart CTAs consume that same selected-variant metadata. They switch immediately between enabled `Add To Cart` and disabled `Out of Stock`, preserve their price markup, and retain the canonical availability message. Loading state is now `loading OR not purchasable`, so ending a request cannot re-enable an unavailable variant.
- Variant changes clear only the temporary cart-message lock before applying the newly selected variant's canonical message, preventing stale success/error availability text from surviving a selection change.

### Verification
- Product listing, Add-to-Cart, availability, quantity, and variant-generation suites passed: 95 tests, 649 assertions. Coverage includes inactive Red/L exclusion and active zero-stock Red/XL serialized with `can_add_to_cart=false`.
- Pint, Blade compilation, inline/external JavaScript syntax checks, and `git diff --check` passed.
- Browser verification was unavailable because this session exposed no browser surface; manual interaction testing remains required.

## 2026-10-03 16:00 IST - Storefront gallery runtime contract hardening

Supersedes: Storefront Color gallery prioritization on 2026-10-03 15:25 IST only as to the browser implementation mechanism; the complete-gallery prioritization rule remains authoritative.

### Finding and Outcome
- Live rendered HTML confirmed that the prior backend supplied all five active image URLs for every Color. The runtime risk was the per-Color array contract combined with removing and re-appending detached slide arrays through Swiper.
- The page now serializes one complete structured active-image dataset containing stable image IDs, URLs, and assigned attribute-value IDs. A standalone client ordering function moves selected-Color images first, or Entire Product images first when no match exists, without removing any other image.
- Main and thumbnail wrapper contents are replaced with the complete ordered slide sets, after which both Swipers explicitly recalculate and update. Drift and the sticky image are refreshed as before; PhotoSwipe continues to target the rebuilt main-gallery anchors.
- `ProductImageService::galleryForVariant()` remains unchanged and strict for its existing callers.

## 2026-10-03 15:25 IST - Storefront Color gallery prioritization

Supersedes: Storefront color-assigned product galleries on 2026-10-03 15:10 IST.

### Decision and Outcome
- Selecting a Color controls gallery priority rather than filtering gallery membership. Selected-Color images appear first, followed by every other active product image in deterministic `sort_order`/ID order.
- If a Color has no assigned image, active Entire Product images are preferred first; all other active images remain available afterward.
- `ProductImageService::galleryForVariant()` retains its established strict/filtering semantics for admin and other callers. Complete-gallery composition is storefront-specific.
- Initial/default variant selection still determines the first image. Inactive images and duplicate image rows remain excluded.

## 2026-10-03 15:10 IST - Storefront color-assigned product galleries

### Goal
Make the storefront product-detail gallery follow the selected Color while preserving the existing Product → Attributes → Variants architecture and merchant image-management workflow.

### Decision and Outcome
- Reused `ProductImageService::galleryForVariant()` as the single image-selection rule: active images assigned to the variant's configured image attribute, then active Entire Product images, then an applicable unassigned/selected-value primary image.
- The storefront detail presenter now supplies the default variant's gallery for initial render plus keyed galleries for each available Color. Selecting a Color replaces the main and thumbnail Swiper slides immediately without a page reload.
- Images exclusive to another Color and inactive images are excluded; existing sort order, generated storage URLs, zoom, lightbox, thumbnail, and sticky-image behavior are retained.
- Initial Color and Size labels now reflect the actual default storefront variant instead of assuming the first displayed option.
- No schema, upload flow, Product Group, or variant pricing/stock/cart rules changed. Query-string Color selection (for example `?color=red`) remains a compatible future enhancement and was intentionally not implemented.

### Verification
- Focused storefront listing, product image, add-to-cart, and variant generation suites passed: 92 tests, 615 assertions.
- Browser automation was attempted but no browser surface was available in the execution environment.

## 2026-10-03 IST - Fresh-install notification template UUID seeding fix

### Goal
Fix the pre-existing MySQL `migrate:fresh --seed` failure where `NotificationTemplateSeeder` attempted to insert a template without the required application-generated UUID.

### Decision and Outcome
- `DatabaseSeeder` intentionally disables model events, so UUID-backed seeders must supply UUIDs explicitly instead of relying on the shared `HasUuid` creating hook.
- `NotificationTemplate` now permits UUID mass assignment and `NotificationTemplateSeeder` supplies a UUID only in `firstOrCreate` creation values, preserving existing template UUIDs and Admin customizations on rerun.
- Added focused coverage that runs the seeder with model events disabled and verifies UUID presence, uniqueness, idempotency, and customization preservation.
- No schema change was required. The real disposable-MySQL fresh-install rerun remains a separate verification step.

## 2026-10-03 IST - Pre-production removal of legacy Admin Settings architecture

Supersedes: the temporary legacy compatibility/fallback decisions recorded in Canonical System Settings Refactor Stages 2-5 on 2026-10-03.

### Goal
Make `system_settings` the sole global settings architecture before WindowShop's first production deployment.

### Decisions
- Removed the `admin_settings` runtime model, service, initializer, seeder, and `SystemSettingService` fallback. Missing canonical values now resolve directly to established application or notification-catalogue defaults.
- Removed both the legacy table-creation migration and its canonical backfill migration together so a fresh installation never creates or depends on `admin_settings`.
- Seed all ten foundational regional/currency settings as physical canonical rows. Runtime defaults remain a safety net, while the non-destructive seeder preserves every configured value on rerun.
- Merchant and shop scoped settings remain separate and unchanged.

### Implementation Outcome
- Fresh isolated SQLite migrations create `system_settings` without creating `admin_settings`.
- Canonical regional, currency, notification, email, and SMTP behavior no longer has an executable legacy dependency.
- Historical entries below remain unchanged as an audit trail of the staged refactor.

### Verification
- Disposable in-memory migration/seeder verification and focused canonical settings tests passed.
- Pint, PHP syntax checks, repository legacy-reference audit, and `git diff --check` were completed before handoff.

## 2026-10-03 IST - Canonical System Settings Refactor — Stage 6B SMTP Secret Payload Marker

### Goal
Replace the temporary use of `SystemSetting.description` as SMTP credential state with the accepted versioned encrypted-payload format before persistent migration.

### Decision and Outcome
- New canonical SMTP passwords are wrapped internally as `canonical-smtp:v1:<password>` and the complete payload is encrypted exactly once through the existing protected secret API.
- Runtime explicit secret access accepts only the recognized versioned payload, strips the prefix internally, and supplies only the actual password to the mail transport.
- Unmarked Stage 2 ciphertext, an empty versioned payload, invalid ciphertext, missing/inactive/tombstoned rows, and failed decryption are all treated as not configured.
- Blank submissions preserve existing ciphertext; replacements create a newly encrypted versioned payload.
- `description` is documentation metadata only. Changing it has no effect on password validity.
- No setting, column, or migration was added or changed.

### Verification
Focused EmailConfigurationSystemSetting, RealEmailDelivery, SystemSettingService, and Stage 2 backfill tests plus Pint, PHP syntax checks, and `git diff --check` passed during completion review.

## 2026-10-03 IST - Canonical System Settings Refactor — Stage 5 Global Email Configuration

### Goal
Move global email delivery, SMTP, sender/reply-to, Admin operational recipient, and transactional email presentation ownership from `admin_settings` to canonical `system_settings` without changing merchant/shop provider settings.

### Decisions
- Non-secret email values resolve by canonical row existence, read-only legacy fallback, then the established application default. Blank, zero, and false canonical values remain authoritative; soft-deleted canonical rows remain tombstones.
- `EmailConfigurationService` retains its public API but now reads and writes through email-specific `SystemSettingService` APIs using the approved `notifications.email.*` keys.
- SMTP passwords never use legacy fallback. A newly entered password is encrypted exactly once through the canonical secret API, stored encrypted/non-public, and read only through explicit secret access.
- Passwords copied by the Stage 2 migration are intentionally not considered runtime-configured until replaced through the protected email settings API. A safe setting-description marker distinguishes newly entered canonical credentials without changing the accepted migration or adding another key.
- Blank password submissions preserve a valid canonical ciphertext. Invalid, unmarked, or undecryptable canonical ciphertext is treated as not configured.
- Email footer social/app settings remain separate from public storefront `social.*` settings.

### Implementation Outcome
- Migrated transport, SMTP non-secret fields, sender/reply-to, `admin_notification_email`, branding, footer, social/app links, and dedicated email-logo persistence to canonical settings.
- Admin Email Settings and Test Email now use canonical configuration and canonical secret access without writing legacy rows or rendering stored passwords.
- `AdminNotificationRecipientResolver` continues to use `EmailConfigurationService`, so the central canonical Admin notification email remains primary with the existing account-recipient fallback when blank/invalid.
- Runtime mailer configuration receives canonical host, port, encryption, username, sender/reply-to, and only a newly configured valid canonical password. Existing error sanitization remains unchanged.

### Verification
- Focused canonical/legacy/tombstone, secret lifecycle, Admin UI, recipient, branding/footer, runtime transport, Test Email, notification delivery, SystemSettingService, and Stage 2 migration tests passed.
- PHP syntax checks, Pint on Stage 5 PHP/test files, and `git diff --check` passed during completion review.

### Deferred
Legacy infrastructure removal and final cleanup remain deferred. Merchant/shop settings, provider modes, provider credentials, additional recipients, and scoped notification overrides were intentionally unchanged.

## 2026-10-03 IST - Canonical System Settings Refactor — Stage 4 Global Notification Preferences

### Goal
Move global/default notification event-channel enablement from `admin_settings` to canonical `system_settings` without changing merchant/shop overrides or migrating email configuration.

### Decisions
- Dynamic canonical keys are formed as `notifications.events.{event}.{channel}.enabled` through `SystemSettingKeys`.
- Global/default resolution is canonical row existence, then read-only legacy `admin_settings` fallback, then the frozen catalogue default. Explicit canonical false values remain authoritative, and soft-deleted canonical rows remain tombstones.
- `NotificationPreferenceResolver` retains the existing hierarchy: mandatory rules, then shop override, then merchant override, then the global/default resolver. Only the final global/default storage ownership changed.
- Global Admin rule writes go only to canonical boolean `system_settings` rows. Reading the rules page creates no legacy preferences.
- SMTP, sender/reply-to, Admin recipient, email branding/footer, and merchant/shop provider configuration remain legacy/scoped and deferred.

### Implementation Outcome
- Added canonical notification preference key generation plus typed read/write APIs to `SystemSettingService`.
- Migrated `NotificationPreferenceResolver::defaultEnabled()`, `setGlobal()`, and `setDefault()` to those APIs.
- Preserved merchant and shop setting services and their precedence unchanged.
- Updated synthetic notification test schemas to provide the canonical tables now required by the resolver, without weakening their behavioral assertions.

### Verification
- Focused canonical preference, catalogue, Admin management, notification foundation, event wiring, merchant/shop override, SystemSettingService, and Stage 2 backfill tests passed.
- PHP syntax checks, Pint on Stage 4 PHP/test files, and `git diff --check` passed during completion review.

### Deferred
`EmailConfigurationService`, SMTP credentials/transport, sender/reply-to, `admin_notification_email`, email branding/footer/social/app links, merchant provider credentials, and legacy-table retirement remain deferred to Stage 5 or later approval.

## 2026-10-03 IST - Canonical System Settings Refactor — Stage 3 Regional and Currency Runtime Migration

### Goal
Move global regional/timezone and currency runtime ownership from `admin_settings` to canonical `system_settings`, while retaining an existence-based, read-only legacy fallback during the transition.

### Decisions
- `default_currency` and `default_timezone` remain the canonical primary keys; the remaining values use the approved `currency.*` and `regional.*` mappings in `SystemSettingKeys`.
- Canonical row existence determines precedence. Active canonical blank and zero values remain authoritative, while a legacy value is read only when no canonical row (including no soft-deleted tombstone) exists.
- The Admin Regional/Currency page reads and writes through `SystemSettingService` only. It no longer invokes `AdminSettingsInitializer`, and normal database seeding no longer calls `AdminSettingsSeeder`.
- Legacy regional/currency infrastructure remains available solely for controlled compatibility fallback and explicit legacy operations. Notification and email consumers remain deferred.
- Settings are resolved before rendering checkout success; Blade templates do not resolve settings services directly.

### Implementation Outcome
- Added canonical currency/regional aggregate reads, validation, updates, defaults, and read-only fallback to `SystemSettingService`.
- Migrated global storefront, cart, checkout, delivery/payment, customer order, merchant POS/order/sales, receipt/activity, notification order presentation, date display, and business-time consumers to `SystemSettingService`.
- Admin updates now write only canonical rows in `system_settings`; viewing or updating the page does not initialize or write regional/currency rows in `admin_settings`.
- Removed `AdminSettingsSeeder` from the normal `DatabaseSeeder` path without removing the legacy table, model, service, initializer, or seeder.

### Verification
- Canonical precedence, absence-only fallback, blank/zero handling, Admin canonical-only writes, no page-triggered legacy initialization, regional formatting, and timezone/business-time behavior: 23 tests passed, 90 assertions.
- Representative storefront currency consumers plus Stage 1/2 regressions: 87 tests passed, 1,031 assertions.
- PHP syntax checks, Pint on changed PHP/test files, and `git diff --check` passed during completion review.
- `BannerLimitServiceTest` remains passing without modification.

### Deferred
`NotificationPreferenceResolver`, `EmailConfigurationService`, SMTP runtime configuration, `admin_notification_email`, email branding/footer, merchant/shop notification settings, and legacy-table retirement remain deferred to later explicitly approved stages.

## 2026-10-03 IST - Canonical System Settings Refactor — Stage 2 Legacy Data Backfill

### Goal
Backfill approved global legacy `admin_settings` values into canonical `system_settings` without migrating runtime consumers, modifying legacy source rows, or exposing secrets.

### Decisions
- Precedence is canonical row existence, then legacy value, then an approved canonical default. Empty strings and stored false/zero values are therefore preserved.
- Static mappings come from `SystemSettingKeys`; dynamic global `notifications.events.*` preferences are migrated consistently. Merchant and shop settings remain untouched.
- A soft-deleted canonical row reserves its globally unique key. The migration leaves that tombstone untouched rather than restoring it or attempting a conflicting duplicate insert.
- Legacy SMTP password ciphertext is copied directly through the query builder, marked `encrypted` and non-public, and is never decrypted or re-encrypted by the migration.
- Regional/currency settings that historically require seeded defaults receive defaults only when neither canonical nor legacy data exists. Optional email/presentation settings are created only from existing legacy values.
- Rollback is intentionally non-destructive because migrated rows cannot be safely distinguished from canonical rows subsequently edited or adopted by runtime code. `down()` does not delete canonical or legacy data.

### Implementation Outcome
- Added `2026_10_03_000001_backfill_admin_settings_into_system_settings.php`.
- The migration safely ensures Localization, Email, and Notifications groups without replacing existing identities or creating duplicates.
- Existing canonical UUIDs, values, configured states, ciphertext, and tombstones survive repeated execution.
- Legacy `admin_settings` and scoped `merchant_settings` / `shop_settings` rows remain unchanged.
- No runtime consumer, fallback, initializer, or legacy model/service was changed in Stage 2.

### Verification
- New Stage 2 migration tests: 6 passed, 34 assertions.
- Combined Stage 1/Stage 2 and directly relevant existing tests: 23 passed, 153 assertions.
- PHP syntax checks, Pint, and `git diff --check` passed.
- The already-classified `BannerLimitServiceTest` remains passing without modification.

### Deferred
All runtime consumer migration, legacy fallback behavior, SMTP/email service migration, and legacy-table retirement remain deferred to later explicitly approved stages.

## 2026-10-03 IST - Canonical System Settings Refactor — Stage 1 Foundation

### Goal
Establish the canonical `system_settings` infrastructure, safe secret handling, non-destructive canonical seeders, and encrypted-value-safe generic Admin UI without yet migrating any legacy `admin_settings` data or consumers.

### Decisions
- The approved legacy-to-canonical key inventory is centralized in `SystemSettingKeys`; existing `default_currency` and `default_timezone` names remain canonical.
- `SystemSettingService` is the single canonical service for typed reads, existence checks, group reads, writes/upserts, and explicit encrypted-secret access.
- The unsafe singleton-local value cache was removed. Reads query current active canonical state so same-request writes, inserts, status changes, and direct existing write paths cannot leave stale values or cached misses.
- Generic reads and group reads never decrypt secrets. Decryption requires the explicit `secret()` API; blank encrypted writes preserve configured ciphertext.
- Canonical seeders may refresh descriptive metadata but must not overwrite existing values. This stage does not backfill, alter, or remove `admin_settings`.
- Generic System Settings pages mask protected values, exclude stored values from search, never repopulate secret inputs, preserve secrets on blank submission, force encrypted rows non-public, and do not accept arbitrary encryption-state changes.

### Implementation Outcome
- Expanded `SystemSettingService` and added centralized `SystemSettingKeys`.
- Hardened `SystemSetting` serialization and the generic Admin System Settings controller/views.
- Made `SystemFoundationSeeder` and `StorefrontBannerSettingSeeder` preserve configured canonical values on rerun.
- Added focused service, secret, UI, mapping, freshness, and seeder-preservation coverage.
- The pre-existing `BannerLimitServiceTest` cache failure now passes without modifying that test or weakening its assertions.

### Verification
- PHP syntax checks passed for all changed PHP files.
- Pint passed for the changed PHP files.
- Focused Stage 1 and directly relevant existing tests: 17 passed, 119 assertions.
- `git diff --check` passed before the final log update and is rerun during completion review.
- Browser verification was attempted, but the computer-use environment exposed no browser and its in-app browser was unavailable.

### Deferred
Legacy backfill and all currency, timezone, notification, SMTP, `admin_notification_email`, and other runtime-consumer migrations remain deferred to later approved stages. The 2026-10-01 Admin Notification Email storage decision is therefore not superseded by Stage 1.

## 2026-10-01 - Central Admin Operational Notification Email

Supersedes: the Admin recipient-resolution portion of `2026-10-01 - Admin Notification for Public Merchant Registration`.

### Goal
Separate the primary recipient for WindowShop administrative/operational email from Admin login-account email addresses while preserving backward compatibility.

### Decision
- `notifications.email.admin_notification_email` in the existing `admin_settings` table is the optional central primary `To` address for Admin-facing email events.
- When the central address is blank, `AdminNotificationRecipientResolver` retains its active Admin/Super Admin account-email fallback. Invalid and duplicate fallback email addresses are excluded.
- The setting affects every event already using `AdminNotificationRecipientResolver`, currently `merchant.registered.admin` and `order.new.admin`; customer and merchant recipient policies are unchanged.
- Template-level CC/BCC remains the only CC/BCC mechanism and continues to deduplicate against the primary `To` address.
- No default or placeholder is seeded. Existing installations continue using role-based recipients until an administrator configures the setting.

### Implementation Outcome
- Added the Admin Notification Email field to Admin > Email Notifications with nullable email validation.
- Extended `EmailConfigurationService` persistence without adding a table or migration.
- Updated the resolver to prefer the configured central email only for the email channel and retain the existing role-based lookup as fallback.

### Verification
- Email delivery/settings, Admin notification management, business-event wiring, notification catalogue/template, and storefront merchant-registration suites: 55 tests passed, 415 assertions.

## 2026-10-01 - Admin Notification for Public Merchant Registration

### Goal
Notify active WindowShop Admin and Super Admin users by email when a merchant profile is successfully created through the public storefront merchant-registration flow, without changing existing merchant/customer notifications or registration behavior.

### Important Decisions
- Added the distinct mandatory email event `merchant.registered.admin`; the existing `merchant.account_created` event remains the merchant-facing acknowledgement.
- `MerchantAccountCreated` now records whether creation originated from the storefront. Only that explicit origin triggers the Admin notification, so Admin-created merchants do not generate the public-registration alert.
- Admin recipients continue to come from `AdminNotificationRecipientResolver`: active Users assigned an active `admin` or `super_admin` role. No email address is hardcoded.
- Delivery remains after the merchant transaction commits and uses `NotificationManager`, the existing email channel, preferences, idempotent delivery claims, and delivery logging. A failed delivery is logged and does not remove or invalidate the merchant.
- The editable seeded template includes merchant identity/status details and links its `Review Merchant` action to the existing `admin.merchants.show` route.
- Added generic optional action-button rendering to the existing transactional email layout; template values and action attributes remain escaped by Blade.

### Implementation Outcome
- New and existing-customer storefront registrations send one Admin notification per resolved Admin recipient.
- Validation failures, duplicate merchants, failed registration transactions, and Admin-created merchants do not send this Admin event.
- Existing merchant registration acknowledgement behavior is unchanged.

### Key Files
- `app/Events/MerchantAccountCreated.php`
- `app/Services/Merchant/MerchantService.php`
- `app/Listeners/DispatchBusinessNotifications.php`
- `config/notification_events.php`
- `app/Services/Notification/NotificationTemplateDefaults.php`
- `app/Notifications/Channels/EmailChannel.php`
- `app/Mail/TransactionalNotificationMail.php`
- `resources/views/emails/transactional-notification.blade.php`
- notification and merchant-registration feature tests

### Verification
- Focused merchant-registration, business-event wiring, notification catalogue/template, and real email-delivery suites: 46 tests passed, 314 assertions.
- Admin notification-management regression suite: 6 tests passed, 86 assertions.
- `php artisan route:list`, PHP syntax checks, and `git diff --check` passed.
- No migration was required. Existing installations must run `php artisan db:seed --class=NotificationTemplateSeeder` to insert the new editable templates.

## 2026-09-28 23:15 IST - Storefront Merchant Registration — Phase 1 Public Registration Foundation

### Topic
Implemented the first public storefront merchant-registration flow for new Users and authenticated existing Customers while preserving the frozen shared-identity, access, suspension, and publication rules.

### Implementation Objective
Provide a public `Sell on {marketplace}` registration entry point that creates or reuses the canonical User, creates one MerchantProfile, assigns the merchant role, initializes existing merchant defaults, and gives the pending merchant immediate panel access without exposing unapproved content publicly.

### Existing Architecture Reused
- `users`, `merchant_profiles`, `auth_roles`, and `auth_user_roles`; the shared `web` authentication session; `MerchantService`; MerchantProfile model initialization hooks; `MerchantAccountCreated`; and the existing notification listener/manager.
- Existing `SystemSettingService` view composition supplies the marketplace name, and existing Merchant Panel/shop-context services remain the operational foundation.

### Important Implementation Decisions
- Added public `GET/POST /sell` and authenticated `/sell/success` routes with a focused storefront form and success page.
- Refactored MerchantService account creation so admin creation keeps its existing behavior while storefront registration shares MerchantProfile creation, role assignment, defaults, transaction handling, and after-commit event dispatch.
- New applicants receive a User with `registration_source = storefront`, an active MerchantProfile, and pending verification. Submitted account/verification status fields are ignored.
- Authenticated existing Users are upgraded in place: their User ID, password, Customer profile/role, and unrelated data are preserved. Existing or soft-deleted MerchantProfiles cannot be duplicated.
- Anonymous registration cannot attach merchant access to an existing email; it returns a sign-in-required validation message and creates no MerchantProfile.
- Pending, rejected, inactive, and suspended merchants can authenticate. Suspended merchants can view status information, while merchant middleware blocks non-safe HTTP methods server-side.
- Added a reusable Merchant Panel warning for pending, submitted, rejected, inactive, and suspended/non-public states, including escaped rejection reasons when present.

### Visibility / Authentication Changes
- Added `MerchantProfile::scopeStorefrontVisible()` requiring account `active` and verification `approved`.
- Applied the centralized eligibility scope to storefront shop discovery/profile lookup, product lists/details/filters, wishlists, and customer product-history loading. Existing shop/product publication requirements still apply.
- Updated merchant authentication and context resolution so inactive and suspended merchant account statuses are login-capable as frozen; verification status `suspended` retains its existing block because that legacy state was not included in the frozen login-status list.

### Changed Areas
- Registration: storefront controller, FormRequest, routes, registration/success views, footer entry point, and storefront registration tests.
- Merchant domain/access: registration source enum, MerchantService, authentication/account/shop-context services, merchant role middleware, layout warning partial, and merchant auth tests.
- Publication: MerchantProfile visibility scope, storefront product/shop/customer/wishlist queries, and storefront visibility regression test.

### Tests / Verification
- Registration, merchant auth, and storefront product visibility: 64 passed, 431 assertions.
- Notification business-event wiring: 13 passed, 50 assertions.
- Route inspection confirmed all three merchant-registration routes.
- `php artisan view:clear` and `php artisan view:cache` passed.
- The existing `StorefrontCustomerAuthPagesTest` was run: 3 tests passed and 1 unrelated pre-existing copy assertion failed because it expects `Phone Number`/`Confirm password:` while the existing customer registration template renders `Mobile Number`/`Confirm Password`. No customer-auth code or copy was changed for that failure.
- Browser verification was attempted, but no in-app or Chrome browser was exposed to the browser-control environment. No live registration records were created.

### Deferred / Known Remaining Gaps
- Subscription plans, Merchant Staff, staff permissions/assignments, KYC/document uploads, detailed approval workflow redesign, and staff-specific POS remain deferred.
- Suspension writes are blocked centrally by HTTP method; a later authorization phase should replace/augment this coarse gate with explicit capability policies and review any mutation incorrectly exposed through safe HTTP methods.
- Verification status `suspended` remains a separate legacy access block pending an explicit business decision.
- Desktop/mobile browser completion of new-user and existing-customer registration remains a manual verification item because browser tooling was unavailable.

### Previous Decision Status
Implements the 2026-09-28 Phase 0 recommendations and the subsequently frozen Merchant Access & Visibility and Shared Identity rules. It does not supersede those entries or introduce Staff/subscription decisions. Public visibility now enforces the authoritative rule: Merchant Active + Verification Approved + Shop Active + normal product publication requirements.

## 2026-09-28 22:49 IST - Storefront Merchant Registration — Staff Membership & Email Identity Rules Frozen

### Topic
Documentation-only extension of the frozen Merchant Staff principles covering email identity, multi-merchant memberships, shop scope, direct staff creation, User reuse, notifications, removal, and suspension scope.

### Decision Being Resolved
How should staff identity and independently scoped merchant relationships behave when one User may work for multiple Merchants and access one or more Shops within each relationship?

### Relevant Existing Architecture
- `users` is the canonical authentication identity, email is already a unique login identifier, and `auth_user_roles` supports multiple global roles per User.
- The prior investigation found no Merchant Staff membership model or shop-assignment mechanism. Current merchant context resolves only the User's owned MerchantProfile, so these rules describe required future behavior rather than existing capability.

### Frozen Decisions
- One email identifies one canonical WindowShop User with one credential set and zero or more Customer, Merchant Owner, or merchant-specific Staff contexts. Usernames will not be introduced for these contexts.
- One User may be staff for multiple Merchants, but each membership belongs to exactly one Merchant and is independently scoped.
- Each membership must support assignment to one or more Shops owned by that Merchant and must never authorize another Merchant's Shop.
- Context selection must distinguish Customer and each merchant-specific Staff workspace without reauthentication; the selected Merchant/Shop authority must be revalidated server-side.
- Merchants add staff directly. There is no accept/decline or pending employee-acceptance workflow.
- An existing email reuses the canonical User and preserves all existing contexts and memberships. A new email may provision one User, but the Merchant must not know or control the employee's permanent password.
- Creating staff access sends an informational account email, not an acceptance request. New-User communication must support a future secure credential-setup process.
- Removing or disabling staff affects only the selected Merchant membership and preserves the User, Customer context, other Merchant memberships, and unrelated roles/contexts.
- Merchant suspension blocks owner and staff operations only for that Merchant; Customer and other Merchant Staff contexts remain independently available. Enforcement remains server-side.

### Affected Future Areas
- Staff membership and shop-assignment domain modeling, identity lookup/reuse, direct staff creation, workspace discovery and switching, merchant-scoped authorization, membership removal, suspension enforcement, account communications, and staff-aware POS access.
- Authentication and context services must resolve a specific authorized membership rather than infer staff authority from a global role or owned MerchantProfile.

### Risks / Dependencies
- Email-based User reuse requires secure identity matching and account-recovery/setup controls to prevent takeover or disclosure of existing accounts.
- Merchant and Shop identifiers stored in session must be revalidated against the selected active membership on every request.
- Membership removal or merchant suspension must invalidate that context's authority without affecting independent contexts or other memberships.
- Shop assignment must enforce that every assigned Shop belongs to the membership's Merchant.

### Items Intentionally Deferred
Actual tables/schema, membership status values and lifecycle, staff role names, detailed permissions, direct-versus-role permissions, shop-assignment storage, selector UI, context persistence, new-User password setup, exact notification wording, and staff-specific POS permissions remain pending. No choices for these items are made here.

### Previous Decision Status
Extends, and does not replace, the 2026-09-28 Shared Identity, Profile Switching & Merchant Staff Principles. It confirms canonical User reuse and server-authorized context switching, freezes multi-Merchant membership and direct staff creation, and narrows the earlier unresolved staff-creation approach by explicitly excluding an acceptance workflow. The earlier visibility and merchant-suspension decisions remain authoritative.

## 2026-09-28 22:36 IST - Storefront Merchant Registration — Shared Identity, Profile Switching & Merchant Staff Principles

### Topic
Documentation of frozen shared-identity and profile-context rules, plus an investigation of the existing Merchant Staff, role, permission, shop-context, and POS authorization architecture.

### Question / Decision Resolved
How should Customer, Merchant Owner, and Merchant Staff identities and contexts coexist, and what staff/membership capabilities already exist before detailed staff permissions are designed?

### Relevant Existing Implementation
- `users` is the canonical identity. `auth_user_roles` is a unique User/role pivot and can assign multiple global roles to one User; `auth_roles`, `auth_permissions`, and `auth_role_permissions` provide generic global role/permission storage.
- Seeded roles are only `super_admin`, `admin`, `merchant`, and `customer`. No owner, manager, cashier, inventory, order-manager, employee, merchant-staff, or shop-staff role is defined.
- `User` has one `merchantProfile` and one `customer`; `merchant_profiles.user_id` is unique. This supports one Customer and one owned MerchantProfile on the same User, but not staff membership.
- Merchant authentication and middleware require the global `merchant` role and resolve the MerchantProfile directly by the authenticated User's `user_id`. `MerchantShopContextService` then selects only active shops owned by that MerchantProfile and stores `merchant_id`, `active_role_id`, and `active_shop_id` in session.
- Storefront customer context already uses `active_role_id` to distinguish Customer from back-office roles. This is partial context infrastructure, not a complete profile selector/switching flow.
- Merchant and POS routes use the same `auth`, `merchant.role`, and (for shop operations) `merchant.active_shop` middleware. POS has no separate employee identity or authorization mechanism.

### Merchant Staff Investigation Findings
- **Existing:** shared Users; multi-role assignment; global role/permission tables; merchant ownership through `merchant_profiles.user_id`; owned-shop selection; session keys for active role/shop; merchant/shop ownership scoping in middleware, services, controllers, and requests.
- **Partial:** generic permissions can be seeded and attached to a global role (currently demonstrated for merchant cancellation-reason permissions), but no general merchant-route permission middleware, policy/gate layer, per-user permissions, or staff-aware enforcement was found.
- **Missing:** no Merchant Staff/Shop Staff table, model, relationship, service, controller, request, route, UI, status/lifecycle, invitation flow, tests, or merchant/shop membership pivot. Therefore there is no current owner-vs-staff distinction, no staff-to-merchant/shop assignment, no multiple-shop staff membership, and no User membership in multiple merchants.
- Current code cannot represent Customer + Merchant Staff or Customer + Merchant Owner + staff of another merchant without new membership/context modeling. Assigning the global `merchant` role alone is insufficient because access resolution still requires that User's own unique MerchantProfile.
- POS has no cashier/employee/manager access model and currently executes as the merchant-profile owner context.
- A suspended merchant cannot use current merchant routes because authentication/context resolution rejects inactive/suspended profiles. No staff bypass exists today because staff does not exist; future staff resolution could create a bypass unless it checks the parent merchant status on every request and mutation.

### Frozen Decisions
- Reuse one User and credentials across Customer, Merchant Owner, and Merchant Staff relationships; preserve existing roles/profiles when adding another context and reject duplicate merchant registration for an existing MerchantProfile.
- Multi-role Users must select and switch authorized contexts without logout; switching is context selection, not impersonation, and requires server-side authorization.
- Merchant Staff has its own User and membership/permission-based access but does not automatically receive a MerchantProfile.
- Removing/disabling staff affects only that merchant/shop access, not the User or independent Customer context. Exact staff lifecycle/status storage remains pending.
- Merchant suspension blocks owner and staff operational mutations for that merchant, but not authentication or independent contexts. Enforcement must be server-side.

### Affected Future Areas
- Registration identity matching and reuse, duplicate-merchant prevention, role/profile attachment, account recovery, and collision handling.
- Context discovery, selection, switching, session state, redirects, navigation, and cross-context logout/session behavior.
- New staff membership and shop-assignment domain modeling; merchant-scoped roles/permissions; invitations and staff lifecycle.
- `MerchantAuthenticationService`, `EnsureMerchantRole`, `EnsureMerchantActiveShop`, `MerchantShopContextService`, merchant routes/controllers/requests/services, POS, policies/middleware, and tests.

### Risks / Dependencies
- Identity matching must avoid both duplicate Users and unsafe account linking/account takeover.
- `active_role_id`, `merchant_id`, and `active_shop_id` are session values and must never be trusted without revalidating current User membership, role, merchant status, shop assignment, and permission on each request.
- Global `auth_roles` alone cannot express merchant-scoped or shop-scoped staff authority; assigning the existing `merchant` role to staff would incorrectly imply owner resolution and broad access.
- Current merchant profile updates/deletion also update or delete the underlying User, which conflicts with preserving independent Customer/staff contexts and will require later implementation review.
- Current merchant login rejects inactive/suspended merchants, conflicting with the previously frozen login rule. Current publication queries also require the previously identified approval-gate review.
- Suspension enforcement must cover every merchant mutation surface, including shops, products, inventory/pricing, promotions, orders, POS, settings, customers, banners, collections, catalogue requests, and other future write endpoints.

### Previous Decision Status
Builds on the 2026-09-28 Phase 0 investigation and Merchant Access & Visibility frozen rules. It confirms the shared User/role architecture and the separation of authentication from merchant operational authorization. It resolves the previously pending existing-customer-to-merchant direction in favor of User reuse while leaving identity-proofing mechanics, context UI/session persistence, and detailed staff role/permission/lifecycle design pending. It does not supersede the public-visibility or suspended-merchant rules; staff inherits the merchant suspension restriction.

## 2026-09-28 22:28 IST - Storefront Merchant Registration — Merchant Access & Visibility Rules Frozen

### Topic
Documentation-only decision freeze for merchant login, pre-approval store preparation, suspension restrictions, and public storefront visibility.

### Question / Decision Resolved
Which merchant account and verification states permit login and merchant activity, and which mandatory conditions make merchant shops and products eligible for public visibility?

### Relevant Existing Implementation
- Phase 0 confirmed that merchants use the shared `users` identity and merchant role, with account status and verification status stored separately on `merchant_profiles`.
- Existing authentication rejects some merchant statuses, and storefront publication checks do not consistently require verification status `approved`; implementation therefore does not yet fully match these frozen rules.
- Merchant lifecycle and rejection-reason information already exists and can support future Merchant Panel status messaging.

### Frozen Decisions
- A merchant with valid credentials may log in when account status is `active`, `inactive`, or `suspended`, regardless of verification status (`pending`, `submitted`, `approved`, or `rejected`), provided the user/account exists.
- Non-suspended merchants may prepare and manage their store before approval unless an independent existing authorization or business rule prevents a specific action.
- `pending`, `submitted`, `rejected`, and `inactive` must not automatically be treated as suspension. Account status and verification status remain separate.
- The Merchant Panel must prominently explain non-public states; rejected merchants should see the existing rejection reason where available. Exact wording/design remains pending.

### Public Visibility Rule
Public storefront eligibility requires: Merchant account `active` AND verification `approved` AND shop `active` AND all normal product visibility/publication requirements. Login or content creation alone does not make a shop or product public.

### Suspended-Account Rule
A suspended merchant may log in and view account-status information, but must not perform merchant operational mutations. Future implementation must enforce this server-side across applicable shop, product, inventory, pricing, promotion, order-processing, and operational-settings actions; UI hiding alone is insufficient. Suspended merchant content is not public.

### Affected Future Areas / Files / Services / Tables
- Authentication and authorization: merchant authentication service, merchant middleware/policies, merchant routes/controllers, and server-side mutation enforcement.
- Publication: storefront shop/product queries and services that determine public visibility.
- Merchant UI: panel layout/dashboard status warnings and rejection/suspension guidance.
- Data and tests: `merchant_profiles` account/verification fields, shops/products, rejection metadata, merchant authentication/activity tests, and storefront visibility tests.

### Risks / Dependencies
- Current authentication and publication behavior may require coordinated changes so login remains available while suspended operations are blocked and all public queries require approval.
- Every mutating merchant endpoint needs consistent server-side suspension enforcement; partial coverage would create an authorization gap.
- Exact status-warning UI and wording remain implementation decisions. Independent restrictions on inactive or other non-suspended merchants must be identified without treating those states as suspension.

### Previous Decision Status
Builds on the 2026-09-28 Phase 0 architecture investigation and does not replace that entry. It confirms the shared identity and separate account/verification status architecture. It supersedes the earlier proposed Merchant Approval statement in `docs/17_Business_Rules.md` that new merchants cannot transact until approval, and supersedes any assumption that active merchant/shop status alone permits public visibility. The authoritative frozen rule is: Merchant Active + Verification Approved + Shop Active + normal product visibility requirements. No application behavior was changed in this documentation task.

## 2026-09-28 20:26 IST - Storefront Merchant Registration — Phase 0 Architecture Investigation

### Topic
Investigation only: assess the existing merchant onboarding, approval, shop, authentication, notification, and storefront foundations before implementing public merchant registration.

### Question Investigated
What merchant lifecycle currently exists from account creation through admin review and shop access, and which existing components can be reused safely for storefront self-registration?

### Relevant Existing Implementation Found
- Merchants use the shared `users` identity plus an active `merchant` assignment in `auth_user_roles`/`auth_roles` and a one-to-one `merchant_profiles` record; shops are separate one-to-many `shops` records.
- Admin merchant creation is implemented by `Admin\MerchantController`, `StoreMerchantRequest`, and `MerchantService::create()`. It transactionally creates the user/profile, assigns the merchant role, initializes merchant settings and availability statuses through model hooks, and dispatches `MerchantAccountCreated` after commit. It does not create an address or shop.
- Merchant login/access uses the normal web guard with `MerchantAuthenticationService`, `EnsureMerchantRole`, and the `/merchant/*` routes. Customer and merchant roles can use the same user/authentication architecture.
- Shop creation is a later, independent operation. Merchant-panel creation uses `MerchantShopService::createShop()`; admin shop persistence remains embedded separately in `Admin\MerchantShopController::saveShop()`.
- Merchant lifecycle notifications already exist for account created, approved, rejected, suspended, and reactivated through `MerchantAccountCreated`, `MerchantLifecycleChanged`, `DispatchBusinessNotifications`, and the notification template/manager infrastructure.

### Findings
- No public merchant registration/application route, controller, request, service, view, invitation flow, or storefront "Sell on WindowShop"/merchant-login entry point currently exists. Public `/register` is customer-only.
- Merchant account status (`active`, `inactive`, `suspended`) and verification status (`pending`, `submitted`, `approved`, `rejected`, `suspended`) are independent admin-editable fields; there are no dedicated transition actions or enforced approval state machine.
- Approval is not currently an access gate: an active merchant with the merchant role may log in unless verification is `suspended`. Storefront shop/product visibility generally checks active merchant/shop status but does not consistently require verification `approved`.
- Existing stored merchant data covers owner/contact details, business/legal name, business type, GST number, shop-license/FSSAI boolean flags, lifecycle metadata, and a separately managed business address. Shops support type/category, name, contacts, address/location, website, logo/banner, audiences, status, and generated unique slug.
- No PAN field or merchant document upload/review model, table, service, or admin UI exists; GST/shop-license/FSSAI evidence is not stored as documents.
- Existing tests cover merchant login/access, role and status rejection, merchant shop management, default initialization, and lifecycle notification wiring, but not public merchant registration or document review.

### Decision / Recommendation
- Reuse the shared `users` + roles + `merchant_profiles` authentication model; do not introduce a separate merchant guard or identity store.
- Extract/adapt a shared merchant-account creation operation from `MerchantService::create()` so admin creation and future public registration share transaction, role assignment, defaults, and after-commit notification behavior while supplying different source/initial-state policies.
- Reuse the existing lifecycle notification infrastructure and `MerchantShopService`; avoid duplicating merchant or shop creation logic. Consider consolidating the currently duplicated admin shop persistence before extending onboarding.
- Pending decisions, not frozen by this investigation: whether pending/rejected applicants may access the merchant panel; whether the first shop is created during application or after approval; whether approval gates login, shop publication, or both; existing-customer-to-merchant upgrade behavior; required registration fields/documents; rejection resubmission rules; and the exact initial account/verification states.

### Affected Files / Services / Tables
- Routes/UI: `routes/web.php`, `routes/merchant.php`, `resources/views/admin/merchants/*`, `resources/views/merchant/auth/login.blade.php`, `resources/views/merchant/shops/*`, and storefront header/footer/mobile-menu views.
- Core classes: `App\Models\User`, `MerchantProfile`, `MerchantAddress`, `Shop`; `App\Services\Merchant\MerchantService`, `MerchantAuthenticationService`, `MerchantShopService`, `MerchantShopContextService`; admin/merchant merchant and shop controllers/requests; merchant lifecycle events/listener and notification services.
- Tables: `users`, `auth_roles`, `auth_user_roles`, `merchant_profiles`, `merchant_addresses`, `merchant_settings`, `product_availability_statuses`, `shops`, `shop_settings`, and notification template/delivery tables.
- Tests reviewed: `MerchantAuthTest`, `MerchantSettingsFoundationTest`, `NotificationBusinessEventWiringTest`, and shop initialization/management suites.

### Risks / Dependencies
- Duplicated account creation could omit role assignment, default initialization, transaction boundaries, or notifications; duplicated shop logic could diverge further between admin and merchant flows.
- Treating `approved` as an existing login/publication gate would be incorrect and could expose pending/rejected merchants or shops unless a consistent policy is deliberately implemented.
- Multi-role users require an explicit identity/role activation policy. Document requirements would need new storage and review architecture if selected.

### Previous Decision Status
No earlier storefront merchant-registration architecture decision was found in this log. This entry records the Phase 0 findings and recommendations only; it confirms the existing shared-user/role architecture and does not change or supersede any prior decision.

## 2026-09-03
Phase 3D — Coupon Promotion Runtime
INVESTIGATION ONLY

Do NOT modify code.
Do NOT create migrations.
Do NOT change schema.
Do NOT implement UI.
Do NOT commit.
Do NOT push.

The goal is to investigate the existing WindowShop coupon/promotion foundation and define the safest V1 runtime design for coupon-based promotions.

==================================================
1. BUSINESS GOAL
==================================================

WindowShop already supports promotion configuration with:

- activation_type = automatic
- activation_type = coupon

Automatic promotions already work through the promotion calculation engine.

Coupon promotions are currently intentionally excluded from runtime.

We now want to investigate how a customer should be able to enter a coupon such as:

WELCOME10
DIWALI500
SAVE20

and have that coupon activate the SAME promotion engine.

Do NOT design a separate coupon discount engine.

Coupon should only activate an existing promotion.

==================================================
2. EXISTING COUPON FOUNDATION
==================================================

Inspect and report the current implementation of:

- promotions
- promotion_coupons
- promotion_redemptions
- Promotion model
- PromotionCoupon model
- PromotionRedemption model
- PromotionRepository
- PromotionCalculator
- PromotionCombinationResolver
- PromotionConditionEvaluator
- PromotionRewardCalculator
- StorePromotionRequest
- UpdatePromotionRequest
- PromotionController
- merchant promotion form
- cart services/controllers
- checkout services/controllers
- OrderCreationService
- customer/session/cart architecture

Confirm exactly what coupon data is already stored.

Report fields such as:

- coupon code
- shop_id
- promotion_id
- status
- usage limits
- customer usage limits
- dates
- redemption fields

Do not assume. Inspect the real schema/models.

==================================================
3. CURRENT MERCHANT COUPON CONFIGURATION
==================================================

Inspect the merchant offer form and controller.

Confirm:

- how merchant selects Coupon activation
- how coupon code is entered
- whether code is required
- whether coupon uniqueness is per shop or global
- whether multiple coupon codes can belong to one promotion
- whether one coupon code can activate multiple promotions
- whether existing validation prevents duplicate code in same shop
- whether coupon codes are normalized for case/spacing

Report current behavior only.

==================================================
4. CUSTOMER COUPON ENTRY POINT
==================================================

Inspect current storefront cart and checkout.

Determine whether there is already:

- coupon input
- apply button
- remove coupon action
- session key
- cart field
- database field
- temporary state
- order coupon field

Report what exists.

If nothing exists, say so.

Do NOT add UI.

==================================================
5. WHERE SHOULD APPLIED COUPON STATE LIVE?
==================================================

Evaluate possible V1 approaches:

A. Store applied coupon in session

B. Store applied coupon on Cart / cart table

C. Store coupon code in a dedicated cart-coupon table

D. Re-submit coupon from browser on every calculation

E. Another existing project mechanism

Consider:

- guest carts
- logged-in carts
- cart persistence across sessions
- multiple shops in one cart
- native app later
- abandoned carts
- checkout revalidation
- security
- coupon removal
- multiple browser tabs

Recommend ONE V1 approach.

Do NOT implement it.

==================================================
6. MULTI-SHOP CART
==================================================

This is critical.

WindowShop can contain products from multiple shops.

Coupon codes are shop-scoped.

Example:

Cart:
Shop A products
Shop B products

Customer enters:

SAVE20

Questions:

- Which shop does SAVE20 belong to?
- Should coupon input be global or per-shop?
- What if both shops independently have a coupon code SAVE20?
- Can a customer apply one coupon per shop?
- Can multiple shop coupons coexist in one cart?

Investigate existing cart grouping and recommend the cleanest V1 behavior.

Do not assume coupon code is globally unique if schema says otherwise.

==================================================
7. COUPON LOOKUP / NORMALIZATION
==================================================

Investigate how coupon codes are currently stored.

Recommend:

- case-sensitive or case-insensitive matching
- trim whitespace
- uppercase normalization or preserve display form
- handling duplicated codes across shops

Example:

SAVE20
save20
 Save20

Should these resolve to the same coupon within one shop?

Explain recommendation.

Do NOT change DB collation/schema.

==================================================
8. COUPON VALIDATION PIPELINE
==================================================

Define the recommended validation sequence.

Investigate which checks are already available.

Potential checks include:

- coupon exists
- correct shop
- coupon active
- promotion active
- promotion start date
- promotion end date
- activation_type = coupon
- coupon usage limit
- per-customer usage limit
- customer restriction
- minimum subtotal
- target eligibility
- new customer only
- stock-dependent reward validity
- promotion still provides positive benefit

Determine which belong at:

- Apply Coupon action
- Cart recalculation
- Checkout authoritative recalculation

Recommend a deterministic V1 sequence.

==================================================
9. COUPON VS AUTOMATIC PROMOTIONS
==================================================

Current frozen V1 rule:

No stacking multiple promotions on the same item/unit.

Automatic promotions already select the best valid customer benefit.

Investigate how coupon promotions should interact.

Example:

Product price ₹2,000

Automatic promotion:
20% OFF
benefit ₹400

Coupon SAVE500:
₹500 OFF

If coupon is applied, should:

A. coupon automatically override automatic promotion
B. best benefit still win
C. coupon be rejected because automatic is better
D. customer explicitly chooses
E. another rule

My preferred direction is:

Coupon participates in the SAME conflict resolver and the best valid customer benefit wins.

But investigate whether this fits the existing architecture.

Also answer:
If customer enters a valid coupon but automatic promotion wins instead, what message should customer receive?

==================================================
10. MULTIPLE COUPONS
==================================================

Investigate whether V1 should support:

- one coupon per entire cart
- one coupon per shop
- multiple coupons per shop
- multiple coupon promotions stacking

Given current no-stacking policy, recommend the simplest V1.

Likely direction:
one active coupon per shop cart group.

But derive from architecture.

==================================================
11. APPLY / REMOVE COUPON FLOW
==================================================

Define recommended runtime flow.

Example:

Customer enters SAVE20
→ server validates coupon
→ stores applied coupon state
→ cart recalculates
→ display applied/invalid message

Then:

Customer removes coupon
→ state cleared
→ automatic promotions recalculate normally

Investigate current AJAX/cart update patterns and recommend where this logic should live.

Do NOT implement routes/controllers yet.

==================================================
12. AUTHORITATIVE CHECKOUT REVALIDATION
==================================================

Coupon must be revalidated during order creation.

Checkout must not trust:

- browser discount
- browser promotion id
- browser coupon id
- browser coupon code eligibility
- previous cart calculation

Investigate how OrderCreationService currently recalculates automatic promotions.

Recommend how it should receive/resolve applied coupon state authoritatively.

==================================================
13. COUPON SNAPSHOT ON ORDER
==================================================

Inspect current order/order_items/order_totals fields and metadata.

Determine what historical coupon information should be snapshotted.

Potential data:

- promotion id
- promotion name
- coupon id
- coupon code
- actual discount
- reward type
- activation type = coupon

Determine whether current order item metadata is enough.

Also inspect whether order-level coupon fields already exist.

Do NOT add fields.

==================================================
14. PROMOTION REDEMPTIONS
==================================================

Inspect `promotion_redemptions`.

Report:

- exact columns
- relationships
- intended purpose
- whether currently used anywhere
- whether order_id is stored
- whether customer/user/shop/promotion/coupon ids exist
- quantity/count semantics
- timestamps/status

Determine when redemption should be recorded:

A. when coupon is applied to cart
B. when checkout starts
C. when order is created
D. when order is paid
E. when order is completed

Recommend one V1 rule.

Consider COD and Cash at Shop.

==================================================
15. USAGE LIMITS
==================================================

Inspect existing promotion/coupon usage limit fields.

Identify:

- global promotion usage limit
- coupon usage limit
- per-customer usage limit
- redemption count
- any max uses per order

Determine how each should be enforced safely under concurrency.

Important:
Investigate whether checkout needs DB locking / transaction-safe enforcement so two simultaneous orders cannot exceed a final available coupon use.

Do NOT implement locking.

Report what is required.

==================================================
16. CUSTOMER-SPECIFIC LIMITS
==================================================

Determine how customer identity is represented for:

- logged-in storefront customer
- guest checkout

If usage limit per customer exists, investigate:

- can guests use it safely?
- should guest use email/phone?
- should per-customer restrictions only apply to authenticated customer IDs?
- what does current architecture support?

Do NOT invent identity rules beyond current architecture.

Recommend V1 behavior.

==================================================
17. NEW CUSTOMER CONDITION
==================================================

We already defined:

New customer = no prior eligible/completed order WITH THAT SHOP.

Investigate how existing PromotionConditionEvaluator handles this.

Determine whether coupon promotions can reuse the same rule unchanged.

Check whether checkout customer context is available to coupon validation.

==================================================
18. MINIMUM SUBTOTAL
==================================================

Investigate coupon promotions with minimum subtotal.

Clarify whether minimum subtotal is:

- eligible subtotal
- shop subtotal
- before promotion discounts
- after promotion discounts

Use existing promotion semantics where already frozen.

Do not introduce coupon-specific subtotal rules unless necessary.

==================================================
19. FIXED DISCOUNT EDGE CASE
==================================================

Example:

Coupon:
₹500 OFF

Eligible subtotal:
₹300

Expected maximum discount should not exceed eligible value.

Confirm existing reward calculator already caps discount appropriately.

Also investigate how discount allocation across multiple lines works for coupon promotions.

This is important for refunds/exchanges.

==================================================
20. COUPON TARGETS
==================================================

Confirm coupon promotions can target:

- all
- product
- variant
- category
- brand
- collection

Coupon should activate a normal promotion, so target matching should reuse the same matcher.

Identify any places where runtime currently assumes automatic-only.

==================================================
21. QUANTITY / BUNDLE / BXY / FREE GIFT COUPONS
==================================================

Investigate whether coupon activation can theoretically be used with all existing reward types:

- percentage_discount
- fixed_discount
- fixed_price
- quantity_discount
- fixed_bundle_price
- tier_pricing
- buy_x_get_y_free
- buy_x_get_y_discount
- free_gift

Current merchant foundation may already allow coupon activation for these.

Determine whether Phase 3D V1 should enable coupon activation for ALL supported runtime reward types or only simple rewards initially.

Recommend scope.

Do not implement.

==================================================
22. FREE GIFT COUPON
==================================================

Specifically inspect Free Gift + coupon.

If customer enters a coupon that triggers a Free Gift:

- should virtual gift appear immediately?
- should checkout revalidate gift stock?
- should redemption be recorded if gift is generated?

Confirm existing Free Gift runtime can accept an explicitly activated promotion without architectural redesign.

==================================================
23. BXY COUPON
==================================================

Inspect whether BXY Free / BXY Discount can be reused unchanged once coupon promotion enters the candidate set.

Confirm group atomicity/no-stacking behavior.

Identify any coupon-specific complication.

==================================================
24. COUPON ERROR / STATUS MESSAGES
==================================================

Recommend merchant/customer-friendly statuses.

Examples:

- Coupon applied
- Invalid coupon
- Coupon expired
- Coupon not active yet
- Coupon not valid for these items
- Minimum order amount not reached
- Coupon usage limit reached
- Coupon already used
- Coupon belongs to another shop
- Better automatic offer applied

Avoid exposing technical implementation details.

Determine whether backend should return reason codes separately from display messages.

==================================================
25. SECURITY / TAMPERING
==================================================

Investigate threats:

- customer submits promotion_id directly
- customer changes coupon_id
- customer changes discount amount
- stale coupon retained after merchant disables offer
- coupon belongs to another shop
- coupon target changed after cart application

Recommend server-side rules.

Checkout must always recalculate.

==================================================
26. CART CHANGES AFTER COUPON APPLY
==================================================

Example:

Coupon requires ₹2,000 subtotal.

Customer applies coupon at ₹2,100.

Then removes an item and subtotal becomes ₹1,500.

What should happen?

Likely:
coupon state may remain stored, but current calculation reports it as not currently eligible.

Or:
coupon should be automatically removed.

Investigate which behavior is better for UX and architecture.

Recommend one.

==================================================
27. COUPON EXPIRY WHILE IN CART
==================================================

If coupon was applied yesterday but expires before checkout:

- cart recalculation should stop applying it
- checkout must reject/recalculate it

Determine whether stored coupon state should remain with an "expired" message or be cleared automatically.

Recommend V1 behavior.

==================================================
28. ORDER CANCELLATION AND REDEMPTION
==================================================

If redemption is recorded when order is created and order later gets cancelled:

Should the coupon usage be restored?

Investigate existing cancellation architecture and `promotion_redemptions`.

Recommend V1 semantics.

Consider:

- merchant cancellation
- customer cancellation
- payment failure
- auto-cancel
- COD
- Cash at Shop
- future online payment

Do not implement.

==================================================
29. REFUNDS / EXCHANGES
==================================================

Do NOT implement refund/exchange coupon logic.

Investigate only what snapshot is required so later partial refund/exchange can use historical allocated coupon discount.

Confirm existing order item promotion allocation is sufficient or identify gaps.

==================================================
30. COUPON DISPLAY ON BILL / INVOICE
==================================================

Determine how coupon should later appear.

Example:

Coupon: DIWALI500
Promotion Discount: -₹500

Should code be shown:
- on order summary
- invoice
- merchant detail
- customer detail

Determine whether current metadata can support this.

==================================================
31. API / NATIVE APP LATER
==================================================

WindowShop may later have a native app.

Recommend business/service APIs so coupon logic is not tied directly to Blade/session behavior.

Avoid controller-only logic.

Identify service boundary that Web + Native App could reuse.

==================================================
32. DATABASE / SCHEMA GAP ANALYSIS
==================================================

Explicitly answer:

Can Coupon Runtime V1 be implemented with the current schema?

YES / NO

If NO:
list only the minimum missing capability.

Do NOT create migrations.

If YES:
explain how current tables represent:

- coupon
- active coupon state
- redemption
- usage limits
- order snapshot

==================================================
33. RECOMMENDED SERVICE ARCHITECTURE
==================================================

Propose the smallest clean V1 architecture.

For example, investigate whether we need concepts such as:

CouponResolver
CouponApplicationService
PromotionRepository accepting activated coupon IDs
CouponRedemptionService

These names are examples only.

Do not create classes.

Prefer reusing the existing promotion engine.

Clearly separate:

- coupon lookup/application state
- promotion calculation
- checkout revalidation
- redemption recording

==================================================
34. IMPLEMENTATION PHASE SPLIT
==================================================

Recommend whether Phase 3D should be one implementation or split.

Possible example:

3D-A:
Coupon apply/remove + simple runtime integration

3D-B:
Usage limits/redemptions/concurrency

3D-C:
Complex rewards via coupon

But do not force this split if current architecture supports one clean phase.

Recommend the safest plan.

==================================================
35. REQUIRED TEST PLAN
==================================================

Recommend comprehensive tests, including:

COUPON LOOKUP
- valid code
- invalid code
- trim spaces
- case behavior
- same code in different shops

STATUS
- inactive coupon
- inactive promotion
- future promotion
- expired promotion

SHOP ISOLATION
- Shop A coupon cannot apply to Shop B
- same code independently used by Shop A and Shop B

CART
- apply coupon
- remove coupon
- cart changes after application
- qualification lost
- qualification regained
- multi-shop cart

CONFLICT
- coupon vs automatic
- automatic better
- coupon better
- equal benefit priority tie
- lower promotion id tie
- no stacking

REWARD TYPES
- percentage
- fixed amount
- fixed price
- quantity
- bundle
- tier
- BXY Free
- BXY Discount
- Free Gift
according to recommended V1 scope

USAGE
- global limit
- per-customer limit
- final use concurrency
- cancelled order behavior

CHECKOUT
- authoritative revalidation
- stale coupon
- merchant disables coupon before checkout
- changed coupon target
- browser tampering ignored

ORDER
- coupon metadata snapshot
- allocated line discounts
- redemption written once
- no duplicate redemption on retries

COUPON FREE GIFT
- gift appears only when coupon valid
- stock revalidated

POS
- coupon runtime does not accidentally affect POS unless explicitly supported

REGRESSIONS
- automatic promotion behavior unchanged
- BXY unchanged
- Free Gift unchanged
- quantity promotions unchanged

==================================================
36. FINAL REPORT FORMAT
==================================================

Return:

1. Existing coupon schema
2. Existing merchant coupon configuration
3. Existing storefront coupon UI/state
4. Current runtime limitations
5. Recommended coupon state storage
6. Multi-shop coupon design
7. Coupon normalization
8. Validation flow
9. Coupon vs automatic conflict behavior
10. Multiple coupon recommendation
11. Apply/remove flow
12. Checkout revalidation
13. Order snapshot requirements
14. Redemption timing
15. Usage-limit/concurrency findings
16. Customer identity implications
17. New-customer condition behavior
18. Complex reward compatibility
19. Free Gift coupon behavior
20. BXY coupon behavior
21. Cancellation/redemption behavior
22. Refund/exchange snapshot implications
23. Security findings
24. API/native-app service boundary
25. Schema gaps
26. Recommended architecture
27. Recommended Phase 3D implementation split
28. Test plan
29. Risks/unresolved decisions

Again:

INVESTIGATION ONLY.

Do NOT modify code.
Do NOT create migrations.
Do NOT change schema.
Do NOT implement UI.
Do NOT commit.
Do NOT push.

## 2026-08-15 - Checkout Step 1 Login/Register Gate

Prompt:
Implement Checkout Step 1 only: Cart to Checkout Account Gate to Login/Register to Merge Cart to Address placeholder. Reuse existing storefront auth/cart architecture, require customer auth for address, block empty carts, merge guest carts once after checkout login/register, and do not implement address CRUD, shipping, payment, checkout processing, or order creation.

Outcome:
Added a checkout gate flow with `/checkout`, `/checkout/account`, and `/checkout/address`. Guests with non-empty carts are sent to the existing two-column account UI; authenticated customers skip directly to the address placeholder. Empty carts are redirected back to `/view-cart` with “Your cart is empty.” The existing login/register pages now submit through shared customer auth handlers, and checkout-origin auth redirects to the address placeholder after merging the guest cart.

Implementation summary:

- Checkout intent is stored as `storefront_checkout_intent` in the Laravel session and paired with the internal intended address route.
- `CartMergeService` preserves the guest cart token before authentication, then merges that guest cart into the authenticated customer cart inside a transaction.
- If the customer has no cart, the guest cart is attached to the customer and the session token is cleared.
- If the customer has a cart, matching `product_variant_id` lines are combined and different variants remain separate.
- Merge reuses `CartItemQuantityValidator` for purchasability, quantity, stock, and current selling price checks; stale invalid guest items are dropped without crashing auth.
- Guest cart session identity is cleared after successful merge, and the guest cart record is deleted when merged into an existing customer cart.
- Repeated checkout/address requests do not merge again because the guest session token is removed and the guest cart is no longer independently active.
- Normal login/register redirects remain storefront-normal; checkout redirects only when the checkout session intent exists.
- Added client-side JavaScript validation to storefront login, registration, and checkout account forms for required fields, email format, optional phone format, password length, password confirmation, and terms acceptance; server-side Laravel validation remains authoritative.
- Added a customer account placeholder and routed header/mobile/footer My Account links through it; authenticated customers are redirected away from login/register to My Account, while guests are sent to login.
- `/checkout/address` is a customer-only placeholder and does not implement address CRUD, shipping, payment, order creation, stock deduction, tax, coupons, or checkout processing.

Files changed:

- `app/Http/Controllers/Customer/Auth/CustomerAuthController.php`
- `app/Http/Controllers/Storefront/CustomerAccountController.php`
- `app/Http/Controllers/Storefront/CheckoutController.php`
- `app/Http/Controllers/Storefront/StorefrontController.php`
- `app/Services/Cart/CartMergeService.php`
- `app/Services/Checkout/CheckoutFlowService.php`
- `resources/views/storefront/pages/cart.blade.php`
- `resources/views/storefront/pages/customer-login.blade.php`
- `resources/views/storefront/pages/customer-register.blade.php`
- `resources/views/storefront/pages/checkout-address.blade.php`
- `resources/views/storefront/pages/customer-account.blade.php`
- `resources/views/storefront/partials/header.blade.php`
- `resources/views/storefront/partials/footer.blade.php`
- `resources/views/storefront/layouts/app.blade.php`
- `routes/web.php`
- `tests/Feature/StorefrontCheckoutGateTest.php`

Tests / checks run:

- `php artisan test tests\Feature\StorefrontCheckoutGateTest.php` passed: 12 tests, 69 assertions.
- `php artisan test tests\Feature\StorefrontCheckoutGateTest.php` passed after JS validation update: 12 tests, 74 assertions.
- `php artisan test tests\Feature\StorefrontCustomerAuthPagesTest.php` passed after JS validation update: 3 tests, 43 assertions.
- `php artisan test tests\Feature\StorefrontCustomerAuthPagesTest.php` passed after account placeholder update: 4 tests, 60 assertions.
- `php artisan test tests\Feature\StorefrontCartPageTest.php` passed: 18 tests, 103 assertions.
- `php artisan test tests\Feature\StorefrontAddToCartTest.php` passed: 18 tests, 81 assertions.
- PHP syntax checks passed for checkout/auth controllers, checkout/cart services, and checkout gate tests.
- `git diff --check` passed for the changed checkout/auth/cart files.

## 2026-08-15 - Cart UI/UX Refinement Drawer Review and Remove Confirmation

Prompt:
Refine the cart UI only: remove item deletion from the right-side mini-cart drawer, keep deletion on `/view-cart`, require a Bootstrap-style confirmation modal before deleting, preserve existing cart ownership/security rules, and keep totals/header/sidebar synchronized after removal.

Outcome:
Removed all mini-cart drawer Remove controls and the drawer-side DELETE listener so the drawer is quick-review only. Kept the full cart page Remove action, but it now opens a confirmation modal with Cancel and Remove actions before calling the existing AJAX DELETE endpoint. The backend ownership checks remain unchanged through the current `CartResolver` cart and cart item mutation flow.

Implementation summary:

- Right drawer still renders shop groups, products, quantities, prices, line amounts, shop subtotals, subtotal, View Cart, Checkout, and Continue Shopping.
- Dynamic drawer/cart totals no longer use the template's legacy USD recalculation classes, preventing populated cart totals from being overwritten as `$0.00`.
- Full cart page stores the selected row/delete URL as pending state when Remove is clicked and sends the DELETE request only from the modal confirm button.
- Remove confirmation modal styling is scoped to the cart modal with slimmer buttons, tighter padding, and a smaller close control.
- Cancel closes the modal without calling the delete endpoint or changing the database.
- Confirmed removal updates the row, empty shop heading, cart subtotal/total, header count, empty cart state, and sidebar mini-cart from the existing cart payload.
- Checkout, cart ownership rules, customer context rules, add-to-cart rules, stock/backorder rules, pricing rules, and database architecture were not changed.

Files changed:

- `resources/views/storefront/partials/search.blade.php`
- `resources/views/storefront/pages/cart.blade.php`
- `tests/Feature/StorefrontCartPageTest.php`
- `docs/Prompt_Outcome_Log.md`

Tests / checks run:

- `php artisan test tests\Feature\StorefrontCartPageTest.php` passed: 18 tests, 95 assertions.
- `php artisan test tests\Feature\StorefrontAddToCartTest.php` passed: 18 tests, 81 assertions.
- PHP syntax checks passed for `CartItemController`, `CartItemMutationService`, `CartPageService`, and `StorefrontCartPageTest`.

## 2026-08-15 - Cart Step 5 Dynamic Cart Page

Prompt:
Convert the existing static storefront Cart page into a dynamic cart page using `CartResolver::current()`, without creating carts on page view and without implementing checkout. Show real cart items grouped by shop, support quantity update and remove, refresh prices from current variant selling price, preserve customer/guest/admin/merchant cart context security, and add focused tests.

Outcome:
Converted the existing `GET /view-cart` page to render the current resolved cart instead of static demo data. Added cart page, quantity validation, and mutation services so Add to Cart and cart-page quantity updates share the same product/variant/shop/merchant, quantity, stock, and backorder rules. Added `PATCH /cart/items/{cartItem}` for quantity updates and `DELETE /cart/items/{cartItem}` for item removal, both enforcing that the item belongs to the current `StorefrontCustomerContext`/`CartResolver` cart.

Implementation summary:

- `CartPageService` loads the current cart with products, variants, shops, primary images, variant attributes, and availability relationships.
- Cart display is grouped by shop with shop subtotals, cart subtotal, and cart total.
- Cart page uses current database variant selling price for valid items and refreshes `cart_items.unit_price` during render/update.
- Quantity update validates `quantity > 0`, integer/decimal rules, minimum, maximum, increment, stock, and purchase-allowed/backorder status.
- Remove deletes only the `cart_items` row and leaves cart/user/product/variant/shop records intact.
- Empty cart state renders without creating a cart record.
- Unavailable or soft-deleted products/variants/shops remain visible with a warning and can be removed.
- Frontend AJAX updates item quantity, line subtotal, shop subtotal, cart subtotal/total, and header cart count.
- The global sidebar/offcanvas cart (`#shoppingCart`) now uses the template mini-cart structure and renders the same current cart data instead of the static empty message.
- Add-to-cart, cart quantity update, and mini-cart remove responses include the cart payload needed to keep the sidebar cart and header count synchronized.
- Checkout, order creation, stock deduction, tax, shipping, coupons, cart merge, and impersonation were not implemented.

Files changed:

- `app/Http/Controllers/Storefront/CartItemController.php`
- `app/Http/Controllers/Storefront/StorefrontController.php`
- `app/Services/Cart/AddToCartService.php`
- `app/Services/Cart/CartItemMutationService.php`
- `app/Services/Cart/CartItemQuantityValidator.php`
- `app/Services/Cart/CartPageService.php`
- `resources/views/storefront/pages/cart.blade.php`
- `resources/views/storefront/partials/search.blade.php`
- `routes/web.php`
- `tests/Feature/StorefrontCartPageTest.php`

Tests / checks run:

- `php artisan test tests\Feature\StorefrontCartPageTest.php` passed: 16 tests, 79 assertions.
- `php artisan test tests\Feature\StorefrontAddToCartTest.php` passed: 18 tests, 81 assertions.
- `php artisan test tests\Feature\CartOwnershipFoundationTest.php` passed: 4 tests, 14 assertions.
- PHP syntax checks passed for changed cart services, storefront controllers, and `StorefrontCartPageTest`.

## 2026-08-14 - Customer Identity Step 3 Cart Ownership to Global User

Prompt:
Change cart ownership from merchant-specific `merchant_customers.id` to global `users.id`. Keep guest carts by `session_token`, keep `cart_items` unchanged, do not change `orders.customer_id`, and do not implement Add to Cart or checkout/cart merge behavior.

Outcome:
Folded cart ownership into the base carts migration so `carts` is created with nullable `user_id -> users.id` and `ON DELETE SET NULL` from the start. Updated `Cart` to belong to `User`, added `User::carts()`, removed the obsolete `MerchantCustomer::carts()` relationship, and added cart ownership foundation tests. `cart_items` and `orders.customer_id` were left unchanged.

## 2026-08-14 - Customer Identity Corrective Step Nullable POS Email

Prompt:
Remove fake POS customer email generation from the global `users` identity flow. Make `users.email` nullable while preserving email uniqueness, clean only exact generated `pos-customer-<uuid>@windowshop.local` placeholder emails, keep secure random password handling, and do not change cart/order/customer ownership architecture.

Outcome:
Folded nullable `users.email` into the base users migration and removed fake POS email generation. Updated POS identity creation to store `NULL` when no real email is provided and normalized real email when present. Existing mobile-first identity matching, random hashed POS passwords, and unverified email/mobile fields are preserved. Added POS tests for null email users, real email reuse, and multiple null emails.

## 2026-08-14 - Customer Identity Step 2 POS Global User Link

Prompt:
Implement Customer Identity Step 2 - POS customer creates or links a global `users` record. New POS customers should resolve an existing global user by normalized mobile first, then valid email if needed, or create a secure POS-origin user and link `merchant_customers.user_id`. Do not change cart ownership, checkout ownership, storefront/mobile registration, OTP, account activation, bulk backfill, or customer edit relinking.

Outcome:
Added `CustomerIdentityResolver` for POS identity resolution/creation, added `MerchantCustomerService::createFromPos()`, and updated `PosController::storeCustomer()` to use that POS-specific flow. POS-created users get `registration_source = pos`, active status, local mobile stored on the global user, optional valid email when available, and a secure random hashed password. Existing users are reused without changing their original `registration_source`; duplicate merchant customers still reuse the existing merchant customer; legacy `merchant_customers.user_id = NULL` rows are not backfilled.

## 2026-08-14 - Customer Identity Step 1 Registration Source

Prompt:
Implement Customer Identity Step 1 - Registration Source only. Add a nullable indexed `registration_source` field to `users`, centralize supported source values, update the User model, update only clearly-known seed/application creation paths, and do not change POS customer creation, cart relationships, or customer/user synchronization.

Outcome:
Added a migration for `users.registration_source`, created `UserRegistrationSource` enum values, added `registration_source` to the `User` model fillable list, set `admin` for newly inserted super admin/demo merchant seeded users, and set `admin` for merchant users created by the admin merchant service. Existing users are not backfilled or overwritten by the migration.


## 2026-08-13 18:45 +05:30 - Postal Code, Restrictions, Storefront PIN Selector, Mega Menu Frozen

### Exact User Prompt

```text
we have done 
1 Master Postal
2 Restrication
3 Model Box for Pin code Storefront
4 Mega menu frozen
can u pls add in docs?
```

### Final Outcome

Documented the completed WindowShop location/navigation foundation work.

Completed items now recorded:

- Master Postal Code module
- Postal Code Restriction module
- Storefront customer PIN code/location selector modal
- Storefront mega menu/navigation frozen decision

### Implementation Summary

Master Postal Code:

- Added Indian postal-code master data infrastructure using the existing `postal_codes` table and `PostalCode` model.
- Supports active/inactive status, soft deletes, office/location metadata, delivery status, shipping-enabled flag, and latitude/longitude fields for future proximity ranking.
- Includes CSV import coverage and admin management coverage through `AdminPostalCodeMasterTest`.
- This is the canonical postal-code master; no duplicate postal-code master table should be created.

Postal Code Restriction:

- Added separate postal-code restriction infrastructure through `postal_code_restrictions`.
- Restriction records remain independent from customer browsing location.
- This module is for serviceability/regulatory/operational restriction rules, not for ranking shops/products by customer location.
- Covered through `PostalCodeRestrictionTest`.

Storefront Customer PIN Code / Location Selector:

- Added a dedicated storefront modal with `id="customer-location-modal"`.
- Added `POST /location/postal-code` named `storefront.location.postal-code.store`.
- Added `App\Services\Storefront\CustomerLocationService` as the centralized resolver for current browsing postal code.
- Stores selected PIN in Laravel session key `storefront.shopping_postal_code`.
- Stores selected PIN in browser cookie `windowshop_postal_code` for 30 days.
- Validates India V1 PIN codes as exactly 6 digits on frontend and backend.
- Validates entered PIN against active, non-deleted records in `postal_codes`.
- Auto-opens the modal only when no current PIN is resolved.
- Lets customers change the selected shopping PIN without logout/login.
- Header uses a compact location icon near the Account icon with tooltip text.
- PIN selection is a ranking preference only; it does not filter or block distant products.

Storefront Mega Menu Frozen:

- Storefront navigation V1 remains frozen around the existing `product_categories` hierarchy.
- No separate menu-builder table is introduced for V1.
- Marketplace mega menu and merchant scoped category navigation continue to use the existing `NavigationService` and Blade partials.
- The frozen decision is recorded in `docs/Architecture_Decisions.md` under `WindowShop Storefront Navigation V1`.

### Files / Areas Referenced

- `app/Models/PostalCode.php`
- `app/Models/PostalCodeRestriction.php`
- `app/Services/Storefront/CustomerLocationService.php`
- `app/Http/Controllers/Storefront/CustomerLocationController.php`
- `resources/views/storefront/partials/customer-location-modal.blade.php`
- `resources/views/storefront/partials/header.blade.php`
- `public/assets/storefront/js/customer-location.js`
- `routes/web.php`
- `docs/Architecture_Decisions.md`

### Tests / Checks Recorded

- `vendor\bin\phpunit tests\Feature\StorefrontCustomerLocationTest.php` passed: 7 tests, 34 assertions.
- `vendor\bin\phpunit tests\Feature\StorefrontCustomerLocationTest.php tests\Feature\StorefrontProductListingTest.php` passed: 15 tests, 123 assertions.

### Architectural Notes

- Future nearest-shop/product ranking should read the customer browsing postal code through `CustomerLocationService::postalCode()`.
- Ranking should prioritize same PIN first, nearby PINs next, and farther shops later.
- Do not treat the selected browsing PIN as a delivery restriction.
- Do not mix this selector with `postal_code_restrictions`.
- Future proximity logic can use `postal_codes.latitude` and `postal_codes.longitude` when the nearest-shop algorithm is implemented.

## 2026-08-12 00:25 +05:30 - Storefront Dynamic Content Step 2 Home Hero Slider

### Exact User Prompt

```text
WINDOWSHOP - STOREFRONT DYNAMIC CONTENT
STEP 2 - HOME HERO SLIDER

Make ONLY the Home Hero slider dynamic.

Reuse the existing banners system.
Do NOT create a new hero_sliders table.
Do NOT redesign the existing Hero section.
Preserve the current storefront layout, dimensions, responsiveness, slider behaviour, and styling.
Do NOT make other homepage sections dynamic in this task.

Load eligible marketplace/global Home Hero banners from the existing banners table, respecting status, schedule,
position, ordering, desktop/mobile images, links, fallback behaviour, and a sensible maximum limit.
Add focused feature tests for the dynamic Home Hero slider.
```

### Final Outcome

Implemented Storefront Dynamic Content Step 2 for the Home Hero Slider.

- reused the existing `banners` table, `Banner` model, `BannerPosition` enum, `BannerService`, and `BannerLinkResolver`
- used canonical position value `homepage_hero` via `BannerPosition::HOMEPAGE_HERO`
- added `BannerService::getMarketplaceHeroBanners()` for the marketplace homepage query
- filtered banners with `forMarketplace()`, `forPosition()`, `currentlyVisible()`, and `ordered()`
- respected active status and schedule fields `starts_at` / `ends_at`
- limited homepage hero banners to `BannerPosition::HOMEPAGE_HERO->maxBanners()` which is 5
- passed `heroBanners` from `StorefrontController::home()` to the home view
- replaced static-only hero rendering with a dynamic banner loop in `storefront.partials.hero`
- preserved the existing Swiper hero wrapper, classes, fade effect, autoplay delay, pagination, dimensions, and static fallback slides
- used `desktop_image_path` for desktop and `mobile_image_path` for mobile, falling back to desktop when mobile is missing
- made dynamic slides clickable only when `BannerLinkResolver` returns a valid URL
- preserved the existing static Hero fallback when no active marketplace Hero banners exist
- did not make any other homepage section dynamic

### Files Changed

- `app/Services/Banner/BannerService.php`
- `app/Http/Controllers/Storefront/StorefrontController.php`
- `resources/views/storefront/partials/hero.blade.php`
- `tests/Feature/StorefrontHomeHeroBannerTest.php`
- `docs/Prompt_Outcome_Log.md`

### Tests / Checks Run

- `php -l app\Services\Banner\BannerService.php`
- `php -l app\Http\Controllers\Storefront\StorefrontController.php`
- `php artisan test tests\Feature\StorefrontHomeHeroBannerTest.php`
- `php artisan test tests\Feature\BannerManagementFoundationTest.php`
- `php artisan test tests\Feature\StorefrontMarketplaceLogoTest.php`
- `php artisan test tests\Feature\StorefrontNavigationTest.php`

## 2026-08-11 23:54 +05:30 - Storefront Dynamic Content Step 1 Marketplace Logo

### Exact User Prompt

```text
WINDOWSHOP - STOREFRONT DYNAMIC CONTENT
STEP 1 - MARKETPLACE LOGO

Context:

The customer storefront pages already exist as static Blade/HTML pages:

- Home page
- Product listing page
- Product detail page
- Cart
- Checkout
- Login

We are now starting to convert the storefront from static content to dynamic content.

This task is ONLY Step 1:
Make the Marketplace Logo dynamic across the customer-facing storefront.

Do NOT convert other storefront content yet.

Use the existing global setting:

group: marketplace
key: logo

Conceptually:

marketplace.logo

Reuse the existing Marketplace Logo resolver/helper/service.
Replace hard-coded customer storefront logo references with the dynamic Marketplace Logo.
Keep fallback to the default WindowShop logo if no custom logo exists or the uploaded file is missing.
Do not expose upload/update functionality to storefront/customer routes.
Add/update focused tests for storefront logo display and fallback behaviour.
```

### Final Outcome

Implemented Storefront Dynamic Content Step 1 for the Marketplace Logo.

- reused the existing `App\Services\Marketplace\MarketplaceLogoService`
- shared the resolved Marketplace Logo URL with `storefront.partials.header` through an `AppServiceProvider` view composer
- cached the resolved logo URL once per request inside the composer
- replaced the hard-coded storefront header logo path in both desktop and mobile header markup
- preserved current logo markup dimensions, layout classes, route, and `WindowShop` alt text
- did not expose any storefront/customer logo upload or update route
- did not make any other storefront content dynamic
- verified uploaded managed logo paths render as `/storage/marketplace/logo/...`
- verified missing managed logo files fall back to the default logo
- verified no hard-coded `assets/storefront/images/logo/logo.svg` references remain in customer storefront Blade views

### Files Changed

- `app/Providers/AppServiceProvider.php`
- `resources/views/storefront/partials/header.blade.php`
- `tests/Feature/StorefrontMarketplaceLogoTest.php`
- `docs/Prompt_Outcome_Log.md`

### Shared Storefront Header Used

- `resources/views/storefront/layouts/app.blade.php`
- `resources/views/storefront/partials/header.blade.php`

### Marketplace Logo Helper/Service Used

- `App\Services\Marketplace\MarketplaceLogoService`
- setting key: `marketplace.logo`
- fallback logo: `assets/admin/images/logov2.png`

### Hard-Coded Logo References

Replaced:

- `resources/views/storefront/partials/header.blade.php`
  - desktop header logo
  - mobile header logo

Left unchanged intentionally:

- storefront favicon references
- non-logo `WindowShop` text in titles, metadata, body copy, and alt text
- admin and merchant area logo references

### Verification

- `php -l app\Providers\AppServiceProvider.php` passed
- `php -l tests\Feature\StorefrontMarketplaceLogoTest.php` passed
- `php artisan test tests\Feature\StorefrontMarketplaceLogoTest.php` passed: 4 tests, 11 assertions
- `php artisan test tests\Feature\StorefrontCustomerAuthPagesTest.php` passed: 3 tests, 37 assertions
- `php artisan test tests\Feature\StorefrontNavigationTest.php` passed: 6 tests, 50 assertions

## 2026-08-05 12:57 +05:30 - Storefront Banner In Admin Settings

### Exact User Prompt

```text
http://127.0.0.1:8082/unwanted/localhyper/windowshop/public/admin/system-settings
*Note  dont do any change in it for now, keep it as it is 

we have also 
http://127.0.0.1:8082/unwanted/localhyper/windowshop/public/admin/settings
can we have here also Storefront Banner?

pls continue
```

### Final Outcome

Added a `Storefront Banner` tab to `/admin/settings` without changing `/admin/system-settings`.

- displays `Maximum Banners Per Shop`
- reads from the existing `system_settings.key = storefront_banner.max_per_shop`
- saves back to the same `system_settings` row
- ensures the setting exists by running the focused Storefront Banner settings seeder
- validates the value as an integer between 1 and 20
- did not duplicate the setting into `admin_settings`

### Verification

- Formatting: `vendor\bin\pint app\Http\Controllers\Admin\AdminSettingsController.php tests\Feature\AdminSettingsFoundationTest.php` passed
- Focused tests passed: `php artisan test tests\Feature\AdminSettingsFoundationTest.php tests\Feature\StorefrontBannerSettingSeederTest.php`
- Result: 6 tests, 70 assertions

## 2026-08-05 12:47 +05:30 - System Setting Identity Fields Read Only

### Exact User Prompt

```text
System Setting Information
Key
Group *
Label *
Value Type *

dont allwo to change this, show as label
does it make sens?
```

### Final Outcome

Updated the Admin System Setting edit form so seeded identity fields are read-only:

- Key is displayed as text
- Group is displayed as text with hidden `group_id`
- Label is displayed as text with hidden `label`
- Value Type is displayed as text with hidden `value_type`
- admins can still update the actual value and other editable metadata

### Verification

- Formatting: `vendor\bin\pint tests\Feature\AdminSystemSettingManagementTest.php` passed
- Focused test passed: `php artisan test tests\Feature\AdminSystemSettingManagementTest.php`
- Result: 4 tests, 21 assertions

## 2026-08-05 12:42 +05:30 - Admin System Settings UI

### Exact User Prompt

```text
How i can see these data in admin?

can we have UI in admin?
```

### Final Outcome

Added a focused Admin UI for records stored in `system_setting_groups` and `system_settings`:

- added `SystemSettingGroup` and `SystemSetting` models
- added `Admin\SystemSettingController`
- added routes:
  - `admin.system-settings.index`
  - `admin.system-settings.edit`
  - `admin.system-settings.update`
- added a Master Data > System Settings sidebar link
- added DataTable-style system settings list with search and filters
- added edit screen for setting value, label, group, type, public/encrypted flags, description, sort order, and status
- added integer/boolean/json value validation
- verified the `storefront_banner.max_per_shop` seeded setting is visible and editable

### Verification

- Formatting: `vendor\bin\pint app\Http\Controllers\Admin\SystemSettingController.php app\Models\SystemSetting.php app\Models\SystemSettingGroup.php tests\Feature\AdminSystemSettingManagementTest.php` passed
- Focused tests passed: `php artisan test tests\Feature\AdminSystemSettingManagementTest.php tests\Feature\StorefrontBannerSettingSeederTest.php`
- Result: 5 tests, 42 assertions
- Route check passed: `php artisan route:list --name=admin.system-settings`

## 2026-08-05 12:30 +05:30 - Storefront Banner System Setting Seeder

### Exact User Prompt

```text
Implement the WindowShop Storefront Banner System Settings Seeder.

Create Database\Seeders\MasterData\StorefrontBannerSettingSeeder.php.
Create/update the Storefront Banner group and storefront_banner.max_per_shop setting using updateOrInsert().
Register it in SystemFoundationSeeder after existing system setting seeders.
Run the seeder and verify no duplicates.
```

### Final Outcome

Implemented the focused Storefront Banner settings seeder:

- added `database/seeders/MasterData/StorefrontBannerSettingSeeder.php`
- seeds/updates `system_setting_groups.slug = storefront-banner`
- seeds/updates `system_settings.key = storefront_banner.max_per_shop`
- uses `DB::table()->updateOrInsert()` with UUID and `created_at` only on inserts
- clears `deleted_at` and restores active metadata on updates
- registered the seeder in `SystemFoundationSeeder`
- did not add any other storefront/banner settings

### Verification

- Formatting: `vendor\bin\pint database\seeders\MasterData\StorefrontBannerSettingSeeder.php database\seeders\MasterData\SystemFoundationSeeder.php tests\Feature\StorefrontBannerSettingSeederTest.php` passed
- Focused test passed: `php artisan test tests\Feature\StorefrontBannerSettingSeederTest.php`
- Result: 2 tests, 29 assertions
- Actual local seeder command ran twice: `php artisan db:seed --class=Database\Seeders\MasterData\StorefrontBannerSettingSeeder`
- Actual local counts after second run: groups 1, settings 1, value 3, type integer

## 2026-08-05 12:19 +05:30 - Expanded Contextual Banner Suggestions

### Exact User Prompt

```text
Title Suggestions (30)
Offers, Products, Seasons, General suggestions...

Subtitle Suggestions (30)
Button Text Suggestions (20)
Festival Titles (15)
Seasonal Titles (10)
Service Titles (10)

Bonus Idea:
Instead of showing all 30 suggestions, make them contextual.

if u can add improve more pls do
```

### Final Outcome

Expanded and improved the shared banner `Quick Suggestions` panel:

- added larger title, subtitle, and button text suggestion sets
- added contextual filters for title suggestions: Popular, Offers, Products, Festival, Seasonal, Services, General
- added contextual filters for subtitle suggestions: Popular, Fresh, Deals, Trust, Lifestyle, Service
- added contextual filters for button text suggestions: Popular, Shopping, Browse, Deals, Details
- default view now shows a concise Popular set instead of every suggestion
- preserved click-to-fill, copy-to-clipboard, checkmark, and selected blue state behavior

### Verification

- Focused test passed: `php artisan test tests\Feature\BannerManagementFoundationTest.php`
- Result: 8 tests, 23 assertions

## 2026-08-05 12:10 +05:30 - Banner Quick Suggestions Selected State

### Exact User Prompt

```text
http://127.0.0.1:8082/unwanted/localhyper/windowshop/public/admin/banners/create
Copy Suggestions
rename to Quick Suggestions
and 
The only small improvement I'd make is when a merchant clicks one.

Current:

[ Up to 50% OFF ]

After clicking:

[ ✓ Up to 50% OFF ]

or change its appearance:

Blue background
White text

so the user knows:

"This suggestion has been applied."
```

### Final Outcome

Updated the shared banner suggestion block:

- renamed `Copy Suggestions` to `Quick Suggestions`
- clicking a suggestion still fills the matching field and copies the value
- the applied suggestion now shows a leading checkmark
- the applied suggestion changes to a blue button with white text
- only one suggestion per field group stays selected at a time

### Verification

- Focused test passed: `php artisan test tests\Feature\BannerManagementFoundationTest.php`
- Result: 8 tests, 23 assertions

## 2026-08-05 12:05 +05:30 - Banner Form Accordion Controls

### Exact User Prompt

```text
http://127.0.0.1:8082/unwanted/localhyper/windowshop/public/admin/banners/create
same here also Open all , collesp all
```

### Final Outcome

Added `Open All` and `Collapse All` controls to the active banner create/edit card header.

Because the banner form partial is shared, the same controls are available on both Admin and Merchant banner create/edit screens. The shared accordion was changed to `accordion-flush` and no longer uses single-open parent behavior, so multiple sections can stay expanded.

### Verification

- Focused test passed: `php artisan test tests\Feature\BannerManagementFoundationTest.php`
- Result: 8 tests, 22 assertions

## 2026-08-05 11:59 +05:30 - Banner Template Form Card Layout

### Exact User Prompt

```text
http://127.0.0.1:8082/unwanted/localhyper/windowshop/public/admin/banner-templates/d02c5f82-8f4f-401c-a919-43d93ca6f421/edit

are u not useing card
```

### Final Outcome

Wrapped the Admin Banner Template create/edit accordion in a Limitless-style card:

- added a `Banner Template Information` card header
- moved `Open All` and `Collapse All` controls into the card header
- changed the accordion to `accordion-flush` inside the card so the form feels contained and cleaner

### Verification

- Focused test passed: `php artisan test tests\Feature\AdminBannerTemplateManagementTest.php`
- Result: 5 tests, 29 assertions

## 2026-08-05 11:54 +05:30 - Banner Pack V1 Seeder

### Exact User Prompt

```text
Implement the WindowShop Banner Pack V1 Seeder.

Do not generate banner images yet.

Only seed the banner_templates table with metadata and placeholder image paths.

Create BannerTemplateSeeder, register it in DatabaseSeeder, use updateOrCreate(), never create duplicate records, use existing BannerTemplate model and enums.

Seed 49 templates across General, Festival, Seasonal, Fashion, Electronics, Grocery, and Services. Run the seeder twice and verify still 49 records.
```

### Final Outcome

Implemented the Banner Pack V1 metadata seeder:

- added `database/seeders/BannerTemplateSeeder.php`
- registered it in `DatabaseSeeder`
- seeded 49 active template metadata records with lowercase machine-safe codes
- used enum values for category, availability, and default position
- used `availability = both`, `default_position = store_hero`, `status = active`, and `default_button_text = Shop Now`
- used placeholder image paths only: `banner-templates/{code}/desktop.webp` and `banner-templates/{code}/mobile.webp`
- added festival event codes and start/end offsets
- used `updateOrCreate()` with soft-deleted record recovery to avoid duplicates
- did not generate or store actual banner images

### Verification

- Formatting: `vendor\bin\pint database\seeders\BannerTemplateSeeder.php database\seeders\DatabaseSeeder.php tests\Feature\BannerTemplateSeederTest.php` passed
- Focused tests passed: `php artisan test tests\Feature\BannerTemplateSeederTest.php tests\Feature\BannerTemplateFoundationTest.php tests\Feature\AdminBannerTemplateManagementTest.php`
- Result: 14 tests, 51 assertions
- Actual local seeder run: `php artisan db:seed --class=BannerTemplateSeeder` ran twice
- Actual local counts after second run: total 49, distinct codes 49
- Category counts: general 10, festival 12, seasonal 6, fashion 6, electronics 5, grocery 5, services 5

## 2026-08-05 11:37 +05:30 - Banner Template Accordion Controls

### Exact User Prompt

```text
http://127.0.0.1:8082/unwanted/localhyper/windowshop/public/admin/banner-templates/create
I like the accordian , can we have feature open all , collesp all?
```

### Final Outcome

Added `Open All` and `Collapse All` buttons above the Admin Banner Template create/edit accordion.

The buttons use Bootstrap Collapse against the existing accordion panels, so admins can quickly expand every section or collapse the full form while keeping the current accordion UI.

### Verification

- Focused test passed: `php artisan test tests\Feature\AdminBannerTemplateManagementTest.php`
- Result: 5 tests, 29 assertions

## 2026-08-05 11:31 +05:30 - Consolidate Banner Template Source Migration

### Exact User Prompt

```text
2026_08_05_000002_add_template_source_to_banners_table
pls move it to main migration

you can changethe order, will migrat fressh
```

### Final Outcome

Consolidated the banner template source migration for fresh installs:

- moved `source_type` and nullable `banner_template_id` into `2026_08_04_000001_create_banners_table.php`
- moved the `banner_templates` table migration earlier as `2026_08_04_000000_create_banner_templates_table.php` so the `banners.banner_template_id` foreign key can be created safely
- deleted `2026_08_05_000001_create_banner_templates_table.php`
- deleted `2026_08_05_000002_add_template_source_to_banners_table.php`
- preserved the `banner_template_id` `nullOnDelete` behavior

### Verification

- PHP lint passed for both banner migrations
- Focused tests passed: `php artisan test tests\Feature\BannerTemplateFoundationTest.php tests\Feature\AdminBannerTemplateManagementTest.php tests\Feature\BannerManagementFoundationTest.php`
- Result: 20 tests, 66 assertions

## 2026-08-05 11:21 +05:30 - Admin Banner Template Management Step 2A

### Exact User Prompt

```text
Implement Step 2 of the WindowShop Banner Library feature: Admin Banner Template Management.

I would not build full CRUD (show, trash, restore, force delete) immediately.

Recommended order
Step 2A (Now)

Implement only:

Admin Banner Template List
Create
Edit
Activate / Deactivate
Image Upload
Preview
Filters
Search

Do NOT implement yet:

Trash
Restore
Force Delete
Show page

use DataTable pls

Final DataTable:
| Preview | Name | Category | Position | Available For | Used By | Status | Updated | Actions |
```

### Final Outcome

Implemented Admin Banner Template Management V1 / Step 2A only:

- added `Admin\BannerTemplateController` with index, create, store, edit, update, and activate/deactivate toggle
- added Form Requests and shared validation concern for template fields, images, enum values, lowercase machine-safe codes, offsets, status, and availability/position scope compatibility
- added focused `BannerTemplateImageService` storing template uploads under `banner-templates/{uuid}`
- added admin list, create, edit, and shared form Blade views
- added DataTable-style list columns: Preview, Name, Category, Position, Available For, Used By, Status, Updated, Actions
- added filters/search for name/code/title, category, availability, default position, and status
- added desktop/mobile image upload previews, current-image edit previews, content live preview, and position-specific recommended dimensions
- added Marketing > Banner Templates menu entry before Banners
- intentionally skipped show, trash, restore, force-delete, seed templates, merchant library, activation workflow, five-slot management, and storefront rendering

### Verification

- Formatting: `vendor\bin\pint ...` passed and fixed import ordering only
- Focused tests: `php artisan test tests\Feature\AdminBannerTemplateManagementTest.php tests\Feature\BannerTemplateFoundationTest.php` passed: 12 tests, 44 assertions
- Full suite: `php artisan test` passed: 437 tests, 3518 assertions

## 2026-08-05 10:35 +05:30 - Banner Template Database Foundation Step 1

### Exact User Prompt

```text
Implement Step 1 of the WindowShop Banner Library feature: Banner Template database foundation.

Before coding, inspect the existing Laravel project conventions for UUID generation, status constants, audit fields, soft deletes, Admin master tables, migration naming and foreign-key conventions, existing banners table and Banner model, and test structure.

Only implement the database/model foundation:

- create `banner_templates` table with UUID, code, category, name, descriptions/default text, desktop/mobile image paths, default position, availability, event code, signed start/end offsets, sort order, status, audit users, timestamps, soft deletes, and requested indexes
- update existing `banners` table in a separate migration with `source_type` and nullable `banner_template_id`
- create `App\Models\BannerTemplate`
- update `App\Models\Banner` with template source fields, constants, relationship, and helper methods
- create enums or central constants for template categories, availability, and banner source types
- add focused tests for storage, UUID, soft deletes, scopes, banner/template relationship, source helpers, existing custom banner compatibility, and null-on-delete behavior

Do not create banner images, seed the 49 templates, create festival dates, build CRUD screens, change banner limits, modify unrelated modules, implement template activation workflow, or add scheduling UI.

Run focused tests and the complete test suite.
```

### Final Outcome

Implemented the Banner Library Step 1 database/model foundation only:

- added `banner_templates` table migration
- added separate migration to add `source_type` and nullable `banner_template_id` to `banners`
- added `BannerTemplate` model with UUIDs, soft deletes, casts, constants, audit relationships, `banners()` relationship, and requested scopes
- updated `Banner` with source constants, source/template fields, enum cast, `bannerTemplate()` relationship, `template()` alias, `usesTemplate()`, and `usesCustomUpload()`
- added enums for banner template categories, template availability, and banner source types
- preserved custom-upload banner compatibility with default `source_type = custom_upload`
- used `nullOnDelete` for template references so hard-removing a template keeps live banners valid
- did not implement UI, template seed data, festival dates, image generation/uploads, activation workflow, storefront display changes, or banner limit changes

### Verification

- Focused tests: `php artisan test tests\Feature\BannerTemplateFoundationTest.php` passed: 7 tests, 15 assertions
- Full suite: `php artisan test` passed: 432 tests, 3489 assertions

## 2026-08-05 09:58 +05:30 - Banner Suggestions Fill Fields

### Exact User Prompt

```text
**Copy Suggestions**
I like it, is it possoble, when we click it cpy and paste value in fieilds?
```

### Final Outcome

Updated the banner `Copy Suggestions` buttons so clicking an example now fills the matching form field directly:

- title examples fill `Title`
- subtitle examples fill `Subtitle`
- button text examples fill `Button Text`

The click still copies the value to the clipboard, updates the live banner preview, focuses the filled field, and briefly changes the clicked suggestion to `Added`.

### Verification

- Focused banner tests were run after the change.

## 2026-08-05 09:56 +05:30 - Banner Form Copy Suggestions

### Exact User Prompt

```text
can u add at bottom , so that  admin can copy it from threre in add/edit form?
Title Examples (10)
**Up to 50% OFF**
**New Arrivals**
**Limited Time Offer**
**Shop the Latest Trends**
**Summer Collection**
**Festive Mega Sale**
**Exclusive Online Deals**
**Best Sellers**
**Premium Collection**
**Buy More, Save More**
Subtitle Examples (10)
**On Top Fashion Brands**
**Discover styles you'll love**
**Limited stock available. Shop today!**
**Fresh arrivals added every week**
**Exclusive offers for a limited time**
**Quality products at unbeatable prices**
**Handpicked collections just for you**
**Upgrade your wardrobe today**
**Save more on your favourite products**
**Fast delivery and easy returns**

Shop Now
Buy Now
Explore
View Collection
View Products
View Offers
Discover
Learn More
Order Now
See More
```

### Final Outcome

Added a `Copy Suggestions` section at the bottom of the shared banner add/edit form.

The section includes the requested title, subtitle, and button text examples as small clickable buttons. Clicking any example copies that value to the clipboard and briefly changes the button text to `Copied`.

Because the section is in the shared banner form partial, it appears on both admin and merchant banner create/edit screens.

### Verification

- Focused banner tests were run after the change.

## 2026-08-05 09:11 +05:30 - Prompt Log Canonical File Correction

### Exact User Prompt

```text
delete this banner-management-v1-log.md

and always log Prompt_Outcome_Log.md
```

### Final Outcome

Deleted the standalone `docs/banner-management-v1-log.md` file.

Confirmed the logging convention: future prompt and outcome entries should be recorded in `docs/Prompt_Outcome_Log.md` rather than separate feature-specific log files.

### Verification

- Documentation cleanup only.

## 2026-08-02 11:25 +05:30 - Payment Status Master V1

### Exact User Prompt

```text
WindowShop - Payment Status Master (V1 Final)
Objective

Implement a dedicated Payment Status Master to manage the payment lifecycle independently from the Order Status lifecycle.

Order Status and Payment Status are separate concepts and must remain independent.

Examples:

Order Status: Confirmed → Payment Status: Pending (Cash on Delivery)
Order Status: Delivered → Payment Status: Paid
Order Status: Cancelled → Payment Status: Refunded
Payment Statuses

Seed the following system payment statuses.

Code	Name	Category	Terminal
pending	Pending	Awaiting Payment	No
partially_paid	Partially Paid	Partially Paid	No
paid	Paid	Paid	Yes
failed	Failed	Failed	Yes
cancelled	Cancelled	Failed	Yes
partially_refunded	Partially Refunded	Refunded	No
refunded	Refunded	Refunded	Yes
chargeback	Chargeback	Disputed	Yes
Categories

Create the following categories.

Category	Description
Awaiting Payment	Waiting for payment.
Partially Paid	Partial payment received.
Paid	Payment completed successfully.
Failed	Payment failed or cancelled.
Refunded	Full or partial refund completed.
Disputed	Payment reversed by bank or payment gateway.
Status Descriptions

Populate the following descriptions during seeding.

Pending

Description

Payment has not yet been received.

Partially Paid

Description

Partial payment has been received. Remaining balance is still outstanding.

Paid

Description

Full payment has been received successfully.

Failed

Description

Payment attempt was unsuccessful.

Cancelled

Description

Payment was cancelled before completion.

Partially Refunded

Description

Part of the payment has been refunded.

Refunded

Description

Full payment has been refunded.

Chargeback

Description

Payment was reversed by the customer's bank or payment provider.

Merchant UI

Payment Status should be managed independently of Order Status.

Examples:

Order Status
Confirmed

Payment Status
Pending
Order Status
Delivered

Payment Status
Paid
Order Status
Cancelled

Payment Status
Refunded
POS Behaviour
Scenario	Payment Status
Cash Sale	Paid
Customer pays advance	Partially Paid
Remaining balance collected	Paid
Cash on Delivery order	Pending
Full refund	Refunded
Partial refund	Partially Refunded
Future Online Payments

This structure must support future payment gateways without schema changes.

Examples:

Razorpay
Stripe
PayPal
PhonePe
Cashfree
Paytm

Future gateway integrations can update these statuses without introducing new core payment states.

Design Rules
Payment Status and Order Status are completely independent.
A payment status change must not automatically change the order status unless business rules explicitly require it.
Business workflows can later define automatic transitions where necessary.

Examples:

Order Status: Confirmed
Payment Status: Pending
Order Status: Delivered
Payment Status: Paid
Order Status: Cancelled
Payment Status: Refunded

All of the above combinations are valid depending on the business workflow.

Expected Result

Implement a scalable Payment Status Master with:

8 system payment statuses.
Category support.
Seeded descriptions.
Independent lifecycle from Order Status.
Ready for POS today.
Compatible with future Web Shop, Mobile App, Payment Gateways, Subscriptions, Refunds, and Chargebacks without requiring database redesign.

- pls append at end the excat prompt and outcome to Prompt_Outcome_Log.md
```

### Final Outcome

Implemented the Payment Status Master V1 foundation:

- added `payment_statuses` master table with UUIDs, code, name, category, category description, status description, badge type, sort order, system/custom flags, terminal flag, merchant visibility, audit columns, timestamps, and soft deletes
- added `PaymentStatus` model with controlled categories, badge types, system defaults, scopes, safe badge rendering, UUID routing, and audit relationships
- seeded 8 system payment statuses: `pending`, `partially_paid`, `paid`, `failed`, `cancelled`, `partially_refunded`, `refunded`, and `chargeback`
- seeded category descriptions and status descriptions from the requested V1 values
- wired the seeder into `DatabaseSeeder`
- added Admin Master Data CRUD for Payment Statuses
- protected system status workflow fields so core seeded codes/categories/terminal/status values cannot be accidentally redesigned from the UI
- allowed safe system presentation edits such as name, description, category helper text, badge, sort order, and merchant visibility
- supported custom payment statuses with immutable generated codes, optional descriptions, soft delete, restore, and usage protection
- added the Payment Statuses sidebar entry under Master Data
- kept payment status lifecycle independent from order status lifecycle; no automatic order status transitions were introduced

### Verification

- `php artisan test tests\Feature\AdminPaymentStatusMasterTest.php` passed: 9 tests, 87 assertions
- `php artisan view:clear` passed
- `php artisan view:cache` passed
- `php artisan test` passed: 404 tests, 3354 assertions

## 2026-08-02 11:00 +05:30 - Order Status Master Description Columns

### User Prompt

User attached "WindowShop - Order Status History Enhancement (V1)" but clarified the current scope should be only table/master changes in `order_statuses`, and asked to append `Prompt_Outcome_Log.md`.

Requested order-status master changes:

- add `admin_description`
- add `customer_description`
- keep/use `internal_notes`
- seed all three fields for every system status
- do not implement order history workflow behavior in this step

### Final Outcome

Updated only the Order Status Master side:

- changed the `order_statuses` migration from generic `description` to `admin_description`
- added `customer_description`
- retained `internal_notes`
- updated `OrderStatus` fillable/defaults
- populated admin/customer/internal text for all 24 system statuses
- updated admin CRUD validation, search, list tooltips, and edit form fields
- system statuses now require both admin and customer descriptions on edit
- custom statuses may leave descriptions blank
- updated focused order-status tests

No `orders`, `order_status_histories`, status-change workflow, notification logic, or snapshot behavior was added.

### Verification

- Focused tests: `php artisan test tests\Feature\AdminOrderStatusMasterTest.php`
- Result: `9 passed (100 assertions)`
- Blade cache: `php artisan view:clear` and `php artisan view:cache` passed

## 2026-08-01 23:34 +05:30 - Order Status Name Tooltip Layout

### User Prompt

User asked to remove the inline truncated description from the Admin Order Status list because the tooltip icon already carries the full description. Preferred display: `Pending` with an info icon beside the name.

### Final Outcome

Updated the Admin Order Status list:

- removed the inline truncated description text under the status name
- moved the description info icon beside the status name
- kept the muted code badge under the status name

### Verification

- Blade cache: `php artisan view:clear` and `php artisan view:cache` passed

## 2026-08-01 23:31 +05:30 - Order Status List UX Polish

### User Prompt

User suggested two optional improvements for the Admin Order Status list:

- make internal `code` less prominent because admins usually care about the status name
- truncate the description in the grid and keep the full description available from an info icon

### Final Outcome

Updated the Admin Order Status list:

- removed the separate Code column
- displayed code under the status name as a small muted badge
- shortened the visible description preview
- kept the full description available through the existing info tooltip

### Verification

- Blade cache: `php artisan view:clear` and `php artisan view:cache` passed

## 2026-08-01 23:22 +05:30 - Prompt Log Rule Correction

### User Prompt

User clarified that new module-specific docs should not be created by default. For future work, append the prompt and final outcome only in `Prompt_Outcome_Log.md`, and include time in the log.

User also asked whether the earlier Order Status Master work was appended.

### Final Outcome

Confirmed that the Order Status Master prompt/outcome had been appended, but a separate `docs/Order_Status_Master.md` file had also been created.

Removed the separate `docs/Order_Status_Master.md` file.

Updated this log convention so new entries include local time.

### Verification

- Documentation/log cleanup only.

## 2026-08-01 - Expand Order Status Master Defaults And Internal Notes

### User Prompt

User requested expanding seeded default order statuses to cover order, fulfilment, cancellation, return, exchange, and failed lifecycles. User also recommended machine-friendly category values:

```text
open
processing
shipping
fulfilled
cancellation
return
exchange
failed
```

Additional requirements:

- system-seeded descriptions must be mandatory
- custom admin-created descriptions remain optional but recommended
- descriptions should display as helper text/tooltips in admin list/edit pages
- add nullable `internal_notes` for admin-only implementation/business notes
- append prompt and outcome in `Prompt_Outcome_Log.md`

### Final Outcome

Updated the Order Status Master foundation:

- expanded seeded system statuses from 6 to 24
- renamed categories to the requested machine-friendly values
- added `internal_notes` to the schema, model, admin validation, controller, views, prompt log, and tests
- seeded mandatory descriptions for all system statuses
- made system-status description required on edit
- kept custom-status description optional
- displayed descriptions and internal notes in admin list/edit UI where appropriate
- preserved seeded presentation/customisation fields on seeder rerun

No order linking, workflow transition logic, JSON registry, runtime cache, or transaction behavior was added.

### Verification

- Focused tests: `php artisan test tests\Feature\AdminOrderStatusMasterTest.php`
- Result: `9 passed (93 assertions)`
- Blade cache: `php artisan view:clear` and `php artisan view:cache` passed
- Full suite: `php artisan test`
- Result: `395 passed (3260 assertions)`

## 2026-08-01 - Global Order Status Master CRUD Foundation

### User Prompt

User attached the final prompt for "Global Order Status Master CRUD Foundation" and explicitly asked to append the prompt and outcome in `Prompt_Outcome_Log.md`.

Requested scope:

- create global `order_statuses` master table
- create model, seeder, admin CRUD, validation, protections, menu entry, and tests
- seed initial system order statuses
- protect system status workflow fields
- allow custom statuses with generated immutable codes
- check current `orders` and `order_status_histories` string references before delete
- do not link orders, replace strings, add workflows, JSON registry, caching, or other modules

### Final Outcome

Implemented the foundation-only global Order Status Master.

Added:

- `order_statuses` migration
- `App\Models\OrderStatus`
- idempotent `OrderStatusSeeder`
- admin Form Requests
- admin CRUD controller
- admin routes under `admin.master.order-statuses.*`
- sidebar menu entry under `Admin -> Master Data -> Order Statuses`
- admin list/create/edit views
- focused feature tests

Confirmed current runtime order status storage remains string-based:

```text
orders.order_status
order_status_histories.from_status
order_status_histories.to_status
```

No order linking, workflow transition logic, JSON registry, runtime cache, or payment/shipping/promotion work was added.

### Verification

- Focused tests: `php artisan test tests\Feature\AdminOrderStatusMasterTest.php`
- Result: `8 passed (78 assertions)`
- Blade cache: `php artisan view:clear` and `php artisan view:cache` passed
- Full suite: `php artisan test`
- Result: `394 passed (3245 assertions)`

## 2026-08-01 - Simplify Product Availability Customer Text Fields

### User Prompt

User clarified that internal `description` is not useful for merchant availability statuses, and that `display_label` is also unnecessary. Preferred rule: use `name` as the customer-visible label, with helper text near the name field, and keep `customer_description` for customer-facing tooltip/detail text.

### Final Outcome

Removed `display_label` and internal `description` from the product availability schema, model defaults, controller validation, merchant form, product badges, and tests.

Updated the Availability Statuses UI so:

- `Name` is the customer-visible label.
- `Customer Description` remains the optional website/mobile customer help text.
- Product and variant badges display `name`.

Updated `ProductAvailabilityResolver` so storefront/mobile payload labels use `name`.

Updated product availability documentation to describe the simplified field model.

### Verification

- Focused tests: `php artisan test tests\Feature\ProductAvailabilityStatusTest.php`
- Result: `8 passed (54 assertions)`
- Blade cache: `php artisan view:clear` and `php artisan view:cache` passed

## 2026-08-01 - Add Customer Availability Description

### User Prompt

User clarified that `customer_description` should be added now, not deferred, and asked to add default customer-facing data for availability statuses.

### Final Outcome

Added `customer_description` to product availability statuses as a separate customer-facing field from the internal merchant `description`.

Added default customer descriptions for:

- `IN_STOCK`
- `OUT_OF_STOCK`
- `PREORDER`
- `BACKORDER`
- `COMING_SOON`
- `DISCONTINUED`

Updated the merchant Availability Statuses form to edit customer description.

Updated `ProductAvailabilityResolver` so storefront/mobile payloads include:

```text
availability.description
```

Updated product availability documentation with default customer descriptions and payload example.

### Verification

- Focused tests: `php artisan test tests\Feature\ProductAvailabilityStatusTest.php`
- Result: `8 passed (53 assertions)`
- Blade cache: `php artisan view:clear` and `php artisan view:cache` passed

### Follow-Up / Notes

Website/mobile UI should use `availability.description` for future tooltips or product-detail helper text.

## 2026-08-01 - Customer-Facing Availability Description Decision

### User Prompt

User asked whether the availability `description` should be customer-facing for future website/mobile tooltip display, instead of only helping merchants understand status purpose.

### Final Outcome

Decision: keep the current `description` as merchant-facing/internal guidance.

Future storefront/mobile work should add a separate customer-facing field, such as:

```text
customer_description
```

Reason:

- Current descriptions use operational wording like "Use when..."
- Customer-facing text needs different language suitable for tooltips or product detail pages.
- Storefront/mobile availability display does not exist yet, so adding the customer field now is not required.

### Verification

- Documentation-only decision.
- No code changes.

### Follow-Up / Notes

Add `customer_description` when website/mobile product availability tooltips or product-detail messaging are implemented.

## 2026-08-01 - Availability Seeder Fix And Default Descriptions

### User Prompt

During `php artisan migrate:fresh --seed`, seeding failed because `product_availability_statuses.uuid` had no default value.

After that, the user asked to add description data for default availability statuses so merchants can understand each status purpose.

### Final Outcome

Fixed the availability default seeder so it explicitly writes a UUID when creating missing default statuses. This was needed because `DatabaseSeeder` uses `WithoutModelEvents`, so the model UUID event does not run during seeding.

Added default descriptions to all six availability statuses:

- `IN_STOCK`
- `OUT_OF_STOCK`
- `PREORDER`
- `BACKORDER`
- `COMING_SOON`
- `DISCONTINUED`

Updated the product availability documentation table to include those descriptions.

### Verification

- Focused tests: `php artisan test tests\Feature\ProductAvailabilityStatusTest.php`
- Result: `8 passed (53 assertions)`

### Follow-Up / Notes

Rerun `php artisan migrate:fresh --seed` after this fix to confirm MySQL seed completion.

## Entry Template

```text
## YYYY-MM-DD - Short Feature/Task Name

### User Prompt

Brief summary of the user request.

### Final Outcome

Brief summary of what was delivered.

### Verification

- Focused tests:
- Full suite:
- Other checks:

### Follow-Up / Notes

Anything intentionally deferred or useful for the next prompt.
```

## 2026-08-01 - Product Availability Statuses

### User Prompt

Implement a merchant-specific Product Availability Status feature for WindowShop.

The request included:

- merchant-specific availability statuses, not a global stock status master
- `purchase_allowed` as the single zero-stock customer purchase control
- six default statuses per merchant: `IN_STOCK`, `OUT_OF_STOCK`, `PREORDER`, `BACKORDER`, `COMING_SOON`, `DISCONTINUED`
- product-level availability status
- variant-level override with product inheritance
- effective availability resolver
- merchant CRUD UI
- customer storefront/mobile payload contract with `availability` and `can_purchase`
- server-side customer cart/checkout guard
- no POS inventory behaviour change
- focused tests and full regression

### Final Outcome

Implemented Merchant Product Availability Statuses and Zero-Stock Customer Purchase Behaviour.

Delivered:

- `product_availability_statuses` merchant-scoped table
- `availability_status_id` on `products`
- `availability_status_id` on `product_variants`
- `ProductAvailabilityStatus` model and relationships
- idempotent `MerchantAvailabilityStatusSeeder`
- `ProductAvailabilityResolver`
- `CustomerPurchaseAvailabilityGuard`
- merchant Availability Statuses CRUD screen under Catalog
- Customer Availability section on product form
- variant availability inheritance/override controls
- admin and merchant product list availability badges
- product duplication preservation within the same merchant
- documentation in `docs/Product_Availability_Statuses.md`

### Verification

- `php artisan view:clear` passed
- `php artisan optimize:clear` passed
- `php artisan view:cache` passed
- Focused availability tests passed: `8 passed`
- Related product/POS tests passed: `93 passed`
- Full test suite passed: `386 passed (3166 assertions)`

### Follow-Up / Notes

There is no storefront/cart module in this repo yet, so the resolver and customer guard were added for future website/mobile/cart integration.

POS stock rules were intentionally left unchanged.

## 2026-08-02 13:39 +05:30 - Merchant Cancellation Reasons CRUD Only

### Exact User Prompt

```text
WindowShop – Merchant Cancellation Reasons CRUD Only
Objective

Implement a new merchant-level Cancellation Reasons master CRUD.

This feature is only for managing cancellation reason records.

Do not integrate it with Orders, POS, refunds, returns, exchanges, webshop, customer portal, notifications, or any other workflow in this step.

Do not modify any existing module unless required only to register this new CRUD, such as routes, permissions, sidebar menu, models, migrations, requests, controllers, Blade views, seeders, and tests.

1. Table

Create the table:

merchant_cancellation_reasons

This is merchant-level and shared across all shops belonging to that merchant.

Do not add:

shop_id
product_category_id
top_parent_category_id

No shop or category mapping is required.

Migration
public function up(): void
{
    Schema::create('merchant_cancellation_reasons', function (Blueprint $table): void {
        $table->engine = 'InnoDB';
        $table->charset = 'utf8mb4';
        $table->collation = 'utf8mb4_unicode_ci';

        $table->id();
        $table->uuid('uuid')->unique();

        $table->foreignId('merchant_id')
            ->constrained('merchant_profiles')
            ->cascadeOnDelete();

        $table->string('code', 80);
        $table->string('name', 120);

        $table->string('description', 500)->nullable();
        $table->text('internal_notes')->nullable();

        $table->unsignedInteger('sort_order')->default(99);

        $table->boolean('customer_selectable')->default(false);
        $table->boolean('merchant_selectable')->default(true);
        $table->boolean('requires_comment')->default(false);

        $table->string('status', 30)->default('active')->index();

        $table->foreignId('created_by')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

        $table->foreignId('updated_by')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

        $table->timestamps();
        $table->softDeletes();

        $table->unique(
            ['merchant_id', 'code'],
            'merchant_cancellation_reasons_merchant_code_unique'
        );

        $table->index(
            ['merchant_id', 'status', 'sort_order'],
            'merchant_cancellation_reasons_merchant_status_sort_idx'
        );
    });
}
Column purpose
merchant_id
Reason belongs to one merchant and is available across all merchant shops.

code
Stable internal code. Unique per merchant.

name
Visible reason name shown in the CRUD list and dropdowns later.

description
Plain business explanation of the reason.

internal_notes
Internal admin/developer note. Not intended for customers.

sort_order
Controls reason ordering.

customer_selectable
Whether this reason may later be shown to customers.

merchant_selectable
Whether this reason may later be selected by merchant users.

requires_comment
Whether a comment will later be mandatory when this reason is selected.

status
active or inactive.

created_by
User who created the record.

updated_by
User who last updated the record.

deleted_at
Soft deletion.
2. Model

Create:

App\Models\MerchantCancellationReason

Requirements:

Use HasFactory
Use SoftDeletes
Generate UUID automatically
Add fillable fields
Add casts for boolean fields
Add constants for statuses
Add merchant relationship
Add createdBy and updatedBy relationships
Add useful scopes:
forMerchant($merchantId)
active()
ordered()

Suggested constants:

public const STATUS_ACTIVE = 'active';
public const STATUS_INACTIVE = 'inactive';

Suggested casts:

protected $casts = [
    'customer_selectable' => 'boolean',
    'merchant_selectable' => 'boolean',
    'requires_comment' => 'boolean',
    'sort_order' => 'integer',
];
3. Seeder

Create or update a dedicated seeder:

MerchantCancellationReasonSeeder

Seed the same default reasons for every existing merchant.

Use updateOrCreate() or another idempotent method so repeated seeding does not create duplicates.

The unique matching key should be:

merchant_id + code

Seed these records.

1. Customer Requested Cancellation
code:
customer_requested

name:
Customer Requested Cancellation

description:
The customer asked to cancel the order before fulfilment was completed.

internal_notes:
Customer-originated cancellation. A comment may be added when extra context is required.

sort_order:
10

customer_selectable:
true

merchant_selectable:
true

requires_comment:
false

status:
active
2. Ordered by Mistake
code:
ordered_by_mistake

name:
Ordered by Mistake

description:
The customer placed the order accidentally or selected incorrect items.

internal_notes:
Customer-originated cancellation. Normally used before shipping or pickup completion.

sort_order:
20

customer_selectable:
true

merchant_selectable:
true

requires_comment:
false

status:
active
3. Duplicate Order
code:
duplicate_order

name:
Duplicate Order

description:
The order duplicates another order already placed by the customer.

internal_notes:
Verify the related order before cancelling.

sort_order:
30

customer_selectable:
true

merchant_selectable:
true

requires_comment:
false

status:
active
4. Product Out of Stock
code:
out_of_stock

name:
Product Out of Stock

description:
One or more products required for the order are unavailable.

internal_notes:
Merchant-originated cancellation. Inventory should be reviewed separately.

sort_order:
40

customer_selectable:
false

merchant_selectable:
true

requires_comment:
false

status:
active
5. Unable to Fulfil
code:
unable_to_fulfil

name:
Unable to Fulfil Order

description:
The merchant is unable to complete the order.

internal_notes:
Use when no more specific cancellation reason applies. A comment is required.

sort_order:
50

customer_selectable:
false

merchant_selectable:
true

requires_comment:
true

status:
active
6. Store Closed
code:
store_closed

name:
Store Closed

description:
The order cannot be completed because the store is temporarily unavailable or closed.

internal_notes:
Merchant-originated cancellation.

sort_order:
60

customer_selectable:
false

merchant_selectable:
true

requires_comment:
false

status:
active
7. Payment Not Received
code:
payment_not_received

name:
Payment Not Received

description:
The required payment was not received within the expected time.

internal_notes:
Do not use this reason as a replacement for payment status management.

sort_order:
70

customer_selectable:
false

merchant_selectable:
true

requires_comment:
false

status:
active
8. Suspected Fraud
code:
suspected_fraud

name:
Suspected Fraud

description:
The order requires cancellation because fraudulent or suspicious activity is suspected.

internal_notes:
Internal reason. Should not normally be exposed to customers.

sort_order:
80

customer_selectable:
false

merchant_selectable:
true

requires_comment:
true

status:
active
9. System Error
code:
system_error

name:
System Error

description:
The order cannot continue because of a technical or system-related issue.

internal_notes:
A comment is required to record the exact issue.

sort_order:
90

customer_selectable:
false

merchant_selectable:
true

requires_comment:
true

status:
active
10. Other
code:
other

name:
Other

description:
The cancellation reason does not match any predefined option.

internal_notes:
A comment is mandatory.

sort_order:
999

customer_selectable:
true

merchant_selectable:
true

requires_comment:
true

status:
active
4. Merchant CRUD

Add a merchant-level CRUD page.

Suggested menu:

Merchant
→ Settings
→ Cancellation Reasons

or place it near the existing Return Reasons menu for consistency.

List page columns
Name
Code
Description
Customer Selectable
Merchant Selectable
Requires Comment
Status
Sort Order
Actions
CRUD capabilities

Implement:

List use DataTables 

Create
Edit
Activate
Deactivate
Soft delete
Trash list
Restore
Merchant scoping

Every query must be restricted to the authenticated merchant.

A merchant must never view, edit, restore, or delete another merchant’s cancellation reason.

Use the current active merchant context already used by the project.

Validation
Create
code
required
string
max 80
lowercase snake_case format
unique within the current merchant, including active records

name
required
string
max 120

description
nullable
string
max 500

internal_notes
nullable
string

sort_order
required
integer
minimum 0

customer_selectable
boolean

merchant_selectable
boolean

requires_comment
boolean

status
required
in active,inactive
Update
code should remain unique within the merchant.
Prefer making code read-only after creation to keep it stable.
Allow editing all other fields.
Business rules
other must always have requires_comment = true.
At least one of customer_selectable or merchant_selectable should be true.
Soft-deleted records must not appear in the normal list.
Inactive records must remain editable.
No force-delete UI is required in this step.
Do not create integration logic with orders.
5. Permissions

Add permissions consistent with the existing project convention, for example:

merchant.cancellation-reasons.view
merchant.cancellation-reasons.create
merchant.cancellation-reasons.update
merchant.cancellation-reasons.delete
merchant.cancellation-reasons.restore

Assign them to the appropriate merchant role through the existing permission seeder pattern.

Do not alter unrelated permissions.

6. UI Requirements

Follow the current Laravel Blade, Bootstrap 5, and Limitless design used in the project.

Create:

index
create
edit
trash
_form partial

Use:

Status badges
Yes/No badges for boolean fields
Confirmation before delete
Confirmation before restore
Validation errors
Success/error flash messages
Existing pagination style
Existing breadcrumb and card layout
7. Tests

Add focused feature tests for only this CRUD.

Cover:

Merchant can view own cancellation reasons
Merchant cannot access another merchant’s reasons
Create reason
Update reason
Duplicate code rejected within same merchant
Same code allowed for a different merchant
Invalid code format rejected
other requires requires_comment = true
At least one selectable audience is required
Status filters
Search
Soft delete
Trash list
Restore
Seeder is idempotent
Default reasons are created for existing merchants

Run focused tests and report the exact test results.

Strict Scope

Do not touch or modify:

POS
Order creation
Order cancellation workflow
Order statuses
Payment statuses
Returns
Refunds
Exchanges
Inventory
Stock
Receipts
Notifications
Webshop
Customer portal
Mobile API
Payment settings
Merchant return reasons

Do not add:

shop_id
category_id
top_parent_category_id
order_id
order_item_id
refund behaviour
restock behaviour
notification behaviour

This step is strictly:

merchant_cancellation_reasons
migration
model
seeder
merchant CRUD
permissions
tests

Stop after the independent CRUD is implemented and verified.

pls append at end the excat prompt and outcome to Prompt_Outcome_Log.md
```

### Final Outcome

Implemented the independent merchant Cancellation Reasons CRUD only:

- added `merchant_cancellation_reasons` migration with merchant-level scoping, UUID, code/name, descriptions, flags, status, audit users, timestamps, soft deletes, unique merchant/code key, and merchant/status/sort index
- added `MerchantCancellationReason` model with UUIDs, factories, soft deletes, casts, status constants, merchant/audit relationships, and `forMerchant`, `active`, and `ordered` scopes
- added idempotent `MerchantCancellationReasonSeeder` with all 10 requested default reasons for every existing merchant
- seeded requested permission slugs and assigned them to the merchant role
- added merchant CRUD routes for index, create, edit, update, soft delete, trash, and restore
- added Merchant Sales sidebar link beside Return reasons
- added Blade views: `index`, `create`, `edit`, `trash`, `_form` partial, and shared confirmation/DataTables script partial
- enforced merchant scoping for view/edit/update/delete/trash/restore
- enforced create code format/uniqueness, read-only code after creation, `other` requires comment, and at least one selectable audience
- did not integrate with Orders, POS, refunds, returns, exchanges, webshop, customer portal, notifications, inventory, stock, receipts, mobile API, or payment settings

### Verification

- `php artisan test tests\Feature\MerchantCancellationReasonCrudTest.php` passed: 7 tests, 53 assertions
- `php artisan view:cache` passed
- `php artisan test` passed: 411 tests, 3407 assertions

## 2026-08-03 10:32 +05:30 - Product Quantity Foundation Step 1B

### Exact User Prompt

```text
Convert these columns
Table	Column	New Type
product_variants	stock_quantity	DECIMAL(12,3)
product_variants	low_stock_threshold	DECIMAL(12,3)
order_items	quantity	DECIMAL(12,3)
order_refund_items	quantity	DECIMAL(12,3)
order_exchange_return_items	quantity	DECIMAL(12,3)

Approved.

Product Variant Selling Rules

Approve these:

allow_decimal_quantity
quantity_increment
minimum_order_quantity
maximum_order_quantity
quantity_multiple
allow_backorder
Two improvements
1. Rename quantity_multiple

I don't like this name.

Magento, Shopify, ERP systems usually call this:

purchase_quantity_multiple

or

order_quantity_multiple

because it clearly means:

Customer must purchase in multiples of X.

Example:

Tiles

Minimum = 10

Multiple = 5

Allowed

10
15
20
25

I recommend:

purchase_quantity_multiple

Much clearer.

2. Add maximum_order_quantity

Instead of NULL forever, I recommend:

NULL = Unlimited

That's already your proposal, and I agree.

One more thing we should add NOW

This is the only thing I think we're still missing.

is_sellable
boolean
default true

Meaning:

true

Product can be purchased.

false

Cannot be purchased.

Useful for:

Seasonal products
Supplier stopped
Legal restriction
Hidden but stock kept

Different from:

status

because:

status = active

means

Product exists.

is_sellable = false

means

Do not allow purchase.

Final Product Variant Future Columns
allow_decimal_quantity

quantity_increment

minimum_order_quantity

maximum_order_quantity

purchase_quantity_multiple

allow_backorder

is_sellable
One thing I do NOT recommend

Don't add

measurement_unit

yet.

Because until decimal selling exists, it will never be used.

Final Verdict

I would approve Step 1B with only these small adjustments:

✅ Convert the 5 quantity columns to DECIMAL(12,3).
✅ Add the future selling-rule columns.
✅ Rename quantity_multiple → purchase_quantity_multiple.
✅ Add is_sellable (default true).
❌ Do not add measurement units yet.

I think this gives WindowShop a future-proof product model without affecting any current POS or order functionality. This is one of those schema changes that's much easier to make now than after the webshop and additional modules are in place.
```

### Final Outcome

Implemented Product Quantity Foundation Step 1B as database/model foundation only:

- converted fresh-schema definitions for `product_variants.stock_quantity`, `product_variants.low_stock_threshold`, `order_items.quantity`, `order_refund_items.quantity`, and `order_exchange_return_items.quantity` to `decimal(12,3)`
- added a forward migration to add the product variant selling-rule fields and convert existing MySQL dev databases without data loss
- added `allow_decimal_quantity`, `quantity_increment`, `minimum_order_quantity`, `maximum_order_quantity`, `purchase_quantity_multiple`, `allow_backorder`, and `is_sellable` to `product_variants`
- updated `ProductVariant` fillable fields and casts for the new future selling-rule fields
- did not add `measurement_unit`
- did not change POS, cart, checkout, orders, refunds, returns, exchanges, reports, inventory logic, validation, Blade templates, merchant UI, APIs, or demo behaviour
- kept current stock quantity model casts as integer so existing whole-number behavior remains unchanged until decimal selling logic is implemented later

### Verification

- `php artisan test tests\Feature\ProductQuantityFoundationTest.php` passed: 2 tests, 18 assertions
- `php artisan test tests\Feature\MerchantPosTest.php tests\Feature\OrderFoundationTest.php tests\Feature\ProductAvailabilityStatusTest.php tests\Feature\MerchantProductManagementTest.php` passed: 98 tests, 713 assertions
- `php artisan test` passed: 413 tests, 3425 assertions

## 2026-08-03 10:48 +05:30 - Product Quantity Foundation Migration Cleanup

### Exact User Prompt

```text
        Schema::table('product_variants', function (Blueprint $table): void {
if possible pls move it too its main migration
```

### Final Outcome

Removed the separate forward migration `2026_08_03_000001_add_decimal_quantity_foundation_to_product_and_order_tables.php`.

The approved decimal quantity columns and product variant future selling-rule columns now live directly in the original create migrations, matching the dev-stage `migrate:fresh --seed` workflow.

### Verification

- `php artisan test tests\Feature\ProductQuantityFoundationTest.php` passed: 2 tests, 18 assertions

# Banner Management V1 Prompt And Outcome

## Prompt

Use a simple reusable banner foundation for WindowShop, but do not create a `banner_positions` table for V1. Banner positions are few and rarely change, so define them in `App\Enums\BannerPosition` with scope, label, metadata, and maximum banner limits. Store the selected position directly on `banners.position`, without a foreign key. Merchants may only use merchant-scoped positions, admins may use admin-scoped marketplace positions or explicitly create merchant-store banners. Log the prompt and outcome.

## Outcome

Implemented the V1 enum-based approach:

- Added `App\Enums\BannerPosition` for fixed banner locations, scope checks, labels, descriptions, max limits, and recommended image dimensions.
- Added `App\Enums\BannerLinkType` for supported banner link types.
- Added a generic `banners` table with `position`, owner fields, desktop/mobile images, link fields, schedule, sort order, status, audit fields, UUID, and soft deletes.
- Added `App\Models\Banner` with relationships and query scopes for marketplace, merchant, shop, position, current visibility, and ordering.
- Added admin and merchant banner CRUD foundations.
- Added validation for owner scope, active/scheduled max limits, shop ownership, schedules, image uploads, link targets, and merchant active-shop ownership.
- Added storefront helpers through `BannerService`, `BannerLinkResolver`, and a reusable `<x-storefront.banner-slider>` component.
- Deferred configurable banner positions and a `banner_positions` table until administrators need to create custom positions.

# Banner Template Selection And Activation Prompt And Outcome

## 2026-08-05 13:01 +05:30 - Next WindowShop Banner Phase

### Exact User Prompt

```text
Implement the next WindowShop Banner phase:

Banner Template selection and activation for both Admin and Merchant.

Current foundation already exists:

- `banner_templates` table
- `banners` table
- `banners.banner_template_id`
- `banners.source_type`
- BannerTemplate model
- Banner model relationship
- Admin Banner Template CRUD
- Banner Template list and edit screens
- Existing Admin Banner CRUD
- Existing Merchant Banner CRUD, if already present
- `system_setting_groups`
- `system_settings`
- New global settings must use `system_settings`
- `admin_settings` is legacy and must not receive new settings

Important frozen decisions:

1. Banner templates are reusable WindowShop master designs.
2. A live banner is a separate row in `banners`.
3. Admin and Merchant can select a template and create a live banner from it.
4. Template values are copied into the live banner.
5. Editing a live banner must never modify the master template.
6. Merchant banner slots are limited per shop.
7. The limit is read from `storefront_banner.max_per_shop`.
8. Default value is 3.
9. All non-soft-deleted merchant banners count toward the limit, including active, inactive and scheduled banners.
10. Merchant may later replace template images with a custom upload.
11. Marketplace/Admin banners are not restricted by the merchant per-shop limit.
12. Do not add new settings to `admin_settings`.

Implementation scope:

- Inspect existing Admin/Merchant Banner CRUD, requests, routes, views, sessions, enums, upload services, settings access patterns, menus, status constants, authorization, Quick Suggestions UI, and tests before coding.
- Add focused `system_settings` access through a service such as `App\Services\System\SystemSettingService`.
- Add a banner limit method/service that reads `storefront_banner.max_per_shop`, falls back to 3, clamps effective values to 1-10, and treats invalid values as 3.
- Add `App\Services\Banner\BannerTemplateLibraryService` for Admin/Merchant template-library queries, availability filtering, category filtering, search, and stable ordering.
- Add `App\Services\Banner\BannerTemplateActivationService` for Admin create-from-template, Merchant create-from-template, and replacing the template on an existing live banner.
- Copy template values into `banners` without modifying `banner_templates`.
- Use `source_type = template` for template-created banners and `source_type = custom_upload` for custom upload banners.
- For Merchant-created banners, assign `merchant_id` and `shop_id` from the current merchant and active shop on the server.
- For Admin-created marketplace banners, keep `merchant_id` and `shop_id` null.
- For Admin-created merchant-store banners, validate that the selected shop belongs to the selected merchant.
- Support recommended dates from fixed-date template events where reliable; do not invent variable festival dates.
- Add an Admin Banner Library or integrate "Use Template" into the existing Admin Banner create flow.
- Add a Merchant Banner Library under the existing Merchant marketing/storefront menu convention.
- Enforce merchant banner slot limits using all non-soft-deleted banners for the merchant and shop, including active, inactive, scheduled, and expired banners.
- Add replace-template behavior that reuses the existing banner row, defaults to replacing images only, can optionally reset fields, and never deletes shared template images.
- Preserve existing custom upload workflow, safely delete only owned custom files after successful replacement, and never delete shared template files.
- Update existing Banner create/edit forms with a Banner Source selector while preserving existing Quick Suggestions for live banners.
- Validate owner/position/template availability compatibility using the existing BannerPosition enum/registry.
- Reuse existing link target handling and searchable selectors where available.
- Keep controllers small, place business rules in services/requests/policies, use transactions, avoid N+1 queries, and preserve existing Admin and Merchant Banner functionality.
- Add focused tests for settings, Admin activation, Merchant library, Merchant activation, slot limits, replace-template behavior, and custom upload behavior.
- Run focused tests and then the full suite.

Important implementation rules:

1. Do not add new settings to `admin_settings`.
2. Use `system_settings` for the banner limit.
3. Do not add a new settings table.
4. Do not add a `banner_positions` table.
5. Reuse existing enums and registries.
6. Do not hardcode the banner limit.
7. Do not modify BannerTemplate when creating/editing a live banner.
8. Do not delete shared template images.
9. Keep controllers small.
10. Put business rules in services, requests or policies.
11. Use transactions for create/replace/upload operations.
12. Preserve existing Admin and Merchant Banner functionality.
13. Avoid N+1 queries.
14. Do not implement Promotion Engine in this task.
15. Do not generate the final 49 image assets in this task.
16. Do not redesign unrelated pages.
17. Follow existing project route, Blade, Bootstrap and Limitless conventions.

After implementation, report files created/modified, routes, menu changes, services, setting access, banner limit behaviour, Admin and Merchant flows, replace-template behavior, custom upload behavior, authorization rules, tests, focused/full suite results, assumptions, and deferred items.
```

### Final Outcome

Implemented the next WindowShop Banner phase for Admin and Merchant template selection, activation, source handling, slot limits, and replacement.

Files created:

- `app/Http/Controllers/Admin/BannerLibraryController.php`
- `app/Http/Controllers/Merchant/BannerLibraryController.php`
- `app/Services/System/SystemSettingService.php`
- `app/Services/Banner/BannerLimitService.php`
- `app/Services/Banner/BannerTemplateLibraryService.php`
- `app/Services/Banner/BannerTemplateActivationService.php`
- `resources/views/admin/banner-library/index.blade.php`
- `resources/views/merchant/banner-library/index.blade.php`
- `tests/Feature/BannerLimitServiceTest.php`
- `tests/Feature/BannerTemplateActivationFlowTest.php`

Files modified:

- `app/Http/Controllers/Admin/BannerController.php`
- `app/Http/Controllers/Merchant/BannerController.php`
- `app/Http/Requests/Concerns/ValidatesBannerRequest.php`
- `app/Services/Banner/BannerImageService.php`
- `resources/views/admin/banners/edit.blade.php`
- `resources/views/admin/banners/index.blade.php`
- `resources/views/merchant/banners/edit.blade.php`
- `resources/views/merchant/banners/index.blade.php`
- `resources/views/partials/sidebar.blade.php`
- `resources/views/partials/merchant/sidebar.blade.php`
- `resources/views/shared/banners/form-fields.blade.php`
- `resources/views/shared/banners/form-script.blade.php`
- `routes/web.php`
- `routes/merchant.php`
- `docs/Prompt_Outcome_Log.md`

Routes added:

- `admin.banner-library.index`
- `admin.banners.replace-template`
- `merchant.banner-library.index`
- `merchant.banners.replace-template`

Menu changes:

- Admin Marketing now includes `Banner Templates`, `Banner Library`, and `Banners`.
- Merchant Storefront now includes `Banner Library` and `My Banners`.

Services added:

- `SystemSettingService` reads active, non-soft-deleted `system_settings` values and casts string, integer, boolean, json, array, and text values.
- `BannerLimitService` reads the merchant per-shop slot limit from `storefront_banner.max_per_shop`.
- `BannerTemplateLibraryService` returns active, non-deleted Admin/Merchant-usable templates with availability, category, position, event/general, search, and ordering rules.
- `BannerTemplateActivationService` creates live banners from templates, replaces templates on existing banners, copies template defaults, and computes fixed-date recommended schedules.

System setting access implemented:

- New banner settings read from `system_settings`.
- No new setting was added to `admin_settings`.
- No new settings table was added.

Banner limit behaviour:

- Merchant banner limit reads `storefront_banner.max_per_shop`.
- Missing, inactive, invalid, below-range, and above-range values fall back safely to 3.
- Effective values are accepted only from 1 through 10.
- All non-soft-deleted merchant banners for the merchant and shop count as slots.
- Soft-deleted banners do not count.
- Merchant template activation and custom upload creation perform final slot validation inside a transaction.

Admin template activation flow:

- Admin can browse active Admin/Both templates in `admin.banner-library.index`.
- Admin can open the existing Banner create form prefilled from a template.
- Template-created marketplace banners copy template values into a new `banners` row.
- Admin can also create merchant-store banners from merchant-compatible templates by selecting Merchant Store owner, merchant, and shop.
- Shop ownership and owner/position compatibility continue to be validated server-side.

Merchant Banner Library flow:

- Merchant can browse active Merchant/Both templates in `merchant.banner-library.index`.
- Merchant library supports search, category, position, and event/general filters.
- The page shows `{used} of {limit} banner slots used`.
- If the shop has reached the configured limit, no banner is created and existing slots are shown with edit actions.
- Merchant-created banners use the current merchant and active shop from server-side context.

Replace-template behaviour:

- Admin and Merchant edit pages include `Replace Template`.
- Replacing a template reuses the same banner row.
- Default option replaces images only.
- Optional modes reset text defaults or all template defaults including position.
- `banner_template_id` updates and `source_type` becomes `template`.
- Shared template images are never deleted.

Custom upload behaviour:

- Existing custom upload workflow remains available.
- Banner forms now include `Banner Source` with `Use WindowShop Template` and `Upload Custom Banner`.
- Switching from template to custom requires a new desktop image.
- Custom uploads set `source_type = custom_upload` and clear `banner_template_id`.
- Old files are deleted only when they are owned live-banner custom files.

Authorization rules:

- Merchant routes resolve merchant and active shop from session/user context.
- Merchants cannot choose another merchant or shop.
- Merchants cannot use inactive, soft-deleted, or Admin-only templates.
- Merchants cannot replace another merchant/shop banner.
- Admin owner type and shop ownership checks remain server-side.
- Arbitrary banner positions are still rejected through `BannerPosition`.

Tests added:

- `BannerLimitServiceTest`
- `BannerTemplateActivationFlowTest`

Focused test results:

- `php artisan test tests\Feature\BannerLimitServiceTest.php tests\Feature\BannerTemplateActivationFlowTest.php tests\Feature\BannerManagementFoundationTest.php tests\Feature\AdminBannerTemplateManagementTest.php tests\Feature\BannerTemplateFoundationTest.php tests\Feature\BannerTemplateSeederTest.php tests\Feature\StorefrontBannerSettingSeederTest.php`
- Passed: 29 tests, 132 assertions

Full test-suite results:

- `php artisan test`
- Passed: 451 tests, 3610 assertions

Assumptions and deferred items:

- Promotion Engine remains deferred and disabled through the existing link type options.
- Variable-date festival auto-fill remains deferred because no maintained event-date source was found.
- Final 49 banner image assets were not generated.
- No `banner_positions` table was added.

--------------------------------------------------

Date:
2026-08-07

Feature Name:
Static Storefront Blade Conversion

Objective:
Convert the selected static Amerce storefront homepage template into a clean Laravel Blade storefront structure for WindowShop without connecting dynamic data.

Scope:
HTML slicing, shared storefront layout creation, reusable static components, dedicated storefront asset copy, and temporary storefront preview route.

Prompt Summary:
Convert `template/index.html` to Blade, preserve visual styling and plugin hooks, keep original template files unchanged, use one common storefront layout, and defer all database-driven storefront integrations.

Files Created:
- `resources/views/storefront/layouts/app.blade.php`
- `resources/views/storefront/pages/home.blade.php`
- `resources/views/storefront/partials/topbar.blade.php`
- `resources/views/storefront/partials/header.blade.php`
- `resources/views/storefront/partials/main-menu.blade.php`
- `resources/views/storefront/partials/mobile-menu.blade.php`
- `resources/views/storefront/partials/hero.blade.php`
- `resources/views/storefront/partials/search.blade.php`
- `resources/views/storefront/partials/footer.blade.php`
- `resources/views/storefront/partials/scripts.blade.php`
- `resources/views/storefront/components/category-card.blade.php`
- `resources/views/storefront/components/product-card.blade.php`
- `resources/views/storefront/components/banner.blade.php`
- `public/assets/storefront/*`

Files Modified:
- `routes/web.php`
- `docs/Prompt_Outcome_Log.md`
- `docs/Architecture_Decisions.md`

Database Changes:
- None

Routes Added:
- `GET /storefront` named `storefront.home`

Services Added:
- None

Controllers Added/Modified:
- None

Models Added/Modified:
- None

Requests Added/Modified:
- None

Views Added/Modified:
- Storefront layout, homepage, partials, and static reusable components listed above.

Tests Added:
- None

Verification Results:
- `php artisan view:clear` passed.
- `php artisan route:list --path=storefront` showed `GET|HEAD storefront`.
- `php artisan view:cache` passed.
- `php artisan test` passed: 451 tests, 3610 assertions.
- `Invoke-WebRequest http://127.0.0.1:8082/unwanted/localhyper/windowshop/public/storefront` returned HTTP 200.
- Rendered storefront HTML includes `assets/storefront/css/styles.css`, `assets/storefront/js/main.js`, and `tf-swiper` markup.
- Storefront Blade scan found no leftover raw `src="assets/`, `href="assets/`, `.html`, or `template/` references.

Assumptions:
- Root `/` remains reserved for the existing admin-login redirect, so storefront preview uses `/storefront`.
- Amerce template assets are copied into `public/assets/storefront` and referenced via Laravel `asset()`.

Deferred Items:
- BannerService integration
- Real products, categories, merchants, shops, cart, wishlist, checkout, login, search backend, promotions, and APIs
- Grocery-specific storefronts
- Category-specific home layouts
- Browser-level screenshots, responsive visual comparison, and console validation were not completed because no in-app browser was available in this session.

--------------------------------------------------

Date:
2026-08-07

Feature Name:
Storefront Navigation V1

Objective:
Implement dynamic WindowShop storefront navigation using the existing `product_categories` hierarchy while preserving the Amerce template menu classes and behaviour.

Scope:
Marketplace category mega menu, merchant shop category-scoped menu, placeholder navigation routes, service-layer category tree loading, Blade loop conversion, and focused tests.

Prompt Summary:
Replace the static storefront menu with dynamic navigation generated from active, non-deleted product categories; use one common storefront layout; do not add product listing, search backend, cart, wishlist, login, checkout, or promotion behaviour.

Files Created:
- `app/Services/Storefront/NavigationService.php`
- `app/Http/Controllers/Storefront/StorefrontController.php`
- `resources/views/storefront/pages/placeholder.blade.php`
- `tests/Feature/StorefrontNavigationTest.php`

Files Modified:
- `routes/web.php`
- `resources/views/storefront/partials/main-menu.blade.php`
- `docs/Prompt_Outcome_Log.md`
- `docs/Architecture_Decisions.md`

Database Changes:
- None

Routes Added:
- `GET /category/{slug}` named `storefront.category.show`
- `GET /store/{slug}` named `storefront.store.show`
- `GET /store/{slug}/category/{categorySlug}` named `storefront.store.category.show`

Services Added:
- `App\Services\Storefront\NavigationService`

Controllers Added/Modified:
- Added `App\Http\Controllers\Storefront\StorefrontController`

Models Added/Modified:
- None

Requests Added/Modified:
- None

Views Added/Modified:
- Dynamic storefront main menu
- Storefront placeholder page

Tests Added:
- `StorefrontNavigationTest`

Verification Results:
- `php artisan test --filter=StorefrontNavigationTest` passed: 5 tests, 43 assertions.
- `php artisan view:cache` passed.
- `php artisan test` passed: 456 tests, 3653 assertions.
- `Invoke-WebRequest http://127.0.0.1:8082/unwanted/localhyper/windowshop/public/storefront` returned HTTP 200 and rendered the storefront menu shell.

Assumptions:
- Active storefront products are products with `status = active` and no soft delete.
- Merchant navigation is limited to the active shop's root category tree and categories that have active products in that shop.

Deferred Items:
- Product listing pages
- Category filters
- Search backend
- Cart, wishlist, customer login, checkout
- Promotion Engine and offer logic
- Root category icon support

--------------------------------------------------

Date:
2026-08-12

Feature Name:
Dynamic Storefront Product Listing Step 1

Objective:
Convert the existing static customer `/products` page into a dynamic storefront product listing while preserving the current Blade structure and storefront design.

Scope:
Dynamic product query, storefront eligibility rules, default variant pricing, primary image fallback, discount badge calculation, empty state, Laravel pagination, and focused tests. Filters, search, real sorting, ratings, color variants, cart, wishlist, and product detail changes remain deferred.

Prompt Summary:
Move WindowShop customer storefront products from static content to dynamic data using existing models, routes, controller structure, variants, primary images, shop/merchant relationships, and current storefront design. Do not redesign the page or add fake ratings/shop names/color dots.

Files Created:
- `app/Services/Storefront/ProductListingService.php`
- `resources/views/storefront/partials/pagination.blade.php`
- `tests/Feature/StorefrontProductListingTest.php`

Files Modified:
- `app/Models/Product.php`
- `app/Http/Controllers/Storefront/StorefrontController.php`
- `resources/views/storefront/pages/products.blade.php`
- `docs/Prompt_Outcome_Log.md`

Database Changes:
- None

Routes Added:
- None; reused existing `GET /products` named `storefront.products`.

Services Added:
- `App\Services\Storefront\ProductListingService`

Controllers Added/Modified:
- Modified `App\Http\Controllers\Storefront\StorefrontController::products()`

Models Added/Modified:
- Added `Product::storefrontCardVariant()` relation for the selected default storefront variant alias.

Requests Added/Modified:
- None

Views Added/Modified:
- Existing products page now renders dynamic product cards.
- Added storefront pagination partial using existing `tf-page-pagination` classes.

Tests Added:
- `StorefrontProductListingTest`

Verification Results:
- `php artisan test tests\Feature\StorefrontProductListingTest.php` passed: 5 tests, 32 assertions.
- `Invoke-WebRequest http://127.0.0.1:8082/unwanted/localhyper/windowshop/public/products` returned HTTP 200.

Assumptions:
- Current storefront publish signal is `products.status = active`; `published_at` exists but is not populated by the current admin/merchant activation flows yet.
- Valid storefront products require an active product, active merchant, active shop tied to the same merchant, and an active sellable default variant with positive MRP and selling price.
- Product detail remains the existing static `storefront.product.detail` route until the product detail step defines dynamic URLs.
- No real ratings/reviews table exists yet, so product-listing stars are hidden.
- Color variant dots are deferred because proper attribute presentation needs a separate implementation.

Deferred Items:
- Search
- Filters
- Dynamic sorting behaviour
- Category listing
- Dynamic product detail URLs/content
- Real ratings/reviews
- Dynamic color/variant indicators
- Add to cart, wishlist, and quick view behaviour

--------------------------------------------------

Date:
2026-08-15

Feature Name:
Checkout One-Page Foundation

Objective:
Convert the temporary checkout wizard/address placeholder into a one-page checkout foundation reached at `/checkout` after customer login/register and cart merge.

Scope:
One-page checkout route, customer auth gate, `/checkout/address` compatibility redirect, delivery address section, saved/default address selection, inline Add/Edit address forms, postal-code validation, delivery option structure, COD payment structure, server-side cart order summary, deferred Place Order endpoint, and fast-checkout-ready default preselection.

Prompt Summary:
Use one main checkout page instead of Address -> Shipping -> Payment pages. Reuse existing cart pricing and customer address architecture, avoid duplicate checkout address tables, keep guests out of final checkout, and do not implement real order creation or payment gateways yet.

Files Created:
- `app/Http/Controllers/Storefront/CheckoutAddressController.php`
- `app/Services/Checkout/CheckoutPageService.php`
- `resources/views/storefront/pages/partials/checkout-address-form.blade.php`

Files Modified:
- `app/Http/Controllers/Storefront/CheckoutController.php`
- `app/Http/Controllers/Storefront/StorefrontController.php`
- `app/Services/Checkout/CheckoutFlowService.php`
- `routes/web.php`
- `resources/views/storefront/pages/checkout.blade.php`
- `tests/Feature/StorefrontCheckoutGateTest.php`
- `docs/Prompt_Outcome_Log.md`

Files Removed:
- `resources/views/storefront/pages/checkout-address.blade.php`

Routes Changed:
- `GET /checkout` now renders the authenticated one-page checkout or redirects guests to `/checkout/account`.
- `GET /checkout/address` now redirects to `/checkout` for backward compatibility.
- Added `POST /checkout/address/select`.
- Added `POST /checkout/addresses`.
- Added `PATCH /checkout/addresses/{address}`.
- Added deferred `POST /checkout/place-order` placeholder.

Address Model/Table Reused:
- Reused `merchant_customer_addresses` through `merchant_customers.user_id`.
- New checkout addresses are attached to the cart's first merchant customer profile; the merchant-customer link is created only when needed.

Default Address Selection:
- Active default shipping address is selected first.
- If there is exactly one active saved address, it is selected automatically.
- A valid manually selected address is kept in checkout session state.

Shipping/PIN Foundation:
- Selected address PIN is exposed to the delivery options section.
- Current V1 shows a controlled Standard Delivery structure only after an address with a valid PIN is selected.
- Complex shipping pricing/serviceability remains deferred.

Order Summary:
- Uses `CartPageService` current server-side cart data and totals.
- Browser-submitted totals are ignored by the deferred place-order endpoint.

Fast Checkout Preparation:
- Shows a disabled fast-checkout panel when the customer has a selected/default address and eligible checkout state.
- It uses the same selected address, shipping, payment, and cart summary data as the normal checkout foundation.

Tests Added/Updated:
- Updated checkout gate/merge redirects for `/checkout`.
- Added coverage for saved/default addresses, address validation, add/update address, address ownership, current cart totals, ignored browser totals, old route redirect, stale/unavailable cart items, and absence of wizard navigation.

Verification Results:
- `php artisan test tests\Feature\StorefrontCheckoutGateTest.php` passed: 19 tests, 108 assertions.
- `php artisan test tests\Feature\StorefrontCustomerAuthPagesTest.php` passed: 4 tests, 60 assertions.
- `php artisan test tests\Feature\StorefrontCartPageTest.php` passed: 18 tests, 103 assertions.
- `php artisan test tests\Feature\StorefrontAddToCartTest.php` passed: 18 tests, 81 assertions.

## 2026-10-03 - Variant-aware cart and checkout presentation

Goal: Keep cart items for different variants of one product visually distinguishable by showing their selected attributes and the image assigned to the selected image attribute.

Decisions and outcome:

- `CartPageService` remains the shared item presenter for full cart, mini-cart, and checkout; no parallel cart or variant representation was added.
- Cart image selection now delegates to the established `ProductImageService::galleryForVariant()` resolver. Its active-image precedence is selected image-attribute assignment, Entire Product/unassigned image, active product primary image, then the existing storefront placeholder.
- Mini-cart now renders the existing generic item `attributes` collection in both its initial Blade markup and its JavaScript refresh path. Full-cart and checkout already rendered this collection and checkout already consumed the shared cart payload.
- Pricing, quantities, promotions, availability, add-to-cart behavior, and product-detail gallery behavior were not changed.

Key files: `CartPageService`, `ProductImageService` (reused unchanged), storefront mini-cart partial, full-cart/checkout views (consumers unchanged), and `StorefrontCartPageTest`.

Verification: cart/image suites passed (48 tests, 329 assertions); focused checkout summary tests passed (2 tests, 20 assertions); Blade compilation, Pint, PHP lint, mini-cart JavaScript syntax, and `git diff --check` passed. The complete checkout suite had four unrelated existing failures involving country fixture setup, notification transaction timing, and an availability-message expectation. Browser verification was unavailable because this session exposed no browser surface.

Deferred Items:
- Full shipping engine and delivery pricing.
- Payment gateway integrations beyond COD display.
- Final order creation and immutable address snapshot.
- Invoice, fulfillment, and final fast checkout one-click ordering.

--------------------------------------------------

Date:
2026-08-15

Feature Name:
Delivery Address Step 1 - India PIN Validation

Objective:
Make checkout delivery addresses country-aware, using `postal_codes` only for India PIN validation/autofill and `loc_*` masters for the actual saved customer address location.

Scope:
Country field, Home/Work/Other address type, India PIN blur lookup, backend India PIN validation, city/district and state autofill, `loc_countries`/`loc_states`/`loc_cities` ID resolution on save, shop-level postal-code restriction lookup, shipping-disabled distinction, and default delivery address preservation.

Files Created:
- `app/Services/Checkout/CheckoutPostalCodeLookupService.php`

Files Modified:
- `app/Http/Controllers/Storefront/CheckoutAddressController.php`
- `app/Services/Checkout/CheckoutPageService.php`
- `routes/web.php`
- `resources/views/storefront/pages/checkout.blade.php`
- `resources/views/storefront/pages/partials/checkout-address-form.blade.php`
- `tests/Feature/StorefrontCheckoutGateTest.php`
- `docs/Prompt_Outcome_Log.md`

Routes Added:
- `GET /checkout/postal-code/{postalCode}` named `storefront.checkout.postal-code.show`

Service/Controller:
- `CheckoutAddressController::postalCode()` is the thin lookup endpoint.
- `CheckoutPostalCodeLookupService` validates India PINs, resolves district/state, reports `shipping_enabled`, and checks current-cart shop restrictions.

India Identification:
- India is resolved from active `loc_countries` by `iso2 = IN`, `iso3 = IND`, or name `India`.

Country Preselection:
- The checkout address form defaults Country to India when the India country master exists. This is only a preselection; saved validation uses the submitted `country_id`.

PIN Lookup:
- For India, the PIN field triggers lookup on blur and the backend repeats validation on save.
- Lookup uses active, non-deleted `postal_codes` rows and supports multiple rows per PIN.

City/State Population:
- `postal_codes.district` is shown as City and `postal_codes.state` as State in the form.
- These frontend values are never trusted for save; the backend re-resolves from the submitted PIN.

Customer Address Mapping:
- India saves `country_id` from `loc_countries`, `state_id` by `loc_states.name + country_id`, and `city_id` by `loc_cities.name + state_id + country_id`.
- No foreign keys were added from `postal_codes` to `loc_*`.

City-Master Mismatch:
- A valid Indian PIN is not rejected only because an exact `loc_cities` match is unavailable.
- In that case, `city_id` remains nullable rather than assigning a wrong city.

Shop Restrictions:
- Restrictions are checked for every shop in the current cart.
- The lookup returns per-shop availability and honors active status, soft deletes, `starts_at`, and `ends_at`.
- Active shop-specific, merchant-level, and global restriction scopes are supported without one query per shop.

Tests Added/Updated:
- India selected requires PIN.
- Non-India skips India PIN validation.
- Valid/invalid PIN behavior.
- City/district and State lookup.
- `shipping_enabled = false` remains valid but unavailable.
- Active restrictions are detected.
- Inactive, future, and expired restrictions are ignored.
- Multiple shops return per-shop availability.
- India save ignores spoofed city/state fields.
- City-master mismatch does not reject a valid PIN.
- Existing address add/update and checkout merge flows continue to pass.

Verification Results:
- `php artisan test tests\Feature\StorefrontCheckoutGateTest.php` passed: 25 tests, 137 assertions.
- `php artisan test tests\Feature\StorefrontCustomerAuthPagesTest.php` passed: 4 tests, 60 assertions.
- `php artisan test tests\Feature\StorefrontCartPageTest.php` passed: 18 tests, 103 assertions.
- `php artisan test tests\Feature\StorefrontAddToCartTest.php` passed: 18 tests, 81 assertions.

Deferred Items:
- Billing address.
- Full shipping engine and delivery pricing.
- Payment and order creation.
- Final restriction enforcement during order placement.

---

Feature Name:
Checkout Country Handling - India-First Default Country Resolver

Objective:
Make checkout delivery addresses India-first without hardcoding India IDs, while keeping the address schema and checkout flow multi-country-ready.

Scope:
Backend default-country resolution, hidden checkout Country UI, server-side `country_id` assignment, India-only PIN validation, and PIN lookup based on backend storefront country instead of browser-submitted country data.

Files Created:
- `app/Services/Storefront/StorefrontCountryResolver.php`

Files Modified:
- `app/Http/Controllers/Storefront/CheckoutAddressController.php`
- `app/Services/Checkout/CheckoutPageService.php`
- `app/Services/Checkout/CheckoutPostalCodeLookupService.php`
- `resources/views/storefront/pages/checkout.blade.php`
- `resources/views/storefront/pages/partials/checkout-address-form.blade.php`
- `tests/Feature/StorefrontCheckoutGateTest.php`
- `docs/Prompt_Outcome_Log.md`

Default Country Resolution:
- `StorefrontCountryResolver` reads `system_settings.default_country_code`.
- If unavailable, it falls back to `config('location.default_country_code')`.
- The resolved code is normalized to uppercase ISO2 and mapped to an active `loc_countries.iso2` record.
- Invalid or missing active country configuration fails safely with a logged configuration error.

Checkout Address Behavior:
- The visible Country selector was removed from the current delivery address form.
- No hidden `country_id` is trusted from the browser.
- `CheckoutAddressController` resolves the storefront default country and assigns `merchant_customer_addresses.country_id` server-side.
- Tampered request `country_id` values cannot override the backend default country.

India PIN Behavior:
- India-specific validation only runs when the resolved default country has `iso2 = IN`.
- For India, PIN remains required, six digits, active in `postal_codes`, and eligible for shipping/restriction lookup.
- PIN lookup endpoint now uses backend default-country resolution and does not require browser country input.
- State lookup remains scoped by `country_id`; city lookup remains scoped by `state_id + country_id`.
- Valid PINs are not rejected only because the city master has no exact district match; `city_id` remains nullable in that case.

Future Compatibility:
- Customer addresses still use generic `country_id`, `state_id`, `city_id`, and `postal_code`.
- No India-specific columns or postal-code foreign keys were added.
- Future multi-country checkout can re-enable a Country selector and validate an accepted country selection without redesigning customer address storage.

Verification Results:
- `php artisan test tests\Feature\StorefrontCheckoutGateTest.php` passed: 28 tests, 147 assertions.
- `php artisan test tests\Feature\StorefrontCustomerAuthPagesTest.php` passed: 4 tests, 60 assertions.
- `php artisan test tests\Feature\StorefrontCartPageTest.php` passed: 18 tests, 103 assertions.
- `php artisan test tests\Feature\StorefrontAddToCartTest.php` passed: 18 tests, 81 assertions.

Deferred Items:
- Billing Address.
- Shipping engine and delivery pricing.
- Payment.
- Order creation.
- Merchant/shop delivery-country configuration.

---

Feature Name:
Checkout Billing Address Section

Objective:
Add Billing Address to the one-page checkout between Delivery Address and Delivery Options, while reusing existing customer address storage and checkout address validation.

Scope:
Billing same-as-delivery state, separate billing address selection, adding billing addresses through the shared address form, default billing preselection, and place-order readiness validation without creating orders.

Files Modified:
- `app/Http/Controllers/Storefront/CheckoutAddressController.php`
- `app/Http/Controllers/Storefront/CheckoutController.php`
- `app/Services/Checkout/CheckoutPageService.php`
- `routes/web.php`
- `resources/views/storefront/pages/checkout.blade.php`
- `resources/views/storefront/pages/partials/checkout-address-form.blade.php`
- `tests/Feature/StorefrontCheckoutGateTest.php`
- `docs/Prompt_Outcome_Log.md`

Routes Added:
- `POST /checkout/billing/same` named `storefront.checkout.billing.same`
- `POST /checkout/billing-address/select` named `storefront.checkout.billing-addresses.select`
- `POST /checkout/billing-addresses` named `storefront.checkout.billing-addresses.store`

Checkout State:
- Delivery address continues to use `storefront.checkout.selected_address_id`.
- Billing same-as-delivery uses `storefront.checkout.billing_same_as_delivery`.
- Separate selected billing address uses `storefront.checkout.selected_billing_address_id`.

Behavior:
- Billing Address section appears after Delivery Address.
- `Same as delivery address` is checked by default.
- When checked, billing resolves internally to the selected delivery address.
- When unchecked, saved customer-owned addresses are shown as selectable billing addresses.
- Existing `is_default_billing` is used to preselect billing address when available.
- Adding a billing address reuses the existing address validation/save path, storefront default country resolver, India PIN validation, postal-code lookup, and `loc_*` resolution.
- Billing address IDs are ownership-checked and cannot be selected from another customer.
- Delivery and billing addresses can be different.
- Place-order placeholder now validates billing state before continuing, but still does not create an order.

Verification Results:
- `php artisan test tests\Feature\StorefrontCheckoutGateTest.php` passed: 35 tests, 181 assertions.
- `php artisan test tests\Feature\StorefrontCustomerAuthPagesTest.php` passed: 4 tests, 60 assertions.
- `php artisan test tests\Feature\StorefrontCartPageTest.php` passed: 18 tests, 103 assertions.
- `php artisan test tests\Feature\StorefrontAddToCartTest.php` passed: 18 tests, 81 assertions.

Deferred Items:
- Shipping engine and delivery pricing.
- Payment gateway.
- Order creation.
- Fast Checkout expansion.

---

Feature Name:
Checkout Address AJAX Saves

Objective:
Prevent full-page reloads when adding or editing checkout addresses on the one-page checkout.

Scope:
AJAX form submission for Add Delivery Address, Edit Delivery Address, and Add Billing Address, while preserving normal non-JavaScript form fallback.

Files Modified:
- `app/Http/Controllers/Storefront/CheckoutAddressController.php`
- `resources/views/storefront/pages/checkout.blade.php`
- `resources/views/storefront/pages/partials/checkout-address-form.blade.php`
- `tests/Feature/StorefrontCheckoutGateTest.php`
- `docs/Prompt_Outcome_Log.md`

Behavior:
- Checkout address forms now submit with `fetch()` when JavaScript is available.
- Successful AJAX saves return JSON and refresh the checkout content area in the background.
- Validation failures return JSON `422` and render inline field errors without a full page reload.
- PIN lookup/autofill still works after the checkout content is refreshed.
- Normal redirect-based form submission remains available as fallback.

Verification Results:
- `php artisan test tests\Feature\StorefrontCheckoutGateTest.php` passed: 38 tests, 193 assertions.
- `php artisan test tests\Feature\StorefrontCustomerAuthPagesTest.php` passed: 4 tests, 60 assertions.
- `php artisan test tests\Feature\StorefrontCartPageTest.php` passed: 18 tests, 103 assertions.
- `php artisan test tests\Feature\StorefrontAddToCartTest.php` passed: 18 tests, 81 assertions.


## 2026-09-28 - Configurable Storefront Footer Logo (marketplace.footer_logo)

### Task
Add a separate configurable Footer Logo: new global system setting marketplace.footer_logo under the Marketplace group, optional, falling back to marketplace.logo.

### Decisions
- Canonical source is system_settings (Marketplace group), NOT legacy admin_settings. Follows the frozen rule that new global settings must use system_settings.
- Reused MarketplaceLogoService (same MANAGED_DIRECTORY marketplace/logo, same isManagedPath/deleteManaged guards, same png/jpg/jpeg/webp 2MB validation) instead of a second upload architecture. New FOOTER_SETTING_KEY constant plus footerPath()/footerUrl(); footerUrl() falls back to url() (header logo chain incl. default).
- Footer Logo is optional: value null means fallback. Removing it clears the value (unlike header logo which resets to the default path) and deletes the old managed file via the same safe handling.
- Seeded via new Database\Seeders\MasterData\MarketplaceFooterLogoSeeder (insert-if-missing with null value; metadata-only update otherwise so configured values are never overwritten), called from SystemFoundationSeeder.
- Header behaviour unchanged: header/mobile header still use marketplaceLogoUrl; footer uses storefrontFooterLogoUrl shared through the existing footer/mobile-menu view composer (cached once per request).
- Footer col-left now always renders the logo; the OUR STORE contact block stays conditional on contact data.

### Files Changed
- app/Services/Marketplace/MarketplaceLogoService.php
- app/Http/Controllers/Admin/AdminSettingsController.php
- app/Providers/AppServiceProvider.php
- database/seeders/MasterData/MarketplaceFooterLogoSeeder.php (new)
- database/seeders/MasterData/SystemFoundationSeeder.php
- resources/views/admin/settings/edit.blade.php (Footer Logo card in Marketplace tab, shared logo-preview JS)
- resources/views/storefront/partials/footer.blade.php
- tests/Feature/StorefrontFooterLogoTest.php (new)
- docs/Prompt_Outcome_Log.md

### Tests
- php artisan test tests/Feature/StorefrontFooterLogoTest.php passed: 11 tests, 42 assertions (configured logo, fallback to marketplace.logo, fallback to default, header unaffected, admin page shows section, listed under Marketplace system settings, upload/replace/remove with managed-file assertions, svg/oversize validation, seeder preserves value).
- Neighbour suites passed: StorefrontMarketplaceLogoTest, AdminMarketplaceLogoSettingsTest, AdminSystemSettingManagementTest, StorefrontContactPageTest (22 tests, 116 assertions).
- git diff --check clean.
- Playwright browser verification NOT performed (browser instance locked by another session); needs manual check of Admin Settings Marketplace tab and storefront footer.
## 2026-09-29 16:47 +05:30 - Merchant Shop Shared Postal Resolution and Audience Assignment

Goal: Make Merchant Shop create/edit reuse the existing customer/checkout postal-code source of truth and expose the existing Admin-supported shop audiences without creating parallel implementations.

Decisions and outcome:

- Merchant Shop India PIN handling reuses `CheckoutPostalCodeLookupService` and the existing `storefront.checkout.postal-code.show` endpoint. The shared lookup response now also exposes resolved `loc_*` IDs and `postal_codes` latitude/longitude when available.
- Merchant server-side requests call the same lookup service, validate India PINs against active `postal_codes`, and normalize country/state/city/coordinates from that result. Non-India submissions retain the existing manual international location behavior.
- Audience values remain owned by `shop_audiences`; assignment continues through the existing `Shop::audiences()` many-to-many relationship and unique `shop_audience_map` pivot.
- Merchant forms load active audiences (plus already-selected inactive values on edit), use the same validation policy as Admin, and sync selections inside the existing shop create/update transactions.
- Storefront audience filtering already consumes the same relationship; no storefront changes or migration were required.

Key files/services/tables: `CheckoutPostalCodeLookupService`, Merchant shop form requests/service/views, `postal_codes`, `loc_countries`, `loc_states`, `loc_cities`, `shop_audiences`, `shop_audience_map`.

Verification: focused Merchant feature coverage verifies multiple-audience create, edit display and add/remove sync, invalid audience rejection, existing ownership enforcement, and PIN-derived India location/coordinates. Admin behavior was not changed.

## 2026-09-29 17:10 +05:30 - Merchant Shop Suggested Description UX

Goal: Give merchants optional predefined writing assistance for Shop Short Description and Description without changing persisted shop data automatically.

Decisions and outcome:

- Suggestions are centralized in `config/shop_description_guidance.php` and keyed by the stable slug of an active root `ProductCategory`.
- `MerchantShopService::formData()` exposes only configured suggestions that match currently active root Shop Types, preventing child-category or inactive-category use.
- Merchant Create/Edit share one compact Blade component. Selecting a Shop Type only shows or changes the available guidance; it never changes either description field.
- Clicking `Use suggested description` fills the existing fields. If either field already contains text, explicit Bootbox confirmation is required before both are replaced.
- Audience does not alter suggestions in V1. Submitted descriptions continue through the existing create/update flow without new columns, template IDs, migrations, AI, or external APIs.

Verification: `MerchantAuthTest` passed with 32 tests and 144 assertions; Blade compilation and `git diff --check` passed. Browser verification was unavailable because this session exposed no browser surface.

## 2026-09-29 18:21 +05:30 - Shared Admin and Merchant Shop Description Guidance

Goal: Make the existing optional Shop Type description suggestions available on Admin Shop Create/Edit without introducing a second implementation.

Decisions and outcome:

- `ShopDescriptionGuidanceService` is now the shared resolver for configured suggestions and canonical root-category slug keys, including database slugs with an ID suffix such as `apparel-1`.
- Admin and Merchant forms use the same `config/shop_description_guidance.php` source and the same Blade/JavaScript guidance component.
- Only active root Shop Types expose configured suggestions. Selecting a type does not modify descriptions; applying a suggestion remains an explicit action and existing text still requires confirmation before replacement.
- Shop Type and description persistence were not changed. No schema change or migration was required.

Key files/services: `ShopDescriptionGuidanceService`, `MerchantShopService`, `Admin\MerchantShopController`, and the shared description-guidance Blade component.

Verification: Admin rendered-DOM contract test passed with 6 assertions; `MerchantAuthTest` passed with 33 tests and 159 assertions; PHP lint, Blade compilation, and `git diff --check` passed. Live Admin interaction could not be exercised because the available browser session had Merchant access and correctly received HTTP 403 from Admin routes.

## 2026-10-03 - WS-010 Email Queue / Cron Delivery

Goal: Remove SMTP delivery from transactional business HTTP requests while preserving the existing notification catalogue, preferences, channels, delivery logs, and transaction boundaries.

Decisions and outcome:

- Business-event email messages are accepted through a dedicated `NotificationManager::queueEmail()` boundary. SMS and WhatsApp retain synchronous handling, and direct `NotificationManager::send()` remains available.
- Each accepted email atomically claims its existing unique delivery identity as `queued` before one `DeliverNotificationEmail` job is dispatched to the database-backed `emails` queue. The job explicitly implements after-commit queue safety and carries only `NotificationMessage` data plus the delivery-log ID, never SMTP credentials.
- The worker transitions the same delivery-log row through `queued -> processing -> sent|skipped|not_configured|failed`. A failed `DeliveryResult` is logged and then thrown so Laravel retries it; a retry atomically reacquires the same failed row instead of creating a duplicate.
- The unique `delivery_key` remains the application-level duplicate business-event guard. SMTP acknowledgement ambiguity can still rarely cause a duplicate email after retry and is documented as an operational limitation.
- Admin Test Email remains synchronous for immediate administrator feedback. No SMTP settings, channel registry, notification-message, SMS/WhatsApp, Redis, schema, or migration changes were introduced.
- The existing once-per-minute Laravel scheduler now drains only the `emails` queue with a bounded, non-overlapping `queue:work --stop-when-empty` process, supporting shared hosting without requiring Supervisor.

Key files/services/tables: `DeliverNotificationEmail`, `NotificationManager`, `NotificationDeliveryLogger`, `DispatchBusinessNotifications`, `routes/console.php`, existing `jobs`/`failed_jobs`/`notification_delivery_logs` tables, and the deployment guide.

Roadmap status: implementation is ready for Testing; WS-010 was not marked Completed automatically.

Verification: focused notification queue, event wiring, and real email suites passed with 41 tests and 194 assertions; the broader notification-related run passed 86 of 87 tests with one pre-existing legacy-template mapping failure for `merchant.registered.admin:email`; focused storefront checkout/order placement passed with 3 tests and 71 assertions. Scheduler registration, Pint, PHP lint, and `git diff --check` passed. No UI changes were made.

## 2026-10-04 - Fulfilment-Driven Checkout Simplification

Goal: Make the single-shop storefront checkout derive its flow entirely from each selected shop's existing pickup/delivery settings, including addressless pickup and a safe unavailable state, without adding settings, schema, payment rules, or notification changes.

Decisions and outcome:

- `fulfillment.pickup_enabled` and `fulfillment.delivery_enabled` are the sole checkout-mode controls. There is one checkout engine and no Local/Normal Checkout setting.
- Pickup-only checkout omits delivery/billing address and delivery-option sections, shows shop pickup details, and creates orders with null shipping and billing snapshots and zero shipping.
- Delivery-only retains the existing address, billing, quote, serviceability, charge, and payment behavior. When both modes are enabled, the customer chooses between them and the existing AJAX refresh updates payment methods and totals.
- Neither-enabled checkout shows an unavailable message and cannot place an order. Server validation rejects disabled or unknown fulfilment values independently of the UI; invalid values are no longer coerced to delivery.
- Payment rules, delivery quote/serviceability rules, V1 selected-shop cart scoping, order workflow, notification architecture, and existing pickup presentation fallbacks were unchanged.

Key files/services: `StorefrontDeliveryService`, `CheckoutPageService`, `StorefrontCheckoutOrderService`, `CheckoutController`, the storefront checkout Blade view, and focused `StorefrontCheckoutGateTest` coverage. Existing nullable order snapshot columns are reused; no migration was required.

Verification: focused fulfilment/delivery regression run passed 9 tests with 116 assertions. The full checkout gate suite passed 88 of 92 tests with the same four unrelated fixture/transaction/copy failures previously recorded in this log. Pint, PHP syntax checks, Blade compilation, and `git diff --check` passed. Live browser verification was unavailable because no browser surface was exposed.

## 2026-10-04 - Fulfilment Checkout UI/UX Refinement

Goal: Apply the approved checkout hierarchy and fulfilment-card visual direction while preserving the completed fulfilment-driven business logic and WindowShop branding.

Decisions and outcome:

- When both modes are enabled, the fulfilment decision is now the first checkout section and uses two responsive, full-label radio cards with the existing WindowShop accent and icon system.
- Delivery context remains Delivery Address, Billing Address, Delivery Options, then Payment Method. Pickup context now uses a dedicated non-interactive Pickup Information panel containing the actual shop name, address, and configured instructions, followed by Payment Method.
- Pickup-only and delivery-only shops omit the unnecessary selector. The neither-enabled state and the desktop Order Summary sidebar remain unchanged.
- The visually competing Fast Checkout banner was removed; the existing Order Summary Place Order button remains the sole final action with its established disabled/spinner/double-submit behavior.
- AJAX switching continues to use the existing endpoint and now also toggles the contextual panels and refreshes the dedicated delivery-option presentation. Backend fulfilment, address, payment, delivery calculation, order workflow, and notification rules were not changed.

Key files/services: storefront checkout Blade view, `StorefrontDeliveryService` pickup presentation data, and `StorefrontCheckoutGateTest` presentation coverage. No schema, migration, setting, or new image dependency was introduced.

Verification: focused UI/placement runs passed (10 tests, 143 assertions; final local-only/pickup/delivery regression run 5 tests, 102 assertions). The full checkout suite initially passed 87 of 92 tests; its one UI-copy failure was corrected and rerun successfully, leaving only the same four unrelated registration fixture, notification transaction, and stale unavailable-copy failures already recorded for this suite. Pint, PHP syntax checks, Blade compilation, inline checkout JavaScript syntax, and `git diff --check` passed. Live browser verification was unavailable because no browser surface was exposed.

## 2026-10-03 - Shop-Specific Notification Email

Supersedes: the shop-recipient fallback recommendation from the preceding Shop-Specific Primary Operational Email investigation.

### Decision
- Shop-level merchant email notifications use the optional shop-scoped `shop_settings` value `notifications / email.shop_notification_email`.
- A blank, missing, or invalid Shop Notification Email disables shop-level merchant email delivery. There is no fallback to `merchant_profiles.contact_email`, the merchant owner's `users.email`, or the public `shops.email` field.
- `shops.email` remains customer-facing Public Contact Email. Merchant lifecycle emails remain account-scoped and retain their existing MerchantProfile/User recipient rules.
- Shop-scoped Additional To, CC, and BCC remain supplementary recipients. They cannot independently activate delivery without a valid Shop Notification Email, and their stored values are preserved while delivery is disabled.
- V1 affects the currently wired `order.new.merchant` event only. Customer and Admin routing are unchanged. `payment.upi_submitted.merchant` remains a separate catalogued-but-unwired notification gap.
- This task is email-only. SMS and WhatsApp recipient/provider work remains deferred to WS-012.
- Recipient resolution still happens before WS-010 queueing, so the resolved destination remains fixed in the queued `NotificationMessage`; no queue architecture changes or database migrations are required.

### Implementation Outcome
- Added the Shop Notification Email field to the existing shop-scoped Merchant Notification Settings page, with blank-means-disabled guidance and supplementary fields that become read-only until the primary is valid.
- Extended `MerchantOperationalEmailRecipientResolver` to store, validate defensively, and resolve the new setting while preserving existing supplementary-recipient normalization and deduplication.
- Updated new-order merchant dispatch to omit the email message entirely when the shop has no valid Shop Notification Email, without changing SMS, WhatsApp, customer, Admin, or merchant-lifecycle delivery.

### Verification
- Merchant notification settings, business-event wiring, WS-010 foundation, and real email delivery suites passed: 52 tests, 357 assertions.
- The broader checkout gate suite passed 85 of 89 tests; its four failures were unrelated existing fixture/transaction/copy failures, while the delivery and pickup order-placement cases passed.
- Pint, PHP syntax checks, Blade compilation, inline JavaScript syntax, and `git diff --check` passed. Live browser verification was unavailable because no browser surface was exposed.
## 2026-10-04 — Direct Merchant UPI dynamic QR V1

- Replaced checkout's static merchant QR availability dependency with server-generated UPI QR data using `endroid/qr-code`.
- UPI URI uses RFC3986 `pa`, `pn`, `tr`, `am` (authoritative checkout total, two decimals), and `cu=INR`; the customer-entered bank reference remains separate.
- Merchant configuration now requires only enabled + UPI ID + payee name; historical QR settings/files are retained.
- Fulfilment switching continues to obtain payment data from the server refresh endpoint, and unverified Direct Merchant UPI orders are blocked centrally from workflow advancement until payment is paid.
- No database migration, gateway, settlement, or checkout redesign was introduced.
## 2026-10-04 — WS-055 Cookie information notice

- Added a storefront-wide, non-blocking cookie information notice with a versioned first-party dismissal cookie (`windowshop_cookie_notice_v1`, 12 months, `/`, SameSite=Lax).
- Added footer Cookie Settings reopening and a static/CMS-compatible Cookie Policy route (`/cookie-policy`, CMS key `cookie`).
- V1 remains informational only: no consent categories, tracker gating, database persistence, migrations, or map changes.
## 2026-10-04 — WS-018 gateway-neutral payment foundation

- Added merchant-owned `PaymentAccount` records, shop mappings, and generic `PaymentAttempt` records.
- Gateway secrets are encrypted with Laravel `Crypt`, excluded from serialization, and decryption fails closed.
- Resolver enforces shop mapping, merchant ownership, provider, mode, enabled state, and public-key presence.
- Payment attempts use integer minor units and domain validation; no gateway SDK/API, checkout enablement, callbacks, webhooks, refunds, settlement, or migrations outside the three foundation tables were added.
## 2026-10-04 — WS-018 Phase 1 foundation test coverage

- Added focused unit coverage for encrypted PaymentAccount serialization/accessors, corrupted-secret fail-closed behavior, mass-assignment protection, mode separation, integer minor-unit amounts, and attempt status vocabulary.
- Corrected PaymentAttemptService to reject an explicitly supplied provider that differs from the mapped PaymentAccount provider.
## 2026-10-04 — WS-018 Phase 2 merchant Razorpay account settings

- Added merchant-scoped Razorpay PaymentAccount configuration to existing Merchant Settings.
- Credentials remain in encrypted PaymentAccount fields, never shop settings; blank secret preserves the existing value.
- Account mode, enabled state, public key, account name, and same-merchant shop assignments are managed server-side.
- Customer Online Payment remains disabled until the future Razorpay checkout phase; no SDK/API/callback/webhook/refund work was added.
