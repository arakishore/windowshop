# WindowShop Database Architecture

## Database Phase Roadmap

### Phase 3 (V1): System Foundation

- `system_setting_groups`
- `system_settings`
- `system_audit_logs`

Language, currency, and timezone are stored as values in `system_settings` during Version 1.

### Shop and Product Category Usage

`product_categories` is the single category master. Root categories, where `parent_id` is `NULL`, represent shop or business types such as `Apparel`, `Footwear`, or `Mobile & Electronics`. Child and leaf categories represent product classification and are used when assigning a category to an individual product.

Shops store their shop type in `shops.root_product_category_id`, which must reference an active root product category. Products store both `products.root_product_category_id`, copied from the selected shop, and `products.product_category_id`, the exact selectable product category under that root. The product root category is not independently editable.

There is no separate `shop_categories` table and no shop-category-to-product-category mapping table. The only category CRUD is Product Categories. In V1, the product category hierarchy is limited to three levels. Product assignment uses active leaf categories under the selected shop type; root categories are visible for context but are not selectable as exact product categories.

Merchant-created shops also use `shops.root_product_category_id` as Shop Type. Merchants may choose `active` or `inactive` when adding or editing their own shops. Shop deletion remains an admin-only operation.

### Product Attribute Category Mapping

`product_category_attribute_groups` maps attribute groups to product categories. The mapping stores category-specific behavior:

- `product_category_id`
- `product_attribute_group_id`
- `is_required`
- `is_variant`
- `sort_order`

`product_attribute_groups.selection_type` remains generic and describes whether a group accepts one or multiple values. `product_category_attribute_groups.is_variant` decides whether selected values from that group generate variants for a specific category. For example, Color and Size generate variants for Apparel, while Material and Sleeve remain descriptive attributes.

### Future Expansion

- `system_languages`
- `system_currencies`
- `system_timezones`

These master tables are intentionally deferred until WindowShop supports multiple operating countries or regions.

### Phase 4 (V1): Merchant Foundation

- `merchant_profiles`
- `merchant_addresses`
- `merchant_documents`
- `merchant_bank_accounts`
- `merchant_verifications`

Merchant foundation tables store business profile, address, KYC document, payout bank, and verification-history data only. Merchant identity and access remain based on `users` plus `auth_roles`; no separate merchant user table is created.

## Authentication & Authorization Database Standards

### Table Naming

Authentication and authorization tables must use the `auth_` prefix. This makes security-owned schema easy to identify and keeps it separate from business modules.

The `users` table is the only exception. It remains named `users` to follow Laravel conventions and preserve compatibility with Laravel's standard authentication ecosystem.

### Planned Tables

| Table | Purpose | Phase |
|---|---|---|
| `users` | Canonical identity and authentication record for every user type | Core |
| `auth_roles` | Defines authorization roles such as Admin, Merchant, Customer, and Staff | Core |
| `auth_permissions` | Defines individual application capabilities | Core |
| `auth_role_permissions` | Assigns permissions to roles | Core |
| `auth_user_roles` | Assigns one or more roles to users | Core |
| `auth_user_sessions` | Tracks active and historical authenticated sessions | Core |
| `auth_user_login_history` | Permanently records successful and failed login attempts | Core |
| `auth_password_reset_tokens` | Stores expiring password-reset tokens | Core |
| `auth_email_verification_tokens` | Stores expiring email-verification tokens | Core |
| `auth_mobile_verification_tokens` | Stores expiring mobile-verification tokens | Core |
| `auth_two_factor_codes` | Supports two-factor authentication challenges | Future |
| `auth_api_tokens` | Supports API and personal-access tokens | Future |

### Design Principles

- WindowShop uses one `users` table for all user types.
- Roles determine whether a user operates as an Admin, Merchant, Customer, Staff member, or another approved role.
- Separate user tables must not be created for individual roles.
- Foreign keys that reference an authenticated identity use `user_id`.
- Business modules reference `users.id` instead of duplicating names, credentials, verification data, or other authentication fields.
- Authentication and authorization remain independent from merchant, customer, shop, order, payment, and other business modules.
- Role-specific profile tables may store business profile data, but authentication credentials remain owned by `users`.

## Implemented Authentication Schema

Data types below reflect the current Laravel migrations for MySQL. `string` columns use `VARCHAR`; Laravel timestamps are nullable unless stated otherwise.

### `users`

**Purpose:** Canonical identity and credential record shared by every user type.

