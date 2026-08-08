--------------------------------------------------

## Decision

Feature:
Storefront Blade Conversion

Status:
Frozen

Decision:
WindowShop V1 uses one common storefront Blade layout:

`resources/views/storefront/layouts/app.blade.php`

All shop, category, banner, merchant, product, and branding variation will be supplied dynamically later without creating category-specific homepage layouts.

Reason:
Keeps the storefront maintainable and consistent while allowing future marketplace data to change content without duplicating template structure.

Alternatives Considered:
Category-specific Blade homepages
Shop-type-specific storefront layouts
Using the static HTML file directly

Decision Date:
2026-08-07

--------------------------------------------------

## Decision

Feature:
WindowShop Storefront Navigation V1

Status:
Frozen

Decision:
Storefront navigation V1 uses the existing `product_categories` table as the only menu source.

No separate menu builder, `storefront_menu_groups`, or custom storefront menu table will be created for V1.

Main navigation:

- Home
- Categories
- Shops
- Brands
- Offers
- New Arrivals
- Contact

Mega menu structure:

- Level 1 root categories render as mega menu columns.
- Level 2 child categories render under each root category.
- Level 3 and deeper categories do not render in the mega menu. They are reserved for category pages.

Category rules:

- Load active categories only.
- Exclude soft-deleted categories.
- Order by `sort_order`, then `name`.

Merchant store navigation:

- Uses the same storefront navigation layout.
- Shows only root categories and child categories that contain active products for that merchant shop.

Reason:
This keeps navigation simple, database-driven, and aligned with the existing catalogue hierarchy while avoiding duplicate menu maintenance.

Alternatives Considered:
Dedicated menu builder
`storefront_menu_groups`
WordPress-style custom menu system
Grouped navigation module such as Fashion containing Apparel and Footwear

Deferred:
Advanced grouped navigation may be added later as a separate module if business requirements require it, without changing the V1 storefront architecture.

Decision Date:
2026-08-07
