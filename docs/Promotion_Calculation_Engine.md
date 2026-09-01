# Promotion Calculation Engine

## Phase 3A/3B/3C Scope

Phase 3A added the first reusable runtime promotion calculation engine for WindowShop. Phase 3B extends that same engine with quantity-based automatic rewards.
Phase 3C adds automatic Buy X Get Y Free, Buy X Get Y at Discount, and Free Gift runtime support.

Supported automatic promotion rewards:

- Percentage Discount
- Fixed Amount Discount
- Fixed / Special Price
- Quantity Discount
- Fixed Bundle Price
- Buy X Get Y Free
- Buy X Get Y at Discount
- Free Gift
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

Quantity-based promotion templates do not all share the same decimal quantity rule. `quantity_discount` and `tier_pricing` use the full eligible quantity. `fixed_bundle_price`, `buy_x_get_y_free`, and `buy_x_get_y_discount` remain discrete-unit based and only complete whole units participate.

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

### Buy X Get Y at Discount

`buy_x_get_y_discount` reuses the same BUY/GET target matching, conceptual whole-unit allocation, cheapest GET selection, repeated group handling, partial-overlap safeguards, and group-safe conflict checks as Buy X Get Y Free.

The current V1 template and merchant form support percentage discounts on rewarded GET units through `value_percent`. Fixed amount GET discounts are not currently exposed by the template configuration.

Behavior:

- Same-pool, different-pool, and partial-overlap group counts follow the same rules as Buy X Get Y Free.
- Each rewarded GET whole unit receives `value_percent` off its base unit price.
- The calculated discount is capped at the GET unit price.
- If the configured value produces no customer benefit, no participation or discount is applied.
- A Buy X Get Y Free promotion and a Buy X Get Y at Discount promotion compete by actual calculated customer benefit, then normal priority/id tie-breaks.

### Tier Pricing

`tier_pricing` uses volume pricing. The engine selects the greatest valid tier whose `min_quantity` is less than or equal to the line's full eligible quantity.

Once a tier matches:

- The matched tier unit price applies to the full eligible quantity, including decimal quantity.
- The engine does not use graduated tier pricing.
- Tiers with invalid values or tier prices greater than/equal to the base unit price produce no discount.

Example for a Rs. 1,000 item with a 3+ tier at Rs. 900: quantity 3.75 has a Rs. 3,750 base subtotal, Rs. 375 discount, and Rs. 3,375 final pre-tax subtotal.

### Free Gift

`free_gift` gives one selected gift variant when the shop order reaches a configured eligible subtotal threshold.

V1 rules:

- One Free Gift promotion generates exactly one gift variant.
- Gift quantity is always 1.
- A Free Gift promotion can apply at most once per shop order.
- Repeated threshold gifts are not supported; Rs. 4,500 on a Rs. 2,000 threshold still generates one gift.
- The gift is virtual in the cart and is not persisted to `cart_items`.
- Checkout recreates the gift from authoritative current promotion, product, variant, selling price, and stock data.
- The generated order item keeps the real variant selling price and receives a full promotion discount.
- Coupon Free Gift runtime remains deferred.
- POS automatic Free Gift application remains disabled.

Qualification uses the combined subtotal of eligible paid lines for the current shop, before promotion discounts. It is not evaluated independently per line. Eligible targets use the existing target types: all, product, variant, category, brand, and collection.

Gift targets are stored with `target_role = gift`, `target_type = variant`, and `target_id = product_variant_id`. Legacy product-only gift targets are not silently resolved to a variant; merchants must choose an explicit gift variant to complete setup.

Free Gift is treated as a group promotion for V1 no-stacking. The qualifying paid lines are promotion participants even though their monetary discount is zero. The generated gift and its qualifying-line participation apply atomically: if the qualifying lines lose conflict resolution to a better promotion, no gift is generated. Free Gift conflict benefit is the actual value of the generated gift discount. Ties use the standard priority and promotion id ordering.

If multiple Free Gift promotions qualify on overlapping paid lines, the best valid promotion wins by gift benefit, priority, then promotion id. Genuinely non-overlapping Free Gift groups may both apply if their qualifying paid lines do not participate in another winning promotion.

Gift stock is not reserved while the gift is virtual in the cart. During calculation the gift is shown only when the configured gift variant is active, sellable, purchasable, in stock, and belongs to the same shop. During checkout the gift variant is locked and stock is validated before creating the order item. If the gift is no longer available, it is not created and its qualifying-line metadata is removed from the authoritative order snapshot.

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

Generated Free Gift rows are derived from `PromotionCalculationResult`. Cart totals include the gift base value and the matching full promotion discount, so the customer payable subtotal is not inflated.

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

Buy-X-Get-Y metadata is stored on both BUY qualification lines and GET reward lines. Same-line participation can include both roles in one metadata payload. The details preserve completed groups, buy/get quantities, participating BUY quantity, rewarded GET quantity, free quantity for free offers, reward value/unit, unit-level BUY and GET allocation, pool type, selection rule, and promotion discount so future bill, invoice, refund, exchange, and audit flows can reconstruct historical participation without reading current promotion configuration.

Free Gift metadata is stored on both the generated gift order item and the qualifying paid lines. The gift line stores role `gift`, `generated_by_promotion = true`, gift product/variant ids, gift quantity, minimum eligible subtotal, eligible subtotal, original unit price, full promotion discount, and qualifying line snapshots. Qualifying paid lines store role `qualifying`, `generated_by_promotion = false`, the gift variant id, gift quantity, threshold, eligible subtotal, and their qualification line snapshot.

Refund and exchange services continue to use historical order snapshots.

## Deferred

Phase 3 does not implement:

- promotion stacking
- coupon UI/application/session storage
- promotion redemption locking or usage-limit enforcement
- merchant conflict advisory
- promotion analytics
- customer gift choice
- multiple gifts per promotion
- repeated Free Gift thresholds
- Free Gift refund/exchange business rules