| Column | Type | Nullable | Default | Comment/notes |
|---|---|---:|---|---|
| `id` | BIGINT UNSIGNED | No | Auto increment | Primary key |
| `uuid` | CHAR(36) | No | — | Public identifier exposed in URLs and APIs |
| `name` | VARCHAR(255) | No | — | Display/full name |
| `email` | VARCHAR(255) | No | — | Login/contact email |
| `mobile` | VARCHAR(20) | Yes | `NULL` | Login/contact mobile |
| `email_verified_at` | TIMESTAMP | Yes | `NULL` | Email verification time |
| `mobile_verified_at` | TIMESTAMP | Yes | `NULL` | Mobile verification time |
| `password` | VARCHAR(255) | No | — | Hashed password |
| `status` | VARCHAR(30) | No | `active` | `active,inactive,suspended,deleted` |
| `last_login_at` | TIMESTAMP | Yes | `NULL` | Last successful login datetime |
| `last_login_ip` | VARCHAR(45) | Yes | `NULL` | IPv4 or IPv6 address |
| `remember_token` | VARCHAR(100) | Yes | `NULL` | Laravel Remember Me token |
| `created_at`, `updated_at` | TIMESTAMP | Yes | `NULL` | Laravel timestamps |
| `deleted_at` | TIMESTAMP | Yes | `NULL` | Soft delete |

- **Unique constraints:** `uuid`, `email`, `mobile`.
- **Indexes:** `status`; unique constraints also provide indexes.
- **Foreign keys:** None.
- **Relationships:** One user may have roles, sessions, login history, verification/reset records, and role-specific business profiles.
- **Soft deletes:** Yes.
- **Notes:** `users` intentionally keeps Laravel's conventional name. The same migration also retains Laravel's default `password_reset_tokens` and `sessions` tables.

### `auth_roles`

**Purpose:** Defines assignable authorization roles.

| Column | Type | Nullable | Default | Comment/notes |
|---|---|---:|---|---|
| `id` | BIGINT UNSIGNED | No | Auto increment | Primary key |
| `uuid` | CHAR(36) | Yes | `NULL` | Public identifier exposed in URLs and APIs |
| `name` | VARCHAR(100) | No | — | Human-readable role name |
| `slug` | VARCHAR(100) | No | — | Stable machine-readable role key |
| `description` | TEXT | Yes | `NULL` | Role description |
| `status` | VARCHAR(30) | No | `active` | `active,inactive,deleted` |
| `created_at`, `updated_at` | TIMESTAMP | Yes | `NULL` | Laravel timestamps |
| `deleted_at` | TIMESTAMP | Yes | `NULL` | Soft delete |

- **Unique constraints:** `uuid`, `name`, `slug`.
- **Indexes:** `status`; unique constraints also provide indexes.
- **Foreign keys:** None.
- **Relationships:** Many-to-many with users through `auth_user_roles`; many-to-many with permissions through `auth_role_permissions`.
- **Soft deletes:** Yes.
- **Notes:** Default role slugs are `super_admin`, `admin`, `merchant`, and `customer`.

### `auth_permissions`

**Purpose:** Defines granular capabilities assignable to roles.

| Column | Type | Nullable | Default | Comment/notes |
|---|---|---:|---|---|
| `id` | BIGINT UNSIGNED | No | Auto increment | Primary key |
| `uuid` | CHAR(36) | Yes | `NULL` | Public identifier exposed in URLs and APIs |
| `name` | VARCHAR(150) | No | — | Human-readable permission name |
| `slug` | VARCHAR(150) | No | — | Stable machine-readable capability |
| `module` | VARCHAR(100) | Yes | `NULL` | Optional module grouping |
| `description` | TEXT | Yes | `NULL` | Permission description |
| `status` | VARCHAR(30) | No | `active` | `active,inactive,deleted` |
| `created_at`, `updated_at` | TIMESTAMP | Yes | `NULL` | Laravel timestamps |
| `deleted_at` | TIMESTAMP | Yes | `NULL` | Soft delete |

- **Unique constraints:** `uuid`, `slug`, and composite (`module`, `name`).
- **Indexes:** `status`; unique constraints also provide indexes.
- **Foreign keys:** None.
- **Relationships:** Many-to-many with roles through `auth_role_permissions`.
- **Soft deletes:** Yes.
- **Notes:** MySQL permits multiple `NULL` module values in a composite unique index; `slug` remains globally unique.

### `auth_role_permissions`

**Purpose:** Assigns permissions to roles.

| Column | Type | Nullable | Default | Comment/notes |
|---|---|---:|---|---|
| `id` | BIGINT UNSIGNED | No | Auto increment | Primary key |
| `role_id` | BIGINT UNSIGNED | No | — | References `auth_roles.id` |
| `permission_id` | BIGINT UNSIGNED | No | — | References `auth_permissions.id` |
| `created_at`, `updated_at` | TIMESTAMP | Yes | `NULL` | Laravel timestamps |

- **Unique constraints:** Composite (`role_id`, `permission_id`).
- **Indexes:** Composite unique index and `permission_id`.
- **Foreign keys:** `role_id` → `auth_roles.id` ON DELETE CASCADE; `permission_id` → `auth_permissions.id` ON DELETE CASCADE.
- **Relationships:** Authorization pivot between roles and permissions.
- **Soft deletes:** No.
- **Notes:** Cascading deletion prevents orphaned assignments.

