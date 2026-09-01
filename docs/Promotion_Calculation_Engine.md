# Promotion Calculation Engine

## Phase 3A/3B/3C-A Scope

Phase 3A added the first reusable runtime promotion calculation engine for WindowShop. Phase 3B extends that same engine with quantity-based automatic rewards.
Phase 3C-A adds automatic Buy X Get Y Free runtime support.

Supported automatic promotion rewards:

- Percentage Discount
- Fixed Amount Discount
- Fixed / Special Price
- Quantity Discount
- Fixed Bundle Price
- Buy X Get Y Free
- Tier Pricing

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

`is_combinable` remains available for future behavior, but the Phase 3 engine does not use it to stack discounts.

Merchant promotion overlap/conflict advisory is deferred and is not part of the calculation engine.

## Quantity Rewards

Quantity-based promotion templates do not all share the same decimal quantity rule. `quantity_discount` and `tier_pricing` use the full eligible quantity. `fixed_bundle_price` and `buy_x_get_y_free` remain discrete-unit based and only complete whole units participate.

### Quantity Discount

`quantity_discount` applies when the line meets its minimum quantity condition. The threshold is inclusive, so a minimum quantity of 3 means quantity greater than or equal to 3.

Once the minimum quantity is reached, the discount applies to the full eligible quantity, including decimal quantity:

- `value_type = percent` discounts the full eligible line subtotal by `value_percent`.
- `value_type = amount` discounts each eligible quantity unit by `value_amount`, including decimal quantity.
- The discount is capped at the line subtotal.

Example for a Rs. 1,000 item with minimum quantity 3 and 10% off: quantity 3.75 has a Rs. 3,750 base subtotal, Rs. 375 discount, and Rs. 3,375 final pre-tax subtotal.

Targets remain item-based and support the normal eligible target types: all, product, variant, category, brand, and collection.

### Fixed Bundle Price

`fixed_bundle_price` evaluates all eligible whole units in the shop calculation together. It supports bundles that span multiple lines/SKUs when those units match the promotion target.

Bundle participation is whole-unit only. Fractional quantity does not participate in a bundle and remains at base price.

Behavior:

- A complete bundle requires `bundle_quantity` eligible whole units.
- Repeated complete bundles are supported.
- Incomplete remainder units keep their base price.
- If the bundle price is not better than the participating units' base value, no discount is applied.
- When more eligible units exist than needed for complete bundles, the engine chooses the highest-priced eligible whole units first.

Bundle discounts are allocated back to participating order/cart lines proportionally by participating base value. Any cent remainder is assigned deterministically by largest remainder, then lowest variant id.

### Buy X Get Y Free

`buy_x_get_y_free` evaluates BUY and GET targets independently using the existing target roles and target types. BUY and GET targets may be the same pool, completely different pools, or partially overlapping pools.

BOGO participation is whole-unit only. Fractional quantity does not participate in BUY/GET counting and remains at base price.

Behavior:

- Same-pool offers require `buy_quantity + get_quantity` eligible whole units per completed group.
- Different-pool offers complete `min(floor(buy_units / buy_quantity), floor(get_units / get_quantity))` groups.
- Partially overlapping pools use conceptual unit consumption so a physical unit cannot count as both a BUY qualification unit and a GET/free unit.
- If selected GET/free units would leave insufficient BUY units, the engine reduces completed groups until the allocation is valid.
- GET/free selection uses the cheapest eligible GET whole units, ordered by unit price ASC, variant id ASC, then unit index ASC.
- Same cart/order line quantities greater than 1 are expanded internally into conceptual whole units, then collapsed back to line-level discounts.

BOGO is treated as a group promotion for conflict checks. GET units receive the monetary discount, while BUY qualification units are also protected from conflicting promotions. A BOGO allocation is rejected or reduced if another promotion would take a required BUY or GET participating line.

### Tier Pricing

`tier_pricing` uses volume pricing. The engine selects the greatest valid tier whose `min_quantity` is less than or equal to the line's full eligible quantity.

Once a tier matches:

- The matched tier unit price applies to the full eligible quantity, including decimal quantity.
- The engine does not use graduated tier pricing.
- Tiers with invalid values or tier prices greater than/equal to the base unit price produce no discount.

Example for a Rs. 1,000 item with a 3+ tier at Rs. 900: quantity 3.75 has a Rs. 3,750 base subtotal, Rs. 375 discount, and Rs. 3,375 final pre-tax subtotal.

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

Quantity-based metadata also includes calculation details such as quantity thresholds, tier config, bundle count, participating quantity, bundle price, and allocation method where applicable.

BOGO metadata is stored on both BUY qualification lines and GET/free lines. Same-line BOGO participation can include both roles in one metadata payload. The details preserve completed groups, buy/get quantities, participating BUY quantity, free quantity, unit-level BUY and GET allocation, pool type, selection rule, and promotion discount so future bill, invoice, refund, exchange, and audit flows can reconstruct historical participation without reading current promotion configuration.

Refund and exchange services continue to use historical order snapshots.

## Deferred

Phase 3 does not implement:

- promotion stacking
- buy X get Y discount
- free gift
- coupon UI/application/session storage
- promotion redemption locking or usage-limit enforcement
- merchant conflict advisory
- promotion analytics
