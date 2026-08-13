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
Storefront Customer Shopping Postal Code

Status:
Frozen for V1 location selection

Decision:
The customer-selected shopping postal code is a browsing/location preference, not a delivery restriction.

Current selected postal code is resolved centrally through:

`App\Services\Storefront\CustomerLocationService::postalCode()`

Storage:

- Laravel session key: `storefront.shopping_postal_code`
- Browser cookie: `windowshop_postal_code`
- Cookie lifetime: 30 days

Validation:

- India V1 PIN codes must be exactly 6 digits.
- Entered PIN codes must exist in active, non-deleted records from the existing `postal_codes` master table.
- Do not create a duplicate postal-code master table.

Storefront UX:

- Auto-open the location modal only when no current shopping PIN is resolved.
- Allow changing the PIN from the storefront header at any time.
- Keep storefront browsing usable even if the modal is dismissed without selection.

Future ranking:

- Same-PIN shops/products should rank first.
- Nearby PINs should rank next using postal-code proximity data.
- Farther shops/products should remain accessible and rank later.
- Do not filter products solely because they are outside the selected PIN.

Separation:

`postal_code_restrictions` remains a separate serviceability/restriction feature and must not be mixed with customer browsing location.

Reason:
This gives future product/shop queries a centralized location preference while preserving catalogue visibility and avoiding premature delivery/serviceability rules.

Alternatives Considered:
JavaScript localStorage only
Overwriting customer account address
Filtering products by selected PIN
Combining customer location with postal-code restrictions

Decision Date:
2026-08-13

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