### `auth_user_roles`

**Purpose:** Assigns one or more roles to a user.

| Column | Type | Nullable | Default | Comment/notes |
|---|---|---:|---|---|
| `id` | BIGINT UNSIGNED | No | Auto increment | Primary key |
| `user_id` | BIGINT UNSIGNED | No | — | References `users.id` |
| `role_id` | BIGINT UNSIGNED | No | — | References `auth_roles.id` |
| `created_at`, `updated_at` | TIMESTAMP | Yes | `NULL` | Laravel timestamps |

- **Unique constraints:** Composite (`user_id`, `role_id`).
- **Indexes:** Composite unique index and `role_id`.
- **Foreign keys:** `user_id` → `users.id` ON DELETE CASCADE; `role_id` → `auth_roles.id` ON DELETE CASCADE.
- **Relationships:** Authorization pivot between users and roles.
- **Soft deletes:** No.
- **Notes:** User type is determined through roles rather than separate credential tables.

### `auth_user_sessions`

**Purpose:** Tracks active and historical authenticated devices/sessions.

| Column | Type | Nullable | Default | Comment/notes |
|---|---|---:|---|---|
| `id` | BIGINT UNSIGNED | No | Auto increment | Primary key |
| `uuid` | CHAR(36) | Yes | `NULL` | Public identifier exposed in URLs and APIs |
| `user_id` | BIGINT UNSIGNED | No | — | References `users.id` |
| `session_id` | VARCHAR(255) | Yes | `NULL` | Laravel session ID if available |
| `guard_name` | VARCHAR(50) | Yes | `NULL` | Auth guard used for login |
| `device_name` | VARCHAR(150) | Yes | `NULL` | Readable device/browser name |
| `device_type` | VARCHAR(50) | Yes | `NULL` | `desktop,mobile,tablet,bot,unknown` |
| `browser` | VARCHAR(100) | Yes | `NULL` | Browser name |
| `browser_version` | VARCHAR(50) | Yes | `NULL` | Browser version |
| `platform` | VARCHAR(100) | Yes | `NULL` | Operating system/platform |
| `platform_version` | VARCHAR(50) | Yes | `NULL` | Platform version |
| `ip_address` | VARCHAR(45) | Yes | `NULL` | IPv4 or IPv6 address |
| `user_agent` | TEXT | Yes | `NULL` | Raw user agent |
| `login_at` | TIMESTAMP | Yes | `NULL` | Login time |
| `last_activity_at` | TIMESTAMP | Yes | `NULL` | Last tracked activity |
| `logout_at` | TIMESTAMP | Yes | `NULL` | Logout/termination time |
| `is_current` | BOOLEAN | No | `false` | Current session for this login request |
| `is_active` | BOOLEAN | No | `true` | Whether session is currently active |
| `logout_reason` | VARCHAR(50) | Yes | `NULL` | `manual,expired,forced,password_changed,admin_terminated` |
| `created_at`, `updated_at` | TIMESTAMP | Yes | `NULL` | Laravel timestamps |
| `deleted_at` | TIMESTAMP | Yes | `NULL` | Soft delete |

- **Unique constraints:** `uuid`.
- **Indexes:** `user_id`, `session_id`, `is_active`, `last_activity_at`, (`user_id`, `is_active`), (`user_id`, `last_activity_at`).
- **Foreign keys:** `user_id` → `users.id` ON DELETE CASCADE.
- **Relationships:** Each session belongs to one user.
- **Soft deletes:** Yes.
- **Notes:** Session tracking supplements rather than replaces Laravel's session mechanism.

### `auth_user_login_history`

**Purpose:** Permanent audit-style history of login attempts and logout events.

| Column | Type | Nullable | Default | Comment/notes |
|---|---|---:|---|---|
| `id` | BIGINT UNSIGNED | No | Auto increment | Primary key |
| `uuid` | CHAR(36) | Yes | `NULL` | Public identifier exposed in URLs and APIs |
| `user_id` | BIGINT UNSIGNED | Yes | `NULL` | References `users.id` when identity is known |
| `email` | VARCHAR(255) | Yes | `NULL` | Email used during login attempt |
| `mobile` | VARCHAR(20) | Yes | `NULL` | Mobile used during login attempt |
| `guard_name` | VARCHAR(50) | Yes | `NULL` | Authentication context |
| `login_identifier` | VARCHAR(255) | Yes | `NULL` | Submitted username/email/mobile |
| `status` | VARCHAR(50) | No | `success` | `success,failed,blocked,logout` |
| `failure_reason` | VARCHAR(150) | Yes | `NULL` | `invalid_credentials,inactive_user,suspended_user,deleted_user,too_many_attempts,otp_failed,password_expired` |
| `session_id` | VARCHAR(255) | Yes | `NULL` | Associated session when available |
| `device_name` | VARCHAR(150) | Yes | `NULL` | Readable device name |
| `device_type` | VARCHAR(50) | Yes | `NULL` | `desktop,mobile,tablet,bot,unknown` |
| `browser`, `platform` | VARCHAR(100) | Yes | `NULL` | Client software/platform |
| `browser_version`, `platform_version` | VARCHAR(50) | Yes | `NULL` | Client versions |
| `ip_address` | VARCHAR(45) | Yes | `NULL` | IPv4 or IPv6 address |
| `user_agent` | TEXT | Yes | `NULL` | Raw user agent |
| `attempted_at` | TIMESTAMP | Yes | `NULL` | Attempt/event time |
| `logout_at` | TIMESTAMP | Yes | `NULL` | Logout time when applicable |
| `created_at`, `updated_at` | TIMESTAMP | Yes | `NULL` | Laravel timestamps |

