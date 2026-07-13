# Module List

## Current Admin Modules

- Dashboard
- Merchants and merchant shops
- Products
- Master Data
  - Product Categories
  - Shop Audiences
  - Brands
  - Product Attributes
  - Product Description Templates

## Current Merchant Modules

- Merchant authentication
- Merchant dashboard
- Profile and business details
- Shop selector
- My Shops
  - List shops with server-side Laravel pagination
  - Add shop
  - View shop
  - Edit shop
  - Activate eligible shop as current working shop

## Category Modules

Product Categories is the only category master module.

Removed modules:

- Shop Categories
- Shop-category-to-product-category mapping

## Merchant Shop Rules

Merchants can add shops from the merchant area. During creation they choose a Shop Type from active root Product Categories and choose `active` or `inactive` status. Merchants can edit their own shops and can switch eligible active shops. Merchants cannot delete shops; delete remains admin-only.

## Product Rules

Admin product creation uses a quick-create flow followed by a tabbed edit screen: Basic Information, Attributes, Variants, Images, Pricing, Inventory, Description, and SEO. Product Category selection is filtered by the selected shop's Shop Type. Root categories are shown for context but are disabled; only active leaf categories under the shop type are selectable.
