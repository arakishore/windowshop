# POS Refund/Exchange Reuse Investigation

Investigation date: 2026-08-28

Status: investigation only. No implementation has been done for storefront refund or exchange from this document.

## Goal

WindowShop already has working POS refund and exchange flows. Storefront refund/exchange should reuse that foundation instead of creating a separate module.

This document records the current POS architecture, what can be reused, POS-specific assumptions, and the recommended path for supporting both POS and storefront orders.

## Current POS Refund Architecture

POS refunds are handled through the merchant sales module.

Routes:

- `GET /merchant/sales/{order}/refund`
- `POST /merchant/sales/{order}/refund`

Main files:

- `routes/merchant.php`
- `app/Http/Controllers/Merchant/SalesHistoryController.php`
- `app/Services/Order/OrderRefundService.php`
- `resources/views/merchant/sales/show.blade.php`
- `resources/views/merchant/sales/refund.blade.php`

The refund flow:

- loads a completed merchant sale
- shows refundable order items and quantities
- requires a merchant return reason
- supports restock/do-not-restock per line
- creates `order_refunds`
- creates `order_refund_items`
- restores inventory when restock is selected
- updates order payment status to `refunded` or `partially_refunded`
- writes `order_status_histories`

Refunds are completed immediately. There is no separate request/approval workflow in the current POS refund flow.

## Current POS Exchange Architecture

POS exchanges are also handled through the merchant sales module.

Routes:

- `GET /merchant/sales/{order}/exchange`
- `POST /merchant/sales/{order}/exchange`
- `GET /merchant/sales/exchanges/{exchange}/receipt`

Main files:

- `routes/merchant.php`
- `app/Http/Controllers/Merchant/SalesHistoryController.php`
- `app/Services/Order/OrderExchangeService.php`
- `resources/views/merchant/sales/exchange.blade.php`
- `resources/views/merchant/sales/exchange-receipt.blade.php`

The exchange flow:

- selects returned original order items and quantities
- optionally restores returned item stock
- selects replacement product variants and quantities
- creates a replacement order through `OrderCreationService`
- deducts replacement stock through the normal order creation path
- creates `order_exchanges`
- creates `order_exchange_return_items`
- records settlement difference as even, collect extra, refund balance, or credit adjustment
- writes `order_status_histories`

Replacement orders use:

- `created_source = exchange_replacement`
- operational paid status

Sales and collection reports exclude exchange replacement orders.

## Reusable Tables and Models

Existing reusable tables:

- `order_refunds`
- `order_refund_items`
- `order_exchanges`
- `order_exchange_return_items`
- `merchant_return_reasons`
- `order_status_histories`

Existing reusable models:

- `OrderRefund`
- `OrderRefundItem`
- `OrderExchange`
- `OrderExchangeReturnItem`
- `ReturnReason`

Existing reusable services:

- `OrderRefundService`
- `OrderExchangeService`
- `OrderCreationService`

## Item and Quantity Handling

The existing module is already item and quantity based.

Refund:

- validates selected quantity per `order_item_id`
- prevents refunding more than remaining refundable quantity
- stores each refunded line in `order_refund_items`

Exchange:

- validates returned quantity per `order_item_id`
- validates replacement variant quantities
- stores returned lines in `order_exchange_return_items`
- stores replacement lines through the replacement order

Important current behavior:

- `OrderExchangeService::exchangeableQuantities()` subtracts both refunded and previously exchanged quantities.
- `OrderRefundService::refundableQuantities()` subtracts prior refunds, but does not subtract previously exchanged quantities.

Before storefront reuse, decide whether exchanged quantity should also reduce refundable quantity. In most commerce flows, it should.

## Inventory Handling

Refund inventory behavior:

- per-line restock checkbox
- merchant return reason can default restock behavior
- restocked quantity increments `product_variants.stock_quantity`

Exchange inventory behavior:

- returned item stock is restored when selected
- replacement stock is deducted by `OrderCreationService` while creating the replacement order

Recommendation:

- keep the existing behavior
- move stock adjustments behind shared inventory methods if storefront reuse expands
- ensure refund and exchange use consistent locking/shop-scope protections

## Refund and Exchange Settlement

Refund settlement:

- stores refund method
- stores refund subtotal, tax, and total
- marks refund as completed
- updates order payment status

There is no external payment-gateway refund lifecycle yet.