- **Unique constraints:** `uuid`.
- **Indexes:** `user_id`, `email`, `mobile`, `status`, `attempted_at`, `session_id`, (`user_id`, `attempted_at`), (`status`, `attempted_at`).
- **Foreign keys:** Nullable `user_id` → `users.id` ON DELETE SET NULL.
- **Relationships:** History may belong to a user, but anonymous/failed attempts can remain unassociated.
- **Soft deletes:** No.
- **Notes:** Records are retained for audit history; secrets and raw credentials must never be stored.

### `auth_password_reset_tokens`

**Purpose:** Stores hashed password-reset tokens or OTPs.

| Column | Type | Nullable | Default | Comment/notes |
|---|---|---:|---|---|
| `id` | BIGINT UNSIGNED | No | Auto increment | Primary key |
| `uuid` | CHAR(36) | Yes | `NULL` | Public identifier exposed in URLs and APIs |
| `user_id` | BIGINT UNSIGNED | Yes | `NULL` | References `users.id` |
| `email` | VARCHAR(255) | Yes | `NULL` | Email used for password reset |
| `mobile` | VARCHAR(20) | Yes | `NULL` | Mobile used for password reset |
| `token` | VARCHAR(255) | Yes | `NULL` | Hashed reset token |
| `otp` | VARCHAR(255) | Yes | `NULL` | Hashed OTP if OTP reset is used |
| `channel` | VARCHAR(30) | No | `email` | `email,mobile,whatsapp` |
| `status` | VARCHAR(30) | No | `pending` | `pending,used,expired,revoked` |
| `requested_ip` | VARCHAR(45) | Yes | `NULL` | IPv4 or IPv6 address |
| `user_agent` | TEXT | Yes | `NULL` | Request user agent |
| `expires_at` | TIMESTAMP | Yes | `NULL` | Expiration time |
| `used_at` | TIMESTAMP | Yes | `NULL` | Consumption time |
| `created_at`, `updated_at` | TIMESTAMP | Yes | `NULL` | Laravel timestamps |
| `deleted_at` | TIMESTAMP | Yes | `NULL` | Soft delete |

- **Unique constraints:** `uuid`.
- **Indexes:** `user_id`, `email`, `mobile`, `token`, `status`, `expires_at`, (`user_id`, `status`), (`email`, `status`), (`mobile`, `status`).
- **Foreign keys:** Nullable `user_id` → `users.id` ON DELETE CASCADE.
- **Relationships:** A reset request may belong to a known user and may also retain its submitted destination.
- **Soft deletes:** Yes.
- **Notes:** Token and OTP values must be hashed; raw values exist only long enough to deliver them.

### `auth_email_verification_tokens`

**Purpose:** Stores hashed email-verification tokens or OTPs.

| Column | Type | Nullable | Default | Comment/notes |
|---|---|---:|---|---|
| `id` | BIGINT UNSIGNED | No | Auto increment | Primary key |
| `uuid` | CHAR(36) | Yes | `NULL` | Public identifier exposed in URLs and APIs |
| `user_id` | BIGINT UNSIGNED | No | — | References `users.id` |
| `email` | VARCHAR(255) | No | — | Email being verified |
| `token` | VARCHAR(255) | Yes | `NULL` | Hashed verification token |
| `otp` | VARCHAR(255) | Yes | `NULL` | Hashed OTP if OTP verification is used |
| `status` | VARCHAR(30) | No | `pending` | `pending,verified,expired,revoked` |
| `requested_ip` | VARCHAR(45) | Yes | `NULL` | IPv4 or IPv6 address |
| `user_agent` | TEXT | Yes | `NULL` | Request user agent |
| `expires_at` | TIMESTAMP | Yes | `NULL` | Expiration time |
| `verified_at` | TIMESTAMP | Yes | `NULL` | Successful verification time |
| `created_at`, `updated_at` | TIMESTAMP | Yes | `NULL` | Laravel timestamps |
| `deleted_at` | TIMESTAMP | Yes | `NULL` | Soft delete |

