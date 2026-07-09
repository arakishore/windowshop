# User Roles and Permissions

## Identity Model

`users` is the canonical identity table. Administrator, merchant, customer, owner, and staff capabilities are domain concerns layered on top of that identity.

`admins.is_super_admin` identifies the platform owner/super-admin profile. It must not replace normal permission checks throughout the codebase.

## Authorization Principles

- Deny access by default.
- Authenticate identity first, then verify the domain profile.
- Authorize every protected action server-side.
- Use Laravel policies for model operations and gates for non-model capabilities.
- Never rely on hidden buttons or route prefixes as authorization.
- Scope merchant and customer queries to the authenticated tenant or owner.

## Planned Role Model

Roles group permissions; permissions describe capabilities. Suggested permission names follow:

```text
users.view
users.create
users.update
users.delete
shops.view
shops.manage
sessions.revoke
audit.view
```

Roles and permissions tables will be designed when the authorization module is implemented. Do not create merchant, customer, shop, or address schema prematurely.

## Super Admin Rules

A super admin may bypass selected platform-level checks through one centralized authorization hook. Sensitive operations must still be validated, logged, and protected by re-authentication where appropriate.

## Review Checklist

- Is the user authenticated in the correct context?
- Is the relevant profile active?
- Does the user have the required permission?
- Is the resource inside the user's allowed scope?
- Is the action security-sensitive and auditable?

## Role Catalogue

| Role | Scope | Typical permissions |
|---|---|---|
| Owner | Entire platform | Governance, billing, security policy, super-admin management |
| Super Admin | Entire platform | Platform operations, users, shops, sessions, audits |
| Admin | Assigned platform functions | Approved user, shop, and support operations |
| Merchant | Owned businesses | Shops, catalogue, inventory, orders, staff, reports |
| Merchant Staff | Assigned merchant/shop | Role-limited POS, order, catalogue, or inventory work |
| Customer | Own account | Profile, addresses, carts, orders, payments, reviews |
| Delivery Boy | Assigned deliveries | View assignment, update delivery state, proof of delivery |
| Support | Assigned support scope | Cases and limited customer/order assistance |
| Future Roles | Explicitly approved scope | Added through the same permission model |

Roles provide a baseline only. Effective authorization also considers account status, profile status, tenant/shop scope, ownership, and restrictions.

Owners and super admins remain distinct. Impersonation, refunds, permission changes, exports, and forced logout require dedicated permissions and audit events.
