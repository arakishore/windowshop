# Direct Merchant UPI Investigation

**Scope:** Existing WindowShop storefront checkout and merchant payment implementation  
**Investigation date:** 2026-09-25  
**Status:** Investigation only; no implementation changes were made

## Executive Summary

Direct Merchant UPI currently stops at merchant configuration. It is not connected to storefront checkout, customer payment submission, merchant verification, or unpaid-order expiry.

The existing order schema provides reusable fields (`payment_method`, `payment_reference`, `upi_txn`, `payment_status`, and `amount_paid`), but no Direct Merchant UPI workflow currently writes or verifies them.

## 1. Existing Merchant UPI Architecture

Direct Merchant UPI is stored as shop-level typed settings in `shop_settings`:

- `payment.merchant_upi_enabled`
- `payment.merchant_upi_id`
- `payment.merchant_upi_payee_name`
- `payment.merchant_upi_qr_path`

Defaults are created by `ShopSettingsInitializer`. Values are read and written through `ShopSettingsService`.

This is correctly shop-specific rather than merchant-global. The Merchant Settings page operates on the merchant's active shop and identifies the affected shop.

The QR is an uploaded static merchant QR image. There is no QR-generation library, UPI URI builder, amount encoding, or order-reference encoding.

The image is stored on Laravel's `public` disk under:

```text
shops/{shop_id}/settings/upi-qr/{generated_filename}
```

Replacing the QR deletes the previous file after the new file is saved.

## 2. Existing Database Fields

### `shop_settings`

The generic typed key/value table contains:

- `shop_id`
- `group`
- `setting_key`
- `setting_value`
- `setting_type`
- timestamps
- A unique constraint on `shop_id + group + setting_key`

There is no dedicated UPI settings table.

### `orders`

Relevant existing fields include:

- `merchant_id`
- `shop_id`
- `payment_method`
- `payment_reference`
- `upi_txn`
- `payment_status`
- `currency_code`
- `grand_total`
- `amount_paid`
- `change_amount`
- `created_at` and `updated_at`
- `completed_at`
- `cancelled_at`

There are no dedicated fields for:

- Payment-submitted timestamp
- `paid_at`
- Payment-verification timestamp
- Payment verifier
- Rejection timestamp or reason
- Payment expiry timestamp

There is no uniqueness constraint on `payment_reference` or `upi_txn`.

### History

`order_status_histories` records order-status transitions, actor, timestamp, notes, and arbitrary JSON metadata. It is an order activity mechanism rather than a dedicated payment history table.

Existing COD and Cash at Shop collection actions use history metadata, so the same mechanism could record Direct UPI payment audit events for a minimum V1 implementation.

## 3. Existing Merchant Settings Behavior

Implemented:

- Enable/disable Direct Merchant UPI
- UPI ID
- Payee name
- QR upload and preview
- Active-shop scoping
- Existing QR retention when no replacement is uploaded
- Old-image deletion after replacement
- Dependent UI that hides or disables UPI fields when the method is disabled

Server-side validation when enabling:

- UPI ID is required
- Payee name is required
- A new or previously stored QR is required
- UPI ID and payee name have a maximum length of 191 characters
- QR must be JPG, JPEG, PNG, or WEBP and no larger than 2 MB

Limitations:

- UPI ID only receives generic string validation; its VPA format is not validated.
- The uploaded image is not verified to contain a usable UPI QR.
- There is no remove-QR action independent of replacement.
- There are no Direct Merchant UPI-specific tests.

## 4. Existing Checkout Behavior

`StorefrontPaymentMethodService` only defines and returns:

- `cash_on_delivery`
- `cash_at_shop`

Direct Merchant UPI:

- Never appears at checkout.
- Is not read from shop settings.
- Does not display the QR, UPI ID, payee name, or payment amount.
- Has no customer transaction/reference input.
- Has no `upi://pay` deep link.
- Cannot be submitted because `CheckoutController::placeOrder()` restricts `payment_method` to COD or Cash at Shop.
- Has no Place Order behavior.

The checkout itself is correctly single-shop:

- A selected shop ID is stored in checkout state.
- Cart calculations are scoped to that shop.
- Order placement requires exactly one shop group.
- The group's shop must equal the selected shop.
- Pricing and totals are recalculated server-side.
- Submitted browser totals are not trusted for order creation.

The foundation for retrieving only the selected shop's UPI settings therefore exists, but it is not yet used for Direct UPI.

## 5. Existing Order and Payment Behavior

Storefront orders are created with:

- `payment_status = pending`
- `amount_paid = 0`
- Server-calculated totals
- Immediate inventory deduction

The `Order` model defines these payment status constants:

- `pending`: awaiting payment
- `unpaid`: no amount received
- `partially_paid`: some payment received
- `paid`: fully paid
- `partially_refunded`
- `refunded`