- **Unique constraints:** `uuid`.
- **Indexes:** `user_id`, `email`, `token`, `status`, `expires_at`, (`user_id`, `status`), (`email`, `status`).
- **Foreign keys:** `user_id` → `users.id` ON DELETE CASCADE.
- **Relationships:** Each verification record belongs to one user.
- **Soft deletes:** Yes.
- **Notes:** Only hashed tokens/OTPs are persisted.

### `auth_mobile_verification_tokens`

**Purpose:** Stores hashed mobile-verification OTPs or tokens.

| Column | Type | Nullable | Default | Comment/notes |
|---|---|---:|---|---|
| `id` | BIGINT UNSIGNED | No | Auto increment | Primary key |
| `uuid` | CHAR(36) | Yes | `NULL` | Public identifier exposed in URLs and APIs |
| `user_id` | BIGINT UNSIGNED | No | — | References `users.id` |
| `mobile` | VARCHAR(20) | No | — | Mobile being verified |
| `otp` | VARCHAR(255) | Yes | `NULL` | Hashed OTP |
| `token` | VARCHAR(255) | Yes | `NULL` | Hashed verification token if token-based verification is used |
| `status` | VARCHAR(30) | No | `pending` | `pending,verified,expired,revoked` |
| `requested_ip` | VARCHAR(45) | Yes | `NULL` | IPv4 or IPv6 address |
| `user_agent` | TEXT | Yes | `NULL` | Request user agent |
| `expires_at` | TIMESTAMP | Yes | `NULL` | Expiration time |
| `verified_at` | TIMESTAMP | Yes | `NULL` | Successful verification time |
| `created_at`, `updated_at` | TIMESTAMP | Yes | `NULL` | Laravel timestamps |
| `deleted_at` | TIMESTAMP | Yes | `NULL` | Soft delete |

- **Unique constraints:** `uuid`.
- **Indexes:** `user_id`, `mobile`, `token`, `status`, `expires_at`, (`user_id`, `status`), (`mobile`, `status`).
- **Foreign keys:** `user_id` → `users.id` ON DELETE CASCADE.
- **Relationships:** Each verification record belongs to one user.
- **Soft deletes:** Yes.
- **Notes:** OTP is deliberately not indexed; verification queries should use the user/mobile and status indexes. Only hashed values are persisted.

## Relationship Summary

```text
users
├── auth_user_roles ── auth_roles
│                       └── auth_role_permissions ── auth_permissions
├── auth_user_sessions
├── auth_user_login_history
├── auth_password_reset_tokens
├── auth_email_verification_tokens
└── auth_mobile_verification_tokens
```

## Location Master Schema

### Naming Standard

All location/reference tables use the `loc_` prefix. The canonical names are:

- `loc_regions`
- `loc_subregions`
- `loc_countries`
- `loc_states`
- `loc_cities`

The previous names `regions`, `subregions`, `mst_countries`, `mst_states`, and `mst_cities` are legacy names and must not be used by new WindowShop code.

### `loc_regions`

**Purpose:** Top-level geographic region master.

| Column | Type | Notes |
|---|---|---|
| `id` | MEDIUMINT UNSIGNED | Primary key |
| `uuid` | CHAR(36), nullable | Unique public identifier |
| `name` | VARCHAR(100) | Unique region name |
| `translations` | TEXT, nullable | Localized region names |
| `flag` | BOOLEAN, default `true` | `1=active,0=inactive` |
| `wiki_data_id` | VARCHAR(255), nullable | GeoDB/WikiData identifier |
| `created_at`, `updated_at` | TIMESTAMP, nullable | Laravel timestamps |

- **Indexes/constraints:** Unique `uuid`, unique `name`, index `flag`.
- **Soft deletes:** No, matching the legacy source schema.
- **Relationships:** Has many subregions and countries.

### `loc_subregions`

**Purpose:** Geographic subdivisions within regions.

| Column | Type | Notes |
|---|---|---|
| `id` | MEDIUMINT UNSIGNED | Primary key |
| `uuid` | CHAR(36), nullable | Unique public identifier |
| `name` | VARCHAR(100) | Subregion name |
| `translations` | TEXT, nullable | Localized subregion names |
| `region_id` | MEDIUMINT UNSIGNED | References `loc_regions.id` |
| `flag` | BOOLEAN, default `true` | `1=active,0=inactive` |
| `wiki_data_id` | VARCHAR(255), nullable | GeoDB/WikiData identifier |
| `created_at`, `updated_at`, `deleted_at` | TIMESTAMP, nullable | Lifecycle timestamps |

- **Indexes/constraints:** Unique `uuid`, unique (`region_id`, `name`), index `flag`.
- **Foreign key:** `region_id` → `loc_regions.id`, delete restricted.
- **Soft deletes:** Yes.
- **Relationships:** Belongs to a region; has many countries.

### `loc_countries`

**Purpose:** Country master with ISO, currency, calling-code, and nationality metadata.

