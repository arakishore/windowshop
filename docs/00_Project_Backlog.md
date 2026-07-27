# Project Backlog

## Project Overview

Laravel + Blade + REST API SaaS project for a hyperlocal marketplace, private shop app, and mobile POS platform.

## Database Architecture Decision

### Main Application Database

Database name: `webtree_commerce`

Purpose:

Stores all business and transactional data, including:

- shops
- products
- customers
- orders
- POS bills
- inventory
- subscriptions
- settings

### Separate Logs Database

Database name: `webtree_commerce_logs`

Purpose:

Stores heavy audit, activity, debug, and history data, including:

- user activity logs
- admin action logs
- shop action logs
- staff action logs
- API logs
- notification logs
- AI usage logs
- login/logout logs
- error/debug logs

### Architecture Rule

Main DB = business and transactional data.

Logs DB = audit, activity, debug, and history data.

## Current Focus: Authentication and Users

Planned areas to design next:

- users
- user types
- roles
- permissions
- shop owner mapping
- staff mapping
- customer login
- password reset
- mobile device tokens

## Pending POS Enhancements

- Add optional POS payment reference fields when needed:
  - UPI transaction/reference number for UPI payments
  - Terminal/Auth reference for card payments
  - Optional generic payment reference for manual reconciliation
- Keep these fields hidden for Cash unless a clear business need appears.
- Store references in existing order fields:
  - `payment_reference`
  - `upi_txn`
  - `terminal_id`
- Continue showing saved references on receipts and sales detail pages.

## POS Exchange V1 Plan

Exchange must remain separate from Refund/Return.

- Refund/Return: customer returns an item and receives money back.
- Exchange: customer returns an old item, receives replacement item(s), and any price difference is settled.

### V1 Scope

- Add a separate Exchange action on sale detail and sales action dropdown.
- Do not place Exchange inside the refund screen.
- Create dedicated exchange records:
  - `order_exchanges`
  - `order_exchange_return_items`
- Use existing `orders`, `order_items`, and `order_totals` for the replacement order.
- Classify replacement orders with a clear source such as `exchange_replacement`.
- Store settlement information on `order_exchanges`; do not create a payment transaction module in V1.
- Keep current direct stock update style for consistency with POS checkout and refund.
- Keep stock logic isolated in an exchange service so it can later move to an inventory movement service.
- Hide/remove `Exchange` from default return reasons.
- Treat "we will exchange it" as shop policy text, not a return reason.
- Add a basic printable Exchange Receipt.
- Completed exchanges are not editable.
- Do not implement exchange cancellation/reversal in V1.

### Returned Value Formula

Returned value must use the original order item financial snapshot, not the product's current price.

```text
returned_unit_value = original_order_item.line_total / original_order_item.quantity
returned_line_total = returned_unit_value * exchange_quantity
returned_line_tax = original_order_item.line_tax * (exchange_quantity / original_order_item.quantity)
returned_total = sum(returned_line_total + returned_line_tax)
```

`order_items.line_total` is tax-exclusive. Current POS tax is zero, so this preserves current behaviour while preventing GST omission when `line_tax` becomes non-zero.

### Exchangeable Quantity Formula

```text
exchangeable_quantity =
  ordered_quantity
  - already_refunded_quantity
  - already_exchanged_quantity
```

Do not allow exchange quantity above remaining exchangeable quantity.

### Difference Formula

```text
replacement_total = total of the new replacement order
difference_amount = abs(replacement_total - returned_total)
```

Difference type:

- `even`: replacement total equals returned total
- `collect_extra`: replacement total is greater than returned total
- `refund_balance`: replacement total is less than returned total

### Settlement Rules

For `collect_extra`, support:

- Cash
- UPI
- Card

For `refund_balance`, support:

- Cash
- Original payment method

Credit-sale refund handling must be reviewed carefully. If the original payment method is `credit`, refund-balance should not silently refund to credit without a clear merchant decision.

### Stock Rules

Inside one database transaction:

- Lock replacement variant rows before validating/deducting stock.
- Increment returned variant stock only when `restock = true`.
- Do not increment returned stock when `restock = false`.
- Deduct replacement variant stock like a normal POS sale.
- Roll back exchange records, replacement order, returned stock changes, and replacement stock deduction if any step fails.

### V1 UI Requirements

Exchange page should include:

- Original order information
- Exchangeable original lines
- Quantity to exchange
- Optional exchange note in V1. Dedicated exchange reasons are deferred and must belong to the Exchange module, not return reasons.
- Restock checkbox per returned line
- Returned line value
- Replacement item search by barcode, SKU, and product name using the POS search endpoint
- Replacement quantity controls
- Replacement stock display
- Difference summary
- Settlement method when needed
- Notes
- Confirmation modal before completion

### Exchange Receipt

Basic Exchange Receipt should show:

- Exchange number
- Original order number
- Replacement order number
- Returned items and values
- Replacement items and values
- Returned total
- Replacement total
- Amount collected or refunded
- Settlement method
- Restock status
- Date/time
- Staff member

