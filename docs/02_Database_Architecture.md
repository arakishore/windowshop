# WindowShop Database Architecture

## Database Phase Roadmap

### Phase 3 (V1): System Foundation

- `system_setting_groups`
- `system_settings`
- `system_audit_logs`

Language, currency, and timezone are stored as values in `system_settings` during Version 1.

### Future Expansion

- `system_languages`
- `system_currencies`
- `system_timezones`

These master tables are intentionally deferred until WindowShop supports multiple operating countries or regions.

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