| Column | Type | Notes |
|---|---|---|
| `id` | MEDIUMINT UNSIGNED | Primary key |
| `uuid` | CHAR(36), nullable | Unique public identifier |
| `name` | VARCHAR(100) | Country name |
| `iso3`, `numeric_code`, `iso2` | CHAR(3), CHAR(3), CHAR(2), nullable | Unique ISO/numeric codes |
| `phonecode` | VARCHAR(255), nullable | International calling code |
| `capital` | VARCHAR(255), nullable | Capital city |
| `currency` | VARCHAR(255), nullable | ISO 4217 currency code |
| `currency_name`, `currency_symbol` | VARCHAR(255), nullable | Currency display metadata |
| `region_id`, `subregion_id` | MEDIUMINT UNSIGNED, nullable | Geographic parents |
| `nationality` | VARCHAR(255), nullable | Nationality/demonym |
| `status` | BOOLEAN, default `true` | `1=active,0=inactive` |
| `created_at`, `updated_at`, `deleted_at` | TIMESTAMP, nullable | Lifecycle timestamps |

- **Indexes/constraints:** Unique `uuid`, `iso2`, `iso3`, and `numeric_code`; indexes `name` and `status`.
- **Foreign keys:** `region_id` → `loc_regions.id`; `subregion_id` → `loc_subregions.id`; deletes restricted.
- **Soft deletes:** Yes.
- **Relationships:** Belongs to a region/subregion; has many states and cities.

### `loc_states`

**Purpose:** State/province master within a country.

| Column | Type | Notes |
|---|---|---|
| `id` | MEDIUMINT UNSIGNED | Primary key |
| `uuid` | CHAR(36), nullable | Unique public identifier |
| `name` | VARCHAR(255) | State/province name |
| `country_id` | MEDIUMINT UNSIGNED | References `loc_countries.id` |
| `country_code` | CHAR(2) | ISO country code |
| `iso2` | VARCHAR(255), nullable | State/province code |
| `iso3166_2` | VARCHAR(10), nullable | ISO 3166-2 subdivision code |
| `status` | BOOLEAN, default `true` | `1=active,0=inactive` |
| `created_at`, `updated_at`, `deleted_at` | TIMESTAMP, nullable | Lifecycle timestamps |

- **Indexes/constraints:** Unique `uuid`, unique (`country_id`, `name`), indexes `status` and (`country_id`, `iso2`).
- **Foreign key:** `country_id` → `loc_countries.id`, delete restricted.
- **Soft deletes:** Yes.
- **Relationships:** Belongs to a country; has many cities.

### `loc_cities`

**Purpose:** City master within a state and country.

| Column | Type | Notes |
|---|---|---|
| `id` | MEDIUMINT UNSIGNED | Primary key |
| `uuid` | CHAR(36), nullable | Unique public identifier |
| `name` | VARCHAR(255) | City name |
| `city_code` | CHAR(3), nullable | Optional city code |
| `state_id` | MEDIUMINT UNSIGNED | References `loc_states.id` |
| `country_id` | MEDIUMINT UNSIGNED | References `loc_countries.id` |
| `created_at`, `updated_at`, `deleted_at` | TIMESTAMP, nullable | Lifecycle timestamps |

- **Indexes/constraints:** Unique `uuid`, unique (`country_id`, `state_id`, `name`), index `state_id`.
- **Foreign keys:** `state_id` → `loc_states.id`; `country_id` → `loc_countries.id`; deletes restricted.
- **Soft deletes:** Yes.
- **Relationships:** Belongs to a state and country.

### Location Relationship Summary

```text
loc_regions
├── loc_subregions
│   └── loc_countries
└── loc_countries
    └── loc_states
        └── loc_cities
```

Cities also reference their country directly for efficient country-scoped lookup and integrity.

### Version 1 Usage

- Location tables are master/reference data only.
- Version 1 registration and profile screens do not display country, state, or city selectors.
- The system assigns India, Maharashtra, and Nashik as defaults where location assignment is required.
- Future searchable dropdowns and APIs will use the same schema without database redesign.
- Initial seed scope is Asia → Southern Asia → India → Maharashtra → Nashik.

## Merchant Foundation Schema

### Naming Standard

All merchant-owned foundation tables use the `merchant_` prefix. The merchant role is represented through `users` and `auth_roles`; `merchant_profiles` stores only merchant/business details linked to one user.

### `merchant_profiles`

