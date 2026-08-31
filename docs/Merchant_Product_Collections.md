PHASE 1
Collections
   ↓
PHASE 2
Promotion Templates
   ↓
PHASE 3
Promotion Core
(promotions / conditions / rewards /
targets / coupons)
   ↓
PHASE 4
Promotion Calculation Engine
   ↓
PHASE 5
Promotion Allocation Engine
   ↓
PHASE 6
Cart Integration + Coupons
   ↓
PHASE 7
Checkout + Usage Tracking
   ↓
PHASE 8
Order Promotion Snapshots
   ↓
PHASE 9
Refund/Exchange Policy Override
   ↓
PHASE 10
Offer Landing Page
   ↓
PHASE 11
Banner Integration
   ↓
PHASE 12
Merchant UI
   ↓
LATER
POS integration
=======================
# Merchant Product Collections and Promotions

Merchant product collections are shop-scoped product groups for reusable merchandising. They are separate from catalogue categories and do not affect pricing, stock, tax, checkout, orders, banners, refunds, exchanges, coupons, or promotions by themselves.

## Collection Tables

- `collections`: one merchant-created collection for one shop.
- `collection_products`: product-level membership pivot.

Products can belong to multiple collections. A product can appear only once inside the same collection. Collection assignment is limited to products from the same shop as the collection.

## Ownership

Collections store both `merchant_id` and `shop_id`, matching product/banner ownership patterns. Merchant routes always resolve the active shop and reject collections or products from any other shop.

## Status

Collections support only:

- `active`
- `inactive`

Promotion lifecycle states such as draft, scheduled, and expired are intentionally excluded from collections.

## Future Use

Future promotions can reference a collection through a target record such as:

- `target_type = collection`
- `target_id = collections.id`

## Promotion Concepts

Product categories are permanent catalogue structure, such as Kurtis, Shirts, Jeans, and Dresses.

Collections are merchant-created reusable groups, such as Diwali Sale, 50% OFF, Buy 1 Get 1, and Clearance Sale.

Promotions are the source of truth for offer business rules. Merchant UI may call these offers, but internal storage uses `promotions`.

Activation type is stored on the promotion:

- `automatic`
- `coupon`

Coupon is an activation method of a promotion, not a separate promotion engine.

## Promotion Template System

`promotion_templates` stores global reference templates available to all merchants. Templates are not shop-owned and do not create merchant promotions automatically.

Seeded V1 templates:

- `percentage_discount`: Percentage Discount, for example 20% OFF up to Rs. 300.
- `fixed_discount`: Fixed Amount Discount, for example Rs. 500 OFF.
- `fixed_price`: Special / Fixed Price, for example Promotion price Rs. 799.
- `fixed_bundle_price`: Any X Items for Rs. Y, for example Any 10 for Rs. 5,000.
- `buy_x_get_y_free`: Buy X Get Y Free, for example Buy 1 Get 1 Free.
- `buy_x_get_y_discount`: Buy X Get Y at Discount, for example Buy 2 Shirts, get 1 Trouser at 50% OFF.
- `quantity_discount`: Quantity Discount, for example Buy 3+ and get 10% OFF.
- `tier_pricing`: Tier / Bulk Pricing, for example 1 = Rs. 500 each, 3+ = Rs. 450 each, 10+ = Rs. 400 each.
- `free_gift`: Free Gift, for example Spend Rs. 2,000 and get Product X free.

## Promotion Foundation Tables

- `promotions`: shop-scoped merchant offer record with status, activation, validity, limits, combinability, priority, and refund/exchange policy override fields.
- `promotion_conditions`: predefined condition rows such as minimum quantity and minimum eligible subtotal.
- `promotion_rewards`: template-specific reward configuration such as percentage, fixed amount, fixed price, bundle price, buy/get quantities, tiers, and metadata.
- `promotion_targets`: target rows with role and type.
- `promotion_coupons`: coupon records scoped by shop and promotion.
- `promotion_redemptions`: future redemption foundation for usage limits and order/customer linkage.

Stored promotion statuses are:

- `draft`
- `active`
- `inactive`

Scheduled and expired are derived from an active promotion's `starts_at` and `ends_at` values.

## System Starter Offers

`promotions.origin` identifies how an offer was created:

- `system`: generated starter offer for a shop.
- `merchant`: merchant-created offer.

Starter offers are created once for each active global promotion template in each shop. They are stored as inactive promotions with no start or end dates, can be edited and activated by merchants, and are protected from deletion. Re-running the starter seeder or shop initializer creates only missing starters and does not overwrite merchant edits to existing starter offers.

Complete starter defaults include percentage discount, fixed discount, and quantity discount offers for all products. Templates that need specific merchant configuration, such as fixed price, bundle price, buy/get targets, tier prices, or gift products, remain inactive and show a derived `Needs Setup` state until the required reward, condition, and target configuration is complete.

## Target Roles and Types

Target roles:

- `eligible`
- `buy`
- `get`
- `gift`

Target types:

- `all`
- `product`
- `variant`
- `category`
- `brand`
- `collection`

Examples:

- 50% OFF Collection: `eligible` target with `collection`.
- 20% OFF all Levi's products: `eligible` target with `brand`.
- Any 10 for Rs. 5,000: `eligible` target plus `fixed_bundle_price` reward.
- Buy 1 Get 1: `buy` and `get` targets plus buy/get quantities.
- Buy 2 Shirts, Get 1 Trouser 50% OFF: `buy` category target for Shirts and `get` category target for Trousers.
- Buy 2 Brand A items, Get 1 Brand B item at 50% OFF: `buy` brand target and `get` brand target.

Targets are validated against the promotion shop where ownership applies. `all` targets use a null `target_id`.

Brand records are global master data, but the merchant promotion selector only exposes active, non-deleted brands represented by products in the active shop. A merchant cannot configure a promotion for a global brand that has no products in the active shop.

## Refund / Exchange Overrides

Promotion policy modes:

- `inherit`
- `allowed`
- `not_allowed`

If refund or exchange is `allowed`, the matching window days field is required. If the mode is `inherit` or `not_allowed`, the window days value is stored as null.

Future intended precedence:

Shop Policy -> Product Override -> Promotion Override

Promotion override applies only to items participating in that promotion. Historical resolved values will later be snapshotted on order items.

## Deferred Work

The foundation intentionally does not implement cart promotion evaluation, automatic cart discounts, customer coupon entry, checkout recalculation, tax integration, promotion pricing services, discount allocation, usage reservation/locking, order item promotion allocation or snapshots, refund/exchange calculation, offer landing pages, banner links, promotion banners, social sharing, delivery promotions, or POS promotion pricing.
====================
