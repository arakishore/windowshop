# WindowShop Development Roadmap

## Completed Foundation Phases

- Authentication foundation: `users` plus `auth_` authorization/session/verification tables.
- Location foundation: `loc_` region, subregion, country, state, and city master tables.
- System foundation: `system_` settings and audit-log tables.

## Merchant Module Phase 1: Database Foundation

Status: foundation schema created.

Scope:

- `merchant_profiles`
- `merchant_addresses`
- `merchant_documents`
- `merchant_bank_accounts`
- `merchant_verifications`

This phase creates only the merchant database foundation. Controllers, models, APIs, UI, CRUD workflows, merchant login, shop tables, product tables, seeders, and tests are deferred to later phases.

Merchant identity remains based on `users` and `auth_roles`; `merchant_profiles` stores business details only. After merchant business data exists, user records should be soft deleted or deactivated rather than physically deleted unless an approved data-retention/legal workflow requires hard deletion.

Merchant bank account numbers are stored in `account_number` as application-encrypted values, masked in all output, and excluded from audit-log `old_values` and `new_values`.

## Merchant Admin CRUD V1

Status: implemented.

Scope:

- Admin merchant list with search, status filter, and verification-status filter.
- Merchant create flow that creates a user and merchant profile in one transaction.
- Active `merchant` role assignment by role slug.
- Merchant edit flow for owner contact, profile fields, status, verification status, and admin notes.
- Merchant detail screen with linked user details and read-only child record counts.
- Soft-delete-only merchant delete action.
- Verification status history in `merchant_verifications`.

Deferred:

- Merchant child CRUD for addresses, documents, bank accounts, and verification records.
- Shop, product, customer, and order modules.
- Automated tests.
