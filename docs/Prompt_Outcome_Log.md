# Prompt Outcome Log

## Purpose

This document records important user prompts and the final implementation outcome.

Use it as a running project memory so we can quickly see:

- what the previous prompt/request was
- what was implemented
- what was intentionally not implemented
- what tests or checks were run
- where the related detailed docs live

Add new entries at the top, newest first.

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
