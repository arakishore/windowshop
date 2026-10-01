# Business Rules

## Purpose and Change Process

This is the canonical register for approved WindowShop business behavior. Each rule should identify its owner, effective date, affected roles, exceptions, and tests. Proposed behavior must be labeled **Proposed** until approved.

## Merchant Approval

**Initial policy: Proposed; superseded on 2026-09-28 by Merchant Access, Verification and Storefront Visibility below.**

- New merchants cannot transact until required verification and approval are complete.
- Approval, rejection, suspension, and reactivation require an authorized actor and audit event.
- Rejection and suspension require a safe internal reason.
- Merchant staff access depends on both staff and merchant status.

## Merchant Access, Verification and Storefront Visibility

**Current policy: Approved on 2026-09-28**

Merchant account status and verification status are separate concepts. Verification approval controls public storefront eligibility; it is not a prerequisite for merchant login or pre-publication store preparation.

### Login and Store Preparation

- A merchant may log in with valid credentials while the merchant account status is `active`, `inactive`, or `suspended`, and while verification status is `pending`, `submitted`, `approved`, or `rejected`, provided the user/account still exists.
- A non-suspended merchant may immediately prepare and manage the business before approval, including shops, products, images, pricing, inventory, applicable shop settings, and promotions/offers where otherwise permitted.
- `pending`, `submitted`, `rejected`, and `inactive` are not suspension states. Unless an independent authorization or business rule applies, they do not prevent merchant preparation or management activity.
- Rejection does not imply suspension, and merchant account status remains independent from verification status.

### Public Storefront Visibility

A shop or product is eligible for public storefront visibility only when every applicable condition is satisfied:

```text
Merchant account status = Active
AND Merchant verification status = Approved
AND Shop status = Active
AND normal product visibility/publication requirements pass
= eligible for storefront visibility
```

Merchant login, shop creation, or product creation alone never makes content public. An inactive or suspended merchant, a merchant whose verification is pending, submitted, or rejected, an inactive shop, or a product that fails normal publication rules is not publicly visible.

### Merchant Panel Status Warning

- The Merchant Panel must prominently explain the actual status when merchant/shop/product content is not publicly visible, including `pending`, `submitted`, `rejected`, `inactive`, and `suspended` states.
- Rejection messaging should use the existing rejection reason when available.
- Exact warning wording and visual design remain implementation decisions and are not frozen here.

### Suspended Merchant Restriction

- A suspended merchant may log in and view the Merchant Panel and account-status information.
- The panel must show a clear suspension message with appropriate contact-admin/support guidance.
- A suspended merchant must not perform operational activity. Server-side authorization must block applicable mutations, including shop, product, inventory, pricing, promotion/offer, order-processing, and operational-settings actions.
- Hiding or disabling UI controls is not sufficient enforcement.
- Suspended merchant shops and products are not publicly visible.

### Decision Matrix

| Merchant Account | Verification | Login | Merchant Activity | Public Visibility |
|---|---|---|---|---|
| Active | Approved | Yes | Yes | Yes, subject to Active Shop and normal product rules |
| Active | Pending | Yes | Yes | No |
| Active | Submitted | Yes | Yes | No |
| Active | Rejected | Yes | Yes | No |
| Inactive | Any | Yes | Yes* | No |
| Suspended | Any | Yes | No operational activity | No |

\* Subject to independent existing authorization or business restrictions; `inactive` must not automatically be treated as `suspended`.

Implementation must enforce suspension restrictions and storefront publication eligibility server-side. Authentication, middleware, publication queries, Merchant Panel warnings, and tests may require later changes; this documentation decision does not implement them.

## Shared Identity, Profile Context and Merchant Staff Principles

**Current policy: Approved on 2026-09-28**

### Shared User Identity

- `users` remains the canonical authentication identity for customers, merchant owners, and merchant staff. A new role or relationship must not create a duplicate User where the person can be safely matched to an existing identity.
- Email remains the login identity for Customers, Merchant Owners, and Merchant Staff. WindowShop must not introduce separate usernames for these contexts.
- One email maps to one canonical User, one set of credentials, and zero or more authorized application contexts.
- One User may hold multiple authorized roles/relationships, including Customer + Merchant Owner or Customer + Merchant Staff, where supported by the domain relationship.
- An existing Customer who registers as a Merchant must retain the Customer role/profile and credentials, receive the Merchant role/profile relationship on the same User, and must not receive a second User record.
- A User who already has a Merchant account/profile must not receive a duplicate merchant registration.

### Authentication and Profile Context

- One authenticated account may expose multiple authorized contexts: Customer, Merchant Owner, and Merchant Staff.
- A multi-role User must be able to select and switch among authorized contexts without logging out or authenticating as another User.
- Every context selection or switch must be authorized server-side against the User's current roles and domain relationships.
- The exact profile-selector UI and whether/how the current or last context is remembered in session are not yet frozen.

### Merchant Owner and Merchant Staff

