# Payment Settings

## Purpose

This document defines how WindowShop separates POS payment settings from storefront payment settings, where each setting is stored, and how checkout should interpret storefront payment capability.

## Scope

This document covers payment method configuration only. It does not define payment execution, gateway integration, UPI verification, settlement, refunds, split payments, or order-payment reconciliation.

## Storage Ownership

### POS Payment Settings

POS tender settings are merchant-level settings stored in `merchant_settings`.

Current POS methods:

- `payment.allow_cash`
- `payment.allow_card`
- `payment.allow_upi`
- `payment.allow_credit`
- `payment.default_payment_method`

These settings apply to merchant POS checkout only.

They must not be reused as storefront checkout settings unless a later architecture decision explicitly changes that.

### Storefront Payment Settings

Storefront payment settings are shop-level settings stored in `shop_settings`.

They are resolved by `shop_id`, not only by `merchant_id`, because different shops under the same merchant may support different storefront payment methods.

Current storefront payment keys:

- `payment.cod_enabled`
- `payment.cod_min_order_amount`
- `payment.cod_max_order_amount`
- `payment.cash_at_shop_enabled`
- `payment.merchant_upi_enabled`
- `payment.merchant_upi_id`
- `payment.merchant_upi_payee_name`
- `payment.merchant_upi_qr_path`
- `payment.online_payment_enabled`

Merchant settings UI must update storefront payment settings only for the resolved Active Shop owned by the authenticated merchant. The form must not accept or trust an arbitrary submitted `shop_id`.

## Storefront Payment Methods

### Cash on Delivery

Cash on Delivery can be offered only when:

- `payment.cod_enabled = true`
- selected fulfillment is `delivery`
- the order amount satisfies the configured COD min/max rule

COD amount limits:

- Minimum Order Amount blank or `0` means no minimum.
- Maximum Order Amount blank or `0` means no maximum.

Examples:

| Minimum | Maximum | Meaning |
| --- | --- | --- |
| blank or `0` | blank or `0` | COD available for any order amount |
| `500` | blank or `0` | COD available for orders `500+` |
| blank or `0` | `5000` | COD available up to `5000` |
| `500` | `5000` | COD available from `500` through `5000` |

Validation must compare minimum to maximum only when both values are greater than `0`.

### Cash at Shop

Cash at Shop can be offered only when:

- `payment.cash_at_shop_enabled = true`
- selected fulfillment is `pickup`

If pickup is disabled, the setting may remain saved, but checkout must not offer Cash at Shop.

### Direct Merchant UPI

Direct Merchant UPI can be offered only when:

- `payment.merchant_upi_enabled = true`
- `payment.merchant_upi_id` exists
- `payment.merchant_upi_payee_name` exists
- `payment.merchant_upi_qr_path` exists

The QR code setting stores only a file path/reference. Do not store binary image data or base64 data in `shop_settings`.

Direct Merchant UPI payment execution, timer, transaction ID submission, and merchant verification are separate future work.

### Online Payment

Online Payment remains disabled until a real gateway flow exists.

Do not allow merchants to enable Online Payment before checkout can process it.

Do not store gateway credentials, API keys, or secrets in `shop_settings`.

## Fulfillment Relationship

Payment methods depend on selected fulfillment:

| Fulfillment | Allowed payment families |
| --- | --- |
| `delivery` | COD, Direct Merchant UPI, Online Payment |
| `pickup` | Cash at Shop, Direct Merchant UPI, Online Payment |
| `counter` | POS flow only |

## Multi-Shop V1 Rules

For storefront checkout V1:

- COD is available only if all involved shops support COD and each applicable amount rule passes.
- Direct Merchant UPI is single-shop only.
- Cash at Shop is single-shop pickup only.
- Do not build split-payment or multi-shop pickup flows until explicitly planned.

## Checkout Integration Rule

Checkout and order creation must read storefront payment settings server-side when payment availability is implemented.

Client-side UI may hide or show methods for convenience, but server-side validation remains authoritative.

## Out Of Scope For Current Foundation

- Payment gateway integration
- UPI payment execution
- UPI transaction verification
- Online payment credentials
- COD enforcement at order placement unless explicitly implemented
- Cash at Shop enforcement at order placement unless explicitly implemented
- Payment settlement
- Refund payment routing
- Split payment
- Multi-shop Direct Merchant UPI