Exchange settlement:

- compares returned value and replacement order value
- supports even exchange
- supports collect extra
- supports refund balance
- supports credit adjustment
- stores the actual settlement amounts on `order_exchanges`

For storefront online paid orders, automatic refund should not be enabled until payment/refund gateway handling exists.

## Status, History, and Activity

Refund and exchange currently create `order_status_histories`.

They do not transition the original order into return/exchange statuses. The original order usually remains completed, while refund/exchange details are tracked in their own tables.

Recommendation for storefront:

- reuse `order_status_histories`
- add customer-safe activity rendering for refund/exchange events
- avoid showing refund/exchange events as generic completed status events

## POS-Specific Assumptions

The merchant Sales History listing is intentionally POS-only:

- `created_source = pos`
- `order_status = completed`

However, the internal `SalesHistoryController` order authorization checks shop, merchant, and completed status, but does not explicitly check `created_source = pos`.

That means a completed storefront order from the same shop could potentially be processed through POS refund/exchange routes by direct URL. This should be treated as accidental, not a finished storefront capability.

Recommended cleanup:

- explicitly protect POS sales refund/exchange routes with `created_source = pos`
- create separate storefront/customer request entry points
- keep shared execution in `OrderRefundService` and `OrderExchangeService`

## Current Policy Support

No existing implementation was found for these policy fields:

- `refund_allowed`
- `refund_window_days`
- `exchange_allowed`
- `exchange_window_days`

No existing refund/exchange policy snapshot fields were found on `order_items`.

Delivery coverage such as `local_only` or `nationwide` should remain separate from refund/exchange eligibility.

## Frozen Policy to Add Later

Shop default:

- `refund_allowed = false`
- `refund_window_days = 0`
- `exchange_allowed = true`
- `exchange_window_days = 7`

Product:

- inherit shop policy by default
- allow optional product-specific override

Order item:

- snapshot the effective policy when purchased
- store `refund_allowed`
- store `refund_window_days`
- store `exchange_allowed`
- store `exchange_window_days`

Delivery:

- `shop.delivery_scope` can be `local_only` or `nationwide`
- delivery coverage is separate from refund/exchange policy

## Recommended Policy Integration

Add one shared eligibility layer, for example:

`OrderReturnExchangeEligibilityService`

Responsibilities:

- resolve effective shop/product policy before purchase
- snapshot effective policy to `order_items`
- calculate refundable/exchangeable quantities
- enforce windows from the chosen business date anchor
- enforce customer self-service restrictions
- allow merchant/POS override behavior where appropriate

Do not calculate eligibility from live product/shop policy for old orders. Old orders must use the snapshot captured at purchase time.

## POS vs Storefront Rules

Recommended behavior:

- Storefront customer self-service should enforce policy strictly.
- Merchant POS should show policy warnings but allow merchant override.
- Merchant processing of storefront requests should enforce policy by default, with override only if the business explicitly allows it.

Reason:

POS is a staff workflow. A merchant may intentionally make an exception for an in-store customer.

## Recommended Storefront Support Path

Do not duplicate the module.

Recommended changes when implementation starts:

1. Keep `OrderRefundService` and `OrderExchangeService` as shared execution services.
2. Add refund/exchange policy fields or settings at shop/product level.
3. Snapshot effective policy onto `order_items` during order creation.
4. Add a shared eligibility service.
5. Add storefront customer request routes/pages under customer account.
6. Reuse existing merchant processing services when requests are approved.
7. Protect POS routes so they only handle POS sales.
8. Add activity rendering for refund/exchange events in customer and merchant order detail.
9. Keep online paid refund handling out of automatic storefront refund until gateway refund support exists.
10. Keep delivery coverage separate from refund/exchange policy.

## Testing Needed When Implemented

Cover at minimum:

- POS refund still works
- POS exchange still works
- POS merchant override behavior
- storefront refund eligibility from order item snapshot
- storefront exchange eligibility from order item snapshot
- policy windows
- product override over shop default
- exchanged quantity cannot be refunded again if that business rule is chosen
- refunded quantity cannot be exchanged again
- inventory restoration on refund
- inventory restoration and deduction on exchange
- online paid order does not auto-refund without settlement support
- storefront customer cannot access another customer's order
- POS routes do not process storefront orders accidentally
- customer and merchant activity show refund/exchange events correctly