- Merchant Owner and Merchant Staff are different concepts. The owner controls the merchant account with full merchant authority, subject to WindowShop/admin restrictions.
- Merchant Staff uses the staff member's own User identity and a merchant/shop relationship with permission-based operational access.
- Staff membership alone must not create a `MerchantProfile` for the staff User.
- A staff User may independently retain a Customer context. Removing or disabling staff access must block only that merchant/shop relationship, not delete or disable the User or Customer access.
- One User may hold separate Merchant Staff memberships for multiple Merchants. Each membership belongs to exactly one Merchant and must remain independently scoped for authorization, permissions, suspension, removal, shop assignment, and context switching.
- Within one membership, staff must be assignable to one or more Shops owned by that membership's Merchant. A membership must never grant access to another Merchant's Shop.
- Removing or disabling one membership must preserve the canonical User, Customer context, other staff memberships, and every unrelated authorized context.

### Staff Creation and Existing Users

- Merchants create or assign staff directly; staff access does not require an accept/decline or pending-acceptance workflow.
- Staff creation must check the submitted email against canonical Users. If it exists, WindowShop reuses that User and preserves all existing valid profiles, memberships, roles, and contexts before adding the independently scoped membership.
- If the email does not exist, WindowShop may provision one new canonical User. The Merchant must not know or control the employee's permanent password.
- Creating staff access sends an informational account email stating that access was created for the Merchant. For a new User, that communication must support a future secure credential-setup process; exact setup mechanics and email wording remain pending.

### Staff Workspace Context

- Context selection must distinguish Customer, Merchant Owner, and each merchant-specific Staff membership. For example, one authenticated User may select Customer, Merchant A Staff, or Merchant B Staff without authenticating as another account.
- The selected context determines the active Merchant and permitted Shop authority and must be revalidated server-side.
- Exact selector UI and session/last-context persistence remain pending.
- Detailed database design, membership statuses and lifecycle, staff role names, permission matrix, direct-versus-role permissions, shop-assignment storage, credential-setup mechanism, notification copy, and staff-specific POS permissions remain pending design decisions.

### Merchant Suspension Inheritance

- Merchant Staff must not bypass a merchant suspension. When a Merchant is suspended, operational access for the owner and all staff associated with that merchant must be blocked server-side.
- Merchant suspension is scoped to that Merchant. Owner and staff Users may still authenticate and use independent authorized contexts, including Customer and staff memberships for other Merchants, while the suspended merchant context is restricted.
- Suspension enforcement must be based on the selected merchant relationship/context as well as the User identity; UI hiding alone is insufficient.

## Shop And Category Rules

**Current policy: Approved**

- `product_categories` is the single category master.
- Root product categories (`parent_id IS NULL`) are Shop Types.
- Child or leaf product categories (`parent_id IS NOT NULL`) classify products.
- Admin and merchant shop forms show Shop Type from active root product categories.
- Products must be assigned to an active leaf product category under the selected shop's Shop Type.
- `products.root_product_category_id` is copied from the selected shop and is not independently editable.
- Merchant users may add shops from the merchant area and choose `active` or `inactive`.
- Merchant users may edit their own shop details and switch eligible shops active/inactive.
- Merchant users cannot delete shops. Shop delete actions remain admin-only.
- Admin shop type changes are blocked if existing products would no longer belong under the new Shop Type.

## Product Attribute Variant Rules

**Current policy: Approved**

- `product_attribute_groups.selection_type` controls how many values can be selected for that group.
- Variant generation is controlled by `product_category_attribute_groups.is_variant`.
- Do not add `is_variant` to `product_attribute_groups`; the same attribute group can behave differently by category.
- Only category attribute mappings where `is_variant = true` may be used to generate product variants.
- Apparel defaults: Color and Size are variant attributes; Material, Sleeve, Neck, Pattern, Fit, and Occasion are descriptive attributes.
- Descriptive attributes must not multiply generated variants.

## Customer Registration

**Initial policy: Proposed**

- Customers register through approved email, mobile/OTP, or future social-login flows.
- Email/mobile uniqueness and verification rules apply before sensitive actions.
- Registration responses must resist account enumeration.
- Acceptance of applicable terms and privacy notices must be recorded.

## Orders

**Initial policy: Proposed**

- Server-side prices, taxes, discounts, availability, and delivery charges are authoritative.
- Detailed delivery-setting rules are maintained in `docs/Delivery_Settings.md`.
- An order receives immutable identifying and pricing snapshots at placement.
- Status transitions follow a defined workflow; arbitrary jumps are rejected.
- Cancellation eligibility depends on current fulfillment and payment state.
- Every material transition is auditable and idempotent.

## Storefront Payment Settings

**Initial policy: Proposed for storefront checkout V1**

