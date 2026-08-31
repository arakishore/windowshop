# Merchant Product Collections

Merchant product collections are shop-scoped product groups for reusable merchandising. They are separate from catalogue categories and do not affect pricing, stock, tax, checkout, orders, banners, refunds, exchanges, coupons, or promotions by themselves.

## Tables

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

Promotion rules, coupon behavior, offer pages, banner integration, pricing changes, and refund/exchange overrides are deferred to later phases.