**Purpose:** Stores merchant/business profile information linked to a user.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED | Primary key |
| `uuid` | CHAR(36) | Unique public identifier exposed in URLs and APIs |
| `user_id` | BIGINT UNSIGNED | References `users.id`; unique in V1 |
| `business_name` | VARCHAR(150) | Merchant/business display name |
| `legal_name` | VARCHAR(150), nullable | Legal business name |
| `business_type` | VARCHAR(50), nullable | `individual,proprietorship,partnership,llp,pvt_ltd,public_ltd,other` |
| `gst_number` | VARCHAR(30), nullable | Unique GST number |
| `has_shop_license` | BOOLEAN, nullable | NULL = not answered, 0 = No, 1 = Yes |
| `has_fssai` | BOOLEAN, nullable | NULL = not answered, 0 = No, 1 = Yes |
| `contact_person_name` | VARCHAR(150), nullable | Primary contact person |
| `contact_email` | VARCHAR(255), nullable | Business contact email |
| `contact_mobile`, `alternate_mobile` | VARCHAR(20), nullable | Business contact phones |
| `verification_status` | VARCHAR(30) | `pending,submitted,approved,rejected,suspended` |
| `verified_at` | TIMESTAMP, nullable | Approval/review timestamp |
| `verified_by` | BIGINT UNSIGNED, nullable | References `users.id` |
| `rejection_reason` | TEXT, nullable | Merchant-visible rejection reason where applicable |
| `status` | VARCHAR(30) | `active,inactive,suspended,deleted` |
| `admin_note` | TEXT, nullable | Internal notes, never visible to merchants or customers |
| `created_by`, `updated_by`, `deleted_by` | BIGINT UNSIGNED, nullable | Audit user references |
| `created_at`, `updated_at`, `deleted_at` | TIMESTAMP, nullable | Lifecycle timestamps |

- **Unique constraints:** `uuid`, `user_id`, `gst_number`.
- **Indexes:** `business_name`, `verification_status`, `status`; unique constraints also provide indexes.
- **Foreign keys:** `user_id` -> `users.id` ON DELETE CASCADE; `verified_by`, `created_by`, `updated_by`, `deleted_by` -> `users.id` ON DELETE SET NULL.
- **Relationships:** One user may have exactly one merchant profile in V1. A merchant profile has many addresses.
- **Soft deletes:** Yes.

### `merchant_addresses`

**Purpose:** Stores merchant business and operational addresses.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED | Primary key |
| `uuid` | CHAR(36) | Unique public identifier exposed in URLs and APIs |
| `merchant_id` | BIGINT UNSIGNED | References `merchant_profiles.id` |
| `address_type` | VARCHAR(30) | `business,billing,pickup,return,warehouse` |
| `contact_name`, `contact_mobile` | VARCHAR(150/20), nullable | Address-level contact |
| `address_line_1` | VARCHAR(255) | Required address line |
| `address_line_2`, `landmark` | VARCHAR(255/150), nullable | Optional address details |
| `city_id`, `state_id`, `country_id` | MEDIUMINT UNSIGNED, nullable | Location master references |
| `pincode` | VARCHAR(20), nullable | Postal code |
| `latitude`, `longitude` | DECIMAL(10,7), nullable | Optional coordinates |
| `is_default` | BOOLEAN | Default address marker |
| `status` | VARCHAR(30) | `active,inactive,deleted` |
| `created_by`, `updated_by`, `deleted_by` | BIGINT UNSIGNED, nullable | Audit user references |
| `created_at`, `updated_at`, `deleted_at` | TIMESTAMP, nullable | Lifecycle timestamps |

- **Unique constraints:** `uuid`.
- **Indexes:** (`merchant_id`, `address_type`), `pincode`, (`merchant_id`, `is_default`), `status`; foreign keys provide lookup indexes for `merchant_id`, `city_id`, `state_id`, and `country_id`.
- **Foreign keys:** `merchant_id` -> `merchant_profiles.id` ON DELETE CASCADE; `city_id` -> `loc_cities.id`, `state_id` -> `loc_states.id`, `country_id` -> `loc_countries.id`, all ON DELETE SET NULL; audit references -> `users.id` ON DELETE SET NULL.
- **Soft deletes:** Yes.
- **Notes:** One default address is enforced later in application logic, not by a simple boolean unique constraint.

### `merchant_documents`

**Purpose:** Stores merchant KYC and business-document metadata.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED | Primary key |
| `uuid` | CHAR(36) | Unique public identifier exposed in URLs and APIs |
| `merchant_id` | BIGINT UNSIGNED | References `merchant_profiles.id` |
| `document_type` | VARCHAR(50) | `gst_certificate,pan_card,aadhaar,shop_license,bank_proof,other` |
| `document_number` | VARCHAR(100), nullable | Business/KYC document number |
| `file_path`, `original_file_name` | VARCHAR(255), nullable | Stored path and original upload name |
| `mime_type` | VARCHAR(100), nullable | File MIME type |
| `file_size` | BIGINT UNSIGNED, nullable | File size in bytes |
| `verification_status` | VARCHAR(30) | `pending,approved,rejected,expired` |
| `verified_at` | TIMESTAMP, nullable | Review timestamp |
| `verified_by` | BIGINT UNSIGNED, nullable | References `users.id` |
| `rejection_reason` | TEXT, nullable | Rejection reason |
| `expires_at` | DATE, nullable | Document expiry date |
| `status` | VARCHAR(30) | `active,inactive,deleted` |
| `created_by`, `updated_by`, `deleted_by` | BIGINT UNSIGNED, nullable | Audit user references |
| `created_at`, `updated_at`, `deleted_at` | TIMESTAMP, nullable | Lifecycle timestamps |

