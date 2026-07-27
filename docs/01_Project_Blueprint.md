# WindowShop Project Blueprint

This document is the single quick-read flow for WindowShop. Detailed table design, module lists, standards, and business rules live in the linked supporting docs.

## Product Purpose

WindowShop is a merchant commerce system for local shops. It supports merchant shop management, product catalogues, POS billing, customer records, refunds, exchanges, receipts, and future web/app shopping.

Primary users:

- Admin: manages platform setup, master data, merchants, and global settings.
- Merchant owner/manager: manages shop profile, products, settings, customers, sales, refunds, and exchanges.
- Cashier: uses POS, checkout, receipt, refund, and exchange workflows.
- Customer: shops through future web/app channels and may be attached to POS orders.

## Technical Request Flow

All modules should follow the same request-to-persistence shape:

```text
Browser / App / API
-> Route
-> Controller
-> Request validation / authorization
-> Service
-> Model
-> Database
-> View / JSON / Redirect
```

Controllers own HTTP concerns. Services own transactions and multi-step business workflows. Models own persistence metadata such as relationships, casts, route keys, UUID creation, and soft deletes.

Reference: `docs/21_Project_Standards.md`

## Merchant Setup Flow

```text
Admin creates user/merchant
-> Merchant profile is created
-> Merchant default settings are initialized
-> Merchant adds or activates shop
-> Active shop context is selected
-> Merchant can manage products, customers, POS, sales, refunds, and exchanges
```

Important rule: authentication uses the shared `users` table. Role-specific merchant information belongs in merchant profile/shop tables, not duplicate login tables.

References:

- `docs/08_Authentication_Architecture.md`
- `docs/11_User_Roles_Permissions.md`
- `docs/17_Business_Rules.md`

## Product Flow

```text
Merchant creates product
-> Product category and attributes are selected
-> Variants are created or generated
-> MRP, selling price, stock, SKU, and barcode are stored per variant
-> Product becomes searchable in active-shop POS
-> Barcode labels can be generated/printed
```

Product pricing used in future order/refund/exchange calculations must come from historical order item rows, not current variant prices.

Reference: `docs/03_Module_List.md`

## POS Sale Flow

```text
Cashier opens POS
-> Active shop product grid loads
-> Cashier searches/scans product
-> Exact barcode/SKU can auto-add to cart
-> Cashier adjusts quantities and discounts
-> Customer is optional for counter sale
-> Delivery requires customer and address
-> Payment method is selected
-> Checkout creates order, order items, totals, and status history
-> Stock is deducted
-> Receipt is shown/printed
```

Payment behavior:

- Cash supports received amount and change.
- UPI/Card default paid amount to payable total.
- Credit creates unpaid sale with zero amount paid.
- Disabled merchant payment methods are rejected.

Reference: `docs/03_Module_List.md`

## POS Customer Flow

```text
Walk-in customer is default
-> Cashier opens customer modal
-> Search by mobile, name, email, or customer code
-> Existing mobile record can be reused
-> If not found, quick-add customer by mobile
-> Customer is selected for cart
-> Shipping address can be selected/created when delivery is used
```

Customer mobile reuse is merchant-wide, so a customer added in one shop can be found again in another shop under the same merchant.

Reference: `docs/03_Module_List.md`

## Refund Flow

```text
Sales History
-> Sale Detail
-> Refund sale
-> Select return reason
-> Select refund method
-> Enter refundable quantities
-> Choose Restock per line
-> Process refund
-> Refund record and refund items are stored
-> Stock increments only for restocked lines
-> Payment/refund status is updated
```

Refund reason explains the cause of refund/return. `Exchange` must not be used as a refund reason.

Reference: `docs/17_Business_Rules.md`

## Exchange Flow

```text
Sales History
-> Sale Detail
-> Exchange sale
-> Scan/select old item from original order
-> Enter exchange quantity
-> Review original MRP, selling price, and paid/item
-> Add replacement item by scan/search/dropdown
-> System calculates returned value and replacement value
-> Difference becomes collect extra, refund balance, credit adjustment, or even exchange
-> Replacement order is created with source exchange_replacement
-> Returned stock increments only when Restock is checked
-> Replacement stock is deducted
-> Exchange receipt is available
```

Returned value rule:

```text
returned_unit_value = original_order_item.line_total / original_order_item.quantity
returned_line_total = returned_unit_value * exchange_quantity
settlement_return_value = returned_line_total + prorated original line tax
```

The original order item selling price after item-level discount is used. Current product price is never used for returned value.

Reference: `docs/00_Project_Backlog.md`

## Reporting Flow

Current reports:

- Sales History shows normal POS sales only.
- Replacement orders from exchanges use `created_source = exchange_replacement` and are excluded from sales/collection/customer spend reporting.
- Dashboard and recent-sales totals should treat only original POS sales as collected sales.

Planned report:

```text
Non-restocked Returns
-> Shows refund/exchange returned items where Restock was unchecked
-> Defaults to last 30 days
-> Allows date filters only within last 1 year
-> Older records stay in audit tables but are hidden from this operational report
```

References:

- `docs/00_Project_Backlog.md`
- `docs/17_Business_Rules.md`

## Inventory Flow

```text
Product variant stock is created/updated
-> POS checkout deducts stock
-> Refund restock increments stock
-> Exchange restock increments old item stock
-> Exchange replacement order deducts new item stock
-> Non-restocked returned items do not affect stock
```

Future inventory states should distinguish sellable, reserved, damaged, returned, and unavailable quantities.

Reference: `docs/17_Business_Rules.md`

## Future Web/App Shopping Flow

The future web storefront and React Native app should reuse the same order, customer, payment, refund, exchange, and stock rules. Channel-specific UI can differ, but source-of-truth behavior should remain shared through services.

Expected shape:

```text
Customer browses products
-> Cart
-> Login/mobile verification
-> Address and fulfilment
-> Payment
-> Order creation
-> Stock deduction
-> Order tracking
-> Refund/exchange request under merchant policy
```

## Supporting Docs

- `docs/02_Database_Architecture.md`: tables and relationships
- `docs/03_Module_List.md`: module-by-module feature list
- `docs/05_Development_Roadmap.md`: staged implementation roadmap
- `docs/08_Authentication_Architecture.md`: auth and registration flow
- `docs/10_File_Folder_Structure.md`: Laravel folder responsibilities
- `docs/15_Testing_Checklist.md`: verification checklist
- `docs/17_Business_Rules.md`: approved business policies
- `docs/20_Master_Data_Dictionary.md`: constants/statuses/reference values
- `docs/21_Project_Standards.md`: coding and architecture standards
