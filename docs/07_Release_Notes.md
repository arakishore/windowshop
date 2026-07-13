# Release Notes

## Unreleased

### Category Architecture

- Consolidated category management into `product_categories`.
- Removed the separate Shop Categories module.
- Removed shop-category-to-product-category mapping.
- Root Product Categories now represent Shop Type.
- Child/leaf Product Categories now represent product classification.
- Shops store Shop Type in `root_product_category_id`.
- Products store both `root_product_category_id` and exact `product_category_id`.

### Admin Product Flow

- Added admin product quick create.
- Added tab-based product edit screen: Basic Information, Attributes, Variants, Images, Pricing, Inventory, Description, SEO.
- Made product Brand optional.
- Product Category dropdown shows hierarchy context, disables roots, and allows only valid leaf categories under the selected shop type.
- Added server-side validation for root/descendant/leaf category rules.
- Integrated Product Description Templates into product creation and Description actions; SEO fields are managed in a separate SEO tab.

### Product Attributes

- Added category-level attribute group mapping through `product_category_attribute_groups`.
- Added `is_variant` on the category mapping so variant generation can use only configured variant attributes.
- Kept `product_attribute_groups.selection_type` generic and independent from variant generation.
- Seeded Apparel so Color and Size are variant attributes while descriptive attributes remain non-variant.

### Merchant Shops

- Added merchant-side Add Shop module.
- Merchants can create shops with Shop Type, contact, address, logo/banner, and `active` or `inactive` status.
- Merchants can edit their own shop details and switch eligible shops.
- Merchant delete is not exposed; shop deletion remains admin-only.

### Verification

- Current full test suite passes: 57 tests, 268 assertions.
