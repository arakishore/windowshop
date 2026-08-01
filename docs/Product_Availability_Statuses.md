# Product Availability Statuses

## Status

Implemented.

## Purpose

Product availability controls the customer-facing availability label, optional customer help text, badge style, and whether customer channels may purchase a product when stock quantity is zero.

This feature is separate from inventory.

- Inventory determines quantity.
- Availability determines customer messaging and zero-stock purchase permission.

POS stock behaviour is separate and unchanged.

## Merchant Scope

Availability statuses are merchant-specific records in `product_availability_statuses`.

The status `code` is unique only within a merchant:

```text
merchant_id + code
```

Merchants cannot view, edit, delete, restore, or assign another merchant's availability statuses.

## Default Statuses

Every merchant receives these active defaults:

| Code | Name | Customer Description | Purchase Allowed | Badge Type | Sort |
|---|---|---|---:|---|---:|
| `IN_STOCK` | In Stock | This item is available and ready to order. | 1 | success | 10 |
| `OUT_OF_STOCK` | Out of Stock | This item is currently out of stock. | 0 | danger | 20 |
| `PREORDER` | Pre-Order | Order now and we will fulfil it when it becomes available. | 1 | warning | 30 |
| `BACKORDER` | Backorder | This item is not in stock right now, but you can order it for later fulfilment. | 1 | warning | 40 |
| `COMING_SOON` | Coming Soon | This item is coming soon and is not available to order yet. | 0 | secondary | 50 |
| `DISCONTINUED` | Discontinued | This item is no longer available for purchase. | 0 | secondary | 60 |

Defaults are created by `App\Services\ProductAvailability\MerchantAvailabilityStatusSeeder`.

The seeder is idempotent. It creates missing defaults only and does not overwrite merchant customisations.

`name` is the customer-visible label shown on website/mobile surfaces. `customer_description` is optional customer-facing help text for future website/mobile tooltips or product-detail messaging.

## Product And Variant Assignment

Products have:

```text
products.availability_status_id
```

Variants have:

```text
product_variants.availability_status_id
```

Product creation defaults to the merchant's active `IN_STOCK` status, resolved by `merchant_id + code`.

Variant behaviour:

```text
variant availability_status_id set
    use variant status
otherwise
    inherit product status
```

Inactive or soft-deleted statuses cannot be newly assigned.

## Resolver Logic

Use `App\Services\ProductAvailability\ProductAvailabilityResolver` for customer-facing availability.

Returned data includes:

```text
availability_status_id
availability_code
availability_label
purchase_allowed
badge_type
stock_quantity
is_in_stock
can_purchase
availability
```

Purchase rule:

```text
stock_quantity > 0
    can_purchase = true

stock_quantity <= 0
    can_purchase = effective_status.purchase_allowed
```

Examples:

| Stock | Status | Purchase Allowed | Can Purchase |
|---:|---|---:|---:|
| 10 | OUT_OF_STOCK | 0 | 1 |
| 0 | OUT_OF_STOCK | 0 | 0 |
| 0 | PREORDER | 1 | 1 |
| 0 | BACKORDER | 1 | 1 |
| 0 | COMING_SOON | 0 | 0 |

If an already assigned status is later inactive or deleted, the resolver does not crash customer-facing code.

## Customer Channel Guard

Use `App\Services\ProductAvailability\CustomerPurchaseAvailabilityGuard` for website/mobile cart and checkout validation.

It should be called:

- when adding to cart
- again before checkout/order creation

Blocked message:

```text
This product is currently unavailable for purchase.
```

## Merchant UI

Merchant menu:

```text
Catalog -> Availability Statuses
```

Supported actions:

- List
- Search
- Filter by active/inactive/trash
- Create
- Edit
- Soft delete
- Restore

Deletion is blocked when a status is assigned to any product or variant.

Product forms include a `Customer Availability` section.

Variant grid supports:

```text
Use Product Default
```

or a merchant-specific status override.

## Badge Types

Allowed badge types:

```text
success
danger
warning
secondary
```

The database stores semantic badge types only. It does not store hex colors or CSS class names.

## Storefront/API Contract

Future website/mobile product payloads should expose:

```json
{
  "stock_quantity": 0,
  "is_in_stock": false,
  "availability": {
    "code": "BACKORDER",
    "label": "Backorder",
    "description": "This item is not in stock right now, but you can order it for later fulfilment.",
    "badge_type": "warning",
    "purchase_allowed": true
  },
  "can_purchase": true
}
```

Frontend code must use `can_purchase` from the backend. It must not infer purchase eligibility from label, badge, or code.

## POS Rule

POS continues to use existing stock rules.

Do not allow zero-stock POS sales only because an availability status has:

```text
purchase_allowed = true
```

POS negative-stock or pre-order behaviour requires a separate future merchant setting.

## Tests

Focused coverage is in:

```text
tests/Feature/ProductAvailabilityStatusTest.php
```

Covered behaviour includes:

- default creation
- idempotency
- customisation preservation
- product default status
- variant inheritance and override
- merchant isolation
- zero-stock purchase rules
- storefront-style resolver payload
- customer guard validation
- deletion and restore protection
- POS regression via related POS tests
