# Changelog

All notable changes to WindowShop are documented here.

This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) conventions. Versions should use semantic versioning once public releases begin.

## [Unreleased]

### Added

- Initial Laravel 12 project structure.
- Extended users schema with public UUID, mobile verification, account status, login metadata, and soft deletes.
- Administrator profile schema with audit fields.
- User session tracking schema.
- Permanent login-attempt logging schema.
- Initial architecture, database, security, audit, integration, and testing documentation.
- UI/UX, business rules, performance, deployment, and master data documentation.
- Architecture Decision Record catalogue covering authentication, users, UUIDs, database standards, sessions, soft deletes, statuses, and audit logging.
- Admin product quick-create flow and tab-based edit screen.
- Product Description Template integration for product creation and Description actions.
- Merchant-side Add Shop module with Shop Type and active/inactive status selection.
- Category-level product attribute mappings with an `is_variant` flag.
- Admin Product Attributes tab for category-based attribute selection.
- Merchant product management with variants, images, barcode generation, archive/restore, duplication, bulk actions, Description, and SEO actions.
- Merchant POS with active-shop product grid, barcode search, auto-add-to-cart, cart quantity controls, held carts, recent sales, and checkout.
- POS customer selection modal with customer search, delivery address selection, and address creation.
- POS line item discounts and order discounts with percent/amount modes, validation, live previews, discount badges, and receipt totals.
- Centralized POS discount and cash rounding services.
- Order foundation with orders, order items, order totals, status histories, customer/address snapshots, discount fields, rounding adjustment, and payment status resolution.
- Merchant customer management with merchant scoping, search, status filter, create/view/edit, activate/deactivate, soft delete, bulk actions, mobile lookup, summary, and order history.
- Customer addresses CRUD with shipping/billing defaults and location lookups.
- Merchant settings foundation using generic grouped settings with typed values, initializer, seeder, and settings UI.
- Admin global settings foundation using generic grouped settings with regional and currency settings.
- Static JSON catalogues for timezones and currencies with reusable catalog readers.
- POS receipt settings for shop, customer, cashier, GST, tax, barcode, QR, SKU, HSN, footer, and return policy display.
- Merchant payment method settings applied to POS checkout, including Cash, UPI, Card, and Credit.

### Changed

- Established database comments for non-obvious business fields and allowed values.
- Established PHP 8.2 backed enums as the application convention for enum-like values.
- Renamed the living V1 planning document to `00_Project_Backlog.md`.
- Consolidated category architecture into `product_categories`; root categories are Shop Types and child/leaf categories classify products.
- Replaced shop category usage with `root_product_category_id` on shops and products.
- Product Category selection is now constrained by the selected shop's Shop Type.
- Product Brand is optional in admin product flows.
- Product attribute variant behavior now depends on category mapping, not only selection type.
- POS currency display now uses global admin currency settings.
- POS payment dropdown and checkout validation now use merchant payment settings.
- POS cash rounding now affects displayed and saved payable totals.
- DEV-stage settings defaults and migrations are kept clean instead of preserving obsolete settings.

### Removed

- Removed separate Shop Categories module and `shop_categories` table.
- Removed shop-category-to-product-category mapping module and table.
- Removed obsolete merchant settings including product search mode, cart auto-clear toggle, cash rounding enable/precision, receipt logo/header text, product barcode type, default product visibility, and bank transfer payment option.

### Security

- Defined session tracking, permanent login-attempt history, and secure authentication baselines.
