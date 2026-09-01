# Promotion Calculation Engine

## Phase 3A Scope

Phase 3A adds the first reusable runtime promotion calculation engine for WindowShop.

Supported automatic promotion rewards:

- Percentage Discount
- Fixed Amount Discount
- Fixed / Special Price

The base sellable price remains `product_variants.selling_price`. Promotions never update `selling_price`, do not add `offer_price`, and do not store temporary promotional prices on product variants.

## Pricing Sequence

WindowShop pricing order is:

```text
MRP
-> selling/base price
-> promotion adjustment
-> taxable amount
-> GST/tax
-> delivery
-> grand total
```

Promotion discounts are calculated before tax and then passed into the existing tax pricing services. The promotion engine does not contain tax logic.

## V1 Conflict Rule

Multiple active promotions may overlap, but WindowShop V1 does not stack promotion adjustments on the same item/unit. The best valid customer promotion is selected.

Tie-breaking is deterministic:

1. Highest customer discount wins.
2. If equal, higher promotion `priority` wins.
3. If still equal, the lowest promotion id wins.

`is_combinable` remains available for future behavior, but Phase 3A does not use it to stack discounts.

Merchant promotion overlap/conflict advisory is deferred and is not part of the calculation engine.

## Shop Isolation

Promotion calculation is shop-scoped. Cart groups are evaluated independently per shop. Checkout and order creation evaluate only the shop being ordered. A promotion from one shop must not affect products from another shop.

## Target Matching

Phase 3A resolves the existing target types:

- `all`
- `product`
- `variant`
- `category`
- `brand`
- `collection`

Category targets match the product category, root category, and loaded parent ancestry. Collection targets use existing collection membership only; collections remain pricing-neutral.

## Cart And Checkout

Cart item `unit_price` continues to store the current variant selling price. Cart promotion data is an effective calculation result for display and totals only.

Checkout/order placement re-evaluates promotions server-side from authoritative product variant data. Browser totals and previously displayed cart promotion totals are not trusted for final order pricing.

## Order Snapshots

Applied promotion discounts are stored in the existing order item discount and tax snapshot fields:

- `unit_price`
- `unit_discount`
- `line_subtotal`
- `item_discount_type`
- `item_discount_value`
- `line_discount`
- `taxable_amount`
- `line_tax`
- `line_total`
- `metadata`

Promotion attribution is stored in `order_items.metadata` with the applied promotion id, name, slug, template code, reward type, priority, base line subtotal, discount amount, and final pre-tax line subtotal.

Refund and exchange services continue to use historical order snapshots.

## Deferred

Phase 3A does not implement:

- promotion stacking
- fixed bundle price
- buy X get Y free
- buy X get Y discount
- quantity discount
- tier pricing
- free gift
- coupon UI/application/session storage
- promotion redemption locking or usage-limit enforcement
- merchant conflict advisory
- promotion analytics