The `PaymentStatus` master defines eight system statuses:

- `pending`: payment has not yet been received
- `partially_paid`: partial payment received
- `paid`: full payment received
- `failed`: unsuccessful payment attempt
- `cancelled`: payment cancelled before completion
- `partially_refunded`
- `refunded`
- `chargeback`

There is a status vocabulary mismatch: `Order` defines `unpaid`, but the payment-status master does not define a system `unpaid` status. Conversely, `failed`, `cancelled`, and `chargeback` exist in the master but not as `Order` constants.

The existing `payment_reference` and `upi_txn` fields are used by POS UPI functionality. Storefront checkout never accepts or populates them.

## 6. Existing Merchant Verification Behavior

Merchant Orders display:

- Payment method
- Payment status
- Order amount or balance

The merchant order payment-method label map already recognizes `merchant_upi` as **Direct Merchant UPI**, but this is display-only preparation.

Missing for Direct UPI orders:

- UPI transaction/reference display
- Verification state distinct from generic payment status
- Confirm Payment action
- Reject Payment action
- Verification/rejection controller endpoints
- Payment-specific validation and authorization
- Direct UPI payment history

Existing payment confirmation is limited to:

- Cash at Shop confirmation while completing pickup
- COD confirmation while completing delivery

Those actions mark payment as paid and record activity metadata. Merchant order acceptance is a separate order-status transition and does not confirm payment. This separation is correct, but no equivalent standalone Direct UPI action exists.

A customer-entered transaction ID must remain a claim of payment and must not be treated as payment confirmation.

## 7. Inventory Behavior

Inventory is deducted immediately inside `OrderCreationService`, after the order and initial status history are created. Payment verification is not a condition.

A future unverified Direct UPI order would therefore reserve or deduct stock immediately, like current storefront orders.

Cancellation restoration already exists:

- Merchant cancellation calls `OrderInventoryService::restoreForCancellation()`.
- Customer cancellation uses the same service.
- Stock is restored per product variant and scoped to the order's shop.
- Cancellation activity metadata records restored quantities.

Current customer self-cancellation rules only allow:

- `cash_at_shop` for pickup
- `cash_on_delivery` for delivery

A future `merchant_upi` order would not currently be customer-cancellable through that policy.

There is no special inventory action for rejected or expired payments. Such an outcome should use the existing cancellation/status workflow so stock is restored exactly once.

## 8. Security and Validation Findings

Existing strengths:

- Checkout is scoped to a selected cart shop.
- Checkout enforces one shop per order.
- Order items are verified against the selected shop.
- Merchant order actions require both the order shop and merchant to match the active shop.
- POS orders cannot use storefront merchant-order actions.
- Payment methods are allow-listed server-side.
- A disabled COD or Cash at Shop method cannot be manually submitted.
- Amounts, shipping, discounts, promotions, and totals are recalculated server-side.
- Customer address ownership is checked.

Direct UPI gaps:

- There is no server-side availability rule for `merchant_upi`.
- Reference validation is absent.
- No duplicate-reference check or database uniqueness protection exists.
- No canonical choice has been made between `payment_reference` and `upi_txn`.
- No merchant verification endpoint exists to authorize.
- No dedicated payment verification actor or timestamp exists, except what could be recorded in status-history metadata.
- UPI ID and QR are currently exposed only on authenticated merchant settings; they are not exposed to customers.

## 9. Timeout and Scheduler Capability

No unpaid-order timeout or auto-cancellation mechanism exists.

Current reusable infrastructure includes:

- Laravel scheduler wiring in `routes/console.php`
- A scheduled `products:purge-trash` command
- Existing `dailyAt()` and `withoutOverlapping()` usage
- Existing order cancellation and stock-restoration services

A later expiry implementation could use an idempotent scheduled command that:

1. Finds eligible pending Direct UPI orders older than the configured deadline.
2. Locks and rechecks each order.
3. Cancels it through the existing order-status workflow.
4. Restores stock using `OrderInventoryService`.
5. Records an expiry activity event.

The schema has no explicit payment expiry timestamp. Initially, expiry could be calculated from `created_at` plus configuration unless a dedicated deadline field is later justified.

## 10. What Is Already Complete

- Shop-specific UPI configuration storage
- Merchant settings UI
- Enable/disable setting
- UPI ID and payee name
- Static QR upload, preview, replacement, and storage
- Required-setting validation when enabled
- Single-shop checkout enforcement
- Server-authoritative pricing and totals
- Existing transaction/reference columns
- Pending, paid, and failed-capable payment-status vocabulary
- Merchant ownership authorization pattern
- Order activity metadata mechanism
- Immediate inventory deduction and cancellation restoration
- Scheduler foundation

## 11. Missing for a Complete V1 Flow

