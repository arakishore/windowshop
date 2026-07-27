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
- Merchant customer management with merchant scoping, search, create/view/edit, soft delete, bulk delete, mobile lookup, summary, and order history.
- Customer addresses CRUD with shipping/billing defaults and location lookups.
- Merchant settings foundation using generic grouped settings with typed values, initializer, seeder, and settings UI.
- Admin global settings foundation using generic grouped settings with regional and currency settings.
- Static JSON catalogues for timezones and currencies with reusable catalog readers.
- POS receipt settings for shop, customer, cashier, GST, tax, barcode, QR, SKU, HSN, footer, and return policy display.
- Merchant payment method settings applied to POS checkout, including Cash, UPI, Card, and Credit.
- Merchant Sales section with POS sales history, sale detail, refund screen, and completed refund records.
- Merchant return reason management with default seeded return reasons for new and existing merchants.
- POS refund processing with line-level refundable quantities, optional restock override, refund totals, payment status updates, and refund status history.
- POS quick-add customer flow from the customer modal with mobile-only creation, duplicate mobile reuse, and auto-selection for the current cart.
- Customer list order-count sorting.
- POS Exchange V1 with dedicated exchange records, replacement order creation, historical returned-value calculation, exchangeable quantity limits, settlement tracking, stock updates, and printable exchange receipt.
- Exchange replacement selection now uses POS-style barcode/SKU/name search instead of loading product dropdowns.
- Merchant POS setting for Exchange replacement selection: Search/Scan only, Dropdown only, or Both.
- Refund and exchange line stock controls now use positive `Restock` wording instead of negative `Do NOT restock` wording.
- Exchange returned-value calculation is isolated and tax-aware; replacement order operational paid amount is excluded from sales/collection reporting.
- Documented that Exchange is a separate workflow/policy concept, not a refund/return reason.
- Exchange screen now shows original MRP, selling price, and paid amount per item beside exchange quantity.
- Exchange screen now includes a static help modal explaining the exchange flow, example, and non-restock guidance.

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
- Merchant customer status controls are hidden from merchant UI; customer removal is handled through soft delete while login/account state remains owned by `users`.
- Customer list now shows mobile number in one line, removes the email column, and supports order-count sorting.
- Customer create/edit form now uses horizontal Bootstrap form layout and hides generated customer code on create.
- POS More Actions dropdown now closes on outside click, Escape, and action selection.
- POS receipt success modal uses shared currency formatting to avoid checkout-success JavaScript errors.
- Exchange is handled as a separate sales action and hidden from return-reason defaults/listing to avoid mixing refunds with item replacement.

### Removed

- Removed separate Shop Categories module and `shop_categories` table.
- Removed shop-category-to-product-category mapping module and table.
- Removed obsolete merchant settings including product search mode, cart auto-clear toggle, cash rounding enable/precision, receipt logo/header text, product barcode type, default product visibility, and bank transfer payment option.
- Removed merchant-facing customer active/inactive filter, actions, badge, and profile controls.

### Security

- Defined session tracking, permanent login-attempt history, and secure authentication baselines.
