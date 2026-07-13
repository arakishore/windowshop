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

### Changed

- Established database comments for non-obvious business fields and allowed values.
- Established PHP 8.2 backed enums as the application convention for enum-like values.
- Renamed the living V1 planning document to `00_Project_Backlog.md`.
- Consolidated category architecture into `product_categories`; root categories are Shop Types and child/leaf categories classify products.
- Replaced shop category usage with `root_product_category_id` on shops and products.
- Product Category selection is now constrained by the selected shop's Shop Type.
- Product Brand is optional in admin product flows.
- Product attribute variant behavior now depends on category mapping, not only selection type.

### Removed

- Removed separate Shop Categories module and `shop_categories` table.
- Removed shop-category-to-product-category mapping module and table.

### Security

- Defined session tracking, permanent login-attempt history, and secure authentication baselines.
