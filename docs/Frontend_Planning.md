# Frontend Planning

## Purpose

This document tracks the customer-facing frontend plan for WindowShop.

The main goal of building the frontend is discovery: once customers can browse, select variants, use promotions, add to cart, and draft checkout, we will understand which catalogue, pricing, promotion, delivery, and customer fields are truly needed.

## Initial Frontend Scope

Start with a simple customer storefront:

1. Shop home
2. Category listing
3. Product listing
4. Product detail
5. Cart
6. Checkout draft
7. Order confirmation
8. Customer login/register later if needed

The first version should be usable enough to expose missing fields and business rules. It does not need a full marketplace, wallet, loyalty, or advanced promotions engine on day one.

## Product Display Fields

Likely product-level fields needed for the frontend:

- Product name
- Product images
- Category
- Brand
- Short description
- Full description
- Variant attributes such as color, size, material, sleeve, fit, pattern, and occasion
- MRP
- Selling price
- Discount display
- Stock or availability text
- Featured flag
- Sort order
- SEO title
- SEO description
- Return/exchange policy text
- Delivery or pickup availability

## Variant Fields

Likely variant-level fields needed for customer selection:

- SKU
- Barcode, mostly internal/POS
- MRP
- Selling price
- Stock quantity
- Customer availability status
- Attribute values
- Image mapping by color or variant
- Minimum quantity
- Maximum quantity
- Quantity step, for future decimal or loose products

## Checkout Fields

Likely checkout fields:

- Customer name
- Customer mobile
- Customer email, optional for V1
- Address
- Delivery method
- Payment method
- Coupon or promo code
- Order notes
- Delivery charge
- Taxes
- Grand total

## Promotion Types To Consider

Possible promotion types:

- Product discount: fixed amount or percentage
- Category discount
- Brand discount
- Cart-level coupon
- Minimum order discount
- Buy X get Y
- Free delivery above amount
- First order discount
- Festival or season sale
- Featured products
- Promoted products
- Clearance sale
- Bundle offer

## Recommended V1 Promotions

Start simple:

- Product MRP vs selling price
- Featured products
- Coupon code with percentage or fixed discount
- Free delivery above order amount

Avoid building the full promotions engine too early. The first frontend should reveal whether merchants actually need category, brand, bundle, first-order, or seasonal promotion rules.

## Discovery Checklist

While building the frontend, record gaps in this checklist:

- Missing product display fields
- Missing image or variant mapping
- Missing promotion rules
- Missing customer checkout fields
- Missing delivery settings
- Missing SEO/meta data
- Missing stock or availability behavior
- Missing customer account requirements
- Missing order confirmation or receipt behavior

## Implementation Notes

- Existing POS behavior should remain separate from customer storefront behavior unless a shared service already exists.
- Product availability for customers should use the customer availability resolver/guard, not POS stock rules.
- Historical orders must continue to read saved snapshots instead of recalculating from current product or promotion data.
- Promotion rules should be server-enforced. Frontend display is only a preview of server-authoritative pricing.
- Any new frontend-specific assumptions should be documented here before or during implementation.

## Open Questions

- Should storefront be shop-specific first, or marketplace/multi-shop first?
- Should guest checkout be allowed in V1?
- Which payment methods should customer checkout support first?
- Should delivery charges be merchant-configured, distance-based, pincode-based, or manual for V1?
- Should coupons be global admin-controlled, merchant-controlled, or both?
- Should variant images be required for color variants?