- **Unique constraints:** `uuid`.
- **Indexes:** (`merchant_id`, `document_type`), `document_number`, `verification_status`, `status`, `expires_at`; foreign keys also provide indexes.
- **Foreign keys:** `merchant_id` -> `merchant_profiles.id` ON DELETE CASCADE; `verified_by` and audit references -> `users.id` ON DELETE SET NULL.
- **Soft deletes:** Yes.
- **Notes:** No uniqueness is applied to `merchant_id`, `document_type`, or `document_number` because replacements and historical uploads are allowed.

### `merchant_bank_accounts`

**Purpose:** Stores merchant payout bank details.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED | Primary key |
| `uuid` | CHAR(36) | Unique public identifier exposed in URLs and APIs |
| `merchant_id` | BIGINT UNSIGNED | References `merchant_profiles.id` |
| `account_holder_name` | VARCHAR(150) | Bank account holder |
| `bank_name`, `branch_name` | VARCHAR(150), nullable | Bank and branch names |
| `account_number` | VARCHAR(255) | Must be encrypted in the model/service layer |
| `ifsc_code` | VARCHAR(20), nullable | IFSC code |
| `account_type` | VARCHAR(30), nullable | `savings,current,other` |
| `upi_id` | VARCHAR(100), nullable | Optional UPI identifier |
| `verification_status` | VARCHAR(30) | `pending,verified,rejected` |
| `is_default` | BOOLEAN | Default payout account marker |
| `verified_at` | TIMESTAMP, nullable | Review timestamp |
| `verified_by` | BIGINT UNSIGNED, nullable | References `users.id` |
| `rejection_reason` | TEXT, nullable | Rejection reason |
| `status` | VARCHAR(30) | `active,inactive,deleted` |
| `created_by`, `updated_by`, `deleted_by` | BIGINT UNSIGNED, nullable | Audit user references |
| `created_at`, `updated_at`, `deleted_at` | TIMESTAMP, nullable | Lifecycle timestamps |

- **Unique constraints:** `uuid`.
- **Indexes:** `ifsc_code`, `verification_status`, (`merchant_id`, `is_default`), `status`; foreign keys also provide indexes.
- **Foreign keys:** `merchant_id` -> `merchant_profiles.id` ON DELETE CASCADE; `verified_by` and audit references -> `users.id` ON DELETE SET NULL.
- **Soft deletes:** Yes.
- **Security notes:** `account_number` is intentionally `VARCHAR(255)` so encrypted values can be stored later. It must be encrypted through the model or service layer, never exposed in full through APIs or lists, displayed only as a masked value such as `********1234`, and excluded from audit-log `old_values` and `new_values`.
- **Notes:** One default bank account is enforced later in application logic, not by a simple boolean unique constraint. No index or unique constraint is created on `account_number`.

### `merchant_verifications`

**Purpose:** Tracks merchant verification review history.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED | Primary key |
| `uuid` | CHAR(36) | Unique public identifier exposed in URLs and APIs |
| `merchant_id` | BIGINT UNSIGNED | References `merchant_profiles.id` |
| `verification_type` | VARCHAR(50) | `profile,document,bank,address` |
| `related_type` | VARCHAR(255), nullable | Related model/table discriminator |
| `related_id` | BIGINT UNSIGNED, nullable | Related record id |
| `old_status` | VARCHAR(30), nullable | Previous verification status |
| `new_status` | VARCHAR(30) | New verification status |
| `remarks` | TEXT, nullable | Review remarks |
| `reviewed_by` | BIGINT UNSIGNED, nullable | References `users.id` |
| `reviewed_at` | TIMESTAMP, nullable | Review timestamp |
| `created_at`, `updated_at` | TIMESTAMP, nullable | Laravel timestamps |

- **Unique constraints:** `uuid`.
- **Indexes:** (`merchant_id`, `verification_type`), (`related_type`, `related_id`), `new_status`, `reviewed_at`; foreign keys also provide indexes for `merchant_id` and `reviewed_by`.
- **Foreign keys:** `merchant_id` -> `merchant_profiles.id` ON DELETE CASCADE; `reviewed_by` -> `users.id` ON DELETE SET NULL.
- **Soft deletes:** No; verification history is retained while its merchant exists.

### Merchant Relationship Summary

```text
users
+-- merchant_profiles
    +-- merchant_addresses
    +-- merchant_documents
    +-- merchant_bank_accounts
    +-- merchant_verifications
```

After merchant business data exists, user records should be deactivated or soft deleted instead of physically deleted except under an explicitly reviewed data-retention/legal workflow. Physical deletion of a user cascades the merchant profile and related merchant records by design.
