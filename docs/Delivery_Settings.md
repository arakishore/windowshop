# Delivery Settings

## Purpose

This document defines the V1 shop-level delivery configuration used by storefront checkout. It intentionally keeps delivery pricing simple while leaving room for future shipping engines.

For the separation between order status and future delivery/logistics status, see `docs/Order_Flow.md`.

## Storage Ownership

Delivery settings are shop-level settings stored in `shop_settings` under the `fulfillment` group.

Do not create a separate delivery/shipping settings table for V1.

Current delivery and pickup keys:

- `fulfillment.delivery_enabled`
- `fulfillment.delivery_min_order_amount`
- `fulfillment.delivery_flat_charge`
- `fulfillment.free_delivery_above`
- `fulfillment.delivery_estimate_min_days`
- `fulfillment.delivery_estimate_max_days`
- `fulfillment.pickup_enabled`
- `fulfillment.pickup_instructions`

## Defaults

| Setting | Default | Meaning |
| --- | --- | --- |
| `delivery_enabled` | `true` | Delivery is available unless the shop turns it off. |
| `delivery_min_order_amount` | `null` | No minimum delivery order. |
| `delivery_flat_charge` | `0` | Delivery is free unless a merchant sets a charge. |
| `free_delivery_above` | `null` | No free-delivery threshold. |
| `delivery_estimate_min_days` | `null` | No minimum estimate shown. |
| `delivery_estimate_max_days` | `null` | No maximum estimate shown. |
| `pickup_enabled` | `true` | Pickup is available unless the shop turns it off. |
| `pickup_instructions` | `null` | No pickup instructions. |

## Amount Semantics

### Minimum Delivery Order

`delivery_min_order_amount` controls the minimum eligible shop subtotal for delivery.

- Blank or `NULL` means no minimum.
- `0` is normalized to `NULL` and also means no minimum.
- Positive values require the shop subtotal to be at least that amount.

### Flat Delivery Charge

`delivery_flat_charge` is the normal V1 delivery charge.

- `0` means free delivery.
- Positive values are used when no free-delivery threshold applies.

### Free Delivery Above

`free_delivery_above` controls when the delivery charge becomes `0`.

- Blank or `NULL` means no free-delivery threshold.
- `0` is normalized to `NULL` and also disables the threshold.
- Positive values make delivery free when the shop subtotal is greater than or equal to that amount.

Example:

| Shop subtotal | Flat charge | Free above | Result |
| --- | --- | --- | --- |
| `700` | `50` | `1000` | Shipping `50` |
| `1200` | `50` | `1000` | Shipping `0` |

## Estimated Delivery

Delivery estimates are stored as numbers, not strings:

- `delivery_estimate_min_days`
- `delivery_estimate_max_days`

Examples:

- Min `2`, Max `4`: estimated delivery `2-4 days`
- Min `2`, Max `2`: estimated delivery `2 days`

Both values are optional. Validation requires non-negative integers and, when both are present, maximum days must be greater than or equal to minimum days.

Do not calculate calendar delivery dates in V1.

## V1 Quote Rule Order

Delivery availability and charge should be calculated per shop using that shop's subtotal:

1. If delivery is disabled, delivery is unavailable.
2. If the destination postal code is restricted, delivery is unavailable.
3. If the minimum delivery order is configured and the shop subtotal is below it, delivery is unavailable.
4. If free-delivery threshold is configured and the shop subtotal is greater than or equal to it, shipping is `0`.
5. Otherwise, shipping is the flat delivery charge.

The centralized V1 calculation entry point is:

```php
App\Services\Checkout\ShopDeliveryQuoteService
```

It returns:

- `available`
- `charge`
- `reason`
- `estimated_min_days`
- `estimated_max_days`

## Postal Code Restrictions

PIN restriction data remains in `postal_code_restrictions`.

Do not add `allowed_pincodes`, `blocked_pincodes`, or similar keys to `shop_settings`.

Delivery eligibility combines:

- shop delivery settings
- marketplace, merchant, or shop-level postal-code restrictions

## Multi-Shop Rule

Do not evaluate a multi-shop cart using the overall cart subtotal.

Each shop must be evaluated independently:

- Shop A subtotal uses Shop A delivery settings.
- Shop B subtotal uses Shop B delivery settings.

Example:

| Shop | Shop subtotal | Free above | Expected |
| --- | --- | --- | --- |
| A | `600` | `1000` | Flat delivery charge |
| B | `700` | `500` | Free delivery |

This remains true even if the overall cart subtotal is `1300`.

## Out Of Scope For V1

- Checkout delivery option redesign
- Split-shipment UI
- Courier APIs
- Weight-based shipping
- Distance/radius shipping
- Zone/table rates
- Per-product shipping
- Delivery date calculation
- Pickup charges