- Detailed payment-setting rules are maintained in `docs/Payment_Settings.md`.
- Storefront payment settings are shop-level settings stored in `shop_settings`, not merchant-level POS settings.
- Existing POS tender settings (`cash`, `card`, `upi`, `credit`, default method, active method) remain merchant-level settings stored in `merchant_settings` and apply only to POS checkout.
- Storefront payment settings must be resolved by the involved `shop_id`.
- A merchant may update storefront payment settings only for the currently resolved Active Shop owned by that merchant.
- The settings form must not trust a submitted `shop_id`.
- Storefront payment settings initially include:
  - `payment.cod_enabled`
  - `payment.cod_min_order_amount`
  - `payment.cod_max_order_amount`
  - `payment.cash_at_shop_enabled`
  - `payment.merchant_upi_enabled`
  - `payment.merchant_upi_id`
  - `payment.merchant_upi_payee_name`
  - `payment.merchant_upi_qr_path`
  - `payment.online_payment_enabled`
- Cash on Delivery can be offered only for delivery fulfillment.
- Cash at Shop can be offered only for pickup fulfillment.
- Direct Merchant UPI can be offered only when enabled and the shop has UPI ID, payee name, and QR code configured.
- Direct Merchant UPI stores only a file path/reference for the QR code; binary or base64 QR data must not be stored in `shop_settings`.
- Online Payment remains disabled until a gateway flow exists; gateway credentials must not be added to `shop_settings`.
- COD minimum and maximum order amounts use `0` as "no limit":
  - `Min blank or 0`, `Max blank or 0`: COD is available for any order amount.
  - `Min 500`, `Max blank or 0`: COD is available for orders of `500` or more.
  - `Min blank or 0`, `Max 5000`: COD is available for orders up to `5000`.
  - `Min 500`, `Max 5000`: COD is available for orders from `500` through `5000`.
- COD min/max validation should compare minimum to maximum only when both values are greater than `0`.
- For V1 multi-shop checkout:
  - COD is available only if all involved shops support COD and the order amount satisfies each applicable shop rule.
  - Direct Merchant UPI is single-shop only.
  - Cash at Shop is single-shop pickup only.
- These settings do not create payment execution, payment verification, split-payment, or gateway behavior by themselves.
- Checkout and order creation must read these settings server-side when storefront payment availability is implemented.

## POS Held Orders

**Current policy: Approved for POS V1 browser-held carts**

- Held orders are paused POS carts, not database orders.
- POS V1 stores held orders in browser `localStorage` under shop-scoped keys.
- Held orders are not stored in cookies, Laravel sessions, or database tables.
- Held orders do not reserve stock and are not included in reports, sales history, customer history, or recent sales.
- Completed POS checkout is the point where a real order is created in the database.
- Cross-device sync, auditability, expiry enforcement, and stock reservation require a future DB-backed held-order module.
- Implementation notes are documented in `docs/POS_Held_Orders.md`.

## Returns and Exchanges

**Current policy: Approved for POS V1 separation**

- Refund/Return and Exchange are separate merchant workflows.
- A refund/return reason explains why money is being returned or a return is being recorded.
- `Exchange` must not be used as a refund/return reason because it describes a workflow, not the item condition or refund cause.
- Shop messages such as "try this, we will exchange it" are exchange policy text and belong in receipt/shop policy settings, not return reasons.
- POS Exchange V1 captures operational notes on the exchange. Dedicated exchange reasons such as size issue, color change, customer preference, wrong item sold, or defective item are a future Exchange-module setting.
- Eligibility depends on product policy, sale date, item condition, and shop exchange policy.
- Return/exchange windows should be configured, not scattered as code literals.
- Approval, rejection, receipt, inspection, replacement, and refund can become explicit states in later workflow phases.
- Inventory and financial adjustments occur only at approved workflow points.

## Inventory

**Initial policy: Proposed**

- Stock cannot become negative unless an explicit overselling policy permits it.
- Reservations are atomic, time-bounded, and released on expiry or cancellation.
- Every manual adjustment records actor, quantity delta, reason, and reference.
- Sellable, reserved, damaged, returned, and unavailable quantities remain distinguishable.
- Non-restocked return/exchange items are audit records; operational reports should default to recent records and show at most the last 1 year, without deleting older source records.

## Coupons

**Initial policy: Proposed**

- Eligibility, validity period, usage limit, minimum order, scope, and stacking rules are server-enforced.
- Coupon use is concurrency-safe and idempotent.
- Discounts never exceed eligible value or produce invalid negative totals.

## Wallet

**Initial policy: Proposed**

- Wallet balances derive from an immutable ledger, not direct balance edits.
- Credits and debits require a type, reference, actor/source, and idempotency key.
- Refund, expiry, withdrawal, and negative-balance rules require separate approval.

## Commission

**Initial policy: Proposed**

- Commission rules are versioned and snapshotted onto financial records.
- Calculation defines base amount, taxes, discounts, shipping, refunds, and rounding.
- Manual overrides require dedicated permission, reason, and audit history.

## Referrals

**Initial policy: Proposed**

- A referral reward is granted only after a defined qualifying event.
- Self-referral, duplicate identity, abuse, reversal, expiry, and maximum-reward rules are enforced.
- Rewards use auditable wallet or benefit records.

## Decision Template

```text
Rule:
Status: Proposed | Approved | Deprecated
Owner:
Effective date:
Applies to:
Decision:
Exceptions:
Audit requirement:
Tests:
Related ADR:
```