## POS Exchange Implementation Report Checklist

After exchange implementation, report:

1. Full list of changed files.
2. Migrations created and resulting table structures.
3. Returned-value and exchangeable-quantity formulas.
4. Replacement-order source handling.
5. Stock locking and update logic.
6. Settlement storage and credit-sale refund handling.
7. Routes and permission/ownership checks.
8. Exchange UI and receipt screenshots when available.
9. Tests added and exact test results.
10. Confirmation that all existing POS and refund tests still pass.
11. Deferred Phase 2/Phase 3 items.

## POS Exchange V1 Implementation Record

Status: Implemented in V1.

Changed files:

- `app/Http/Controllers/Merchant/SalesHistoryController.php`
- `app/Http/Controllers/Merchant/ReturnReasonController.php`
- `app/Models/Order.php`
- `app/Models/OrderExchange.php`
- `app/Models/OrderExchangeReturnItem.php`
- `app/Services/Merchant/MerchantReturnReasonInitializer.php`
- `app/Services/Order/OrderExchangeService.php`
- `database/migrations/2026_07_19_000006_create_order_exchanges_table.php`
- `database/migrations/2026_07_19_000007_create_order_exchange_return_items_table.php`
- `resources/views/merchant/sales/exchange.blade.php`
- `resources/views/merchant/sales/exchange-receipt.blade.php`
- `resources/views/merchant/sales/index.blade.php`
- `resources/views/merchant/sales/show.blade.php`
- `resources/views/merchant/settings/edit.blade.php`
- `routes/merchant.php`
- `tests/Feature/MerchantPosTest.php`
- `tests/Feature/MerchantSettingsFoundationTest.php`
- `docs/00_Project_Backlog.md`
- `docs/02_Database_Architecture.md`
- `docs/03_Module_List.md`
- `docs/CHANGELOG.md`

Tables:

- `order_exchanges`: exchange header, original order, replacement order, returned/replacement totals, signed difference, amount collected/refunded, credit adjustment, settlement type/method, status, actor, metadata.
- `order_exchange_return_items`: original returned lines, quantity, historical unit return value, line tax, line total, restock flag, metadata.

Formulas:

```text
returned_unit_value = original_order_item.line_total / original_order_item.quantity
returned_line_total = returned_unit_value * exchange_quantity
exchangeable_quantity = ordered_quantity - already_refunded_quantity - already_exchanged_quantity
difference_amount = replacement_total - returned_total
```

Replacement orders:

- Created through the normal order creation service.
- Stored in `orders` and `order_items`.
- Marked with `orders.created_source = exchange_replacement`.
- Linked back from `order_exchanges.replacement_order_id`.
- The replacement order's `amount_paid` is operational so the replacement order is completed/receiptable; actual newly collected/refunded money is stored only on `order_exchanges`.
- Sales, collection, dashboard, recent-sales, and customer spend reports must exclude `created_source = exchange_replacement`.

Stock:

- Original order is locked during exchange creation.
- Replacement variants are locked and deducted by the order creation service.
- Returned variants are locked and incremented only when restocked.
- The exchange record, returned stock increment, replacement order, and replacement stock deduction are in one transaction.

Credit-sale handling:

- If replacement is cheaper and the original order was a credit sale, no cash refund is recorded.
- The balance is stored as `credit_adjustment_amount` with `settlement_type = credit_adjustment`.

Routes and checks:

- `GET /merchant/sales/{order}/exchange`
- `POST /merchant/sales/{order}/exchange`
- `GET /merchant/sales/exchanges/{exchange}/receipt`
- Existing merchant auth, merchant role, and active-shop middleware apply.
- Controller checks active shop, merchant ownership, and completed order status.

Tests:

- Added exchange tests for historical discounted returned value, replacement source, stock updates, exchangeable quantity after refunds/exchanges, and credit adjustment.
- Added regression coverage for Search/Scan + Dropdown default, Search-only mode, Dropdown-only mode, original price display, exchange help modal, non-restock guidance, tax-aware returned value, report exclusion, settlement difference, and hidden Exchange return reasons.
- Exact result: `php artisan test tests\Feature\MerchantSettingsFoundationTest.php tests\Feature\MerchantPosTest.php` passed, 58 tests and 425 assertions.

Deferred:

- Exchange cancellation/reversal.
- Dedicated payment transaction ledger.
- Search-as-you-type result refinement beyond the current scan/search button and Enter-key flow.
- Manager approval workflow.
- Screenshots from browser verification.
- Exchange policy settings such as exchange window, bill required, item condition, and category/product eligibility.
- Dedicated Exchange-module reason list, separate from refund/return reasons.
- Non-restocked Returns report:
  - Shows refund and exchange returned items where stock was not incremented.
  - Default view shows the last 30 days.
  - Date filters are allowed only within the last 1 year.
  - Older records stay in refund/exchange audit tables but are hidden from this operational report.
  - Suggested filters: date range, store, Refund/Exchange type, product/SKU, customer, cashier, and review status.