- Direct Merchant UPI in checkout payment resolution
- Selected-shop enabled/configuration checks
- A decision on availability for delivery, pickup, or both
- QR, UPI ID, payee name, and exact amount display
- Customer transaction/reference input
- Server-side UPI reference validation
- Duplicate-reference policy
- Reference persistence without marking payment paid
- Direct UPI checkout-success instructions
- Merchant display of submitted reference and amount
- Standalone Confirm Payment action
- Standalone Reject Payment action
- Merchant authorization for those actions
- `amount_paid` update only after confirmation
- Payment verification/rejection activity records
- Explicit rejected-payment and inventory behavior
- Customer cancellation policy for unverified UPI
- Unpaid-order expiry, if required
- Mobile `upi://pay` link
- Direct UPI-specific feature tests

## 12. Recommended Minimum Implementation Steps

1. Add `merchant_upi` to `StorefrontPaymentMethodService`, resolving settings exclusively from the selected shop and only when enabled with complete configuration.
2. Return a checkout-safe payment payload containing UPI ID, payee name, static QR URL, and server-calculated total.
3. Extend checkout with payment instructions and a required transaction/reference field.
4. Extend checkout controller and order service validation, revalidating the selected shop's UPI availability during order placement.
5. Choose one canonical existing field, preferably `upi_txn`, for the customer-provided UPI transaction ID and define how `payment_reference` differs before using both.
6. Create the order as `pending` with `amount_paid = 0`; never infer confirmation from the submitted ID.
7. Show the submitted transaction ID and amount on Merchant Orders.
8. Add separate Confirm Payment and Reject Payment actions protected by existing active-shop authorization.
9. On confirmation, atomically set `payment_status = paid`, set `amount_paid = grand_total`, and record actor, time, and reference in activity metadata.
10. On rejection, record a failed/rejected payment event. Explicitly decide whether rejection keeps the order pending for correction or cancels it and restores stock.
11. Add duplicate-reference checks scoped according to the business decision, at least shop-wide and potentially globally.
12. Add targeted tests before introducing automatic expiry.
13. Later, generate a standards-compatible encoded `upi://pay` URI from the stored UPI ID, payee name, server-calculated amount, currency, and order number.

The existing data is sufficient to generate a dynamic mobile UPI payment URI later while payment continues to go directly from customer to merchant. WindowShop would remain an instruction and verification layer, not the payment recipient or gateway.

A schema migration may not be required for minimum V1 if audit timestamps and actors are stored in order-status-history metadata. A dedicated payment-attempt/history model would be cleaner if multiple submissions, rejection cycles, or stronger uniqueness and auditing are required.

## 13. Relevant Files

- `app/Services/Merchant/ShopSettingsInitializer.php`
- `app/Services/Merchant/ShopSettingsService.php`
- `app/Models/ShopSetting.php`
- `app/Http/Controllers/Merchant/MerchantSettingsController.php`
- `resources/views/merchant/settings/edit.blade.php`
- `app/Services/Checkout/StorefrontPaymentMethodService.php`
- `app/Http/Controllers/Storefront/CheckoutController.php`
- `app/Services/Checkout/StorefrontCheckoutOrderService.php`
- `resources/views/storefront/pages/checkout.blade.php`
- `resources/views/storefront/pages/checkout-success.blade.php`
- `app/Models/Order.php`
- `app/Models/PaymentStatus.php`
- `app/Models/OrderStatusHistory.php`
- `app/Services/Order/OrderCreationService.php`
- `app/Services/Order/OrderInventoryService.php`
- `app/Services/Order/CustomerOrderCancellationService.php`
- `app/Http/Controllers/Merchant/OrderController.php`
- `resources/views/merchant/orders/show.blade.php`
- `config/order_workflow.php`
- `routes/console.php`
- `database/migrations/2026_07_15_000003_create_orders_table.php`
- `database/migrations/2026_07_15_000006_create_order_status_histories_table.php`
- `database/migrations/2026_08_24_000001_create_shop_settings_table.php`

## 14. Tests and Verification

The following targeted tests were run:

```text
php artisan test tests/Feature/MerchantSettingsFoundationTest.php tests/Feature/StorefrontCheckoutGateTest.php tests/Feature/MerchantOrderActionsTest.php tests/Unit/OrderStatusWorkflowTest.php
```

Results:

- 165 passed
- 3 failed
- 1,347 assertions
- Merchant settings suite passed.
- Merchant order action suite passed.
- Order workflow unit suite passed.

The three checkout failures appeared unrelated to Direct Merchant UPI:

- Registration expected a mobile field.
- Default country `IN` was not configured.
- An unavailable-item message assertion expected different text.

No tests mention `merchant_upi`, its QR, payee name, or checkout/verification behavior.

Browser verification was attempted, but the computer-use environment reported that no browser surface was available. The Blade UI and its behavior were inspected directly in code; no live visual verification was claimed.

