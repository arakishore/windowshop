# Product Merchandising

## Featured Products Foundation

Featured products provide a reusable merchandising flag for future website, mobile app, marketplace, and optional POS quick-pick experiences.

Fields on `products`:

- `sort_order`
- `is_featured`
- `featured_from`
- `featured_until`

`featured_sort_order` is intentionally not used. Featured products reuse the product's existing `sort_order`, then `product_name`, then `id` for stable display ordering.

Current featured rule:

- `is_featured = true`
- `featured_from` is `null` or less than or equal to the effective time
- `featured_until` is `null` or greater than or equal to the effective time

Date boundaries are inclusive. A product with `is_featured = false` is never currently featured, even if its dates are active.

Disabling a featured product preserves `featured_from` and `featured_until`, so merchants can pause a schedule without losing it.

Bulk actions:

- Mark Featured sets `is_featured = true`
- Remove Featured sets `is_featured = false`
- Both preserve existing featured dates
- Neither changes `sort_order`

Future channels should use `Product::currentlyFeatured()` and `Product::featuredOrder()` instead of duplicating date logic. Channel payloads can expose `is_featured`, `featured_from`, `featured_until`, `is_currently_featured`, and `sort_order`.

Best seller, trending, new arrival, sponsored placement, coupons, and promotions remain separate future concepts.
