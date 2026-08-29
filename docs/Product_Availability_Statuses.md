# Product Availability Statuses

## Status

Implemented.

## Purpose

Product availability controls the customer-facing availability label, optional customer help text, badge style, and whether customer channels may purchase a product.

This feature is separate from inventory.

- Inventory determines quantity.
- Availability determines customer messaging and customer purchase permission.
- The status code determines whether physical stock is a hard limit.

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
availability_status_active
purchase_allowed
badge_type
stock_quantity
is_in_stock
can_purchase
availability
```

Frozen V1 customer purchase rules:

```text
Product active?
    no -> block

Effective availability status active?
    no -> block

Status rule:
    IN_STOCK      -> purchase allowed only up to physical stock
    OUT_OF_STOCK  -> blocked regardless of stock
    BACKORDER     -> purchase allowed beyond physical stock when purchase_allowed = true
    PREORDER      -> purchase allowed without current stock when purchase_allowed = true
    COMING_SOON   -> blocked
    DISCONTINUED  -> blocked
```

Examples:

| Stock | Status | Purchase Allowed | Can Purchase |
|---:|---|---:|---:|
| 5 | IN_STOCK, qty 5 | 1 | 1 |
| 5 | IN_STOCK, qty 6 | 1 | 0 |
| 0 | IN_STOCK | 1 | 0 |
| 100 | OUT_OF_STOCK | 0 | 0 |
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

The guard returns a central decision payload with the effective status code, active flag, purchase flag, stock quantity, requested quantity, shortage quantity, stock-limit flag, stock limit, allowed flag, and customer-facing message.

Blocked message example:

```text
This product is currently unavailable for purchase.
```

Backorder message examples:

```text
5 items are currently in stock. 2 items require confirmation from the merchant.
This item is currently not in stock. Your order requires confirmation from the merchant.
```

Preorder message:

```text
This is a pre-order item. Availability and fulfilment will be confirmed by the merchant.
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

Website/mobile product payloads should expose:

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

Product detail and cart use the central customer purchase availability guard:

- `IN_STOCK` exposes physical stock as the quantity max.
- `BACKORDER` and `PREORDER` do not expose a physical-stock max.
- Cart can show non-blocking backorder/preorder confirmation messages.
- Negative stock is never shown directly to customers.

## Backorder Display Rule

For `BACKORDER` products, the stored availability status remains `BACKORDER`.

On the storefront Product Detail page, the customer-facing label depends on the selected quantity:

```text
selected quantity <= current physical stock
    display In Stock

selected quantity > current physical stock
    display Backorder
    show shortage confirmation message
```

Example:

```text
effective status BACKORDER
stock 2
selected quantity 1
customer display In Stock

selected quantity 3
customer display Backorder
message 2 items are currently in stock. 1 item requires confirmation from the merchant.
```

This is display-only. It does not modify the product, variant, availability status record, cart rule, checkout rule, or order creation rule.

## Negative Stock

Negative stock is allowed only when customer demand is validly placed under a status whose V1 behaviour permits selling beyond current stock:

```text
BACKORDER
PREORDER
```

Example:

```text
stock 5
customer orders 7
new operational stock -2
```

The negative value means 2 units are outstanding against customer demand. Merchant inventory/admin may display the operational negative quantity. Storefront customer views must show confirmation messaging instead of negative availability.

## Removed Merchant Setting

The old merchant setting below is obsolete and removed from defaults/UI:

```text
inventory.allow_negative_stock
```

Do not add another merchant-wide negative-stock toggle for V1. Availability status is the customer purchase authority.

## POS Rule

POS continues to use existing stock rules through `OrderCreationService` for non-storefront orders.

Do not allow zero-stock POS sales only because an availability status has:

```text
purchase_allowed = true
```

POS negative-stock or pre-order behaviour remains deferred.

## Merchant Stock-Shortage Alerts

For storefront orders placed under `BACKORDER` or `PREORDER`, the system records any original shortage in `order_status_histories` when the order is created.

The permanent history record uses the existing order history mechanism:

```text
notes
metadata.stock_shortages
```

Example history note:

```text
Order placed with insufficient stock.
Product: White Slim Classic Casual Shirt
Ordered Qty: 4
Available Stock: 2
Short Qty: 2
Availability Status: BACKORDER
```

Merchant order list/detail warnings are live warnings, not historical warnings. They are calculated from current variant stock and disappear after stock is replenished.

When showing physical stock in merchant/customer-facing UI, clamp negative inventory to zero for display only:

```text
display_available_stock = max(current_stock, 0)
```

Example:

```text
internal stock_quantity: -3
merchant display: Currently In Stock: 0
merchant display: Stock Shortage: 3
```

Do not clamp the stored `product_variants.stock_quantity`; negative stock remains the internal V1 signal for outstanding BACKORDER/PREORDER demand.

For a single open demand row, live shortage accounts for the order's own deducted quantity:

```text
stock before order: 2
order qty: 4
stock after order: -2
live shortage for that order: 2
```

Do not calculate live shortage as `order qty - current stock`; that would incorrectly show `6` in the example above.

When multiple open orders share the same variant, later restocks cannot be allocated to a specific order in V1 because there is no allocation/reservation table. In that case merchant UI shows a safe warning such as:

```text
Outstanding stock demand exists for this item.
```

V1 does not implement allocation, procurement, reservations, customer notifications, or quantity adjustment for shortage fulfilment.

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
- V1 status/stock rules
- storefront-style resolver payload
- customer guard validation
- deletion and restore protection
- POS regression via related POS tests
