# Business Rules

## Purpose and Change Process

This is the canonical register for approved WindowShop business behavior. Each rule should identify its owner, effective date, affected roles, exceptions, and tests. Proposed behavior must be labeled **Proposed** until approved.

## Merchant Approval

**Initial policy: Proposed**

- New merchants cannot transact until required verification and approval are complete.
- Approval, rejection, suspension, and reactivation require an authorized actor and audit event.
- Rejection and suspension require a safe internal reason.
- Merchant staff access depends on both staff and merchant status.

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
- An order receives immutable identifying and pricing snapshots at placement.
- Status transitions follow a defined workflow; arbitrary jumps are rejected.
- Cancellation eligibility depends on current fulfillment and payment state.
- Every material transition is auditable and idempotent.

## Returns and Exchanges

**Initial policy: Proposed**

- Eligibility depends on product policy, delivery date, condition, and reason.
- Return/exchange windows are configured, not scattered as code literals.
- Approval, rejection, receipt, inspection, replacement, and refund are explicit states.
- Inventory and financial adjustments occur only at approved workflow points.

## Inventory

**Initial policy: Proposed**

- Stock cannot become negative unless an explicit overselling policy permits it.
- Reservations are atomic, time-bounded, and released on expiry or cancellation.
- Every manual adjustment records actor, quantity delta, reason, and reference.
- Sellable, reserved, damaged, returned, and unavailable quantities remain distinguishable.

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
